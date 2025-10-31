<?php
/**
 * SteadFast API Plugin Wrapper
 *
 * This wrapper integrates with the steadfast-api WordPress plugin,
 * providing a WooCommerce-compatible interface for ShipSync.
 *
 * @package ShipSync
 * @subpackage Couriers
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class ShipSync_Steadfast_API_Wrapper
 *
 * Wraps the steadfast-api plugin's functions to provide a consistent
 * WooCommerce-compatible API for ShipSync
 */
class ShipSync_Steadfast_API_Wrapper {

    /**
     * Check if the steadfast-api plugin is active
     *
     * @return bool
     */
    public static function is_plugin_active() {
        return class_exists('STDF_Courier_Main') && function_exists('send_order_to_steadfast_api');
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

        $checkbox = get_option('stdf_settings_tab_checkbox', false);
        $api_key = get_option('api_settings_tab_api_key', false);
        $api_secret_key = get_option('api_settings_tab_api_secret_key', false);

        return ($checkbox === 'yes' && !empty($api_key) && !empty($api_secret_key));
    }

    /**
     * Get API credentials from the plugin's options
     *
     * @return array Array with 'api_key' and 'secret_key' keys
     */
    public static function get_credentials() {
        if (!self::is_plugin_active()) {
            return array();
        }

        return array(
            'api_key' => get_option('api_settings_tab_api_key', ''),
            'secret_key' => get_option('api_settings_tab_api_secret_key', ''),
            'enabled' => get_option('stdf_settings_tab_checkbox', false) === 'yes'
        );
    }

    /**
     * Create an order using the steadfast-api plugin
     *
     * This is a WooCommerce-compatible wrapper around send_order_to_steadfast_api()
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters (note, cod_amount, etc.)
     * @return array Response array with 'success', 'message', and optionally 'consignment'
     */
    public static function create_order($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not active', 'shipsync')
            );
        }

        if (!self::is_configured()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not configured. Please set up API credentials.', 'shipsync')
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

        // If custom COD amount is provided, store it in meta (the plugin checks for this)
        if (isset($params['cod_amount']) && !empty($params['cod_amount'])) {
            update_post_meta($order_id, 'steadfast_amount', floatval($params['cod_amount']));
        }

        // Call the plugin's function
        $result = send_order_to_steadfast_api($order_id);

        if ($result === 'success') {
            // Get the consignment ID that was stored by the plugin
            $consignment_id = get_post_meta($order_id, 'steadfast_consignment_id', true);

            // Try to get tracking code if available
            $tracking_code = '';
            if ($consignment_id) {
                // The plugin stores consignment_id, but tracking code might need to be fetched
                // We'll get it from the status check if needed
                $status_data = self::get_status($consignment_id);
                if (!empty($status_data['tracking_code'])) {
                    $tracking_code = $status_data['tracking_code'];
                }
            }

            // Update order meta for ShipSync compatibility
            $order->update_meta_data('_steadfast_consignment_id', $consignment_id);
            if ($tracking_code) {
                $order->update_meta_data('_steadfast_tracking_code', $tracking_code);
            }
            $order->update_meta_data('_steadfast_is_sent', 'yes');
            $order->save();

            // Add order note
            $order->add_order_note(sprintf(
                __('Steadfast Consignment Created via Plugin - Consignment ID: %s', 'shipsync'),
                $consignment_id
            ));

            return array(
                'success' => true,
                'message' => __('Order sent to Steadfast successfully', 'shipsync'),
                'consignment' => array(
                    'consignment_id' => $consignment_id,
                    'tracking_code' => $tracking_code
                )
            );
        } elseif ($result === 'unauthorized') {
            return array(
                'success' => false,
                'message' => __('Unauthorized: Please check your API credentials', 'shipsync')
            );
        } else {
            // The plugin returns error messages as strings
            return array(
                'success' => false,
                'message' => sprintf(__('Error: %s', 'shipsync'), $result)
            );
        }
    }

    /**
     * Get order status by consignment ID
     *
     * @param string $consignment_id Steadfast consignment ID
     * @return array Status data or error message
     */
    public static function get_status($consignment_id) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not active', 'shipsync')
            );
        }

        if (!function_exists('stdf_get_status_by_consignment_id')) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin function not available', 'shipsync')
            );
        }

        $response = stdf_get_status_by_consignment_id($consignment_id);

        if ($response === 'unauthorized') {
            return array(
                'success' => false,
                'message' => __('Unauthorized: Please check your API credentials', 'shipsync')
            );
        } elseif ($response === 'failed') {
            return array(
                'success' => false,
                'message' => __('Failed to get status', 'shipsync')
            );
        } elseif (is_array($response) && isset($response['status']) && $response['status'] == '200') {
            // Return the response data in a consistent format
            return array(
                'success' => true,
                'status' => isset($response['delivery_status']) ? $response['delivery_status'] : '',
                'tracking_code' => isset($response['tracking_code']) ? $response['tracking_code'] : '',
                'consignment_id' => $consignment_id,
                'raw' => $response
            );
        }

        return array(
            'success' => false,
            'message' => __('Unknown error occurred', 'shipsync')
        );
    }

    /**
     * Get current balance
     *
     * @return array Balance data or error message
     */
    public static function get_balance() {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not active', 'shipsync')
            );
        }

        if (!function_exists('stdf_check_current_balance')) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin function not available', 'shipsync')
            );
        }

        $response = stdf_check_current_balance('check-yes');

        if ($response === 'unauthorized') {
            return array(
                'success' => false,
                'message' => __('Unauthorized: Please check your API credentials', 'shipsync')
            );
        } elseif ($response === 'failed') {
            return array(
                'success' => false,
                'message' => __('Failed to get balance', 'shipsync')
            );
        } elseif (is_array($response) && isset($response['status']) && $response['status'] == '200') {
            return array(
                'success' => true,
                'balance' => isset($response['current_balance']) ? floatval($response['current_balance']) : 0,
                'raw' => $response
            );
        }

        return array(
            'success' => false,
            'message' => __('Unknown error occurred', 'shipsync')
        );
    }

    /**
     * Get customer courier score (fraud check)
     *
     * @param string $phone_number Customer phone number
     * @param int $order_id Optional order ID to store score
     * @return array Score data or error message
     */
    public static function get_customer_score($phone_number, $order_id = 0) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not active', 'shipsync')
            );
        }

        if (!function_exists('stdf_customer_courier_score')) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin function not available', 'shipsync')
            );
        }

        $response = stdf_customer_courier_score($phone_number, $order_id);

        if (is_array($response) && isset($response['total_parcels'])) {
            return array(
                'success' => true,
                'total_parcels' => isset($response['total_parcels']) ? intval($response['total_parcels']) : 0,
                'total_delivered' => isset($response['total_delivered']) ? intval($response['total_delivered']) : 0,
                'success_ratio' => isset($response['success_ratio']) ? $response['success_ratio'] : '0%',
                'raw' => $response
            );
        }

        return array(
            'success' => false,
            'message' => is_string($response) ? $response : __('Failed to get customer score', 'shipsync')
        );
    }

    /**
     * Update order status in WooCommerce based on Steadfast delivery status
     *
     * This provides WooCommerce compatibility by mapping Steadfast statuses
     * to WooCommerce order statuses
     *
     * @param WC_Order $order WooCommerce order object
     * @param string $delivery_status Steadfast delivery status
     * @return void
     */
    public static function update_wc_order_status($order, $delivery_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        // Map Steadfast statuses to WooCommerce statuses
        $status_map = array(
            'delivered' => 'wc-completed',
            'cancelled' => 'wc-cancelled',
            'returned' => 'wc-refunded',
            'pending' => 'wc-processing',
            'on_hold' => 'wc-on-hold',
            'in_review' => 'wc-processing',
            'in_transit' => 'out-shipping', // Custom status if available
        );

        // Default status if not in map
        $default_status = 'wc-processing';

        // Get WooCommerce status
        $wc_status = isset($status_map[strtolower($delivery_status)])
            ? $status_map[strtolower($delivery_status)]
            : $default_status;

        // Remove 'wc-' prefix if it exists
        $wc_status = str_replace('wc-', '', $wc_status);

        // Update order status
        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('Steadfast delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace('_', ' ', $delivery_status))
            ));
        }

        // Store delivery status in order meta
        $order->update_meta_data('_steadfast_status', $delivery_status);
        $order->save();
    }

    /**
     * Sync order status from Steadfast to WooCommerce
     *
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @return array Result with success status and message
     */
    public static function sync_order_status($order) {
        if (is_numeric($order)) {
            $order = wc_get_order($order);
        }

        if (!$order || !is_a($order, 'WC_Order')) {
            return array(
                'success' => false,
                'message' => __('Invalid order', 'shipsync')
            );
        }

        $consignment_id = $order->get_meta('_steadfast_consignment_id');
        if (empty($consignment_id)) {
            return array(
                'success' => false,
                'message' => __('No consignment ID found for this order', 'shipsync')
            );
        }

        $status_data = self::get_status($consignment_id);

        if (!$status_data['success']) {
            return $status_data;
        }

        // Update order meta
        if (isset($status_data['status'])) {
            $order->update_meta_data('_steadfast_status', $status_data['status']);
            update_post_meta($order->get_id(), 'stdf_delivery_status', $status_data['status']);
        }

        if (isset($status_data['tracking_code'])) {
            $order->update_meta_data('_steadfast_tracking_code', $status_data['tracking_code']);
        }

        $order->save();

        // Update WooCommerce order status based on delivery status
        if (isset($status_data['status'])) {
            self::update_wc_order_status($order, $status_data['status']);
        }

        return array(
            'success' => true,
            'message' => __('Order status synced successfully', 'shipsync'),
            'status' => isset($status_data['status']) ? $status_data['status'] : ''
        );
    }
}

