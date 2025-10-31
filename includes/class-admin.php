<?php
/**
 * Admin interface for ShipSync
 */

if (!defined("ABSPATH")) {
    exit();
}

class ShipSync_Admin
{
    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null)
    {
        $this->database = $database ?: ShipSync_Database::instance();
        add_action("admin_menu", [$this, "add_admin_menu"]);
        add_action("admin_enqueue_scripts", [$this, "enqueue_admin_scripts"]);
        add_action("admin_init", [$this, "handle_admin_actions"]);

        // Hide default WordPress footer on ShipSync admin pages
        add_action("admin_head", [$this, "hide_default_footer"]);
        add_action("admin_footer", [$this, "hide_default_footer_js"]);
    }

    public function add_admin_menu()
    {
        add_menu_page(
            __("ShipSync", "shipsync"),
            __("ShipSync", "shipsync"),
            "manage_options",
            "ocm-orders",
            [$this, "orders_page"],
            "dashicons-cart",
            30,
        );

        add_submenu_page(
            "ocm-orders",
            __("All Orders", "shipsync"),
            __("All Orders", "shipsync"),
            "manage_options",
            "ocm-orders",
            [$this, "orders_page"],
        );

        add_submenu_page(
            "ocm-orders",
            __("Courier Orders", "shipsync"),
            __("Courier Orders", "shipsync"),
            "manage_options",
            "ocm-courier-orders",
            [$this, "courier_orders_page"],
        );

        add_submenu_page(
            "ocm-orders",
            __("Courier Integration", "shipsync"),
            __("Courier Integration", "shipsync"),
            "manage_options",
            "ocm-courier-settings",
            [$this, "courier_settings_page"],
        );

        add_submenu_page(
            "ocm-orders",
            __("Settings", "shipsync"),
            __("Settings", "shipsync"),
            "manage_options",
            "ocm-settings",
            [$this, "settings_page"],
        );
    }

    public function enqueue_admin_scripts($hook)
    {
        if (strpos($hook, "shipsync-") !== false || strpos($hook, "ocm-") !== false) { // Support both old and new hooks
            wp_enqueue_style(
                "shipsync-admin-style",
                SHIPSYNC_PLUGIN_URL . "assets/css/admin.css",
                [],
                SHIPSYNC_VERSION,
            );
            wp_enqueue_style(
                "shipsync-admin-responsive",
                SHIPSYNC_PLUGIN_URL . "assets/css/admin-responsive.css",
                ["shipsync-admin-style"],
                SHIPSYNC_VERSION,
            );
            wp_enqueue_script(
                "shipsync-admin-script",
                SHIPSYNC_PLUGIN_URL . "assets/js/admin.js",
                ["jquery"],
                SHIPSYNC_VERSION,
                true,
            );

                wp_localize_script("shipsync-admin-script", "shipsyncAjax", [
                "ajax_url" => admin_url("admin-ajax.php"),
                "nonce" => wp_create_nonce("shipsync_nonce"),
                "strings" => [
                    "confirm_delete" => __(
                        "Are you sure you want to delete this item?",
                        "shipsync",
                    ),
                    "loading" => __("Loading...", "shipsync"),
                    "error" => __(
                        "An error occurred. Please try again.",
                        "shipsync",
                    ),
                    "select_courier" => __("Please select a courier service", "shipsync"),
                    "sending" => __("Sending...", "shipsync"),
                    "send_to_courier" => __("Send to Courier", "shipsync"),
                    "checking" => __("Checking...", "shipsync"),
                    "check_status" => __("Check Status", "shipsync"),
                    "status" => __("Status: ", "shipsync"),
                ],
            ]);

            // Enqueue orders-specific script for orders pages
            if (strpos($hook, "shipsync-orders") !== false || strpos($hook, "ocm-orders") !== false ||
                strpos($hook, "shipsync-courier-orders") !== false || strpos($hook, "ocm-courier-orders") !== false) {
                wp_enqueue_script(
                    "shipsync-admin-orders-script",
                    SHIPSYNC_PLUGIN_URL . "assets/js/admin-orders.js",
                    ["jquery", "shipsync-admin-script"],
                    SHIPSYNC_VERSION,
                    true,
                );

                wp_localize_script("shipsync-admin-orders-script", "shipSyncOrders", [
                    "nonce" => wp_create_nonce("shipsync_order_status"),
                    "ajaxUrl" => admin_url("admin-ajax.php"),
                    "i18n" => [
                        "confirmStatusChange" => __("Are you sure you want to change the order status?", "shipsync"),
                        "selectCourier" => __("Please select a courier service", "shipsync"),
                        "sending" => __("Sending...", "shipsync"),
                        "confirmAndShip" => __("Confirm & Ship", "shipsync"),
                        "error" => __("Error updating order status", "shipsync"),
                        "copyFailed" => __("Failed to copy to clipboard", "shipsync"),
                        "copied" => __("Copied to clipboard!", "shipsync"),
                    ],
                ]);
            }
        }
    }

    public function handle_admin_actions()
    {
        // Accept both old and new action names for backward compatibility
        $action = null;
        $nonce_valid = false;
        if (isset($_POST["shipsync_action"]) && wp_verify_nonce($_POST["shipsync_nonce"], "shipsync_action")) {
            $action = $_POST["shipsync_action"];
            $nonce_valid = true;
        } elseif (isset($_POST["ocm_action"]) && wp_verify_nonce($_POST["ocm_nonce"], "ocm_action")) {
            $action = $_POST["ocm_action"];
            $nonce_valid = true;
        }

        if ($nonce_valid && $action) {
            switch ($action) {
                case "update_order_status":
                    $this->handle_update_order_status();
                    break;
                case "assign_courier":
                    $this->handle_assign_courier();
                    break;
            }
        }
    }

    private function handle_update_order_status()
    {
        $order_id = intval($_POST["order_id"]);
        $status = sanitize_text_field($_POST["status"]);
        $notes = sanitize_textarea_field($_POST["notes"]);

        $result = $this->database->update_order_status($order_id, $status, $notes);

        if ($result) {
            add_action("admin_notices", function () {
                echo '<div class="notice notice-success"><p>' .
                    __(
                        "Order status updated successfully!",
                        "shipsync",
                    ) .
                    "</p></div>";
            });
        } else {
            add_action("admin_notices", function () {
                echo '<div class="notice notice-error"><p>' .
                    __(
                        "Error updating order status. Please try again.",
                        "shipsync",
                    ) .
                    "</p></div>";
            });
        }
    }

    private function handle_assign_courier()
    {
        $order_id = intval($_POST["order_id"]);
        $courier_id = intval($_POST["courier_id"]);

        $result = $this->database->assign_courier($order_id, $courier_id);

        if ($result !== false) {
            add_action("admin_notices", function () {
                echo '<div class="notice notice-success"><p>' .
                    __(
                        "Courier assigned successfully!",
                        "shipsync",
                    ) .
                    "</p></div>";
            });
        } else {
            add_action("admin_notices", function () {
                echo '<div class="notice notice-error"><p>' .
                    __(
                        "Error assigning courier. Please try again.",
                        "shipsync",
                    ) .
                    "</p></div>";
            });
        }
    }

    public function orders_page()
    {
        $orders = $this->database->get_orders(20);
        $couriers = $this->database->get_couriers();

        include SHIPSYNC_PLUGIN_PATH . "admin/orders.php";
    }

    public function courier_orders_page()
    {
        include SHIPSYNC_PLUGIN_PATH . "admin/courier-orders.php";
    }

    public function courier_settings_page()
    {
        include SHIPSYNC_PLUGIN_PATH . "admin/courier-settings.php";
    }

    public function settings_page()
    {
        $settings = get_option("ocm_settings", []);

        if (isset($_POST["save_settings"]) && check_admin_referer('shipsync_settings', 'shipsync_settings_nonce')) {
            // Save general settings
            $settings = [
                "orders_per_page" => intval($_POST["orders_per_page"] ?? 20),
                "enable_notifications" => isset($_POST["enable_notifications"]),
                "default_order_status" => sanitize_text_field(
                    $_POST["default_order_status"] ?? 'pending',
                ),
            ];

            update_option("ocm_settings", $settings);

            // Save widget settings
            if (isset($_POST["widget_title"])) {
                update_option("ocm_widget_title", sanitize_text_field($_POST["widget_title"]));
            }

            if (isset($_POST["widget_orders_limit"])) {
                $limit = intval($_POST["widget_orders_limit"]);
                $limit = max(1, min(20, $limit)); // Clamp between 1 and 20
                update_option("ocm_widget_orders_limit", $limit);
            }

            update_option("ocm_widget_show_status", isset($_POST["widget_show_status"]));
            update_option("ocm_widget_show_courier", isset($_POST["widget_show_courier"]));

            // Redirect to show success message
            wp_redirect(add_query_arg('settings-updated', 'true', admin_url('admin.php?page=ocm-settings')));
            exit;
        }

        include SHIPSYNC_PLUGIN_PATH . "admin/settings.php";
    }

    /**
     * Hide default WordPress footer on ShipSync admin pages (CSS)
     */
    public function hide_default_footer()
    {
        // Check if we're on a ShipSync admin page
        $shipsync_pages = ['ocm-orders', 'ocm-courier-orders', 'ocm-courier-settings', 'ocm-settings', 'ocm-couriers', 'ocm-add-courier', 'ocm-add-order'];

        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if (in_array($current_page, $shipsync_pages)) {
            ?>
            <style>
            /* Hide default WordPress admin footer on ShipSync pages */
            #wpfooter {
                display: none !important;
                visibility: hidden !important;
                height: 0 !important;
                overflow: hidden !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            /* Adjust content area to account for removed footer */
            #wpbody-content {
                padding-bottom: 20px;
            }

            /* Ensure footer replacement has proper positioning */
            .shipsync-admin-footer {
                position: relative;
            }
            </style>
            <?php
        }
    }

    /**
     * Hide default WordPress footer on ShipSync admin pages (JavaScript backup)
     */
    public function hide_default_footer_js()
    {
        // Check if we're on a ShipSync admin page
        $shipsync_pages = ['ocm-orders', 'ocm-courier-orders', 'ocm-courier-settings', 'ocm-settings', 'ocm-couriers', 'ocm-add-courier', 'ocm-add-order'];

        $current_page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';

        if (in_array($current_page, $shipsync_pages)) {
            ?>
            <script>
            (function() {
                // Hide footer immediately
                var footer = document.getElementById('wpfooter');
                if (footer) {
                    footer.style.display = 'none';
                    footer.style.visibility = 'hidden';
                    footer.style.height = '0';
                    footer.style.overflow = 'hidden';
                }

                // Also use jQuery when available
                if (typeof jQuery !== 'undefined') {
                    jQuery(document).ready(function($) {
                        $('#wpfooter').hide();
                    });
                }
            })();
            </script>
            <?php
        }
    }

    /**
     * Render plugin footer (similar to WooCommerce)
     * Displays rating request and version number
     */
    public static function render_admin_footer()
    {
        ?>
        <div class="shipsync-admin-footer">
            <div class="shipsync-footer-left">
                <?php
                printf(
                    /* translators: %s: Plugin name */
                    __('If you like <strong>%s</strong> please leave us a %s rating. A huge thanks in advance!', 'shipsync'),
                    __('ShipSync', 'shipsync'),
                    '<a href="https://wordpress.org/support/plugin/shipsync/reviews/?filter=5#new-post" target="_blank" rel="noopener noreferrer" style="text-decoration: none; color: #2271b1;">★★★★★</a>'
                );
                ?>
            </div>
            <div class="shipsync-footer-right">
                <?php
                printf(
                    /* translators: %s: Plugin version */
                    __('Version %s', 'shipsync'),
                    SHIPSYNC_VERSION
                );
                ?>
            </div>
        </div>
        <style>
        /* Ensure admin pages have proper structure for sticky footer */
        .wrap {
            position: relative;
            min-height: calc(100vh - 100px);
            padding-bottom: 80px;
            box-sizing: border-box;
        }

        .shipsync-admin-footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            width: 100%;
            max-width: 100%;
            margin: 0;
            margin-left: -20px;
            margin-right: -20px;
            padding: 15px 20px;
            border-top: 1px solid #dcdcde;
            background: #fff;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
            font-size: 13px;
            color: #646970;
            box-sizing: border-box;
            z-index: 10;
            box-shadow: 0 -1px 0 #dcdcde;
        }

        .shipsync-footer-left {
            flex: 1;
            min-width: 200px;
            white-space: normal;
            word-wrap: break-word;
        }

        .shipsync-footer-left a {
            text-decoration: none;
            color: #2271b1;
            font-weight: 600;
            margin-left: 2px;
            white-space: nowrap;
        }

        .shipsync-footer-left a:hover {
            color: #135e96;
        }

        .shipsync-footer-right {
            color: #646970;
            font-size: 13px;
            white-space: nowrap;
            flex-shrink: 0;
            min-width: fit-content;
        }

        /* Footer is already within #wpcontent, so no adjustment needed for menu */

        /* Responsive adjustments */
        @media (max-width: 782px) {
            .shipsync-admin-footer {
                left: 0;
                flex-direction: column;
                align-items: flex-start;
                padding: 12px 15px;
            }

            .shipsync-footer-left,
            .shipsync-footer-right {
                width: 100%;
                min-width: 0;
            }

            .shipsync-footer-right {
                text-align: left;
                margin-top: 5px;
            }
        }

        /* Adjust for mobile admin */
        @media screen and (max-width: 600px) {
            .shipsync-admin-footer {
                padding: 10px 12px;
            }
        }
        </style>
        <?php
    }
}
