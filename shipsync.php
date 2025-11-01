<?php
/**
 * Plugin Name: ShipSync
 * Plugin URI: https://wordpress.org/plugins/shipsync
 * Description: Bangladesh courier integration for WooCommerce. Track deliveries with Steadfast, Pathao, RedX and other local courier services.
 * Version: 2.1.0
 * Author: Rakibul Yeasin
 * Author URI: https://profiles.wordpress.org/rakibulyeasin/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shipsync
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 * Network: false
 */

// Prevent direct access
if (!defined("ABSPATH")) {
    exit();
}

// Load Composer autoloader if available (for WP Dependency Installer)
$autoload_file = __DIR__ . "/vendor/autoload.php";
if (file_exists($autoload_file)) {
    require_once $autoload_file;
}

// Define plugin constants
define("SHIPSYNC_PLUGIN_URL", plugin_dir_url(__FILE__));
define("SHIPSYNC_PLUGIN_PATH", plugin_dir_path(__FILE__));
define("SHIPSYNC_VERSION", "2.1.0");

// Legacy constants for backwards compatibility
if (!defined("SHIPSYNC_PLUGIN_URL")) {
    define("SHIPSYNC_PLUGIN_URL", SHIPSYNC_PLUGIN_URL);
}
if (!defined("SHIPSYNC_PLUGIN_PATH")) {
    define("SHIPSYNC_PLUGIN_PATH", SHIPSYNC_PLUGIN_PATH);
}
if (!defined("SHIPSYNC_VERSION")) {
    define("SHIPSYNC_VERSION", SHIPSYNC_VERSION);
}

// Initialize WP Dependency Installer after WordPress functions are loaded (if available)
add_action(
    "plugins_loaded",
    function () {
        if (class_exists("WP_Dependency_Installer")) {
            WP_Dependency_Installer::instance()->run(SHIPSYNC_PLUGIN_PATH);
        }
    },
    1,
);

class ShipSync_Plugin
{
    public function __construct()
    {
        // WP Dependency Installer handles plugin dependencies
        add_action("init", [$this, "init"]);
        add_action("admin_notices", [$this, "dependency_notice"]);

        // Load all required class files (since we're not using Composer autoload)
        $this->load_classes();

        // Create database tables when WooCommerce is activated (if not already created)
        add_action("activated_plugin", [$this, "on_plugin_activated"]);

        register_activation_hook(__FILE__, [$this, "activate"]);
        register_deactivation_hook(__FILE__, [$this, "deactivate"]);
    }

    /**
     * Load all required class files
     */
    private function load_classes()
    {
        $includes_path = SHIPSYNC_PLUGIN_PATH . 'includes/';

        // Load constants first (needed by other classes)
        require_once $includes_path . 'class-constants.php';

        // Load core classes
        require_once $includes_path . 'class-database.php';

        // Load core utilities and base classes
        require_once $includes_path . 'core/class-helper.php';

        // Load services
        require_once $includes_path . 'services/class-order-service.php';
        require_once $includes_path . 'services/class-courier-service.php';

        // Load feature classes
        require_once $includes_path . 'class-woocommerce.php';
        require_once $includes_path . 'class-courier-manager.php';
        require_once $includes_path . 'class-courier-webhook.php';
        require_once $includes_path . 'class-ajax.php';
        require_once $includes_path . 'class-frontend.php';
        require_once $includes_path . 'class-notifications.php';
        require_once $includes_path . 'class-widget.php';

        // Load admin class only in admin
        if (is_admin()) {
            require_once $includes_path . 'class-admin.php';
        }

        // Pathao plugin loader is now integrated into class-courier-manager.php

        // Load courier abstract class and courier implementations
        require_once $includes_path . 'couriers/abstract-courier.php';
        require_once $includes_path . 'couriers/class-steadfast-courier.php';
        require_once $includes_path . 'couriers/class-pathao-courier.php';
        require_once $includes_path . 'couriers/class-redx-courier.php';
    }

    public function on_plugin_activated($plugin)
    {
        // Check if WooCommerce was just activated
        if ($plugin === "woocommerce/woocommerce.php") {
            // Flag to create tables on next page load
            set_transient(ShipSync_Transients::NEEDS_TABLE_CREATION, true, ShipSync_Defaults::CACHE_EXPIRATION);
        }
    }

    public function dependency_notice()
    {
        // Check if WooCommerce is active
        if (!class_exists("WooCommerce")) { ?>
            <div class="notice notice-error">
                <p>
                    <strong><?php _e(
                        "ShipSync",
                        "shipsync",
                    ); ?></strong>
                    <?php _e("requires", "shipsync"); ?>
                    <strong><?php _e(
                        "WooCommerce",
                        "shipsync",
                    ); ?></strong>
                    <?php _e(
                        "to be installed and active.",
                        "shipsync",
                    ); ?>
                    <?php if (current_user_can("install_plugins")): ?>
                        <br>
                        <a href="<?php echo admin_url(
                            "plugin-install.php?tab=plugin-information&plugin=woocommerce",
                        ); ?>"
                           class="button button-primary" style="margin-top: 10px;">
                            <?php _e(
                                "Install WooCommerce",
                                "shipsync",
                            ); ?>
                        </a>
                    <?php endif; ?>
                </p>
            </div>
            <?php }
    }

    public function init()
    {
        // Don't initialize if WooCommerce is not active
        if (!class_exists("WooCommerce")) {
            return;
        }

        // Create database tables if needed (on first load after activation)
        if (get_transient(ShipSync_Transients::NEEDS_TABLE_CREATION)) {
            $database = new ShipSync_Database();
            $database->create_tables();
            delete_transient(ShipSync_Transients::NEEDS_TABLE_CREATION);
        }

        // Load text domain
        load_plugin_textdomain(
            "shipsync",
            false,
            dirname(plugin_basename(__FILE__)) . "/languages",
        );

        // Initialize components (classes are loaded in constructor)
        $this->init_hooks();
    }

    private function init_hooks()
    {
        // Initialize database
        new ShipSync_Database();

        // Initialize WooCommerce integration
        new ShipSync_WooCommerce();

        // Initialize courier integrations
        ShipSync_Courier_Manager::instance();

        // Initialize courier webhook handler
        new ShipSync_Courier_Webhook();

        // Initialize admin
        if (is_admin()) {
            new ShipSync_Admin();
        }

        // Initialize frontend
        new ShipSync_Frontend();

        // Initialize AJAX
        new ShipSync_Ajax();

        // Initialize notifications
        new ShipSync_Notifications();

        // Register widget
        add_action("widgets_init", [$this, "register_widget"]);
    }

    public function register_widget()
    {
        register_widget("ShipSync_Order_Card_Widget");
    }

    public function activate()
    {
        try {
            // Check PHP version
            if (version_compare(PHP_VERSION, "7.4", "<")) {
                wp_die(
                    __(
                        "ShipSync requires PHP 7.4 or higher. You are running PHP " .
                            PHP_VERSION,
                        "shipsync",
                    ),
                );
            }

            // Set default options
            add_option(ShipSync_Options::VERSION, SHIPSYNC_VERSION);
            add_option(ShipSync_Options::SETTINGS, [
                "orders_per_page" => ShipSync_Defaults::ORDERS_PER_PAGE,
                "enable_notifications" => true,
                "show_courier_on_order_email" => true,
            ]);

            // Flag to create tables on first init when WooCommerce is available
            set_transient(ShipSync_Transients::NEEDS_TABLE_CREATION, true, ShipSync_Defaults::CACHE_EXPIRATION);

            // Flush rewrite rules (needed for webhook endpoints)
            flush_rewrite_rules();
        } catch (Exception $e) {
            // Log the error
            error_log(
                "ShipSync Activation Error: " . $e->getMessage(),
            );
            wp_die(
                __("Plugin activation failed: ", "shipsync") .
                    $e->getMessage(),
            );
        }
    }

    public function deactivate()
    {
        // Clean up scheduled hooks
        wp_clear_scheduled_hook("ocm_cleanup_expired_orders");

        // Flush rewrite rules
        flush_rewrite_rules();
    }
}

// Initialize the plugin
new ShipSync_Plugin();


