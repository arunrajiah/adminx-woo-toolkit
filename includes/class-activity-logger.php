<?php
/**
 * Activity Logger Class
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AdminX WooCommerce Activity Logger
 */
class AdminX_Woo_Toolkit_Activity_Logger {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('adminx_woo_toolkit_log_activity', array($this, 'log_activity'), 10, 5);
        add_action('woocommerce_new_order', array($this, 'log_new_order'));
        add_action('woocommerce_order_status_changed', array($this, 'log_order_status_change'), 10, 3);
        add_action('woocommerce_update_product', array($this, 'log_product_update'));
        add_action('woocommerce_new_customer', array($this, 'log_new_customer'));
        add_action('wp_login', array($this, 'log_user_login'), 10, 2);
        add_action('wp_logout', array($this, 'log_user_logout'));
        
        // Schedule cleanup
        if (!wp_next_scheduled('adminx_woo_toolkit_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'adminx_woo_toolkit_cleanup_logs');
        }
        add_action('adminx_woo_toolkit_cleanup_logs', array($this, 'cleanup_old_logs'));
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'adminx-woo-toolkit',
            __('Activity Log', 'adminx-woo-toolkit'),
            __('Activity Log', 'adminx-woo-toolkit'),
            'manage_woocommerce',
            'adminx-activity-log',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        include ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'templates/activity-log.php';
    }
    
    /**
     * Log activity
     */
    public function log_activity($action, $object_type, $object_id, $old_values = array(), $new_values = array()) {
        global $wpdb;
        
        $user_id = get_current_user_id();
        $ip_address = $this->get_user_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        $table_name = $wpdb->prefix . 'adminx_woo_activity_log';
        
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'action' => $action,
                'object_type' => $object_type,
                'object_id' => $object_id,
                'old_values' => maybe_serialize($old_values),
                'new_values' => maybe_serialize($new_values),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Log new order
     */
    public function log_new_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $this->log_activity(
            'create_order',
            'order',
            $order_id,
            array(),
            array(
                'order_number' => $order->get_order_number(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
                'customer_id' => $order->get_customer_id()
            )
        );
    }
    
    /**
     * Log order status change
     */
    public function log_order_status_change($order_id, $old_status, $new_status) {
        $this->log_activity(
            'update_order_status',
            'order',
            $order_id,
            array('status' => $old_status),
            array('status' => $new_status)
        );
    }
    
    /**
     * Log product update
     */
    public function log_product_update($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) {
            return;
        }
        
        $this->log_activity(
            'update_product',
            'product',
            $product_id,
            array(),
            array(
                'name' => $product->get_name(),
                'price' => $product->get_price(),
                'stock_quantity' => $product->get_stock_quantity()
            )
        );
    }
    
    /**
     * Log new customer
     */
    public function log_new_customer($customer_id) {
        $customer = new WC_Customer($customer_id);
        
        $this->log_activity(
            'create_customer',
            'customer',
            $customer_id,
            array(),
            array(
                'email' => $customer->get_email(),
                'first_name' => $customer->get_first_name(),
                'last_name' => $customer->get_last_name()
            )
        );
    }
    
    /**
     * Log user login
     */
    public function log_user_login($user_login, $user) {
        $this->log_activity(
            'user_login',
            'user',
            $user->ID,
            array(),
            array(
                'username' => $user_login,
                'email' => $user->user_email
            )
        );
    }
    
    /**
     * Log user logout
     */
    public function log_user_logout() {
        $user_id = get_current_user_id();
        if ($user_id) {
            $this->log_activity(
                'user_logout',
                'user',
                $user_id,
                array(),
                array()
            );
        }
    }
    
    /**
     * Get activity logs
     */
    public function get_activity_logs($args = array()) {
        global $wpdb;
        
        $defaults = array(
            'limit' => 50,
            'offset' => 0,
            'action' => '',
            'object_type' => '',
            'user_id' => 0,
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $table_name = $wpdb->prefix . 'adminx_woo_activity_log';
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($args['action'])) {
            $where_clauses[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (!empty($args['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $where_values[] = $args['object_type'];
        }
        
        if (!empty($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $query = "SELECT * FROM $table_name WHERE $where_clause ORDER BY $orderby LIMIT %d OFFSET %d";
        $where_values[] = $args['limit'];
        $where_values[] = $args['offset'];
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        $results = $wpdb->get_results($query);
        
        // Format results
        foreach ($results as &$result) {
            $result->old_values = maybe_unserialize($result->old_values);
            $result->new_values = maybe_unserialize($result->new_values);
            $result->user_name = get_userdata($result->user_id) ? get_userdata($result->user_id)->display_name : __('Unknown User', 'adminx-woo-toolkit');
            $result->formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($result->created_at));
        }
        
        return $results;
    }
    
    /**
     * Get activity log count
     */
    public function get_activity_log_count($args = array()) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'adminx_woo_activity_log';
        
        $where_clauses = array('1=1');
        $where_values = array();
        
        if (!empty($args['action'])) {
            $where_clauses[] = 'action = %s';
            $where_values[] = $args['action'];
        }
        
        if (!empty($args['object_type'])) {
            $where_clauses[] = 'object_type = %s';
            $where_values[] = $args['object_type'];
        }
        
        if (!empty($args['user_id'])) {
            $where_clauses[] = 'user_id = %d';
            $where_values[] = $args['user_id'];
        }
        
        if (!empty($args['date_from'])) {
            $where_clauses[] = 'created_at >= %s';
            $where_values[] = $args['date_from'] . ' 00:00:00';
        }
        
        if (!empty($args['date_to'])) {
            $where_clauses[] = 'created_at <= %s';
            $where_values[] = $args['date_to'] . ' 23:59:59';
        }
        
        $where_clause = implode(' AND ', $where_clauses);
        
        $query = "SELECT COUNT(*) FROM $table_name WHERE $where_clause";
        
        if (!empty($where_values)) {
            $query = $wpdb->prepare($query, $where_values);
        }
        
        return $wpdb->get_var($query);
    }
    
    /**
     * Clean up old logs
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $retention_days = get_option('adminx_woo_toolkit_log_retention_days', 90);
        $cutoff_date = date('Y-m-d H:i:s', strtotime('-' . $retention_days . ' days'));
        
        $table_name = $wpdb->prefix . 'adminx_woo_activity_log';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE created_at < %s",
            $cutoff_date
        ));
    }
    
    /**
     * Get user IP address
     */
    private function get_user_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Export logs to CSV
     */
    public function export_logs_csv($args = array()) {
        $logs = $this->get_activity_logs(array_merge($args, array('limit' => -1)));
        
        $filename = 'adminx-activity-logs-' . date('Y-m-d') . '.csv';
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, array(
            'Date',
            'User',
            'Action',
            'Object Type',
            'Object ID',
            'IP Address',
            'Details'
        ));
        
        // CSV data
        foreach ($logs as $log) {
            $details = '';
            if (!empty($log->new_values)) {
                $details = json_encode($log->new_values);
            }
            
            fputcsv($output, array(
                $log->formatted_date,
                $log->user_name,
                $log->action,
                $log->object_type,
                $log->object_id,
                $log->ip_address,
                $details
            ));
        }
        
        fclose($output);
        exit;
    }
}