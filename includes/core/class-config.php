<?php
/**
 * Configuration Management
 * Centralized configuration handling with type safety and defaults
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Config {

    /**
     * Configuration cache
     *
     * @var array
     */
    private array $cache = [];

    /**
     * Configuration defaults
     *
     * @var array
     */
    private array $defaults = [
        'general' => [
            'orders_per_page' => 20,
            'courier_orders_per_page' => 10,
            'enable_notifications' => true,
            'show_courier_on_order_email' => true,
            'default_delivery_charge' => 150,
        ],
        'logging' => [
            'enable_courier_logs' => false,
            'enable_webhook_logs' => false,
            'log_retention_days' => 30,
        ],
        'security' => [
            'webhook_auth_enabled' => true,
            'webhook_auth_method' => 'token',
            'rate_limit_enabled' => true,
            'rate_limit_window' => 60,
            'rate_limit_max_requests' => 10,
        ],
        'cache' => [
            'enable_cache' => true,
            'cache_expiration' => HOUR_IN_SECONDS,
        ],
    ];

    /**
     * Constructor
     */
    public function __construct() {
        $this->load_configuration();
    }

    /**
     * Load configuration from database
     *
     * @return void
     */
    private function load_configuration(): void {
        // Load general settings
        $general_settings = get_option(ShipSync_Options::SETTINGS, []);
        $this->cache['general'] = wp_parse_args($general_settings, $this->defaults['general']);

        // Load logging settings
        $this->cache['logging'] = [
            'enable_courier_logs' => (bool) get_option(ShipSync_Options::ENABLE_COURIER_LOGS, false),
            'enable_webhook_logs' => (bool) get_option(ShipSync_Options::ENABLE_WEBHOOK_LOGS, false),
            'log_retention_days' => (int) get_option('shipsync_log_retention_days', 30),
        ];

        // Load security settings
        $this->cache['security'] = [
            'webhook_auth_enabled' => (bool) get_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, true),
            'webhook_auth_method' => get_option(ShipSync_Options::WEBHOOK_AUTH_METHOD, 'token'),
            'webhook_auth_token' => get_option(ShipSync_Options::WEBHOOK_AUTH_TOKEN, ''),
            'rate_limit_enabled' => (bool) get_option('shipsync_rate_limit_enabled', true),
            'rate_limit_window' => (int) get_option('shipsync_rate_limit_window', 60),
            'rate_limit_max_requests' => (int) get_option('shipsync_rate_limit_max_requests', 10),
        ];

        // Load cache settings
        $this->cache['cache'] = [
            'enable_cache' => (bool) get_option('shipsync_enable_cache', true),
            'cache_expiration' => (int) get_option('shipsync_cache_expiration', HOUR_IN_SECONDS),
        ];

        // Load courier settings
        $this->cache['courier_settings'] = get_option(ShipSync_Options::COURIER_SETTINGS, []);

        // Allow extensions via filter
        $this->cache = apply_filters('shipsync_config_loaded', $this->cache);
    }

    /**
     * Get configuration value with dot notation support
     *
     * @param string $key Configuration key (e.g., 'general.orders_per_page')
     * @param mixed  $default Default value if key not found
     * @return mixed Configuration value
     */
    public function get(string $key, $default = null) {
        $keys = explode('.', $key);
        $value = $this->cache;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Get all configuration in a section
     *
     * @param string $section Section name
     * @return array Section configuration
     */
    public function section(string $section): array {
        return $this->cache[$section] ?? [];
    }

    /**
     * Set configuration value
     *
     * @param string $key Configuration key
     * @param mixed  $value Configuration value
     * @param bool   $persist Whether to persist to database
     * @return bool Success status
     */
    public function set(string $key, $value, bool $persist = true): bool {
        $keys = explode('.', $key);
        $section = array_shift($keys);

        if (empty($keys)) {
            return false; // Need at least section.key
        }

        // Update cache
        if (!isset($this->cache[$section])) {
            $this->cache[$section] = [];
        }

        $target = &$this->cache[$section];
        $final_key = array_pop($keys);

        foreach ($keys as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }
            $target = &$target[$segment];
        }

        $target[$final_key] = $value;

        // Persist if requested
        if ($persist) {
            return $this->persist_section($section);
        }

        return true;
    }

    /**
     * Persist a configuration section to database
     *
     * @param string $section Section name
     * @return bool Success status
     */
    private function persist_section(string $section): bool {
        switch ($section) {
            case 'general':
                return update_option(ShipSync_Options::SETTINGS, $this->cache['general']);

            case 'logging':
                update_option(ShipSync_Options::ENABLE_COURIER_LOGS, $this->cache['logging']['enable_courier_logs']);
                update_option(ShipSync_Options::ENABLE_WEBHOOK_LOGS, $this->cache['logging']['enable_webhook_logs']);
                return update_option('shipsync_log_retention_days', $this->cache['logging']['log_retention_days']);

            case 'security':
                update_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, $this->cache['security']['webhook_auth_enabled']);
                update_option(ShipSync_Options::WEBHOOK_AUTH_METHOD, $this->cache['security']['webhook_auth_method']);
                update_option(ShipSync_Options::WEBHOOK_AUTH_TOKEN, $this->cache['security']['webhook_auth_token']);
                return true;

            case 'courier_settings':
                return update_option(ShipSync_Options::COURIER_SETTINGS, $this->cache['courier_settings']);

            default:
                return false;
        }
    }

    /**
     * Get courier-specific configuration
     *
     * @param string $courier_id Courier identifier
     * @return array Courier configuration
     */
    public function get_courier_config(string $courier_id): array {
        return $this->cache['courier_settings'][$courier_id] ?? [];
    }

    /**
     * Set courier-specific configuration
     *
     * @param string $courier_id Courier identifier
     * @param array  $config Configuration array
     * @return bool Success status
     */
    public function set_courier_config(string $courier_id, array $config): bool {
        if (!isset($this->cache['courier_settings'])) {
            $this->cache['courier_settings'] = [];
        }

        $this->cache['courier_settings'][$courier_id] = $config;

        return update_option(ShipSync_Options::COURIER_SETTINGS, $this->cache['courier_settings']);
    }

    /**
     * Reload configuration from database
     *
     * @return void
     */
    public function reload(): void {
        $this->cache = [];
        $this->load_configuration();
    }

    /**
     * Check if configuration key exists
     *
     * @param string $key Configuration key
     * @return bool
     */
    public function has(string $key): bool {
        $keys = explode('.', $key);
        $value = $this->cache;

        foreach ($keys as $segment) {
            if (!isset($value[$segment])) {
                return false;
            }
            $value = $value[$segment];
        }

        return true;
    }
}
