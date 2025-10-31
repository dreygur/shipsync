<?php
/**
 * Add Courier admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Add New Courier', 'shipsync'); ?></h1>

    <form method="post" action="" class="ocm-form">
        <input type="hidden" name="ocm_action" value="add_courier">
        <?php wp_nonce_field('ocm_action', 'ocm_nonce'); ?>

        <div class="ocm-form-section">
            <h3><?php _e('Courier Information', 'shipsync'); ?></h3>

            <div class="ocm-form-row">
                <div class="ocm-form-group">
                    <label for="name"><?php _e('Full Name', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="text" id="name" name="name" required>
                </div>

                <div class="ocm-form-group">
                    <label for="email"><?php _e('Email', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                </div>
            </div>

            <div class="ocm-form-row">
                <div class="ocm-form-group">
                    <label for="phone"><?php _e('Phone Number', 'shipsync'); ?> <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required>
                </div>

                <div class="ocm-form-group">
                    <label for="vehicle_type"><?php _e('Vehicle Type', 'shipsync'); ?></label>
                    <select id="vehicle_type" name="vehicle_type">
                        <option value=""><?php _e('Select vehicle type...', 'shipsync'); ?></option>
                        <option value="Motorcycle"><?php _e('Motorcycle', 'shipsync'); ?></option>
                        <option value="Car"><?php _e('Car', 'shipsync'); ?></option>
                        <option value="Bicycle"><?php _e('Bicycle', 'shipsync'); ?></option>
                        <option value="Scooter"><?php _e('Scooter', 'shipsync'); ?></option>
                        <option value="Van"><?php _e('Van', 'shipsync'); ?></option>
                        <option value="Truck"><?php _e('Truck', 'shipsync'); ?></option>
                    </select>
                </div>
            </div>

            <div class="ocm-form-row">
                <div class="ocm-form-group">
                    <label for="license_number"><?php _e('License Number', 'shipsync'); ?></label>
                    <input type="text" id="license_number" name="license_number" placeholder="<?php _e('e.g., MC123456', 'shipsync'); ?>">
                </div>

                <div class="ocm-form-group">
                    <label for="status"><?php _e('Status', 'shipsync'); ?></label>
                    <select id="status" name="status">
                        <option value="active"><?php _e('Active', 'shipsync'); ?></option>
                        <option value="inactive"><?php _e('Inactive', 'shipsync'); ?></option>
                        <option value="suspended"><?php _e('Suspended', 'shipsync'); ?></option>
                    </select>
                </div>
            </div>
        </div>

        <div class="ocm-form-actions">
            <button type="submit" class="button button-primary button-large"><?php _e('Add Courier', 'shipsync'); ?></button>
            <a href="<?php echo admin_url('admin.php?page=ocm-couriers'); ?>" class="button button-secondary button-large"><?php _e('Cancel', 'shipsync'); ?></a>
        </div>
    </form>
</div>

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>
