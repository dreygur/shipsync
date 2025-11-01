<?php
/**
 * Dependency Injection Container
 * PSR-11 inspired simple DI container for managing dependencies
 *
 * @package ShipSync
 * @since 3.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Container {

    /**
     * Container instance (singleton)
     *
     * @var ShipSync_Container|null
     */
    private static ?ShipSync_Container $instance = null;

    /**
     * Registered services
     *
     * @var array
     */
    private array $services = [];

    /**
     * Service instances (singletons)
     *
     * @var array
     */
    private array $instances = [];

    /**
     * Service factories (callables)
     *
     * @var array
     */
    private array $factories = [];

    /**
     * Get container instance
     *
     * @return ShipSync_Container
     */
    public static function instance(): ShipSync_Container {
        if (null === self::$instance) {
            self::$instance = new self();
            self::$instance->register_core_services();
        }
        return self::$instance;
    }

    /**
     * Private constructor to enforce singleton
     */
    private function __construct() {}

    /**
     * Register core services
     *
     * @return void
     */
    private function register_core_services(): void {
        // Database & Repositories
        $this->singleton('database', function() {
            global $wpdb;
            return new ShipSync_Database($wpdb);
        });

        $this->singleton('order_repository', function($container) {
            return new ShipSync_Order_Repository($container->get('database'));
        });

        $this->singleton('courier_repository', function($container) {
            return new ShipSync_Courier_Repository($container->get('database'));
        });

        // Configuration
        $this->singleton('config', function() {
            return new ShipSync_Config();
        });

        // Services
        $this->singleton('order_service', function($container) {
            return new ShipSync_Order_Service(
                $container->get('order_repository'),
                $container->get('config')
            );
        });

        $this->singleton('courier_service', function($container) {
            return new ShipSync_Courier_Service(
                $container->get('courier_repository'),
                $container->get('courier_manager')
            );
        });

        // Courier Manager
        $this->singleton('courier_manager', function() {
            return new ShipSync_Courier_Manager();
        });

        // Validators
        $this->factory('order_validator', function() {
            return new ShipSync_Order_Validator();
        });

        $this->factory('courier_validator', function() {
            return new ShipSync_Courier_Validator();
        });

        // Allow extensions via hook
        do_action('shipsync_register_services', $this);
    }

    /**
     * Register a singleton service
     *
     * @param string   $id Service identifier
     * @param callable $factory Factory function to create the service
     * @return void
     */
    public function singleton(string $id, callable $factory): void {
        $this->services[$id] = $factory;
    }

    /**
     * Register a factory service (new instance each time)
     *
     * @param string   $id Service identifier
     * @param callable $factory Factory function to create the service
     * @return void
     */
    public function factory(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    /**
     * Get a service from the container
     *
     * @param string $id Service identifier
     * @return mixed Service instance
     * @throws RuntimeException If service not found
     */
    public function get(string $id) {
        // Check if it's a factory service
        if (isset($this->factories[$id])) {
            return call_user_func($this->factories[$id], $this);
        }

        // Check if already instantiated
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is registered
        if (!isset($this->services[$id])) {
            throw new RuntimeException(
                sprintf('Service "%s" not found in container', $id)
            );
        }

        // Create and cache the instance
        $this->instances[$id] = call_user_func($this->services[$id], $this);

        return $this->instances[$id];
    }

    /**
     * Check if a service exists
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    /**
     * Bind an instance directly
     *
     * @param string $id Service identifier
     * @param mixed  $instance Service instance
     * @return void
     */
    public function bind(string $id, $instance): void {
        $this->instances[$id] = $instance;
    }
}
