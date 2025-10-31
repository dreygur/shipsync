<?php
/**
 * Courier Orders/Consignments Dashboard
 * Shows all orders sent to courier services
 */

if (!defined('ABSPATH')) {
    exit;
}

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

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>

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
