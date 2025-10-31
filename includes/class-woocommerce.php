<?php
/**
 * WooCommerce Integration for Order & Courier Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_WooCommerce {

    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null) {
        $this->database = $database ?: ShipSync_Database::instance();
        // Add courier meta box to WooCommerce order edit screen
        add_action('add_meta_boxes', array($this, 'add_courier_meta_box'));

        // Save courier assignment from meta box
        add_action('woocommerce_process_shop_order_meta', array($this, 'save_courier_meta_box'));

        // Add courier column to orders list
        add_filter('manage_edit-shop_order_columns', array($this, 'add_courier_column'));
        add_action('manage_shop_order_posts_custom_column', array($this, 'courier_column_content'), 10, 2);

        // Add courier info to order emails
        add_action('woocommerce_email_after_order_table', array($this, 'add_courier_to_email'), 10, 4);

        // Add courier tracking to My Account orders
        add_action('woocommerce_order_details_after_order_table', array($this, 'add_courier_to_order_details'));

        // Register custom order statuses if needed
        add_action('init', array($this, 'register_custom_order_statuses'));
        add_filter('wc_order_statuses', array($this, 'add_custom_order_statuses'));
    }

    /**
     * Add courier meta box to order edit screen
     */
    public function add_courier_meta_box() {
        add_meta_box(
            'shipsync_courier_assignment',
            __('Courier Assignment', 'shipsync'),
            array($this, 'render_courier_meta_box'),
            'shop_order',
            'side',
            'high'
        );
    }

    /**
     * Render courier meta box content
     */
    public function render_courier_meta_box($post) {
        $order = wc_get_order($post->ID);
        $courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);
        $couriers = $this->database->get_couriers();

        wp_nonce_field('shipsync_save_courier', 'shipsync_courier_nonce');
        wp_nonce_field('ocm_save_courier', 'ocm_courier_nonce'); // Backward compatibility
        ?>
        <div class="ocm-courier-assignment">
            <p>
                <label for="shipsync_courier_id"><strong><?php _e('Select Courier:', 'shipsync'); ?></strong></label>
                <select name="shipsync_courier_id" id="shipsync_courier_id" style="width: 100%;">
                    <option value=""><?php _e('No courier assigned', 'shipsync'); ?></option>
                    <?php foreach ($couriers as $courier): ?>
                        <option value="<?php echo esc_attr($courier->id); ?>" <?php selected($courier_id, $courier->id); ?>>
                            <?php echo esc_html($courier->name . ' - ' . $courier->vehicle_type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <!-- Backward compatibility -->
                <select name="ocm_courier_id" id="ocm_courier_id" style="display:none;">
                    <option value=""><?php _e('No courier assigned', 'shipsync'); ?></option>
                    <?php foreach ($couriers as $courier): ?>
                        <option value="<?php echo esc_attr($courier->id); ?>" <?php selected($courier_id, $courier->id); ?>>
                            <?php echo esc_html($courier->name . ' - ' . $courier->vehicle_type); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <?php if ($courier_id):
                $courier = $this->database->get_courier_by_id($courier_id);
                if ($courier):
            ?>
            <div class="ocm-courier-info" style="background: #f5f5f5; padding: 10px; margin-top: 10px; border-radius: 4px;">
                <p style="margin: 5px 0;"><strong><?php _e('Courier Details:', 'shipsync'); ?></strong></p>
                <p style="margin: 5px 0;"><strong><?php _e('Name:', 'shipsync'); ?></strong> <?php echo esc_html($courier->name); ?></p>
                <p style="margin: 5px 0;"><strong><?php _e('Phone:', 'shipsync'); ?></strong> <?php echo esc_html($courier->phone); ?></p>
                <p style="margin: 5px 0;"><strong><?php _e('Vehicle:', 'shipsync'); ?></strong> <?php echo esc_html($courier->vehicle_type); ?></p>
            </div>
            <?php
                endif;
            endif; ?>
        </div>
        <?php
    }

    /**
     * Save courier assignment from meta box
     */
    public function save_courier_meta_box($post_id) {
        // Check nonce
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        $nonce_valid = false;
        if (isset($_POST['shipsync_courier_nonce']) && wp_verify_nonce($_POST['shipsync_courier_nonce'], 'shipsync_save_courier')) {
            $nonce_valid = true;
        } elseif (isset($_POST['ocm_courier_nonce']) && wp_verify_nonce($_POST['ocm_courier_nonce'], 'ocm_save_courier')) {
            $nonce_valid = true;
        }
        if (!$nonce_valid) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_shop_orders')) {
            return;
        }

        $order = wc_get_order($post_id);
        if (!$order) {
            return;
        }

        $old_courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);
        // Accept both new and old field names for backward compatibility
        $new_courier_id = 0;
        if (isset($_POST['shipsync_courier_id'])) {
            $new_courier_id = intval($_POST['shipsync_courier_id']);
        } elseif (isset($_POST['ocm_courier_id'])) {
            $new_courier_id = intval($_POST['ocm_courier_id']);
        }

        if ($new_courier_id != $old_courier_id) {
            if ($new_courier_id > 0) {
                $this->database->assign_courier($post_id, $new_courier_id);
            } else {
                // Remove courier assignment
                $order->delete_meta_data(ShipSync_Meta_Keys::COURIER_ID);
                $order->save();
                $order->add_order_note(__('Courier assignment removed', 'shipsync'));
            }
        }
    }

    /**
     * Add courier column to orders list
     */
    public function add_courier_column($columns) {
        $new_columns = array();

        foreach ($columns as $key => $column) {
            $new_columns[$key] = $column;

            // Add courier column after order status
            if ($key === 'order_status') {
                $new_columns['courier'] = __('Courier', 'shipsync');
            }
        }

        return $new_columns;
    }

    /**
     * Display courier in orders list column
     */
    public function courier_column_content($column, $post_id) {
        if ($column === 'courier') {
            $order = wc_get_order($post_id);
            $courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);

            if ($courier_id) {
                $courier = $this->database->get_courier_by_id($courier_id);
                if ($courier) {
                    echo '<span class="ocm-courier-badge" style="background: #2271b1; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 11px;">';
                    echo esc_html($courier->name);
                    echo '</span>';
                } else {
                    echo '<span style="color: #999;">' . __('Unknown', 'shipsync') . '</span>';
                }
            } else {
                echo '<span style="color: #999;">' . __('Not assigned', 'shipsync') . '</span>';
            }
        }
    }

    /**
     * Add courier info to order emails
     */
    public function add_courier_to_email($order, $sent_to_admin, $plain_text, $email) {
        $settings = get_option('ocm_settings', array());
        if (empty($settings['show_courier_on_order_email'])) {
            return;
        }

        $courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);
        if (!$courier_id) {
            return;
        }

        $courier = $this->database->get_courier_by_id($courier_id);
        if (!$courier) {
            return;
        }

        if ($plain_text) {
            echo "\n" . __('Courier Information', 'shipsync') . "\n";
            echo __('Courier:', 'shipsync') . ' ' . $courier->name . "\n";
            echo __('Vehicle:', 'shipsync') . ' ' . $courier->vehicle_type . "\n";
            echo __('Phone:', 'shipsync') . ' ' . $courier->phone . "\n\n";
        } else {
            ?>
            <div style="margin-top: 30px; padding: 20px; background: #f5f5f5; border: 1px solid #ddd;">
                <h2 style="margin: 0 0 15px 0; font-size: 18px;"><?php _e('Courier Information', 'shipsync'); ?></h2>
                <p style="margin: 5px 0;"><strong><?php _e('Courier:', 'shipsync'); ?></strong> <?php echo esc_html($courier->name); ?></p>
                <p style="margin: 5px 0;"><strong><?php _e('Vehicle:', 'shipsync'); ?></strong> <?php echo esc_html($courier->vehicle_type); ?></p>
                <p style="margin: 5px 0;"><strong><?php _e('Phone:', 'shipsync'); ?></strong> <?php echo esc_html($courier->phone); ?></p>
            </div>
            <?php
        }
    }

    /**
     * Add courier info to order details page (My Account)
     */
    public function add_courier_to_order_details($order) {
        $courier_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_ID);
        if (!$courier_id) {
            return;
        }

        $courier = $this->database->get_courier_by_id($courier_id);
        if (!$courier) {
            return;
        }

        ?>
        <section class="woocommerce-order-courier-info">
            <h2><?php _e('Courier Information', 'shipsync'); ?></h2>
            <table class="woocommerce-table woocommerce-table--courier-info shop_table courier_info">
                <tbody>
                    <tr>
                        <th><?php _e('Courier:', 'shipsync'); ?></th>
                        <td><?php echo esc_html($courier->name); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Vehicle:', 'shipsync'); ?></th>
                        <td><?php echo esc_html($courier->vehicle_type); ?></td>
                    </tr>
                    <tr>
                        <th><?php _e('Contact:', 'shipsync'); ?></th>
                        <td><?php echo esc_html($courier->phone); ?></td>
                    </tr>
                </tbody>
            </table>
        </section>
        <?php
    }

    /**
     * Register custom order statuses
     */
    public function register_custom_order_statuses() {
        register_post_status('wc-out-shipping', array(
            'label' => __('Out for Shipping', 'shipsync'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Out for Shipping <span class="count">(%s)</span>', 'Out for Shipping <span class="count">(%s)</span>', 'shipsync')
        ));
    }

    /**
     * Add custom order statuses to WooCommerce
     */
    public function add_custom_order_statuses($order_statuses) {
        $new_statuses = array();

        foreach ($order_statuses as $key => $status) {
            $new_statuses[$key] = $status;

            if ($key === 'wc-processing') {
                $new_statuses['wc-out-shipping'] = __('Out for Shipping', 'shipsync');
            }
        }

        return $new_statuses;
    }
}
