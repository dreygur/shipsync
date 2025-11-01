<?php
/**
 * Helper/Utility Class
 * Common utility functions used throughout the plugin
 */

if (!defined('ABSPATH')) {
    exit;
}

class ShipSync_Helper {

    /**
     * Sanitize array recursively
     */
    public static function sanitize_array($data, $callback = 'sanitize_text_field') {
        if (!is_array($data)) {
            return is_callable($callback) ? call_user_func($callback, $data) : sanitize_text_field($data);
        }

        $sanitized = array();
        foreach ($data as $key => $value) {
            $sanitized_key = sanitize_key($key);
            $sanitized[$sanitized_key] = is_array($value)
                ? self::sanitize_array($value, $callback)
                : (is_callable($callback) ? call_user_func($callback, $value) : sanitize_text_field($value));
        }

        return $sanitized;
    }

    /**
     * Get order statistics
     */
    public static function get_order_stats() {
        return array(
            'total' => count(wc_get_orders(array('limit' => -1, 'return' => 'ids'))),
            'pending' => count(wc_get_orders(array('status' => 'pending', 'limit' => -1, 'return' => 'ids'))),
            'processing' => count(wc_get_orders(array('status' => 'processing', 'limit' => -1, 'return' => 'ids'))),
            'delivered' => count(wc_get_orders(array('status' => 'completed', 'limit' => -1, 'return' => 'ids'))),
            'cancelled' => count(wc_get_orders(array('status' => 'cancelled', 'limit' => -1, 'return' => 'ids')))
        );
    }

    /**
     * Format currency
     */
    public static function format_currency($amount, $currency_symbol = '৳') {
        return $currency_symbol . number_format((float) $amount, 2);
    }

    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status, $type = 'order') {
        $status_labels = self::get_status_labels($type);
        $status_colors = self::get_status_colors($type);

        $label = isset($status_labels[$status]) ? $status_labels[$status] : ucfirst(str_replace('_', ' ', $status));
        $color = isset($status_colors[$status]) ? $status_colors[$status] : '#666';

        return sprintf(
            '<span class="shipsync-status-badge" style="background: %s; color: #fff; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block;">%s</span>',
            esc_attr($color),
            esc_html($label)
        );
    }

    /**
     * Get status labels
     */
    public static function get_status_labels($type = 'order') {
        if ($type === 'courier') {
            return array(
                'in_review' => __('In Review', 'shipsync'),
                'pending' => __('Pending', 'shipsync'),
                'hold' => __('On Hold', 'shipsync'),
                'delivered' => __('Delivered', 'shipsync'),
                'delivered_approval_pending' => __('Delivered (Pending Approval)', 'shipsync'),
                'cancelled' => __('Cancelled', 'shipsync'),
                'cancelled_approval_pending' => __('Cancelled (Pending Approval)', 'shipsync'),
                'partial_delivered' => __('Partially Delivered', 'shipsync'),
                'partial_delivered_approval_pending' => __('Partially Delivered (Pending Approval)', 'shipsync')
            );
        }

        return array(
            'pending' => __('Pending', 'shipsync'),
            'processing' => __('Processing', 'shipsync'),
            'on-hold' => __('On Hold', 'shipsync'),
            'completed' => __('Completed', 'shipsync'),
            'cancelled' => __('Cancelled', 'shipsync'),
            'refunded' => __('Refunded', 'shipsync'),
            'failed' => __('Failed', 'shipsync'),
            'out-shipping' => __('Out for Shipping', 'shipsync')
        );
    }

    /**
     * Get status colors
     */
    public static function get_status_colors($type = 'order') {
        if ($type === 'courier') {
            return array(
                'in_review' => '#2271b1',
                'pending' => '#dba617',
                'hold' => '#d63638',
                'delivered' => '#00a32a',
                'delivered_approval_pending' => '#72aee6',
                'cancelled' => '#646970',
                'cancelled_approval_pending' => '#8c8f94',
                'partial_delivered' => '#f56e28',
                'partial_delivered_approval_pending' => '#f0b849'
            );
        }

        return array(
            'pending' => '#dba617',
            'processing' => '#2271b1',
            'on-hold' => '#d63638',
            'completed' => '#00a32a',
            'cancelled' => '#646970',
            'refunded' => '#8c8f94',
            'failed' => '#d63638',
            'out-shipping' => '#2271b1'
        );
    }

    /**
     * Render empty state
     */
    public static function render_empty_state($args = array()) {
        $defaults = array(
            'icon' => 'info',
            'title' => __('No items found', 'shipsync'),
            'message' => '',
            'actions' => array()
        );

        $args = wp_parse_args($args, $defaults);
        ?>
        <div style="text-align: center; padding: 60px 20px;">
            <div style="color: #646970; max-width: 500px; margin: 0 auto;">
                <span class="dashicons dashicons-<?php echo esc_attr($args['icon']); ?>"
                      style="font-size: 64px; color: #c3c4c7; display: block; margin-bottom: 20px; opacity: 0.5;"></span>
                <h3 style="color: #1d2327; margin: 0 0 10px 0; font-size: 18px; font-weight: 500;">
                    <?php echo esc_html($args['title']); ?>
                </h3>
                <?php if (!empty($args['message'])): ?>
                    <p style="margin: 15px 0; font-size: 14px; line-height: 1.6;">
                        <?php echo esc_html($args['message']); ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($args['actions'])): ?>
                    <div style="margin-top: 20px;">
                        <?php foreach ($args['actions'] as $action): ?>
                            <a href="<?php echo esc_url($action['url']); ?>"
                               class="<?php echo esc_attr($action['class'] ?? 'button button-primary'); ?>"
                               style="<?php echo isset($action['style']) ? esc_attr($action['style']) : ''; ?>">
                                <?php if (isset($action['icon'])): ?>
                                    <span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>"
                                          style="vertical-align: middle; margin-right: 5px;"></span>
                                <?php endif; ?>
                                <?php echo esc_html($action['text']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get pagination HTML
     */
    public static function render_pagination($current_page, $total_items, $per_page, $base_url) {
        $total_pages = ceil($total_items / $per_page);

        if ($total_pages <= 1) {
            return '';
        }

        $pagination = '<div class="tablenav-pages" style="margin: 20px 0;">';
        $pagination .= '<span class="displaying-num">' . sprintf(__('%s items', 'shipsync'), number_format_i18n($total_items)) . '</span>';
        $pagination .= '<span class="pagination-links">';

        // Previous page
        if ($current_page > 1) {
            $prev_url = add_query_arg('paged', $current_page - 1, $base_url);
            $pagination .= '<a class="button" href="' . esc_url($prev_url) . '">&laquo;</a>';
        } else {
            $pagination .= '<span class="button disabled">&laquo;</span>';
        }

        // Current page info
        $pagination .= '<span class="paging-input">';
        $pagination .= sprintf(
            __('%1$s of %2$s', 'shipsync'),
            '<span class="tablenav-paging-text">' . number_format_i18n($current_page) . '</span>',
            '<span class="total-pages">' . number_format_i18n($total_pages) . '</span>'
        );
        $pagination .= '</span>';

        // Next page
        if ($current_page < $total_pages) {
            $next_url = add_query_arg('paged', $current_page + 1, $base_url);
            $pagination .= '<a class="button" href="' . esc_url($next_url) . '">&raquo;</a>';
        } else {
            $pagination .= '<span class="button disabled">&raquo;</span>';
        }

        $pagination .= '</span></div>';

        return $pagination;
    }

    /**
     * Check if WooCommerce is active
     */
    public static function is_woocommerce_active() {
        return class_exists('WooCommerce');
    }

    /**
     * Get WooCommerce version
     */
    public static function get_woocommerce_version() {
        if (!self::is_woocommerce_active()) {
            return false;
        }

        return defined('WC_VERSION') ? WC_VERSION : get_option('woocommerce_version');
    }

    /**
     * Log debug message
     */
    public static function log($message, $context = array()) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        if (is_array($context) && !empty($context)) {
            $message .= ' | Context: ' . wp_json_encode($context);
        }

        error_log('ShipSync: ' . $message);
    }
}

