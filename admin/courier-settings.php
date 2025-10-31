<?php
/**
 * Courier Settings Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$courier_manager = ShipSync_Courier_Manager::instance();
$couriers = $courier_manager->get_couriers();

// Handle form submission
// Accept both old and new nonce names for backward compatibility
$save_settings = false;
if (isset($_POST['shipsync_save_courier_settings']) && check_admin_referer('shipsync_courier_settings', 'shipsync_courier_nonce')) {
    $save_settings = true;
} elseif (isset($_POST['ocm_save_courier_settings']) && check_admin_referer('ocm_courier_settings', 'ocm_courier_nonce')) {
    $save_settings = true;
}

if ($save_settings) {
    $settings = array();

    foreach ($couriers as $courier_id => $courier) {
        if (isset($_POST['courier_' . $courier_id])) {
            $settings[$courier_id] = array();

            foreach ($courier->get_settings_fields() as $field_id => $field) {
                $field_name = 'courier_' . $courier_id . '_' . $field_id;

                if (isset($_POST[$field_name])) {
                    if ($field['type'] === 'checkbox') {
                        $settings[$courier_id][$field_id] = $_POST[$field_name] === '1';
                    } else {
                        $settings[$courier_id][$field_id] = sanitize_text_field($_POST[$field_name]);
                    }
                } else if ($field['type'] === 'checkbox') {
                    $settings[$courier_id][$field_id] = false;
                }
            }
        }
    }

    update_option('ocm_courier_settings', $settings);

    // Save other options
    update_option('ocm_enable_courier_logs', isset($_POST['enable_courier_logs']));
    update_option('ocm_enable_webhook_logs', isset($_POST['enable_webhook_logs']));

    // Save default courier
    if (isset($_POST['default_courier'])) {
        $ocm_settings = get_option('ocm_settings', array());
        $ocm_settings['default_courier'] = sanitize_text_field($_POST['default_courier']);
        update_option('ocm_settings', $ocm_settings);
    }

    // Save webhook authentication settings
    if (isset($_POST['webhook_auth_enabled'])) {
        update_option('ocm_webhook_auth_enabled', true);

        if (isset($_POST['webhook_auth_token']) && !empty($_POST['webhook_auth_token'])) {
            update_option('ocm_webhook_auth_token', sanitize_text_field($_POST['webhook_auth_token']));
        }

        if (isset($_POST['webhook_auth_method'])) {
            $method = sanitize_text_field($_POST['webhook_auth_method']);
            if (in_array($method, array('header', 'api_token', 'bearer', 'query', 'both'))) {
                update_option('ocm_webhook_auth_method', $method);
            }
        }
    } else {
        update_option('ocm_webhook_auth_enabled', false);
    }

    echo '<div class="notice notice-success is-dismissible ocm-admin-notice"><p><span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px;"></span>' . __('Settings saved successfully!', 'shipsync') . '</p></div>';
    echo '<script>jQuery(document).ready(function($){ setTimeout(function(){ $(".ocm-admin-notice").fadeOut(300); }, 5000); });</script>';
}

// Get current active tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';
?>

<div class="wrap shipsync-settings-wrap">
    <h1 class="shipsync-page-title">
        <span class="dashicons dashicons-admin-settings" style="margin-right: 8px; color: #2271b1;"></span>
        <?php _e('Courier Integration Settings', 'shipsync'); ?>
    </h1>
    <p class="description" style="margin-bottom: 20px; color: #646970;">
        <?php _e('Configure and manage your courier service integrations. Enable services, set credentials, and customize delivery options.', 'shipsync'); ?>
    </p>

    <h2 class="nav-tab-wrapper">
        <a href="?page=ocm-courier-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <?php _e('General', 'shipsync'); ?>
        </a>
        <?php foreach ($couriers as $courier): ?>
            <a href="?page=ocm-courier-settings&tab=<?php echo esc_attr($courier->get_id()); ?>" class="nav-tab <?php echo $active_tab === $courier->get_id() ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($courier->get_name()); ?>
            </a>
        <?php endforeach; ?>
        <a href="?page=ocm-courier-settings&tab=webhooks" class="nav-tab <?php echo $active_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
            <?php _e('Webhooks', 'shipsync'); ?>
        </a>
    </h2>

    <form method="post" action="">
        <?php wp_nonce_field('shipsync_courier_settings', 'shipsync_courier_nonce'); ?>
        <?php wp_nonce_field('ocm_courier_settings', 'ocm_courier_nonce'); // Backward compatibility ?>

        <?php if ($active_tab === 'general'): ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Default Courier Service', 'shipsync'); ?></th>
                    <td>
                        <?php
                        $enabled_couriers = $courier_manager->get_enabled_couriers();
                        $ocm_settings = get_option('ocm_settings', array());
                        $default_courier = isset($ocm_settings['default_courier']) ? $ocm_settings['default_courier'] : '';
                        ?>
                        <select name="default_courier" style="min-width: 300px;">
                            <option value=""><?php _e('None (Prompt for selection)', 'shipsync'); ?></option>
                            <?php foreach ($enabled_couriers as $courier_id => $courier): ?>
                                <option value="<?php echo esc_attr($courier_id); ?>" <?php selected($default_courier, $courier_id); ?>>
                                    <?php echo esc_html($courier->get_name()); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">
                            <?php _e('Select a default courier service to use when changing order status to "Out for Shipping". If set, orders will automatically be sent to this courier without prompting.', 'shipsync'); ?>
                            <br>
                            <?php _e('If no default is set and multiple couriers are enabled, you will be prompted to select one.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Courier Logs', 'shipsync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_courier_logs" value="1" <?php checked(get_option('ocm_enable_courier_logs', false)); ?>>
                            <?php _e('Enable logging of courier API activities', 'shipsync'); ?>
                        </label>
                        <p class="description"><?php _e('Logs will be stored in WordPress debug log if WP_DEBUG_LOG is enabled.', 'shipsync'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Enable Webhook Logs', 'shipsync'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_webhook_logs" value="1" <?php checked(get_option('ocm_enable_webhook_logs', false)); ?>>
                            <?php _e('Enable logging of webhook activities', 'shipsync'); ?>
                        </label>
                        <p class="description"><?php _e('Recent webhook logs will be stored in transients for debugging.', 'shipsync'); ?></p>
                    </td>
                </tr>
            </table>

        <?php elseif ($active_tab === 'webhooks'): ?>
            <h2><?php _e('Webhook Configuration', 'shipsync'); ?></h2>
            <p><?php _e('Configure webhook authentication and endpoints. Use these URLs in your courier service dashboard to receive real-time status updates.', 'shipsync'); ?></p>

            <table class="form-table" style="margin-top: 20px;">
                <tr>
                    <th scope="row">
                        <label for="webhook_auth_enabled">
                            <span class="dashicons dashicons-lock" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Enable Webhook Authentication', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <label>
                            <input type="checkbox"
                                   id="webhook_auth_enabled"
                                   name="webhook_auth_enabled"
                                   value="1"
                                   <?php checked(get_option('ocm_webhook_auth_enabled', false)); ?>>
                            <?php _e('Require authentication token for incoming webhooks', 'shipsync'); ?>
                        </label>
                        <p class="description">
                            <?php _e('When enabled, webhooks must include a valid authentication token in the request header or query parameter.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="webhook_auth_token">
                            <span class="dashicons dashicons-admin-network" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Webhook Secret Token', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <input type="text"
                               id="webhook_auth_token"
                               name="webhook_auth_token"
                               value="<?php echo esc_attr(get_option('ocm_webhook_auth_token', '')); ?>"
                               class="regular-text"
                               placeholder="<?php esc_attr_e('Enter your webhook secret token', 'shipsync'); ?>">
                        <button type="button" class="button button-small" id="generate-webhook-token" style="margin-left: 10px;">
                            <span class="dashicons dashicons-update" style="font-size: 16px; vertical-align: middle;"></span>
                            <?php _e('Generate Token', 'shipsync'); ?>
                        </button>
                        <p class="description">
                            <?php _e('Secret token for authenticating webhook requests. Use this token based on the selected authentication method.', 'shipsync'); ?>
                            <br>
                            <strong><?php _e('Example X-Webhook-Token:', 'shipsync'); ?></strong> <code>X-Webhook-Token: your-token-here</code>
                            <br>
                            <strong><?php _e('Example X-API-Token:', 'shipsync'); ?></strong> <code>X-API-Token: your-token-here</code>
                            <br>
                            <strong><?php _e('Example Bearer Token:', 'shipsync'); ?></strong> <code>Authorization: Bearer your-token-here</code>
                            <br>
                            <strong><?php _e('Example Query:', 'shipsync'); ?></strong> <code>?token=your-token-here</code>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="webhook_auth_method">
                            <span class="dashicons dashicons-admin-settings" style="color: #646970; margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Authentication Method', 'shipsync'); ?>
                        </label>
                    </th>
                    <td>
                        <select id="webhook_auth_method" name="webhook_auth_method">
                            <option value="header" <?php selected(get_option('ocm_webhook_auth_method', 'header'), 'header'); ?>>
                                <?php _e('Header (X-Webhook-Token)', 'shipsync'); ?>
                            </option>
                            <option value="api_token" <?php selected(get_option('ocm_webhook_auth_method', 'header'), 'api_token'); ?>>
                                <?php _e('API Token (X-API-Token)', 'shipsync'); ?>
                            </option>
                            <option value="bearer" <?php selected(get_option('ocm_webhook_auth_method', 'header'), 'bearer'); ?>>
                                <?php _e('Bearer Token (Authorization Header)', 'shipsync'); ?>
                            </option>
                            <option value="query" <?php selected(get_option('ocm_webhook_auth_method', 'header'), 'query'); ?>>
                                <?php _e('Query Parameter (token)', 'shipsync'); ?>
                            </option>
                            <option value="both" <?php selected(get_option('ocm_webhook_auth_method', 'header'), 'both'); ?>>
                                <?php _e('Any Method (All Headers/Query)', 'shipsync'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Choose how webhooks should send the authentication token. Bearer token uses standard Authorization header format.', 'shipsync'); ?>
                        </p>
                    </td>
                </tr>
            </table>

            <h3 style="margin-top: 30px;">
                <span class="dashicons dashicons-admin-links" style="color: #2271b1; margin-right: 5px; vertical-align: middle;"></span>
                <?php _e('Webhook Endpoints', 'shipsync'); ?>
            </h3>
            <p><?php _e('Copy and configure these webhook URLs in your courier service dashboard:', 'shipsync'); ?></p>

            <table class="widefat striped">
                <thead>
                <tr>
                    <th><?php _e('Courier Service', 'shipsync'); ?></th>
                    <th><?php _e('Webhook URL', 'shipsync'); ?></th>
                    <th><?php _e('Authenticated URL', 'shipsync'); ?></th>
                </tr>
                </thead>
                <tbody>
                    <?php
                    $webhook_auth_enabled = get_option('ocm_webhook_auth_enabled', false);
                    $webhook_token = get_option('ocm_webhook_auth_token', '');
                    $webhook_auth_method = get_option('ocm_webhook_auth_method', 'header');
                    foreach ($couriers as $courier):
                        $base_url = ShipSync_Courier_Webhook::get_webhook_url($courier->get_id());
                        $auth_url = $base_url;
                        if ($webhook_auth_enabled && !empty($webhook_token)) {
                            if ($webhook_auth_method === 'query' || $webhook_auth_method === 'both') {
                                $auth_url = add_query_arg('token', $webhook_token, $base_url);
                            }
                        }
                    ?>
                        <tr>
                            <td><strong><?php echo esc_html($courier->get_name()); ?></strong></td>
                            <td>
                                <code style="display: block; padding: 8px; background: #f5f5f5; margin: 5px 0; word-break: break-all;">
                                    <?php echo esc_url($base_url); ?>
                                </code>
                                <button type="button" class="button button-small ocm-copy-webhook" data-url="<?php echo esc_attr($base_url); ?>">
                                    <?php _e('Copy URL', 'shipsync'); ?>
                                </button>
                            </td>
                            <td>
                                <?php if ($webhook_auth_enabled && !empty($webhook_token)): ?>
                                    <code style="display: block; padding: 8px; background: #f5f5f5; margin: 5px 0; word-break: break-all;">
                                        <?php echo esc_url($auth_url); ?>
                                    </code>
                                    <button type="button" class="button button-small ocm-copy-webhook" data-url="<?php echo esc_attr($auth_url); ?>">
                                        <?php _e('Copy Authenticated URL', 'shipsync'); ?>
                                    </button>
                                    <?php if ($webhook_auth_method === 'header' || $webhook_auth_method === 'api_token' || $webhook_auth_method === 'bearer' || $webhook_auth_method === 'both'): ?>
                                        <p class="description" style="margin-top: 8px; font-size: 12px; color: #646970;">
                                            <?php if ($webhook_auth_method === 'header'): ?>
                                                <?php _e('Use header:', 'shipsync'); ?> <code>X-Webhook-Token: <?php echo esc_html($webhook_token); ?></code>
                                            <?php elseif ($webhook_auth_method === 'api_token'): ?>
                                                <?php _e('Use header:', 'shipsync'); ?> <code>X-API-Token: <?php echo esc_html($webhook_token); ?></code>
                                            <?php elseif ($webhook_auth_method === 'bearer'): ?>
                                                <?php _e('Use Bearer token:', 'shipsync'); ?> <code>Authorization: Bearer <?php echo esc_html($webhook_token); ?></code>
                                            <?php else: ?>
                                                <?php _e('Use any of:', 'shipsync'); ?>
                                                <br>
                                                <code>X-Webhook-Token: <?php echo esc_html($webhook_token); ?></code>
                                                <br>
                                                <code>X-API-Token: <?php echo esc_html($webhook_token); ?></code>
                                                <br>
                                                <code>Authorization: Bearer <?php echo esc_html($webhook_token); ?></code>
                                            <?php endif; ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span style="color: #646970; font-style: italic;">
                                        <?php _e('Authentication not enabled', 'shipsync'); ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <?php
            // Show recent webhook logs
            $logs = get_transient('ocm_webhook_logs');
            if (!empty($logs) && is_array($logs)):
            ?>
                <h3 style="margin-top: 30px;"><?php _e('Recent Webhook Logs', 'shipsync'); ?></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <th><?php _e('Time', 'shipsync'); ?></th>
                            <th><?php _e('Courier', 'shipsync'); ?></th>
                            <th><?php _e('Type', 'shipsync'); ?></th>
                            <th><?php _e('Details', 'shipsync'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($logs, 0, 20) as $log): ?>
                            <tr>
                                <td><?php echo esc_html($log['timestamp']); ?></td>
                                <td><?php echo esc_html($log['courier']); ?></td>
                                <td><?php echo isset($log['payload']['notification_type']) ? esc_html($log['payload']['notification_type']) : '-'; ?></td>
                                <td>
                                    <details>
                                        <summary style="cursor: pointer;"><?php _e('View Payload', 'shipsync'); ?></summary>
                                        <pre style="background: #f5f5f5; padding: 10px; overflow: auto; max-height: 300px;"><?php echo esc_html(wp_json_encode($log['payload'], JSON_PRETTY_PRINT)); ?></pre>
                                    </details>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        <?php else:
            // Courier-specific settings
            $courier = $courier_manager->get_courier($active_tab);
            if ($courier):
                $fields = $courier->get_settings_fields();
                $current_settings = get_option('ocm_courier_settings', array());
                $courier_settings = isset($current_settings[$active_tab]) ? $current_settings[$active_tab] : array();
            ?>
                <input type="hidden" name="courier_<?php echo esc_attr($active_tab); ?>" value="1">

                <h2><?php echo esc_html($courier->get_name()); ?> <?php _e('Settings', 'shipsync'); ?></h2>

                <?php
                // Show plugin status for couriers that use external plugins
                $plugin_status = array();
                if ($active_tab === 'steadfast') {
                    if (class_exists('ShipSync_Steadfast_API_Wrapper')) {
                        $is_active = ShipSync_Steadfast_API_Wrapper::is_plugin_active();
                        $is_configured = ShipSync_Steadfast_API_Wrapper::is_configured();
                        $plugin_status = array(
                            'name' => 'SteadFast API',
                            'active' => $is_active,
                            'configured' => $is_configured
                        );
                    }
                } elseif ($active_tab === 'pathao') {
                    if (class_exists('ShipSync_Pathao_API_Wrapper')) {
                        $is_active = ShipSync_Pathao_API_Wrapper::is_plugin_active();
                        $is_configured = ShipSync_Pathao_API_Wrapper::is_configured();
                        $plugin_status = array(
                            'name' => 'Pathao Courier',
                            'active' => $is_active,
                            'configured' => $is_configured
                        );
                    }
                } elseif ($active_tab === 'redx') {
                    if (class_exists('ShipSync_RedX_API_Wrapper')) {
                        $is_active = ShipSync_RedX_API_Wrapper::is_plugin_active();
                        $is_configured = ShipSync_RedX_API_Wrapper::is_configured();
                        $plugin_status = array(
                            'name' => 'RedX for WooCommerce',
                            'active' => $is_active,
                            'configured' => $is_configured
                        );
                    }
                }

                if (!empty($plugin_status)):
                ?>
                    <div class="notice plugin-status-notice <?php echo $plugin_status['active'] && $plugin_status['configured'] ? 'notice-success' : 'notice-warning'; ?>" style="border-left-width: 4px;">
                        <p>
                            <strong>
                                <span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span>
                                <?php echo esc_html($plugin_status['name']); ?> <?php _e('Plugin Status', 'shipsync'); ?>
                            </strong>
                            <?php if ($plugin_status['active']): ?>
                                <span style="color: #00a32a; margin-left: 15px;">
                                    <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                                    <?php _e('Active', 'shipsync'); ?>
                                </span>
                                <?php if ($plugin_status['configured']): ?>
                                    <span style="color: #00a32a; margin-left: 10px;">
                                        <span class="dashicons dashicons-admin-settings" style="vertical-align: middle;"></span>
                                        <?php _e('Configured', 'shipsync'); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #d63638; margin-left: 10px;">
                                        <span class="dashicons dashicons-warning" style="vertical-align: middle;"></span>
                                        <?php _e('Not Configured', 'shipsync'); ?>
                                    </span>
                                    <br><small style="margin-top: 8px; display: inline-block; color: #646970;">
                                        <?php printf(__('Please configure the %s plugin in its settings page.', 'shipsync'), esc_html($plugin_status['name'])); ?>
                                    </small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span style="color: #d63638; margin-left: 15px;">
                                    <span class="dashicons dashicons-admin-plugins" style="vertical-align: middle;"></span>
                                    <?php _e('Not Installed/Active', 'shipsync'); ?>
                                </span>
                                <br><small style="margin-top: 8px; display: inline-block; color: #646970;">
                                    <?php printf(__('The %s plugin is recommended for better integration. Please install and activate it.', 'shipsync'), esc_html($plugin_status['name'])); ?>
                                </small>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php endif; ?>

                <table class="form-table">
                    <?php
                    // For Steadfast: Skip credential fields if plugin is active and configured
                    $skip_credential_fields = false;
                    if ($active_tab === 'steadfast' && class_exists('ShipSync_Steadfast_API_Wrapper')) {
                        $skip_credential_fields = ShipSync_Steadfast_API_Wrapper::is_plugin_active() && ShipSync_Steadfast_API_Wrapper::is_configured();
                    }

                    foreach ($fields as $field_id => $field):
                        // Skip credential fields if plugin is active and configured
                        if ($skip_credential_fields && ($field_id === 'api_key' || $field_id === 'secret_key')) {
                            continue;
                        }
                    ?>
                        <?php if ($field['type'] === 'html'): ?>
                            <tr>
                                <td colspan="2">
                                    <?php
                                    // HTML fields are trusted content from our own code, but we escape what we can
                                    echo wp_kses_post($field['html']);
                                    ?>
                                </td>
                            </tr>
                        <?php else: ?>
                        <tr>
                            <th scope="row">
                                <label for="courier_<?php echo esc_attr($active_tab . '_' . $field_id); ?>">
                                    <?php echo esc_html($field['title']); ?>
                                    <?php if (isset($field['required']) && $field['required']): ?>
                                        <span class="required" style="color: red;">*</span>
                                    <?php endif; ?>
                                </label>
                            </th>
                            <td>
                                <?php
                                $field_name = 'courier_' . $active_tab . '_' . $field_id;
                                $field_value = isset($courier_settings[$field_id]) ? $courier_settings[$field_id] : (isset($field['default']) ? $field['default'] : '');

                                switch ($field['type']):
                                    case 'html':
                                        // Output HTML directly
                                        echo $field['html'];
                                        break;

                                    case 'text':
                                    case 'password':
                                        ?>
                                        <input type="<?php echo esc_attr($field['type']); ?>"
                                               id="<?php echo esc_attr($field_name); ?>"
                                               name="<?php echo esc_attr($field_name); ?>"
                                               value="<?php echo esc_attr($field_value); ?>"
                                               class="regular-text"
                                               <?php echo isset($field['required']) && $field['required'] ? 'required' : ''; ?>>
                                        <?php
                                        break;

                                    case 'checkbox':
                                        ?>
                                        <label>
                                            <input type="checkbox"
                                                   id="<?php echo esc_attr($field_name); ?>"
                                                   name="<?php echo esc_attr($field_name); ?>"
                                                   value="1"
                                                   <?php checked($field_value, true); ?>>
                                            <?php if (isset($field['label'])): ?>
                                                <?php echo esc_html($field['label']); ?>
                                            <?php endif; ?>
                                        </label>
                                        <?php
                                        break;

                                    case 'select':
                                        ?>
                                        <select id="<?php echo esc_attr($field_name); ?>"
                                                name="<?php echo esc_attr($field_name); ?>">
                                            <?php foreach ($field['options'] as $option_value => $option_label): ?>
                                                <option value="<?php echo esc_attr($option_value); ?>"
                                                        <?php selected($field_value, $option_value); ?>>
                                                    <?php echo esc_html($option_label); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php
                                        break;

                                    case 'textarea':
                                        ?>
                                        <textarea id="<?php echo esc_attr($field_name); ?>"
                                                  name="<?php echo esc_attr($field_name); ?>"
                                                  rows="5"
                                                  class="large-text"><?php echo esc_textarea($field_value); ?></textarea>
                                        <?php
                                        break;
                                endswitch;
                                ?>

                                <?php if (isset($field['description']) && $field['type'] !== 'html'): ?>
                                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </table>

                <?php if ($courier->is_enabled()): ?>
                    <div class="shipsync-test-connection-card" style="margin-top: 30px; padding: 20px; background: #f6f7f7; border: 1px solid #dcdcde; border-radius: 4px; border-left: 4px solid #2271b1;">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px;">
                            <span class="dashicons dashicons-admin-tools" style="color: #2271b1;"></span>
                            <?php _e('Test Connection', 'shipsync'); ?>
                        </h3>
                        <p style="margin-bottom: 15px; color: #646970;">
                            <?php _e('Validate your API credentials and check connectivity with the courier service.', 'shipsync'); ?>
                        </p>
                        <button type="button" class="button button-secondary" id="test-courier-connection" data-courier="<?php echo esc_attr($active_tab); ?>">
                            <span class="dashicons dashicons-update" style="margin-right: 5px; vertical-align: middle;"></span>
                            <?php _e('Test Connection', 'shipsync'); ?>
                        </button>
                        <div id="test-result" style="margin-top: 15px;"></div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

        <div class="shipsync-save-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #dcdcde;">
            <button type="submit" name="ocm_save_courier_settings" class="button button-primary button-large">
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

<script>
jQuery(document).ready(function($) {
    // Copy webhook URL
    $('.ocm-copy-webhook').on('click', function() {
        var url = $(this).data('url');
        var $btn = $(this);

        // Create temporary input
        var $temp = $('<input>');
        $('body').append($temp);
        $temp.val(url).select();
        document.execCommand('copy');
        $temp.remove();

        // Show feedback
        $btn.text('<?php _e('Copied!', 'shipsync'); ?>');
        setTimeout(function() {
            $btn.text('<?php _e('Copy URL', 'shipsync'); ?>');
        }, 2000);
    });

    // Generate webhook token
    $('#generate-webhook-token').on('click', function() {
        var token = 'shipsync_' + Math.random().toString(36).substring(2, 15) + Math.random().toString(36).substring(2, 15);
        $('#webhook_auth_token').val(token);
    });

    // Test courier connection
    $('#test-courier-connection').on('click', function() {
        var $btn = $(this);
        var courier = $btn.data('courier');
        var $result = $('#test-result');

        var originalHtml = $btn.html();
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="animation: ocm-spin 1s linear infinite; margin-right: 5px; vertical-align: middle;"></span><?php _e('Testing...', 'shipsync'); ?>');
        $result.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'shipsync_validate_courier_credentials',
                courier: courier,
                nonce: '<?php echo wp_create_nonce('shipsync_settings_nonce'); ?>'
            },
            success: function(response) {
                var icon = response.success ? '<span class="dashicons dashicons-yes-alt" style="color: #00a32a; margin-right: 5px;"></span>' : '<span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 5px;"></span>';
                var noticeClass = response.success ? 'notice-success' : 'notice-error';
                $result.html('<div class="notice ' + noticeClass + ' inline shipsync-test-result"><p>' + icon + response.data.message + '</p></div>');

                // Auto-dismiss after 8 seconds
                setTimeout(function() {
                    $result.fadeOut(300, function() {
                        $(this).html('').show();
                    });
                }, 8000);

                $btn.prop('disabled', false).html(originalHtml);
            },
            error: function() {
                $result.html('<div class="notice notice-error inline shipsync-test-result"><p><span class="dashicons dashicons-warning" style="color: #d63638; margin-right: 5px;"></span><?php _e('An error occurred while testing the connection. Please try again.', 'shipsync'); ?></p></div>');
                $btn.prop('disabled', false).html(originalHtml);
            }
        });
    });
});
</script>

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

.nav-tab-wrapper {
    margin-bottom: 25px;
    border-bottom: 1px solid #dcdcde;
}

.nav-tab {
    padding: 8px 15px;
    font-weight: 500;
    transition: all 0.2s;
}

.nav-tab:hover {
    background: #f0f0f1;
}

.nav-tab-active {
    border-bottom: 2px solid #2271b1;
    background: #fff;
}

.form-table {
    background: #fff;
    border: 1px solid #dcdcde;
    border-radius: 4px;
    overflow: hidden;
}

.form-table th {
    padding: 15px 20px;
    background: #f6f7f7;
    font-weight: 600;
    width: 200px;
}

.form-table td {
    padding: 15px 20px;
}

.shipsync-test-connection-card {
    transition: box-shadow 0.2s;
}

.shipsync-test-connection-card:hover {
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.shipsync-save-actions {
    display: flex;
    align-items: center;
}

.shipsync-test-result {
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes ocm-spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.ocm-admin-notice {
    animation: slideDown 0.3s;
}

@keyframes slideDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.form-table tr:not(:last-child) {
    border-bottom: 1px solid #f0f0f1;
}

/* Improve plugin status notice */
.plugin-status-notice {
    padding: 15px 20px;
    border-radius: 4px;
    margin: 20px 0;
}

.plugin-status-notice p {
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.plugin-status-notice strong {
    display: flex;
    align-items: center;
    gap: 5px;
}

.notice.inline {
    margin: 10px 0 0 0;
    padding: 10px;
}
</style>
