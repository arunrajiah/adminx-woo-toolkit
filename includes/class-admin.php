<?php
/**
 * Admin Class
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AdminX WooCommerce Toolkit Admin
 */
class AdminX_Woo_Toolkit_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . ADMINX_WOO_TOOLKIT_PLUGIN_BASENAME, array($this, 'add_plugin_action_links'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            __('AdminX WooCommerce Toolkit', 'adminx-woo-toolkit'),
            __('AdminX WooCommerce', 'adminx-woo-toolkit'),
            'manage_woocommerce',
            'adminx-woo-toolkit',
            array($this, 'admin_page'),
            'dashicons-admin-tools',
            56
        );
        
        add_submenu_page(
            'adminx-woo-toolkit',
            __('Dashboard', 'adminx-woo-toolkit'),
            __('Dashboard', 'adminx-woo-toolkit'),
            'manage_woocommerce',
            'adminx-woo-toolkit',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'adminx-woo-toolkit',
            __('Settings', 'adminx-woo-toolkit'),
            __('Settings', 'adminx-woo-toolkit'),
            'manage_options',
            'adminx-woo-toolkit-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'adminx-woo-toolkit') === false) {
            return;
        }
        
        wp_enqueue_style('adminx-woo-toolkit-admin', ADMINX_WOO_TOOLKIT_PLUGIN_URL . 'assets/css/admin.css', array(), ADMINX_WOO_TOOLKIT_VERSION);
        wp_enqueue_script('adminx-woo-toolkit-admin', ADMINX_WOO_TOOLKIT_PLUGIN_URL . 'assets/js/admin.js', array('jquery'), ADMINX_WOO_TOOLKIT_VERSION, true);
        
        wp_localize_script('adminx-woo-toolkit-admin', 'adminx_woo_toolkit', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adminx_woo_toolkit_nonce'),
            'strings' => array(
                'confirm_action' => __('Are you sure you want to perform this action?', 'adminx-woo-toolkit'),
                'processing' => __('Processing...', 'adminx-woo-toolkit'),
                'success' => __('Action completed successfully!', 'adminx-woo-toolkit'),
                'error' => __('An error occurred. Please try again.', 'adminx-woo-toolkit')
            )
        ));
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_bulk_editor_enabled');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_pdf_invoice_enabled');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_activity_log_enabled');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_auto_generate_invoice');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_pdf_company_name');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_pdf_company_address');
        register_setting('adminx_woo_toolkit_settings', 'adminx_woo_toolkit_log_retention_days');
    }
    
    /**
     * Add plugin action links
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=adminx-woo-toolkit-settings') . '">' . __('Settings', 'adminx-woo-toolkit') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'templates/admin-dashboard.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
        }
        
        include ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'templates/admin-settings.php';
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        check_admin_referer('adminx_woo_toolkit_settings');
        
        $settings = array(
            'adminx_woo_toolkit_bulk_editor_enabled',
            'adminx_woo_toolkit_pdf_invoice_enabled',
            'adminx_woo_toolkit_activity_log_enabled',
            'adminx_woo_toolkit_auto_generate_invoice',
            'adminx_woo_toolkit_pdf_company_name',
            'adminx_woo_toolkit_pdf_company_address',
            'adminx_woo_toolkit_log_retention_days'
        );
        
        foreach ($settings as $setting) {
            $value = isset($_POST[$setting]) ? sanitize_text_field($_POST[$setting]) : '';
            update_option($setting, $value);
        }
        
        add_settings_error('adminx_woo_toolkit_settings', 'settings_updated', __('Settings saved successfully!', 'adminx-woo-toolkit'), 'updated');
    }
    
    /**
     * Get dashboard stats
     */
    public function get_dashboard_stats() {
        global $wpdb;
        
        $stats = array();
        
        // Total orders today
        $today = date('Y-m-d');
        $stats['orders_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'shop_order' AND DATE(post_date) = %s",
            $today
        ));
        
        // Total revenue today
        $stats['revenue_today'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(meta_value) FROM {$wpdb->postmeta} pm 
             INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID 
             WHERE pm.meta_key = '_order_total' 
             AND p.post_type = 'shop_order' 
             AND DATE(p.post_date) = %s",
            $today
        ));
        
        // Total products
        $stats['total_products'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'product' AND post_status = 'publish'"
        );
        
        // Low stock products
        $stats['low_stock_products'] = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_stock'
             AND CAST(pm.meta_value AS UNSIGNED) <= 5"
        );
        
        // Recent activity count
        $activity_table = $wpdb->prefix . 'adminx_woo_activity_log';
        if ($wpdb->get_var("SHOW TABLES LIKE '$activity_table'") == $activity_table) {
            $stats['recent_activities'] = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $activity_table WHERE created_at >= %s",
                date('Y-m-d H:i:s', strtotime('-24 hours'))
            ));
        } else {
            $stats['recent_activities'] = 0;
        }
        
        return $stats;
    }
    
    /**
     * Get recent orders
     */
    public function get_recent_orders($limit = 5) {
        $orders = wc_get_orders(array(
            'limit' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array('wc-processing', 'wc-completed', 'wc-pending')
        ));
        
        $formatted_orders = array();
        
        foreach ($orders as $order) {
            $formatted_orders[] = array(
                'id' => $order->get_id(),
                'number' => $order->get_order_number(),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
                'customer' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'edit_url' => get_edit_post_link($order->get_id())
            );
        }
        
        return $formatted_orders;
    }
    
    /**
     * Get low stock products
     */
    public function get_low_stock_products($limit = 5) {
        global $wpdb;
        
        $products = $wpdb->get_results($wpdb->prepare(
            "SELECT p.ID, p.post_title, pm.meta_value as stock_quantity
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
             WHERE p.post_type = 'product'
             AND p.post_status = 'publish'
             AND pm.meta_key = '_stock'
             AND CAST(pm.meta_value AS UNSIGNED) <= 5
             ORDER BY CAST(pm.meta_value AS UNSIGNED) ASC
             LIMIT %d",
            $limit
        ));
        
        $formatted_products = array();
        
        foreach ($products as $product) {
            $wc_product = wc_get_product($product->ID);
            if ($wc_product) {
                $formatted_products[] = array(
                    'id' => $product->ID,
                    'name' => $product->post_title,
                    'stock_quantity' => $product->stock_quantity,
                    'price' => $wc_product->get_price(),
                    'edit_url' => get_edit_post_link($product->ID)
                );
            }
        }
        
        return $formatted_products;
    }
}