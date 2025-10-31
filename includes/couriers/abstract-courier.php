<?php
/**
 * Abstract Courier Integration Class
 * Base class for all courier service integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class ShipSync_Abstract_Courier {

    /**
     * Courier service ID (unique identifier)
     * @var string
     */
    protected $courier_id;

    /**
     * Courier service name
     * @var string
     */
    protected $courier_name;

    /**
     * API credentials
     * @var array
     */
    protected $credentials = array();

    /**
     * Base API URL
     * @var string
     */
    protected $base_url;

    /**
     * Is the courier service enabled
     * @var bool
     */
    protected $enabled = false;

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_credentials();
    }

    /**
     * Load API credentials from settings
     */
    protected function load_credentials() {
        $settings = get_option('ocm_courier_settings', array());

        if (isset($settings[$this->courier_id])) {
            $this->credentials = $settings[$this->courier_id];
            $this->enabled = isset($this->credentials['enabled']) && $this->credentials['enabled'];
        }
    }

    /**
     * Get courier ID
     * @return string
     */
    public function get_id() {
        return $this->courier_id;
    }

    /**
     * Get courier name
     * @return string
     */
    public function get_name() {
        return $this->courier_name;
    }

    /**
     * Check if courier is enabled
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }

    /**
     * Get settings fields for admin
     * @return array
     */
    abstract public function get_settings_fields();

    /**
     * Create an order/consignment
     * @param WC_Order $order WooCommerce order object
     * @param array $params Additional parameters
     * @return array Response with status and data
     */
    abstract public function create_order($order, $params = array());

    /**
     * Create bulk orders
     * @param array $orders Array of WooCommerce order objects
     * @return array Response with status and data
     */
    abstract public function create_bulk_orders($orders);

    /**
     * Get delivery status
     * @param string $identifier Order identifier (tracking code, invoice, etc)
     * @param string $type Identifier type (tracking_code, invoice, consignment_id)
     * @return array Response with status and data
     */
    abstract public function get_delivery_status($identifier, $type = 'tracking_code');

    /**
     * Get account balance
     * @return array Response with status and balance
     */
    abstract public function get_balance();

    /**
     * Handle webhook callback
     * @param array $payload Webhook payload
     * @return array Response
     */
    abstract public function handle_webhook($payload);

    /**
     * Validate API credentials
     * @return bool|WP_Error True if valid, WP_Error on failure
     */
    abstract public function validate_credentials();

    /**
     * Get tracking URL for a tracking code
     * @param string $tracking_code Tracking code
     * @param string $consignment_id Optional consignment ID
     * @return string|null Tracking URL or null if not available
     */
    public function get_tracking_url($tracking_code, $consignment_id = null) {
        // Default implementation - return null
        // Child classes should override this method
        return null;
    }

    /**
     * Make HTTP request
     * @param string $endpoint API endpoint
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param array $data Request data
     * @param array $headers Additional headers
     * @return array|WP_Error Response array or WP_Error on failure
     */
    protected function make_request($endpoint, $method = 'GET', $data = array(), $headers = array()) {
        $url = trailingslashit($this->base_url) . ltrim($endpoint, '/');

        $args = array(
            'method' => $method,
            'headers' => $headers,
            'timeout' => 30,
        );

        if ($method === 'POST' || $method === 'PUT') {
            $args['body'] = json_encode($data);
            $args['headers']['Content-Type'] = 'application/json';
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Failed to decode JSON response');
        }

        return array(
            'status_code' => $status_code,
            'data' => $decoded
        );
    }

    /**
     * Log API activity
     * @param string $action Action performed
     * @param array $data Data related to action
     * @param bool $is_error Whether this is an error log
     */
    protected function log($action, $data = array(), $is_error = false) {
        if (!get_option('ocm_enable_courier_logs', false)) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'courier' => $this->courier_name,
            'action' => $action,
            'data' => $data,
            'is_error' => $is_error
        );

        do_action('shipsync_courier_log', $log_entry);
        do_action('ocm_courier_log', $log_entry); // Backward compatibility

        // Also log errors to WordPress debug log
        if ($is_error) {
            error_log(sprintf(
                'OCM Courier Error [%s]: %s - %s',
                $this->courier_name,
                $action,
                print_r($data, true)
            ));
        }
    }
}
