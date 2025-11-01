<?php
/**
 * Error Handler
 * Centralized error handling and logging
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Error_Handler {

    /**
     * Configuration instance
     *
     * @var ShipSync_Config
     */
    private ShipSync_Config $config;

    /**
     * Constructor
     *
     * @param ShipSync_Config $config Configuration instance
     */
    public function __construct(ShipSync_Config $config) {
        $this->config = $config;
    }

    /**
     * Create WP_Error from array
     *
     * @param array $errors Errors array [code => message]
     * @return WP_Error
     */
    public function create_wp_error(array $errors): WP_Error {
        $wp_error = new WP_Error();

        foreach ($errors as $code => $message) {
            $wp_error->add($code, $message);
        }

        return $wp_error;
    }

    /**
     * Create error response array
     *
     * @param string      $message Error message
     * @param string|null $code Optional error code
     * @param array       $data Optional additional data
     * @return array Error response
     */
    public function create_error_response(string $message, ?string $code = null, array $data = []): array {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => $data,
        ];
    }

    /**
     * Create success response array
     *
     * @param string $message Success message
     * @param array  $data Optional additional data
     * @return array Success response
     */
    public function create_success_response(string $message, array $data = []): array {
        return [
            'success' => true,
            'message' => $message,
            'data' => $data,
        ];
    }

    /**
     * Handle exception
     *
     * @param Exception $exception Exception to handle
     * @param string    $context Context description
     * @return WP_Error
     */
    public function handle_exception(Exception $exception, string $context = ''): WP_Error {
        $message = $exception->getMessage();
        $code = $exception->getCode() ?: 'exception';

        // Log the exception
        $this->log_error($message, [
            'context' => $context,
            'code' => $code,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Create user-friendly message
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $user_message = sprintf(
                __('Error in %s: %s', 'shipsync'),
                $context,
                $message
            );
        } else {
            $user_message = __('An error occurred. Please try again or contact support.', 'shipsync');
        }

        return new WP_Error($code, $user_message, [
            'exception' => $exception,
            'context' => $context,
        ]);
    }

    /**
     * Log error
     *
     * @param string $message Error message
     * @param array  $context Error context
     * @return void
     */
    public function log_error(string $message, array $context = []): void {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_message = sprintf(
            '[ShipSync Error] %s | Context: %s',
            $message,
            wp_json_encode($context)
        );

        error_log($log_message);

        // Store in database if enabled
        if ($this->config->get('logging.enable_courier_logs')) {
            $this->store_error_log($message, $context);
        }

        // Fire action for external logging
        do_action('shipsync_error_logged', $message, $context);
    }

    /**
     * Store error log in database
     *
     * @param string $message Error message
     * @param array  $context Error context
     * @return void
     */
    private function store_error_log(string $message, array $context): void {
        global $wpdb;

        $table = $wpdb->prefix . 'shipsync_logs';

        // Check if logs table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return;
        }

        $wpdb->insert(
            $table,
            [
                'type' => 'error',
                'message' => $message,
                'context' => wp_json_encode($context),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s']
        );
    }

    /**
     * Validate response structure
     *
     * @param array $response Response array to validate
     * @return bool Validation result
     */
    public function validate_response(array $response): bool {
        return isset($response['success']) &&
               isset($response['message']) &&
               is_bool($response['success']) &&
               is_string($response['message']);
    }

    /**
     * Sanitize error message for display
     *
     * @param string $message Error message
     * @return string Sanitized message
     */
    public function sanitize_message(string $message): string {
        // Remove sensitive information patterns
        $patterns = [
            '/api[_-]?key[:\s=]+[^\s&]+/i',
            '/secret[:\s=]+[^\s&]+/i',
            '/password[:\s=]+[^\s&]+/i',
            '/token[:\s=]+[^\s&]+/i',
        ];

        $replacements = [
            'api_key=[REDACTED]',
            'secret=[REDACTED]',
            'password=[REDACTED]',
            'token=[REDACTED]',
        ];

        $message = preg_replace($patterns, $replacements, $message);

        // Sanitize for output
        return wp_kses_post($message);
    }

    /**
     * Convert WP_Error to response array
     *
     * @param WP_Error $wp_error WP_Error object
     * @return array Error response array
     */
    public function wp_error_to_response(WP_Error $wp_error): array {
        $code = $wp_error->get_error_code();
        $message = $wp_error->get_error_message($code);
        $data = $wp_error->get_error_data($code);

        return $this->create_error_response($message, $code, $data ?: []);
    }

    /**
     * Is response successful
     *
     * @param array|WP_Error $response Response to check
     * @return bool
     */
    public function is_success($response): bool {
        if (is_wp_error($response)) {
            return false;
        }

        if (is_array($response) && isset($response['success'])) {
            return (bool) $response['success'];
        }

        return false;
    }
}
