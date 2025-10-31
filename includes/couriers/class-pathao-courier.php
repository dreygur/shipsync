<?php
/**
 * Pathao Courier Integration
 * Implements Pathao Courier API via WordPress plugin integration
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/abstract-courier.php';
require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/class-pathao-api-wrapper.php';

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
        if (class_exists('ShipSync_Pathao_API_Wrapper') && !ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
        if (ShipSync_Pathao_API_Wrapper::is_plugin_active() && ShipSync_Pathao_API_Wrapper::is_configured()) {
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
            if (ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
            $use_plugin = ShipSync_Pathao_API_Wrapper::is_plugin_active() &&
                         ShipSync_Pathao_API_Wrapper::is_configured();
        }

        if ($use_plugin && ShipSync_Pathao_API_Wrapper::is_plugin_active() && ShipSync_Pathao_API_Wrapper::is_configured()) {
            // Merge default params
            $order_params = array_merge(array(
                'store_id' => isset($this->credentials['default_store_id']) ? $this->credentials['default_store_id'] : 1,
                'delivery_type' => isset($this->credentials['default_delivery_type']) ? $this->credentials['default_delivery_type'] : 48,
                'item_type' => isset($this->credentials['default_item_type']) ? $this->credentials['default_item_type'] : 2,
            ), $params);

            // Use the wrapper which uses the plugin's API
            $result = ShipSync_Pathao_API_Wrapper::create_order($order, $order_params);

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
        if (!ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
            $use_plugin = ShipSync_Pathao_API_Wrapper::is_plugin_active() &&
                         ShipSync_Pathao_API_Wrapper::is_configured();
        }

        if ($use_plugin && ShipSync_Pathao_API_Wrapper::is_plugin_active() && ShipSync_Pathao_API_Wrapper::is_configured()) {
            $default_params = array(
                'store_id' => isset($this->credentials['default_store_id']) ? $this->credentials['default_store_id'] : 1,
                'delivery_type' => isset($this->credentials['default_delivery_type']) ? $this->credentials['default_delivery_type'] : 48,
                'item_type' => isset($this->credentials['default_item_type']) ? $this->credentials['default_item_type'] : 2,
            );

            return ShipSync_Pathao_API_Wrapper::create_bulk_orders($orders, $default_params);
        }

        // Check if bundled plugin failed to load
        $message = __('Pathao Courier integration is not available.', 'shipsync');
        if (!ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
                return ShipSync_Pathao_API_Wrapper::get_order_status($order);
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
                return ShipSync_Pathao_API_Wrapper::get_order_status($orders[0]);
            }
        }

        $message = __('Order not found', 'shipsync');
        if (!ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
            ShipSync_Pathao_API_Wrapper::update_wc_order_status($order, $status);
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
        if (ShipSync_Pathao_API_Wrapper::is_plugin_active() && ShipSync_Pathao_API_Wrapper::is_configured()) {
            $use_plugin = isset($this->credentials['use_plugin_api']) && $this->credentials['use_plugin_api'] === 'yes';
            if (!$use_plugin) {
                $use_plugin = true; // Default to using plugin if available
            }

            if ($use_plugin) {
                $token = ShipSync_Pathao_API_Wrapper::get_token();
                if ($token) {
                    return true;
                }
                return new WP_Error('invalid_credentials', __('Invalid API credentials in Pathao Courier plugin', 'shipsync'));
            }
        }

        $message = __('Pathao Courier integration is not available.', 'shipsync');
        if (!ShipSync_Pathao_API_Wrapper::is_plugin_active()) {
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
}

