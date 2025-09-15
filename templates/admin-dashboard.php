<?php
/**
 * Admin Dashboard Template
 *
 * @package AdminX_Woo_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

$admin = new AdminX_Woo_Toolkit_Admin();
$stats = $admin->get_dashboard_stats();
$recent_orders = $admin->get_recent_orders();
$low_stock_products = $admin->get_low_stock_products();
?>

<div class="wrap adminx-woo-toolkit">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="adminx-dashboard-stats">
        <div class="adminx-stat-card">
            <div class="adminx-stat-number"><?php echo esc_html($stats['orders_today']); ?></div>
            <div class="adminx-stat-label"><?php _e('Orders Today', 'adminx-woo-toolkit'); ?></div>
        </div>
        
        <div class="adminx-stat-card">
            <div class="adminx-stat-number"><?php echo wc_price($stats['revenue_today'] ?: 0); ?></div>
            <div class="adminx-stat-label"><?php _e('Revenue Today', 'adminx-woo-toolkit'); ?></div>
        </div>
        
        <div class="adminx-stat-card">
            <div class="adminx-stat-number"><?php echo esc_html($stats['total_products']); ?></div>
            <div class="adminx-stat-label"><?php _e('Total Products', 'adminx-woo-toolkit'); ?></div>
        </div>
        
        <div class="adminx-stat-card">
            <div class="adminx-stat-number"><?php echo esc_html($stats['low_stock_products']); ?></div>
            <div class="adminx-stat-label"><?php _e('Low Stock Products', 'adminx-woo-toolkit'); ?></div>
        </div>
        
        <div class="adminx-stat-card">
            <div class="adminx-stat-number"><?php echo esc_html($stats['recent_activities']); ?></div>
            <div class="adminx-stat-label"><?php _e('Recent Activities (24h)', 'adminx-woo-toolkit'); ?></div>
        </div>
    </div>
    
    <div class="adminx-dashboard-content">
        <div class="adminx-dashboard-section">
            <h2><?php _e('Quick Actions', 'adminx-woo-toolkit'); ?></h2>
            <p>
                <a href="<?php echo admin_url('admin.php?page=adminx-bulk-editor'); ?>" class="button button-primary"><?php _e('Bulk Edit Products', 'adminx-woo-toolkit'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=adminx-pdf-invoices'); ?>" class="button"><?php _e('Manage Invoices', 'adminx-woo-toolkit'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=adminx-activity-log'); ?>" class="button"><?php _e('View Activity Log', 'adminx-woo-toolkit'); ?></a>
                <a href="<?php echo admin_url('admin.php?page=adminx-woo-toolkit-settings'); ?>" class="button"><?php _e('Settings', 'adminx-woo-toolkit'); ?></a>
            </p>
        </div>
        
        <?php if (!empty($recent_orders)): ?>
        <div class="adminx-dashboard-section">
            <h2><?php _e('Recent Orders', 'adminx-woo-toolkit'); ?></h2>
            <table class="adminx-products-table">
                <thead>
                    <tr>
                        <th><?php _e('Order', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Customer', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Status', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Total', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Date', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Actions', 'adminx-woo-toolkit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_orders as $order): ?>
                    <tr>
                        <td>#<?php echo esc_html($order['number']); ?></td>
                        <td><?php echo esc_html($order['customer']); ?></td>
                        <td><?php echo esc_html(wc_get_order_status_name($order['status'])); ?></td>
                        <td><?php echo wc_price($order['total']); ?></td>
                        <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($order['date']))); ?></td>
                        <td><a href="<?php echo esc_url($order['edit_url']); ?>" class="button button-small"><?php _e('Edit', 'adminx-woo-toolkit'); ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($low_stock_products)): ?>
        <div class="adminx-dashboard-section">
            <h2><?php _e('Low Stock Products', 'adminx-woo-toolkit'); ?></h2>
            <table class="adminx-products-table">
                <thead>
                    <tr>
                        <th><?php _e('Product', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Stock', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Price', 'adminx-woo-toolkit'); ?></th>
                        <th><?php _e('Actions', 'adminx-woo-toolkit'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($low_stock_products as $product): ?>
                    <tr>
                        <td><?php echo esc_html($product['name']); ?></td>
                        <td><span style="color: #dc3232; font-weight: bold;"><?php echo esc_html($product['stock_quantity']); ?></span></td>
                        <td><?php echo wc_price($product['price']); ?></td>
                        <td><a href="<?php echo esc_url($product['edit_url']); ?>" class="button button-small"><?php _e('Edit', 'adminx-woo-toolkit'); ?></a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>