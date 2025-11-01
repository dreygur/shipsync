<?php
/**
 * Courier Manager Class
 * Manages all courier service integrations
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Courier_Manager {

    /**
     * Registered courier services
     * @var array
     */
    private $couriers = array();

    /**
     * Singleton instance
     * @var ShipSync_Courier_Manager
     */
    private static $instance = null;

    /**
     * Get singleton instance
     * @return ShipSync_Courier_Manager
     */
    public static function instance() {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->load_pathao_plugin();
        $this->load_couriers();
        $this->init_hooks();
    }

    /**
     * Load bundled Pathao Courier plugin if not already installed separately
     * Merged from includes/integrations/pathao-loader.php
     */
    private function load_pathao_plugin() {
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
            add_action('admin_enqueue_scripts', array($this, 'pathao_enqueue_scripts'), 5);

            // Add bulk action for Pathao
            add_filter('bulk_actions-woocommerce_page_wc-orders', array($this, 'pathao_add_bulk_action'));
            add_filter('handle_bulk_actions-woocommerce_page_wc-orders', array($this, 'pathao_handle_bulk_action'), 10, 3);

            // Helper function for bulk action
            if (!function_exists('transformData')) {
                function transformData(array $getPtOrderData) {
                    return array(
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
                    );
                }
            }
        }
    }

    /**
     * Enqueue Pathao admin styles and scripts
     * Merged from pathao-loader.php
     */
    public function pathao_enqueue_scripts($hook) {
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
            array('jquery'),
            file_exists(PTC_PLUGIN_DIR . '/js/ptc-admin-script.js')
                ? filemtime(PTC_PLUGIN_DIR . '/js/ptc-admin-script.js')
                : SHIPSYNC_VERSION,
            true
        );

        wp_enqueue_script(
            'ptc-admin-alpine-js',
            'https://cdn.jsdelivr.net/npm/alpinejs@3.13.5/dist/cdn.min.js',
            array('jquery'),
        );

        wp_localize_script('ptc-admin-js', 'ptcSettings', array(
            'nonce' => wp_create_nonce('wp_rest'),
            'merchantPanelBaseUrl' => function_exists('get_ptc_merchant_panel_base_url')
                ? get_ptc_merchant_panel_base_url()
                : 'https://merchant.pathao.com',
        ));

        wp_enqueue_script(
            'ptc-bulk-action',
            PTC_PLUGIN_URL . 'js/ptc-bulk-action.js',
            array('jquery'),
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
            array('jquery'),
            null,
            true
        );

        wp_enqueue_script(
            'handsontable-js',
            'https://cdn.jsdelivr.net/npm/handsontable@13.0.0/dist/handsontable.full.min.js',
            array('jquery'),
            null,
            true
        );

        wp_enqueue_style(
            'handsontable-css',
            'https://cdn.jsdelivr.net/npm/handsontable@13.0.0/dist/handsontable.full.min.css'
        );
    }

    /**
     * Add bulk action for Pathao
     * Merged from pathao-loader.php
     */
    public function pathao_add_bulk_action($bulk_actions) {
        $bulk_actions['send_with_pathao'] = __('Send with Pathao', 'pathao_text_domain');
        return $bulk_actions;
    }

    /**
     * Handle bulk action for Pathao
     * Merged from pathao-loader.php
     */
    public function pathao_handle_bulk_action($redirect_to, $do_action, $post_ids) {
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

    /**
     * Load all courier integrations
     */
    private function load_couriers() {
        // Load Steadfast Courier
        require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/class-steadfast-courier.php';
        $this->register_courier(new ShipSync_Steadfast_Courier());

        // Load Pathao Courier
        require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/class-pathao-courier.php';
        $this->register_courier(new ShipSync_Pathao_Courier());

        // Load RedX Courier
        require_once SHIPSYNC_PLUGIN_PATH . 'includes/couriers/class-redx-courier.php';
        $this->register_courier(new ShipSync_RedX_Courier());

        // Future courier integrations can be added here

        do_action('shipsync_load_couriers', $this);
        do_action('ocm_load_couriers', $this); // Backward compatibility
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add courier meta box to WooCommerce orders
        add_action('add_meta_boxes', array($this, 'add_courier_meta_box'));

        // Handle AJAX requests
        add_action('wp_ajax_shipsync_send_to_courier', array($this, 'ajax_send_to_courier'));
        add_action('wp_ajax_shipsync_check_courier_status', array($this, 'ajax_check_courier_status'));
        add_action('wp_ajax_shipsync_validate_courier_credentials', array($this, 'ajax_validate_credentials'));

        // Backward compatibility: Register old AJAX actions
        add_action('wp_ajax_ocm_send_to_courier', array($this, 'ajax_send_to_courier'));
        add_action('wp_ajax_ocm_check_courier_status', array($this, 'ajax_check_courier_status'));
        add_action('wp_ajax_ocm_validate_courier_credentials', array($this, 'ajax_validate_credentials'));

        // Add bulk action to orders list
        add_filter('bulk_actions-edit-shop_order', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-shop_order', array($this, 'handle_bulk_actions'), 10, 3);

        // Add order list column
        add_filter('manage_edit-shop_order_columns', array($this, 'add_order_columns'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'render_order_column'), 10, 2);
    }

    /**
     * Register a courier service
     * @param ShipSync_Abstract_Courier $courier
     */
    public function register_courier($courier) {
        $this->couriers[$courier->get_id()] = $courier;
    }

    /**
     * Get all registered couriers
     * @return array
     */
    public function get_couriers() {
        return $this->couriers;
    }

    /**
     * Get enabled couriers
     * @return array
     */
    public function get_enabled_couriers() {
        return array_filter($this->couriers, function($courier) {
            return $courier->is_enabled();
        });
    }

    /**
     * Get courier by ID
     * @param string $courier_id
     * @return ShipSync_Abstract_Courier|null
     */
    public function get_courier($courier_id) {
        return isset($this->couriers[$courier_id]) ? $this->couriers[$courier_id] : null;
    }

    /**
     * Add courier meta box to orders
     */
    public function add_courier_meta_box() {
        add_meta_box(
            'shipsync_courier_shipment',
            __('Courier Shipment', 'shipsync'),
            array($this, 'render_courier_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Render courier meta box
     * @param WP_Post $post
     */
    public function render_courier_meta_box($post) {
        $order = wc_get_order($post->ID);
        if (!$order) {
            return;
        }

        $enabled_couriers = $this->get_enabled_couriers();

        if (empty($enabled_couriers)) {
            echo '<p>' . __('No courier services are enabled. Please configure courier settings.', 'shipsync') . '</p>';
            return;
        }

        wp_nonce_field('shipsync_courier_action', 'shipsync_courier_nonce');
        wp_nonce_field('ocm_courier_action', 'ocm_courier_nonce'); // Backward compatibility
        ?>
        <div class="ocm-courier-metabox">
            <?php
            // Check if order has been sent to any courier
            $sent_couriers = array();
            foreach ($enabled_couriers as $courier) {
                $tracking_meta = $order->get_meta('_' . $courier->get_id() . '_tracking_code');
                if ($tracking_meta) {
                    $sent_couriers[$courier->get_id()] = array(
                        'name' => $courier->get_name(),
                        'tracking_code' => $tracking_meta,
                        'consignment_id' => $order->get_meta('_' . $courier->get_id() . '_consignment_id'),
                        'status' => $order->get_meta('_' . $courier->get_id() . '_status')
                    );
                }
            }

            if (!empty($sent_couriers)):
                foreach ($sent_couriers as $courier_id => $data): ?>
                    <div class="ocm-courier-info" style="margin-bottom: 15px; padding: 10px; background: #f9f9f9; border-left: 3px solid #0073aa;">
                        <h4 style="margin: 0 0 10px 0;"><?php echo esc_html($data['name']); ?></h4>
                        <p style="margin: 5px 0;">
                            <strong><?php _e('Tracking Code:', 'shipsync'); ?></strong><br>
                            <code><?php echo esc_html($data['tracking_code']); ?></code>
                        </p>
                        <?php if ($data['consignment_id']): ?>
                            <p style="margin: 5px 0;">
                                <strong><?php _e('Consignment ID:', 'shipsync'); ?></strong><br>
                                <?php echo esc_html($data['consignment_id']); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($data['status']): ?>
                            <p style="margin: 5px 0;">
                                <strong><?php _e('Status:', 'shipsync'); ?></strong><br>
                                <span class="ocm-status-badge"><?php echo esc_html(ucfirst(str_replace('_', ' ', $data['status']))); ?></span>
                            </p>
                        <?php endif; ?>
                        <button type="button" class="button ocm-check-status" data-order-id="<?php echo $order->get_id(); ?>" data-courier="<?php echo esc_attr($courier_id); ?>">
                            <?php _e('Check Status', 'shipsync'); ?>
                        </button>
                    </div>
                <?php endforeach;
            else: ?>
                <div class="ocm-send-to-courier">
                    <p>
                        <label for="ocm_courier_service"><?php _e('Select Courier Service:', 'shipsync'); ?></label>
                        <select id="ocm_courier_service" style="width: 100%; margin-top: 5px;">
                            <option value=""><?php _e('-- Select --', 'shipsync'); ?></option>
                            <?php foreach ($enabled_couriers as $courier): ?>
                                <option value="<?php echo esc_attr($courier->get_id()); ?>">
                                    <?php echo esc_html($courier->get_name()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </p>

                    <p>
                        <label for="ocm_delivery_note"><?php _e('Delivery Note (Optional):', 'shipsync'); ?></label>
                        <textarea id="ocm_delivery_note" rows="3" style="width: 100%; margin-top: 5px;"></textarea>
                    </p>

                    <p>
                        <button type="button" class="button button-primary ocm-send-order" data-order-id="<?php echo $order->get_id(); ?>" style="width: 100%;">
                            <?php _e('Send to Courier', 'shipsync'); ?>
                        </button>
                    </p>

                    <div class="ocm-courier-response" style="margin-top: 10px;"></div>
                </div>
            <?php endif; ?>
        </div>

        <?php
        // Inline JavaScript has been moved to assets/js/admin.js
        // Script is enqueued via admin_enqueue_scripts hook
    }

    /**
     * Handle AJAX request to send order to courier
     */
    public function ajax_send_to_courier() {
        // Accept both old and new nonce names for backward compatibility
        if (!check_ajax_referer('shipsync_courier_action', 'nonce', false) && !check_ajax_referer('ocm_courier_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'shipsync')));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'shipsync')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $courier_id = isset($_POST['courier']) ? sanitize_text_field($_POST['courier']) : '';
        $note = isset($_POST['note']) ? sanitize_textarea_field($_POST['note']) : '';

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Invalid order', 'shipsync')));
        }

        $courier = $this->get_courier($courier_id);
        if (!$courier || !$courier->is_enabled()) {
            wp_send_json_error(array('message' => __('Invalid courier service', 'shipsync')));
        }

        $params = array();
        if ($note) {
            $params['note'] = $note;
        }

        $result = $courier->create_order($order, $params);

        if ($result['success']) {
            wp_send_json_success(array('message' => $result['message'], 'data' => $result));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle AJAX request to check courier status
     */
    public function ajax_check_courier_status() {
        // Accept both old and new nonce names for backward compatibility
        if (!check_ajax_referer('shipsync_courier_action', 'nonce', false) && !check_ajax_referer('ocm_courier_action', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'shipsync')));
        }

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(array('message' => __('Permission denied', 'shipsync')));
        }

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $courier_id = isset($_POST['courier']) ? sanitize_text_field($_POST['courier']) : '';

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array('message' => __('Invalid order', 'shipsync')));
        }

        $courier = $this->get_courier($courier_id);
        if (!$courier || !$courier->is_enabled()) {
            wp_send_json_error(array('message' => __('Invalid courier service', 'shipsync')));
        }

        $tracking_code = $order->get_meta('_' . $courier_id . '_tracking_code');
        if (!$tracking_code) {
            wp_send_json_error(array('message' => __('No tracking code found', 'shipsync')));
        }

        $result = $courier->get_delivery_status($tracking_code, 'tracking_code');

        if ($result['success']) {
            // Update order meta
            $order->update_meta_data('_' . $courier_id . '_status', $result['status']);
            $order->save();

            wp_send_json_success(array('status' => $result['status'], 'data' => $result));
        } else {
            wp_send_json_error(array('message' => $result['message']));
        }
    }

    /**
     * Handle AJAX request to validate credentials
     */
    public function ajax_validate_credentials() {
        // Accept both old and new nonce names for backward compatibility
        if (!check_ajax_referer('shipsync_settings_nonce', 'nonce', false) && !check_ajax_referer('ocm_settings_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Security check failed.', 'shipsync')));
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied', 'shipsync')));
        }

        $courier_id = isset($_POST['courier']) ? sanitize_text_field($_POST['courier']) : '';

        $courier = $this->get_courier($courier_id);
        if (!$courier) {
            wp_send_json_error(array('message' => __('Invalid courier service', 'shipsync')));
        }

        $result = $courier->validate_credentials();

        if ($result === true) {
            wp_send_json_success(array('message' => __('Credentials validated successfully', 'shipsync')));
        } else {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }
    }

    /**
     * Add bulk actions to orders list
     */
    public function add_bulk_actions($actions) {
        $enabled_couriers = $this->get_enabled_couriers();

        foreach ($enabled_couriers as $courier) {
            $actions['shipsync_send_to_' . $courier->get_id()] = sprintf(
                __('Send to %s', 'shipsync'),
                $courier->get_name()
            );

            // Backward compatibility
            $actions['ocm_send_to_' . $courier->get_id()] = sprintf(
                __('Send to %s', 'shipsync'),
                $courier->get_name()
            );
        }

        return $actions;
    }

    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        // Check for new shipsync_ prefix first, then old ocm_ prefix for backward compatibility
        $courier_id = null;
        if (strpos($action, 'shipsync_send_to_') === 0) {
            $courier_id = str_replace('shipsync_send_to_', '', $action);
        } elseif (strpos($action, 'ocm_send_to_') === 0) {
            $courier_id = str_replace('ocm_send_to_', '', $action);
        }

        if ($courier_id === null) {
            return $redirect_to;
        }
        $courier = $this->get_courier($courier_id);

        if (!$courier || !$courier->is_enabled()) {
            return $redirect_to;
        }

        $orders = array();
        foreach ($post_ids as $post_id) {
            $order = wc_get_order($post_id);
            if ($order) {
                $orders[] = $order;
            }
        }

        if (!empty($orders)) {
            $result = $courier->create_bulk_orders($orders);
            $redirect_to = add_query_arg('shipsync_bulk_sent', count($orders), $redirect_to);
            // Backward compatibility
            $redirect_to = add_query_arg('ocm_bulk_sent', count($orders), $redirect_to);
        }

        return $redirect_to;
    }

    /**
     * Add courier column to orders list
     */
    public function add_order_columns($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            if ($key === 'order_status') {
                $new_columns['courier_tracking'] = __('Courier', 'shipsync');
            }
        }

        return $new_columns;
    }

    /**
     * Render courier column
     */
    public function render_order_column($column, $post_id) {
        if ($column !== 'courier_tracking') {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }

        $enabled_couriers = $this->get_enabled_couriers();
        $has_tracking = false;

        foreach ($enabled_couriers as $courier) {
            $tracking_code = $order->get_meta('_' . $courier->get_id() . '_tracking_code');
            if ($tracking_code) {
                $status = $order->get_meta('_' . $courier->get_id() . '_status');
                echo '<small>';
                echo '<strong>' . esc_html($courier->get_name()) . ':</strong><br>';
                echo esc_html($tracking_code);
                if ($status) {
                    echo '<br><em>' . esc_html($status) . '</em>';
                }
                echo '</small>';
                $has_tracking = true;
                break;
            }
        }

        if (!$has_tracking) {
            echo '<span style="color: #999;">—</span>';
        }
    }
}
