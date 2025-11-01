<?php
/**
 * Order Service
 * Handles order-related business logic
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Order_Service {

    /**
     * @var ShipSync_Database
     */
    private $database;

    /**
     * Constructor
     */
    public function __construct() {
        $this->database = ShipSync_Database::instance();
    }

    /**
     * Get filtered orders
     */
    public function get_filtered_orders($args = array()) {
        $defaults = array(
            'status_filter' => 'all',
            'search' => '',
            'paged' => 1,
            'per_page' => 10,
            'orderby' => 'date',
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'limit' => $args['per_page'],
            'offset' => ($args['paged'] - 1) * $args['per_page'],
            'orderby' => $args['orderby'],
            'order' => $args['order'],
            'return' => 'objects'
        );

        if ($args['status_filter'] !== 'all') {
            $query_args['status'] = $args['status_filter'];
        }

        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        return wc_get_orders($query_args);
    }

    /**
     * Get total filtered orders count
     */
    public function get_filtered_orders_count($args = array()) {
        $defaults = array(
            'status_filter' => 'all',
            'search' => ''
        );

        $args = wp_parse_args($args, $defaults);

        $query_args = array(
            'limit' => -1,
            'return' => 'ids'
        );

        if ($args['status_filter'] !== 'all') {
            $query_args['status'] = $args['status_filter'];
        }

        if (!empty($args['search'])) {
            $query_args['s'] = $args['search'];
        }

        return count(wc_get_orders($query_args));
    }

    /**
     * Get order statistics
     */
    public function get_statistics() {
        return ShipSync_Helper::get_order_stats();
    }

    /**
     * Get order tracking information
     */
    public function get_order_tracking($order) {
        if (!$order instanceof WC_Order) {
            return null;
        }

        return array(
            'tracking_data' => $this->database->get_tracking_code_from_order($order),
            'status_data' => $this->database->get_delivery_status_from_order($order)
        );
    }

    /**
     * Format order for display
     */
    public function format_order_for_display($order) {
        if (!$order instanceof WC_Order) {
            return null;
        }

        $tracking_info = $this->get_order_tracking($order);

        return array(
            'id' => $order->get_id(),
            'order_number' => $order->get_order_number(),
            'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'customer_phone' => $order->get_billing_phone(),
            'customer_email' => $order->get_billing_email(),
            'total' => $order->get_total(),
            'status' => $order->get_status(),
            'date' => $order->get_date_created(),
            'tracking_code' => $tracking_info['tracking_data']['tracking_code'] ?? null,
            'courier_service' => $tracking_info['tracking_data']['courier_service'] ?? null,
            'delivery_status' => $tracking_info['status_data']['status'] ?? null
        );
    }
}

