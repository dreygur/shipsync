<?php
/**
 * Uninstall script for Order & Courier Manager
 *
 * This file is executed when the plugin is deleted from WordPress
 */

// If uninstall not called from WordPress, then exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Remove database tables
global $wpdb;

// Only remove couriers table now (WooCommerce handles orders)
// Use esc_sql for table name to prevent SQL injection
$couriers_table = $wpdb->prefix . 'ocm_couriers';
$wpdb->query("DROP TABLE IF EXISTS " . esc_sql($couriers_table));

// Remove plugin options
delete_option('ocm_version');
delete_option('ocm_settings');

// Remove any scheduled hooks
wp_clear_scheduled_hook('ocm_cleanup_expired_orders');

// Remove courier meta from WooCommerce orders
$args = array(
    'meta_key' => '_ocm_courier_id',
    'return' => 'ids',
    'limit' => -1
);

if (function_exists('wc_get_orders')) {
    $orders = wc_get_orders($args);
    foreach ($orders as $order_id) {
        delete_post_meta($order_id, '_ocm_courier_id');
    }
}
