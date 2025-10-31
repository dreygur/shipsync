<?php
/**
 * RedX Courier Integration
 * Implements RedX Courier API via WordPress plugin integration
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/abstract-courier.php';
require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/class-redx-api-wrapper.php';

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
        if (class_exists('ShipSync_RedX_API_Wrapper') && !ShipSync_RedX_API_Wrapper::is_plugin_active()) {
            // Plugin not active, but we can still work with direct API calls (future implementation)
            add_action('admin_notices', array($this, 'plugin_dependency_notice'));
        }
    }

    /**
     * Show admin notice about plugin dependency
     */
    public function plugin_dependency_notice() {
        if (current_user_can('manage_options') && class_exists('ShipSync_RedX_API_Wrapper') && !ShipSync_RedX_API_Wrapper::is_plugin_active()) {
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
        if (ShipSync_RedX_API_Wrapper::is_plugin_active() && ShipSync_RedX_API_Wrapper::is_configured()) {
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
            $use_plugin = ShipSync_RedX_API_Wrapper::is_plugin_active() &&
                         ShipSync_RedX_API_Wrapper::is_configured();
        }

        if ($use_plugin && ShipSync_RedX_API_Wrapper::is_plugin_active() && ShipSync_RedX_API_Wrapper::is_configured()) {
            // Merge default params
            $order_params = array_merge(array(
                'parcel_weight' => isset($this->credentials['default_parcel_weight'])
                    ? intval($this->credentials['default_parcel_weight'])
                    : 1,
            ), $params);

            // Use the wrapper which uses the plugin's API
            $result = ShipSync_RedX_API_Wrapper::create_order($order, $order_params);

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
            $use_plugin = ShipSync_RedX_API_Wrapper::is_plugin_active() &&
                         ShipSync_RedX_API_Wrapper::is_configured();
        }

        if ($use_plugin && ShipSync_RedX_API_Wrapper::is_plugin_active() && ShipSync_RedX_API_Wrapper::is_configured()) {
            $default_params = array(
                'parcel_weight' => isset($this->credentials['default_parcel_weight'])
                    ? intval($this->credentials['default_parcel_weight'])
                    : 1,
            );

            return ShipSync_RedX_API_Wrapper::create_bulk_orders($orders, $default_params);
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
                return ShipSync_RedX_API_Wrapper::get_order_status($order);
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
                return ShipSync_RedX_API_Wrapper::get_order_status($orders[0]);
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
            ShipSync_RedX_API_Wrapper::update_wc_order_status($order, $status);
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
        if (ShipSync_RedX_API_Wrapper::is_plugin_active() && ShipSync_RedX_API_Wrapper::is_configured()) {
            $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
            if (!$use_plugin) {
                $use_plugin = true; // Default to using plugin if available
            }

            if ($use_plugin) {
                // Test by checking if API key exists
                $creds = ShipSync_RedX_API_Wrapper::get_credentials();
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
}

