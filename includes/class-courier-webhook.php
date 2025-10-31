<?php
/**
 * Courier Webhook Handler
 * Handles incoming webhooks from courier services
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Courier_Webhook {

    /**
     * Constructor
     */
    public function __construct() {
        // Register webhook endpoints
        add_action('rest_api_init', array($this, 'register_routes'));

        // Alternative: Use WordPress query vars for compatibility
        add_action('init', array($this, 'add_rewrite_rules'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'handle_webhook_request'));
    }

    /**
     * Register REST API routes for webhooks
     */
    public function register_routes() {
        register_rest_route('ocm/v1', '/webhook/(?P<courier>[a-zA-Z0-9-]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_webhook'),
            'permission_callback' => array($this, 'verify_webhook_authentication'),
            'args' => array(
                'courier' => array(
                    'required' => true,
                    'sanitize_callback' => 'sanitize_text_field'
                )
            )
        ));
    }

    /**
     * Verify webhook authentication
     * @param WP_REST_Request $request
     * @return bool|WP_Error
     */
    public function verify_webhook_authentication($request) {
        $auth_enabled = get_option('ocm_webhook_auth_enabled', false);

        // If authentication is not enabled, allow all requests
        if (!$auth_enabled) {
            return true;
        }

        $webhook_token = get_option('ocm_webhook_auth_token', '');

        // If no token is set, allow requests (backward compatibility)
        if (empty($webhook_token)) {
            return true;
        }

        $auth_method = get_option('ocm_webhook_auth_method', 'header');

        // Check header authentication (X-Webhook-Token)
        if ($auth_method === 'header' || $auth_method === 'both') {
            $header_token = $request->get_header('X-Webhook-Token');
            if (!empty($header_token) && hash_equals($webhook_token, $header_token)) {
                return true;
            }
        }

        // Check API token authentication (X-API-Token)
        if ($auth_method === 'api_token' || $auth_method === 'both') {
            $api_token = $request->get_header('X-API-Token');
            if (!empty($api_token) && hash_equals($webhook_token, $api_token)) {
                return true;
            }
        }

        // Check Bearer token authentication (Authorization: Bearer <token>)
        if ($auth_method === 'bearer' || $auth_method === 'both') {
            $auth_header = $request->get_header('Authorization');
            if (!empty($auth_header)) {
                // Check for Bearer token format
                if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
                    $bearer_token = trim($matches[1]);
                    if (hash_equals($webhook_token, $bearer_token)) {
                        return true;
                    }
                }
            }
        }

        // Check query parameter authentication
        if ($auth_method === 'query' || $auth_method === 'both') {
            $query_token = $request->get_param('token');
            if (!empty($query_token) && hash_equals($webhook_token, $query_token)) {
                return true;
            }
        }

        // Authentication failed
        return new WP_Error(
            'rest_forbidden',
            __('Webhook authentication failed. Invalid or missing token.', 'shipsync'),
            array('status' => 401)
        );
    }

    /**
     * Add rewrite rules for webhook endpoints
     */
    public function add_rewrite_rules() {
        add_rewrite_rule(
            '^ocm-webhook/([^/]*)/?',
            'index.php?shipsync_webhook=1&shipsync_courier=$matches[1]',
            'top'
        );
    }

    /**
     * Add custom query vars
     */
    public function add_query_vars($vars) {
        $vars[] = 'shipsync_webhook';
        $vars[] = 'shipsync_courier';

        // Backward compatibility
        $vars[] = 'ocm_webhook';
        $vars[] = 'ocm_courier';
        return $vars;
    }

    /**
     * Handle webhook request via query vars
     */
    public function handle_webhook_request() {
        $webhook_var = get_query_var('shipsync_webhook') ?: get_query_var('ocm_webhook'); // Backward compatibility
        if (!$webhook_var) {
            return;
        }

        $courier_id = get_query_var('shipsync_courier') ?: get_query_var('ocm_courier'); // Backward compatibility

        if (empty($courier_id)) {
            $this->send_response(array(
                'status' => 'error',
                'message' => 'Missing courier parameter'
            ), 400);
        }

        // Verify authentication for query var endpoints
        $auth_enabled = get_option('ocm_webhook_auth_enabled', false);
        if ($auth_enabled) {
            $webhook_token = get_option('ocm_webhook_auth_token', '');
            if (!empty($webhook_token)) {
                $auth_method = get_option('ocm_webhook_auth_method', 'header');
                $authenticated = false;

                // Check query parameter
                if ($auth_method === 'query' || $auth_method === 'both') {
                    $query_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
                    if (!empty($query_token) && hash_equals($webhook_token, $query_token)) {
                        $authenticated = true;
                    }
                }

                // Check header (X-Webhook-Token)
                if (!$authenticated && ($auth_method === 'header' || $auth_method === 'both')) {
                    $header_token = isset($_SERVER['HTTP_X_WEBHOOK_TOKEN']) ? sanitize_text_field($_SERVER['HTTP_X_WEBHOOK_TOKEN']) : '';
                    if (!empty($header_token) && hash_equals($webhook_token, $header_token)) {
                        $authenticated = true;
                    }
                }

                // Check API token (X-API-Token)
                if (!$authenticated && ($auth_method === 'api_token' || $auth_method === 'both')) {
                    $api_token = isset($_SERVER['HTTP_X_API_TOKEN']) ? sanitize_text_field($_SERVER['HTTP_X_API_TOKEN']) : '';
                    if (!empty($api_token) && hash_equals($webhook_token, $api_token)) {
                        $authenticated = true;
                    }
                }

                // Check Bearer token (Authorization: Bearer <token>)
                if (!$authenticated && ($auth_method === 'bearer' || $auth_method === 'both')) {
                    $auth_header = isset($_SERVER['HTTP_AUTHORIZATION']) ? sanitize_text_field($_SERVER['HTTP_AUTHORIZATION']) : '';
                    // Also check REDIRECT_HTTP_AUTHORIZATION for some server configurations
                    if (empty($auth_header) && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
                        $auth_header = sanitize_text_field($_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
                    }
                    if (!empty($auth_header)) {
                        // Check for Bearer token format
                        if (preg_match('/Bearer\s+(.+)$/i', $auth_header, $matches)) {
                            $bearer_token = trim($matches[1]);
                            if (hash_equals($webhook_token, $bearer_token)) {
                                $authenticated = true;
                            }
                        }
                    }
                }

                if (!$authenticated) {
                    $this->send_response(array(
                        'status' => 'error',
                        'message' => 'Webhook authentication failed. Invalid or missing token.'
                    ), 401);
                }
            }
        }

        // Get request body
        $payload = json_decode(file_get_contents('php://input'), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->send_response(array(
                'status' => 'error',
                'message' => 'Invalid JSON payload'
            ), 400);
        }

        $result = $this->process_webhook_data($courier_id, $payload);

        if ($result['success']) {
            $this->send_response(array(
                'status' => 'success',
                'message' => $result['message']
            ), 200);
        } else {
            $this->send_response(array(
                'status' => 'error',
                'message' => $result['message']
            ), 400);
        }
    }

    /**
     * Process webhook via REST API
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public function process_webhook($request) {
        $courier_id = $request->get_param('courier');
        $payload = $request->get_json_params();

        $result = $this->process_webhook_data($courier_id, $payload);

        if ($result['success']) {
            return new WP_REST_Response(array(
                'status' => 'success',
                'message' => $result['message']
            ), 200);
        }

        return new WP_REST_Response(array(
            'status' => 'error',
            'message' => $result['message']
        ), 400);
    }

    /**
     * Process webhook data
     * @param string $courier_id
     * @param array $payload
     * @return array
     */
    private function process_webhook_data($courier_id, $payload) {
        // Log webhook
        $this->log_webhook($courier_id, $payload);

        // Get courier instance
        $courier_manager = ShipSync_Courier_Manager::instance();
        $courier = $courier_manager->get_courier($courier_id);

        if (!$courier) {
            return array(
                'success' => false,
                'message' => 'Invalid courier service'
            );
        }

        // Process webhook
        $result = $courier->handle_webhook($payload);

        return $result;
    }

    /**
     * Log webhook data
     * @param string $courier_id
     * @param array $payload
     */
    private function log_webhook($courier_id, $payload) {
        if (!get_option('ocm_enable_webhook_logs', false)) {
            return;
        }

        $log_entry = array(
            'timestamp' => current_time('mysql'),
            'courier' => $courier_id,
            'payload' => $payload,
            'ip' => $this->get_client_ip()
        );

        // Store in transient for recent logs (last 50)
        $logs = get_transient('ocm_webhook_logs');
        if (!is_array($logs)) {
            $logs = array();
        }

        array_unshift($logs, $log_entry);
        $logs = array_slice($logs, 0, 50);

        set_transient('ocm_webhook_logs', $logs, DAY_IN_SECONDS);

        // Also trigger action for custom logging
        do_action('shipsync_webhook_received', $courier_id, $payload);
        do_action('ocm_webhook_received', $courier_id, $payload); // Backward compatibility
    }

    /**
     * Get client IP address
     * @return string
     */
    private function get_client_ip() {
        $ip = '';

        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }

        return sanitize_text_field($ip);
    }

    /**
     * Send JSON response and exit
     * @param array $data
     * @param int $status_code
     */
    private function send_response($data, $status_code = 200) {
        status_header($status_code);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    /**
     * Get webhook URL for a courier
     * @param string $courier_id
     * @return string
     */
    public static function get_webhook_url($courier_id) {
        // Try REST API endpoint first
        $rest_url = rest_url('ocm/v1/webhook/' . $courier_id);

        // Alternative: Use query var endpoint
        $query_url = home_url('ocm-webhook/' . $courier_id);

        $filtered_url = apply_filters('shipsync_webhook_url', $rest_url, $courier_id, $query_url);
        return apply_filters('ocm_webhook_url', $filtered_url, $courier_id, $query_url); // Backward compatibility
    }
}
