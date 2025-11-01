<?php
/**
 * Pathao Courier Integration
 * Implements Pathao Courier API via WordPress plugin integration
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/abstract-courier.php';

class ShipSync_Pathao_Courier extends ShipSync_Abstract_Courier {

    /**
     * Constructor
     */
    public function __construct() {
        $this->courier_id = 'pathao';
        $this->courier_name = 'Pathao Courier';
        $this->base_url = 'https://api-hermes.pathao.com';

        parent::__construct();

        // Check if pathao-courier plugin is available
        $this->check_plugin_dependency();
    }

    /**
     * Check if pathao-courier plugin is available and show notice if needed
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
        // Pathao plugin is now bundled with ShipSync, so no notice needed
        // This method is kept for backward compatibility
    }

    /**
     * Get settings fields for admin
     * @return array
     */
    public function get_settings_fields() {
        $fields = array(
            'enabled' => array(
                'title' => __('Enable Pathao Courier', 'shipsync'),
                'type' => 'checkbox',
                'description' => __('Enable this courier service', 'shipsync'),
                'default' => 'no'
            ),
        );

        // If pathao-courier plugin is active and configured, use its credentials
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            $fields['use_plugin_api'] = array(
                'title' => __('Use Pathao Courier Plugin', 'shipsync'),
                'type' => 'checkbox',
                'description' => __('Use credentials from Pathao Courier plugin (recommended)', 'shipsync'),
                'default' => 'yes'
            );
            $fields['plugin_info'] = array(
                'title' => '',
                'type' => 'html',
                'html' => '<div class="notice notice-success inline"><p>' .
                    __('Pathao Courier plugin is active and configured. ShipSync will use plugin credentials.', 'shipsync') .
                    '</p></div>'
            );
        } else {
            // Check if bundled plugin is actually available
            if (self::is_plugin_active()) {
                $fields['plugin_notice'] = array(
                    'title' => '',
                    'type' => 'html',
                    'html' => '<div class="notice notice-success inline"><p>' .
                        __('Pathao Courier plugin is bundled with ShipSync and ready to use. Please configure your API credentials below.', 'shipsync') .
                        '</p></div>'
                );
            } else {
                // Bundled plugin not found
                $pathao_dir = SHIPSYNC_PLUGIN_PATH . 'courier-woocommerce-plugin-main/';
                $fields['plugin_notice'] = array(
                    'title' => '',
                    'type' => 'html',
                    'html' => '<div class="notice notice-warning inline"><p>' .
                        __('<strong>Warning:</strong> The bundled Pathao Courier plugin was not found. Please ensure the courier-woocommerce-plugin-main directory is included with ShipSync. Pathao integration will not be available until the plugin files are present.', 'shipsync') .
                        '</p></div>'
                );
            }
        }

        $fields['default_store_id'] = array(
            'title' => __('Default Store ID', 'shipsync'),
            'type' => 'text',
            'description' => __('Enter your default Pathao store ID', 'shipsync'),
            'required' => false
        );

        $fields['default_delivery_type'] = array(
            'title' => __('Default Delivery Type', 'shipsync'),
            'type' => 'select',
            'options' => array(
                '48' => __('Standard Delivery (48 hours)', 'shipsync'),
                '24' => __('Express Delivery (24 hours)', 'shipsync'),
                '12' => __('Super Express Delivery (12 hours)', 'shipsync'),
            ),
            'default' => '48'
        );

        $fields['default_item_type'] = array(
            'title' => __('Default Item Type', 'shipsync'),
            'type' => 'select',
            'options' => array(
                '1' => __('Document', 'shipsync'),
                '2' => __('Parcel', 'shipsync'),
                '3' => __('Food', 'shipsync'),
            ),
            'default' => '2'
        );

        return $fields;
    }

    /**
     * Create an order/consignment
     * @param WC_Order $order WooCommerce order object
     * @param array $params Additional parameters
     * @return array Response with status and data
     */
    public function create_order($order, $params = array()) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier is not enabled', 'shipsync')
            );
        }

        // If pathao-courier plugin is active and configured, use it
        $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
        if (!$use_plugin) {
            $use_plugin = self::is_plugin_active() &&
                         self::is_plugin_configured();
        }

        if ($use_plugin && self::is_plugin_active() && self::is_plugin_configured()) {
            // Merge default params
            $order_params = array_merge(array(
                'store_id' => isset($this->credentials['default_store_id']) ? $this->credentials['default_store_id'] : 1,
                'delivery_type' => isset($this->credentials['default_delivery_type']) ? $this->credentials['default_delivery_type'] : 48,
                'item_type' => isset($this->credentials['default_item_type']) ? $this->credentials['default_item_type'] : 2,
            ), $params);

            // Use the wrapper which uses the plugin's API
            $result = self::create_order_via_plugin($order, $order_params);

            if ($result['success']) {
                // Fire action hooks
                if (isset($result['consignment'])) {
                    do_action('shipsync_pathao_order_created', $order->get_id(), $result['consignment']);
                }
            }

            return $result;
        }

        // Fall back to direct API calls (not yet implemented, would require API credentials)
        // Check if bundled plugin failed to load
        $message = __('Pathao Courier integration is not available.', 'shipsync');
        if (!self::is_plugin_active()) {
            $message .= ' ' . __('The bundled Pathao plugin was not found. Please ensure the courier-woocommerce-plugin-main directory is included with ShipSync, or install Pathao Courier plugin separately.', 'shipsync');
        } else {
            $message .= ' ' . __('Please configure your Pathao API credentials in the settings.', 'shipsync');
        }

        return array(
            'success' => false,
            'message' => $message
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
                'message' => __('Pathao Courier is not enabled', 'shipsync')
            );
        }

        // Limit to 500 orders
        $orders = array_slice($orders, 0, 500);

        // If pathao-courier plugin is active and configured, use it
        $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
        if (!$use_plugin) {
            $use_plugin = self::is_plugin_active() &&
                         self::is_plugin_configured();
        }

        if ($use_plugin && self::is_plugin_active() && self::is_plugin_configured()) {
            $default_params = array(
                'store_id' => isset($this->credentials['default_store_id']) ? $this->credentials['default_store_id'] : 1,
                'delivery_type' => isset($this->credentials['default_delivery_type']) ? $this->credentials['default_delivery_type'] : 48,
                'item_type' => isset($this->credentials['default_item_type']) ? $this->credentials['default_item_type'] : 2,
            );

            return self::create_bulk_orders_via_plugin($orders, $default_params);
        }

        // Check if bundled plugin failed to load
        $message = __('Pathao Courier integration is not available.', 'shipsync');
        if (!self::is_plugin_active()) {
            $message .= ' ' . __('The bundled Pathao plugin was not found. Please ensure the courier-woocommerce-plugin-main directory is included with ShipSync, or install Pathao Courier plugin separately.', 'shipsync');
        } else {
            $message .= ' ' . __('Please configure your Pathao API credentials in the settings.', 'shipsync');
        }

        return array(
            'success' => false,
            'message' => $message
        );
    }

    /**
     * Get delivery status
     * @param string $identifier Order identifier
     * @param string $type Identifier type (consignment_id, merchant_order_id)
     * @return array Response with status and data
     */
    public function get_delivery_status($identifier, $type = 'consignment_id') {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier is not enabled', 'shipsync')
            );
        }

        // If using merchant_order_id, get order and check status
        if ($type === 'merchant_order_id') {
            $order = wc_get_order($identifier);
            if ($order) {
                return self::get_order_status_via_plugin($order);
            }
        }

        // For consignment_id, we need to find the order first
        if ($type === 'consignment_id') {
            $orders = wc_get_orders(array(
                'meta_key' => '_pathao_consignment_id',
                'meta_value' => $identifier,
                'limit' => 1
            ));

            // Also check plugin's meta key
            if (empty($orders)) {
                $orders = wc_get_orders(array(
                    'meta_key' => 'ptc_consignment_id',
                    'meta_value' => $identifier,
                    'limit' => 1
                ));
            }

            if (!empty($orders)) {
                return self::get_order_status_via_plugin($orders[0]);
            }
        }

        $message = __('Order not found', 'shipsync');
        if (!self::is_plugin_active()) {
            $message .= ' ' . __('Pathao Courier plugin is not available.', 'shipsync');
        }

        return array(
            'success' => false,
            'message' => $message
        );
    }

    /**
     * Handle webhook callback
     * @param array $payload Webhook payload
     * @return array Response
     */
    public function handle_webhook($payload) {
        $this->log('webhook_received', $payload);

        if (!isset($payload['event']) && !isset($payload['order_status'])) {
            return array('success' => false, 'message' => 'Invalid payload');
        }

        $order_id = isset($payload['merchant_order_id']) ? intval($payload['merchant_order_id']) : null;

        if (!$order_id) {
            return array('success' => false, 'message' => 'Missing merchant_order_id');
        }

        $order = wc_get_order($order_id);

        if (!$order) {
            $this->log('webhook_order_not_found', $payload, true);
            return array('success' => false, 'message' => 'Order not found');
        }

        // Update order meta
        $status = isset($payload['order_status']) ? $payload['order_status'] : null;
        if (!$status && isset($payload['event'])) {
            // Map event to status
            $event = $payload['event'];
            $status_map = array(
                'order.delivered' => 'delivered',
                'order.partial-delivery' => 'partial-delivery',
                'order.returned' => 'returned',
                'order.picked' => 'picked',
                'order.in-transit' => 'in-transit',
                'order.on-hold' => 'on-hold',
            );
            $status = isset($status_map[$event]) ? $status_map[$event] : $event;
        }

        if ($status) {
            $order->update_meta_data('_pathao_status', $status);
            // Also update plugin's meta
            update_post_meta($order_id, 'ptc_status', $status);
        }

        if (isset($payload['delivery_fee'])) {
            $order->update_meta_data('_pathao_delivery_fee', floatval($payload['delivery_fee']));
            update_post_meta($order_id, 'ptc_delivery_fee', floatval($payload['delivery_fee']));
        }

        if (isset($payload['consignment_id'])) {
            $order->update_meta_data('_pathao_consignment_id', $payload['consignment_id']);
            update_post_meta($order_id, 'ptc_consignment_id', $payload['consignment_id']);
        }

        $order->save();

        // Add order note
        $note = sprintf(
            __('Pathao Status Update: %s', 'shipsync'),
            ucwords(str_replace(array('_', '-', '.'), ' ', $status))
        );
        if (isset($payload['delivery_fee'])) {
            $note .= "\n" . sprintf(__('Delivery Fee: %s', 'shipsync'), wc_price($payload['delivery_fee']));
        }
        $order->add_order_note($note);

        // Update WooCommerce order status based on Pathao status
        if ($status) {
            self::update_wc_order_status_via_plugin($order, $status);
        }

        do_action('shipsync_pathao_status_updated', $order->get_id(), $payload);

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
                'message' => __('Pathao Courier is not enabled', 'shipsync')
            );
        }

        // Pathao API doesn't provide a balance endpoint in the plugin
        // Return a placeholder response indicating balance check is not available
        return array(
            'success' => false,
            'message' => __('Balance check is not available for Pathao Courier. Please check your Pathao dashboard for account balance.', 'shipsync')
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
                $token = self::get_token_via_plugin();
                if ($token) {
                    return true;
                }
                return new WP_Error('invalid_credentials', __('Invalid API credentials in Pathao Courier plugin', 'shipsync'));
            }
        }

        $message = __('Pathao Courier integration is not available.', 'shipsync');
        if (!self::is_plugin_active()) {
            $message .= ' ' . __('The bundled Pathao plugin was not found. Please ensure the courier-woocommerce-plugin-main directory is included with ShipSync.', 'shipsync');
        } else {
            $message .= ' ' . __('Please configure your Pathao API credentials.', 'shipsync');
        }

        return new WP_Error('missing_plugin', $message);
    }

    /**
     * Format phone number for Pathao (if needed)
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
        if (empty($tracking_code) && empty($consignment_id)) {
            return null;
        }

        // Use consignment_id if available (Pathao uses consignment_id for tracking)
        $identifier = $consignment_id ? $consignment_id : $tracking_code;

        // Get merchant panel base URL from Pathao plugin if available
        $base_url = 'https://merchant.pathao.com';
        if (function_exists('get_ptc_merchant_panel_base_url')) {
            $base_url = get_ptc_merchant_panel_base_url();
        }

        // Pathao tracking URL format
        return $base_url . '/orders/' . urlencode($identifier);
    }

    /**
     * ============================================
     * API WRAPPER METHODS (Merged from class-pathao-api-wrapper.php)
     * ============================================
     */

    /**
     * Check if the Pathao Courier plugin is active
     * @return bool
     */
    private static function is_plugin_active() {
        return function_exists('pt_hms_create_new_order') &&
               function_exists('pt_hms_get_token') &&
               function_exists('getPtOrderData');
    }

    /**
     * Check if the plugin is configured (has API credentials)
     * @return bool
     */
    private static function is_plugin_configured() {
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
     * @return array Array with 'client_id', 'client_secret', 'environment', and 'webhook_secret'
     */
    private static function get_plugin_credentials() {
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
     * @return string|false Access token or false on error
     */
    private static function get_token_via_plugin($reset = false) {
        if (!self::is_plugin_active()) {
            return false;
        }

        return pt_hms_get_token($reset);
    }

    /**
     * Get order data formatted for Pathao API
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @return array|false Order data array or false on error
     */
    private static function get_order_data_via_plugin($order) {
        if (!self::is_plugin_active()) {
            return false;
        }

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
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters
     * @return array Response array with 'success', 'message', and optionally 'consignment'
     */
    private static function create_order_via_plugin($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not active', 'shipsync')
            );
        }

        if (!self::is_plugin_configured()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not configured. Please set up API credentials.', 'shipsync')
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

        $existing_consignment = get_post_meta($order_id, 'ptc_consignment_id', true);
        if (!empty($existing_consignment) && $existing_consignment !== '-') {
            return array(
                'success' => false,
                'message' => __('Order already has a Pathao consignment ID', 'shipsync')
            );
        }

        $order_data = self::get_order_data_via_plugin($order);
        if (!$order_data) {
            return array(
                'success' => false,
                'message' => __('Failed to get order data', 'shipsync')
            );
        }

        $pathao_order_data = array(
            'merchant_order_id' => $order_id,
            'recipient_name' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
            'recipient_phone' => $order_data['billing']['phone'],
            'recipient_address' => self::format_address_via_plugin($order_data),
            'amount_to_collect' => $order->get_total(),
            'item_quantity' => isset($order_data['total_items']) ? $order_data['total_items'] : 1,
            'item_weight' => isset($order_data['total_weight']) ? $order_data['total_weight'] : 0.5,
            'item_description' => self::format_item_description_via_plugin($order_data),
        );

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

        $response = pt_hms_create_new_order($pathao_order_data);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        if (isset($response['data']['consignment_id'])) {
            $consignment_id = $response['data']['consignment_id'];
            $delivery_fee = isset($response['data']['delivery_fee']) ? $response['data']['delivery_fee'] : 0;

            $order->update_meta_data('_pathao_consignment_id', $consignment_id);
            $order->update_meta_data('_pathao_status', 'pending');
            $order->update_meta_data('_pathao_delivery_fee', $delivery_fee);
            $order->save();

            update_post_meta($order_id, 'ptc_consignment_id', $consignment_id);
            update_post_meta($order_id, 'ptc_status', 'pending');
            if ($delivery_fee) {
                update_post_meta($order_id, 'ptc_delivery_fee', $delivery_fee);
            }

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
     * Create bulk orders via plugin
     * @param array $orders Array of WooCommerce order objects or IDs
     * @param array $default_params Default parameters for all orders
     * @return array Response with status and data
     */
    private static function create_bulk_orders_via_plugin($orders, $default_params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not active', 'shipsync')
            );
        }

        if (!self::is_plugin_configured()) {
            return array(
                'success' => false,
                'message' => __('Pathao Courier plugin is not configured', 'shipsync')
            );
        }

        $pathao_orders = array();

        foreach ($orders as $order) {
            if (is_numeric($order)) {
                $order = wc_get_order($order);
            }

            if (!$order || !is_a($order, 'WC_Order')) {
                continue;
            }

            $order_id = $order->get_id();

            $existing_consignment = get_post_meta($order_id, 'ptc_consignment_id', true);
            if (!empty($existing_consignment) && $existing_consignment !== '-') {
                continue;
            }

            $order_data = self::get_order_data_via_plugin($order);
            if (!$order_data) {
                continue;
            }

            $pathao_order_data = array(
                'merchant_order_id' => $order_id,
                'recipient_name' => $order_data['billing']['first_name'] . ' ' . $order_data['billing']['last_name'],
                'recipient_phone' => $order_data['billing']['phone'],
                'recipient_address' => self::format_address_via_plugin($order_data),
                'amount_to_collect' => $order->get_total(),
                'item_quantity' => isset($order_data['total_items']) ? $order_data['total_items'] : 1,
                'item_weight' => isset($order_data['total_weight']) ? $order_data['total_weight'] : 0.5,
                'item_description' => self::format_item_description_via_plugin($order_data),
            );

            $pathao_order_data = array_merge($default_params, $pathao_order_data);
            $pathao_orders[] = $pathao_order_data;
        }

        if (empty($pathao_orders)) {
            return array(
                'success' => false,
                'message' => __('No valid orders to process', 'shipsync')
            );
        }

        $response = pt_hms_create_new_order_bulk($pathao_orders);

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

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
     * @param WC_Order $order WooCommerce order object
     * @param string $pathao_status Pathao order status
     * @return void
     */
    private static function update_wc_order_status_via_plugin($order, $pathao_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

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
            'in-transit' => 'out-shipping',
            'In_Transit' => 'out-shipping',
            'order.in-transit' => 'out-shipping',
        );

        $default_status = 'processing';
        $wc_status = isset($status_map[$pathao_status])
            ? $status_map[$pathao_status]
            : $default_status;

        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('Pathao delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace(array('_', '-', '.'), ' ', $pathao_status))
            ));
        }

        $order->update_meta_data('_pathao_status', $pathao_status);
        $order->save();
    }

    /**
     * Format address from order data
     * @param array $order_data Order data from getPtOrderData
     * @return string Formatted address
     */
    private static function format_address_via_plugin($order_data) {
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
     * @param array $order_data Order data from getPtOrderData
     * @return string Formatted item description
     */
    private static function format_item_description_via_plugin($order_data) {
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
}

