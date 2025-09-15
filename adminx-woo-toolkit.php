<?php
/**
 * Plugin Name: AdminX WooCommerce Toolkit
 * Plugin URI: https://github.com/arunrajiah/adminx-plugins/adminx-woo-toolkit
 * Description: Comprehensive WooCommerce toolkit for bulk editing, PDF invoices, and order management. Includes bulk price & stock editor, local PDF invoice generator, and order & customer activity logging.
 * Version: 1.0.0
 * Author: AdminX
 * Author URI: https://adminx.dev
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: adminx-woo-toolkit
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ADMINX_WOO_TOOLKIT_VERSION', '1.0.0');
define('ADMINX_WOO_TOOLKIT_PLUGIN_FILE', __FILE__);
define('ADMINX_WOO_TOOLKIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ADMINX_WOO_TOOLKIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ADMINX_WOO_TOOLKIT_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Main AdminX WooCommerce Toolkit class
 */
class AdminX_Woo_Toolkit {
    
    /**
     * Single instance of the class
     */
    private static $instance = null;
    
    /**
     * Get single instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return;
        }
        
        // Load plugin files
        $this->load_includes();
        
        // Initialize components
        $this->init_hooks();
        
        // Load text domain
        load_plugin_textdomain('adminx-woo-toolkit', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    /**
     * Load include files
     */
    private function load_includes() {
        require_once ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'includes/class-bulk-editor.php';
        require_once ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'includes/class-pdf-invoice.php';
        require_once ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'includes/class-activity-logger.php';
        require_once ADMINX_WOO_TOOLKIT_PLUGIN_DIR . 'includes/class-admin.php';
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Initialize admin interface
        if (is_admin()) {
            new AdminX_Woo_Toolkit_Admin();
        }
        
        // Initialize components
        new AdminX_Woo_Toolkit_Bulk_Editor();
        new AdminX_Woo_Toolkit_PDF_Invoice();
        new AdminX_Woo_Toolkit_Activity_Logger();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Check WooCommerce dependency
        if (!class_exists('WooCommerce')) {
            deactivate_plugins(plugin_basename(__FILE__));
            wp_die(__('AdminX WooCommerce Toolkit requires WooCommerce to be installed and active.', 'adminx-woo-toolkit'));
        }
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up temporary files
        $this->cleanup_temp_files();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Activity log table
        $table_name = $wpdb->prefix . 'adminx_woo_activity_log';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(100) NOT NULL,
            object_type varchar(50) NOT NULL,
            object_id bigint(20) NOT NULL,
            old_values longtext,
            new_values longtext,
            ip_address varchar(45),
            user_agent text,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY action (action),
            KEY object_type (object_type),
            KEY created_at (created_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'adminx_woo_toolkit_bulk_editor_enabled' => 'yes',
            'adminx_woo_toolkit_pdf_invoice_enabled' => 'yes',
            'adminx_woo_toolkit_activity_log_enabled' => 'yes',
            'adminx_woo_toolkit_pdf_company_name' => get_bloginfo('name'),
            'adminx_woo_toolkit_pdf_company_address' => '',
            'adminx_woo_toolkit_log_retention_days' => 90
        );
        
        foreach ($defaults as $option => $value) {
            if (get_option($option) === false) {
                add_option($option, $value);
            }
        }
    }
    
    /**
     * Clean up temporary files
     */
    private function cleanup_temp_files() {
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/adminx-woo-toolkit/temp/';
        
        if (is_dir($temp_dir)) {
            $files = glob($temp_dir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
        }
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        echo '<div class="notice notice-error"><p>';
        echo __('AdminX WooCommerce Toolkit requires WooCommerce to be installed and active.', 'adminx-woo-toolkit');
        echo '</p></div>';
    }
}

// Initialize the plugin
AdminX_Woo_Toolkit::get_instance();
