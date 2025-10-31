<?php
/**
 * Orders admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get filter parameters
$status_filter = isset($_GET['status_filter']) ? sanitize_text_field($_GET['status_filter']) : 'all';
$search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
$paged = isset($_GET['paged']) ? intval($_GET['paged']) : 1;
$per_page = 10;

// Get orders based on filter
$args = array(
    'limit' => $per_page,
    'offset' => ($paged - 1) * $per_page,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'objects'
);

if ($status_filter !== 'all') {
    $args['status'] = $status_filter;
}

if (!empty($search)) {
    $args['s'] = $search;
}

$orders = wc_get_orders($args);

// Get statistics
$all_orders = wc_get_orders(array('limit' => -1, 'return' => 'ids'));
$stats = array(
    'total' => count($all_orders),
    'pending' => count(wc_get_orders(array('status' => 'pending', 'limit' => -1, 'return' => 'ids'))),
    'processing' => count(wc_get_orders(array('status' => 'processing', 'limit' => -1, 'return' => 'ids'))),
    'delivered' => count(wc_get_orders(array('status' => 'completed', 'limit' => -1, 'return' => 'ids'))),
    'cancelled' => count(wc_get_orders(array('status' => 'cancelled', 'limit' => -1, 'return' => 'ids')))
);

$total_filtered = count(wc_get_orders(array_merge($args, array('limit' => -1, 'return' => 'ids'))));
?>

<div class="wrap ocm-orders-dashboard">
    <h1 class="wp-heading-inline"><?php _e('Orders Management', 'shipsync'); ?></h1>
    <a href="<?php echo admin_url('post-new.php?post_type=shop_order'); ?>" class="page-title-action">
        <?php _e('Add New Order', 'shipsync'); ?>
    </a>
    <hr class="wp-header-end">

    <!-- Statistics Cards -->
    <div class="ocm-stats-cards">
        <div class="ocm-stat-card">
            <div class="ocm-stat-label"><?php _e('Total Orders', 'shipsync'); ?></div>
            <div class="ocm-stat-value"><?php echo $stats['total']; ?></div>
        </div>

        <div class="ocm-stat-card ocm-stat-pending">
            <div class="ocm-stat-label"><?php _e('Pending', 'shipsync'); ?></div>
            <div class="ocm-stat-value"><?php echo $stats['pending']; ?></div>
        </div>

        <div class="ocm-stat-card ocm-stat-processing">
            <div class="ocm-stat-label"><?php _e('Processing', 'shipsync'); ?></div>
            <div class="ocm-stat-value"><?php echo $stats['processing']; ?></div>
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
    <div class="ocm-search-bar">
        <form method="get" action="">
            <input type="hidden" name="page" value="ocm-orders">
            <input type="hidden" name="status_filter" value="<?php echo esc_attr($status_filter); ?>">
            <input type="text"
                   name="s"
                   value="<?php echo esc_attr($search); ?>"
                   placeholder="<?php _e('Search by order ID, name, phone, or tracking code...', 'shipsync'); ?>"
                   class="ocm-search-input">
            <button type="submit" class="button"><?php _e('Search', 'shipsync'); ?></button>
            <?php if ($search): ?>
                <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="button">
                    <?php _e('Clear', 'shipsync'); ?>
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Status Filter Tabs -->
    <div class="ocm-status-tabs">
        <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=all'); ?>"
           class="ocm-tab <?php echo $status_filter === 'all' ? 'active' : ''; ?>">
            <?php _e('All Orders', 'shipsync'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=pending'); ?>"
           class="ocm-tab <?php echo $status_filter === 'pending' ? 'active' : ''; ?>">
            <?php _e('Pending', 'shipsync'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=processing'); ?>"
           class="ocm-tab <?php echo $status_filter === 'processing' ? 'active' : ''; ?>">
            <?php _e('Processing', 'shipsync'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=completed'); ?>"
           class="ocm-tab <?php echo $status_filter === 'completed' ? 'active' : ''; ?>">
            <?php _e('Delivered', 'shipsync'); ?>
        </a>
        <a href="<?php echo admin_url('admin.php?page=ocm-orders&status_filter=cancelled'); ?>"
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
                    <th style="width: 12%;"><?php _e('CUSTOMER', 'shipsync'); ?></th>
                    <th style="width: 10%;"><?php _e('PHONE', 'shipsync'); ?></th>
                    <th style="width: 5%;"><?php _e('QTY', 'shipsync'); ?></th>
                    <th style="width: 10%;"><?php _e('TOTAL / COD', 'shipsync'); ?></th>
                    <th style="width: 12%;"><?php _e('ORDER STATUS', 'shipsync'); ?></th>
                    <th style="width: 12%;"><?php _e('DELIVERY STATUS', 'shipsync'); ?></th>
                    <th style="width: 18%;"><?php _e('TRACKING', 'shipsync'); ?></th>
                    <th style="width: 11%;"><?php _e('ACTIONS', 'shipsync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($orders)): ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        // Get tracking info from any courier service
                        $database = ShipSync_Database::instance();
                        $tracking_data = $database->get_tracking_code_from_order($order);
                        $status_data = $database->get_delivery_status_from_order($order);

                        $tracking_code = $tracking_data ? $tracking_data['tracking_code'] : null;
                        $consignment_id = $tracking_data ? $tracking_data['consignment_id'] : null;
                        $courier_service = $tracking_data ? $tracking_data['courier_service'] : null;
                        $delivery_status = $status_data ? $status_data['status'] : null;

                        // Get courier service display name
                        $courier_manager = ShipSync_Courier_Manager::instance();
                        $courier_service_name = '';
                        if ($courier_service) {
                            $courier_obj = $courier_manager->get_courier($courier_service);
                            if ($courier_obj) {
                                $courier_service_name = $courier_obj->get_name();
                            }
                        }

                        // Legacy courier (manual assignment)
                        $courier_id = $order->get_meta('_ocm_courier_id');
                        $courier_name = '';
                        if ($courier_id) {
                            $courier = ShipSync_Database::instance()->get_courier_by_id($courier_id);
                            if ($courier) {
                                $courier_name = $courier->name;
                            }
                        }

                        // Get order items
                        $items = $order->get_items();
                        $total_qty = 0;
                        foreach ($items as $item) {
                            $total_qty += $item->get_quantity();
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($order->get_order_number()); ?></strong>
                                <br><small style="color: #666;"><?php echo $order->get_date_created()->date('d/m/Y'); ?></small>
                            </td>
                            <td>
                                <strong><?php echo esc_html($order->get_billing_first_name() . ' ' . $order->get_billing_last_name()); ?></strong>
                            </td>
                            <td><?php echo esc_html($order->get_billing_phone()); ?></td>
                            <td><strong><?php echo $total_qty; ?></strong></td>
                            <td>
                                <strong>৳<?php echo number_format($order->get_total(), 0); ?></strong>
                                <?php
                                // Get delivery charge based on courier service
                                $delivery_charge = null;
                                if ($courier_service === 'steadfast') {
                                    $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::STEADFAST_DELIVERY_CHARGE);
                                } elseif ($courier_service === 'pathao') {
                                    $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::PATHAO_DELIVERY_CHARGE);
                                } elseif ($courier_service === 'redx') {
                                    $delivery_charge = $order->get_meta(ShipSync_Meta_Keys::REDX_DELIVERY_CHARGE);
                                }

                                if ($delivery_charge):
                                ?>
                                    <br><small style="color: #666;">+৳<?php echo number_format($delivery_charge, 0); ?> delivery</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select class="ocm-order-status-select" data-order-id="<?php echo $order->get_id(); ?>" style="padding: 6px; border: 1px solid #ddd; border-radius: 4px; width: 100%;">
                                    <option value="pending" <?php selected($order->get_status(), 'pending'); ?>><?php _e('Pending', 'shipsync'); ?></option>
                                    <option value="processing" <?php selected($order->get_status(), 'processing'); ?>><?php _e('Processing', 'shipsync'); ?></option>
                                    <option value="out-shipping" <?php selected($order->get_status(), 'out-shipping'); ?>><?php _e('Out for Shipping', 'shipsync'); ?></option>
                                    <option value="on-hold" <?php selected($order->get_status(), 'on-hold'); ?>><?php _e('On Hold', 'shipsync'); ?></option>
                                    <option value="completed" <?php selected($order->get_status(), 'completed'); ?>><?php _e('Delivered', 'shipsync'); ?></option>
                                    <option value="cancelled" <?php selected($order->get_status(), 'cancelled'); ?>><?php _e('Cancelled', 'shipsync'); ?></option>
                                </select>
                            </td>
                            <td>
                                <?php if ($delivery_status): ?>
                                    <?php
                                    $status_class = 'ocm-status-badge';
                                    $status_text = ucfirst(str_replace('_', ' ', $delivery_status));

                                    switch ($delivery_status) {
                                        case 'delivered':
                                        case 'delivered_approval_pending':
                                            $status_class .= ' status-delivered';
                                            break;
                                        case 'cancelled':
                                        case 'cancelled_approval_pending':
                                            $status_class .= ' status-cancelled';
                                            break;
                                        case 'pending':
                                            $status_class .= ' status-pending';
                                            break;
                                        default:
                                            $status_class .= ' status-in-review';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $status_class; ?>">
                                        <?php echo esc_html($status_text); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($tracking_code): ?>
                                    <?php if ($courier_service_name): ?>
                                        <small style="color: #666; font-size: 10px; display: block; margin-bottom: 4px;">
                                            <?php echo esc_html($courier_service_name); ?>
                                        </small>
                                    <?php endif; ?>
                                    <?php
                                    // Get tracking URL from courier service
                                    $tracking_url = null;
                                    if ($courier_service && $courier_obj) {
                                        $tracking_url = $courier_obj->get_tracking_url($tracking_code, $consignment_id);
                                    }
                                    ?>
                                    <code class="ocm-tracking-code"
                                          style="background: #f0f0f0; padding: 4px 8px; border-radius: 3px; font-size: 11px; display: inline-block; cursor: pointer; transition: all 0.2s; <?php echo $tracking_url ? 'color: #2271b1;' : ''; ?>"
                                          <?php if ($tracking_url): ?>
                                              data-tracking-url="<?php echo esc_attr($tracking_url); ?>"
                                              title="<?php esc_attr_e('Click to copy tracking URL', 'shipsync'); ?>"
                                          <?php endif; ?>>
                                        <?php echo esc_html($tracking_code); ?>
                                    </code>
                                    <?php if ($tracking_url): ?>
                                        <span class="dashicons dashicons-admin-page" style="font-size: 12px; color: #2271b1; margin-left: 4px; vertical-align: middle; cursor: pointer;" data-tracking-url="<?php echo esc_attr($tracking_url); ?>" title="<?php esc_attr_e('Copy tracking URL', 'shipsync'); ?>"></span>
                                    <?php endif; ?>
                                    <br><small style="color: #666; font-size: 11px;">
                                        <?php
                                        if ($delivery_status) {
                                            echo __('Status:', 'shipsync') . ' ' . ucfirst(str_replace('_', ' ', $delivery_status));
                                        } elseif ($consignment_id) {
                                            echo __('Consignment ID:', 'shipsync') . ' ' . esc_html($consignment_id);
                                        }
                                        ?>
                                    </small>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;"><?php _e('No tracking', 'shipsync'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                // Prepare order details for copy/WhatsApp
                                $customer_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();

                                // Get customer note (special instructions from checkout)
                                // This is the note customers add during checkout for special delivery instructions
                                $customer_note = $order->get_customer_note();
                                if (!empty($customer_note)) {
                                    $customer_note = wp_strip_all_tags($customer_note);
                                    $customer_note = trim($customer_note);
                                } else {
                                    $customer_note = '';
                                }

                                $order_details = array(
                                    'order_id' => $order->get_order_number(),
                                    'customer_name' => $customer_name,
                                    'phone' => $order->get_billing_phone(),
                                    'address' => $order->get_billing_address_1() . ($order->get_billing_address_2() ? ', ' . $order->get_billing_address_2() : '') . ', ' . $order->get_billing_city(),
                                    'quantity' => $total_qty,
                                    'notes' => $customer_note
                                );
                                ?>
                                <div class="ocm-action-buttons">
                                    <button class="ocm-copy-order" title="<?php _e('Copy Order Details', 'shipsync'); ?>"
                                            data-order-details='<?php echo json_encode($order_details, JSON_UNESCAPED_UNICODE); ?>'>
                                        <span class="dashicons dashicons-admin-page"></span>
                                    </button>
                                    <button class="ocm-whatsapp-order" title="<?php _e('Send to WhatsApp', 'shipsync'); ?>"
                                            data-order-details='<?php echo json_encode($order_details, JSON_UNESCAPED_UNICODE); ?>'>
                                        <span class="dashicons dashicons-share"></span>
                                    </button>
                                    <a href="<?php echo $order->get_edit_order_url(); ?>" class="ocm-action-btn" title="<?php _e('Edit Order', 'shipsync'); ?>">
                                        <span class="dashicons dashicons-edit"></span>
                                    </a>
                                </div>
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
                                <?php _e('No orders found', 'shipsync'); ?>
                            </h3>
                            <p style="margin: 0 0 20px 0; color: #646970; max-width: 500px; margin-left: auto; margin-right: auto;">
                                <?php if ($search): ?>
                                    <?php _e('No orders match your search criteria. Try adjusting your filters or search terms.', 'shipsync'); ?>
                                <?php elseif ($status_filter !== 'all'): ?>
                                    <?php _e('No orders match the selected status. Try selecting a different status filter or view all orders.', 'shipsync'); ?>
                                <?php else: ?>
                                    <?php _e('Orders will appear here once customers place them.', 'shipsync'); ?>
                                <?php endif; ?>
                            </p>
                            <?php if ($search || $status_filter !== 'all'): ?>
                                <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="button button-secondary">
                                    <span class="dashicons dashicons-filter" style="vertical-align: middle; margin-right: 5px;"></span>
                                    <?php _e('Clear Filters', 'shipsync'); ?>
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_filtered > $per_page): ?>
        <div class="ocm-pagination">
            <?php
            $total_pages = ceil($total_filtered / $per_page);

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
        </div>
    <?php endif; ?>
</div>

<style>
/* Additional page-specific styles */
.ocm-orders-dashboard {
    background: #f0f0f1;
    margin: -10px -20px -20px -10px;
    padding: 20px;
}

.ocm-stat-pending .ocm-stat-value { color: #dba617; }
.ocm-stat-processing .ocm-stat-value { color: #2271b1; }
.ocm-stat-delivered .ocm-stat-value { color: #00a32a; }
.ocm-stat-cancelled .ocm-stat-value { color: #d63638; }


/* Courier Selection Modal */
.ocm-courier-modal {
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.6);
    display: none;
}

.ocm-courier-modal-content {
    background-color: #fefefe;
    margin: 10% auto;
    border: 1px solid #888;
    border-radius: 8px;
    width: 90%;
    max-width: 500px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

.ocm-courier-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f9f9f9;
    border-radius: 8px 8px 0 0;
}

.ocm-courier-modal-header h3 {
    margin: 0;
    color: #333;
}

.ocm-courier-modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    transition: color 0.2s;
}

.ocm-courier-modal-close:hover {
    color: #000;
}

.ocm-courier-modal-body {
    padding: 25px;
}

.ocm-courier-option {
    padding: 15px;
    margin-bottom: 10px;
    border: 2px solid #ddd;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
}

.ocm-courier-option:hover {
    border-color: #2271b1;
    background: #f0f6fc;
}

.ocm-courier-option input[type="radio"] {
    margin-right: 12px;
    width: 18px;
    height: 18px;
}

.ocm-courier-option label {
    margin: 0;
    cursor: pointer;
    font-size: 15px;
    font-weight: 500;
}

.ocm-courier-modal-footer {
    padding: 15px 25px;
    border-top: 1px solid #ddd;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    background: #f9f9f9;
    border-radius: 0 0 8px 8px;
}
</style>

<!-- Courier Selection Modal -->
<div id="ocm-courier-selection-modal" class="ocm-courier-modal">
    <div class="ocm-courier-modal-content">
        <div class="ocm-courier-modal-header">
            <h3><?php _e('Select Courier Service', 'shipsync'); ?></h3>
            <span class="ocm-courier-modal-close">&times;</span>
        </div>
        <div class="ocm-courier-modal-body">
            <p style="margin-top: 0; color: #666;"><?php _e('Please select a courier service to ship this order:', 'shipsync'); ?></p>
            <div id="ocm-courier-options">
                <!-- Courier options will be dynamically inserted here -->
            </div>
        </div>
        <div class="ocm-courier-modal-footer">
            <button type="button" class="button ocm-courier-modal-close"><?php _e('Cancel', 'shipsync'); ?></button>
            <button type="button" class="button button-primary" id="ocm-confirm-courier"><?php _e('Confirm & Ship', 'shipsync'); ?></button>
        </div>
    </div>
</div>

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>

<?php
// Inline JavaScript has been moved to assets/js/admin-orders.js
// The script is enqueued via class-admin.php
?>
