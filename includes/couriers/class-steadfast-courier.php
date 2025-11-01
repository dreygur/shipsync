<?php
/**
 * Steadfast Courier Integration
 * Implements Steadfast Courier Ltd API V1
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/abstract-courier.php';

class ShipSync_Steadfast_Courier extends ShipSync_Abstract_Courier {

    /**
     * Constructor
     */
    public function __construct() {
        $this->courier_id = 'steadfast';
        $this->courier_name = 'Steadfast Courier';
        $this->base_url = 'https://portal.packzy.com/api/v1';

        parent::__construct();

        // Check if steadfast-api plugin is available
        $this->check_plugin_dependency();
    }

    /**
     * Check if steadfast-api plugin is available and show notice if needed
     */
    private function check_plugin_dependency() {
        if (!self::is_plugin_active()) {
            // Plugin not active, but we can still work with direct API calls
            add_action('admin_notices', array($this, 'plugin_dependency_notice'));
        }
    }

    /**
     * Show admin notice about plugin dependency
     */
    public function plugin_dependency_notice() {
        if (current_user_can('manage_options') && !self::is_plugin_active()) {
            $plugin_file = 'steadfast-api/steadfast-api.php';
            $install_url = wp_nonce_url(
                self_admin_url('update.php?action=install-plugin&plugin=steadfast-api'),
                'install-plugin_steadfast-api'
            );

            echo '<div class="notice notice-info"><p>';
            echo sprintf(
                __('ShipSync: The SteadFast API plugin is recommended for better integration. %s', 'shipsync'),
                '<a href="' . esc_url($install_url) . '">' . __('Install Now', 'shipsync') . '</a>'
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
                'title' => __('Enable Steadfast Courier', 'shipsync'),
                'type' => 'checkbox',
                'description' => __('Enable this courier service', 'shipsync'),
                'default' => 'no'
            ),
        );

        // If steadfast-api plugin is active and configured, use its credentials (no need for duplicate fields)
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            // Plugin is available and configured - use its credentials automatically
            // No need to show duplicate credential fields
            $fields['plugin_info'] = array(
                'title' => '',
                'type' => 'html',
                'html' => '<div class="notice notice-success inline"><p>' .
                    __('<strong>SteadFast API plugin is active and configured.</strong> ShipSync will automatically use the credentials from the SteadFast API plugin settings. Configure your API credentials in <a href="' . admin_url('admin.php?page=steadfast&tab=settings') . '">SteadFast API plugin settings</a>.', 'shipsync') .
                    '</p></div>'
            );
            // Automatically use plugin credentials (no option needed)
            // The code will automatically fall back to plugin credentials if ShipSync credentials are empty
        } else {
            // Plugin not available - show credential fields
            $fields['api_key'] = array(
                'title' => __('API Key', 'shipsync'),
                'type' => 'text',
                'description' => __('Enter your Steadfast API Key', 'shipsync'),
                'required' => true
            );
            $fields['secret_key'] = array(
                'title' => __('Secret Key', 'shipsync'),
                'type' => 'password',
                'description' => __('Enter your Steadfast Secret Key', 'shipsync'),
                'required' => true
            );

            // Check if plugin exists but is not configured
            if (class_exists('STDF_Courier_Main')) {
                $fields['plugin_notice'] = array(
                    'title' => '',
                    'type' => 'html',
                    'html' => '<div class="notice notice-warning inline"><p>' .
                        __('<strong>SteadFast API plugin is installed but not configured.</strong> You can either configure credentials here or in the <a href="' . admin_url('admin.php?page=steadfast&tab=settings') . '">SteadFast API plugin settings</a>.', 'shipsync') .
                        '</p></div>'
                );
            } elseif (file_exists(WP_PLUGIN_DIR . '/steadfast-api/steadfast-api.php')) {
                $fields['plugin_notice'] = array(
                    'title' => '',
                    'type' => 'html',
                    'html' => '<div class="notice notice-info inline"><p>' .
                        __('<strong>Tip:</strong> Install and activate the SteadFast API plugin for better integration. Once configured, ShipSync will automatically use its credentials.', 'shipsync') .
                        '</p></div>'
                );
            }
        }

        $fields['default_delivery_type'] = array(
            'title' => __('Default Delivery Type', 'shipsync'),
            'type' => 'select',
            'options' => array(
                '0' => __('Home Delivery', 'shipsync'),
                '1' => __('Point Delivery', 'shipsync')
            ),
            'default' => '0'
        );

        return $fields;
    }

    /**
     * Get API headers
     * @return array
     */
    private function get_headers() {
        // Priority: Use plugin credentials if available and configured, otherwise use ShipSync settings
        $api_key = '';
        $secret_key = '';

        // First priority: Use plugin credentials if plugin is active and configured
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            $plugin_creds = self::get_plugin_credentials();
            if (!empty($plugin_creds['api_key']) && !empty($plugin_creds['secret_key'])) {
                $api_key = $plugin_creds['api_key'];
                $secret_key = $plugin_creds['secret_key'];
            }
        }

        // Fallback: Use ShipSync settings if plugin credentials not available
        if (empty($api_key) || empty($secret_key)) {
            $api_key = isset($this->credentials['api_key']) ? $this->credentials['api_key'] : '';
            $secret_key = isset($this->credentials['secret_key']) ? $this->credentials['secret_key'] : '';
        }

        return array(
            'Api-Key' => $api_key,
            'Secret-Key' => $secret_key,
            'Content-Type' => 'application/json'
        );
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
                'message' => __('Steadfast Courier is not enabled', 'shipsync')
            );
        }

        // If steadfast-api plugin is active and configured, use it automatically
        // Priority: Plugin credentials > ShipSync settings
        $use_plugin = self::is_plugin_active() && self::is_plugin_configured();

        // If plugin credentials are available, prefer them (unless explicitly overridden in ShipSync settings)
        if (!$use_plugin) {
            // Only use ShipSync credentials if plugin is not available or not configured
            $use_plugin = false;
        }

        if ($use_plugin) {
            // Use the wrapper which uses the plugin's API
            $result = self::create_order_via_plugin($order, $params);

            if ($result['success']) {
                // Fire action hooks
                if (isset($result['consignment'])) {
                    do_action('shipsync_steadfast_order_created', $order->get_id(), $result['consignment']);
                    do_action('ocm_steadfast_order_created', $order->get_id(), $result['consignment']);
                }
            }

            return $result;
        }

        // Fall back to direct API calls (existing behavior)

        // Prepare order data
        $order_data = array(
            'invoice' => $order->get_order_number(),
            'recipient_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
            'recipient_phone' => $this->format_phone($order->get_billing_phone()),
            'recipient_address' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
            'cod_amount' => floatval($order->get_total()),
            'recipient_email' => $order->get_billing_email(),
        );

        // Add optional parameters
        if (!empty($params['note'])) {
            $order_data['note'] = $params['note'];
        }

        if (!empty($params['deliver_within'])) {
            $order_data['deliver_within'] = $params['deliver_within'];
        }

        if (!empty($params['alternative_phone'])) {
            $order_data['alternative_phone'] = $this->format_phone($params['alternative_phone']);
        }

        // Add item description
        $items = $order->get_items();
        $item_names = array();
        foreach ($items as $item) {
            $item_names[] = $item->get_name() . ' x' . $item->get_quantity();
        }
        $order_data['item_description'] = implode(', ', $item_names);
        $order_data['total_lot'] = count($items);

        // Delivery type
        $order_data['delivery_type'] = isset($params['delivery_type'])
            ? $params['delivery_type']
            : (isset($this->credentials['default_delivery_type']) ? $this->credentials['default_delivery_type'] : 0);

        $this->log('create_order_request', $order_data);

        // Make API request
        $response = $this->make_request(
            '/create_order',
            'POST',
            $order_data,
            $this->get_headers()
        );

        if (is_wp_error($response)) {
            $this->log('create_order_error', array('error' => $response->get_error_message()), true);
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $this->log('create_order_response', $response);

        // Process response
        if ($response['status_code'] == 200 && isset($response['data']['status']) && $response['data']['status'] == 200) {
            $consignment = $response['data']['consignment'];

            // Store tracking information in order meta
            $order->update_meta_data('_steadfast_consignment_id', $consignment['consignment_id']);
            $order->update_meta_data('_steadfast_tracking_code', $consignment['tracking_code']);
            $order->update_meta_data('_steadfast_status', $consignment['status']);
            $order->save();

            // Add order note
            $order->add_order_note(sprintf(
                __('Steadfast Consignment Created - Tracking Code: %s, Consignment ID: %s', 'shipsync'),
                $consignment['tracking_code'],
                $consignment['consignment_id']
            ));

            do_action('shipsync_steadfast_order_created', $order->get_id(), $consignment);
            do_action('ocm_steadfast_order_created', $order->get_id(), $consignment); // Backward compatibility

            return array(
                'success' => true,
                'message' => $response['data']['message'],
                'consignment' => $consignment
            );
        }

        // Handle error
        $error_message = isset($response['data']['message'])
            ? $response['data']['message']
            : __('Failed to create consignment', 'shipsync');

        $this->log('create_order_failed', $response['data'], true);

        return array(
            'success' => false,
            'message' => $error_message,
            'response' => $response['data']
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
                'message' => __('Steadfast Courier is not enabled', 'shipsync')
            );
        }

        // Limit to 500 orders
        $orders = array_slice($orders, 0, 500);

        $data = array();
        foreach ($orders as $order) {
            $items = $order->get_items();
            $item_names = array();
            foreach ($items as $item) {
                $item_names[] = $item->get_name() . ' x' . $item->get_quantity();
            }

            $data[] = array(
                'invoice' => $order->get_order_number(),
                'recipient_name' => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                'recipient_phone' => $this->format_phone($order->get_billing_phone()),
                'recipient_address' => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
                'cod_amount' => floatval($order->get_total()),
                'recipient_email' => $order->get_billing_email(),
                'item_description' => implode(', ', $item_names),
                'total_lot' => count($items)
            );
        }

        $this->log('create_bulk_orders_request', array('count' => count($data)));

        // Make API request
        $response = $this->make_request(
            '/create_order/bulk-order',
            'POST',
            array('data' => $data),
            $this->get_headers()
        );

        if (is_wp_error($response)) {
            $this->log('create_bulk_orders_error', array('error' => $response->get_error_message()), true);
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        $this->log('create_bulk_orders_response', $response);

        if ($response['status_code'] == 200 && is_array($response['data'])) {
            // Process each consignment
            foreach ($response['data'] as $consignment_data) {
                if ($consignment_data['status'] === 'success') {
                    // Find the order by invoice
                    foreach ($orders as $order) {
                        if ($order->get_order_number() == $consignment_data['invoice']) {
                            $order->update_meta_data('_steadfast_consignment_id', $consignment_data['consignment_id']);
                            $order->update_meta_data('_steadfast_tracking_code', $consignment_data['tracking_code']);
                            $order->update_meta_data('_steadfast_status', 'in_review');
                            $order->save();

                            $order->add_order_note(sprintf(
                                __('Steadfast Consignment Created - Tracking Code: %s', 'shipsync'),
                                $consignment_data['tracking_code']
                            ));
                            break;
                        }
                    }
                }
            }

            return array(
                'success' => true,
                'message' => __('Bulk orders created successfully', 'shipsync'),
                'consignments' => $response['data']
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to create bulk orders', 'shipsync'),
            'response' => $response['data']
        );
    }

    /**
     * Get delivery status
     * @param string $identifier Order identifier
     * @param string $type Identifier type (tracking_code, invoice, consignment_id)
     * @return array Response with status and data
     */
    public function get_delivery_status($identifier, $type = 'tracking_code') {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Steadfast Courier is not enabled', 'shipsync')
            );
        }

        // If using consignment_id and plugin is available, use wrapper automatically
        if ($type === 'consignment_id' && self::is_plugin_active() && self::is_plugin_configured()) {
            return self::get_status_via_plugin($identifier);
        }

        // Build endpoint based on type
        $endpoints = array(
            'consignment_id' => '/status_by_cid/' . $identifier,
            'invoice' => '/status_by_invoice/' . $identifier,
            'tracking_code' => '/status_by_trackingcode/' . $identifier
        );

        if (!isset($endpoints[$type])) {
            return array(
                'success' => false,
                'message' => __('Invalid identifier type', 'shipsync')
            );
        }

        $response = $this->make_request(
            $endpoints[$type],
            'GET',
            array(),
            $this->get_headers()
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        if ($response['status_code'] == 200 && isset($response['data']['delivery_status'])) {
            return array(
                'success' => true,
                'status' => $response['data']['delivery_status'],
                'data' => $response['data']
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to get delivery status', 'shipsync'),
            'response' => $response['data']
        );
    }

    /**
     * Get account balance
     * @return array Response with status and balance
     */
    public function get_balance() {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Steadfast Courier is not enabled', 'shipsync')
            );
        }

        // If plugin is available and configured, use wrapper automatically
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            return self::get_balance_via_plugin();
        }

        $response = $this->make_request(
            '/get_balance',
            'GET',
            array(),
            $this->get_headers()
        );

        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => $response->get_error_message()
            );
        }

        if ($response['status_code'] == 200 && isset($response['data']['current_balance'])) {
            return array(
                'success' => true,
                'balance' => $response['data']['current_balance']
            );
        }

        return array(
            'success' => false,
            'message' => __('Failed to get balance', 'shipsync')
        );
    }

    /**
     * Handle webhook callback
     * @param array $payload Webhook payload
     * @return array Response
     */
    public function handle_webhook($payload) {
        $this->log('webhook_received', $payload);

        if (!isset($payload['notification_type'])) {
            return array('success' => false, 'message' => 'Invalid payload');
        }

        switch ($payload['notification_type']) {
            case 'delivery_status':
                return $this->handle_delivery_status_webhook($payload);

            case 'tracking_update':
                return $this->handle_tracking_update_webhook($payload);

            default:
                return array('success' => false, 'message' => 'Unknown notification type');
        }
    }

    /**
     * Handle delivery status webhook
     * @param array $payload
     * @return array
     */
    private function handle_delivery_status_webhook($payload) {
        // Find order by consignment ID or invoice
        $order = null;

        if (isset($payload['consignment_id'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_steadfast_consignment_id',
                'meta_value' => $payload['consignment_id'],
                'limit' => 1
            ));
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }

        if (!$order && isset($payload['invoice'])) {
            $order = wc_get_order($payload['invoice']);
        }

        if (!$order) {
            $this->log('webhook_order_not_found', $payload, true);
            return array('success' => false, 'message' => 'Order not found');
        }

        // Update order meta
        $order->update_meta_data('_steadfast_status', $payload['status']);
        if (isset($payload['delivery_charge'])) {
            $order->update_meta_data('_steadfast_delivery_charge', $payload['delivery_charge']);
        }
        $order->save();

        // Add order note
        $note = sprintf(
            __('Steadfast Status Update: %s', 'shipsync'),
            $payload['status']
        );
        if (isset($payload['tracking_message'])) {
            $note .= "\n" . $payload['tracking_message'];
        }
        $order->add_order_note($note);

        // Update WooCommerce order status based on delivery status
        $this->update_wc_order_status($order, $payload['status']);

        do_action('shipsync_steadfast_status_updated', $order->get_id(), $payload);
        do_action('ocm_steadfast_status_updated', $order->get_id(), $payload); // Backward compatibility

        return array('success' => true, 'message' => 'Status updated successfully');
    }

    /**
     * Handle tracking update webhook
     * @param array $payload
     * @return array
     */
    private function handle_tracking_update_webhook($payload) {
        // Find order
        $order = null;

        if (isset($payload['consignment_id'])) {
            $orders = wc_get_orders(array(
                'meta_key' => '_steadfast_consignment_id',
                'meta_value' => $payload['consignment_id'],
                'limit' => 1
            ));
            if (!empty($orders)) {
                $order = $orders[0];
            }
        }

        if (!$order && isset($payload['invoice'])) {
            $order = wc_get_order($payload['invoice']);
        }

        if (!$order) {
            return array('success' => false, 'message' => 'Order not found');
        }

        // Add tracking update as order note
        if (isset($payload['tracking_message'])) {
            $order->add_order_note(
                sprintf(
                    __('Steadfast Tracking: %s', 'shipsync'),
                    $payload['tracking_message']
                )
            );
        }

        do_action('shipsync_steadfast_tracking_updated', $order->get_id(), $payload);
        do_action('ocm_steadfast_tracking_updated', $order->get_id(), $payload); // Backward compatibility

        return array('success' => true, 'message' => 'Tracking updated successfully');
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

        // Use consignment_id if available, otherwise use tracking_code
        $identifier = $consignment_id ? $consignment_id : $tracking_code;

        // Steadfast tracking URL format
        return 'https://portal.packzy.com/track/' . urlencode($identifier);
    }

    /**
     * Update WooCommerce order status based on Steadfast status
     * @param WC_Order $order
     * @param string $steadfast_status
     */
    private function update_wc_order_status($order, $steadfast_status) {
        // Use wrapper's WooCommerce-compatible status mapping if available
        if (self::is_plugin_active()) {
            self::update_wc_order_status_via_plugin($order, $steadfast_status);
            return;
        }

        // Fallback status mapping
        $status_map = array(
            'delivered' => 'completed',
            'partial_delivered' => 'processing',
            'cancelled' => 'cancelled',
            'hold' => 'on-hold'
        );

        if (isset($status_map[$steadfast_status])) {
            $order->update_status($status_map[$steadfast_status]);
        }
    }

    /**
     * Validate API credentials
     * @return bool|WP_Error
     */
    public function validate_credentials() {
        // If plugin is configured, validate through plugin automatically
        if (self::is_plugin_active() && self::is_plugin_configured()) {
            $result = self::get_balance_via_plugin();
            if ($result['success']) {
                return true;
            }
            return new WP_Error('invalid_credentials', __('Invalid API credentials in SteadFast API plugin. Please check your settings in the SteadFast API plugin.', 'shipsync'));
        }

        // Validate ShipSync credentials (only if plugin is not available)
        if (empty($this->credentials['api_key']) || empty($this->credentials['secret_key'])) {
            return new WP_Error('missing_credentials', __('API Key and Secret Key are required. Please configure them below or install the SteadFast API plugin.', 'shipsync'));
        }

        // Test credentials by checking balance
        $result = $this->get_balance();

        if ($result['success']) {
            return true;
        }

        return new WP_Error('invalid_credentials', __('Invalid API credentials', 'shipsync'));
    }

    /**
     * Format phone number to 11 digits
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
     * ============================================
     * API WRAPPER METHODS (Merged from class-steadfast-api-wrapper.php)
     * ============================================
     */

    /**
     * Check if the steadfast-api plugin is active
     * @return bool
     */
    private static function is_plugin_active() {
        return class_exists('STDF_Courier_Main') && function_exists('send_order_to_steadfast_api');
    }

    /**
     * Check if the plugin is configured (has API credentials)
     * @return bool
     */
    private static function is_plugin_configured() {
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
     * @return array Array with 'api_key' and 'secret_key' keys
     */
    private static function get_plugin_credentials() {
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
     * @param WC_Order|int $order WooCommerce order object or order ID
     * @param array $params Additional parameters
     * @return array Response array with 'success', 'message', and optionally 'consignment'
     */
    private static function create_order_via_plugin($order, $params = array()) {
        if (!self::is_plugin_active()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not active', 'shipsync')
            );
        }

        if (!self::is_plugin_configured()) {
            return array(
                'success' => false,
                'message' => __('SteadFast API plugin is not configured. Please set up API credentials.', 'shipsync')
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

        if (isset($params['cod_amount']) && !empty($params['cod_amount'])) {
            update_post_meta($order_id, 'steadfast_amount', floatval($params['cod_amount']));
        }

        $result = send_order_to_steadfast_api($order_id);

        if ($result === 'success') {
            $consignment_id = get_post_meta($order_id, 'steadfast_consignment_id', true);
            $tracking_code = '';
            if ($consignment_id) {
                $status_data = self::get_status_via_plugin($consignment_id);
                if (!empty($status_data['tracking_code'])) {
                    $tracking_code = $status_data['tracking_code'];
                }
            }

            $order->update_meta_data('_steadfast_consignment_id', $consignment_id);
            if ($tracking_code) {
                $order->update_meta_data('_steadfast_tracking_code', $tracking_code);
            }
            $order->update_meta_data('_steadfast_is_sent', 'yes');
            $order->save();

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
            return array(
                'success' => false,
                'message' => sprintf(__('Error: %s', 'shipsync'), $result)
            );
        }
    }

    /**
     * Get order status by consignment ID via plugin
     * @param string $consignment_id Steadfast consignment ID
     * @return array Status data or error message
     */
    private static function get_status_via_plugin($consignment_id) {
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
     * Get current balance via plugin
     * @return array Balance data or error message
     */
    private static function get_balance_via_plugin() {
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
     * Update order status in WooCommerce based on Steadfast delivery status
     * @param WC_Order $order WooCommerce order object
     * @param string $delivery_status Steadfast delivery status
     * @return void
     */
    private static function update_wc_order_status_via_plugin($order, $delivery_status) {
        if (!$order || !is_a($order, 'WC_Order')) {
            return;
        }

        $status_map = array(
            'delivered' => 'wc-completed',
            'cancelled' => 'wc-cancelled',
            'returned' => 'wc-refunded',
            'pending' => 'wc-processing',
            'on_hold' => 'wc-on-hold',
            'in_review' => 'wc-processing',
            'in_transit' => 'out-shipping',
        );

        $default_status = 'wc-processing';
        $wc_status = isset($status_map[strtolower($delivery_status)])
            ? $status_map[strtolower($delivery_status)]
            : $default_status;

        $wc_status = str_replace('wc-', '', $wc_status);

        $current_status = $order->get_status();
        if ($current_status !== $wc_status) {
            $order->update_status($wc_status, sprintf(
                __('Steadfast delivery status updated to: %s', 'shipsync'),
                ucwords(str_replace('_', ' ', $delivery_status))
            ));
        }

        $order->update_meta_data('_steadfast_status', $delivery_status);
        $order->save();
    }
}
