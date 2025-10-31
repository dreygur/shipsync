<?php
/**
 * Couriers admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get database instance
$database = ShipSync_Database::instance();

// Get courier statistics
$courier_stats = array();
foreach ($couriers as $courier) {
    $total_orders = $database->get_orders_count_by_courier($courier->id);
    $active_orders = $database->get_orders_count_by_courier($courier->id, array('pending', 'confirmed', 'preparing', 'ready', 'in_progress'));
    $completed_orders = $database->get_orders_count_by_courier($courier->id, array('delivered'));

    $courier_stats[$courier->id] = array(
        'total' => $total_orders,
        'active' => $active_orders,
        'completed' => $completed_orders
    );
}

// Get enabled courier integrations
$courier_manager = ShipSync_Courier_Manager::instance();
$enabled_integrations = $courier_manager->get_enabled_couriers();
?>

<div class="wrap">
    <h1><?php _e('Couriers Management', 'shipsync'); ?></h1>

    <div class="ocm-admin-header">
        <a href="<?php echo admin_url('admin.php?page=ocm-add-courier'); ?>" class="button button-primary">
            <?php _e('Add New Courier', 'shipsync'); ?>
        </a>

        <?php if (!empty($enabled_integrations)): ?>
            <a href="<?php echo admin_url('admin.php?page=ocm-courier-settings'); ?>" class="button">
                <?php _e('Courier Integrations', 'shipsync'); ?>
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($enabled_integrations)): ?>
        <div class="ocm-integration-notice" style="background: #e7f5ff; border-left: 4px solid #0073aa; padding: 12px 20px; margin: 20px 0;">
            <h3 style="margin: 0 0 8px 0;"><?php _e('Active Courier Integrations', 'shipsync'); ?></h3>
            <p style="margin: 0;">
                <?php
                $integration_names = array();
                foreach ($enabled_integrations as $integration) {
                    $integration_names[] = '<strong>' . esc_html($integration->get_name()) . '</strong>';
                }
                echo sprintf(
                    __('Connected: %s. You can send orders directly to these courier services from the order details page.', 'shipsync'),
                    implode(', ', $integration_names)
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <div class="ocm-couriers-container">
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 20%;"><?php _e('Courier Details', 'shipsync'); ?></th>
                    <th style="width: 15%;"><?php _e('Contact', 'shipsync'); ?></th>
                    <th style="width: 15%;"><?php _e('Vehicle Info', 'shipsync'); ?></th>
                    <th style="width: 15%;"><?php _e('Statistics', 'shipsync'); ?></th>
                    <th style="width: 10%;"><?php _e('Status', 'shipsync'); ?></th>
                    <th style="width: 15%;"><?php _e('Assigned Orders', 'shipsync'); ?></th>
                    <th style="width: 10%;"><?php _e('Actions', 'shipsync'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($couriers)): ?>
                    <?php foreach ($couriers as $courier): ?>
                        <?php
                        $stats = isset($courier_stats[$courier->id]) ? $courier_stats[$courier->id] : array('total' => 0, 'active' => 0, 'completed' => 0);
                        ?>
                        <tr>
                            <td>
                                <div class="courier-details">
                                    <strong style="font-size: 14px;"><?php echo esc_html($courier->name); ?></strong><br>
                                    <small style="color: #666;">
                                        <?php _e('Joined:', 'shipsync'); ?>
                                        <?php echo date('M j, Y', strtotime($courier->created_at)); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="contact-info">
                                    <small>
                                        📧 <?php echo esc_html($courier->email); ?><br>
                                        📱 <?php echo esc_html($courier->phone); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="vehicle-info">
                                    <strong><?php echo esc_html($courier->vehicle_type); ?></strong><br>
                                    <small style="color: #666;">
                                        <?php _e('License:', 'shipsync'); ?>
                                        <?php echo esc_html($courier->license_number); ?>
                                    </small>
                                </div>
                            </td>
                            <td>
                                <div class="courier-stats" style="font-size: 12px;">
                                    <div style="margin-bottom: 4px;">
                                        <strong><?php echo $stats['total']; ?></strong>
                                        <span style="color: #666;"><?php _e('Total', 'shipsync'); ?></span>
                                    </div>
                                    <div style="margin-bottom: 4px;">
                                        <strong style="color: #d63638;"><?php echo $stats['active']; ?></strong>
                                        <span style="color: #666;"><?php _e('Active', 'shipsync'); ?></span>
                                    </div>
                                    <div>
                                        <strong style="color: #00a32a;"><?php echo $stats['completed']; ?></strong>
                                        <span style="color: #666;"><?php _e('Completed', 'shipsync'); ?></span>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="courier-status status-<?php echo esc_attr($courier->status); ?>" style="display: inline-block; padding: 4px 10px; border-radius: 3px; font-size: 11px; font-weight: 600; text-transform: uppercase;">
                                    <?php if ($courier->status === 'active'): ?>
                                        <span style="color: #00a32a;">● <?php echo esc_html(ucfirst($courier->status)); ?></span>
                                    <?php else: ?>
                                        <span style="color: #999;">● <?php echo esc_html(ucfirst($courier->status)); ?></span>
                                    <?php endif; ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($stats['active'] > 0): ?>
                                    <button class="button button-small ocm-view-orders" data-courier-id="<?php echo $courier->id; ?>" data-courier-name="<?php echo esc_attr($courier->name); ?>">
                                        <?php _e('View Orders', 'shipsync'); ?> (<?php echo $stats['active']; ?>)
                                    </button>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;"><?php _e('No active orders', 'shipsync'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="courier-actions">
                                    <button class="button button-small ocm-edit-courier" data-courier-id="<?php echo $courier->id; ?>" title="<?php _e('Edit Courier', 'shipsync'); ?>">
                                        <span class="dashicons dashicons-edit" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    </button>
                                    <button class="button button-small ocm-delete-courier" data-courier-id="<?php echo $courier->id; ?>" title="<?php _e('Delete Courier', 'shipsync'); ?>" style="color: #d63638;">
                                        <span class="dashicons dashicons-trash" style="font-size: 16px; width: 16px; height: 16px;"></span>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="no-couriers" style="text-align: center; padding: 40px 20px; color: #666;">
                            <div style="font-size: 48px; margin-bottom: 15px;">🚚</div>
                            <strong style="font-size: 16px; display: block; margin-bottom: 10px;">
                                <?php _e('No couriers found.', 'shipsync'); ?>
                            </strong>
                            <p style="margin: 0;">
                                <?php _e('Add your first courier to start managing deliveries.', 'shipsync'); ?>
                            </p>
                            <a href="<?php echo admin_url('admin.php?page=ocm-add-courier'); ?>" class="button button-primary" style="margin-top: 15px;">
                                <?php _e('Add New Courier', 'shipsync'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- View Orders Modal -->
<div id="ocm-view-orders-modal" class="ocm-modal" style="display: none;">
    <div class="ocm-modal-content" style="max-width: 900px;">
        <div class="ocm-modal-header">
            <h3><?php _e('Assigned Orders', 'shipsync'); ?> - <span id="modal-courier-name"></span></h3>
            <span class="ocm-modal-close">&times;</span>
        </div>
        <div class="ocm-modal-body">
            <div id="courier-orders-list">
                <p style="text-align: center; padding: 20px;">
                    <span class="spinner is-active" style="float: none;"></span>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Edit Courier Modal -->
<div id="ocm-edit-courier-modal" class="ocm-modal" style="display: none;">
    <div class="ocm-modal-content">
        <div class="ocm-modal-header">
            <h3><?php _e('Edit Courier', 'shipsync'); ?></h3>
            <span class="ocm-modal-close">&times;</span>
        </div>
        <div class="ocm-modal-body">
            <form id="ocm-edit-courier-form">
                <input type="hidden" name="courier_id" id="edit-courier-id">
                <input type="hidden" name="shipsync_nonce" value="<?php echo wp_create_nonce('shipsync_edit_courier'); ?>">
                <input type="hidden" name="ocm_nonce" value="<?php echo wp_create_nonce('ocm_edit_courier'); ?>" style="display:none;">

                <div class="form-group">
                    <label for="edit-name"><?php _e('Name', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="text" name="name" id="edit-name" class="regular-text" required>
                </div>

                <div class="form-group">
                    <label for="edit-email"><?php _e('Email', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="email" name="email" id="edit-email" class="regular-text" required>
                </div>

                <div class="form-group">
                    <label for="edit-phone"><?php _e('Phone', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="text" name="phone" id="edit-phone" class="regular-text" required>
                </div>

                <div class="form-group">
                    <label for="edit-vehicle-type"><?php _e('Vehicle Type', 'shipsync'); ?></label>
                    <select name="vehicle_type" id="edit-vehicle-type">
                        <option value="Motorcycle"><?php _e('Motorcycle', 'shipsync'); ?></option>
                        <option value="Car"><?php _e('Car', 'shipsync'); ?></option>
                        <option value="Van"><?php _e('Van', 'shipsync'); ?></option>
                        <option value="Truck"><?php _e('Truck', 'shipsync'); ?></option>
                        <option value="Bicycle"><?php _e('Bicycle', 'shipsync'); ?></option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="edit-license-number"><?php _e('License Number', 'shipsync'); ?></label>
                    <input type="text" name="license_number" id="edit-license-number" class="regular-text">
                </div>

                <div class="form-group">
                    <label for="edit-status"><?php _e('Status', 'shipsync'); ?></label>
                    <select name="status" id="edit-status">
                        <option value="active"><?php _e('Active', 'shipsync'); ?></option>
                        <option value="inactive"><?php _e('Inactive', 'shipsync'); ?></option>
                    </select>
                </div>

                <div class="form-actions">
                    <button type="submit" class="button button-primary"><?php _e('Save Changes', 'shipsync'); ?></button>
                    <button type="button" class="button ocm-modal-close"><?php _e('Cancel', 'shipsync'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>

<script>
jQuery(document).ready(function($) {
    // View orders for courier
    $('.ocm-view-orders').on('click', function() {
        var courierId = $(this).data('courier-id');
        var courierName = $(this).data('courier-name');

        $('#modal-courier-name').text(courierName);
        $('#ocm-view-orders-modal').fadeIn();

        // Load orders
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_get_courier_orders',
                courier_id: courierId,
                nonce: '<?php echo wp_create_nonce('shipsync_courier_orders'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    $('#courier-orders-list').html(response.data.html);
                } else {
                    $('#courier-orders-list').html('<p style="text-align: center; color: #666;">' + response.data.message + '</p>');
                }
            },
            error: function() {
                $('#courier-orders-list').html('<p style="text-align: center; color: #d63638;"><?php _e('Error loading orders', 'shipsync'); ?></p>');
            }
        });
    });

    // Edit courier
    $('.ocm-edit-courier').on('click', function() {
        var courierId = $(this).data('courier-id');

        // Load courier data
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_get_courier_data',
                courier_id: courierId,
                nonce: '<?php echo wp_create_nonce('shipsync_get_courier'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var courier = response.data;
                    $('#edit-courier-id').val(courier.id);
                    $('#edit-name').val(courier.name);
                    $('#edit-email').val(courier.email);
                    $('#edit-phone').val(courier.phone);
                    $('#edit-vehicle-type').val(courier.vehicle_type);
                    $('#edit-license-number').val(courier.license_number);
                    $('#edit-status').val(courier.status);
                    $('#ocm-edit-courier-modal').fadeIn();
                }
            }
        });
    });

    // Submit edit courier form
    $('#ocm-edit-courier-form').on('submit', function(e) {
        e.preventDefault();

        var $form = $(this);
        var $btn = $form.find('button[type="submit"]');

        $btn.prop('disabled', true).text('<?php _e('Saving...', 'shipsync'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_update_courier',
                courier_id: $('#edit-courier-id').val(),
                name: $('#edit-name').val(),
                email: $('#edit-email').val(),
                phone: $('#edit-phone').val(),
                vehicle_type: $('#edit-vehicle-type').val(),
                license_number: $('#edit-license-number').val(),
                status: $('#edit-status').val(),
                nonce: ($form.find('input[name="shipsync_nonce"]').length ? $form.find('input[name="shipsync_nonce"]').val() : $form.find('input[name="ocm_nonce"]').val())
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                    $btn.prop('disabled', false).text('<?php _e('Save Changes', 'shipsync'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('An error occurred', 'shipsync'); ?>');
                $btn.prop('disabled', false).text('<?php _e('Save Changes', 'shipsync'); ?>');
            }
        });
    });

    // Delete courier
    $('.ocm-delete-courier').on('click', function() {
        if (!confirm('<?php _e('Are you sure you want to delete this courier? This action cannot be undone.', 'shipsync'); ?>')) {
            return;
        }

        var courierId = $(this).data('courier-id');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_delete_courier',
                courier_id: courierId,
                nonce: '<?php echo wp_create_nonce('shipsync_delete_courier'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            },
            error: function() {
                alert('<?php _e('An error occurred', 'shipsync'); ?>');
            }
        });
    });

    // Close modal
    $('.ocm-modal-close').on('click', function() {
        $(this).closest('.ocm-modal').fadeOut();
    });

    // Close modal on outside click
    $(window).on('click', function(e) {
        if ($(e.target).hasClass('ocm-modal')) {
            $(e.target).fadeOut();
        }
    });
});
</script>

<style>
.ocm-modal {
    position: fixed;
    z-index: 999999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    overflow: auto;
    background-color: rgba(0,0,0,0.5);
}

.ocm-modal-content {
    background-color: #fefefe;
    margin: 5% auto;
    border: 1px solid #888;
    border-radius: 4px;
    width: 90%;
    max-width: 600px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
}

.ocm-modal-header {
    padding: 20px;
    border-bottom: 1px solid #ddd;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.ocm-modal-header h3 {
    margin: 0;
    font-size: 18px;
}

.ocm-modal-close {
    font-size: 28px;
    font-weight: bold;
    color: #aaa;
    cursor: pointer;
    line-height: 20px;
}

.ocm-modal-close:hover,
.ocm-modal-close:focus {
    color: #000;
}

.ocm-modal-body {
    padding: 20px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    font-weight: 600;
}

.form-group input[type="text"],
.form-group input[type="email"],
.form-group input[type="number"],
.form-group select,
.form-group textarea {
    width: 100%;
    padding: 8px;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.form-group .required {
    color: #d63638;
}

.form-actions {
    margin-top: 20px;
    display: flex;
    gap: 10px;
}

.courier-actions {
    display: flex;
    gap: 5px;
}

.courier-actions button {
    padding: 4px 8px !important;
    min-height: auto !important;
    height: auto !important;
}
</style>
