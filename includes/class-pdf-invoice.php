<?php
/**
 * PDF Invoice Class
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AdminX WooCommerce PDF Invoice Generator
 */
class AdminX_Woo_Toolkit_PDF_Invoice {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('woocommerce_order_status_completed', array($this, 'auto_generate_invoice'));
        add_action('add_meta_boxes', array($this, 'add_order_meta_box'));
        add_action('wp_ajax_adminx_generate_invoice', array($this, 'ajax_generate_invoice'));
        add_action('wp_ajax_adminx_download_invoice', array($this, 'ajax_download_invoice'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'adminx-woo-toolkit',
            __('PDF Invoices', 'adminx-woo-toolkit'),
            __('PDF Invoices', 'adminx-woo-toolkit'),
            'manage_woocommerce',
            'adminx-pdf-invoices',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'templates/pdf-invoices.php';
    }
    
    /**
     * Add order meta box
     */
    public function add_order_meta_box() {
        add_meta_box(
            'adminx-invoice-actions',
            __('AdminX Invoice Actions', 'adminx-woo-toolkit'),
            array($this, 'order_meta_box_content'),
            'shop_order',
            'side',
            'high'
        );
    }
    
    /**
     * Order meta box content
     */
    public function order_meta_box_content($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }
        
        $invoice_path = $this->get_invoice_path($order->get_id());
        $invoice_exists = file_exists($invoice_path);
        
        echo '<div class="adminx-invoice-actions">';
        
        if ($invoice_exists) {
            echo '<p><strong>' . __('Invoice Status:', 'adminx-woo-toolkit') . '</strong> ' . __('Generated', 'adminx-woo-toolkit') . '</p>';
            echo '<p><a href="#" class="button button-primary adminx-download-invoice" data-order-id="' . $order->get_id() . '">' . __('Download Invoice', 'adminx-woo-toolkit') . '</a></p>';
            echo '<p><a href="#" class="button adminx-regenerate-invoice" data-order-id="' . $order->get_id() . '">' . __('Regenerate Invoice', 'adminx-woo-toolkit') . '</a></p>';
        } else {
            echo '<p><strong>' . __('Invoice Status:', 'adminx-woo-toolkit') . '</strong> ' . __('Not Generated', 'adminx-woo-toolkit') . '</p>';
            echo '<p><a href="#" class="button button-primary adminx-generate-invoice" data-order-id="' . $order->get_id() . '">' . __('Generate Invoice', 'adminx-woo-toolkit') . '</a></p>';
        }
        
        echo '</div>';
        
        // Add JavaScript
        ?>
        <script>
        jQuery(document).ready(function($) {
            $('.adminx-generate-invoice, .adminx-regenerate-invoice').on('click', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                var button = $(this);
                
                button.prop('disabled', true).text('<?php echo esc_js(__('Generating...', 'adminx-woo-toolkit')); ?>');
                
                $.post(ajaxurl, {
                    action: 'adminx_generate_invoice',
                    order_id: orderId,
                    nonce: '<?php echo wp_create_nonce('adminx_invoice_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data || '<?php echo esc_js(__('Error generating invoice', 'adminx-woo-toolkit')); ?>');
                        button.prop('disabled', false).text('<?php echo esc_js(__('Generate Invoice', 'adminx-woo-toolkit')); ?>');
                    }
                });
            });
            
            $('.adminx-download-invoice').on('click', function(e) {
                e.preventDefault();
                var orderId = $(this).data('order-id');
                
                window.open(ajaxurl + '?action=adminx_download_invoice&order_id=' + orderId + '&nonce=<?php echo wp_create_nonce('adminx_invoice_download_nonce'); ?>', '_blank');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Auto generate invoice on order completion
     */
    public function auto_generate_invoice($order_id) {
        if (get_option('adminx_woo_toolkit_auto_generate_invoice', 'yes') === 'yes') {
            $this->generate_invoice($order_id);
        }
    }
    
    /**
     * AJAX generate invoice
     */
    public function ajax_generate_invoice() {
        if (!wp_verify_nonce($_POST['nonce'], 'adminx_invoice_nonce')) {
            wp_send_json_error(__('Security check failed', 'adminx-woo-toolkit'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(__('Insufficient permissions', 'adminx-woo-toolkit'));
        }
        
        $order_id = intval($_POST['order_id']);
        $result = $this->generate_invoice($order_id);
        
        if ($result) {
            wp_send_json_success(__('Invoice generated successfully', 'adminx-woo-toolkit'));
        } else {
            wp_send_json_error(__('Failed to generate invoice', 'adminx-woo-toolkit'));
        }
    }
    
    /**
     * AJAX download invoice
     */
    public function ajax_download_invoice() {
        if (!wp_verify_nonce($_GET['nonce'], 'adminx_invoice_download_nonce')) {
            wp_die(__('Security check failed', 'adminx-woo-toolkit'));
        }
        
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'adminx-woo-toolkit'));
        }
        
        $order_id = intval($_GET['order_id']);
        $this->download_invoice($order_id);
    }
    
    /**
     * Generate invoice PDF
     */
    public function generate_invoice($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return false;
        }
        
        // Create upload directory if it doesn't exist
        $upload_dir = wp_upload_dir();
        $invoice_dir = $upload_dir['basedir'] . '/adminx-woo-toolkit/invoices/';
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }
        
        // Generate invoice content
        $invoice_content = $this->get_invoice_content($order);
        
        // Use TCPDF or dompdf for PDF generation
        if (class_exists('TCPDF')) {
            return $this->generate_pdf_tcpdf($order_id, $invoice_content);
        } else {
            return $this->generate_pdf_simple($order_id, $invoice_content);
        }
    }
    
    /**
     * Generate PDF using TCPDF
     */
    private function generate_pdf_tcpdf($order_id, $content) {
        require_once(ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'includes/tcpdf/tcpdf.php');
        
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
        
        // Set document information
        $pdf->SetCreator('AdminX WooCommerce Toolkit');
        $pdf->SetAuthor(get_bloginfo('name'));
        $pdf->SetTitle('Invoice #' . $order_id);
        
        // Set margins
        $pdf->SetMargins(15, 27, 15);
        $pdf->SetHeaderMargin(5);
        $pdf->SetFooterMargin(10);
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak(TRUE, 25);
        
        // Add a page
        $pdf->AddPage();
        
        // Set font
        $pdf->SetFont('helvetica', '', 10);
        
        // Add content
        $pdf->writeHTML($content, true, false, true, false, '');
        
        // Save PDF
        $invoice_path = $this->get_invoice_path($order_id);
        $pdf->Output($invoice_path, 'F');
        
        return file_exists($invoice_path);
    }
    
    /**
     * Generate simple PDF (fallback)
     */
    private function generate_pdf_simple($order_id, $content) {
        // Simple HTML to PDF conversion (basic implementation)
        $invoice_path = $this->get_invoice_path($order_id);
        
        // For now, save as HTML file (can be enhanced with proper PDF library)
        $html_content = '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Invoice #' . $order_id . '</title></head><body>' . $content . '</body></html>';
        
        return file_put_contents($invoice_path, $html_content) !== false;
    }
    
    /**
     * Get invoice content HTML
     */
    private function get_invoice_content($order) {
        $company_name = get_option('adminx_woo_toolkit_pdf_company_name', get_bloginfo('name'));
        $company_address = get_option('adminx_woo_toolkit_pdf_company_address', '');
        
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; font-size: 12px;">
            <div style="text-align: center; margin-bottom: 30px;">
                <h1 style="color: #333; margin: 0;"><?php echo esc_html($company_name); ?></h1>
                <?php if ($company_address): ?>
                    <p style="margin: 5px 0;"><?php echo nl2br(esc_html($company_address)); ?></p>
                <?php endif; ?>
            </div>
            
            <div style="margin-bottom: 30px;">
                <h2 style="color: #333; border-bottom: 2px solid #333; padding-bottom: 5px;">Invoice #<?php echo $order->get_order_number(); ?></h2>
            </div>
            
            <div style="margin-bottom: 30px;">
                <div style="float: left; width: 48%;">
                    <h3 style="color: #333; margin-bottom: 10px;">Bill To:</h3>
                    <p style="margin: 0;">
                        <?php echo $order->get_formatted_billing_address(); ?>
                    </p>
                    <?php if ($order->get_billing_email()): ?>
                        <p style="margin: 5px 0 0 0;">Email: <?php echo $order->get_billing_email(); ?></p>
                    <?php endif; ?>
                    <?php if ($order->get_billing_phone()): ?>
                        <p style="margin: 5px 0 0 0;">Phone: <?php echo $order->get_billing_phone(); ?></p>
                    <?php endif; ?>
                </div>
                
                <div style="float: right; width: 48%;">
                    <h3 style="color: #333; margin-bottom: 10px;">Invoice Details:</h3>
                    <p style="margin: 0;"><strong>Order Date:</strong> <?php echo $order->get_date_created()->format('F j, Y'); ?></p>
                    <p style="margin: 5px 0 0 0;"><strong>Payment Method:</strong> <?php echo $order->get_payment_method_title(); ?></p>
                    <p style="margin: 5px 0 0 0;"><strong>Order Status:</strong> <?php echo wc_get_order_status_name($order->get_status()); ?></p>
                </div>
                
                <div style="clear: both;"></div>
            </div>
            
            <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
                <thead>
                    <tr style="background-color: #f8f8f8;">
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: left;">Product</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: center;">Qty</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Price</th>
                        <th style="border: 1px solid #ddd; padding: 10px; text-align: right;">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($order->get_items() as $item): ?>
                        <tr>
                            <td style="border: 1px solid #ddd; padding: 10px;"><?php echo $item->get_name(); ?></td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: center;"><?php echo $item->get_quantity(); ?></td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;"><?php echo wc_price($item->get_total() / $item->get_quantity()); ?></td>
                            <td style="border: 1px solid #ddd; padding: 10px; text-align: right;"><?php echo wc_price($item->get_total()); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            
            <div style="float: right; width: 300px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px; text-align: right;"><strong>Subtotal:</strong></td>
                        <td style="padding: 5px; text-align: right;"><?php echo wc_price($order->get_subtotal()); ?></td>
                    </tr>
                    <?php if ($order->get_total_tax() > 0): ?>
                        <tr>
                            <td style="padding: 5px; text-align: right;"><strong>Tax:</strong></td>
                            <td style="padding: 5px; text-align: right;"><?php echo wc_price($order->get_total_tax()); ?></td>
                        </tr>
                    <?php endif; ?>
                    <?php if ($order->get_shipping_total() > 0): ?>
                        <tr>
                            <td style="padding: 5px; text-align: right;"><strong>Shipping:</strong></td>
                            <td style="padding: 5px; text-align: right;"><?php echo wc_price($order->get_shipping_total()); ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr style="border-top: 2px solid #333;">
                        <td style="padding: 10px 5px 5px 5px; text-align: right;"><strong>Total:</strong></td>
                        <td style="padding: 10px 5px 5px 5px; text-align: right;"><strong><?php echo wc_price($order->get_total()); ?></strong></td>
                    </tr>
                </table>
            </div>
            
            <div style="clear: both;"></div>
            
            <?php if ($order->get_customer_note()): ?>
                <div style="margin-top: 30px;">
                    <h3 style="color: #333;">Customer Note:</h3>
                    <p><?php echo nl2br(esc_html($order->get_customer_note())); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Download invoice
     */
    public function download_invoice($order_id) {
        $invoice_path = $this->get_invoice_path($order_id);
        
        if (!file_exists($invoice_path)) {
            wp_die(__('Invoice not found', 'adminx-woo-toolkit'));
        }
        
        $filename = 'invoice-' . $order_id . '.pdf';
        
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($invoice_path));
        
        readfile($invoice_path);
        exit;
    }
    
    /**
     * Get invoice file path
     */
    private function get_invoice_path($order_id) {
        $upload_dir = wp_upload_dir();
        return $upload_dir['basedir'] . '/adminx-woo-toolkit/invoices/invoice-' . $order_id . '.pdf';
    }
}