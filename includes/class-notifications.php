<?php
/**
 * Notification system for Order & Courier Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Notifications {

    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null) {
        $this->database = $database ?: ShipSync_Database::instance();
        // Hook into order status updates
        add_action('shipsync_order_status_updated', array($this, 'send_status_update_email'), 10, 4);
        add_action('shipsync_order_created', array($this, 'send_order_created_email'), 10, 2);
        add_action('shipsync_courier_assigned', array($this, 'send_courier_assigned_email'), 10, 2);

        // Backward compatibility
        add_action('ocm_order_status_updated', array($this, 'send_status_update_email'), 10, 4);
        add_action('ocm_order_created', array($this, 'send_order_created_email'), 10, 2);
        add_action('ocm_courier_assigned', array($this, 'send_courier_assigned_email'), 10, 2);
    }

    /**
     * Send email notification when order status is updated
     */
    public function send_status_update_email($order_id, $new_status, $old_status, $notes) {
        // Check if notifications are enabled
        $settings = get_option('ocm_settings', array());
        if (empty($settings['enable_notifications'])) {
            return;
        }

        $order = $this->database->get_order_by_id($order_id);
        if (!$order) {
            return;
        }

        $to = $order->customer_email;
        $subject = sprintf(__('Order %s Status Update', 'shipsync'), $order->order_number);

        $status_labels = array(
            'pending' => __('Pending', 'shipsync'),
            'confirmed' => __('Confirmed', 'shipsync'),
            'preparing' => __('Preparing', 'shipsync'),
            'ready' => __('Ready for Pickup', 'shipsync'),
            'in_progress' => __('In Progress', 'shipsync'),
            'delivered' => __('Delivered', 'shipsync'),
            'cancelled' => __('Cancelled', 'shipsync')
        );

        $new_status_label = isset($status_labels[$new_status]) ? $status_labels[$new_status] : ucfirst($new_status);

        // Build email message
        $message = sprintf(__('Hello %s,', 'shipsync'), $order->customer_name) . "\n\n";
        $message .= sprintf(__('Your order %s has been updated.', 'shipsync'), $order->order_number) . "\n\n";
        $message .= sprintf(__('New Status: %s', 'shipsync'), $new_status_label) . "\n";

        if (!empty($notes)) {
            $message .= "\n" . __('Notes:', 'shipsync') . " " . $notes . "\n";
        }

        if ($order->courier_name) {
            $message .= "\n" . sprintf(__('Courier: %s', 'shipsync'), $order->courier_name) . "\n";
        }

        $message .= "\n" . sprintf(__('Order Total: $%s', 'shipsync'), number_format($order->total_amount, 2)) . "\n";
        $message .= "\n" . __('Thank you for your order!', 'shipsync') . "\n";

        // Apply filter to allow customization
        $message = apply_filters('shipsync_status_update_email_message', $message, $order, $new_status, $old_status, $notes);
        $message = apply_filters('ocm_status_update_email_message', $message, $order, $new_status, $old_status, $notes); // Backward compatibility
        $subject = apply_filters('shipsync_status_update_email_subject', $subject, $order, $new_status);
        $subject = apply_filters('ocm_status_update_email_subject', $subject, $order, $new_status); // Backward compatibility

        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);

        // Fire action after email sent
        do_action('shipsync_status_update_email_sent', $order_id, $to, $subject, $message);
        do_action('ocm_status_update_email_sent', $order_id, $to, $subject, $message); // Backward compatibility
    }

    /**
     * Send email notification when new order is created
     */
    public function send_order_created_email($order_id, $order_data) {
        // Check if notifications are enabled
        $settings = get_option('ocm_settings', array());
        if (empty($settings['enable_notifications'])) {
            return;
        }

        $order = $this->database->get_order_by_id($order_id);
        if (!$order) {
            return;
        }

        $to = $order->customer_email;
        $subject = sprintf(__('Order Confirmation - %s', 'shipsync'), $order->order_number);

        // Build email message
        $message = sprintf(__('Hello %s,', 'shipsync'), $order->customer_name) . "\n\n";
        $message .= __('Thank you for your order!', 'shipsync') . "\n\n";
        $message .= sprintf(__('Order Number: %s', 'shipsync'), $order->order_number) . "\n";
        $message .= sprintf(__('Order Total: $%s', 'shipsync'), number_format($order->total_amount, 2)) . "\n";
        $message .= sprintf(__('Status: %s', 'shipsync'), ucfirst($order->order_status)) . "\n\n";
        $message .= __('Order Items:', 'shipsync') . "\n";

        // Decode and display order items
        $items = json_decode($order->order_items, true);
        if (is_array($items)) {
            foreach ($items as $item) {
                $message .= sprintf("- %s x%d - $%s\n", $item['item'], $item['quantity'], number_format($item['price'], 2));
            }
        }

        $message .= "\n" . sprintf(__('Delivery Address: %s', 'shipsync'), $order->customer_address) . "\n";
        $message .= "\n" . __('We will notify you when your order status changes.', 'shipsync') . "\n";

        // Apply filter to allow customization
        $message = apply_filters('shipsync_order_created_email_message', $message, $order);
        $message = apply_filters('ocm_order_created_email_message', $message, $order); // Backward compatibility
        $subject = apply_filters('shipsync_order_created_email_subject', $subject, $order);
        $subject = apply_filters('ocm_order_created_email_subject', $subject, $order); // Backward compatibility

        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);

        // Fire action after email sent
        do_action('shipsync_order_created_email_sent', $order_id, $to, $subject, $message);
        do_action('ocm_order_created_email_sent', $order_id, $to, $subject, $message); // Backward compatibility
    }

    /**
     * Send email notification when courier is assigned
     */
    public function send_courier_assigned_email($order_id, $courier_id) {
        // Check if notifications are enabled
        $settings = get_option('ocm_settings', array());
        if (empty($settings['enable_notifications'])) {
            return;
        }

        $order = $this->database->get_order_by_id($order_id);
        if (!$order || !$order->courier_name) {
            return;
        }

        $to = $order->customer_email;
        $subject = sprintf(__('Courier Assigned to Order %s', 'shipsync'), $order->order_number);

        // Build email message
        $message = sprintf(__('Hello %s,', 'shipsync'), $order->customer_name) . "\n\n";
        $message .= sprintf(__('A courier has been assigned to your order %s.', 'shipsync'), $order->order_number) . "\n\n";
        $message .= sprintf(__('Courier: %s', 'shipsync'), $order->courier_name) . "\n";
        $message .= sprintf(__('Order Total: $%s', 'shipsync'), number_format($order->total_amount, 2)) . "\n";
        $message .= "\n" . __('Your order will be delivered soon!', 'shipsync') . "\n";

        // Apply filter to allow customization
        $message = apply_filters('shipsync_courier_assigned_email_message', $message, $order, $courier_id);
        $message = apply_filters('ocm_courier_assigned_email_message', $message, $order, $courier_id); // Backward compatibility
        $subject = apply_filters('shipsync_courier_assigned_email_subject', $subject, $order);
        $subject = apply_filters('ocm_courier_assigned_email_subject', $subject, $order); // Backward compatibility

        // Send email
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);

        // Fire action after email sent
        do_action('shipsync_courier_assigned_email_sent', $order_id, $courier_id, $to, $subject, $message);
        do_action('ocm_courier_assigned_email_sent', $order_id, $courier_id, $to, $subject, $message); // Backward compatibility
    }
}
