<?php
/**
 * RedX API Plugin Wrapper
 *
 * This wrapper integrates with the RedX for WooCommerce WordPress plugin,
 * providing a WooCommerce-compatible interface for ShipSync.
 *
 * @package ShipSync
 * @subpackage Couriers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ShipSync_RedX_API_Wrapper
 *
 * Wraps the RedX for WooCommerce plugin's functions to provide a consistent
 * WooCommerce-compatible API for ShipSync
 */
class ShipSync_RedX_API_Wrapper {

    /**
     * Check if the RedX for WooCommerce plugin is active
     *
     * @return bool
     */
    public static function is_plugin_active() {
        return class_exists('ShopUP\RedxTrackingForWoocommerce\Init') &&
               class_exists('ShopUP\RedxTrackingForWoocommerce\Services\RedxOrderActionsService');
    }

    /**
     * Check if the plugin is configured (has API credentials and is enabled)
     *
     * @return bool
     */
    public static function is_configured() {
        if (!self::is_plugin_active()) {
            return false;
        }

        $enabled = get_option('redex_tracking_enabled', 'no');
        $api_key = get_option('redex_tracking_api_key', '');

        return ($enabled === 'yes' && !empty($api_key));
    }

    /**
     * Get API credentials from the plugin's options
     *
     * @return array Array with 'api_key' and 'enabled' keys
     */
    public static function get_credentials() {
        if (!self::is_plugin_active()) {
            return array();
        }

        return array(
            'api_key' => get_option('redex_tracking_api_key', ''),
            'enabled' => get_option('redex_tracking_enabled', 'no') === 'yes'
        );
    }

    /**
     * Get API base URL
     *
     * @return string API base URL
     */
    public static function get_api_url() {
        return 'https://openapi.redx.com.bd/v1.0.0-beta/wordpress/parcel';
    }

    /**
     * Create an order using the RedX plugin's API
     *
     * This is a WooCommerce-compatible wrapper around the plugin's API calls
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters (parcel_weight, instruction, etc.)
     * @return array Response array with 'success', 'message', and optionally 'tracking_id'
     */
    public static function create_order($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not active', 'shipsync')
            );
        }

        if (!self::is_configured()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not configured. Please set up API credentials and enable tracking.', 'shipsync')
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

        // Check if order already has a tracking ID
        $existing_tracking_id = get_post_meta($order_id, '_redx_tracking_id', true);
        if (!empty($existing_tracking_id)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Order already has a RedX tracking ID: %s', 'shipsync'), $existing_tracking_id)
            );
        }

        // Get API key
        $api_key = get_option('redex_tracking_api_key');
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('RedX API key is not configured', 'shipsync')
            );
        }

            // Prepare order data
            $shipping_phone = $order->get_meta('_shipping_phone');
            $customer_phone = !empty($shipping_phone) ? $shipping_phone : $order->get_billing_phone();

        $request_data = array(
            'customer_name' => sanitize_text_field($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
            'customer_phone' => sanitize_text_field($customer_phone),
            'customer_address' => sanitize_text_field($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
            'merchant_invoice_id' => sanitize_text_field($order->get_order_number()),
            'cash_collection_amount' => floatval($order->get_total()),
            'parcel_weight' => isset($params['parcel_weight']) ? intval($params['parcel_weight']) : (intval(get_post_meta($order_id, '_parcel_weight', true)) ?: 1),
            'instruction' => isset($params['instruction']) ? sanitize_text_field($params['instruction']) : sanitize_text_field($order->get_customer_note()),
            'value' => floatval($order->get_total()),
        );

        // API endpoint and headers
        $api_url = self::get_api_url();
        $headers = array(
            'Content-Type' => 'application/json',
            'API-ACCESS-TOKEN' => 'Bearer ' . $api_key,
        );

        // Make the API request
        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => $headers,
                'body' => wp_json_encode($request_data),
                'timeout' => 30,
            )
        );

        // Handle the response
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => __('API request failed: ', 'shipsync') . $response->get_error_message()
            );
        }

        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        $response_data = json_decode($response_body, true);

        if ($response_code < 200 || $response_code >= 300) {
            $error_message = isset($response_data['message'])
                ? $response_data['message']
                : __('API error: ', 'shipsync') . $response_body;

            return array(
                'success' => false,
                'message' => $error_message,
                'response' => $response_data
            );
        }

        if (empty($response_data['tracking_id'])) {
            return array(
                'success' => false,
                'message' => __('API response missing tracking ID', 'shipsync'),
                'response' => $response_data
            );
        }

        // Success - save tracking ID
        $tracking_id = $response_data['tracking_id'];

        // Update order meta
        $order->update_meta_data('_redx_tracking_id', $tracking_id);
        $order->save();

        // Also ensure post meta is set (plugin uses post_meta)
        update_post_meta($order_id, '_redx_tracking_id', $tracking_id);

        // Add order note
        $order->add_order_note(sprintf(
            __('RedX Parcel Created via Plugin - Tracking ID: %s', 'shipsync'),
            $tracking_id
        ));

        return array(
            'success' => true,
            'message' => __('Order sent to RedX successfully', 'shipsync'),
            'tracking_id' => $tracking_id,
            'response' => $response_data
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
                'message' => __('RedX for WooCommerce plugin is not active', 'shipsync')
            );
        }

        if (!self::is_configured()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not configured', 'shipsync')
            );
        }

        // Get API key
        $api_key = get_option('redex_tracking_api_key');
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('RedX API key is not configured', 'shipsync')
            );
        }

        $api_url = self::get_api_url();
        $headers = array(
            'Content-Type' => 'application/json',
            'API-ACCESS-TOKEN' => 'Bearer ' . $api_key,
        );

        $success_count = 0;
        $failure_count = 0;
        $results = array();

        // Process each order
        foreach ($orders as $order) {
            // Get order object if ID was passed
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }

            if (!$order || !is_a($order, 'WC_Order')) {
                $failure_count++;
                continue;
            }

            $order_id = $order->get_id();

            // Skip if already has tracking ID
            $existing_tracking_id = get_post_meta($order_id, '_redx_tracking_id', true);
            if (!empty($existing_tracking_id)) {
                continue;
            }

            // Prepare order data
            $shipping_phone = $order->get_meta('_shipping_phone');
            $customer_phone = !empty($shipping_phone) ? $shipping_phone : $order->get_billing_phone();

            $request_data = array(
                'customer_name' => sanitize_text_field($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()),
                'customer_phone' => sanitize_text_field($customer_phone),
                'customer_address' => sanitize_text_field($order->get_billing_address_1() . ' ' . $order->get_billing_address_2()),
                'merchant_invoice_id' => sanitize_text_field($order->get_order_number()),
                'cash_collection_amount' => floatval($order->get_total()),
                'parcel_weight' => isset($default_params['parcel_weight'])
                    ? intval($default_params['parcel_weight'])
                    : (intval(get_post_meta($order_id, '_parcel_weight', true)) ?: 1),
                'instruction' => isset($default_params['instruction'])
                    ? sanitize_text_field($default_params['instruction'])
                    : sanitize_text_field($order->get_customer_note()),
                'value' => floatval($order->get_total()),
            );

            // Make API request
            $response = wp_remote_post(
                $api_url,
                array(
                    'headers' => $headers,
                    'body' => wp_json_encode($request_data),
                    'timeout' => 30,
                )
            );

            if (is_wp_error($response)) {
                $failure_count++;
                $results[] = array(
                    'order_id' => $order_id,
                    'success' => false,
                    'message' => $response->get_error_message()
                );
                continue;
            }

            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $response_data = json_decode($response_body, true);

            if ($response_code >= 200 && $response_code < 300 && !empty($response_data['tracking_id'])) {
                // Success
                $tracking_id = $response_data['tracking_id'];

                $order->update_meta_data('_redx_tracking_id', $tracking_id);
                $order->save();
                update_post_meta($order_id, '_redx_tracking_id', $tracking_id);

                $order->add_order_note(sprintf(
                    __('RedX Parcel Created via Plugin - Tracking ID: %s', 'shipsync'),
                    $tracking_id
                ));

                $success_count++;
                $results[] = array(
                    'order_id' => $order_id,
                    'success' => true,
                    'tracking_id' => $tracking_id
                );
            } else {
                $failure_count++;
                $error_message = isset($response_data['message'])
                    ? $response_data['message']
                    : __('API error', 'shipsync');
                $results[] = array(
                    'order_id' => $order_id,
                    'success' => false,
                    'message' => $error_message
                );
            }
        }

        return array(
            'success' => $failure_count === 0,
            'message' => sprintf(
                __('Bulk orders processed: %d successful, %d failed', 'shipsync'),
                $success_count,
                $failure_count
            ),
            'success_count' => $success_count,
            'failure_count' => $failure_count,
            'results' => $results
        );
    }

    /**
     * Get order status from order meta
     *
     * Note: RedX plugin doesn't have a direct status API, status is typically
     * updated via webhooks or tracking lookups
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

        $tracking_id = $order->get_meta('_redx_tracking_id');
        if (empty($tracking_id)) {
            $tracking_id = get_post_meta($order->get_id(), '_redx_tracking_id', true);
        }

        if (empty($tracking_id)) {
            return array(
                'success' => false,
                'message' => __('No tracking ID found for this order', 'shipsync')
            );
        }

        // RedX plugin doesn't store status in meta, so we return what we have
        return array(
            'success' => true,
            'tracking_id' => $tracking_id,
            'status' => 'pending' // Default status since RedX doesn't track status in plugin
        );
    }

    /**
     * Update WooCommerce order status based on RedX status
     *
     * Maps RedX statuses to WooCommerce order statuses
     *
     * @param WC_Order $order WooCommerce order object
     * @param string $redx_status RedX order status
     * @return void
     */
    public static function update_wc_order_status($order, $redx_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        // Map RedX statuses to WooCommerce statuses
        // Note: RedX plugin doesn't provide standard status mapping, so we use common patterns
        $status_map = array(
            'delivered' => 'completed',
            'Delivered' => 'completed',
            'completed' => 'completed',
            'completed_delivery' => 'completed',
            'returned' => 'refunded',
            'Returned' => 'refunded',
            'cancelled' => 'cancelled',
            'Cancelled' => 'cancelled',
            'pending' => 'processing',
            'Pending' => 'processing',
            'in_transit' => 'out-shipping', // Custom status if available
            'In Transit' => 'out-shipping',
            'on_hold' => 'on-hold',
            'On Hold' => 'on-hold',
        );

        // Default status if not in map
        $default_status = 'processing';

        // Get WooCommerce status
        $wc_status = isset($status_map[strtolower($redx_status)])
            ? $status_map[strtolower($redx_status)]
            : $default_status;

        // Update order status
        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('RedX delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace(array('_', '-'), ' ', $redx_status))
            ));
        }

        // Store RedX status in order meta
        $order->update_meta_data('_redx_status', $redx_status);
        $order->save();
    }

    /**
     * Sync order status from RedX to WooCommerce
     *
     * This would typically be called via webhook when RedX updates status
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param string $status RedX status
     * @return array Result with success status and message
     */
    public static function sync_order_status($order, $status) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return array(
                'success' => false,
                'message' => __('Invalid order', 'shipsync')
            );
        }

        // Update order meta
        $order->update_meta_data('_redx_status', $status);
        $order->save();

        // Update WooCommerce order status based on RedX status
        self::update_wc_order_status($order, $status);

        return array(
            'success' => true,
            'message' => __('Order status synced successfully', 'shipsync'),
            'status' => $status
        );
    }
}

