<?php
/**
 * Add Order admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Add New Order', 'shipsync'); ?></h1>

    <form method="post" action="" class="ocm-form">
        <input type="hidden" name="ocm_action" value="add_order">
        <?php wp_nonce_field('ocm_action', 'ocm_nonce'); ?>

        <div class="ocm-form-row">
            <div class="ocm-form-group">
                <label for="order_number"><?php _e('Order Number', 'shipsync'); ?> <span class="required">*</span></label>
                <input type="text" id="order_number" name="order_number" required
                       value="<?php echo 'ORD-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT); ?>">
            </div>

            <div class="ocm-form-group">
                <label for="order_status"><?php _e('Status', 'shipsync'); ?></label>
                <select id="order_status" name="order_status">
                    <option value="pending"><?php _e('Pending', 'shipsync'); ?></option>
                    <option value="confirmed"><?php _e('Confirmed', 'shipsync'); ?></option>
                    <option value="preparing"><?php _e('Preparing', 'shipsync'); ?></option>
                    <option value="ready"><?php _e('Ready for Pickup', 'shipsync'); ?></option>
                    <option value="in_progress"><?php _e('In Progress', 'shipsync'); ?></option>
                    <option value="delivered"><?php _e('Delivered', 'shipsync'); ?></option>
                </select>
            </div>
        </div>

        <div class="ocm-form-section">
            <h3><?php _e('Customer Information', 'shipsync'); ?></h3>

            <div class="ocm-form-row">
                <div class="ocm-form-group">
                    <label for="customer_name"><?php _e('Customer Name', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="text" id="customer_name" name="customer_name" required>
                </div>

                <div class="ocm-form-group">
                    <label for="customer_email"><?php _e('Email', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="email" id="customer_email" name="customer_email" required>
                </div>
            </div>

            <div class="ocm-form-row">
                <div class="ocm-form-group">
                    <label for="customer_phone"><?php _e('Phone', 'shipsync'); ?></label>
                    <input type="tel" id="customer_phone" name="customer_phone">
                </div>
            </div>

            <div class="ocm-form-group">
                <label for="customer_address"><?php _e('Delivery Address', 'shipsync'); ?> <span class="required">*</span></label>
                <textarea id="customer_address" name="customer_address" rows="3" required></textarea>
            </div>
        </div>

        <div class="ocm-form-section">
            <h3><?php _e('Order Items', 'shipsync'); ?></h3>

            <div id="order-items-container">
                <div class="order-item">
                    <div class="ocm-form-row">
                        <div class="ocm-form-group">
                            <label><?php _e('Item Name', 'shipsync'); ?></label>
                            <input type="text" name="order_items[0][item]" placeholder="<?php _e('e.g., Pizza Margherita', 'shipsync'); ?>">
                        </div>

                        <div class="ocm-form-group">
                            <label><?php _e('Quantity', 'shipsync'); ?></label>
                            <input type="number" name="order_items[0][quantity]" min="1" value="1">
                        </div>

                        <div class="ocm-form-group">
                            <label><?php _e('Price', 'shipsync'); ?></label>
                            <input type="number" name="order_items[0][price]" step="0.01" min="0" placeholder="0.00">
                        </div>

                        <div class="ocm-form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="button remove-item"><?php _e('Remove', 'shipsync'); ?></button>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" id="add-item" class="button button-secondary"><?php _e('Add Item', 'shipsync'); ?></button>
        </div>

        <div class="ocm-form-section">
            <div class="ocm-form-group">
                <label for="total_amount"><?php _e('Total Amount', 'shipsync'); ?> <span class="required">*</span></label>
                <input type="number" id="total_amount" name="total_amount" step="0.01" min="0" required>
            </div>
        </div>

        <div class="ocm-form-actions">
            <button type="submit" class="button button-primary button-large"><?php _e('Add Order', 'shipsync'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=ocm-orders'); ?>" class="button button-secondary button-large"><?php _e('Cancel', 'shipsync'); ?></a>
        </div>
    </form>
</div>

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>

<script>
jQuery(document).ready(function($) {
    let itemIndex = 1;

    // Add new item
    $('#add-item').on('click', function() {
        const itemHtml = `
            <div class="order-item">
                <div class="ocm-form-row">
                    <div class="ocm-form-group">
                        <label><?php _e('Item Name', 'shipsync'); ?></label>
                        <input type="text" name="order_items[${itemIndex}][item]" placeholder="<?php _e('e.g., Pizza Margherita', 'shipsync'); ?>">
                    </div>

                    <div class="ocm-form-group">
                        <label><?php _e('Quantity', 'shipsync'); ?></label>
                        <input type="number" name="order_items[${itemIndex}][quantity]" min="1" value="1">
                    </div>

                    <div class="ocm-form-group">
                        <label><?php _e('Price', 'shipsync'); ?></label>
                        <input type="number" name="order_items[${itemIndex}][price]" step="0.01" min="0" placeholder="0.00">
                    </div>

                    <div class="ocm-form-group">
                        <label>&nbsp;</label>
                        <button type="button" class="button remove-item"><?php _e('Remove', 'shipsync'); ?></button>
                    </div>
                </div>
            </div>
        `;

        $('#order-items-container').append(itemHtml);
        itemIndex++;
    });

    // Remove item
    $(document).on('click', '.remove-item', function() {
        $(this).closest('.order-item').remove();
    });

    // Calculate total
    $(document).on('input', 'input[name*="[quantity]"], input[name*="[price]"]', function() {
        let total = 0;
        $('.order-item').each(function() {
            const quantity = parseFloat($(this).find('input[name*="[quantity]"]').val()) || 0;
            const price = parseFloat($(this).find('input[name*="[price]"]').val()) || 0;
            total += quantity * price;
        });
        $('#total_amount').val(total.toFixed(2));
    });
});
</script>
