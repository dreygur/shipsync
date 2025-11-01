<?php
/**
 * RedX Courier Integration
 * Implements RedX Courier API via WordPress plugin integration
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/abstract-courier.php';

class ShipSync_RedX_Courier extends ShipSync_Abstract_Courier {

    /**
     * Constructor
     */
    public function __construct() {
        $this->courier_id = 'redx';
        $this->courier_name = 'RedX Courier';
        $this->base_url = 'https://openapi.redx.com.bd/v1.0.0-beta';

        parent::__construct();

        // Check if redx-for-woocommerce plugin is available
        $this->check_plugin_dependency();
    }

    /**
     * Check if redx-for-woocommerce plugin is available and show notice if needed
     */
    private function check_plugin_dependency() {
        if (!self::is_plugin_active()) {
            // Plugin not active, but we can still work with direct API calls (future implementation)
            add_action('admin_notices', array($this, 'plugin_dependency_notice'));
        }
    }

    /**
     * Show admin notice about plugin dependency
     */
    public function plugin_dependency_notice() {
        if (current_user_can('manage_options') && !self::is_plugin_active()) {
            echo '<div class="notice notice-info"><p>';
            echo sprintf(
                __('ShipSync: The RedX for WooCommerce plugin is recommended for better integration. Please install and activate it.', 'shipsync')
            );
            echo '</p></div>';
        }
    }

    /**
     * Get settings fields for admin
     * @return array
     */
    public function get_settings_fields() {
        $fields = array(
            'enabled' => array(
                'title' => __('Enable RedX Courier', 'shipsync'),
                'type' => 'checkbox',
                'description' => __('Enable this courier service', 'shipsync'),
                'default' => 'no'
            ),
        );

        // If redx-for-woocommerce plugin is active and configured, use its credentials
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            $fields['use_plugin_api'] = array(
                'title' => __('Use RedX for WooCommerce Plugin', 'shipsync'),
                'type' => 'checkbox',
                'description' => __('Use credentials from RedX for WooCommerce plugin (recommended)', 'shipsync'),
                'default' => 'yes'
            );
            $fields['plugin_info'] = array(
                'title' => '',
                'type' => 'html',
                'html' => '<div class="notice notice-success inline"><p>' .
                    __('RedX for WooCommerce plugin is active and configured. ShipSync will use plugin credentials.', 'shipsync') .
                    '</p></div>'
            );
        } else {
            if (file_exists(WP_PLUGIN_DIR . '/redx-for-woocommerce/redex-tracking-for-woocommerce.php')) {
                $fields['plugin_notice'] = array(
                    'title' => '',
                    'type' => 'html',
                    'html' => '<div class="notice notice-info inline"><p>' .
                        __('Tip: Install and activate the RedX for WooCommerce plugin for better integration.', 'shipsync') .
                        '</p></div>'
                );
            }
        }

        $fields['default_parcel_weight'] = array(
            'title' => __('Default Parcel Weight (kg)', 'shipsync'),
            'type' => 'number',
            'description' => __('Enter default parcel weight in kilograms', 'shipsync'),
            'default' => '1',
            'min' => 0.1,
            'step' => 0.1
        );

        return $fields;
    }

    /**
     * Create an order/parcel
     * @param WC_Order $order WooCommerce order object
     * @param array $params Additional parameters
     * @return array Response with status and data
     */
    public function create_order($order, $params = array()) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('RedX Courier is not enabled', 'shipsync')
            );
        }

        // If redx-for-woocommerce plugin is active and configured, use it
        $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
        if (!$use_plugin) {
            $use_plugin = self::is_plugin_active() &&
                         self::is_plugin_configured();
        }

        if ($use_plugin && self::is_plugin_active() && self::is_plugin_configured()) {
            // Merge default params
            $order_params = array_merge(array(
                'parcel_weight' => isset($this->credentials['default_parcel_weight'])
                    ? intval($this->credentials['default_parcel_weight'])
                    : 1,
            ), $params);

            // Use the wrapper which uses the plugin's API
            $result = self::create_order_via_plugin($order, $order_params);

            if ($result['success']) {
                // Fire action hooks
                if (isset($result['tracking_id'])) {
                    do_action('shipsync_redx_order_created', $order->get_id(), array(
                        'tracking_id' => $result['tracking_id']
                    ));
                }
            }

            return $result;
        }

        // Fall back to direct API calls (not yet implemented, would require API credentials)
        return array(
            'success' => false,
            'message' => __('RedX for WooCommerce plugin is required. Please install and activate it.', 'shipsync')
        );
    }

    /**
     * Create bulk orders
     * @param array $orders Array of WooCommerce order objects (max 500)
     * @return array Response with status and data
     */
    public function create_bulk_orders($orders) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('RedX Courier is not enabled', 'shipsync')
            );
        }

        // Limit to 500 orders
        $orders = array_slice($orders, 0, 500);

        // If redx-for-woocommerce plugin is active and configured, use it
        $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
        if (!$use_plugin) {
            $use_plugin = self::is_plugin_active() &&
                         self::is_plugin_configured();
        }

        if ($use_plugin && self::is_plugin_active() && self::is_plugin_configured()) {
            $default_params = array(
                'parcel_weight' => isset($this->credentials['default_parcel_weight'])
                    ? intval($this->credentials['default_parcel_weight'])
                    : 1,
            );

            return self::create_bulk_orders_via_plugin($orders, $default_params);
        }

        return array(
            'success' => false,
            'message' => __('RedX for WooCommerce plugin is required. Please install and activate it.', 'shipsync')
        );
    }

    /**
     * Get delivery status
     * @param string $identifier Order identifier
     * @param string $type Identifier type (tracking_id, merchant_order_id)
     * @return array Response with status and data
     */
    public function get_delivery_status($identifier, $type = 'tracking_id') {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('RedX Courier is not enabled', 'shipsync')
            );
        }

        // If using merchant_order_id, get order and check status
        if ($type === 'merchant_order_id') {
            $order = wc_get_order($identifier);
            if ($order) {
                return self::get_order_status_via_plugin($order);
            }
        }

        // For tracking_id, we need to find the order first
        if ($type === 'tracking_id') {
            $orders = wc_get_orders(array(
                'meta_key' => '_redx_tracking_id',
                'meta_value' => $identifier,
                'limit' => 1
            ));

            if (!empty($orders)) {
                return self::get_order_status_via_plugin($orders[0]);
            }
        }

        return array(
            'success' => false,
            'message' => __('Order not found', 'shipsync')
        );
    }

    /**
     * Handle webhook callback
     * @param array $payload Webhook payload
     * @return array Response
     */
    public function handle_webhook($payload) {
        $this->log('webhook_received', $payload);

        // RedX webhook format may vary, but typically includes tracking_id and status
        $tracking_id = isset($payload['tracking_id']) ? $payload['tracking_id'] : null;
        $order_id = isset($payload['merchant_order_id']) ? intval($payload['merchant_order_id']) : null;
        $status = isset($payload['status']) ? $payload['status'] : null;

        if (!$tracking_id && !$order_id) {
            return array('success' => false, 'message' => 'Invalid payload - missing tracking_id or merchant_order_id');
        }

        // Find order by tracking_id or merchant_order_id
        $order = null;
        if ($order_id) {
            $order = wc_get_order($order_id);
        }

        if (!$order && $tracking_id) {
            $orders = wc_get_orders(array(
                'meta_key' => '_redx_tracking_id',
                'meta_value' => $tracking_id,
                'limit' => 1
            ));
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }

        if (!$order) {
            $this->log('webhook_order_not_found', $payload, true);
            return array('success' => false, 'message' => 'Order not found');
        }

        // Update order meta
        if ($tracking_id) {
            $order->update_meta_data('_redx_tracking_id', $tracking_id);
            update_post_meta($order->get_id(), '_redx_tracking_id', $tracking_id);
        }

        if ($status) {
            $order->update_meta_data('_redx_status', $status);
        }

        // Update delivery fee if provided
        if (isset($payload['delivery_fee'])) {
            $order->update_meta_data('_redx_delivery_fee', floatval($payload['delivery_fee']));
        }

        $order->save();

        // Add order note
        $note = sprintf(
            __('RedX Status Update: %s', 'shipsync'),
            $status ? ucwords(str_replace(array('_', '-'), ' ', $status)) : __('Status updated', 'shipsync')
        );
        if ($tracking_id) {
            $note .= "\n" . sprintf(__('Tracking ID: %s', 'shipsync'), $tracking_id);
        }
        $order->add_order_note($note);

        // Update WooCommerce order status based on RedX status
        if ($status) {
            self::update_wc_order_status_via_plugin($order, $status);
        }

        do_action('shipsync_redx_status_updated', $order->get_id(), $payload);

        return array('success' => true, 'message' => 'Status updated successfully');
    }

    /**
     * Get account balance
     * @return array Response with status and balance
     */
    public function get_balance() {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('RedX Courier is not enabled', 'shipsync')
            );
        }

        // RedX API doesn't provide a balance endpoint in the plugin
        // Return a placeholder response indicating balance check is not available
        return array(
            'success' => false,
            'message' => __('Balance check is not available for RedX Courier. Please check your RedX dashboard for account balance.', 'shipsync')
        );
    }

    /**
     * Validate API credentials
     * @return bool|WP_Error
     */
    public function validate_credentials() {
        // If plugin is configured and we're using it, validate through plugin
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
            if (!$use_plugin) {
                $use_plugin = true; // Default to using plugin if available
            }

            if ($use_plugin) {
                // Test by checking if API key exists
                $creds = self::get_plugin_credentials();
                if (!empty($creds['api_key']) && $creds['enabled']) {
                    return true;
                }
                return new WP_Error('invalid_credentials', __('Invalid API credentials in RedX for WooCommerce plugin', 'shipsync'));
            }
        }

        return new WP_Error('missing_plugin', __('RedX for WooCommerce plugin is required. Please install and activate it.', 'shipsync'));
    }

    /**
     * Format phone number for RedX (if needed)
     * @param string $phone
     * @return string
     */
    private function format_phone($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // If starts with +88, remove it
        if (substr($phone, 0, 2) === '88') {
            $phone = substr($phone, 2);
        }

        // If starts with 0 and is 11 digits, it's already correct
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            return $phone;
        }

        // If 10 digits and doesn't start with 0, add 0
        if (strlen($phone) === 10) {
            return '0' . $phone;
        }

        return $phone;
    }

    /**
     * Get tracking URL for a tracking code
     * @param string $tracking_code Tracking code
     * @param string $consignment_id Optional consignment ID
     * @return string|null Tracking URL
     */
    public function get_tracking_url($tracking_code, $consignment_id = null) {
        if (empty($tracking_code)) {
            return null;
        }

        // RedX tracking URL format (typically uses tracking_id)
        return 'https://redx.com.bd/track/' . urlencode($tracking_code);
    }

    /**
     * ============================================
     * API WRAPPER METHODS (Merged from class-redx-api-wrapper.php)
     * ============================================
     */

    /**
     * Check if the RedX for WooCommerce plugin is active
     * @return bool
     */
    private static function is_plugin_active() {
        return class_exists('ShopUP\RedxTrackingForWoocommerce\Init') &&
               class_exists('ShopUP\RedxTrackingForWoocommerce\Services\RedxOrderActionsService');
    }

    /**
     * Check if the plugin is configured (has API credentials and is enabled)
     * @return bool
     */
    private static function is_plugin_configured() {
        if (!self::is_plugin_active()) {
            return false;
        }

        $enabled = get_option('redex_tracking_enabled', 'no');
        $api_key = get_option('redex_tracking_api_key', '');

        return ($enabled === 'yes' && !empty($api_key));
    }

    /**
     * Get API credentials from the plugin's options
     * @return array Array with 'api_key' and 'enabled' keys
     */
    private static function get_plugin_credentials() {
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
     * @return string API base URL
     */
    private static function get_api_url() {
        return 'https://openapi.redx.com.bd/v1.0.0-beta/wordpress/parcel';
    }

    /**
     * Create an order using the RedX plugin's API
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters
     * @return array Response array with 'success', 'message', and optionally 'tracking_id'
     */
    private static function create_order_via_plugin($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not active', 'shipsync')
            );
        }

        if (!self::is_plugin_configured()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not configured. Please set up API credentials and enable tracking.', 'shipsync')
            );
        }

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

        $existing_tracking_id = get_post_meta($order_id, '_redx_tracking_id', true);
        if (!empty($existing_tracking_id)) {
            return array(
                'success' => false,
                'message' => sprintf(__('Order already has a RedX tracking ID: %s', 'shipsync'), $existing_tracking_id)
            );
        }

        $api_key = get_option('redex_tracking_api_key');
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => __('RedX API key is not configured', 'shipsync')
            );
        }

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

        $api_url = self::get_api_url();
        $headers = array(
            'Content-Type' => 'application/json',
            'API-ACCESS-TOKEN' => 'Bearer ' . $api_key,
        );

        $response = wp_remote_post(
            $api_url,
            array(
                'headers' => $headers,
                'body' => wp_json_encode($request_data),
                'timeout' => 30,
            )
        );

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

        $tracking_id = $response_data['tracking_id'];

        $order->update_meta_data('_redx_tracking_id', $tracking_id);
        $order->save();

        update_post_meta($order_id, '_redx_tracking_id', $tracking_id);

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
     * Create bulk orders via plugin
     * @param array $orders Array of WooCommerce order objects or IDs
     * @param array $default_params Default parameters for all orders
     * @return array Response with status and data
     */
    private static function create_bulk_orders_via_plugin($orders, $default_params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not active', 'shipsync')
            );
        }

        if (!self::is_plugin_configured()) {
            return array(
                'success' => false,
                'message' => __('RedX for WooCommerce plugin is not configured', 'shipsync')
            );
        }

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

        foreach ($orders as $order) {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }

            if (!$order || !is_a($order, 'WC_Order')) {
                $failure_count++;
                continue;
            }

            $order_id = $order->get_id();

            $existing_tracking_id = get_post_meta($order_id, '_redx_tracking_id', true);
            if (!empty($existing_tracking_id)) {
                continue;
            }

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
     * Get order status from order meta via plugin
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @return array Status data
     */
    private static function get_order_status_via_plugin($order) {
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

        return array(
            'success' => true,
            'tracking_id' => $tracking_id,
            'status' => 'pending'
        );
    }

    /**
     * Update WooCommerce order status based on RedX status
     * @param WC_Order $order WooCommerce order object
     * @param string $redx_status RedX order status
     * @return void
     */
    private static function update_wc_order_status_via_plugin($order, $redx_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

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
            'in_transit' => 'out-shipping',
            'In Transit' => 'out-shipping',
            'on_hold' => 'on-hold',
            'On Hold' => 'on-hold',
        );

        $default_status = 'processing';
        $wc_status = isset($status_map[strtolower($redx_status)])
            ? $status_map[strtolower($redx_status)]
            : $default_status;

        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('RedX delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace(array('_', '-'), ' ', $redx_status))
            ));
        }

        $order->update_meta_data('_redx_status', $redx_status);
        $order->save();
    }
}

