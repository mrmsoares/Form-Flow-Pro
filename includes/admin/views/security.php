<?php

/**
 * Security & 2FA Settings Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Handle form submission
if (isset($_POST['formflow_security_submit'])) {
    check_admin_referer('formflow_security', 'formflow_security_nonce');

    // General security settings
    update_option('formflow_security_enabled', isset($_POST['security_enabled']) ? 1 : 0);

    // 2FA Settings
    update_option('formflow_2fa_enabled', isset($_POST['2fa_enabled']) ? 1 : 0);
    update_option('formflow_2fa_method', sanitize_text_field($_POST['2fa_method'] ?? 'totp'));
    update_option('formflow_2fa_required_roles', isset($_POST['2fa_required_roles']) ? array_map('sanitize_text_field', $_POST['2fa_required_roles']) : []);

    // Audit Log Settings
    update_option('formflow_audit_enabled', isset($_POST['audit_enabled']) ? 1 : 0);
    update_option('formflow_audit_retention_days', intval($_POST['audit_retention_days'] ?? 90));

    // GDPR Settings
    update_option('formflow_gdpr_enabled', isset($_POST['gdpr_enabled']) ? 1 : 0);
    update_option('formflow_gdpr_consent_text', sanitize_textarea_field($_POST['gdpr_consent_text'] ?? ''));
    update_option('formflow_gdpr_data_retention_days', intval($_POST['gdpr_data_retention_days'] ?? 365));
    update_option('formflow_gdpr_anonymize_ip', isset($_POST['gdpr_anonymize_ip']) ? 1 : 0);

    // Access Control
    update_option('formflow_access_ip_whitelist', sanitize_textarea_field($_POST['access_ip_whitelist'] ?? ''));
    update_option('formflow_access_ip_blacklist', sanitize_textarea_field($_POST['access_ip_blacklist'] ?? ''));
    update_option('formflow_access_rate_limit', intval($_POST['access_rate_limit'] ?? 60));

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Security settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current settings
$settings = [
    'enabled' => get_option('formflow_security_enabled', 1),
    '2fa' => [
        'enabled' => get_option('formflow_2fa_enabled', 0),
        'method' => get_option('formflow_2fa_method', 'totp'),
        'required_roles' => get_option('formflow_2fa_required_roles', ['administrator']),
    ],
    'audit' => [
        'enabled' => get_option('formflow_audit_enabled', 1),
        'retention_days' => get_option('formflow_audit_retention_days', 90),
    ],
    'gdpr' => [
        'enabled' => get_option('formflow_gdpr_enabled', 0),
        'consent_text' => get_option('formflow_gdpr_consent_text', __('I agree to the processing of my personal data according to the Privacy Policy.', 'formflow-pro')),
        'data_retention_days' => get_option('formflow_gdpr_data_retention_days', 365),
        'anonymize_ip' => get_option('formflow_gdpr_anonymize_ip', 0),
    ],
    'access' => [
        'ip_whitelist' => get_option('formflow_access_ip_whitelist', ''),
        'ip_blacklist' => get_option('formflow_access_ip_blacklist', ''),
        'rate_limit' => get_option('formflow_access_rate_limit', 60),
    ],
];

// Get security statistics
$stats = [
    'blocked_attempts' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_security_logs WHERE action = 'blocked'"),
    'audit_entries' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_audit_logs"),
    '2fa_users' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = 'formflow_2fa_enabled' AND meta_value = '1'"),
    'gdpr_requests' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}formflow_gdpr_requests"),
];

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

// Get all WordPress roles
$wp_roles = wp_roles();
$all_roles = $wp_roles->get_names();

?>

<div class="wrap formflow-admin formflow-security">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-shield-alt"></span>
        <?php esc_html_e('Security & Compliance', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #e74c3c, #c0392b); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <hr class="wp-header-end">

    <!-- Security Score Card -->
    <div class="card" style="padding: 20px; margin: 20px 0; background: linear-gradient(135deg, #1e3a5f, #2c5282);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <div>
                <h2 style="margin: 0; color: #fff;">
                    <span class="dashicons dashicons-shield" style="font-size: 28px;"></span>
                    <?php esc_html_e('Security Score', 'formflow-pro'); ?>
                </h2>
                <p style="color: #a0aec0; margin: 5px 0 0 0;"><?php esc_html_e('Based on your current security configuration', 'formflow-pro'); ?></p>
            </div>
            <div style="text-align: center;">
                <?php
                $score = 50;
                if ($settings['enabled']) $score += 10;
                if ($settings['2fa']['enabled']) $score += 20;
                if ($settings['audit']['enabled']) $score += 10;
                if ($settings['gdpr']['enabled']) $score += 10;
                $score = min($score, 100);
                $score_color = $score >= 80 ? '#48bb78' : ($score >= 60 ? '#ecc94b' : '#fc8181');
                ?>
                <div style="font-size: 48px; font-weight: bold; color: <?php echo $score_color; ?>;"><?php echo $score; ?>%</div>
                <div style="color: #a0aec0; font-size: 12px;">
                    <?php
                    if ($score >= 80) {
                        esc_html_e('Excellent', 'formflow-pro');
                    } elseif ($score >= 60) {
                        esc_html_e('Good', 'formflow-pro');
                    } else {
                        esc_html_e('Needs Improvement', 'formflow-pro');
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Cards -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 20px;">
        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #e74c3c;">
                <span class="dashicons dashicons-dismiss"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['blocked_attempts']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Blocked Attempts', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #0073aa;">
                <span class="dashicons dashicons-list-view"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['audit_entries']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('Audit Entries', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #27ae60;">
                <span class="dashicons dashicons-lock"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['2fa_users']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('2FA Users', 'formflow-pro'); ?></div>
        </div>

        <div class="card" style="padding: 20px; text-align: center; margin: 0;">
            <div style="font-size: 32px; color: #9b59b6;">
                <span class="dashicons dashicons-privacy"></span>
            </div>
            <div style="font-size: 28px; font-weight: bold; color: #23282d;"><?php echo esc_html($stats['gdpr_requests']); ?></div>
            <div style="color: #666; font-size: 13px;"><?php esc_html_e('GDPR Requests', 'formflow-pro'); ?></div>
        </div>
    </div>

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix">
        <a href="?page=formflow-security&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Overview', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-security&tab=2fa" class="nav-tab <?php echo $active_tab === '2fa' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-lock"></span>
            <?php esc_html_e('Two-Factor Auth', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-security&tab=audit" class="nav-tab <?php echo $active_tab === 'audit' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-list-view"></span>
            <?php esc_html_e('Audit Log', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-security&tab=gdpr" class="nav-tab <?php echo $active_tab === 'gdpr' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-privacy"></span>
            <?php esc_html_e('GDPR / LGPD', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-security&tab=access" class="nav-tab <?php echo $active_tab === 'access' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-network"></span>
            <?php esc_html_e('Access Control', 'formflow-pro'); ?>
        </a>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('formflow_security', 'formflow_security_nonce'); ?>

        <!-- Overview Tab -->
        <?php if ($active_tab === 'overview') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-admin-settings" style="color: #0073aa;"></span>
                        <?php esc_html_e('General Security Settings', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Security Features', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="security_enabled"
                                           value="1"
                                           <?php checked($settings['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable all security features', 'formflow-pro'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Master switch for all security features. Individual features can be configured in their respective tabs.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Security Checklist -->
                <div class="card" style="padding: 20px; margin-top: 20px;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Security Checklist', 'formflow-pro'); ?></h3>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-<?php echo $settings['enabled'] ? 'yes-alt' : 'marker'; ?>" style="color: <?php echo $settings['enabled'] ? '#46b450' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('Security features enabled', 'formflow-pro'); ?>
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-<?php echo $settings['2fa']['enabled'] ? 'yes-alt' : 'marker'; ?>" style="color: <?php echo $settings['2fa']['enabled'] ? '#46b450' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('Two-factor authentication configured', 'formflow-pro'); ?>
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-<?php echo $settings['audit']['enabled'] ? 'yes-alt' : 'marker'; ?>" style="color: <?php echo $settings['audit']['enabled'] ? '#46b450' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('Audit logging enabled', 'formflow-pro'); ?>
                        </li>
                        <li style="padding: 10px 0; border-bottom: 1px solid #eee; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-<?php echo $settings['gdpr']['enabled'] ? 'yes-alt' : 'marker'; ?>" style="color: <?php echo $settings['gdpr']['enabled'] ? '#46b450' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('GDPR/LGPD compliance configured', 'formflow-pro'); ?>
                        </li>
                        <li style="padding: 10px 0; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-<?php echo is_ssl() ? 'yes-alt' : 'warning'; ?>" style="color: <?php echo is_ssl() ? '#46b450' : '#f0ad4e'; ?>;"></span>
                            <?php esc_html_e('SSL/HTTPS enabled', 'formflow-pro'); ?>
                            <?php if (!is_ssl()) : ?>
                                <span style="color: #f0ad4e; font-size: 12px;">(<?php esc_html_e('Recommended', 'formflow-pro'); ?>)</span>
                            <?php endif; ?>
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2FA Tab -->
        <?php if ($active_tab === '2fa') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-lock" style="color: #27ae60;"></span>
                        <?php esc_html_e('Two-Factor Authentication', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable 2FA', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="2fa_enabled"
                                           value="1"
                                           <?php checked($settings['2fa']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable two-factor authentication', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="2fa_method"><?php esc_html_e('2FA Method', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <select id="2fa_method" name="2fa_method" class="regular-text">
                                    <option value="totp" <?php selected($settings['2fa']['method'], 'totp'); ?>>
                                        <?php esc_html_e('TOTP (Google Authenticator, Authy)', 'formflow-pro'); ?>
                                    </option>
                                    <option value="email" <?php selected($settings['2fa']['method'], 'email'); ?>>
                                        <?php esc_html_e('Email Code', 'formflow-pro'); ?>
                                    </option>
                                    <option value="sms" <?php selected($settings['2fa']['method'], 'sms'); ?>>
                                        <?php esc_html_e('SMS Code', 'formflow-pro'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Required for Roles', 'formflow-pro'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($all_roles as $role_key => $role_name) : ?>
                                        <label style="display: block; margin-bottom: 5px;">
                                            <input type="checkbox"
                                                   name="2fa_required_roles[]"
                                                   value="<?php echo esc_attr($role_key); ?>"
                                                   <?php checked(in_array($role_key, $settings['2fa']['required_roles'])); ?>>
                                            <?php echo esc_html($role_name); ?>
                                        </label>
                                    <?php endforeach; ?>
                                </fieldset>
                                <p class="description"><?php esc_html_e('2FA will be required for users with these roles.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- Audit Log Tab -->
        <?php if ($active_tab === 'audit') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-list-view" style="color: #0073aa;"></span>
                        <?php esc_html_e('Audit Log Settings', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable Audit Log', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="audit_enabled"
                                           value="1"
                                           <?php checked($settings['audit']['enabled'], 1); ?>>
                                    <?php esc_html_e('Log all form and system activities', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="audit_retention_days"><?php esc_html_e('Retention Period (days)', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="audit_retention_days"
                                       name="audit_retention_days"
                                       value="<?php echo esc_attr($settings['audit']['retention_days']); ?>"
                                       min="7"
                                       max="365"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Audit logs older than this will be automatically deleted.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Recent Audit Entries -->
                <div class="card" style="padding: 0; margin-top: 20px;">
                    <h3 style="padding: 15px 20px; margin: 0; border-bottom: 1px solid #eee;"><?php esc_html_e('Recent Activity', 'formflow-pro'); ?></h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php esc_html_e('Date', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('User', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Action', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('Details', 'formflow-pro'); ?></th>
                                <th><?php esc_html_e('IP Address', 'formflow-pro'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $audit_logs = $wpdb->get_results(
                                "SELECT * FROM {$wpdb->prefix}formflow_audit_logs ORDER BY created_at DESC LIMIT 20"
                            );

                            if (empty($audit_logs)) :
                            ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; padding: 20px; color: #666;">
                                        <?php esc_html_e('No audit entries yet.', 'formflow-pro'); ?>
                                    </td>
                                </tr>
                            <?php else : ?>
                                <?php foreach ($audit_logs as $log) : ?>
                                    <tr>
                                        <td><?php echo esc_html(wp_date(get_option('date_format') . ' ' . get_option('time_format'), strtotime($log->created_at))); ?></td>
                                        <td>
                                            <?php
                                            $user = get_user_by('id', $log->user_id);
                                            echo $user ? esc_html($user->display_name) : esc_html__('System', 'formflow-pro');
                                            ?>
                                        </td>
                                        <td><code><?php echo esc_html($log->action); ?></code></td>
                                        <td><?php echo esc_html(wp_trim_words($log->details ?? '', 10)); ?></td>
                                        <td><code><?php echo esc_html($log->ip_address ?? '-'); ?></code></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- GDPR Tab -->
        <?php if ($active_tab === 'gdpr') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-privacy" style="color: #9b59b6;"></span>
                        <?php esc_html_e('GDPR / LGPD Compliance', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable GDPR Features', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="gdpr_enabled"
                                           value="1"
                                           <?php checked($settings['gdpr']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable GDPR/LGPD compliance features', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="gdpr_consent_text"><?php esc_html_e('Consent Text', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="gdpr_consent_text"
                                          name="gdpr_consent_text"
                                          rows="3"
                                          class="large-text"><?php echo esc_textarea($settings['gdpr']['consent_text']); ?></textarea>
                                <p class="description"><?php esc_html_e('Text displayed next to the consent checkbox on forms.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="gdpr_data_retention_days"><?php esc_html_e('Data Retention (days)', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="gdpr_data_retention_days"
                                       name="gdpr_data_retention_days"
                                       value="<?php echo esc_attr($settings['gdpr']['data_retention_days']); ?>"
                                       min="30"
                                       max="3650"
                                       class="small-text">
                                <p class="description"><?php esc_html_e('Personal data older than this will be automatically anonymized/deleted.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('IP Anonymization', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="gdpr_anonymize_ip"
                                           value="1"
                                           <?php checked($settings['gdpr']['anonymize_ip'], 1); ?>>
                                    <?php esc_html_e('Anonymize IP addresses in logs', 'formflow-pro'); ?>
                                </label>
                                <p class="description"><?php esc_html_e('Last octet of IPv4 and last 80 bits of IPv6 will be masked.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="card" style="padding: 20px; margin-top: 20px; background: #f8f9fa;">
                    <h3 style="margin-top: 0;"><?php esc_html_e('Data Subject Rights', 'formflow-pro'); ?></h3>
                    <p style="color: #666;"><?php esc_html_e('Users can exercise their rights through these endpoints:', 'formflow-pro'); ?></p>
                    <ul style="margin-left: 20px;">
                        <li><strong><?php esc_html_e('Data Export:', 'formflow-pro'); ?></strong> <code><?php echo esc_html(home_url('/formflow/gdpr/export')); ?></code></li>
                        <li><strong><?php esc_html_e('Data Deletion:', 'formflow-pro'); ?></strong> <code><?php echo esc_html(home_url('/formflow/gdpr/delete')); ?></code></li>
                        <li><strong><?php esc_html_e('Consent Withdrawal:', 'formflow-pro'); ?></strong> <code><?php echo esc_html(home_url('/formflow/gdpr/consent')); ?></code></li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>

        <!-- Access Control Tab -->
        <?php if ($active_tab === 'access') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-admin-network" style="color: #e74c3c;"></span>
                        <?php esc_html_e('Access Control', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="access_ip_whitelist"><?php esc_html_e('IP Whitelist', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="access_ip_whitelist"
                                          name="access_ip_whitelist"
                                          rows="4"
                                          class="large-text code"
                                          placeholder="192.168.1.1&#10;10.0.0.0/8"><?php echo esc_textarea($settings['access']['ip_whitelist']); ?></textarea>
                                <p class="description"><?php esc_html_e('One IP or CIDR range per line. If set, only these IPs can access admin forms.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="access_ip_blacklist"><?php esc_html_e('IP Blacklist', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="access_ip_blacklist"
                                          name="access_ip_blacklist"
                                          rows="4"
                                          class="large-text code"
                                          placeholder="192.168.1.100&#10;10.0.0.0/8"><?php echo esc_textarea($settings['access']['ip_blacklist']); ?></textarea>
                                <p class="description"><?php esc_html_e('One IP or CIDR range per line. These IPs will be blocked from submitting forms.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="access_rate_limit"><?php esc_html_e('Rate Limit', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="access_rate_limit"
                                       name="access_rate_limit"
                                       value="<?php echo esc_attr($settings['access']['rate_limit']); ?>"
                                       min="1"
                                       max="1000"
                                       class="small-text">
                                <span><?php esc_html_e('requests per minute per IP', 'formflow-pro'); ?></span>
                                <p class="description"><?php esc_html_e('Limit form submissions to prevent abuse.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" name="formflow_security_submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                <?php esc_html_e('Save Security Settings', 'formflow-pro'); ?>
            </button>
        </p>
    </form>
</div>
