<?php
/**
 * AJAX functionality for ShipSync
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Ajax {

    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null) {
        $this->database = $database ?: new ShipSync_Database();
        // Admin AJAX actions
        add_action('wp_ajax_shipsync_update_order_status', array($this, 'update_order_status'));
        add_action('wp_ajax_shipsync_update_wc_order_status', array($this, 'update_wc_order_status'));
        add_action('wp_ajax_shipsync_send_to_selected_courier', array($this, 'send_to_selected_courier'));
        add_action('wp_ajax_shipsync_assign_courier', array($this, 'assign_courier'));
        add_action('wp_ajax_shipsync_get_order_details', array($this, 'get_order_details'));
        add_action('wp_ajax_shipsync_delete_courier', array($this, 'delete_courier'));
        add_action('wp_ajax_shipsync_get_courier_orders', array($this, 'get_courier_orders'));
        add_action('wp_ajax_shipsync_get_courier_data', array($this, 'get_courier_data'));
        add_action('wp_ajax_shipsync_update_courier', array($this, 'update_courier'));

        // Frontend AJAX actions
        add_action('wp_ajax_nopriv_shipsync_track_order', array($this, 'track_order'));
        add_action('wp_ajax_shipsync_track_order', array($this, 'track_order'));

        // Backward compatibility: Register old AJAX actions
        add_action('wp_ajax_ocm_update_order_status', array($this, 'update_order_status'));
        add_action('wp_ajax_ocm_update_wc_order_status', array($this, 'update_wc_order_status'));
        add_action('wp_ajax_ocm_send_to_selected_courier', array($this, 'send_to_selected_courier'));
        add_action('wp_ajax_ocm_assign_courier', array($this, 'assign_courier'));
        add_action('wp_ajax_ocm_get_order_details', array($this, 'get_order_details'));
        add_action('wp_ajax_ocm_delete_courier', array($this, 'delete_courier'));
        add_action('wp_ajax_ocm_get_courier_orders', array($this, 'get_courier_orders'));
        add_action('wp_ajax_ocm_get_courier_data', array($this, 'get_courier_data'));
        add_action('wp_ajax_ocm_update_courier', array($this, 'update_courier'));
        add_action('wp_ajax_nopriv_ocm_track_order', array($this, 'track_order'));
        add_action('wp_ajax_ocm_track_order', array($this, 'track_order'));
    }

    public function update_order_status() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_nonce') && !wp_verify_nonce($_POST['nonce'], 'ocm_nonce'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $order_id = intval($_POST['order_id']);
        $status = sanitize_text_field($_POST['status']);
        $notes = sanitize_textarea_field($_POST['notes']);

        // Validate order status
        if (!ShipSync_Order_Status::is_valid($status)) {
            wp_send_json_error(array(
                'message' => __('Invalid order status', 'shipsync')
            ));
        }

        $result = $this->database->update_order_status($order_id, $status, $notes);

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Order status updated successfully!', 'shipsync'),
                'order_id' => $order_id,
                'status' => $status
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error updating order status. Please try again.', 'shipsync')
            ));
        }
    }

    public function update_wc_order_status() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_order_status') && !wp_verify_nonce($_POST['nonce'], 'ocm_order_status'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $order_id = intval($_POST['order_id']);
        $status = sanitize_text_field($_POST['status']);

        // Validate order status
        if (!ShipSync_Order_Status::is_valid($status)) {
            wp_send_json_error(array(
                'message' => __('Invalid order status', 'shipsync')
            ));
        }

        // Get WooCommerce order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Invalid order ID', 'shipsync')
            ));
        }

        global $wpdb;

        // Start transaction for data consistency
        $wpdb->query('START TRANSACTION');

        try {
            // Check if changing to "Out for Shipping" status
            if ($status === ShipSync_Order_Status::OUT_SHIPPING) {
            // Get enabled couriers
            $courier_manager = ShipSync_Courier_Manager::instance();
            $enabled_couriers = $courier_manager->get_enabled_couriers();

            if (empty($enabled_couriers)) {
                // No couriers enabled - just update status
                $order->set_status($status, __('Status changed to Out for Shipping', 'shipsync'));
                $order->save();
                $wpdb->query('COMMIT');

                wp_send_json_success(array(
                    'message' => __('Order status updated successfully!', 'shipsync'),
                    'order_id' => $order_id,
                    'status' => $status,
                    'courier_action' => 'none'
                ));
            } elseif (count($enabled_couriers) === 1) {
                // Only one courier - auto send
                $courier = reset($enabled_couriers);
                $result = $courier->create_order($order, array());

                if ($result['success']) {
                    $order->set_status($status, __('Status changed to Out for Shipping', 'shipsync'));
                    $order->save();
                    $wpdb->query('COMMIT');

                    wp_send_json_success(array(
                        'message' => __('Order sent to courier and status updated!', 'shipsync'),
                        'order_id' => $order_id,
                        'status' => $status,
                        'courier_action' => 'auto_sent',
                        'courier_name' => $courier->get_name()
                    ));
                } else {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array(
                        'message' => __('Failed to send to courier: ', 'shipsync') . $result['message'],
                        'order_id' => $order_id
                    ));
                }
            } else {
                // Multiple couriers - check for default
                $settings = get_option(ShipSync_Options::SETTINGS, array());
                $default_courier_id = isset($settings['default_courier']) ? $settings['default_courier'] : '';

                if ($default_courier_id && isset($enabled_couriers[$default_courier_id])) {
                    // Default courier set - use it
                    $courier = $enabled_couriers[$default_courier_id];
                    $result = $courier->create_order($order, array());

                    if ($result['success']) {
                        $order->set_status($status, __('Status changed to Out for Shipping', 'shipsync'));
                        $order->save();
                        $wpdb->query('COMMIT');

                        wp_send_json_success(array(
                            'message' => __('Order sent to default courier and status updated!', 'shipsync'),
                            'order_id' => $order_id,
                            'status' => $status,
                            'courier_action' => 'default_sent',
                            'courier_name' => $courier->get_name()
                        ));
                    } else {
                        $wpdb->query('ROLLBACK');
                        wp_send_json_error(array(
                            'message' => __('Failed to send to courier: ', 'shipsync') . $result['message'],
                            'order_id' => $order_id
                        ));
                    }
                } else {
                    // No default courier - need to show selection modal
                    $wpdb->query('ROLLBACK');
                    $courier_list = array();
                    foreach ($enabled_couriers as $courier) {
                        $courier_list[] = array(
                            'id' => $courier->get_id(),
                            'name' => $courier->get_name()
                        );
                    }

                    wp_send_json_success(array(
                        'message' => __('Please select a courier service', 'shipsync'),
                        'order_id' => $order_id,
                        'status' => $status,
                        'courier_action' => 'select_required',
                        'couriers' => $courier_list
                    ));
                }
            }
        } else {
            // Regular status change
            $order->set_status($status, __('Status changed via ShipSync', 'shipsync'));
            $order->save();
            $wpdb->query('COMMIT');

            wp_send_json_success(array(
                'message' => __('Order status updated successfully!', 'shipsync'),
                'order_id' => $order_id,
                'status' => $status,
                'courier_action' => 'none'
            ));
        }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array(
                'message' => __('Error updating order: ', 'shipsync') . $e->getMessage()
            ));
        }
    }

    public function send_to_selected_courier() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_order_status') && !wp_verify_nonce($_POST['nonce'], 'ocm_order_status'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $order_id = intval($_POST['order_id']);
        $courier_id = sanitize_text_field($_POST['courier_id']);

        // Get WooCommerce order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error(array(
                'message' => __('Invalid order ID', 'shipsync')
            ));
        }

        // Get courier
        $courier_manager = ShipSync_Courier_Manager::instance();
        $courier = $courier_manager->get_courier($courier_id);

        if (!$courier || !$courier->is_enabled()) {
            wp_send_json_error(array(
                'message' => __('Invalid courier service', 'shipsync')
            ));
        }

        global $wpdb;
        $wpdb->query('START TRANSACTION');

        try {
            // Send to courier
            $result = $courier->create_order($order, array());

            if ($result['success']) {
                // Update order status to Out for Shipping
                $order->set_status(ShipSync_Order_Status::OUT_SHIPPING, __('Status changed to Out for Shipping', 'shipsync'));
                $order->save();
                $wpdb->query('COMMIT');
                wp_send_json_success(array(
                    'message' => __('Order sent to courier and status updated!', 'shipsync'),
                    'order_id' => $order_id,
                    'courier_name' => $courier->get_name()
                ));
            } else {
                $wpdb->query('ROLLBACK');
                wp_send_json_error(array(
                    'message' => __('Failed to send to courier: ', 'shipsync') . $result['message'],
                    'order_id' => $order_id
                ));
            }
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error(array(
                'message' => __('Error sending to courier: ', 'shipsync') . $e->getMessage()
            ));
        }
    }

    public function assign_courier() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_nonce') && !wp_verify_nonce($_POST['nonce'], 'ocm_nonce'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $order_id = intval($_POST['order_id']);
        $courier_id = intval($_POST['courier_id']);

        $result = $this->database->assign_courier($order_id, $courier_id);

        if ($result !== false) {
            // Get courier name
            global $wpdb;
            $couriers_table = $wpdb->prefix . 'ocm_couriers';
            $courier = $wpdb->get_row($wpdb->prepare("SELECT name FROM $couriers_table WHERE id = %d", $courier_id));

            wp_send_json_success(array(
                'message' => __('Courier assigned successfully!', 'shipsync'),
                'order_id' => $order_id,
                'courier_id' => $courier_id,
                'courier_name' => $courier ? $courier->name : ''
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error assigning courier. Please try again.', 'shipsync')
            ));
        }
    }

    public function get_order_details() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_nonce') && !wp_verify_nonce($_POST['nonce'], 'ocm_nonce'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $order_id = intval($_POST['order_id']);
        $order = $this->database->get_order_by_id($order_id);

        if ($order) {
            // Decode order items
            $order->order_items = json_decode($order->order_items, true);

            wp_send_json_success(array(
                'order' => $order
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Order not found.', 'shipsync')
            ));
        }
    }

    public function delete_courier() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_nonce') && !wp_verify_nonce($_POST['nonce'], 'ocm_nonce'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $courier_id = intval($_POST['courier_id']);

        global $wpdb;
        $couriers_table = $wpdb->prefix . 'ocm_couriers';

        // Check if courier has assigned orders
        $orders_table = $wpdb->prefix . 'ocm_orders';
        $assigned_orders = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $orders_table WHERE courier_id = %d",
            $courier_id
        ));

        if ($assigned_orders > 0) {
            wp_send_json_error(array(
                'message' => __('Cannot delete courier with assigned orders. Please reassign orders first.', 'shipsync')
            ));
        }

        $result = $wpdb->delete($couriers_table, array('id' => $courier_id), array('%d'));

        if ($result) {
            wp_send_json_success(array(
                'message' => __('Courier deleted successfully!', 'shipsync'),
                'courier_id' => $courier_id
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error deleting courier. Please try again.', 'shipsync')
            ));
        }
    }

    public function track_order() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_frontend_nonce') && !wp_verify_nonce($_POST['nonce'], 'ocm_frontend_nonce'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        $order_number = sanitize_text_field($_POST['order_number']);

        // Use WooCommerce to find order by order number
        $order = wc_get_order(wc_get_order_id_by_order_key($order_number));

        // Alternative: Try to find by order number string
        if (!$order) {
            $orders = wc_get_orders(array(
                'meta_key' => '_order_number',
                'meta_value' => $order_number,
                'limit' => 1,
                'return' => 'ids'
            ));

            if (!empty($orders)) {
                $order = wc_get_order($orders[0]);
            }
        }

        // Fallback: try searching by order number string directly
        if (!$order) {
            $order_id = wc_get_order_id_by_order_number($order_number);
            if ($order_id) {
                $order = wc_get_order($order_id);
            }
        }

        if ($order) {
            // Get tracking information
            $tracking_data = ShipSync_Database::instance()->get_tracking_code_from_order($order);
            $status_data = ShipSync_Database::instance()->get_delivery_status_from_order($order);

            $order_data = array(
                'id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'customer_name' => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'total' => $order->get_total(),
                'status' => $order->get_status(),
                'tracking_code' => $tracking_data ? $tracking_data['tracking_code'] : null,
                'delivery_status' => $status_data ? $status_data['status'] : null,
                'courier_service' => $tracking_data ? $tracking_data['courier_service'] : null
            );

            wp_send_json_success(array(
                'order' => $order_data,
                'history' => array() // Status history not available in WooCommerce orders
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Order not found.', 'shipsync')
            ));
        }
    }

    public function get_courier_orders() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_courier_orders') && !wp_verify_nonce($_POST['nonce'], 'ocm_courier_orders'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $courier_id = intval($_POST['courier_id']);
        $orders = $this->database->get_orders_by_courier($courier_id, 50, array('pending', 'confirmed', 'preparing', 'ready', 'in_progress'));

        if (!empty($orders)) {
            ob_start();
            ?>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 0;">
                <thead>
                    <tr>
                        <th><?php _e('Order #', 'shipsync'); ?></th>
                        <th><?php _e('Customer', 'shipsync'); ?></th>
                        <th><?php _e('Total', 'shipsync'); ?></th>
                        <th><?php _e('Status', 'shipsync'); ?></th>
                        <th><?php _e('Tracking', 'shipsync'); ?></th>
                        <th><?php _e('Date', 'shipsync'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><strong><?php echo esc_html($order->order_number); ?></strong></td>
                            <td>
                                <?php echo esc_html($order->customer_name); ?><br>
                                <small style="color: #666;"><?php echo esc_html($order->customer_phone); ?></small>
                            </td>
                            <td>$<?php echo number_format($order->total_amount, 2); ?></td>
                            <td>
                                <span class="order-status" style="display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; background: #e8e8e8;">
                                    <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->order_status))); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($order->tracking_code): ?>
                                    <code style="background: #f0f0f0; padding: 2px 6px; font-size: 11px;">
                                        <?php echo esc_html($order->tracking_code); ?>
                                    </code>
                                    <?php if ($order->delivery_status): ?>
                                        <br><small style="color: #666;"><?php echo esc_html(ucfirst(str_replace('_', ' ', $order->delivery_status))); ?></small>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo date('M j, Y', strtotime($order->created_at)); ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php
            $html = ob_get_clean();

            wp_send_json_success(array('html' => $html));
        } else {
            wp_send_json_success(array(
                'html' => '<p style="text-align: center; color: #666; padding: 20px;">' .
                    __('No active orders found for this courier.', 'shipsync') .
                    '</p>'
            ));
        }
    }

    public function get_courier_data() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_get_courier') && !wp_verify_nonce($_POST['nonce'], 'ocm_get_courier'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $courier_id = intval($_POST['courier_id']);
        $courier = $this->database->get_courier_by_id($courier_id);

        if ($courier) {
            wp_send_json_success($courier);
        } else {
            wp_send_json_error(array(
                'message' => __('Courier not found.', 'shipsync')
            ));
        }
    }

    public function update_courier() {
        // Verify nonce (accept both old and new nonce names for backward compatibility)
        if (!isset($_POST['nonce']) || (!wp_verify_nonce($_POST['nonce'], 'shipsync_edit_courier') && !wp_verify_nonce($_POST['nonce'], 'ocm_edit_courier'))) {
            wp_send_json_error(array(
                'message' => __('Security check failed.', 'shipsync')
            ));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array(
                'message' => __('Insufficient permissions.', 'shipsync')
            ));
        }

        $courier_id = intval($_POST['courier_id']);

        $data = array(
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'vehicle_type' => sanitize_text_field($_POST['vehicle_type']),
            'license_number' => sanitize_text_field($_POST['license_number']),
            'status' => sanitize_text_field($_POST['status'])
        );

        $result = $this->database->update_courier($courier_id, $data);

        if ($result !== false) {
            wp_send_json_success(array(
                'message' => __('Courier updated successfully!', 'shipsync')
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Error updating courier. Please try again.', 'shipsync')
            ));
        }
    }
}
