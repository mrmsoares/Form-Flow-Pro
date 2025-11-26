<?php

/**
 * Integrations Settings Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['formflow_integrations_submit'])) {
    check_admin_referer('formflow_integrations', 'formflow_integrations_nonce');

    // Salesforce
    update_option('formflow_salesforce_enabled', isset($_POST['salesforce_enabled']) ? 1 : 0);
    update_option('formflow_salesforce_client_id', sanitize_text_field($_POST['salesforce_client_id'] ?? ''));
    update_option('formflow_salesforce_client_secret', sanitize_text_field($_POST['salesforce_client_secret'] ?? ''));
    update_option('formflow_salesforce_instance_url', esc_url_raw($_POST['salesforce_instance_url'] ?? ''));

    // HubSpot
    update_option('formflow_hubspot_enabled', isset($_POST['hubspot_enabled']) ? 1 : 0);
    update_option('formflow_hubspot_api_key', sanitize_text_field($_POST['hubspot_api_key'] ?? ''));

    // Google Sheets
    update_option('formflow_google_sheets_enabled', isset($_POST['google_sheets_enabled']) ? 1 : 0);
    update_option('formflow_google_sheets_credentials', sanitize_textarea_field($_POST['google_sheets_credentials'] ?? ''));

    // Zapier
    update_option('formflow_zapier_enabled', isset($_POST['zapier_enabled']) ? 1 : 0);

    // Slack
    update_option('formflow_slack_enabled', isset($_POST['slack_enabled']) ? 1 : 0);
    update_option('formflow_slack_webhook_url', esc_url_raw($_POST['slack_webhook_url'] ?? ''));

    // Twilio (SMS)
    update_option('formflow_twilio_enabled', isset($_POST['twilio_enabled']) ? 1 : 0);
    update_option('formflow_twilio_account_sid', sanitize_text_field($_POST['twilio_account_sid'] ?? ''));
    update_option('formflow_twilio_auth_token', sanitize_text_field($_POST['twilio_auth_token'] ?? ''));
    update_option('formflow_twilio_phone_number', sanitize_text_field($_POST['twilio_phone_number'] ?? ''));

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Integration settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current settings
$settings = [
    'salesforce' => [
        'enabled' => get_option('formflow_salesforce_enabled', 0),
        'client_id' => get_option('formflow_salesforce_client_id', ''),
        'client_secret' => get_option('formflow_salesforce_client_secret', ''),
        'instance_url' => get_option('formflow_salesforce_instance_url', ''),
    ],
    'hubspot' => [
        'enabled' => get_option('formflow_hubspot_enabled', 0),
        'api_key' => get_option('formflow_hubspot_api_key', ''),
    ],
    'google_sheets' => [
        'enabled' => get_option('formflow_google_sheets_enabled', 0),
        'credentials' => get_option('formflow_google_sheets_credentials', ''),
    ],
    'zapier' => [
        'enabled' => get_option('formflow_zapier_enabled', 0),
    ],
    'slack' => [
        'enabled' => get_option('formflow_slack_enabled', 0),
        'webhook_url' => get_option('formflow_slack_webhook_url', ''),
    ],
    'twilio' => [
        'enabled' => get_option('formflow_twilio_enabled', 0),
        'account_sid' => get_option('formflow_twilio_account_sid', ''),
        'auth_token' => get_option('formflow_twilio_auth_token', ''),
        'phone_number' => get_option('formflow_twilio_phone_number', ''),
    ],
];

// Zapier webhook URL
$zapier_webhook_url = home_url('/formflow/integrations/zapier/webhook');

// Integration definitions
$integrations = [
    'salesforce' => [
        'name' => 'Salesforce',
        'description' => __('Sync form submissions with Salesforce CRM', 'formflow-pro'),
        'icon' => 'dashicons-cloud',
        'color' => '#00A1E0',
        'category' => 'crm',
    ],
    'hubspot' => [
        'name' => 'HubSpot',
        'description' => __('Send leads to HubSpot CRM', 'formflow-pro'),
        'icon' => 'dashicons-admin-users',
        'color' => '#FF7A59',
        'category' => 'crm',
    ],
    'google_sheets' => [
        'name' => 'Google Sheets',
        'description' => __('Export submissions to Google Sheets', 'formflow-pro'),
        'icon' => 'dashicons-media-spreadsheet',
        'color' => '#0F9D58',
        'category' => 'productivity',
    ],
    'zapier' => [
        'name' => 'Zapier',
        'description' => __('Connect to 5000+ apps via Zapier', 'formflow-pro'),
        'icon' => 'dashicons-randomize',
        'color' => '#FF4A00',
        'category' => 'automation',
    ],
    'slack' => [
        'name' => 'Slack',
        'description' => __('Send notifications to Slack channels', 'formflow-pro'),
        'icon' => 'dashicons-format-chat',
        'color' => '#4A154B',
        'category' => 'communication',
    ],
    'twilio' => [
        'name' => 'Twilio',
        'description' => __('Send SMS notifications', 'formflow-pro'),
        'icon' => 'dashicons-phone',
        'color' => '#F22F46',
        'category' => 'communication',
    ],
];

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

?>

<div class="wrap formflow-admin formflow-integrations">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-plugins"></span>
        <?php esc_html_e('Integrations', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 20px;">
        <a href="?page=formflow-integrations&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Overview', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-integrations&tab=crm" class="nav-tab <?php echo $active_tab === 'crm' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-groups"></span>
            <?php esc_html_e('CRM', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-integrations&tab=automation" class="nav-tab <?php echo $active_tab === 'automation' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-randomize"></span>
            <?php esc_html_e('Automation', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-integrations&tab=communication" class="nav-tab <?php echo $active_tab === 'communication' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-megaphone"></span>
            <?php esc_html_e('Communication', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-integrations&tab=webhooks" class="nav-tab <?php echo $active_tab === 'webhooks' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-rest-api"></span>
            <?php esc_html_e('Webhooks', 'formflow-pro'); ?>
        </a>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('formflow_integrations', 'formflow_integrations_nonce'); ?>

        <!-- Overview Tab -->
        <?php if ($active_tab === 'overview') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <p style="font-size: 15px; color: #555; max-width: 800px;">
                    <?php esc_html_e('Connect FormFlow Pro with your favorite tools and services. Enable integrations to automatically sync form data with CRMs, spreadsheets, messaging platforms, and more.', 'formflow-pro'); ?>
                </p>

                <!-- Integrations Grid -->
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
                    <?php foreach ($integrations as $key => $integration) :
                        $is_enabled = $settings[$key]['enabled'] ?? false;
                    ?>
                        <div class="card" style="padding: 0; overflow: hidden; <?php echo $is_enabled ? 'border-left: 4px solid ' . $integration['color'] . ';' : ''; ?>">
                            <div style="padding: 20px;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                    <div style="display: flex; gap: 15px; align-items: center;">
                                        <div style="width: 48px; height: 48px; background: <?php echo esc_attr($integration['color']); ?>; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                            <span class="<?php echo esc_attr($integration['icon']); ?>" style="color: #fff; font-size: 24px;"></span>
                                        </div>
                                        <div>
                                            <h3 style="margin: 0; font-size: 16px;"><?php echo esc_html($integration['name']); ?></h3>
                                            <span style="color: #666; font-size: 12px; text-transform: uppercase;"><?php echo esc_html($integration['category']); ?></span>
                                        </div>
                                    </div>
                                    <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $is_enabled ? '#46b45022' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $is_enabled ? '#46b450' : '#666'; ?>; font-size: 11px; font-weight: 500;">
                                        <?php echo $is_enabled ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                                    </span>
                                </div>
                                <p style="color: #555; font-size: 13px; margin: 15px 0 0 0;">
                                    <?php echo esc_html($integration['description']); ?>
                                </p>
                            </div>
                            <div style="padding: 15px 20px; background: #f8f9fa; border-top: 1px solid #eee;">
                                <a href="?page=formflow-integrations&tab=<?php echo esc_attr($integration['category']); ?>" class="button">
                                    <?php esc_html_e('Configure', 'formflow-pro'); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- CRM Tab -->
        <?php if ($active_tab === 'crm') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <!-- Salesforce -->
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #00A1E0; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-cloud" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        Salesforce
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Salesforce', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="salesforce_enabled"
                                           value="1"
                                           <?php checked($settings['salesforce']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable Salesforce integration', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="salesforce_client_id"><?php esc_html_e('Client ID', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="salesforce_client_id"
                                       name="salesforce_client_id"
                                       value="<?php echo esc_attr($settings['salesforce']['client_id']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="salesforce_client_secret"><?php esc_html_e('Client Secret', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="salesforce_client_secret"
                                       name="salesforce_client_secret"
                                       value="<?php echo esc_attr($settings['salesforce']['client_secret']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="salesforce_instance_url"><?php esc_html_e('Instance URL', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       id="salesforce_instance_url"
                                       name="salesforce_instance_url"
                                       value="<?php echo esc_attr($settings['salesforce']['instance_url']); ?>"
                                       class="regular-text"
                                       placeholder="https://your-org.salesforce.com">
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- HubSpot -->
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #FF7A59; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-admin-users" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        HubSpot
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable HubSpot', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="hubspot_enabled"
                                           value="1"
                                           <?php checked($settings['hubspot']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable HubSpot integration', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="hubspot_api_key"><?php esc_html_e('API Key', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="hubspot_api_key"
                                       name="hubspot_api_key"
                                       value="<?php echo esc_attr($settings['hubspot']['api_key']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                                <p class="description">
                                    <?php printf(
                                        esc_html__('Get your API key from %s', 'formflow-pro'),
                                        '<a href="https://app.hubspot.com/api-key" target="_blank">HubSpot Settings</a>'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Automation Tab -->
        <?php if ($active_tab === 'automation') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <!-- Zapier -->
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #FF4A00; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-randomize" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        Zapier
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Zapier', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="zapier_enabled"
                                           value="1"
                                           <?php checked($settings['zapier']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable Zapier integration', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Webhook URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #f5f5f5;"><?php echo esc_html($zapier_webhook_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($zapier_webhook_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                                <p class="description"><?php esc_html_e('Use this URL when creating a Zapier trigger for FormFlow Pro.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 4px;">
                        <h4 style="margin-top: 0;"><?php esc_html_e('How to connect Zapier', 'formflow-pro'); ?></h4>
                        <ol style="margin-left: 20px; line-height: 1.8;">
                            <li><?php esc_html_e('Create a new Zap in Zapier', 'formflow-pro'); ?></li>
                            <li><?php esc_html_e('Choose "Webhooks by Zapier" as the trigger app', 'formflow-pro'); ?></li>
                            <li><?php esc_html_e('Select "Catch Hook" as the trigger event', 'formflow-pro'); ?></li>
                            <li><?php esc_html_e('Copy the webhook URL from Zapier', 'formflow-pro'); ?></li>
                            <li><?php esc_html_e('Use it in your form automations', 'formflow-pro'); ?></li>
                        </ol>
                    </div>
                </div>

                <!-- Google Sheets -->
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #0F9D58; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-media-spreadsheet" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        Google Sheets
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Google Sheets', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="google_sheets_enabled"
                                           value="1"
                                           <?php checked($settings['google_sheets']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable Google Sheets integration', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="google_sheets_credentials"><?php esc_html_e('Service Account JSON', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="google_sheets_credentials"
                                          name="google_sheets_credentials"
                                          rows="6"
                                          class="large-text code"
                                          placeholder='{"type": "service_account", ...}'><?php echo esc_textarea($settings['google_sheets']['credentials']); ?></textarea>
                                <p class="description">
                                    <?php printf(
                                        esc_html__('Download service account credentials from %s', 'formflow-pro'),
                                        '<a href="https://console.cloud.google.com/apis/credentials" target="_blank">Google Cloud Console</a>'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Communication Tab -->
        <?php if ($active_tab === 'communication') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <!-- Slack -->
                <div class="card" style="padding: 20px; margin-bottom: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #4A154B; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-format-chat" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        Slack
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Slack', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="slack_enabled"
                                           value="1"
                                           <?php checked($settings['slack']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable Slack notifications', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="slack_webhook_url"><?php esc_html_e('Webhook URL', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       id="slack_webhook_url"
                                       name="slack_webhook_url"
                                       value="<?php echo esc_attr($settings['slack']['webhook_url']); ?>"
                                       class="regular-text"
                                       placeholder="https://hooks.slack.com/services/...">
                                <p class="description">
                                    <?php printf(
                                        esc_html__('Create an Incoming Webhook in %s', 'formflow-pro'),
                                        '<a href="https://api.slack.com/apps" target="_blank">Slack App Settings</a>'
                                    ); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Twilio -->
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span style="display: inline-flex; width: 32px; height: 32px; background: #F22F46; border-radius: 6px; align-items: center; justify-content: center;">
                            <span class="dashicons dashicons-phone" style="color: #fff; font-size: 18px;"></span>
                        </span>
                        Twilio (SMS)
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Twilio', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="twilio_enabled"
                                           value="1"
                                           <?php checked($settings['twilio']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable SMS notifications via Twilio', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twilio_account_sid"><?php esc_html_e('Account SID', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="twilio_account_sid"
                                       name="twilio_account_sid"
                                       value="<?php echo esc_attr($settings['twilio']['account_sid']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twilio_auth_token"><?php esc_html_e('Auth Token', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="twilio_auth_token"
                                       name="twilio_auth_token"
                                       value="<?php echo esc_attr($settings['twilio']['auth_token']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="twilio_phone_number"><?php esc_html_e('Phone Number', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="twilio_phone_number"
                                       name="twilio_phone_number"
                                       value="<?php echo esc_attr($settings['twilio']['phone_number']); ?>"
                                       class="regular-text"
                                       placeholder="+1234567890">
                                <p class="description"><?php esc_html_e('Your Twilio phone number in E.164 format.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Webhooks Tab -->
        <?php if ($active_tab === 'webhooks') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-rest-api" style="color: #0073aa;"></span>
                        <?php esc_html_e('Custom Webhooks', 'formflow-pro'); ?>
                    </h2>
                    <p style="color: #666;">
                        <?php esc_html_e('Configure custom webhooks to send form data to any external service.', 'formflow-pro'); ?>
                    </p>

                    <?php
                    global $wpdb;
                    $webhooks = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}formflow_webhooks ORDER BY created_at DESC");
                    ?>

                    <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Name', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('URL', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Events', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($webhooks)) : ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 30px; color: #666;">
                                        <?php esc_html_e('No webhooks configured yet.', 'formflow-pro'); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($webhooks as $webhook) : ?>
                                    <tr>
                                        <td><strong><?php echo esc_html($webhook->name); ?></strong></td>
                                        <td><code><?php echo esc_html(wp_trim_words($webhook->url, 5)); ?></code></td>
                                        <td><?php echo esc_html($webhook->events); ?></td>
                                        <td>
                                            <span style="color: <?php echo $webhook->status === 'active' ? '#46b450' : '#666'; ?>;">
                                                <?php echo esc_html(ucfirst($webhook->status)); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <button type="button" class="button button-small"><?php esc_html_e('Edit', 'formflow-pro'); ?></button>
                                            <button type="button" class="button button-small"><?php esc_html_e('Test', 'formflow-pro'); ?></button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>

                    <p style="margin-top: 20px;">
                        <button type="button" id="add-webhook" class="button button-primary">
                            <span class="dashicons dashicons-plus-alt" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Add Webhook', 'formflow-pro'); ?>
                        </button>
                    </p>
                </div>

                <div class="card" style="padding: 20px; margin-top: 20px; background: #f8f9fa;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Webhook Payload Format', 'formflow-pro'); ?></h3>
                    <pre style="background: #23282d; color: #fff; padding: 15px; border-radius: 4px; overflow-x: auto;"><code>{
  "event": "form.submitted",
  "timestamp": "2024-01-15T10:30:00Z",
  "form": {
    "id": 1,
    "name": "Contact Form"
  },
  "submission": {
    "id": 123,
    "data": {
      "name": "John Doe",
      "email": "john@example.com",
      "message": "Hello!"
    },
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  }
}</code></pre>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($active_tab !== 'webhooks') : ?>
            <p class="submit">
                <button type="submit" name="formflow_integrations_submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                    <?php esc_html_e('Save Integration Settings', 'formflow-pro'); ?>
                </button>
            </p>
        <?php endif; ?>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    $('.copy-url').on('click', function() {
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function() {
            alert('<?php esc_html_e('Copied to clipboard!', 'formflow-pro'); ?>');
        });
    });
});
</script>
