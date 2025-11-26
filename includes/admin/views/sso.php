<?php

/**
 * SSO Enterprise Settings Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Handle form submission
if (isset($_POST['formflow_sso_submit'])) {
    check_admin_referer('formflow_sso', 'formflow_sso_nonce');

    // General SSO settings
    update_option('formflow_sso_enabled', isset($_POST['sso_enabled']) ? 1 : 0);

    // SAML Settings
    update_option('formflow_sso_saml_enabled', isset($_POST['saml_enabled']) ? 1 : 0);
    update_option('formflow_sso_saml_idp_entity_id', sanitize_text_field($_POST['saml_idp_entity_id'] ?? ''));
    update_option('formflow_sso_saml_idp_sso_url', esc_url_raw($_POST['saml_idp_sso_url'] ?? ''));
    update_option('formflow_sso_saml_idp_slo_url', esc_url_raw($_POST['saml_idp_slo_url'] ?? ''));
    update_option('formflow_sso_saml_idp_certificate', sanitize_textarea_field($_POST['saml_idp_certificate'] ?? ''));

    // OAuth Settings
    update_option('formflow_sso_oauth_enabled', isset($_POST['oauth_enabled']) ? 1 : 0);
    update_option('formflow_sso_oauth_provider', sanitize_text_field($_POST['oauth_provider'] ?? 'google'));
    update_option('formflow_sso_oauth_client_id', sanitize_text_field($_POST['oauth_client_id'] ?? ''));
    update_option('formflow_sso_oauth_client_secret', sanitize_text_field($_POST['oauth_client_secret'] ?? ''));

    // LDAP Settings
    update_option('formflow_sso_ldap_enabled', isset($_POST['ldap_enabled']) ? 1 : 0);
    update_option('formflow_sso_ldap_host', sanitize_text_field($_POST['ldap_host'] ?? ''));
    update_option('formflow_sso_ldap_port', intval($_POST['ldap_port'] ?? 389));
    update_option('formflow_sso_ldap_base_dn', sanitize_text_field($_POST['ldap_base_dn'] ?? ''));
    update_option('formflow_sso_ldap_bind_dn', sanitize_text_field($_POST['ldap_bind_dn'] ?? ''));
    update_option('formflow_sso_ldap_bind_password', sanitize_text_field($_POST['ldap_bind_password'] ?? ''));

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('SSO settings saved successfully.', 'formflow-pro') . '</p></div>';
}

// Get current settings
$settings = [
    'enabled' => get_option('formflow_sso_enabled', 0),
    'saml' => [
        'enabled' => get_option('formflow_sso_saml_enabled', 0),
        'idp_entity_id' => get_option('formflow_sso_saml_idp_entity_id', ''),
        'idp_sso_url' => get_option('formflow_sso_saml_idp_sso_url', ''),
        'idp_slo_url' => get_option('formflow_sso_saml_idp_slo_url', ''),
        'idp_certificate' => get_option('formflow_sso_saml_idp_certificate', ''),
    ],
    'oauth' => [
        'enabled' => get_option('formflow_sso_oauth_enabled', 0),
        'provider' => get_option('formflow_sso_oauth_provider', 'google'),
        'client_id' => get_option('formflow_sso_oauth_client_id', ''),
        'client_secret' => get_option('formflow_sso_oauth_client_secret', ''),
    ],
    'ldap' => [
        'enabled' => get_option('formflow_sso_ldap_enabled', 0),
        'host' => get_option('formflow_sso_ldap_host', ''),
        'port' => get_option('formflow_sso_ldap_port', 389),
        'base_dn' => get_option('formflow_sso_ldap_base_dn', ''),
        'bind_dn' => get_option('formflow_sso_ldap_bind_dn', ''),
        'bind_password' => get_option('formflow_sso_ldap_bind_password', ''),
    ],
];

// Service Provider metadata URLs
$sp_entity_id = home_url('/formflow/sso/metadata');
$sp_acs_url = home_url('/formflow/sso/acs');
$sp_slo_url = home_url('/formflow/sso/slo');
$oauth_callback_url = home_url('/formflow/sso/callback');

$active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';

?>

<div class="wrap formflow-admin formflow-sso">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-admin-network"></span>
        <?php esc_html_e('SSO Enterprise', 'formflow-pro'); ?>
        <span class="badge badge-enterprise" style="background: linear-gradient(135deg, #9b59b6, #8e44ad); color: #fff; font-size: 11px; padding: 3px 10px; border-radius: 3px; margin-left: 10px; vertical-align: middle;">
            <?php esc_html_e('Enterprise', 'formflow-pro'); ?>
        </span>
    </h1>

    <hr class="wp-header-end">

    <!-- Tabs Navigation -->
    <nav class="nav-tab-wrapper wp-clearfix" style="margin-top: 20px;">
        <a href="?page=formflow-sso&tab=overview" class="nav-tab <?php echo $active_tab === 'overview' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-dashboard"></span>
            <?php esc_html_e('Overview', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-sso&tab=saml" class="nav-tab <?php echo $active_tab === 'saml' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-shield"></span>
            <?php esc_html_e('SAML 2.0', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-sso&tab=oauth" class="nav-tab <?php echo $active_tab === 'oauth' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-admin-users"></span>
            <?php esc_html_e('OAuth / Social', 'formflow-pro'); ?>
        </a>
        <a href="?page=formflow-sso&tab=ldap" class="nav-tab <?php echo $active_tab === 'ldap' ? 'nav-tab-active' : ''; ?>">
            <span class="dashicons dashicons-networking"></span>
            <?php esc_html_e('LDAP / Active Directory', 'formflow-pro'); ?>
        </a>
    </nav>

    <form method="post" action="">
        <?php wp_nonce_field('formflow_sso', 'formflow_sso_nonce'); ?>

        <!-- Overview Tab -->
        <?php if ($active_tab === 'overview') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <!-- SSO Status -->
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                        <span class="dashicons dashicons-admin-network" style="color: #0073aa;"></span>
                        <?php esc_html_e('Single Sign-On Status', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable SSO', 'formflow-pro'); ?></th>
                            <td>
                                <label class="toggle-switch">
                                    <input type="checkbox"
                                           name="sso_enabled"
                                           value="1"
                                           <?php checked($settings['enabled'], 1); ?>>
                                    <span class="toggle-slider round"></span>
                                </label>
                                <p class="description"><?php esc_html_e('Enable Single Sign-On for form authentication.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Provider Status Cards -->
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 20px;">
                    <!-- SAML Status -->
                    <div class="card" style="padding: 20px; <?php echo $settings['saml']['enabled'] ? 'border-left: 4px solid #46b450;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-shield" style="color: <?php echo $settings['saml']['enabled'] ? '#46b450' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('SAML 2.0', 'formflow-pro'); ?>
                        </h3>
                        <p style="color: #666; margin-bottom: 15px;"><?php esc_html_e('Enterprise identity provider integration', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['saml']['enabled'] ? '#46b45022' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['saml']['enabled'] ? '#46b450' : '#666'; ?>;">
                                <span class="dashicons dashicons-<?php echo $settings['saml']['enabled'] ? 'yes' : 'no'; ?>" style="font-size: 14px;"></span>
                                <?php echo $settings['saml']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-sso&tab=saml" class="button" style="margin-top: 10px;">
                            <?php esc_html_e('Configure', 'formflow-pro'); ?>
                        </a>
                    </div>

                    <!-- OAuth Status -->
                    <div class="card" style="padding: 20px; <?php echo $settings['oauth']['enabled'] ? 'border-left: 4px solid #0073aa;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-admin-users" style="color: <?php echo $settings['oauth']['enabled'] ? '#0073aa' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('OAuth / Social', 'formflow-pro'); ?>
                        </h3>
                        <p style="color: #666; margin-bottom: 15px;"><?php esc_html_e('Google, Microsoft, GitHub login', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['oauth']['enabled'] ? '#0073aa22' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['oauth']['enabled'] ? '#0073aa' : '#666'; ?>;">
                                <span class="dashicons dashicons-<?php echo $settings['oauth']['enabled'] ? 'yes' : 'no'; ?>" style="font-size: 14px;"></span>
                                <?php echo $settings['oauth']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-sso&tab=oauth" class="button" style="margin-top: 10px;">
                            <?php esc_html_e('Configure', 'formflow-pro'); ?>
                        </a>
                    </div>

                    <!-- LDAP Status -->
                    <div class="card" style="padding: 20px; <?php echo $settings['ldap']['enabled'] ? 'border-left: 4px solid #9b59b6;' : 'border-left: 4px solid #ccc;'; ?>">
                        <h3 style="margin-top: 0; display: flex; align-items: center; gap: 10px;">
                            <span class="dashicons dashicons-networking" style="color: <?php echo $settings['ldap']['enabled'] ? '#9b59b6' : '#ccc'; ?>;"></span>
                            <?php esc_html_e('LDAP / AD', 'formflow-pro'); ?>
                        </h3>
                        <p style="color: #666; margin-bottom: 15px;"><?php esc_html_e('Active Directory integration', 'formflow-pro'); ?></p>
                        <p>
                            <span style="display: inline-flex; align-items: center; gap: 5px; padding: 3px 8px; background: <?php echo $settings['ldap']['enabled'] ? '#9b59b622' : '#ccc22'; ?>; border-radius: 3px; color: <?php echo $settings['ldap']['enabled'] ? '#9b59b6' : '#666'; ?>;">
                                <span class="dashicons dashicons-<?php echo $settings['ldap']['enabled'] ? 'yes' : 'no'; ?>" style="font-size: 14px;"></span>
                                <?php echo $settings['ldap']['enabled'] ? esc_html__('Active', 'formflow-pro') : esc_html__('Inactive', 'formflow-pro'); ?>
                            </span>
                        </p>
                        <a href="?page=formflow-sso&tab=ldap" class="button" style="margin-top: 10px;">
                            <?php esc_html_e('Configure', 'formflow-pro'); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- SAML Tab -->
        <?php if ($active_tab === 'saml') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-shield" style="color: #0073aa;"></span>
                        <?php esc_html_e('SAML 2.0 Configuration', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable SAML', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="saml_enabled"
                                           value="1"
                                           <?php checked($settings['saml']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable SAML 2.0 authentication', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="saml_idp_entity_id"><?php esc_html_e('IdP Entity ID', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="saml_idp_entity_id"
                                       name="saml_idp_entity_id"
                                       value="<?php echo esc_attr($settings['saml']['idp_entity_id']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Identity Provider Entity ID (Issuer)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="saml_idp_sso_url"><?php esc_html_e('IdP SSO URL', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       id="saml_idp_sso_url"
                                       name="saml_idp_sso_url"
                                       value="<?php echo esc_attr($settings['saml']['idp_sso_url']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Single Sign-On Service URL', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="saml_idp_slo_url"><?php esc_html_e('IdP SLO URL', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="url"
                                       id="saml_idp_slo_url"
                                       name="saml_idp_slo_url"
                                       value="<?php echo esc_attr($settings['saml']['idp_slo_url']); ?>"
                                       class="regular-text">
                                <p class="description"><?php esc_html_e('Single Logout Service URL (optional)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="saml_idp_certificate"><?php esc_html_e('IdP Certificate', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <textarea id="saml_idp_certificate"
                                          name="saml_idp_certificate"
                                          rows="8"
                                          class="large-text code"
                                          placeholder="-----BEGIN CERTIFICATE-----
...
-----END CERTIFICATE-----"><?php echo esc_textarea($settings['saml']['idp_certificate']); ?></textarea>
                                <p class="description"><?php esc_html_e('X.509 certificate from your Identity Provider', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>

                <!-- Service Provider Metadata -->
                <div class="card" style="padding: 20px; margin-top: 20px; background: #f8f9fa;">
                    <h3 style="margin-top: 0;">
                        <span class="dashicons dashicons-info" style="color: #0073aa;"></span>
                        <?php esc_html_e('Service Provider Metadata', 'formflow-pro'); ?>
                    </h3>
                    <p class="description"><?php esc_html_e('Use these values to configure your Identity Provider:', 'formflow-pro'); ?></p>

                    <table class="form-table" style="margin-top: 15px;">
                        <tr>
                            <th><?php esc_html_e('SP Entity ID', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #fff;"><?php echo esc_html($sp_entity_id); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($sp_entity_id); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('ACS URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #fff;"><?php echo esc_html($sp_acs_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($sp_acs_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('SLO URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #fff;"><?php echo esc_html($sp_slo_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($sp_slo_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                            </td>
                        </tr>
                    </table>

                    <p style="margin-top: 15px;">
                        <a href="<?php echo esc_url($sp_entity_id); ?>" class="button" target="_blank">
                            <span class="dashicons dashicons-download" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Download Metadata XML', 'formflow-pro'); ?>
                        </a>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- OAuth Tab -->
        <?php if ($active_tab === 'oauth') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-admin-users" style="color: #0073aa;"></span>
                        <?php esc_html_e('OAuth / Social Login Configuration', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable OAuth', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="oauth_enabled"
                                           value="1"
                                           <?php checked($settings['oauth']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable OAuth / Social login', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="oauth_provider"><?php esc_html_e('OAuth Provider', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <select id="oauth_provider" name="oauth_provider" class="regular-text">
                                    <option value="google" <?php selected($settings['oauth']['provider'], 'google'); ?>>
                                        <?php esc_html_e('Google', 'formflow-pro'); ?>
                                    </option>
                                    <option value="microsoft" <?php selected($settings['oauth']['provider'], 'microsoft'); ?>>
                                        <?php esc_html_e('Microsoft / Azure AD', 'formflow-pro'); ?>
                                    </option>
                                    <option value="github" <?php selected($settings['oauth']['provider'], 'github'); ?>>
                                        <?php esc_html_e('GitHub', 'formflow-pro'); ?>
                                    </option>
                                    <option value="facebook" <?php selected($settings['oauth']['provider'], 'facebook'); ?>>
                                        <?php esc_html_e('Facebook', 'formflow-pro'); ?>
                                    </option>
                                    <option value="linkedin" <?php selected($settings['oauth']['provider'], 'linkedin'); ?>>
                                        <?php esc_html_e('LinkedIn', 'formflow-pro'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="oauth_client_id"><?php esc_html_e('Client ID', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="oauth_client_id"
                                       name="oauth_client_id"
                                       value="<?php echo esc_attr($settings['oauth']['client_id']); ?>"
                                       class="regular-text">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="oauth_client_secret"><?php esc_html_e('Client Secret', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="oauth_client_secret"
                                       name="oauth_client_secret"
                                       value="<?php echo esc_attr($settings['oauth']['client_secret']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row"><?php esc_html_e('Callback URL', 'formflow-pro'); ?></th>
                            <td>
                                <code style="padding: 5px 10px; background: #f5f5f5;"><?php echo esc_html($oauth_callback_url); ?></code>
                                <button type="button" class="button button-small copy-url" data-url="<?php echo esc_attr($oauth_callback_url); ?>">
                                    <span class="dashicons dashicons-clipboard" style="margin-top: 4px;"></span>
                                </button>
                                <p class="description"><?php esc_html_e('Add this URL to your OAuth application settings.', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <!-- LDAP Tab -->
        <?php if ($active_tab === 'ldap') : ?>
            <div class="tab-content" style="margin-top: 20px;">
                <div class="card" style="padding: 20px;">
                    <h2 style="margin-top: 0;">
                        <span class="dashicons dashicons-networking" style="color: #9b59b6;"></span>
                        <?php esc_html_e('LDAP / Active Directory Configuration', 'formflow-pro'); ?>
                    </h2>

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Enable LDAP', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox"
                                           name="ldap_enabled"
                                           value="1"
                                           <?php checked($settings['ldap']['enabled'], 1); ?>>
                                    <?php esc_html_e('Enable LDAP / Active Directory authentication', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ldap_host"><?php esc_html_e('LDAP Host', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="ldap_host"
                                       name="ldap_host"
                                       value="<?php echo esc_attr($settings['ldap']['host']); ?>"
                                       class="regular-text"
                                       placeholder="ldap.example.com">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ldap_port"><?php esc_html_e('LDAP Port', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="number"
                                       id="ldap_port"
                                       name="ldap_port"
                                       value="<?php echo esc_attr($settings['ldap']['port']); ?>"
                                       class="small-text"
                                       min="1"
                                       max="65535">
                                <p class="description"><?php esc_html_e('Default: 389 (LDAP) or 636 (LDAPS)', 'formflow-pro'); ?></p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ldap_base_dn"><?php esc_html_e('Base DN', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="ldap_base_dn"
                                       name="ldap_base_dn"
                                       value="<?php echo esc_attr($settings['ldap']['base_dn']); ?>"
                                       class="regular-text"
                                       placeholder="dc=example,dc=com">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ldap_bind_dn"><?php esc_html_e('Bind DN', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="text"
                                       id="ldap_bind_dn"
                                       name="ldap_bind_dn"
                                       value="<?php echo esc_attr($settings['ldap']['bind_dn']); ?>"
                                       class="regular-text"
                                       placeholder="cn=admin,dc=example,dc=com">
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="ldap_bind_password"><?php esc_html_e('Bind Password', 'formflow-pro'); ?></label>
                            </th>
                            <td>
                                <input type="password"
                                       id="ldap_bind_password"
                                       name="ldap_bind_password"
                                       value="<?php echo esc_attr($settings['ldap']['bind_password']); ?>"
                                       class="regular-text"
                                       autocomplete="new-password">
                            </td>
                        </tr>
                    </table>

                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <button type="button" id="test-ldap-connection" class="button">
                            <span class="dashicons dashicons-update" style="margin-top: 4px;"></span>
                            <?php esc_html_e('Test Connection', 'formflow-pro'); ?>
                        </button>
                        <span id="ldap-test-result" style="margin-left: 10px;"></span>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <p class="submit">
            <button type="submit" name="formflow_sso_submit" class="button button-primary button-large">
                <span class="dashicons dashicons-saved" style="margin-top: 4px;"></span>
                <?php esc_html_e('Save SSO Settings', 'formflow-pro'); ?>
            </button>
        </p>
    </form>
</div>

<style>
.toggle-switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.toggle-switch input {
    opacity: 0;
    width: 0;
    height: 0;
}
.toggle-slider {
    position: absolute;
    cursor: pointer;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-color: #ccc;
    transition: .3s;
}
.toggle-slider.round {
    border-radius: 26px;
}
.toggle-slider:before {
    position: absolute;
    content: "";
    height: 20px;
    width: 20px;
    left: 3px;
    bottom: 3px;
    background-color: white;
    transition: .3s;
    border-radius: 50%;
}
.toggle-switch input:checked + .toggle-slider {
    background-color: #0073aa;
}
.toggle-switch input:checked + .toggle-slider:before {
    transform: translateX(24px);
}
</style>

<script>
jQuery(document).ready(function($) {
    // Copy URL to clipboard
    $('.copy-url').on('click', function() {
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function() {
            alert('<?php esc_html_e('Copied to clipboard!', 'formflow-pro'); ?>');
        });
    });

    // Test LDAP connection
    $('#test-ldap-connection').on('click', function() {
        var $btn = $(this);
        var $result = $('#ldap-test-result');

        $btn.prop('disabled', true);
        $result.html('<span class="spinner is-active" style="float: none;"></span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'formflow_test_ldap_connection',
                nonce: '<?php echo wp_create_nonce('formflow_test_ldap'); ?>',
                host: $('#ldap_host').val(),
                port: $('#ldap_port').val(),
                base_dn: $('#ldap_base_dn').val(),
                bind_dn: $('#ldap_bind_dn').val(),
                bind_password: $('#ldap_bind_password').val()
            },
            success: function(response) {
                if (response.success) {
                    $result.html('<span style="color: #46b450;"><span class="dashicons dashicons-yes"></span> <?php esc_html_e('Connection successful!', 'formflow-pro'); ?></span>');
                } else {
                    $result.html('<span style="color: #dc3545;"><span class="dashicons dashicons-no"></span> ' + (response.data?.message || '<?php esc_html_e('Connection failed', 'formflow-pro'); ?>') + '</span>');
                }
            },
            error: function() {
                $result.html('<span style="color: #dc3545;"><?php esc_html_e('Connection test failed', 'formflow-pro'); ?></span>');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
});
</script>
