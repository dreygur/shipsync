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

        // Add courier service selector to checkout
        add_filter('woocommerce_checkout_fields', array($this, 'add_courier_service_checkout_field'));
        add_action('woocommerce_checkout_update_order_meta', array($this, 'save_courier_service_checkout_field'));

        // Display courier service on order edit page
        add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'display_courier_service_admin'));
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

        // Get courier service selection
        $courier_service_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_SERVICE);
        $courier_manager = ShipSync_Courier_Manager::instance();
        $enabled_courier_services = $courier_manager->get_enabled_couriers();

        // Validate enabled_courier_services is array
        if (!is_array($enabled_courier_services)) {
            $enabled_courier_services = array();
        }

        wp_nonce_field('shipsync_save_courier', 'shipsync_courier_nonce');
        wp_nonce_field('ocm_save_courier', 'ocm_courier_nonce'); // Backward compatibility
        ?>
        <div class="ocm-courier-assignment">
            <?php if (!empty($enabled_courier_services) && is_array($enabled_courier_services)): ?>
            <p>
                <label for="shipsync_courier_service_select"><strong><?php _e('Courier Service:', 'shipsync'); ?></strong></label>
                <select name="shipsync_courier_service_select" id="shipsync_courier_service_select" style="width: 100%;">
                    <option value=""><?php _e('-- Select Courier Service --', 'shipsync'); ?></option>
                    <?php
                    // Show currently selected service even if disabled
                    if ($courier_service_id) {
                        $current_service = $courier_manager->get_courier($courier_service_id);
                        // Check if current service is not in enabled list by comparing IDs
                        $enabled_ids = array_map(function($c) { return $c->get_id(); }, $enabled_courier_services);
                        if ($current_service && !$current_service->is_enabled() && !in_array($current_service->get_id(), $enabled_ids)) {
                            ?>
                            <option value="<?php echo esc_attr($current_service->get_id()); ?>" selected disabled style="background: #fff3cd; color: #d63638;">
                                <?php echo esc_html($current_service->get_name()); ?> <?php _e('(Currently selected - Disabled)', 'shipsync'); ?>
                            </option>
                            <?php
                        }
                    }
                    // Show enabled services
                    foreach ($enabled_courier_services as $courier_service): ?>
                        <option value="<?php echo esc_attr($courier_service->get_id()); ?>" <?php selected($courier_service_id, $courier_service->get_id()); ?>>
                            <?php echo esc_html($courier_service->get_name()); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p class="description" style="margin-top: 5px; font-size: 11px; color: #666;">
                    <?php _e('Select which courier service to use for this order. This preference can be set by customers at checkout or by admins here.', 'shipsync'); ?>
                </p>
            </p>

            <?php if ($courier_service_id):
                $selected_courier_service = $courier_manager->get_courier($courier_service_id);
                if ($selected_courier_service):
                    $is_service_enabled = $selected_courier_service->is_enabled();
            ?>
            <div class="ocm-courier-service-info" style="background: <?php echo $is_service_enabled ? '#e7f5ff' : '#fff3cd'; ?>; padding: 10px; margin-top: 10px; border-left: 3px solid <?php echo $is_service_enabled ? '#2271b1' : '#d63638'; ?>; border-radius: 4px;">
                <p style="margin: 5px 0; font-size: 12px;">
                    <strong><?php _e('Selected Service:', 'shipsync'); ?></strong><br>
                    <?php echo esc_html($selected_courier_service->get_name()); ?>
                    <?php if (!$is_service_enabled): ?>
                        <span style="color: #d63638; font-size: 11px; font-weight: 600; margin-left: 8px;">
                            <span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span>
                            <?php _e('(Disabled)', 'shipsync'); ?>
                        </span>
                        <br>
                        <small style="color: #646970; margin-top: 5px; display: block;">
                            <?php _e('This courier service is currently disabled. Please enable it in settings or select a different service.', 'shipsync'); ?>
                        </small>
                    <?php endif; ?>
                    <?php if ($order->get_meta(ShipSync_Meta_Keys::COURIER_SERVICE . '_from_checkout')): ?>
                        <span style="color: #666; font-size: 11px; display: block; margin-top: 4px;"><?php echo '— ' . __('Selected by customer at checkout', 'shipsync'); ?></span>
                    <?php endif; ?>
                </p>
            </div>
            <?php
                elseif ($courier_service_id):
                    // Courier service ID exists but courier not found (deleted or invalid)
            ?>
            <div class="ocm-courier-service-info" style="background: #fff3cd; padding: 10px; margin-top: 10px; border-left: 3px solid #d63638; border-radius: 4px;">
                <p style="margin: 5px 0; font-size: 12px;">
                    <strong><?php _e('Selected Service:', 'shipsync'); ?></strong><br>
                    <span style="color: #d63638;">
                        <span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span>
                        <?php printf(__('Unknown or Invalid (ID: %s)', 'shipsync'), esc_html($courier_service_id)); ?>
                    </span>
                    <br>
                    <small style="color: #646970; margin-top: 5px; display: block;">
                        <?php _e('The previously selected courier service is no longer available. Please select a different service.', 'shipsync'); ?>
                    </small>
                </p>
            </div>
            <?php
                endif;
            endif; ?>
            <hr style="margin: 15px 0; border: 0; border-top: 1px solid #ddd;">
            <?php endif; ?>

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
                <p class="description" style="margin-top: 5px; font-size: 11px; color: #666;">
                    <?php _e('Assign a specific courier personnel to this order.', 'shipsync'); ?>
                </p>
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

        // Save courier service selection
        if (isset($_POST['shipsync_courier_service_select'])) {
            $courier_service_id = sanitize_text_field($_POST['shipsync_courier_service_select']);
            $old_courier_service_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_SERVICE);

            if ($courier_service_id !== $old_courier_service_id) {
                $courier_manager = ShipSync_Courier_Manager::instance();

                // Check if order already has tracking/consignment from previous courier
                $has_existing_tracking = false;
                $existing_courier_info = '';
                if ($old_courier_service_id) {
                    $old_courier = $courier_manager->get_courier($old_courier_service_id);
                    if ($old_courier) {
                        $tracking_meta_key = '_' . $old_courier_service_id . '_tracking_code';
                        $consignment_meta_key = '_' . $old_courier_service_id . '_consignment_id';
                        $has_tracking = $order->get_meta($tracking_meta_key);
                        $has_consignment = $order->get_meta($consignment_meta_key);

                        if ($has_tracking || $has_consignment) {
                            $has_existing_tracking = true;
                            $existing_courier_info = $old_courier->get_name();
                            if ($has_tracking) {
                                $existing_courier_info .= ' (Tracking: ' . $has_tracking . ')';
                            }
                            if ($has_consignment) {
                                $existing_courier_info .= ' (Consignment: ' . $has_consignment . ')';
                            }
                        }
                    }
                }

                $courier_service = $courier_manager->get_courier($courier_service_id);

                if ($courier_service && $courier_service->is_enabled()) {
                    // Show warning if changing courier on order with existing tracking
                    if ($has_existing_tracking) {
                        $order->add_order_note(
                            sprintf(__('WARNING: Courier service changed from %s (which had tracking/consignment data) to %s. Previous tracking data is preserved but may not be applicable to the new courier.', 'shipsync'),
                                esc_html($existing_courier_info),
                                esc_html($courier_service->get_name())
                            )
                        );
                    }

                    $order->update_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE, $courier_service_id);
                    // Remove the "from checkout" flag since admin changed it
                    $order->delete_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE . '_from_checkout');

                    if (empty($old_courier_service_id)) {
                        $order->add_order_note(
                            sprintf(__('Courier service set to: %s', 'shipsync'), esc_html($courier_service->get_name()))
                        );
                    } else {
                        $old_courier = $courier_manager->get_courier($old_courier_service_id);
                        $old_name = $old_courier ? esc_html($old_courier->get_name()) : esc_html($old_courier_service_id);
                        $order->add_order_note(
                            sprintf(__('Courier service changed from %s to %s', 'shipsync'), $old_name, esc_html($courier_service->get_name()))
                        );
                    }
                } elseif (empty($courier_service_id)) {
                    // Admin cleared the selection
                    if ($has_existing_tracking) {
                        $order->add_order_note(
                            sprintf(__('WARNING: Courier service selection cleared, but order still has tracking data from %s.', 'shipsync'), esc_html($existing_courier_info))
                        );
                    }
                    $order->delete_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE);
                    $order->delete_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE . '_from_checkout');
                    if ($old_courier_service_id) {
                        $order->add_order_note(__('Courier service selection cleared', 'shipsync'));
                    }
                } else {
                    // Invalid or disabled courier selected - show warning
                    $order->add_order_note(
                        sprintf(__('WARNING: Attempted to set courier service to "%s" but it is invalid or disabled.', 'shipsync'), $courier_service_id)
                    );
                }

                $order->save();
            }
        }

        // Save courier personnel assignment
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

            // Add courier service and courier personnel columns after order status
            if ($key === 'order_status') {
                $new_columns['courier_service'] = __('Courier Service', 'shipsync');
                $new_columns['courier'] = __('Courier', 'shipsync');
            }
        }

        return $new_columns;
    }

    /**
     * Display courier in orders list column
     */
    public function courier_column_content($column, $post_id) {
        $order = wc_get_order($post_id);

        // Display courier service
        if ($column === 'courier_service') {
            $courier_service_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_SERVICE);

            if ($courier_service_id) {
                $courier_manager = ShipSync_Courier_Manager::instance();
                $courier_service = $courier_manager->get_courier($courier_service_id);

                if ($courier_service) {
                    $is_enabled = $courier_service->is_enabled();
                    $bg_color = $is_enabled ? '#00a32a' : '#d63638';
                    $title = $is_enabled ? '' : __(' (Disabled)', 'shipsync');

                    echo '<span class="shipsync-courier-service-badge" style="background: ' . esc_attr($bg_color) . '; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 11px;" title="' . esc_attr($courier_service->get_name() . $title) . '">';
                    echo esc_html($courier_service->get_name());
                    if (!$is_enabled) {
                        echo ' <span style="opacity: 0.8;">⚠</span>';
                    }
                    echo '</span>';
                } else {
                    echo '<span style="color: #d63638;" title="' . esc_attr__('Invalid courier service ID', 'shipsync') . '">';
                    echo '<span class="dashicons dashicons-warning" style="font-size: 12px; vertical-align: middle;"></span> ';
                    echo __('Unknown', 'shipsync');
                    echo '</span>';
                }
            } else {
                echo '<span style="color: #999;">—</span>';
            }
        }

        // Display courier personnel
        if ($column === 'courier') {
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
        $settings = get_option(ShipSync_Options::SETTINGS, array());
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

    /**
     * Add courier service selector to checkout fields
     *
     * @param array $fields WooCommerce checkout fields
     * @return array Modified fields
     */
    public function add_courier_service_checkout_field($fields) {
        // Validate we're in a proper checkout context
        if (!function_exists('is_checkout') || !is_checkout()) {
            return $fields;
        }

        // Get enabled courier services
        $courier_manager = ShipSync_Courier_Manager::instance();
        $enabled_couriers = $courier_manager->get_enabled_couriers();

        // Only add field if there are enabled courier services
        if (empty($enabled_couriers) || !is_array($enabled_couriers)) {
            return $fields;
        }

        // Build options array with validation
        $options = array('' => __('Select a courier service (Optional)', 'shipsync'));
        foreach ($enabled_couriers as $courier) {
            if ($courier && method_exists($courier, 'get_id') && method_exists($courier, 'get_name')) {
                $courier_id = $courier->get_id();
                $courier_name = $courier->get_name();
                if (!empty($courier_id) && !empty($courier_name)) {
                    $options[$courier_id] = $courier_name;
                }
            }
        }

        // Only add if we have valid options (more than just the empty option)
        if (count($options) > 1) {
            // Add field to billing section
            $fields['billing']['shipsync_courier_service'] = array(
                'type'        => 'select',
                'label'       => __('Preferred Courier Service', 'shipsync'),
                'placeholder' => __('Choose a courier service', 'shipsync'),
                'required'    => false,
                'class'       => array('form-row-wide', 'address-field'),
                'options'     => $options,
                'priority'    => 120,
                'description' => __('Select your preferred courier service for this order. This helps us process your shipment more efficiently.', 'shipsync'),
            );
        }

        return $fields;
    }

    /**
     * Save courier service selection from checkout
     *
     * @param int $order_id Order ID
     */
    public function save_courier_service_checkout_field($order_id) {
        if (!empty($_POST['shipsync_courier_service'])) {
            $courier_service = sanitize_text_field($_POST['shipsync_courier_service']);

            // Validate that it's a valid courier service
            $courier_manager = ShipSync_Courier_Manager::instance();
            $courier = $courier_manager->get_courier($courier_service);

            if ($courier && $courier->is_enabled()) {
                $order = wc_get_order($order_id);
                if ($order) {
                    try {
                        $order->update_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE, $courier_service);
                        // Flag that this was selected from checkout
                        $order->update_meta_data(ShipSync_Meta_Keys::COURIER_SERVICE . '_from_checkout', 'yes');
                        $order->save();

                    // Add order note (courier name is from our own object, safe to use directly in sprintf)
                    $order->add_order_note(
                        sprintf(__('Customer selected courier service: %s', 'shipsync'), esc_html($courier->get_name()))
                    );
                    } catch (Exception $e) {
                        // Log error but don't break checkout
                        error_log('ShipSync: Error saving courier service selection: ' . $e->getMessage());
                        // Optionally add admin notice if in admin context
                        if (is_admin() && function_exists('add_action')) {
                            add_action('admin_notices', function() use ($order_id, $e) {
                                printf(
                                    '<div class="notice notice-error"><p>%s</p></div>',
                                    sprintf(__('ShipSync: Failed to save courier service selection for order #%d: %s', 'shipsync'), $order_id, $e->getMessage())
                                );
                            });
                        }
                    }
                }
            }
        }
    }

    /**
     * Display courier service on order edit page
     *
     * @param WC_Order $order WooCommerce order object
     */
    public function display_courier_service_admin($order) {
        $courier_service_id = $order->get_meta(ShipSync_Meta_Keys::COURIER_SERVICE);

        if (!$courier_service_id) {
            return;
        }

        $courier_manager = ShipSync_Courier_Manager::instance();
        $courier = $courier_manager->get_courier($courier_service_id);

        if (!$courier) {
            ?>
            <div class="address">
                <p>
                    <strong><?php _e('Preferred Courier Service:', 'shipsync'); ?></strong><br>
                    <span style="color: #d63638;">
                        <span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span>
                        <?php printf(__('Unknown or Invalid (ID: %s)', 'shipsync'), esc_html($courier_service_id)); ?>
                    </span>
                </p>
            </div>
            <?php
            return;
        }

        $is_enabled = $courier->is_enabled();

        ?>
        <div class="address">
            <p>
                <strong><?php _e('Preferred Courier Service:', 'shipsync'); ?></strong><br>
                <?php echo esc_html($courier->get_name()); ?>
                <?php if (!$is_enabled): ?>
                    <span style="color: #d63638; margin-left: 8px; font-size: 12px;">
                        <span class="dashicons dashicons-warning" style="font-size: 14px; vertical-align: middle;"></span>
                        <?php _e('(Disabled)', 'shipsync'); ?>
                    </span>
                <?php endif; ?>
            </p>
        </div>
        <?php
    }
}
