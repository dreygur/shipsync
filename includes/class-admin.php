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
            "dashicons-store",
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
        $this->render_orders_page();
    }

    public function courier_orders_page()
    {
        $this->render_courier_orders_page();
    }

    public function courier_settings_page()
    {
        $this->render_courier_settings_page();
    }

    public function settings_page()
    {
        $settings = get_option(ShipSync_Options::SETTINGS, array());

        if (isset($_POST["save_settings"]) && check_admin_referer('shipsync_settings', 'shipsync_settings_nonce')) {
            // Save general settings
            $settings = [
                "orders_per_page" => intval($_POST["orders_per_page"] ?? 20),
                "enable_notifications" => isset($_POST["enable_notifications"]),
                "default_order_status" => sanitize_text_field(
                    $_POST["default_order_status"] ?? 'pending',
                ),
            ];

            update_option(ShipSync_Options::SETTINGS, $settings);

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

        $this->render_settings_page($settings);
    }

    /**
     * Hide default WordPress footer on ShipSync admin pages (CSS)
     */
    public function hide_default_footer()
    {
        // Check if we're on a ShipSync admin page
        $shipsync_pages = ['ocm-orders', 'ocm-courier-orders', 'ocm-courier-settings', 'ocm-settings'];

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
        $shipsync_pages = ['ocm-orders', 'ocm-courier-orders', 'ocm-courier-settings', 'ocm-settings'];

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
     * ============================================
     * RENDER METHODS (Merged from admin templates)
     * ============================================
     */

    /**
     * Render orders page
     */
    private function render_orders_page()
    {
        // Get filter parameters
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 10;

        // Get orders based on filter
        $args = array(
            'limit' => $per_page,
            'offset' => ($paged - 1) * $per_page,
            'orderby' => 'date',
            'order' => 'DESC',
            'return' => 'objects'
        );

        if ($status_filter !== 'all') {
            $args['status'] = $status_filter;
        }

        if (!empty($search)) {
            $args['s'] = $search;
        }

        $orders = wc_get_orders($args);

        // Get statistics
        $all_orders = wc_get_orders(array('limit' => -1, 'return' => 'ids'));
        $stats = array(
            'total' => count($all_orders),
            'pending' => count(wc_get_orders(array('status' => 'pending', 'limit' => -1, 'return' => 'ids'))),
            'processing' => count(wc_get_orders(array('status' => 'processing', 'limit' => -1, 'return' => 'ids'))),
            'delivered' => count(wc_get_orders(array('status' => 'completed', 'limit' => -1, 'return' => 'ids'))),
            'cancelled' => count(wc_get_orders(array('status' => 'cancelled', 'limit' => -1, 'return' => 'ids')))
        );

        $total_filtered = count(wc_get_orders(array_merge($args, array('limit' => -1, 'return' => 'ids'))));
        ?>
        <div class="wrap ocm-orders-dashboard">
            <h1 class="wp-heading-inline"><?php _e('Orders Management', 'shipsync'); ?></h1>
            <a href="<?php echo admin_url('post-new.php?post_type=shop_order'); ?>" class="page-title-action">
                <?php _e('Add New Order', 'shipsync'); ?>
            </a>
            <hr class="wp-header-end">

            <!-- Statistics Cards -->
            <div class="ocm-stats-cards">
                <div class="ocm-stat-card">
                    <div class="ocm-stat-label"><?php _e('Total Orders', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-pending">
                    <div class="ocm-stat-label"><?php _e('Pending', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['pending']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-processing">
                    <div class="ocm-stat-label"><?php _e('Processing', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['processing']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-delivered">
                    <div class="ocm-stat-label"><?php _e('Delivered', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['delivered']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-cancelled">
                    <div class="ocm-stat-label"><?php _e('Cancelled', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['cancelled']; ?></div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="ocm-search-bar">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ocm-orders">
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                    <input type="text"
                           name="s"
                           value="<?php echo esc_attr($search); ?>"
                           placeholder="<?php _e('Search by order ID, name, phone, or tracking code...', 'shipsync'); ?>"
                           class="ocm-search-input">
                    <button type="submit" class="button"><?php _e('Search', 'shipsync'); ?></button>
                    <?php if ($search): ?>
                        <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="button">
                            <?php _e('Clear', 'shipsync'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Status Filter Tabs -->
            <div class="ocm-status-tabs">
                <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=all'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <?php _e('All Orders', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=pending'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <?php _e('Pending', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=processing'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
                    <?php _e('Processing', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=completed'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
                    <?php _e('Delivered', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=cancelled'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    <?php _e('Cancelled', 'shipsync'); ?>
                </a>
            </div>

            <!-- Orders Table -->
            <div class="ocm-orders-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 10%;"><?php _e('ORDER ID', 'shipsync'); ?></th>
                            <th style="width: 12%;"><?php _e('CUSTOMER', 'shipsync'); ?></th>
                            <th style="width: 10%;"><?php _e('PHONE', 'shipsync'); ?></th>
                            <th style="width: 5%;"><?php _e('QTY', 'shipsync'); ?></th>
                            <th style="width: 10%;"><?php _e('TOTAL / COD', 'shipsync'); ?></th>
                            <th style="width: 12%;"><?php _e('ORDER STATUS', 'shipsync'); ?></th>
                            <th style="width: 12%;"><?php _e('DELIVERY STATUS', 'shipsync'); ?></th>
                            <th style="width: 18%;"><?php _e('TRACKING', 'shipsync'); ?></th>
                            <th style="width: 11%;"><?php _e('ACTIONS', 'shipsync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <?php
                                // Get tracking info from any courier service
                                $database = ShipSync_Database::instance();
                                $tracking_data = $database->get_tracking_code_from_order($order);
                                $status_data = $database->get_delivery_status_from_order($order);

                                $tracking_code = $tracking_data ? $tracking_data['tracking_code'] : null;
                                $consignment_id = $tracking_data ? $tracking_data['consignment_id'] : null;
                                $courier_service = $tracking_data ? $tracking_data['courier_service'] : null;
                                $delivery_status = $status_data ? $status_data['status'] : null;

                                // Get courier service display name
                                $courier_manager = ShipSync_Courier_Manager::instance();
                                $courier_service_name = '';
                                if ($courier_service) {
                                    $courier_obj = $courier_manager->get_courier($courier_service);
                                    if ($courier_obj) {
                                        $courier_service_name = $courier_obj->get_name();
                                    }
                                }

                                // Legacy courier (manual assignment)
                                $courier_id = $order->get_meta('_ocm_courier_id');
                                $courier_name = '';
                                if ($courier_id) {
                                    $courier = ShipSync_Database::instance()->get_courier_by_id($courier_id);
                                    if ($courier) {
                                        $courier_name = $courier->name;
                                    }
                                }

                                // Get order items
                                $items = $order->get_items();
                                $total_qty = 0;
                                foreach ($items as $item) {
                                    $total_qty += $item->get_quantity();
                                }
                                ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($order->get_order_number()); ?></strong>
                                        <br><small style="color: #666;"><?php echo $order->get_date_created()->date('d/m/Y'); ?></small>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($order->get_billing_phone()); ?></td>
                                    <td><strong><?php echo $total_qty; ?></strong></td>
                                    <td>
                                        <strong>৳<?php echo number_format($order->get_total(), 0); ?></strong>
                                        <?php
                                        // Get delivery charge based on courier service
                                        $delivery_charge = null;
                                        if ($courier_service === 'steadfast') {
                                            $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_DELIVERY_CHARGE);
                                        } elseif ($courier_service === 'pathao') {
                                            $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::PATHAO_DELIVERY_CHARGE);
                                        } elseif ($courier_service === 'redx') {
                                            $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::REDX_DELIVERY_CHARGE);
                                        }

                                        if ($delivery_charge):
                                        ?>
                                            <br><small style="color: #666;">+৳<?php echo number_format($delivery_charge, 0); ?> delivery</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <select class="ocm-order-status-select" data-order-id="<?php echo $order->get_id(); ?>" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                            <option value="pending" <?php selected($order->get_status(), 'pending'); ?>><?php _e('Pending', 'shipsync'); ?></option>
                                            <option value="processing" <?php selected($order->get_status(), 'processing'); ?>><?php _e('Processing', 'shipsync'); ?></option>
                                            <option value="out-shipping" <?php selected($order->get_status(), 'out-shipping'); ?>><?php _e('Out for Shipping', 'shipsync'); ?></option>
                                            <option value="on-hold" <?php selected($order->get_status(), 'on-hold'); ?>><?php _e('On Hold', 'shipsync'); ?></option>
                                            <option value="completed" <?php selected($order->get_status(), 'completed'); ?>><?php _e('Delivered', 'shipsync'); ?></option>
                                            <option value="cancelled" <?php selected($order->get_status(), 'cancelled'); ?>><?php _e('Cancelled', 'shipsync'); ?></option>
                                        </select>
                                    </td>
                                    <td>
                                        <?php if ($delivery_status): ?>
                                            <?php
                                            $status_class = 'ocm-status-badge';
                                            $status_text = ucfirst(str_replace('_', ' ', $delivery_status));

                                            switch ($delivery_status) {
                                                case 'delivered':
                                                case 'delivered_approval_pending':
                                                    $status_class .= ' status-delivered';
                                                    break;
                                                case 'cancelled':
                                                case 'cancelled_approval_pending':
                                                    $status_class .= ' status-cancelled';
                                                    break;
                                                case 'pending':
                                                    $status_class .= ' status-pending';
                                                    break;
                                                default:
                                                    $status_class .= ' status-in-review';
                                                    break;
                                            }
                                            ?>
                                            <span class="<?php echo $status_class; ?>">
                                                <?php echo esc_html($status_text); ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($tracking_code): ?>
                                            <?php if ($courier_service_name): ?>
                                                <small style="color: #666; font-size: 10px; display: block; margin-bottom: 4px;">
                                                    <?php echo esc_html($courier_service_name); ?>
                                                </small>
                                            <?php endif; ?>
                                            <?php
                                            // Get tracking URL from courier service
                                            $tracking_url = null;
                                            if ($courier_service && $courier_obj) {
                                                $tracking_url = $courier_obj->get_tracking_url($tracking_code, $consignment_id);
                                            }
                                            ?>
                                            <code class="ocm-tracking-code"
                                                  style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block; cursor: pointer; transition: all 0.2s; <?php echo $tracking_url ? 'color: #2271b1;' : ''; ?>"
                                                  <?php if ($tracking_url): ?>
                                                      data-tracking-url="<?php echo esc_attr($tracking_url); ?>"
                                                      title="<?php esc_attr_e('Click to copy tracking URL', 'shipsync'); ?>"
                                                  <?php endif; ?>>
                                                <?php echo esc_html($tracking_code); ?>
                                            </code>
                                            <?php if ($tracking_url): ?>
                                                <span class="dashicons dashicons-admin-page" style="font-size: 12px; color: #2271b1; margin-left: 4px; vertical-align: middle; cursor: pointer;" data-tracking-url="<?php echo esc_attr($tracking_url); ?>" title="<?php esc_attr_e('Copy tracking URL', 'shipsync'); ?>"></span>
                                            <?php endif; ?>
                                            <br><small style="color: #666; font-size: 11px;">
                                                <?php
                                                if ($delivery_status) {
                                                    echo __('Status:', 'shipsync') . ' ' . ucfirst(str_replace('_', ' ', $delivery_status));
                                                } elseif ($consignment_id) {
                                                    echo __('Consignment ID:', 'shipsync') . ' ' . esc_html($consignment_id);
                                                }
                                                ?>
                                            </small>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;"><?php _e('No tracking', 'shipsync'); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        // Prepare order details for copy/WhatsApp
                                        $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

                                        // Get customer note (special instructions from checkout)
                                        // This is the note customers add during checkout for special delivery instructions
                                        $customer_note = $order->get_customer_note();
                                        if (!empty($customer_note)) {
                                            $customer_note = wp_strip_all_tags($customer_note);
                                            $customer_note = trim($customer_note);
                                        } else {
                                            $customer_note = '';
                                        }

                                        $order_details = array(
                                            'order_id' => $order->get_order_number(),
                                            'customer_name' => $customer_name,
                                            'phone' => $order->get_billing_phone(),
                                            'address' => $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '') . ', ' . $order->get_billing_city(),
                                            'quantity' => $total_qty,
                                            'notes' => $customer_note
                                        );
                                        ?>
                                        <div class="ocm-action-buttons">
                                            <button class="ocm-copy-order" title="<?php _e('Copy Order Details', 'shipsync'); ?>"
                                                    data-order-details='<?php echo json_encode($order_details, JSON_UNESCAPED_UNICODE); ?>'>
                                                <span class="dashicons dashicons-admin-page"></span>
                                            </button>
                                            <button class="ocm-whatsapp-order" title="<?php _e('Send to WhatsApp', 'shipsync'); ?>"
                                                    data-order-details='<?php echo json_encode($order_details, JSON_UNESCAPED_UNICODE); ?>'>
                                                <span class="dashicons dashicons-share"></span>
                                            </button>
                                            <a href="<?php echo $order->get_edit_order_url(); ?>" class="ocm-action-btn" title="<?php _e('Edit Order', 'shipsync'); ?>">
                                                <span class="dashicons dashicons-edit"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 60px 20px;">
                                    <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;">
                                        <span class="dashicons dashicons-cart" style="font-size: 64px; width: 64px; height: 64px;"></span>
                                    </div>
                                    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1d2327;">
                                        <?php _e('No orders found', 'shipsync'); ?>
                                    </h3>
                                    <p style="margin: 0 0 20px 0; color: #646970; max-width: 500px; margin-left: auto; margin-right: auto;">
                                        <?php if ($search): ?>
                                            <?php _e('No orders match your search criteria. Try adjusting your filters or search terms.', 'shipsync'); ?>
                                        <?php elseif ($status_filter !== 'all'): ?>
                                            <?php _e('No orders match the selected status. Try selecting a different status filter or view all orders.', 'shipsync'); ?>
                                        <?php else: ?>
                                            <?php _e('Orders will appear here once customers place them.', 'shipsync'); ?>
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($search || $status_filter !== 'all'): ?>
                                        <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="button button-secondary">
                                            <span class="dashicons dashicons-filter" style="vertical-align: middle; margin-right: 5px;"></span>
                                            <?php _e('Clear Filters', 'shipsync'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_filtered > $per_page): ?>
                <div class="ocm-pagination">
                    <?php
                    $total_pages = ceil($total_filtered / $per_page);

                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('Previous', 'shipsync'),
                        'next_text' => __('Next', 'shipsync'),
                        'total' => $total_pages,
                        'current' => $paged,
                        'type' => 'list'
                    ));
                    ?>
                </div>
            <?php endif; ?>
        </div>

        <style>
        /* Additional page-specific styles */
        .ocm-orders-dashboard {
            background: #f0f0f1;
            margin: -10px -20px -20px -10px;
            padding: 20px;
        }

        .ocm-stat-pending .ocm-stat-value { color: #dba617; }
        .ocm-stat-processing .ocm-stat-value { color: #2271b1; }
        .ocm-stat-delivered .ocm-stat-value { color: #00a32a; }
        .ocm-stat-cancelled .ocm-stat-value { color: #d63638; }

        /* Courier Selection Modal */
        .ocm-courier-modal {
            position: fixed;
            z-index: 999999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
            display: none;
        }

        .ocm-courier-modal-content {
            background-color: #fefefe;
            margin: 10% auto;
            border: 1px solid #888;
            border-radius: 8px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.2);
        }

        .ocm-courier-modal-header {
            padding: 20px;
            border-bottom: 1px solid #ddd;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #f9f9f9;
            border-radius: 8px 8px 0 0;
        }

        .ocm-courier-modal-header h3 {
            margin: 0;
            color: #333;
        }

        .ocm-courier-modal-close {
            font-size: 28px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s;
        }

        .ocm-courier-modal-close:hover {
            color: #000;
        }

        .ocm-courier-modal-body {
            padding: 25px;
        }

        .ocm-courier-option {
            padding: 15px;
            margin-bottom: 10px;
            border: 2px solid #ddd;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
        }

        .ocm-courier-option:hover {
            border-color: #2271b1;
            background: #f0f6fc;
        }

        .ocm-courier-option input[type="radio"] {
            margin-right: 12px;
            width: 18px;
            height: 18px;
        }

        .ocm-courier-option label {
            margin: 0;
            cursor: pointer;
            font-size: 15px;
            font-weight: 500;
        }

        .ocm-courier-modal-footer {
            padding: 15px 25px;
            border-top: 1px solid #ddd;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            background: #f9f9f9;
            border-radius: 0 0 8px 8px;
        }
        </style>

        <!-- Courier Selection Modal -->
        <div id="ocm-courier-selection-modal" class="ocm-courier-modal">
            <div class="ocm-courier-modal-content">
                <div class="ocm-courier-modal-header">
                    <h3><?php _e('Select Courier Service', 'shipsync'); ?></h3>
                    <span class="ocm-courier-modal-close">&times;</span>
                </div>
                <div class="ocm-courier-modal-body">
                    <p style="margin-top: 0; color: #666;"><?php _e('Please select a courier service to ship this order:', 'shipsync'); ?></p>
                    <div id="ocm-courier-options">
                        <!-- Courier options will be dynamically inserted here -->
                    </div>
                </div>
                <div class="ocm-courier-modal-footer">
                    <button type="button" class="button ocm-courier-modal-close"><?php _e('Cancel', 'shipsync'); ?></button>
                    <button type="button" class="button button-primary" id="ocm-confirm-courier"><?php _e('Confirm & Ship', 'shipsync'); ?></button>
                </div>
            </div>
        </div>

        <?php self::render_admin_footer(); ?>
        <?php
    }

    /**
     * Render courier orders page
     */
    private function render_courier_orders_page()
    {
        // Get courier manager
        $courier_manager = ShipSync_Courier_Manager::instance();
        $enabled_couriers = $courier_manager->get_enabled_couriers();

        // Get database instance
        $database = ShipSync_Database::instance();

        // Get filter parameters
        $status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
        $per_page = 10;

        // Get all orders sent to courier services
        $orders = $database->get_courier_orders($status_filter, $search, $paged, $per_page);
        $total_orders = $database->count_courier_orders($status_filter, $search);

        // Get statistics
        $stats = $database->get_courier_orders_stats();
        ?>
        <div class="wrap ocm-courier-orders-dashboard">
            <h1 class="wp-heading-inline"><?php _e('Courier Orders', 'shipsync'); ?></h1>

            <?php if (!empty($enabled_couriers)): ?>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-settings'); ?>" class="page-title-action">
                    <?php _e('Manage Integrations', 'shipsync'); ?>
                </a>
            <?php endif; ?>

            <hr class="wp-header-end">

            <!-- Statistics Cards -->
            <div class="ocm-stats-cards" style="margin: 20px 0;">
                <div class="ocm-stat-card ocm-stat-total">
                    <div class="ocm-stat-label"><?php _e('Total', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['total']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-in-review">
                    <div class="ocm-stat-label"><?php _e('In Review', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['in_review']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-pending">
                    <div class="ocm-stat-label"><?php _e('Pending', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['pending']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-hold">
                    <div class="ocm-stat-label"><?php _e('On Hold', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['hold']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-delivered">
                    <div class="ocm-stat-label"><?php _e('Delivered', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['delivered']; ?></div>
                </div>

                <div class="ocm-stat-card ocm-stat-cancelled">
                    <div class="ocm-stat-label"><?php _e('Cancelled', 'shipsync'); ?></div>
                    <div class="ocm-stat-value"><?php echo $stats['cancelled']; ?></div>
                </div>
            </div>

            <!-- Search Bar -->
            <div class="ocm-search-bar" style="margin: 20px 0;">
                <form method="get" action="">
                    <input type="hidden" name="page" value="ocm-courier-orders">
                    <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
                    <input type="text"
                           name="s"
                           value="<?php echo esc_attr($search); ?>"
                           placeholder="<?php _e('Search by order ID, tracking code, consignment ID, name, or phone...', 'shipsync'); ?>"
                           class="ocm-search-input">
                    <button type="submit" class="button"><?php _e('Search', 'shipsync'); ?></button>
                    <?php if ($search): ?>
                        <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders'); ?>" class="button">
                            <?php _e('Clear', 'shipsync'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Status Filter Tabs -->
            <div class="ocm-status-tabs" style="margin: 20px 0;">
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=all'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
                    <?php _e('All', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=in_review'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'in_review' ? 'active' : ''; ?>">
                    <?php _e('In Review', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=pending'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
                    <?php _e('Pending', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=hold'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'hold' ? 'active' : ''; ?>">
                    <?php _e('On Hold', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=delivered'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'delivered' ? 'active' : ''; ?>">
                    <?php _e('Delivered', 'shipsync'); ?>
                </a>
                <a href="<?php echo admin_url('admin.php?page=ocm-courier-orders&status_filter=cancelled'); ?>"
                   class="ocm-tab <?php echo $status_filter === 'cancelled' ? 'active' : ''; ?>">
                    <?php _e('Cancelled', 'shipsync'); ?>
                </a>
            </div>

            <!-- Orders Table -->
            <div class="ocm-orders-table-container">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 10%;"><?php _e('ORDER ID', 'shipsync'); ?></th>
                            <th style="width: 12%;"><?php _e('COURIER SERVICE', 'shipsync'); ?></th>
                            <th style="width: 12%;"><?php _e('TRACKING', 'shipsync'); ?></th>
                            <th style="width: 13%;"><?php _e('CUSTOMER', 'shipsync'); ?></th>
                            <th style="width: 9%;"><?php _e('PHONE', 'shipsync'); ?></th>
                            <th style="width: 9%;"><?php _e('COD AMOUNT', 'shipsync'); ?></th>
                            <th style="width: 9%;"><?php _e('DELIVERY CHARGE', 'shipsync'); ?></th>
                            <th style="width: 13%;"><?php _e('STATUS', 'shipsync'); ?></th>
                            <th style="width: 13%;"><?php _e('LAST UPDATE', 'shipsync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($orders)): ?>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo esc_html($order->order_number); ?></strong>
                                        <?php if ($order->consignment_id): ?>
                                            <br><small style="color: #666;">#<?php echo esc_html($order->consignment_id); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($order->courier_service)): ?>
                                            <?php
                                            $courier_obj = $courier_manager->get_courier($order->courier_service);
                                            $courier_service_name = $courier_obj ? $courier_obj->get_name() : ucfirst($order->courier_service);
                                            ?>
                                            <strong style="color: #2271b1;"><?php echo esc_html($courier_service_name); ?></strong>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order->tracking_code): ?>
                                            <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; font-size: 11px;">
                                                <?php echo esc_html($order->tracking_code); ?>
                                            </code>
                                            <?php if ($order->consignment_id): ?>
                                                <br><small style="color: #666; font-size: 10px;">
                                                    <?php _e('Consignment:', 'shipsync'); ?> <?php echo esc_html($order->consignment_id); ?>
                                                </small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <strong><?php echo esc_html($order->customer_name); ?></strong>
                                    </td>
                                    <td><?php echo esc_html($order->customer_phone); ?></td>
                                    <td>
                                        <strong>৳<?php echo number_format($order->cod_amount, 0); ?></strong>
                                    </td>
                                    <td>
                                        <?php if ($order->delivery_charge): ?>
                                            ৳<?php echo number_format($order->delivery_charge, 0); ?>
                                        <?php else: ?>
                                            ৳<?php echo number_format($order->default_delivery_charge, 0); ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = 'ocm-status-badge';
                                        $status_text = ucfirst(str_replace('_', ' ', $order->delivery_status));

                                        switch ($order->delivery_status) {
                                            case 'delivered':
                                            case 'delivered_approval_pending':
                                                $status_class .= ' status-delivered';
                                                break;
                                            case 'cancelled':
                                            case 'cancelled_approval_pending':
                                                $status_class .= ' status-cancelled';
                                                break;
                                            case 'pending':
                                            case 'partial_delivered_approval_pending':
                                                $status_class .= ' status-pending';
                                                break;
                                            case 'hold':
                                                $status_class .= ' status-hold';
                                                break;
                                            case 'in_review':
                                            default:
                                                $status_class .= ' status-in-review';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>">
                                            <?php echo esc_html($status_text); ?>
                                        </span>
                                        <?php if ($order->status_message): ?>
                                            <br><small style="color: #666; font-size: 11px;">
                                                <?php echo esc_html(wp_trim_words($order->status_message, 10)); ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php echo date('d/m/Y, H:i', strtotime($order->updated_at)); ?>
                                        </small>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 60px 20px;">
                                    <div style="font-size: 64px; margin-bottom: 20px; opacity: 0.5;">
                                        <span class="dashicons dashicons-cart" style="font-size: 64px; width: 64px; height: 64px;"></span>
                                    </div>
                                    <h3 style="margin: 0 0 10px 0; font-size: 18px; color: #1d2327;">
                                        <?php _e('No courier orders found', 'shipsync'); ?>
                                    </h3>
                                    <p style="margin: 0 0 20px 0; color: #646970; max-width: 500px; margin-left: auto; margin-right: auto;">
                                        <?php _e('Orders sent to courier services will appear here. Start by enabling a courier service and sending orders.', 'shipsync'); ?>
                                    </p>
                                    <?php if (empty($enabled_couriers)): ?>
                                        <a href="<?php echo admin_url('admin.php?page=ocm-courier-settings'); ?>" class="button button-primary">
                                            <span class="dashicons dashicons-admin-settings" style="vertical-align: middle; margin-right: 5px;"></span>
                                            <?php _e('Configure Courier Services', 'shipsync'); ?>
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_orders > $per_page): ?>
                <div class="ocm-pagination" style="margin: 20px 0; text-align: right;">
                    <?php
                    $total_pages = ceil($total_orders / $per_page);

                    echo '<span style="margin-right: 10px;">';
                    printf(
                        __('Showing %d to %d of %d couriers', 'shipsync'),
                        (($paged - 1) * $per_page) + 1,
                        min($paged * $per_page, $total_orders),
                        $total_orders
                    );
                    echo '</span>';

                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('Previous', 'shipsync'),
                        'next_text' => __('Next', 'shipsync'),
                        'total' => $total_pages,
                        'current' => $paged,
                        'type' => 'list'
                    ));
                    ?>

                    <select id="ocm-per-page" style="margin-left: 10px;">
                        <option value="10" <?php selected($per_page, 10); ?>>10 per page</option>
                        <option value="25" <?php selected($per_page, 25); ?>>25 per page</option>
                        <option value="50" <?php selected($per_page, 50); ?>>50 per page</option>
                        <option value="100" <?php selected($per_page, 100); ?>>100 per page</option>
                    </select>
                </div>
            <?php endif; ?>
        </div>

        <?php self::render_admin_footer(); ?>

        <style>
        /* Additional page-specific styles */
        .ocm-courier-orders-dashboard {
            background: #f0f0f1;
            margin: -10px -20px -20px -10px;
            padding: 20px;
        }

        .ocm-stat-total .ocm-stat-value { color: #2271b1; }
        .ocm-stat-in-review .ocm-stat-value { color: #dba617; }
        .ocm-stat-pending .ocm-stat-value { color: #d63638; }
        .ocm-stat-hold .ocm-stat-value { color: #8c62aa; }
        .ocm-stat-delivered .ocm-stat-value { color: #00a32a; }
        .ocm-stat-cancelled .ocm-stat-value { color: #d63638; }

        .ocm-status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-delivered {
            background: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .status-pending {
            background: #fff3cd;
            color: #856404;
        }

        .status-hold {
            background: #e2d9f3;
            color: #6f42c1;
        }

        .status-in-review {
            background: #d1ecf1;
            color: #0c5460;
        }

        .ocm-pagination {
            background: white;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 15px;
        }

        .ocm-pagination .page-numbers {
            display: inline-block;
            padding: 8px 12px;
            margin: 0 2px;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            text-decoration: none;
            color: #2271b1;
        }

        .ocm-pagination .page-numbers.current {
            background: #ff5722;
            color: white;
            border-color: #ff5722;
        }

        .ocm-pagination .page-numbers:hover:not(.current) {
            background: #f0f0f1;
        }
        </style>

        <script>
        jQuery(document).ready(function($) {
            // Handle per page change
            $('#ocm-per-page').on('change', function() {
                var perPage = $(this).val();
                var url = new URL(window.location.href);
                url.searchParams.set('per_page', perPage);
                url.searchParams.set('paged', 1);
                window.location.href = url.toString();
            });
        });
        </script>
        <?php
    }

    /**
     * Render courier settings page
     */
    private function render_courier_settings_page()
    {
        $courier_manager = ShipSync_Courier_Manager::instance();
        $couriers = $courier_manager->get_couriers();

        // Handle form submission
        // Accept both old and new nonce names for backward compatibility
        $save_settings = false;
        if (isset($_POST['shipsync_save_courier_settings']) && check_admin_referer('shipsync_courier_settings', 'shipsync_courier_nonce')) {
            $save_settings = true;
        } elseif (isset($_POST['ocm_save_courier_settings']) && check_admin_referer('ocm_courier_settings', 'ocm_courier_nonce')) {
            $save_settings = true;
        }

        // Rate limiting for settings save
        if ($save_settings) {
            $user_id = get_current_user_id();
            $rate_limit_key = 'shipsync_settings_save_' . $user_id;
            $rate_limit_data = get_transient($rate_limit_key);

            if ($rate_limit_data === false) {
                $rate_limit_data = array('count' => 0, 'reset_time' => time() + ShipSync_Defaults::RATE_LIMIT_WINDOW);
            }

            $rate_limit_data['count']++;

            if ($rate_limit_data['count'] > ShipSync_Defaults::RATE_LIMIT_MAX_REQUESTS) {
                $remaining = $rate_limit_data['reset_time'] - time();
                echo '<div class="notice notice-error is-dismissible"><p>' .
                    sprintf(__('Too many save attempts. Please wait %d seconds before trying again.', 'shipsync'), $remaining) .
                    '</p></div>';
                $save_settings = false;
            } else {
                set_transient($rate_limit_key, $rate_limit_data, ShipSync_Defaults::RATE_LIMIT_WINDOW);
            }
        }

        // Get current active tab
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

        if ($save_settings) {
            // Load existing settings to preserve other couriers' settings
            $settings = get_option(ShipSync_Options::COURIER_SETTINGS, array());

            // Handle settings from General tab or individual courier tabs
            if ($active_tab === 'general') {
                // Handle "Enable All" checkbox from General tab
                if (isset($_POST['enable_all_couriers']) && $_POST['enable_all_couriers'] === '1') {
                    // Enable all courier services with validation
                    $enabled_count = 0;
                    $errors = array();

                    foreach ($couriers as $courier_id => $courier) {
                        if (!isset($settings[$courier_id])) {
                            $settings[$courier_id] = array();
                        }

                        // Check if courier can be enabled (basic validation)
                        // Note: Full credential validation happens when trying to use the courier
                        $settings[$courier_id]['enabled'] = true;
                        $enabled_count++;
                    }

                    // Show success notice with details
                    if ($enabled_count > 0) {
                        echo '<div class="notice notice-success is-dismissible"><p>' .
                            sprintf(__('%d courier service(s) enabled. Please configure credentials in each courier\'s settings tab for full functionality.', 'shipsync'), $enabled_count) .
                            '</p></div>';
                    }
                }
                // If "Enable All" is not checked, don't change anything - preserve existing settings
                // Users can enable/disable individually from each courier's tab
            } else {
                // Update only the courier on the current active tab
                // This preserves all other couriers' settings
                foreach ($couriers as $courier_id => $courier) {
                    // Check if this courier's settings are being submitted
                    // This happens when on that courier's tab
                    if (isset($_POST['courier_' . $courier_id])) {
                        // Initialize courier settings array if it doesn't exist
                        if (!isset($settings[$courier_id])) {
                            $settings[$courier_id] = array();
                        }

                        // Update settings for this courier
                        foreach ($courier->get_settings_fields() as $field_id => $field) {
                            $field_name = 'courier_' . $courier_id . '_' . $field_id;

                            if (isset($_POST[$field_name])) {
                                if ($field['type'] === 'checkbox') {
                                    $settings[$courier_id][$field_id] = $_POST[$field_name] === '1';
                                } else {
                                    $settings[$courier_id][$field_id] = sanitize_text_field($_POST[$field_name]);
                                }
                            } else if ($field['type'] === 'checkbox') {
                                // Checkbox not in POST means unchecked
                                $settings[$courier_id][$field_id] = false;
                            }
                        }
                    }
                    // If courier settings are not in POST, preserve existing settings (do nothing)
                }
            }

            update_option(ShipSync_Options::COURIER_SETTINGS, $settings);

            // Save other options
            update_option(ShipSync_Options::ENABLE_COURIER_LOGS, isset($_POST['enable_courier_logs']));
            update_option(ShipSync_Options::ENABLE_WEBHOOK_LOGS, isset($_POST['enable_webhook_logs']));

            // Save default courier
            if (isset($_POST['default_courier'])) {
                $ocm_settings = get_option(ShipSync_Options::SETTINGS, array());
                $ocm_settings['default_courier'] = sanitize_text_field($_POST['default_courier']);
                update_option(ShipSync_Options::SETTINGS, $ocm_settings);
            }

            // Save webhook authentication settings
            if (isset($_POST['webhook_auth_enabled'])) {
                update_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, true);

                if (isset($_POST['webhook_auth_token']) && !empty($_POST['webhook_auth_token'])) {
                    update_option(ShipSync_Options::WEBHOOK_AUTH_TOKEN, sanitize_text_field($_POST['webhook_auth_token']));
                }

                if (isset($_POST['webhook_auth_method'])) {
                    $method = sanitize_text_field($_POST['webhook_auth_method']);
                    if (in_array($method, array('header', 'api_token', 'bearer', 'query', 'both'))) {
                        update_option(ShipSync_Options::WEBHOOK_AUTH_METHOD, $method);
                    }
                }
            } else {
                update_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, false);
            }

            // Redirect with success parameter for better UX
            $redirect_url = add_query_arg(array(
                'page' => 'ocm-courier-settings',
                'tab' => $active_tab,
                'settings-saved' => '1'
            ), admin_url('admin.php'));

            wp_safe_redirect($redirect_url);
            exit;
        }
        ?>
        <div class="wrap shipsync-settings-wrap">
            <h1 class="shipsync-page-title">
                <span class="dashicons dashicons-admin-settings" style="margin-right: 8px; color: #2271b1;"></span>
                <?php _e('Courier Integration Settings', 'shipsync'); ?>
            </h1>
            <p class="description" style="margin-bottom: 20px; color: #646970;">
                <?php _e('Configure and manage your courier service integrations. Enable services, set credentials, and customize delivery options.', 'shipsync'); ?>
            </p>

            <h2 class="nav-tab-wrapper">
                <a href="?page=ocm-courier-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('General', 'shipsync'); ?>
                </a>
                <?php foreach ($couriers as $courier): ?>
                    <a href="?page=ocm-courier-settings&tab=<?php echo esc_attr($courier->get_id()); ?>" class="nav-tab <?php echo $active_tab === $courier->get_id() ? 'nav-tab-active' : ''; ?>">
                        <?php echo esc_html($courier->get_name()); ?>
                    </a>
                <?php endforeach; ?>
                <a href="?page=ocm-courier-settings&tab=webhooks" class="nav-tab <?php echo $active_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
                    <?php _e('Webhooks', 'shipsync'); ?>
                </a>
            </h2>

            <form method="post" action="">
                <?php wp_nonce_field('shipsync_courier_settings', 'shipsync_courier_nonce'); ?>
                <?php wp_nonce_field('ocm_courier_settings', 'ocm_courier_nonce'); // Backward compatibility ?>

                <?php if ($active_tab === 'general'): ?>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Enable All Courier Services', 'shipsync'); ?></th>
                            <td>
                                <?php
                                $current_settings = get_option(ShipSync_Options::COURIER_SETTINGS, array());
                                $all_enabled = true;
                                $enabled_list = array();
                                foreach ($couriers as $courier_id => $courier) {
                                    $courier_settings = isset($current_settings[$courier_id]) ? $current_settings[$courier_id] : array();
                                    $is_enabled = isset($courier_settings['enabled']) && $courier_settings['enabled'];
                                    if (!$is_enabled) {
                                        $all_enabled = false;
                                    } else {
                                        $enabled_list[] = $courier->get_name();
                                    }
                                }
                                ?>
                                <label>
                                    <input type="checkbox"
                                           id="enable_all_couriers"
                                           name="enable_all_couriers"
                                           value="1"
                                           <?php checked($all_enabled); ?>>
                                    <?php _e('Enable all courier services at once', 'shipsync'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Check this to enable all courier services (Steadfast, Pathao, RedX) at once. To enable/disable individual courier services, visit each courier\'s settings tab.', 'shipsync'); ?>
                                    <?php if (!empty($enabled_list)): ?>
                                        <br>
                                        <small style="color: #646970;">
                                            <?php echo sprintf(__('Currently enabled: %s', 'shipsync'), implode(', ', $enabled_list)); ?>
                                        </small>
                                    <?php endif; ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Default Courier Service', 'shipsync'); ?></th>
                            <td>
                                <?php
                                $enabled_couriers = $courier_manager->get_enabled_couriers();
                                $ocm_settings = get_option(ShipSync_Options::SETTINGS, array());
                                $default_courier = isset($ocm_settings['default_courier']) ? $ocm_settings['default_courier'] : '';
                                ?>
                                <select name="default_courier" style="min-width: 300px;">
                                    <option value=""><?php _e('None (Prompt for selection)', 'shipsync'); ?></option>
                                    <?php foreach ($enabled_couriers as $courier_id => $courier): ?>
                                        <option value="<?php echo esc_attr($courier_id); ?>" <?php selected($default_courier, $courier_id); ?>>
                                            <?php echo esc_html($courier->get_name()); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('Select a default courier service to use when changing order status to "Out for Shipping". If set, orders will automatically be sent to this courier without prompting.', 'shipsync'); ?>
                                    <br>
                                    <?php _e('If no default is set and multiple couriers are enabled, you will be prompted to select one.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enable Courier Logs', 'shipsync'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_courier_logs" value="1" <?php checked(get_option(ShipSync_Options::ENABLE_COURIER_LOGS, false)); ?>>
                                    <?php _e('Enable logging of courier API activities', 'shipsync'); ?>
                                </label>
                                <p class="description"><?php _e('Logs will be stored in WordPress debug log if WP_DEBUG_LOG is enabled.', 'shipsync'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Enable Webhook Logs', 'shipsync'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="enable_webhook_logs" value="1" <?php checked(get_option(ShipSync_Options::ENABLE_WEBHOOK_LOGS, false)); ?>>
                                    <?php _e('Enable logging of webhook activities', 'shipsync'); ?>
                                </label>
                                <p class="description"><?php _e('Recent webhook logs will be stored in transients for debugging.', 'shipsync'); ?></p>
                            </td>
                        </tr>
                    </table>

                <?php elseif ($active_tab === 'webhooks'): ?>
                    <h2><?php _e('Webhook Configuration', 'shipsync'); ?></h2>
                    <p><?php _e('Configure webhook authentication and endpoints. Use these URLs in your courier service dashboard to receive real-time status updates.', 'shipsync'); ?></p>

                    <table class="form-table" style="margin-top: 20px;">
                        <tr>
                            <th scope="row">
                                <label for="webhook_auth_enabled">
                                    <span class="dashicons dashicons-lock" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Enable Webhook Authentication', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           id="webhook_auth_enabled"
                                           name="webhook_auth_enabled"
                                           value="1"
                                           <?php checked(get_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, false)); ?>>
                                    <?php _e('Require authentication token for incoming webhooks', 'shipsync'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('When enabled, webhooks must include a valid authentication token in the request header or query parameter.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="webhook_auth_token">
                                    <span class="dashicons dashicons-admin-network" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Webhook Secret Token', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="webhook_auth_token"
                                       name="webhook_auth_token"
                                       value="<?php echo esc_attr(get_option(ShipSync_Options::WEBHOOK_AUTH_TOKEN, '')); ?>"
                                       class="regular-text"
                                       placeholder="<?php esc_attr_e('Enter your webhook secret token', 'shipsync'); ?>">
                                <button type="button" class="button button-small" id="generate-webhook-token" style="margin-left: 10px;">
                                    <span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle;"></span>
                                    <?php _e('Generate Token', 'shipsync'); ?>
                                </button>
                                <p class="description">
                                    <?php _e('Secret token for authenticating webhook requests. Use this token based on the selected authentication method.', 'shipsync'); ?>
                                    <br>
                                    <strong><?php _e('Example X-Webhook-Token:', 'shipsync'); ?></strong> <code>X-Webhook-Token: your-token-here</code>
                                    <br>
                                    <strong><?php _e('Example X-API-Token:', 'shipsync'); ?></strong> <code>X-API-Token: your-token-here</code>
                                    <br>
                                    <strong><?php _e('Example Bearer Token:', 'shipsync'); ?></strong> <code>Authorization: Bearer your-token-here</code>
                                    <br>
                                    <strong><?php _e('Example Query:', 'shipsync'); ?></strong> <code>?token=your-token-here</code>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="webhook_auth_method">
                                    <span class="dashicons dashicons-admin-settings" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Authentication Method', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="webhook_auth_method" name="webhook_auth_method">
                                    <?php $current_auth_method = get_option(ShipSync_Options::WEBHOOK_AUTH_METHOD, 'header'); ?>
                                    <option value="header" <?php selected($current_auth_method, 'header'); ?>>
                                        <?php _e('Header (X-Webhook-Token)', 'shipsync'); ?>
                                    </option>
                                    <option value="api_token" <?php selected($current_auth_method, 'api_token'); ?>>
                                        <?php _e('API Token (X-API-Token)', 'shipsync'); ?>
                                    </option>
                                    <option value="bearer" <?php selected($current_auth_method, 'bearer'); ?>>
                                        <?php _e('Bearer Token (Authorization Header)', 'shipsync'); ?>
                                    </option>
                                    <option value="query" <?php selected($current_auth_method, 'query'); ?>>
                                        <?php _e('Query Parameter (token)', 'shipsync'); ?>
                                    </option>
                                    <option value="both" <?php selected($current_auth_method, 'both'); ?>>
                                        <?php _e('Any Method (All Headers/Query)', 'shipsync'); ?>
                                    </option>
                                </select>
                                <p class="description">
                                    <?php _e('Choose how webhooks should send the authentication token. Bearer token uses standard Authorization header format.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <h3 style="margin-top: 30px;">
                        <span class="dashicons dashicons-admin-links" style="color: #2271b1; margin-right: 5px; vertical-align: middle;"></span>
                        <?php _e('Webhook Endpoints', 'shipsync'); ?>
                    </h3>
                    <p><?php _e('Copy and configure these webhook URLs in your courier service dashboard:', 'shipsync'); ?></p>

                    <table class="widefat striped">
                        <thead>
                        <tr>
                            <th><?php _e('Courier Service', 'shipsync'); ?></th>
                            <th><?php _e('Webhook URL', 'shipsync'); ?></th>
                            <th><?php _e('Authenticated URL', 'shipsync'); ?></th>
                        </tr>
                        </thead>
                        <tbody>
                            <?php
                            $webhook_auth_enabled = get_option(ShipSync_Options::WEBHOOK_AUTH_ENABLED, false);
                            $webhook_token = get_option(ShipSync_Options::WEBHOOK_AUTH_TOKEN, '');
                            $webhook_auth_method = get_option(ShipSync_Options::WEBHOOK_AUTH_METHOD, 'header');
                            foreach ($couriers as $courier):
                                $base_url = ShipSync_Courier_Webhook::get_webhook_url($courier->get_id());
                                $auth_url = $base_url;
                                if ($webhook_auth_enabled && !empty($webhook_token)) {
                                    if ($webhook_auth_method === 'query' || $webhook_auth_method === 'both') {
                                        $auth_url = add_query_arg('token', $webhook_token, $base_url);
                                    }
                                }
                            ?>
                                <tr>
                                    <td><strong><?php echo esc_html($courier->get_name()); ?></strong></td>
                                    <td>
                                        <code style="display: block; padding: 8px; background: #f5f5f5; margin: 5px 0; word-break: break-all;">
                                            <?php echo esc_url($base_url); ?>
                                        </code>
                                        <button type="button" class="button button-small ocm-copy-webhook" data-url="<?php echo esc_attr($base_url); ?>">
                                            <?php _e('Copy URL', 'shipsync'); ?>
                                        </button>
                                    </td>
                                    <td>
                                        <?php if ($webhook_auth_enabled && !empty($webhook_token)): ?>
                                            <code style="display: block; padding: 8px; background: #f5f5f5; margin: 5px 0; word-break: break-all;">
                                                <?php echo esc_url($auth_url); ?>
                                            </code>
                                            <button type="button" class="button button-small ocm-copy-webhook" data-url="<?php echo esc_attr($auth_url); ?>">
                                                <?php _e('Copy Authenticated URL', 'shipsync'); ?>
                                            </button>
                                            <?php if ($webhook_auth_method === 'header' || $webhook_auth_method === 'api_token' || $webhook_auth_method === 'bearer' || $webhook_auth_method === 'both'): ?>
                                                <p class="description" style="margin-top: 8px; font-size: 12px; color: #646970;">
                                                    <?php if ($webhook_auth_method === 'header'): ?>
                                                        <?php _e('Use header:', 'shipsync'); ?> <code>X-Webhook-Token: <?php echo esc_html($webhook_token); ?></code>
                                                    <?php elseif ($webhook_auth_method === 'api_token'): ?>
                                                        <?php _e('Use header:', 'shipsync'); ?> <code>X-API-Token: <?php echo esc_html($webhook_token); ?></code>
                                                    <?php elseif ($webhook_auth_method === 'bearer'): ?>
                                                        <?php _e('Use Bearer token:', 'shipsync'); ?> <code>Authorization: Bearer <?php echo esc_html($webhook_token); ?></code>
                                                    <?php else: ?>
                                                        <?php _e('Use any of:', 'shipsync'); ?>
                                                        <br>
                                                        <code>X-Webhook-Token: <?php echo esc_html($webhook_token); ?></code>
                                                        <br>
                                                        <code>X-API-Token: <?php echo esc_html($webhook_token); ?></code>
                                                        <br>
                                                        <code>Authorization: Bearer <?php echo esc_html($webhook_token); ?></code>
                                                    <?php endif; ?>
                                                </p>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span style="color: #646970; font-style: italic;">
                                                <?php _e('Authentication not enabled', 'shipsync'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <?php
                    // Show recent webhook logs
                    $logs = get_transient('ocm_webhook_logs');
                    if (!empty($logs) && is_array($logs)):
                    ?>
                        <h3 style="margin-top: 30px;"><?php _e('Recent Webhook Logs', 'shipsync'); ?></h3>
                        <table class="widefat striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Time', 'shipsync'); ?></th>
                                    <th><?php _e('Courier', 'shipsync'); ?></th>
                                    <th><?php _e('Type', 'shipsync'); ?></th>
                                    <th><?php _e('Details', 'shipsync'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                                    <tr>
                                        <td><?php echo esc_html($log['timestamp']); ?></td>
                                        <td><?php echo esc_html($log['courier']); ?></td>
                                        <td><?php echo isset($log['payload']['notification_type']) ? esc_html($log['payload']['notification_type']) : '-'; ?></td>
                                        <td>
                                            <details>
                                                <summary style="cursor: pointer;"><?php _e('View Payload', 'shipsync'); ?></summary>
                                                <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;"><?php echo esc_html(wp_json_encode($log['payload'], JSON_PRETTY_PRINT)); ?></pre>
                                            </details>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>

                <?php else:
                    // Courier-specific settings
                    $courier = $courier_manager->get_courier($active_tab);
                    if ($courier):
                        $fields = $courier->get_settings_fields();
                        $current_settings = get_option(ShipSync_Options::COURIER_SETTINGS, array());
                        $courier_settings = isset($current_settings[$active_tab]) ? $current_settings[$active_tab] : array();
                    ?>
                        <input type="hidden" name="courier_<?php echo esc_attr($active_tab); ?>" value="1">

                        <h2><?php echo esc_html($courier->get_name()); ?> <?php _e('Settings', 'shipsync'); ?></h2>

                        <table class="form-table">
                            <?php
                            foreach ($fields as $field_id => $field):
                                // Skip credential fields if plugin is active and configured
                                $skip_credential_fields = false;
                                if ($active_tab === 'steadfast' && method_exists($courier, 'is_plugin_active') && method_exists($courier, 'is_configured')) {
                                    $skip_credential_fields = $courier->is_plugin_active() && $courier->is_configured();
                                }
                                if ($skip_credential_fields && ($field_id === 'api_key' || $field_id === 'secret_key')) {
                                    continue;
                                }
                            ?>
                                <?php if ($field['type'] === 'html'): ?>
                                    <tr>
                                        <td colspan="2">
                                            <?php echo wp_kses_post($field['html']); ?>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                <tr>
                                    <th scope="row">
                                        <label for="courier_<?php echo esc_attr($active_tab . '_' . $field_id); ?>">
                                            <?php echo esc_html($field['title']); ?>
                                            <?php if (isset($field['required']) && $field['required']): ?>
                                                <span class="required" style="color: red;">*</span>
                                            <?php endif; ?>
                                        </label>
                                    </th>
                                    <td>
                                        <?php
                                        $field_name = 'courier_' . $active_tab . '_' . $field_id;
                                        $field_value = isset($courier_settings[$field_id]) ? $courier_settings[$field_id] : (isset($field['default']) ? $field['default'] : '');

                                        switch ($field['type']):
                                            case 'text':
                                            case 'password':
                                                ?>
                                                <input type="<?php echo esc_attr($field['type']); ?>"
                                                       id="<?php echo esc_attr($field_name); ?>"
                                                       name="<?php echo esc_attr($field_name); ?>"
                                                       value="<?php echo esc_attr($field_value); ?>"
                                                       class="regular-text"
                                                       <?php echo isset($field['required']) && $field['required'] ? 'required' : ''; ?>>
                                                <?php
                                                break;

                                            case 'checkbox':
                                                ?>
                                                <label>
                                                    <input type="checkbox"
                                                           id="<?php echo esc_attr($field_name); ?>"
                                                           name="<?php echo esc_attr($field_name); ?>"
                                                           value="1"
                                                           <?php checked($field_value, true); ?>>
                                                    <?php if (isset($field['label'])): ?>
                                                        <?php echo esc_html($field['label']); ?>
                                                    <?php endif; ?>
                                                </label>
                                                <?php
                                                break;

                                            case 'select':
                                                ?>
                                                <select id="<?php echo esc_attr($field_name); ?>"
                                                        name="<?php echo esc_attr($field_name); ?>">
                                                    <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                                        <option value="<?php echo esc_attr($option_value); ?>"
                                                                <?php selected($field_value, $option_value); ?>>
                                                            <?php echo esc_html($option_label); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <?php
                                                break;

                                            case 'textarea':
                                                ?>
                                                <textarea id="<?php echo esc_attr($field_name); ?>"
                                                          name="<?php echo esc_attr($field_name); ?>"
                                                          rows="5"
                                                          class="large-text"><?php echo esc_textarea($field_value); ?></textarea>
                                                <?php
                                                break;
                                        endswitch;
                                        ?>

                                        <?php if (isset($field['description']) && $field['type'] !== 'html'): ?>
                                            <p class="description"><?php echo esc_html($field['description']); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </table>

                    <?php endif; ?>
                <?php endif; ?>

                <div class="shipsync-save-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde;">
                    <button type="submit"
                            name="ocm_save_courier_settings"
                            id="shipsync-save-settings-btn"
                            class="button button-primary button-large"
                            aria-label="<?php esc_attr_e('Save all courier integration settings', 'shipsync'); ?>">
                        <span class="dashicons dashicons-yes-alt" style="margin-right: 5px; vertical-align: middle;"></span>
                        <span class="shipsync-save-text"><?php _e('Save Settings', 'shipsync'); ?></span>
                    </button>
                    <span class="description" style="margin-left: 15px; color: #646970;">
                        <?php _e('Save your changes to apply the settings.', 'shipsync'); ?>
                    </span>
                    <div id="shipsync-save-feedback" style="margin-left: 15px; display: none;"></div>
                </div>
            </form>
        </div>

        <?php self::render_admin_footer(); ?>

        <script>
        jQuery(document).ready(function($) {
            // Warn before changing courier service on order edit page
            var $courierServiceSelect = $('#shipsync_courier_service_select');
            if ($courierServiceSelect.length) {
                var originalValue = $courierServiceSelect.val();
                $courierServiceSelect.on('change', function() {
                    var newValue = $(this).val();
                    if (originalValue && originalValue !== newValue && originalValue !== '') {
                        var confirmed = confirm('<?php echo esc_js(__('Changing the courier service may affect existing tracking information. Are you sure you want to continue?', 'shipsync')); ?>');
                        if (!confirmed) {
                            $(this).val(originalValue);
                            return false;
                        }
                    }
                    originalValue = newValue;
                });
            }

            // Add save feedback for settings page
            var $saveForm = $('form[method="post"]');
            if ($saveForm.length && $('#shipsync-save-settings-btn').length) {
                var $saveBtn = $('#shipsync-save-settings-btn');
                var $saveText = $saveBtn.find('.shipsync-save-text');
                var $saveIcon = $saveBtn.find('.dashicons');
                var $feedback = $('#shipsync-save-feedback');

                $saveForm.on('submit', function() {
                    // Show loading state
                    $saveBtn.prop('disabled', true);
                    $saveIcon.removeClass('dashicons-yes-alt').addClass('dashicons-update ocm-spinning');
                    $saveText.text('<?php echo esc_js(__('Saving...', 'shipsync')); ?>');
                    $feedback.hide();
                });

                // Check if we're coming back from a successful save
                if (window.location.search.indexOf('settings-saved=1') !== -1) {
                    $feedback.html('<span class="dashicons dashicons-yes-alt" style="color: #00a32a; vertical-align: middle;"></span> <span style="color: #00a32a; font-weight: 500;"><?php echo esc_js(__('Settings saved successfully!', 'shipsync')); ?></span>')
                             .show()
                             .fadeOut(5000);

                    // Clean URL
                    var newUrl = window.location.href.replace(/[?&]settings-saved=1/, '');
                    history.replaceState({}, document.title, newUrl);
                }
            }

            // Copy webhook URL
            $('.ocm-copy-webhook').on('click', function() {
                var url = $(this).data('url');
                var $btn = $(this);

                // Create temporary input
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(url).select();
                document.execCommand('copy');
                $temp.remove();

                // Show feedback
                $btn.text('<?php _e('Copied!', 'shipsync'); ?>');
                setTimeout(function() {
                    $btn.text('<?php _e('Copy URL', 'shipsync'); ?>');
                }, 2000);
            });

            // Generate webhook token
            $('#generate-webhook-token').on('click', function() {
                var token = 'shipsync_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
                $('#webhook_auth_token').val(token);
            });

            // Test courier connection
            $('#test-courier-connection').on('click', function() {
                var $btn = $(this);
                var courier = $btn.data('courier');
                var $result = $('#test-result');

                var originalHtml = $btn.html();
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: ocm-spin 1s linear infinite; margin-right: 5px; vertical-align: middle;"></span><?php _e('Testing...', 'shipsync'); ?>');
                $result.html('');

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'shipsync_validate_courier_credentials',
                        courier: courier,
                        nonce: '<?php echo wp_create_nonce('shipsync_settings_nonce'); ?>'
                    },
                    success: function(response) {
                        var icon = response.success ? '<span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px;"></span>' : '<span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 5px;"></span>';
                        var noticeClass = response.success ? 'notice-success' : 'notice-error';
                        $result.html('<div class="notice ' + noticeClass + ' inline shipsync-test-result"><p>' + icon + response.data.message + '</p></div>');

                        // Auto-dismiss after 8 seconds
                        setTimeout(function() {
                            $result.fadeOut(300, function() {
                                $(this).html('').show();
                            });
                        }, 8000);

                        $btn.prop('disabled', false).html(originalHtml);
                    },
                    error: function() {
                        $result.html('<div class="notice notice-error inline shipsync-test-result"><p><span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 5px;"></span><?php _e('An error occurred while testing the connection. Please try again.', 'shipsync'); ?></p></div>');
                        $btn.prop('disabled', false).html(originalHtml);
                    }
                });
            });
        });
        </script>

        <style>
        .shipsync-settings-wrap {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
        }

        .shipsync-page-title {
            font-size: 23px;
            font-weight: 400;
            margin: 0 0 8px 0;
            padding: 9px 0 4px 0;
            line-height: 1.3;
        }

        .nav-tab-wrapper {
            margin-bottom: 25px;
            border-bottom: 1px solid #dcdcde;
        }

        .nav-tab {
            padding: 8px 15px;
            font-weight: 500;
            transition: all 0.2s;
        }

        .nav-tab:hover {
            background: #f0f0f1;
        }

        .nav-tab-active {
            border-bottom: 2px solid #2271b1;
            background: #fff;
        }

        .form-table {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            overflow: hidden;
        }

        .form-table th {
            padding: 15px 20px;
            background: #f6f7f7;
            font-weight: 600;
            width: 200px;
        }

        .form-table td {
            padding: 15px 20px;
        }

        .shipsync-test-connection-card {
            transition: box-shadow 0.2s;
        }

        .shipsync-test-connection-card:hover {
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .shipsync-save-actions {
            display: flex;
            align-items: center;
        }

        .shipsync-test-result {
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        @keyframes ocm-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .ocm-admin-notice {
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-table tr:not(:last-child) {
            border-bottom: 1px solid #f0f0f1;
        }

        .plugin-status-notice {
            padding: 15px 20px;
            border-radius: 4px;
            margin: 20px 0;
        }

        .plugin-status-notice p {
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .plugin-status-notice strong {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .notice.inline {
            margin: 10px 0 0 0;
            padding: 10px;
        }
        </style>
        <?php
    }

    /**
     * Render settings page
     */
    private function render_settings_page($settings)
    {
        // Show success message if settings were saved
        if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
            <div class="notice notice-success is-dismissible ocm-admin-notice">
                <p>
                    <span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px; vertical-align: middle;"></span>
                    <?php _e('Settings saved successfully!', 'shipsync'); ?>
                </p>
            </div>
            <script>jQuery(document).ready(function($){ setTimeout(function(){ $(".ocm-admin-notice").fadeOut(300); }, 5000); });</script>
        <?php endif; ?>

        <div class="wrap shipsync-settings-wrap">
            <h1 class="shipsync-page-title">
                <span class="dashicons dashicons-admin-generic" style="margin-right: 8px; color: #2271b1;"></span>
                <?php _e('ShipSync Settings', 'shipsync'); ?>
            </h1>
            <p class="description" style="margin-bottom: 25px; color: #646970;">
                <?php _e('Configure general plugin settings, email notifications, and widget display options.', 'shipsync'); ?>
            </p>

            <form method="post" action="" class="shipsync-settings-form">
                <?php wp_nonce_field('shipsync_settings', 'shipsync_settings_nonce'); ?>

                <div class="shipsync-settings-section">
                    <div class="shipsync-section-header">
                        <h2>
                            <span class="dashicons dashicons-admin-settings" style="color: #2271b1; margin-right: 8px; vertical-align: middle;"></span>
                            <?php _e('General Settings', 'shipsync'); ?>
                        </h2>
                        <p class="description"><?php _e('Configure basic plugin behavior and defaults.', 'shipsync'); ?></p>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="orders_per_page">
                                    <span class="dashicons dashicons-list-view" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Orders per page', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="orders_per_page"
                                       name="orders_per_page"
                                       value="<?php echo esc_attr($settings['orders_per_page']); ?>"
                                       min="5"
                                       max="100"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Number of orders to display per page in the admin area. Recommended: 20-50.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="default_order_status">
                                    <span class="dashicons dashicons-flag" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Default order status', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="default_order_status" name="default_order_status" class="regular-text">
                                    <option value="pending" <?php selected($settings['default_order_status'], 'pending'); ?>><?php _e('Pending', 'shipsync'); ?></option>
                                    <option value="confirmed" <?php selected($settings['default_order_status'], 'confirmed'); ?>><?php _e('Confirmed', 'shipsync'); ?></option>
                                    <option value="preparing" <?php selected($settings['default_order_status'], 'preparing'); ?>><?php _e('Preparing', 'shipsync'); ?></option>
                                    <option value="ready" <?php selected($settings['default_order_status'], 'ready'); ?>><?php _e('Ready for Pickup', 'shipsync'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('Default status assigned to new orders when they are created.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <span class="dashicons dashicons-email-alt" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                <?php _e('Email notifications', 'shipsync'); ?>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="enable_notifications"
                                           value="1"
                                           <?php checked($settings['enable_notifications']); ?>>
                                    <?php _e('Enable email notifications', 'shipsync'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('Send email notifications to customers when order status changes.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="shipsync-settings-section">
                    <div class="shipsync-section-header">
                        <h2>
                            <span class="dashicons dashicons-admin-widgets" style="color: #2271b1; margin-right: 8px; vertical-align: middle;"></span>
                            <?php _e('Widget Settings', 'shipsync'); ?>
                        </h2>
                        <p class="description"><?php _e('Customize the appearance and behavior of the order card widget on the frontend.', 'shipsync'); ?></p>
                    </div>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="widget_title">
                                    <span class="dashicons dashicons-edit" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Widget title', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="text"
                                       id="widget_title"
                                       name="widget_title"
                                       value="<?php echo esc_attr(get_option('ocm_widget_title', 'Recent Orders')); ?>"
                                       class="regular-text">
                                <p class="description">
                                    <?php _e('Title displayed above the order card widget on the frontend.', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="widget_orders_limit">
                                    <span class="dashicons dashicons-admin-post" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                    <?php _e('Number of orders to display', 'shipsync'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number"
                                       id="widget_orders_limit"
                                       name="widget_orders_limit"
                                       value="<?php echo esc_attr(get_option('ocm_widget_orders_limit', 5)); ?>"
                                       min="1"
                                       max="20"
                                       class="small-text">
                                <p class="description">
                                    <?php _e('Maximum number of orders to show in the widget (1-20).', 'shipsync'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <span class="dashicons dashicons-visibility" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                                <?php _e('Display options', 'shipsync'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <legend class="screen-reader-text"><span><?php _e('Display options', 'shipsync'); ?></span></legend>
                                    <label style="display: block; margin-bottom: 10px;">
                                        <input type="checkbox"
                                               name="widget_show_status"
                                               value="1"
                                               <?php checked(get_option('ocm_widget_show_status', true)); ?>>
                                        <?php _e('Show order status', 'shipsync'); ?>
                                    </label>
                                    <p class="description" style="margin-left: 25px; margin-top: -5px; margin-bottom: 15px;">
                                        <?php _e('Display order status badges in the widget.', 'shipsync'); ?>
                                    </p>

                                    <label>
                                        <input type="checkbox"
                                               name="widget_show_courier"
                                               value="1"
                                               <?php checked(get_option('ocm_widget_show_courier', true)); ?>>
                                        <?php _e('Show courier information', 'shipsync'); ?>
                                    </label>
                                    <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                        <?php _e('Display courier name and tracking information in the widget.', 'shipsync'); ?>
                                    </p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="shipsync-save-actions">
                    <button type="submit" name="save_settings" class="button button-primary button-large">
                        <span class="dashicons dashicons-yes-alt" style="margin-right: 5px; vertical-align: middle;"></span>
                        <?php _e('Save Settings', 'shipsync'); ?>
                    </button>
                    <span class="description" style="margin-left: 15px; color: #646970;">
                        <?php _e('Save your changes to apply the settings.', 'shipsync'); ?>
                    </span>
                </div>
            </form>
        </div>

        <?php self::render_admin_footer(); ?>

        <style>
        .shipsync-settings-wrap {
            background: #fff;
            padding: 20px;
            margin: 20px 0;
        }

        .shipsync-page-title {
            font-size: 23px;
            font-weight: 400;
            margin: 0 0 8px 0;
            padding: 9px 0 4px 0;
            line-height: 1.3;
        }

        .shipsync-settings-section {
            background: #fff;
            border: 1px solid #dcdcde;
            border-radius: 4px;
            padding: 0;
            margin-bottom: 25px;
            overflow: hidden;
        }

        .shipsync-section-header {
            padding: 20px 20px 15px 20px;
            background: #f6f7f7;
            border-bottom: 1px solid #dcdcde;
        }

        .shipsync-section-header h2 {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            display: flex;
            align-items: center;
        }

        .shipsync-section-header .description {
            margin: 0;
            color: #646970;
            font-size: 13px;
        }

        .shipsync-settings-section .form-table {
            margin: 0;
            border: none;
        }

        .shipsync-settings-section .form-table th {
            padding: 20px 20px 15px 20px;
            background: #fafafa;
            font-weight: 600;
            width: 200px;
        }

        .shipsync-settings-section .form-table td {
            padding: 15px 20px;
        }

        .shipsync-settings-section .form-table tr:not(:last-child) {
            border-bottom: 1px solid #f0f0f1;
        }

        .shipsync-settings-section .form-table tr:last-child td {
            padding-bottom: 20px;
        }

        .shipsync-settings-section .form-table label {
            display: inline-flex;
            align-items: center;
            font-weight: 600;
        }

        .shipsync-save-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dcdcde;
            display: flex;
            align-items: center;
        }

        .ocm-admin-notice {
            animation: slideDown 0.3s;
        }

        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .shipsync-settings-section fieldset {
            border: none;
            padding: 0;
            margin: 0;
        }

        .shipsync-settings-section fieldset label {
            font-weight: normal;
        }
        </style>
        <?php
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
