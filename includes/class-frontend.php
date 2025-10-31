<?php
/**
 * Frontend functionality for Order & Courier Manager
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Frontend {

    /**
     * @var ShipSync_Database
     */
    private $database;

    public function __construct($database = null) {
        $this->database = $database ?: ShipSync_Database::instance();
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));
        add_shortcode('shipsync_order_card', array($this, 'order_card_shortcode'));
        add_shortcode('shipsync_track_order', array($this, 'track_order_shortcode'));

        // Backward compatibility
        add_shortcode('ocm_order_card', array($this, 'order_card_shortcode'));
        add_shortcode('ocm_track_order', array($this, 'track_order_shortcode'));
    }

    public function enqueue_frontend_scripts() {
        wp_enqueue_style('shipsync-frontend-style', SHIPSYNC_PLUGIN_URL . 'assets/css/frontend.css', array(), SHIPSYNC_VERSION);
        wp_enqueue_script('shipsync-frontend-script', SHIPSYNC_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SHIPSYNC_VERSION, true);

        // Backward compatibility
        wp_enqueue_style('ocm-frontend-style', SHIPSYNC_PLUGIN_URL . 'assets/css/frontend.css', array(), SHIPSYNC_VERSION);
        wp_enqueue_script('ocm-frontend-script', SHIPSYNC_PLUGIN_URL . 'assets/js/frontend.js', array('jquery'), SHIPSYNC_VERSION, true);

        wp_localize_script('shipsync-frontend-script', 'shipsyncFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('shipsync_frontend_nonce')
        ));

        // Backward compatibility
        wp_localize_script('ocm-frontend-script', 'ocm_frontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ocm_frontend_nonce')
        ));
    }

    public function order_card_shortcode($atts) {
        $atts = shortcode_atts(array(
            'limit' => 5,
            'show_status' => true,
            'show_courier' => true,
            'title' => 'Recent Orders'
        ), $atts);

        // Apply filter to shortcode attributes
        $atts = apply_filters('shipsync_order_card_atts', $atts);
        $atts = apply_filters('ocm_order_card_atts', $atts); // Backward compatibility

        $orders = $this->database->get_orders($atts['limit']);

        ob_start();
        ?>
        <div class="ocm-order-card-widget">
            <h3 class="ocm-widget-title"><?php echo esc_html($atts['title']); ?></h3>

            <?php if (!empty($orders)): ?>
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

                                <?php if ($atts['show_status']): ?>
                                    <div class="ocm-order-status">
                                        <span class="ocm-status-badge status-<?php echo esc_attr($order->order_status); ?>">
                                            <?php echo esc_html(ucfirst(str_replace('_', ' ', $order->order_status))); ?>
                                        </span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($atts['show_courier'] && $order->courier_name): ?>
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
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function track_order_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Track Your Order',
            'placeholder' => 'Enter your order number',
            'button_text' => 'Track Order'
        ), $atts);

        // Apply filter to shortcode attributes
        $atts = apply_filters('shipsync_track_order_atts', $atts);
        $atts = apply_filters('ocm_track_order_atts', $atts); // Backward compatibility

        ob_start();
        ?>
        <div class="ocm-track-order">
            <h3 class="ocm-track-title"><?php echo esc_html($atts['title']); ?></h3>
            <form class="ocm-track-form">
                <div class="ocm-track-input-group">
                    <input type="text"
                           name="order_number"
                           placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                           required>
                    <button type="submit" class="ocm-track-button">
                        <?php echo esc_html($atts['button_text']); ?>
                    </button>
                </div>
            </form>
            <div class="ocm-track-result"></div>
        </div>
        <?php
        return ob_get_clean();
    }
}
