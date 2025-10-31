<?php
/**
 * Pathao API Plugin Wrapper
 *
 * This wrapper integrates with the Pathao Courier WordPress plugin,
 * providing a WooCommerce-compatible interface for ShipSync.
 *
 * @package ShipSync
 * @subpackage Couriers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ShipSync_Pathao_API_Wrapper
 *
 * Wraps the Pathao Courier plugin's functions to provide a consistent
 * WooCommerce-compatible API for ShipSync
 */
class ShipSync_Pathao_API_Wrapper {

    /**
     * Check if the Pathao Courier plugin is active
     *
     * @return bool
     */
    public static function is_plugin_active() {
        return function_exists('pt_hms_create_new_order') &&
               function_exists('pt_hms_get_token') &&
               function_exists('getPtOrderData');
    }

    /**
     * Check if the plugin is configured (has API credentials)
     *
     * @return bool
     */
    public static function is_configured() {
        if (!self::is_plugin_active()) {
            return false;
        }

        $options = get_option('pt_hms_settings', array());

        return !empty($options['client_id']) &&
               !empty($options['client_secret']) &&
               !empty($options['environment']);
    }

    /**
     * Get API credentials from the plugin's options
     *
     * @return array Array with 'client_id', 'client_secret', 'environment', and 'webhook_secret'
     */
    public static function get_credentials() {
        if (!self::is_plugin_active()) {
            return array();
        }

        $options = get_option('pt_hms_settings', array());

        return array(
            'client_id' => isset($options['client_id']) ? $options['client_id'] : '',
            'client_secret' => isset($options['client_secret']) ? $options['client_secret'] : '',
            'environment' => isset($options['environment']) ? $options['environment'] : 'live',
            'webhook_secret' => isset($options['webhook_secret']) ? $options['webhook_secret'] : ''
        );
    }

    /**
     * Get access token (handles refresh automatically)
     *
     * @return string|false Access token or false on error
     */
    public static function get_token($reset = false) {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_token($reset);
    }

    /**
     * Get order data formatted for Pathao API
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @return array|false Order data array or false on error
     */
    public static function get_order_data($order) {
        if (!self::is_plugin_active()) {
            return false;
        }

        // Get order object if ID was passed
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return false;
        }

        return getPtOrderData($order);
    }

    /**
     * Create an order using the Pathao plugin
     *
     * This is a WooCommerce-compatible wrapper around pt_hms_create_new_order()
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters (store_id, delivery_type, item_type, etc.)
     * @return array Response array with 'success', 'message', and optionally 'consignment'
     */
    public static function create_order($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not active', 'shipsync')
            );
        }

        if (!self::is_configured()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not configured. Please set up API credentials.', 'shipsync')
            );
        }

        // Get order object if ID was passed
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return array(
                'success' => false,
                'message' => __('Invalid order', 'shipsync')
            );
        }

        $order_id = $order->get_id();

        // Check if order already has a consignment ID
        $existing_consignment = get_post_meta($order_id, 'ptc_consignment_id', true);
        if (!empty($existing_consignment) && $existing_consignment !== '-') {
            return array(
                'success' => false,
                'message' => __('Order already has a Pathao consignment ID', 'shipsync')
            );
        }

        // Get order data from plugin function
        $order_data = self::get_order_data($order);
        if (!$order_data) {
            return array(
                'success' => false,
                'message' => __('Failed to get order data', 'shipsync')
            );
        }

        // Prepare order data for Pathao API
        $pathao_order_data = array(
            'merchant_order_id' => $order_id,
            'recipient_name' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
            'recipient_phone' => $order_data['billing']['phone'],
            'recipient_address' => self::format_address($order_data),
            'amount_to_collect' => $order->get_total(),
            'item_quantity' => isset($order_data['total_items']) ? $order_data['total_items'] : 1,
            'item_weight' => isset($order_data['total_weight']) ? $order_data['total_weight'] : 0.5,
            'item_description' => self::format_item_description($order_data),
        );

        // Add optional parameters
        if (isset($params['store_id'])) {
            $pathao_order_data['store_id'] = intval($params['store_id']);
        }

        if (isset($params['delivery_type'])) {
            $pathao_order_data['delivery_type'] = intval($params['delivery_type']);
        }

        if (isset($params['item_type'])) {
            $pathao_order_data['item_type'] = intval($params['item_type']);
        }

        if (isset($params['recipient_city'])) {
            $pathao_order_data['recipient_city'] = intval($params['recipient_city']);
        }

        if (isset($params['recipient_zone'])) {
            $pathao_order_data['recipient_zone'] = intval($params['recipient_zone']);
        }

        if (isset($params['recipient_area'])) {
            $pathao_order_data['recipient_area'] = intval($params['recipient_area']);
        }

        if (isset($params['special_instruction'])) {
            $pathao_order_data['special_instruction'] = sanitize_text_field($params['special_instruction']);
        }

        if (!empty($order->get_customer_note())) {
            $pathao_order_data['special_instruction'] = $order->get_customer_note();
        }

        // Call the plugin's function
        $response = pt_hms_create_new_order($pathao_order_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        // Check response
        if (isset($response['data']['consignment_id'])) {
            $consignment_id = $response['data']['consignment_id'];
            $delivery_fee = isset($response['data']['delivery_fee']) ? $response['data']['delivery_fee'] : 0;

            // Update order meta (plugin already does this, but we ensure it for ShipSync compatibility)
            $order->update_meta_data('_pathao_consignment_id', $consignment_id);
            $order->update_meta_data('_pathao_status', 'pending');
            $order->update_meta_data('_pathao_delivery_fee', $delivery_fee);
            $order->save();

            // Also update plugin's meta keys for compatibility
            update_post_meta($order_id, 'ptc_consignment_id', $consignment_id);
            update_post_meta($order_id, 'ptc_status', 'pending');
            if ($delivery_fee) {
                update_post_meta($order_id, 'ptc_delivery_fee', $delivery_fee);
            }

            // Add order note
            $order->add_order_note(sprintf(
                __('Pathao Consignment Created via Plugin - Consignment ID: %s, Delivery Fee: %s', 'shipsync'),
                $consignment_id,
                wc_price($delivery_fee)
            ));

            return array(
                'success' => true,
                'message' => __('Order sent to Pathao successfully', 'shipsync'),
                'consignment' => array(
                    'consignment_id' => $consignment_id,
                    'delivery_fee' => $delivery_fee
                ),
                'response' => $response
            );
        }

        // Handle error
        $error_message = isset($response['message'])
            ? $response['message']
            : __('Failed to create Pathao order', 'shipsync');

        if (isset($response['errors'])) {
            $error_message .= ': ' . wp_json_encode($response['errors']);
        }

        return array(
            'success' => false,
            'message' => $error_message,
            'response' => $response
        );
    }

    /**
     * Create bulk orders
     *
     * @param array $orders Array of WooCommerce order objects or IDs
     * @param array $default_params Default parameters for all orders
     * @return array Response with status and data
     */
    public static function create_bulk_orders($orders, $default_params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not active', 'shipsync')
            );
        }

        if (!self::is_configured()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not configured', 'shipsync')
            );
        }

        $pathao_orders = array();

        foreach ($orders as $order) {
            // Get order object if ID was passed
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }

            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }

            $order_id = $order->get_id();

            // Skip if already has consignment ID
            $existing_consignment = get_post_meta($order_id, 'ptc_consignment_id', true);
            if (!empty($existing_consignment) && $existing_consignment !== '-') {
                continue;
            }

            // Get order data
            $order_data = self::get_order_data($order);
            if (!$order_data) {
                continue;
            }

            // Prepare order data
            $pathao_order_data = array(
                'merchant_order_id' => $order_id,
                'recipient_name' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
                'recipient_phone' => $order_data['billing']['phone'],
                'recipient_address' => self::format_address($order_data),
                'amount_to_collect' => $order->get_total(),
                'item_quantity' => isset($order_data['total_items']) ? $order_data['total_items'] : 1,
                'item_weight' => isset($order_data['total_weight']) ? $order_data['total_weight'] : 0.5,
                'item_description' => self::format_item_description($order_data),
            );

            // Merge with default params
            $pathao_order_data = array_merge($default_params, $pathao_order_data);

            $pathao_orders[] = $pathao_order_data;
        }

        if (empty($pathao_orders)) {
            return array(
                'success' => false,
                'message' => __('No valid orders to process', 'shipsync')
            );
        }

        // Call bulk create function
        $response = pt_hms_create_new_order_bulk($pathao_orders);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        // Process response and update order meta
        if (isset($response['data']) && is_array($response['data'])) {
            foreach ($response['data'] as $index => $consignment_data) {
                if (isset($pathao_orders[$index]['merchant_order_id'])) {
                    $order_id = $pathao_orders[$index]['merchant_order_id'];
                    $order = wc_get_order($order_id);

                    if ($order && isset($consignment_data['consignment_id'])) {
                        $order->update_meta_data('_pathao_consignment_id', $consignment_data['consignment_id']);
                        $order->update_meta_data('_pathao_status', 'pending');
                        if (isset($consignment_data['delivery_fee'])) {
                            $order->update_meta_data('_pathao_delivery_fee', $consignment_data['delivery_fee']);
                        }
                        $order->save();

                        // Also update plugin's meta
                        update_post_meta($order_id, 'ptc_consignment_id', $consignment_data['consignment_id']);
                        update_post_meta($order_id, 'ptc_status', 'pending');
                    }
                }
            }
        }

        return array(
            'success' => true,
            'message' => sprintf(__('Bulk orders sent to Pathao: %d orders', 'shipsync'), count($pathao_orders)),
            'response' => $response
        );
    }

    /**
     * Get order status from order meta
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @return array Status data
     */
    public static function get_order_status($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return array(
                'success' => false,
                'message' => __('Invalid order', 'shipsync')
            );
        }

        $consignment_id = $order->get_meta('_pathao_consignment_id');
        if (empty($consignment_id)) {
            $consignment_id = get_post_meta($order->get_id(), 'ptc_consignment_id', true);
        }

        if (empty($consignment_id) || $consignment_id === '-') {
            return array(
                'success' => false,
                'message' => __('No consignment ID found for this order', 'shipsync')
            );
        }

        $status = $order->get_meta('_pathao_status');
        if (empty($status)) {
            $status = get_post_meta($order->get_id(), 'ptc_status', true);
        }

        $delivery_fee = $order->get_meta('_pathao_delivery_fee');
        if (empty($delivery_fee)) {
            $delivery_fee = get_post_meta($order->get_id(), 'ptc_delivery_fee', true);
        }

        return array(
            'success' => true,
            'consignment_id' => $consignment_id,
            'status' => $status ?: 'pending',
            'delivery_fee' => $delivery_fee ? floatval($delivery_fee) : 0
        );
    }

    /**
     * Update WooCommerce order status based on Pathao status
     *
     * Maps Pathao statuses to WooCommerce order statuses
     *
     * @param WC_Order $order WooCommerce order object
     * @param string $pathao_status Pathao order status
     * @return void
     */
    public static function update_wc_order_status($order, $pathao_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        // Map Pathao statuses to WooCommerce statuses
        $status_map = array(
            'delivered' => 'completed',
            'Delivered' => 'completed',
            'order.delivered' => 'completed',
            'partial-delivery' => 'processing',
            'Partial_Delivery' => 'processing',
            'order.partial-delivery' => 'processing',
            'returned' => 'refunded',
            'Return' => 'refunded',
            'order.returned' => 'refunded',
            'cancelled' => 'cancelled',
            'Pickup_Cancelled' => 'cancelled',
            'order.pickup-cancelled' => 'cancelled',
            'on-hold' => 'on-hold',
            'On_Hold' => 'on-hold',
            'order.on-hold' => 'on-hold',
            'pending' => 'processing',
            'Picked' => 'processing',
            'order.picked' => 'processing',
            'in-transit' => 'out-shipping', // Custom status if available
            'In_Transit' => 'out-shipping',
            'order.in-transit' => 'out-shipping',
        );

        // Default status if not in map
        $default_status = 'processing';

        // Get WooCommerce status
        $wc_status = isset($status_map[$pathao_status])
            ? $status_map[$pathao_status]
            : $default_status;

        // Update order status
        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('Pathao delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace(array('_', '-', '.'), ' ', $pathao_status))
            ));
        }

        // Store Pathao status in order meta
        $order->update_meta_data('_pathao_status', $pathao_status);
        $order->save();
    }

    /**
     * Format address from order data
     *
     * @param array $order_data Order data from getPtOrderData
     * @return string Formatted address
     */
    private static function format_address($order_data) {
        $address_parts = array();

        if (!empty($order_data['billing']['address_1'])) {
            $address_parts[] = $order_data['billing']['address_1'];
        }
        if (!empty($order_data['billing']['address_2'])) {
            $address_parts[] = $order_data['billing']['address_2'];
        }
        if (!empty($order_data['billing']['city'])) {
            $address_parts[] = $order_data['billing']['city'];
        }
        if (!empty($order_data['billing']['postcode'])) {
            $address_parts[] = $order_data['billing']['postcode'];
        }
        if (!empty($order_data['billing']['country'])) {
            $address_parts[] = $order_data['billing']['country'];
        }

        return implode(', ', $address_parts);
    }

    /**
     * Format item description from order data
     *
     * @param array $order_data Order data from getPtOrderData
     * @return string Formatted item description
     */
    private static function format_item_description($order_data) {
        if (empty($order_data['items']) || !is_array($order_data['items'])) {
            return __('Order items', 'shipsync');
        }

        $descriptions = array();
        foreach ($order_data['items'] as $item) {
            $name = isset($item['name']) ? $item['name'] : '';
            $quantity = isset($item['quantity']) ? $item['quantity'] : 1;
            $descriptions[] = sprintf('%s x%d', $name, $quantity);
        }

        return implode(', ', $descriptions);
    }

    /**
     * Get stores from Pathao
     *
     * @return array|false Stores array or false on error
     */
    public static function get_stores() {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_stores();
    }

    /**
     * Get cities from Pathao
     *
     * @return array|false Cities array or false on error
     */
    public static function get_cities() {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_cities();
    }

    /**
     * Get zones for a city
     *
     * @param int $city_id City ID
     * @return array|false Zones array or false on error
     */
    public static function get_zones($city_id) {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_zones($city_id);
    }

    /**
     * Get areas for a zone
     *
     * @param int $zone_id Zone ID
     * @return array|false Areas array or false on error
     */
    public static function get_areas($zone_id) {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_areas($zone_id);
    }
}

