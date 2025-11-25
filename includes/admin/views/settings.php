<?php

/**
 * Settings Page
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['formflow_settings_submit'])) {
    check_admin_referer('formflow_settings', 'formflow_settings_nonce');

    $tab = sanitize_text_field($_POST['current_tab'] ?? 'general');

    // General settings
    if ($tab === 'general') {
        update_option('formflow_company_name', sanitize_text_field($_POST['company_name'] ?? ''));
        update_option('formflow_company_email', sanitize_email($_POST['company_email'] ?? ''));
        update_option('formflow_submissions_per_page', intval($_POST['submissions_per_page'] ?? 25));
        update_option('formflow_delete_data_on_uninstall', isset($_POST['delete_data_on_uninstall']) ? 1 : 0);
    }

    // Autentique settings
    if ($tab === 'autentique') {
        update_option('formflow_autentique_api_key', sanitize_text_field($_POST['autentique_api_key'] ?? ''));
        update_option('formflow_autentique_sandbox_mode', isset($_POST['autentique_sandbox_mode']) ? 1 : 0);
        update_option('formflow_autentique_webhook_url', sanitize_text_field($_POST['autentique_webhook_url'] ?? ''));
        update_option('formflow_autentique_auto_send', isset($_POST['autentique_auto_send']) ? 1 : 0);
        update_option('formflow_autentique_reminder_enabled', isset($_POST['autentique_reminder_enabled']) ? 1 : 0);
        update_option('formflow_autentique_document_message', sanitize_textarea_field($_POST['autentique_document_message'] ?? ''));
    }

    // Email settings
    if ($tab === 'email') {
        update_option('formflow_email_from_name', sanitize_text_field($_POST['email_from_name'] ?? ''));
        update_option('formflow_email_from_address', sanitize_email($_POST['email_from_address'] ?? ''));
        update_option('formflow_email_admin_notification', isset($_POST['email_admin_notification']) ? 1 : 0);
        update_option('formflow_email_user_confirmation', isset($_POST['email_user_confirmation']) ? 1 : 0);
        update_option('formflow_email_admin_subject', sanitize_text_field($_POST['email_admin_subject'] ?? ''));
        update_option('formflow_email_user_subject', sanitize_text_field($_POST['email_user_subject'] ?? ''));
    }

    // Cache settings
    if ($tab === 'cache') {
        update_option('formflow_cache_enabled', isset($_POST['cache_enabled']) ? 1 : 0);
        update_option('formflow_cache_driver', sanitize_text_field($_POST['cache_driver'] ?? 'transient'));
        update_option('formflow_cache_ttl', intval($_POST['cache_ttl'] ?? 3600));

        // Clear cache if requested
        if (isset($_POST['clear_cache'])) {
            // Clear cache logic here
            do_action('formflow_clear_cache');
        }
    }

    // Queue settings
    if ($tab === 'queue') {
        update_option('formflow_queue_enabled', isset($_POST['queue_enabled']) ? 1 : 0);
        update_option('formflow_queue_batch_size', intval($_POST['queue_batch_size'] ?? 10));
        update_option('formflow_queue_retry_attempts', intval($_POST['queue_retry_attempts'] ?? 3));
        update_option('formflow_queue_retry_delay', intval($_POST['queue_retry_delay'] ?? 300));
    }

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current tab
$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'general';

// Get settings
$settings = [
    'general' => [
        'company_name' => get_option('formflow_company_name', ''),
        'company_email' => get_option('formflow_company_email', get_option('admin_email')),
        'submissions_per_page' => get_option('formflow_submissions_per_page', 25),
        'delete_data_on_uninstall' => get_option('formflow_delete_data_on_uninstall', 0),
    ],
    'autentique' => [
        'api_key' => get_option('formflow_autentique_api_key', ''),
        'sandbox_mode' => get_option('formflow_autentique_sandbox_mode', 1),
        'webhook_url' => get_option('formflow_autentique_webhook_url', rest_url('formflow/v1/autentique/webhook')),
        'auto_send' => get_option('formflow_autentique_auto_send', 0),
    ],
    'email' => [
        'from_name' => get_option('formflow_email_from_name', get_bloginfo('name')),
        'from_address' => get_option('formflow_email_from_address', get_option('admin_email')),
        'admin_notification' => get_option('formflow_email_admin_notification', 1),
        'user_confirmation' => get_option('formflow_email_user_confirmation', 1),
        'admin_subject' => get_option('formflow_email_admin_subject', __('New Form Submission', 'formflow-pro')),
        'user_subject' => get_option('formflow_email_user_subject', __('Form Submission Received', 'formflow-pro')),
    ],
    'cache' => [
        'enabled' => get_option('formflow_cache_enabled', 1),
        'driver' => get_option('formflow_cache_driver', 'transient'),
        'ttl' => get_option('formflow_cache_ttl', 3600),
    ],
    'queue' => [
        'enabled' => get_option('formflow_queue_enabled', 1),
        'batch_size' => get_option('formflow_queue_batch_size', 10),
        'retry_attempts' => get_option('formflow_queue_retry_attempts', 3),
        'retry_delay' => get_option('formflow_queue_retry_delay', 300),
    ],
];

?>

<div class="wrap formflow-admin formflow-settings">
    <h1 class="wp-heading-inline">
        <?php esc_html_e('Settings', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?page=formflow-settings&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php esc_html_e('General', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-settings&tab=autentique" class="nav-tab <?php echo $active_tab === 'autentique' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-edit-page"></span>
            <?php esc_html_e('Autentique', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-settings&tab=email" class="nav-tab <?php echo $active_tab === 'email' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-email"></span>
            <?php esc_html_e('Email', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-settings&tab=cache" class="nav-tab <?php echo $active_tab === 'cache' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-performance"></span>
            <?php esc_html_e('Cache', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-settings&tab=queue" class="nav-tab <?php echo $active_tab === 'queue' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-update"></span>
            <?php esc_html_e('Queue', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-settings&tab=system" class="nav-tab <?php echo $active_tab === 'system' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-info"></span>
            <?php esc_html_e('System Info', 'formflow-pro'); ?>
        </a>
    </nav>

    <form method="post" action="" class="settings-form">
        <?php wp_nonce_field('formflow_settings', 'formflow_settings_nonce'); ?>
        <input type="hidden" name="current_tab" value="<?php echo esc_attr($active_tab); ?>">

        <!-- General Tab -->
        <?php if ($active_tab === 'general') : ?>
            <div class="tab-content">
                <div class="settings-section card">
                    <h2><?php esc_html_e('General Settings', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="company_name"><?php esc_html_e('Company Name', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="company_name"
                                       name="company_name"
                                       value="<?php echo esc_attr($settings['general']['company_name']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Used in documents and email templates', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="company_email"><?php esc_html_e('Company Email', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="email"
                                       id="company_email"
                                       name="company_email"
                                       value="<?php echo esc_attr($settings['general']['company_email']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Default email for notifications', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="submissions_per_page"><?php esc_html_e('Submissions Per Page', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="submissions_per_page"
                                       name="submissions_per_page"
                                       value="<?php echo esc_attr($settings['general']['submissions_per_page']); ?>"
                                       min="10"
                                       max="100"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Number of submissions to display per page', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Data Management', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="delete_data_on_uninstall"
                                               value="1"
                                               <?php checked($settings['general']['delete_data_on_uninstall'], 1); ?>>
                                        <?php esc_html_e('Delete all data on plugin uninstall', 'formflow-pro'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Warning: This will permanently delete all forms, submissions, and settings', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Autentique Tab -->
        <?php if ($active_tab === 'autentique') : ?>
            <?php
            // Get Autentique statistics
            global $wpdb;
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}formflow_autentique_documents'");
            $autentique_stats = [
                'total' => 0,
                'pending' => 0,
                'signed' => 0,
                'refused' => 0,
            ];
            if ($table_exists) {
                $autentique_stats['total'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents"
                );
                $autentique_stats['pending'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'pending'"
                );
                $autentique_stats['signed'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'signed'"
                );
                $autentique_stats['refused'] = (int) $wpdb->get_var(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}formflow_autentique_documents WHERE status = 'refused'"
                );
            }
            $is_api_configured = !empty($settings['autentique']['api_key']);
            ?>
            <div class="tab-content">
                <!-- Connection Status Banner -->
                <div class="autentique-status-banner <?php echo $is_api_configured ? 'status-configured' : 'status-not-configured'; ?>" style="padding: 15px; margin-bottom: 20px; border-radius: 4px; display: flex; align-items: center; gap: 15px; <?php echo $is_api_configured ? 'background: #d4edda; border: 1px solid #c3e6cb;' : 'background: #fff3cd; border: 1px solid #ffeeba;'; ?>">
                    <span class="dashicons <?php echo $is_api_configured ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>" style="font-size: 24px; <?php echo $is_api_configured ? 'color: #28a745;' : 'color: #856404;'; ?>"></span>
                    <div>
                        <strong><?php echo $is_api_configured ? esc_html__('API Configured', 'formflow-pro') : esc_html__('API Not Configured', 'formflow-pro'); ?></strong>
                        <p style="margin: 5px 0 0 0; opacity: 0.8;">
                            <?php echo $is_api_configured
                                ? esc_html__('Your Autentique integration is ready to use.', 'formflow-pro')
                                : esc_html__('Enter your API key below to enable digital signatures.', 'formflow-pro'); ?>
                        </p>
                    </div>
                    <?php if ($is_api_configured) : ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-autentique')); ?>" class="button" style="margin-left: auto;">
                            <?php esc_html_e('View Documents', 'formflow-pro'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Quick Stats (if configured) -->
                <?php if ($is_api_configured && $autentique_stats['total'] > 0) : ?>
                <div class="autentique-quick-stats" style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div class="stat-box" style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #ddd; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #0073aa;"><?php echo esc_html($autentique_stats['total']); ?></div>
                        <div style="color: #666; font-size: 12px;"><?php esc_html_e('Total Documents', 'formflow-pro'); ?></div>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #ddd; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #f0ad4e;"><?php echo esc_html($autentique_stats['pending']); ?></div>
                        <div style="color: #666; font-size: 12px;"><?php esc_html_e('Pending', 'formflow-pro'); ?></div>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #ddd; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #28a745;"><?php echo esc_html($autentique_stats['signed']); ?></div>
                        <div style="color: #666; font-size: 12px;"><?php esc_html_e('Signed', 'formflow-pro'); ?></div>
                    </div>
                    <div class="stat-box" style="background: #fff; padding: 15px; border-radius: 4px; border: 1px solid #ddd; text-align: center;">
                        <div style="font-size: 24px; font-weight: bold; color: #dc3545;"><?php echo esc_html($autentique_stats['refused']); ?></div>
                        <div style="color: #666; font-size: 12px;"><?php esc_html_e('Refused', 'formflow-pro'); ?></div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- API Configuration -->
                <div class="settings-section card">
                    <h2><?php esc_html_e('API Configuration', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="autentique_api_key"><?php esc_html_e('API Key', 'formflow-pro'); ?> <span class="required" style="color: #dc3545;">*</span></label>
                            </th>
                            <td>
                                <div style="display: flex; gap: 10px; align-items: flex-start;">
                                    <input type="password"
                                           id="autentique_api_key"
                                           name="autentique_api_key"
                                           value="<?php echo esc_attr($settings['autentique']['api_key']); ?>"
                                           class="regular-text"
                                           placeholder="<?php esc_attr_e('Enter your Autentique API key', 'formflow-pro'); ?>"
                                           autocomplete="off">
                                    <button type="button" class="button" id="toggle-api-key-visibility" title="<?php esc_attr_e('Show/Hide API Key', 'formflow-pro'); ?>">
                                        <span class="dashicons dashicons-visibility"></span>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Get your API key from', 'formflow-pro'); ?>
                                    <a href="https://www.autentique.com.br/developers" target="_blank" rel="noopener noreferrer">
                                        Autentique Developers
                                        <span class="dashicons dashicons-external" style="font-size: 14px; text-decoration: none;"></span>
                                    </a>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Environment', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label style="display: flex; align-items: center; gap: 8px;">
                                        <input type="checkbox"
                                               name="autentique_sandbox_mode"
                                               value="1"
                                               <?php checked($settings['autentique']['sandbox_mode'], 1); ?>>
                                        <?php esc_html_e('Enable Sandbox Mode', 'formflow-pro'); ?>
                                        <span class="sandbox-badge" style="background: #ffc107; color: #000; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold;">
                                            <?php esc_html_e('TEST', 'formflow-pro'); ?>
                                        </span>
                                    </label>
                                    <p class="description"><?php esc_html_e('Use sandbox environment for testing. Documents created in sandbox mode are not legally binding. Disable for production use.', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>
                    </table>

                    <div class="api-test-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <h3 style="margin-top: 0;"><?php esc_html_e('Connection Test', 'formflow-pro'); ?></h3>
                        <p class="description" style="margin-bottom: 15px;"><?php esc_html_e('Verify that your API key is valid and the connection to Autentique is working.', 'formflow-pro'); ?></p>
                        <button type="button" class="button button-secondary" id="test-api-connection">
                            <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Test Connection', 'formflow-pro'); ?>
                        </button>
                        <div id="api-test-result" style="margin-top: 15px;"></div>
                    </div>
                </div>

                <!-- Webhook Configuration -->
                <div class="settings-section card" style="margin-top: 20px;">
                    <h2><?php esc_html_e('Webhook Configuration', 'formflow-pro'); ?></h2>
                    <p class="description"><?php esc_html_e('Configure webhooks to receive real-time notifications when documents are signed or refused.', 'formflow-pro'); ?></p>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="autentique_webhook_url"><?php esc_html_e('Webhook URL', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <div style="display: flex; gap: 10px; align-items: center;">
                                    <input type="text"
                                           id="autentique_webhook_url"
                                           name="autentique_webhook_url"
                                           value="<?php echo esc_attr($settings['autentique']['webhook_url']); ?>"
                                           class="regular-text"
                                           readonly
                                           style="background: #f5f5f5;">
                                    <button type="button" class="button copy-webhook-url">
                                        <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                        <?php esc_html_e('Copy', 'formflow-pro'); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php esc_html_e('Copy this URL and configure it in your', 'formflow-pro'); ?>
                                    <a href="https://app.autentique.com.br/configuracoes/webhooks" target="_blank" rel="noopener noreferrer">
                                        <?php esc_html_e('Autentique Webhook Settings', 'formflow-pro'); ?>
                                        <span class="dashicons dashicons-external" style="font-size: 14px;"></span>
                                    </a>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Document Settings -->
                <div class="settings-section card" style="margin-top: 20px;">
                    <h2><?php esc_html_e('Document Settings', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Auto Send Documents', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="autentique_auto_send"
                                               value="1"
                                               <?php checked($settings['autentique']['auto_send'], 1); ?>>
                                        <?php esc_html_e('Automatically send documents to signers after creation', 'formflow-pro'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('When enabled, signers will automatically receive an email with the signature link. If disabled, documents will be created but you will need to send them manually.', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Signature Reminders', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="autentique_reminder_enabled"
                                               value="1"
                                               <?php checked(get_option('formflow_autentique_reminder_enabled', 0), 1); ?>>
                                        <?php esc_html_e('Send automatic reminders for pending signatures', 'formflow-pro'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Automatically send reminder emails to signers who have not signed their documents.', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="autentique_document_message"><?php esc_html_e('Default Message', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="autentique_document_message"
                                          name="autentique_document_message"
                                          rows="3"
                                          class="large-text"
                                          placeholder="<?php esc_attr_e('Please review and sign this document.', 'formflow-pro'); ?>"><?php echo esc_textarea(get_option('formflow_autentique_document_message', '')); ?></textarea>
                                <p class="description"><?php esc_html_e('Default message included in signature request emails. Leave empty to use Autentique default.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Help Section -->
                <div class="settings-section card" style="margin-top: 20px; background: #f8f9fa;">
                    <h2 style="display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-editor-help"></span>
                        <?php esc_html_e('Getting Started', 'formflow-pro'); ?>
                    </h2>
                    <ol style="margin-left: 20px; line-height: 1.8;">
                        <li><?php esc_html_e('Create an account at', 'formflow-pro'); ?> <a href="https://www.autentique.com.br" target="_blank">autentique.com.br</a></li>
                        <li><?php esc_html_e('Go to Developer Settings and generate an API key', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Paste your API key in the field above', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Configure the webhook URL in your Autentique dashboard', 'formflow-pro'); ?></li>
                        <li><?php esc_html_e('Test the connection to verify everything is working', 'formflow-pro'); ?></li>
                    </ol>
                    <p>
                        <a href="https://docs.autentique.com.br" target="_blank" class="button">
                            <span class="dashicons dashicons-book" style="margin-top: 4px;"></span>
                            <?php esc_html_e('View Documentation', 'formflow-pro'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Email Tab -->
        <?php if ($active_tab === 'email') : ?>
            <div class="tab-content">
                <div class="settings-section card">
                    <h2><?php esc_html_e('Email Settings', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="email_from_name"><?php esc_html_e('From Name', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="email_from_name"
                                       name="email_from_name"
                                       value="<?php echo esc_attr($settings['email']['from_name']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="email_from_address"><?php esc_html_e('From Email', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="email"
                                       id="email_from_address"
                                       name="email_from_address"
                                       value="<?php echo esc_attr($settings['email']['from_address']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Notifications', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="email_admin_notification"
                                               value="1"
                                               <?php checked($settings['email']['admin_notification'], 1); ?>>
                                        <?php esc_html_e('Send admin notification on new submission', 'formflow-pro'); ?>
                                    </label>
                                    <br><br>
                                    <label>
                                        <input type="checkbox"
                                               name="email_user_confirmation"
                                               value="1"
                                               <?php checked($settings['email']['user_confirmation'], 1); ?>>
                                        <?php esc_html_e('Send user confirmation email', 'formflow-pro'); ?>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="email_admin_subject"><?php esc_html_e('Admin Email Subject', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="email_admin_subject"
                                       name="email_admin_subject"
                                       value="<?php echo esc_attr($settings['email']['admin_subject']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Available tags: {form_name}, {submission_id}, {date}', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="email_user_subject"><?php esc_html_e('User Email Subject', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="email_user_subject"
                                       name="email_user_subject"
                                       value="<?php echo esc_attr($settings['email']['user_subject']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Available tags: {form_name}, {submission_id}, {date}', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Cache Tab -->
        <?php if ($active_tab === 'cache') : ?>
            <div class="tab-content">
                <div class="settings-section card">
                    <h2><?php esc_html_e('Cache Configuration', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Enable Cache', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="cache_enabled"
                                               value="1"
                                               <?php checked($settings['cache']['enabled'], 1); ?>>
                                        <?php esc_html_e('Enable caching system', 'formflow-pro'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Recommended for production. Significantly improves performance.', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cache_driver"><?php esc_html_e('Cache Driver', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <select name="cache_driver" id="cache_driver">
                                    <option value="transient" <?php selected($settings['cache']['driver'], 'transient'); ?>>
                                        <?php esc_html_e('WordPress Transient (Default)', 'formflow-pro'); ?>
                                    </option>
                                    <option value="redis" <?php selected($settings['cache']['driver'], 'redis'); ?>>
                                        <?php esc_html_e('Redis', 'formflow-pro'); ?>
                                    </option>
                                    <option value="memcached" <?php selected($settings['cache']['driver'], 'memcached'); ?>>
                                        <?php esc_html_e('Memcached', 'formflow-pro'); ?>
                                    </option>
                                </select>
                                <p class="description"><?php esc_html_e('Select cache storage method', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="cache_ttl"><?php esc_html_e('Cache TTL (seconds)', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="cache_ttl"
                                       name="cache_ttl"
                                       value="<?php echo esc_attr($settings['cache']['ttl']); ?>"
                                       min="60"
                                       max="86400"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('How long to keep cached data (60-86400 seconds)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Clear Cache', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <button type="submit" name="clear_cache" class="button button-secondary">
                                    <span class="dashicons dashicons-trash"></span>
                                    <?php esc_html_e('Clear All Cache', 'formflow-pro'); ?>
                                </button>
                                <p class="description"><?php esc_html_e('Remove all cached data. Use if experiencing issues.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Queue Tab -->
        <?php if ($active_tab === 'queue') : ?>
            <div class="tab-content">
                <div class="settings-section card">
                    <h2><?php esc_html_e('Queue Configuration', 'formflow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <?php esc_html_e('Enable Queue', 'formflow-pro'); ?>
                            </th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="checkbox"
                                               name="queue_enabled"
                                               value="1"
                                               <?php checked($settings['queue']['enabled'], 1); ?>>
                                        <?php esc_html_e('Enable background queue processing', 'formflow-pro'); ?>
                                    </label>
                                    <p class="description"><?php esc_html_e('Process tasks in background for better performance', 'formflow-pro'); ?></p>
                                </fieldset>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="queue_batch_size"><?php esc_html_e('Batch Size', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="queue_batch_size"
                                       name="queue_batch_size"
                                       value="<?php echo esc_attr($settings['queue']['batch_size']); ?>"
                                       min="1"
                                       max="100"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Number of jobs to process per batch', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="queue_retry_attempts"><?php esc_html_e('Retry Attempts', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="queue_retry_attempts"
                                       name="queue_retry_attempts"
                                       value="<?php echo esc_attr($settings['queue']['retry_attempts']); ?>"
                                       min="0"
                                       max="10"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Number of times to retry failed jobs', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="queue_retry_delay"><?php esc_html_e('Retry Delay (seconds)', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="queue_retry_delay"
                                       name="queue_retry_delay"
                                       value="<?php echo esc_attr($settings['queue']['retry_delay']); ?>"
                                       min="60"
                                       max="3600"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Delay between retry attempts', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- System Info Tab -->
        <?php if ($active_tab === 'system') : ?>
            <div class="tab-content">
                <div class="settings-section card">
                    <h2><?php esc_html_e('System Information', 'formflow-pro'); ?></h2>

                    <table class="widefat system-info-table">
                        <tbody>
                            <tr>
                                <th><?php esc_html_e('Plugin Version', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(FORMFLOW_VERSION); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Database Version', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(get_option('formflow_db_version', 'Not installed')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('WordPress Version', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(get_bloginfo('version')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('PHP Version', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(PHP_VERSION); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('MySQL Version', 'formflow-pro'); ?></th>
                                <td><?php global $wpdb; echo esc_html($wpdb->db_version()); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Web Server', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html($_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('WordPress Memory Limit', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(WP_MEMORY_LIMIT); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('PHP Memory Limit', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(ini_get('memory_limit')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('PHP Max Upload Size', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(ini_get('upload_max_filesize')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('PHP Post Max Size', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(ini_get('post_max_size')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Active Theme', 'formflow-pro'); ?></th>
                                <td><?php echo esc_html(wp_get_theme()->get('Name')); ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Redis Available', 'formflow-pro'); ?></th>
                                <td><?php echo class_exists('Redis') ? '✓ Yes' : '✗ No'; ?></td>
                            </tr>
                            <tr>
                                <th><?php esc_html_e('Memcached Available', 'formflow-pro'); ?></th>
                                <td><?php echo class_exists('Memcached') ? '✓ Yes' : '✗ No'; ?></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="settings-section card" style="margin-top: 20px;">
                    <h2><?php esc_html_e('Database Tables', 'formflow-pro'); ?></h2>

                    <table class="widefat">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Table Name', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Rows', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Size', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            global $wpdb;
                            $tables = [
                                'formflow_forms',
                                'formflow_submissions',
                                'formflow_submission_meta',
                                'formflow_logs',
                                'formflow_queue',
                                'formflow_templates',
                                'formflow_analytics',
                                'formflow_webhooks',
                                'formflow_cache',
                                'formflow_settings'
                            ];

                            foreach ($tables as $table) {
                                $full_table = $wpdb->prefix . $table;
                                $count = $wpdb->get_var("SELECT COUNT(*) FROM $full_table");
                                $size = $wpdb->get_var("SELECT ROUND(((data_length + index_length) / 1024 / 1024), 2) FROM information_schema.TABLES WHERE table_schema = DATABASE() AND table_name = '$full_table'");
                                ?>
                                <tr>
                                    <td><code><?php echo esc_html($full_table); ?></code></td>
                                    <td><?php echo number_format($count); ?></td>
                                    <td><?php echo esc_html($size); ?> MB</td>
                                </tr>
                            <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Submit Button (except for system info tab) -->
        <?php if ($active_tab !== 'system') : ?>
            <p class="submit">
                <button type="submit" name="formflow_settings_submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-saved"></span>
                    <?php esc_html_e('Save Settings', 'formflow-pro'); ?>
                </button>
            </p>
        <?php endif; ?>
    </form>
</div>
