<?php
/**
 * Settings admin page template
 */

if (!defined('ABSPATH')) {
    exit;
}

// Show success message if settings were saved
if (isset($_GET['settings-updated']) && $_GET['settings-updated'] === 'true'): ?>
    <div class="notice notice-success is-dismissible ocm-admin-notice">
        <p>
            <span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px; vertical-align: middle;"></span>
            <?php _e('Settings saved successfully!', 'shipsync'); ?>
        </p>
    </div>
    <script>jQuery(document).ready(function($){ setTimeout(function(){ $(".ocm-admin-notice").fadeOut(300); }, 5000); });</script>
<?php endif; ?>

<div class="wrap shipsync-settings-wrap">
    <h1 class="shipsync-page-title">
        <span class="dashicons dashicons-admin-generic" style="margin-right: 8px; color: #2271b1;"></span>
        <?php _e('ShipSync Settings', 'shipsync'); ?>
    </h1>
    <p class="description" style="margin-bottom: 25px; color: #646970;">
        <?php _e('Configure general plugin settings, email notifications, and widget display options.', 'shipsync'); ?>
    </p>

    <form method="post" action="" class="shipsync-settings-form">
        <?php wp_nonce_field('shipsync_settings', 'shipsync_settings_nonce'); ?>

        <div class="shipsync-settings-section">
            <div class="shipsync-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-settings" style="color: #2271b1; margin-right: 8px; vertical-align: middle;"></span>
                    <?php _e('General Settings', 'shipsync'); ?>
                </h2>
                <p class="description"><?php _e('Configure basic plugin behavior and defaults.', 'shipsync'); ?></p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="orders_per_page">
                            <span class="dashicons dashicons-list-view" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Orders per page', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number"
                               id="orders_per_page"
                               name="orders_per_page"
                               value="<?php echo esc_attr($settings['orders_per_page']); ?>"
                               min="5"
                               max="100"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Number of orders to display per page in the admin area. Recommended: 20-50.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="default_order_status">
                            <span class="dashicons dashicons-flag" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Default order status', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="default_order_status" name="default_order_status" class="regular-text">
                            <option value="pending" <?php selected($settings['default_order_status'], 'pending'); ?>><?php _e('Pending', 'shipsync'); ?></option>
                            <option value="confirmed" <?php selected($settings['default_order_status'], 'confirmed'); ?>><?php _e('Confirmed', 'shipsync'); ?></option>
                            <option value="preparing" <?php selected($settings['default_order_status'], 'preparing'); ?>><?php _e('Preparing', 'shipsync'); ?></option>
                            <option value="ready" <?php selected($settings['default_order_status'], 'ready'); ?>><?php _e('Ready for Pickup', 'shipsync'); ?></option>
                        </select>
                        <p class="description">
                            <?php _e('Default status assigned to new orders when they are created.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <span class="dashicons dashicons-email-alt" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                        <?php _e('Email notifications', 'shipsync'); ?>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   name="enable_notifications"
                                   value="1"
                                   <?php checked($settings['enable_notifications']); ?>>
                            <?php _e('Enable email notifications', 'shipsync'); ?>
                        </label>
                        <p class="description">
                            <?php _e('Send email notifications to customers when order status changes.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="shipsync-settings-section">
            <div class="shipsync-section-header">
                <h2>
                    <span class="dashicons dashicons-admin-widgets" style="color: #2271b1; margin-right: 8px; vertical-align: middle;"></span>
                    <?php _e('Widget Settings', 'shipsync'); ?>
                </h2>
                <p class="description"><?php _e('Customize the appearance and behavior of the order card widget on the frontend.', 'shipsync'); ?></p>
            </div>

            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="widget_title">
                            <span class="dashicons dashicons-edit" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Widget title', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               id="widget_title"
                               name="widget_title"
                               value="<?php echo esc_attr(get_option('ocm_widget_title', 'Recent Orders')); ?>"
                               class="regular-text">
                        <p class="description">
                            <?php _e('Title displayed above the order card widget on the frontend.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <label for="widget_orders_limit">
                            <span class="dashicons dashicons-admin-post" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Number of orders to display', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="number"
                               id="widget_orders_limit"
                               name="widget_orders_limit"
                               value="<?php echo esc_attr(get_option('ocm_widget_orders_limit', 5)); ?>"
                               min="1"
                               max="20"
                               class="small-text">
                        <p class="description">
                            <?php _e('Maximum number of orders to show in the widget (1-20).', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>

                <tr>
                    <th scope="row">
                        <span class="dashicons dashicons-visibility" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                        <?php _e('Display options', 'shipsync'); ?>
                    </th>
                    <td>
                        <fieldset>
                            <legend class="screen-reader-text"><span><?php _e('Display options', 'shipsync'); ?></span></legend>
                            <label style="display: block; margin-bottom: 10px;">
                                <input type="checkbox"
                                       name="widget_show_status"
                                       value="1"
                                       <?php checked(get_option('ocm_widget_show_status', true)); ?>>
                                <?php _e('Show order status', 'shipsync'); ?>
                            </label>
                            <p class="description" style="margin-left: 25px; margin-top: -5px; margin-bottom: 15px;">
                                <?php _e('Display order status badges in the widget.', 'shipsync'); ?>
                            </p>

                            <label>
                                <input type="checkbox"
                                       name="widget_show_courier"
                                       value="1"
                                       <?php checked(get_option('ocm_widget_show_courier', true)); ?>>
                                <?php _e('Show courier information', 'shipsync'); ?>
                            </label>
                            <p class="description" style="margin-left: 25px; margin-top: 5px;">
                                <?php _e('Display courier name and tracking information in the widget.', 'shipsync'); ?>
                            </p>
                        </fieldset>
                    </td>
                </tr>
            </table>
        </div>

        <div class="shipsync-save-actions">
            <button type="submit" name="save_settings" class="button button-primary button-large">
                <span class="dashicons dashicons-yes-alt" style="margin-right: 5px; vertical-align: middle;"></span>
                <?php _e('Save Settings', 'shipsync'); ?>
            </button>
            <span class="description" style="margin-left: 15px; color: #646970;">
                <?php _e('Save your changes to apply the settings.', 'shipsync'); ?>
            </span>
        </div>
    </form>
</div>

<?php
// Render admin footer
ShipSync_Admin::render_admin_footer();
?>

<style>
.shipsync-settings-wrap {
    background: #fff;
    padding: 20px;
    margin: 20px 0;
}

.shipsync-page-title {
    font-size: 23px;
    font-weight: 400;
    margin: 0 0 8px 0;
    padding: 9px 0 4px 0;
    line-height: 1.3;
}

.shipsync-settings-section {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    padding: 0;
    margin-bottom: 25px;
    overflow: hidden;
}

.shipsync-section-header {
    padding: 20px 20px 15px 20px;
    background: #f6f7f7;
    border-bottom: 1px solid #dcdcde;
}

.shipsync-section-header h2 {
    margin: 0 0 8px 0;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
}

.shipsync-section-header .description {
    margin: 0;
    color: #646970;
    font-size: 13px;
}

.shipsync-settings-section .form-table {
    margin: 0;
    border: none;
}

.shipsync-settings-section .form-table th {
    padding: 20px 20px 15px 20px;
    background: #fafafa;
    font-weight: 600;
    width: 200px;
}

.shipsync-settings-section .form-table td {
    padding: 15px 20px;
}

.shipsync-settings-section .form-table tr:not(:last-child) {
    border-bottom: 1px solid #f0f0f1;
}

.shipsync-settings-section .form-table tr:last-child td {
    padding-bottom: 20px;
}

.shipsync-settings-section .form-table label {
    display: inline-flex;
    align-items: center;
    font-weight: 600;
}

.shipsync-save-actions {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 1px solid #dcdcde;
    display: flex;
    align-items: center;
}

.ocm-admin-notice {
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.shipsync-settings-section fieldset {
    border: none;
    padding: 0;
    margin: 0;
}

.shipsync-settings-section fieldset label {
    font-weight: normal;
}
</style>
