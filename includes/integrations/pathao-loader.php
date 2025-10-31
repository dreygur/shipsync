<?php
/**
 * Pathao Courier Plugin Loader
 * Loads the bundled Pathao Courier plugin if not already installed separately
 */

if (!defined('ABSPATH')) {
    exit;
}

// Check if Pathao plugin is already installed separately
// If not, try to load the bundled version from ShipSync
if (!defined('PTC_PLUGIN_DIR') && !function_exists('pt_hms_get_token')) {
    // Define Pathao plugin constants relative to ShipSync
    $pathao_plugin_dir = SHIPSYNC_PLUGIN_PATH . 'courier-woocommerce-plugin-main/';
    $pathao_plugin_url = SHIPSYNC_PLUGIN_URL . 'courier-woocommerce-plugin-main/';

    // Check if the bundled plugin directory exists
    if (!is_dir($pathao_plugin_dir)) {
        // Bundled plugin not available - log warning but don't break
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ShipSync: Bundled Pathao Courier plugin directory not found at: ' . $pathao_plugin_dir);
        }
        return; // Exit early if directory doesn't exist
    }

    define('PTC_PLUGIN_URL', $pathao_plugin_url);
    define('PTC_PLUGIN_DIR', $pathao_plugin_dir);
    define('PTC_PLUGIN_TEMPLATE_DIR', $pathao_plugin_dir . 'templates/');
    define('PTC_PLUGIN_FILE', 'shipsync/courier-woocommerce-plugin-main/pathao-courier.php');
    define('PTC_PLUGIN_PREFIX', 'ptc');
    define('PTC_EMPTY_FLAG', '-');

    // Load Pathao plugin files
    $pathao_files = array(
        'pathao-bridge.php',
        'plugin-api.php',
        'settings-page.php',
        'wc-order-list.php',
        'db-queries.php'
    );

    $files_loaded = 0;
    foreach ($pathao_files as $file) {
        $file_path = $pathao_plugin_dir . $file;
        if (file_exists($file_path)) {
            require_once $file_path;
            $files_loaded++;
        }
    }

    // If critical files weren't loaded, log a warning
    if ($files_loaded === 0 && (defined('WP_DEBUG') && WP_DEBUG)) {
        error_log('ShipSync: No Pathao plugin files were loaded from bundled directory');
    }

    // Only register hooks if files were successfully loaded
    if ($files_loaded === 0 || !function_exists('pt_hms_get_token')) {
        return; // Exit if essential functions aren't available
    }

    // Enqueue Pathao admin styles and scripts
    add_action('admin_enqueue_scripts', 'shipsync_pathao_enqueue_scripts', 5);
    function shipsync_pathao_enqueue_scripts($hook) {
        if (!defined('PTC_PLUGIN_URL')) {
            return;
        }

        wp_enqueue_style(
            'ptc-admin-css',
            PTC_PLUGIN_URL . 'css/ptc-admin-style.css',
            null,
            file_exists(PTC_PLUGIN_DIR . '/css/ptc-admin-style.css')
                ? filemtime(PTC_PLUGIN_DIR . '/css/ptc-admin-style.css')
                : SHIPSYNC_VERSION,
            'all'
        );

        wp_enqueue_script(
            'ptc-admin-js',
            PTC_PLUGIN_URL . 'js/ptc-admin-script.js',
            ['jquery'],
            file_exists(PTC_PLUGIN_DIR . '/js/ptc-admin-script.js')
                ? filemtime(PTC_PLUGIN_DIR . '/js/ptc-admin-script.js')
                : SHIPSYNC_VERSION,
            true
        );

        wp_enqueue_script(
            'ptc-admin-alpine-js',
            'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js',
            ['jquery'],
        );

        wp_localize_script('ptc-admin-js', 'ptcSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
            'merchantPanelBaseUrl' => function_exists('get_ptc_merchant_panel_base_url')
                ? get_ptc_merchant_panel_base_url()
                : 'https://merchant.pathao.com',
        ]);

        wp_enqueue_script(
            'ptc-bulk-action',
            PTC_PLUGIN_URL . 'js/ptc-bulk-action.js',
            ['jquery'],
            file_exists(PTC_PLUGIN_DIR . '/js/ptc-bulk-action.js')
                ? filemtime(PTC_PLUGIN_DIR . '/js/ptc-bulk-action.js')
                : SHIPSYNC_VERSION,
            true
        );

        wp_enqueue_style(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css'
        );

        wp_enqueue_script(
            'sweetalert2',
            'https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.all.min.js',
            ['jquery'],
            null,
            true
        );

        wp_enqueue_script(
            'handsontable-js',
            'https://cdn.jsdelivr.net/npm/handsontable@13.0.0/dist/handsontable.full.min.js',
            ['jquery'],
            null,
            true
        );

        wp_enqueue_style(
            'handsontable-css',
            'https://cdn.jsdelivr.net/npm/handsontable@13.0.0/dist/handsontable.full.min.css'
        );
    }

    // Add bulk action for Pathao
    add_filter('bulk_actions-woocommerce_page_wc-orders', 'shipsync_pathao_add_bulk_action');
    function shipsync_pathao_add_bulk_action($bulk_actions) {
        $bulk_actions['send_with_pathao'] = __('Send with Pathao', 'pathao_text_domain');
        return $bulk_actions;
    }

    add_filter('handle_bulk_actions-woocommerce_page_wc-orders', 'shipsync_pathao_handle_bulk_action', 10, 3);
    function shipsync_pathao_handle_bulk_action($redirect_to, $do_action, $post_ids) {
        if ($do_action !== 'send_with_pathao') {
            return $redirect_to;
        }

        // Process the selected orders
        foreach ($post_ids as $order_id) {
            $order = wc_get_order($order_id);
            if ($order && function_exists('getPtOrderData')) {
                $orderData = transformData(getPtOrderData($order));
                // Process order data (handled by Pathao plugin functions)
            }
        }

        $redirect_to = add_query_arg('example_updated', count($post_ids), $redirect_to);
        return $redirect_to;
    }

    // Helper function for bulk action
    if (!function_exists('transformData')) {
        function transformData(array $getPtOrderData) {
            return [
                "store_id" => 1,
                "merchant_order_id" => $getPtOrderData['merchant_order_id'] ?? 1,
                "recipient_name" => $getPtOrderData['recipient_name'] ?? "Demo Recipient One",
                "recipient_phone" => $getPtOrderData['recipient_phone'] ?? "015XXXXXXXX",
                "recipient_address" => $getPtOrderData['recipient_address'] ?? "House 123, Road 4, Sector 10, Uttara, Dhaka-1230, Bangladesh",
                "delivery_type" => $getPtOrderData['delivery_type'] ?? 48,
                "item_type" => $getPtOrderData['item_type'] ?? 2,
                "special_instruction" => $getPtOrderData['special_instruction'] ?? "",
                "item_quantity" => $getPtOrderData['item_quantity'] ?? 2,
                "item_weight" => $getPtOrderData['item_weight'] ?? "0.5",
                "amount_to_collect" => $getPtOrderData['amount_to_collect'] ?? 100,
                "item_description" => $getPtOrderData['item_description'] ?? "This is a Cloth item",
            ];
        }
    }
}

