<?php
/**
 * Order Card Widget for Order & Courier Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Order_Card_Widget extends WP_Widget {

    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null) {
        $this->database = $database ?: ShipSync_Database::instance();
        parent::__construct(
            'shipsync_order_card_widget',
            __('Order Card Widget', 'shipsync'),
            array(
                'description' => __('Display recent orders in a card format', 'shipsync'),
                'classname' => 'ocm-order-card-widget'
            )
        );
    }

    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Orders', 'shipsync');
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 5;
        $show_status = !empty($instance['show_status']);
        $show_courier = !empty($instance['show_courier']);

        echo $args['before_widget'];

        if ($title) {
            echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        }

        $orders = $this->database->get_orders($limit);

        if (!empty($orders)): ?>
            <div class="ocm-orders-list">
                <?php foreach ($orders as $order):
                    // Apply filter to order display data
                    $order = apply_filters('shipsync_order_display_data', $order);
                    $order = apply_filters('ocm_order_display_data', $order); // Backward compatibility
                ?>
                    <div class="ocm-order-item">
                        <div class="ocm-order-header">
                            <span class="ocm-order-number"><?php echo esc_html($order->order_number); ?></span>
                            <span class="ocm-order-total">$<?php echo number_format($order->total_amount, 2); ?></span>
                        </div>

                        <div class="ocm-order-details">
                            <div class="ocm-customer-info">
                                <strong><?php echo esc_html($order->customer_name); ?></strong>
                                <span class="ocm-customer-email"><?php echo esc_html($order->customer_email); ?></span>
                            </div>

                            <?php if ($show_status): ?>
                                <div class="ocm-order-status">
                                    <span class="ocm-status-badge status-<?php echo esc_attr($order->order_status); ?>">
                                        <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->order_status))); ?>
                                    </span>
                                </div>
                            <?php endif; ?>

                            <?php if ($show_courier && $order->courier_name): ?>
                                <div class="ocm-courier-info">
                                    <span class="ocm-courier-label"><?php _e('Courier:', 'shipsync'); ?></span>
                                    <span class="ocm-courier-name"><?php echo esc_html($order->courier_name); ?></span>
                                </div>
                            <?php endif; ?>

                            <div class="ocm-order-date">
                                <?php echo date('M j, Y g:i A', strtotime($order->created_at)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="ocm-widget-footer">
                <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="ocm-view-all-btn">
                    <?php _e('View All Orders', 'shipsync'); ?>
                </a>
            </div>
        <?php else: ?>
            <div class="ocm-no-orders">
                <p><?php _e('No orders found.', 'shipsync'); ?></p>
            </div>
        <?php endif;

        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : __('Recent Orders', 'shipsync');
        $limit = !empty($instance['limit']) ? intval($instance['limit']) : 5;
        $show_status = !empty($instance['show_status']);
        $show_courier = !empty($instance['show_courier']);
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'shipsync'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>"
                   name="<?php echo $this->get_field_name('title'); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>

        <p>
            <label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('Number of orders to display:', 'shipsync'); ?></label>
            <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>"
                   name="<?php echo $this->get_field_name('limit'); ?>" type="number"
                   value="<?php echo esc_attr($limit); ?>" min="1" max="20">
        </p>

        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_status); ?>
                   id="<?php echo $this->get_field_id('show_status'); ?>"
                   name="<?php echo $this->get_field_name('show_status'); ?>">
            <label for="<?php echo $this->get_field_id('show_status'); ?>"><?php _e('Show order status', 'shipsync'); ?></label>
        </p>

        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_courier); ?>
                   id="<?php echo $this->get_field_id('show_courier'); ?>"
                   name="<?php echo $this->get_field_name('show_courier'); ?>">
            <label for="<?php echo $this->get_field_id('show_courier'); ?>"><?php _e('Show courier information', 'shipsync'); ?></label>
        </p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? sanitize_text_field($new_instance['title']) : '';
        $instance['limit'] = (!empty($new_instance['limit'])) ? intval($new_instance['limit']) : 5;
        $instance['show_status'] = !empty($new_instance['show_status']);
        $instance['show_courier'] = !empty($new_instance['show_courier']);

        return $instance;
    }
}
