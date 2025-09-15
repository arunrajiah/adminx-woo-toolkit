<?php
/**
 * Bulk Editor Class
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AdminX WooCommerce Bulk Editor
 */
class AdminX_Woo_Toolkit_Bulk_Editor {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_adminx_bulk_update_products', array($this, 'ajax_bulk_update_products'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'adminx-woo-toolkit',
            __('Bulk Editor', 'adminx-woo-toolkit'),
            __('Bulk Editor', 'adminx-woo-toolkit'),
            'manage_woocommerce',
            'adminx-bulk-editor',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Enqueue scripts
     */
    public function enqueue_scripts($hook) {
        if (strpos($hook, 'adminx-bulk-editor') === false) {
            return;
        }
        
        wp_enqueue_script('jquery');
        wp_enqueue_script('adminx-bulk-editor', ADMINX_WOO_TOOLKIT_PLUGIN_URL . 'assets/js/bulk-editor.js', array('jquery'), ADMINX_WOO_TOOLKIT_VERSION, true);
        wp_localize_script('adminx-bulk-editor', 'adminx_bulk_editor', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('adminx_bulk_editor_nonce'),
            'strings' => array(
                'confirm_update' => __('Are you sure you want to update these products?', 'adminx-woo-toolkit'),
                'updating' => __('Updating products...', 'adminx-woo-toolkit'),
                'success' => __('Products updated successfully!', 'adminx-woo-toolkit'),
                'error' => __('Error updating products.', 'adminx-woo-toolkit')
            )
        ));
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'templates/bulk-editor.php';
    }
    
    /**
     * AJAX bulk update products
     */
    public function ajax_bulk_update_products() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'], 'adminx_bulk_editor_nonce')) {
            wp_die(__('Security check failed', 'adminx-woo-toolkit'));
        }
        
        // Check permissions
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Insufficient permissions', 'adminx-woo-toolkit'));
        }
        
        $product_ids = isset($_POST['product_ids']) ? array_map('intval', $_POST['product_ids']) : array();
        $update_data = isset($_POST['update_data']) ? $_POST['update_data'] : array();
        
        if (empty($product_ids) || empty($update_data)) {
            wp_send_json_error(__('Invalid data provided', 'adminx-woo-toolkit'));
        }
        
        $updated_count = 0;
        $errors = array();
        
        foreach ($product_ids as $product_id) {
            try {
                $product = wc_get_product($product_id);
                if (!$product) {
                    continue;
                }
                
                // Update price
                if (isset($update_data['price']) && !empty($update_data['price'])) {
                    $price = floatval($update_data['price']);
                    if ($update_data['price_action'] === 'set') {
                        $product->set_regular_price($price);
                    } elseif ($update_data['price_action'] === 'increase') {
                        $current_price = floatval($product->get_regular_price());
                        $new_price = $current_price + $price;
                        $product->set_regular_price($new_price);
                    } elseif ($update_data['price_action'] === 'decrease') {
                        $current_price = floatval($product->get_regular_price());
                        $new_price = max(0, $current_price - $price);
                        $product->set_regular_price($new_price);
                    } elseif ($update_data['price_action'] === 'percentage_increase') {
                        $current_price = floatval($product->get_regular_price());
                        $new_price = $current_price * (1 + $price / 100);
                        $product->set_regular_price($new_price);
                    } elseif ($update_data['price_action'] === 'percentage_decrease') {
                        $current_price = floatval($product->get_regular_price());
                        $new_price = $current_price * (1 - $price / 100);
                        $product->set_regular_price(max(0, $new_price));
                    }
                }
                
                // Update stock
                if (isset($update_data['stock']) && !empty($update_data['stock'])) {
                    $stock = intval($update_data['stock']);
                    if ($update_data['stock_action'] === 'set') {
                        $product->set_stock_quantity($stock);
                    } elseif ($update_data['stock_action'] === 'increase') {
                        $current_stock = intval($product->get_stock_quantity());
                        $product->set_stock_quantity($current_stock + $stock);
                    } elseif ($update_data['stock_action'] === 'decrease') {
                        $current_stock = intval($product->get_stock_quantity());
                        $product->set_stock_quantity(max(0, $current_stock - $stock));
                    }
                }
                
                // Update stock status
                if (isset($update_data['stock_status']) && !empty($update_data['stock_status'])) {
                    $product->set_stock_status($update_data['stock_status']);
                }
                
                // Update categories
                if (isset($update_data['categories']) && !empty($update_data['categories'])) {
                    $categories = array_map('intval', $update_data['categories']);
                    if ($update_data['category_action'] === 'set') {
                        $product->set_category_ids($categories);
                    } elseif ($update_data['category_action'] === 'add') {
                        $current_categories = $product->get_category_ids();
                        $new_categories = array_unique(array_merge($current_categories, $categories));
                        $product->set_category_ids($new_categories);
                    } elseif ($update_data['category_action'] === 'remove') {
                        $current_categories = $product->get_category_ids();
                        $new_categories = array_diff($current_categories, $categories);
                        $product->set_category_ids($new_categories);
                    }
                }
                
                // Update tags
                if (isset($update_data['tags']) && !empty($update_data['tags'])) {
                    $tags = array_map('intval', $update_data['tags']);
                    if ($update_data['tag_action'] === 'set') {
                        $product->set_tag_ids($tags);
                    } elseif ($update_data['tag_action'] === 'add') {
                        $current_tags = $product->get_tag_ids();
                        $new_tags = array_unique(array_merge($current_tags, $tags));
                        $product->set_tag_ids($new_tags);
                    } elseif ($update_data['tag_action'] === 'remove') {
                        $current_tags = $product->get_tag_ids();
                        $new_tags = array_diff($current_tags, $tags);
                        $product->set_tag_ids($new_tags);
                    }
                }
                
                // Save product
                $product->save();
                $updated_count++;
                
                // Log activity
                do_action('adminx_woo_toolkit_log_activity', 'bulk_update_product', 'product', $product_id, array(), $update_data);
                
            } catch (Exception $e) {
                $errors[] = sprintf(__('Error updating product ID %d: %s', 'adminx-woo-toolkit'), $product_id, $e->getMessage());
            }
        }
        
        $response = array(
            'updated_count' => $updated_count,
            'total_count' => count($product_ids)
        );
        
        if (!empty($errors)) {
            $response['errors'] = $errors;
        }
        
        wp_send_json_success($response);
    }
    
    /**
     * Get products for bulk editing
     */
    public function get_products($args = array()) {
        $defaults = array(
            'status' => 'publish',
            'limit' => 50,
            'offset' => 0,
            'orderby' => 'date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $products = wc_get_products($args);
        $formatted_products = array();
        
        foreach ($products as $product) {
            $formatted_products[] = array(
                'id' => $product->get_id(),
                'name' => $product->get_name(),
                'sku' => $product->get_sku(),
                'price' => $product->get_regular_price(),
                'sale_price' => $product->get_sale_price(),
                'stock_quantity' => $product->get_stock_quantity(),
                'stock_status' => $product->get_stock_status(),
                'categories' => wp_get_post_terms($product->get_id(), 'product_cat', array('fields' => 'names')),
                'tags' => wp_get_post_terms($product->get_id(), 'product_tag', array('fields' => 'names')),
                'edit_url' => get_edit_post_link($product->get_id())
            );
        }
        
        return $formatted_products;
    }
}
