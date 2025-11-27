<?php
/**
 * SSO Manager - Central Enterprise Single Sign-On Management
 *
 * Integrates SAML 2.0, LDAP/AD, and OAuth2/OIDC providers
 * for unified enterprise authentication.
 *
 * @package FormFlowPro
 * @subpackage SSO
 * @since 3.0.0
 */

namespace FormFlowPro\SSO;

use FormFlowPro\Core\SingletonTrait;

/**
 * SSO Session model
 */
class SSOSession
{
    public int $id;
    public int $user_id;
    public string $provider_type;
    public string $provider_id;
    public string $session_id;
    public string $external_id;
    public array $attributes;
    public string $access_token;
    public string $refresh_token;
    public int $token_expires;
    public string $ip_address;
    public string $user_agent;
    public string $created_at;
    public string $expires_at;
    public string $last_activity;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->user_id = $data['user_id'] ?? 0;
        $this->provider_type = $data['provider_type'] ?? '';
        $this->provider_id = $data['provider_id'] ?? '';
        $this->session_id = $data['session_id'] ?? '';
        $this->external_id = $data['external_id'] ?? '';
        $this->attributes = $data['attributes'] ?? [];
        $this->access_token = $data['access_token'] ?? '';
        $this->refresh_token = $data['refresh_token'] ?? '';
        $this->token_expires = $data['token_expires'] ?? 0;
        $this->ip_address = $data['ip_address'] ?? '';
        $this->user_agent = $data['user_agent'] ?? '';
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->expires_at = $data['expires_at'] ?? '';
        $this->last_activity = $data['last_activity'] ?? current_time('mysql');
    }

    public function isExpired(): bool
    {
        if (empty($this->expires_at)) {
            return false;
        }
        return strtotime($this->expires_at) < time();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'provider_type' => $this->provider_type,
            'provider_id' => $this->provider_id,
            'session_id' => $this->session_id,
            'external_id' => $this->external_id,
            'attributes' => $this->attributes,
            'access_token' => $this->access_token,
            'refresh_token' => $this->refresh_token,
            'token_expires' => $this->token_expires,
            'ip_address' => $this->ip_address,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at,
            'expires_at' => $this->expires_at,
            'last_activity' => $this->last_activity
        ];
    }
}

/**
 * SSO Identity Link model
 */
class SSOIdentityLink
{
    public int $id;
    public int $user_id;
    public string $provider_type;
    public string $provider_id;
    public string $external_id;
    public string $email;
    public array $profile_data;
    public bool $is_primary;
    public string $linked_at;
    public string $last_login;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? 0;
        $this->user_id = $data['user_id'] ?? 0;
        $this->provider_type = $data['provider_type'] ?? '';
        $this->provider_id = $data['provider_id'] ?? '';
        $this->external_id = $data['external_id'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->profile_data = $data['profile_data'] ?? [];
        $this->is_primary = $data['is_primary'] ?? false;
        $this->linked_at = $data['linked_at'] ?? current_time('mysql');
        $this->last_login = $data['last_login'] ?? '';
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'provider_type' => $this->provider_type,
            'provider_id' => $this->provider_id,
            'external_id' => $this->external_id,
            'email' => $this->email,
            'profile_data' => $this->profile_data,
            'is_primary' => $this->is_primary,
            'linked_at' => $this->linked_at,
            'last_login' => $this->last_login
        ];
    }
}

/**
 * SSO Settings model
 */
class SSOSettings
{
    public bool $enabled;
    public bool $force_sso;
    public bool $allow_local_login;
    public bool $auto_provision_users;
    public string $default_role;
    public array $role_mapping;
    public bool $sync_profile_on_login;
    public bool $sync_groups_on_login;
    public int $session_lifetime;
    public bool $single_logout_enabled;
    public array $allowed_domains;
    public array $blocked_domains;
    public string $login_button_text;
    public string $login_button_position;
    public bool $hide_local_login;
    public string $redirect_after_login;
    public string $redirect_after_logout;
    public array $enabled_providers;

    public function __construct(array $data = [])
    {
        $this->enabled = $data['enabled'] ?? false;
        $this->force_sso = $data['force_sso'] ?? false;
        $this->allow_local_login = $data['allow_local_login'] ?? true;
        $this->auto_provision_users = $data['auto_provision_users'] ?? true;
        $this->default_role = $data['default_role'] ?? 'subscriber';
        $this->role_mapping = $data['role_mapping'] ?? [];
        $this->sync_profile_on_login = $data['sync_profile_on_login'] ?? true;
        $this->sync_groups_on_login = $data['sync_groups_on_login'] ?? true;
        $this->session_lifetime = $data['session_lifetime'] ?? 28800; // 8 hours
        $this->single_logout_enabled = $data['single_logout_enabled'] ?? true;
        $this->allowed_domains = $data['allowed_domains'] ?? [];
        $this->blocked_domains = $data['blocked_domains'] ?? [];
        $this->login_button_text = $data['login_button_text'] ?? __('Sign in with SSO', 'formflow-pro');
        $this->login_button_position = $data['login_button_position'] ?? 'above';
        $this->hide_local_login = $data['hide_local_login'] ?? false;
        $this->redirect_after_login = $data['redirect_after_login'] ?? admin_url();
        $this->redirect_after_logout = $data['redirect_after_logout'] ?? home_url();
        $this->enabled_providers = $data['enabled_providers'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'force_sso' => $this->force_sso,
            'allow_local_login' => $this->allow_local_login,
            'auto_provision_users' => $this->auto_provision_users,
            'default_role' => $this->default_role,
            'role_mapping' => $this->role_mapping,
            'sync_profile_on_login' => $this->sync_profile_on_login,
            'sync_groups_on_login' => $this->sync_groups_on_login,
            'session_lifetime' => $this->session_lifetime,
            'single_logout_enabled' => $this->single_logout_enabled,
            'allowed_domains' => $this->allowed_domains,
            'blocked_domains' => $this->blocked_domains,
            'login_button_text' => $this->login_button_text,
            'login_button_position' => $this->login_button_position,
            'hide_local_login' => $this->hide_local_login,
            'redirect_after_login' => $this->redirect_after_login,
            'redirect_after_logout' => $this->redirect_after_logout,
            'enabled_providers' => $this->enabled_providers
        ];
    }
}

/**
 * SSO Manager - Central SSO Controller
 */
class SSOManager
{
    use SingletonTrait;

    private const OPTION_SETTINGS = 'formflow_sso_settings';
    private const OPTION_PROVIDERS = 'formflow_sso_providers';
    private const SESSION_COOKIE = 'formflow_sso_session';
    private const NONCE_ACTION = 'formflow_sso_nonce';

    private ?SAMLProvider $saml_provider = null;
    private ?LDAPProvider $ldap_provider = null;
    private ?OAuth2EnterpriseProvider $oauth2_provider = null;
    private ?SSOSettings $settings = null;
    private array $providers_config = [];

    /**
     * Initialize SSO Manager
     */
    public function init(): void
    {
        $this->loadSettings();
        $this->initProviders();
        $this->registerHooks();
        $this->registerAdminPages();
        $this->registerRestRoutes();
        $this->registerRewriteRules();
    }

    /**
     * Load SSO settings
     */
    private function loadSettings(): void
    {
        $data = get_option(self::OPTION_SETTINGS, []);
        $this->settings = new SSOSettings($data);
        $this->providers_config = get_option(self::OPTION_PROVIDERS, []);
    }

    /**
     * Initialize SSO providers
     */
    private function initProviders(): void
    {
        // Initialize SAML Provider
        if (class_exists(SAMLProvider::class)) {
            $this->saml_provider = SAMLProvider::getInstance();
            if (isset($this->providers_config['saml'])) {
                $this->saml_provider->configure($this->providers_config['saml']);
            }
        }

        // Initialize LDAP Provider
        if (class_exists(LDAPProvider::class)) {
            $this->ldap_provider = LDAPProvider::getInstance();
            if (isset($this->providers_config['ldap'])) {
                $this->ldap_provider->configure($this->providers_config['ldap']);
            }
        }

        // Initialize OAuth2 Provider
        if (class_exists(OAuth2EnterpriseProvider::class)) {
            $this->oauth2_provider = OAuth2EnterpriseProvider::getInstance();
            if (isset($this->providers_config['oauth2'])) {
                foreach ($this->providers_config['oauth2'] as $provider_config) {
                    $this->oauth2_provider->registerProvider($provider_config);
                }
            }
        }
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Login page modifications
        add_action('login_form', [$this, 'renderLoginButtons']);
        add_action('login_enqueue_scripts', [$this, 'enqueueLoginStyles']);
        add_filter('login_message', [$this, 'filterLoginMessage']);

        // Authentication hooks
        add_filter('authenticate', [$this, 'handleSSOAuthentication'], 5, 3);
        add_action('wp_logout', [$this, 'handleLogout']);
        add_action('clear_auth_cookie', [$this, 'clearSSOSession']);

        // Session management
        add_action('init', [$this, 'validateSSOSession']);
        add_action('wp_login', [$this, 'onUserLogin'], 10, 2);

        // User profile
        add_action('show_user_profile', [$this, 'renderUserSSOSection']);
        add_action('edit_user_profile', [$this, 'renderUserSSOSection']);

        // AJAX handlers
        add_action('wp_ajax_ffp_sso_test_connection', [$this, 'ajaxTestConnection']);
        add_action('wp_ajax_ffp_sso_get_providers', [$this, 'ajaxGetProviders']);
        add_action('wp_ajax_ffp_sso_save_provider', [$this, 'ajaxSaveProvider']);
        add_action('wp_ajax_ffp_sso_delete_provider', [$this, 'ajaxDeleteProvider']);
        add_action('wp_ajax_ffp_sso_unlink_identity', [$this, 'ajaxUnlinkIdentity']);

        // Cron for session cleanup
        add_action('formflow_sso_cleanup_sessions', [$this, 'cleanupExpiredSessions']);
        if (!wp_next_scheduled('formflow_sso_cleanup_sessions')) {
            wp_schedule_event(time(), 'hourly', 'formflow_sso_cleanup_sessions');
        }
    }

    /**
     * Register rewrite rules for SSO endpoints
     */
    private function registerRewriteRules(): void
    {
        add_action('init', function () {
            // SAML endpoints
            add_rewrite_rule('^saml/metadata/?$', 'index.php?ffp_sso_action=saml_metadata', 'top');
            add_rewrite_rule('^saml/acs/?$', 'index.php?ffp_sso_action=saml_acs', 'top');
            add_rewrite_rule('^saml/slo/?$', 'index.php?ffp_sso_action=saml_slo', 'top');
            add_rewrite_rule('^saml/login/?$', 'index.php?ffp_sso_action=saml_login', 'top');

            // OAuth2 endpoints
            add_rewrite_rule('^oauth2/callback/?$', 'index.php?ffp_sso_action=oauth2_callback', 'top');
            add_rewrite_rule('^oauth2/login/([^/]+)/?$', 'index.php?ffp_sso_action=oauth2_login&provider=$matches[1]', 'top');

            // LDAP endpoint
            add_rewrite_rule('^ldap/login/?$', 'index.php?ffp_sso_action=ldap_login', 'top');
        });

        add_filter('query_vars', function ($vars) {
            $vars[] = 'ffp_sso_action';
            $vars[] = 'provider';
            return $vars;
        });

        add_action('template_redirect', [$this, 'handleSSOEndpoints']);
    }

    /**
     * Handle SSO endpoints
     */
    public function handleSSOEndpoints(): void
    {
        $action = get_query_var('ffp_sso_action');
        if (empty($action)) {
            return;
        }

        switch ($action) {
            case 'saml_metadata':
                $this->handleSAMLMetadata();
                break;
            case 'saml_acs':
                $this->handleSAMLACS();
                break;
            case 'saml_slo':
                $this->handleSAMLSLO();
                break;
            case 'saml_login':
                $this->initiateSAMLLogin();
                break;
            case 'oauth2_callback':
                $this->handleOAuth2Callback();
                break;
            case 'oauth2_login':
                $provider = get_query_var('provider');
                $this->initiateOAuth2Login($provider);
                break;
            case 'ldap_login':
                $this->handleLDAPLogin();
                break;
        }
        exit;
    }

    /**
     * Handle SAML Metadata request
     */
    private function handleSAMLMetadata(): void
    {
        if (!$this->saml_provider) {
            wp_die(__('SAML provider not configured', 'formflow-pro'), 400);
        }

        header('Content-Type: application/xml');
        echo $this->saml_provider->generateMetadata();
    }

    /**
     * Handle SAML ACS (Assertion Consumer Service)
     */
    private function handleSAMLACS(): void
    {
        if (!$this->saml_provider) {
            wp_die(__('SAML provider not configured', 'formflow-pro'), 400);
        }

        try {
            $saml_response = $_POST['SAMLResponse'] ?? '';
            if (empty($saml_response)) {
                throw new \Exception(__('No SAML response received', 'formflow-pro'));
            }

            $result = $this->saml_provider->processResponse($saml_response);
            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            $user = $this->processAuthenticationResult('saml', 'saml', $result['user']);
            if ($user) {
                $this->loginUser($user, 'saml', 'saml', $result['user']);
                wp_safe_redirect($this->settings->redirect_after_login);
            } else {
                throw new \Exception(__('Failed to authenticate user', 'formflow-pro'));
            }
        } catch (\Exception $e) {
            $this->logError('SAML ACS Error', $e->getMessage());
            wp_safe_redirect(
                add_query_arg('sso_error', urlencode($e->getMessage()), wp_login_url())
            );
        }
    }

    /**
     * Handle SAML SLO (Single Logout)
     */
    private function handleSAMLSLO(): void
    {
        if (!$this->saml_provider) {
            wp_die(__('SAML provider not configured', 'formflow-pro'), 400);
        }

        try {
            $result = $this->saml_provider->processLogout($_REQUEST);
            wp_logout();
            wp_safe_redirect($this->settings->redirect_after_logout);
        } catch (\Exception $e) {
            $this->logError('SAML SLO Error', $e->getMessage());
            wp_safe_redirect(home_url());
        }
    }

    /**
     * Initiate SAML Login
     */
    private function initiateSAMLLogin(): void
    {
        if (!$this->saml_provider) {
            wp_die(__('SAML provider not configured', 'formflow-pro'), 400);
        }

        try {
            $redirect_url = $this->saml_provider->initiateLogin();
            wp_redirect($redirect_url);
        } catch (\Exception $e) {
            $this->logError('SAML Login Error', $e->getMessage());
            wp_safe_redirect(
                add_query_arg('sso_error', urlencode($e->getMessage()), wp_login_url())
            );
        }
    }

    /**
     * Handle OAuth2 Callback
     */
    private function handleOAuth2Callback(): void
    {
        if (!$this->oauth2_provider) {
            wp_die(__('OAuth2 provider not configured', 'formflow-pro'), 400);
        }

        try {
            $code = $_GET['code'] ?? '';
            $state = $_GET['state'] ?? '';
            $error = $_GET['error'] ?? '';

            if (!empty($error)) {
                throw new \Exception($_GET['error_description'] ?? $error);
            }

            if (empty($code)) {
                throw new \Exception(__('No authorization code received', 'formflow-pro'));
            }

            $result = $this->oauth2_provider->handleCallback($code, $state);
            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            $user = $this->processAuthenticationResult('oauth2', $result['provider_id'], $result['user']);
            if ($user) {
                $this->loginUser($user, 'oauth2', $result['provider_id'], $result['user'], $result['tokens'] ?? []);
                wp_safe_redirect($this->settings->redirect_after_login);
            } else {
                throw new \Exception(__('Failed to authenticate user', 'formflow-pro'));
            }
        } catch (\Exception $e) {
            $this->logError('OAuth2 Callback Error', $e->getMessage());
            wp_safe_redirect(
                add_query_arg('sso_error', urlencode($e->getMessage()), wp_login_url())
            );
        }
    }

    /**
     * Initiate OAuth2 Login
     */
    private function initiateOAuth2Login(string $provider_id): void
    {
        if (!$this->oauth2_provider) {
            wp_die(__('OAuth2 provider not configured', 'formflow-pro'), 400);
        }

        try {
            $redirect_url = $this->oauth2_provider->initiateLogin($provider_id);
            wp_redirect($redirect_url);
        } catch (\Exception $e) {
            $this->logError('OAuth2 Login Error', $e->getMessage());
            wp_safe_redirect(
                add_query_arg('sso_error', urlencode($e->getMessage()), wp_login_url())
            );
        }
    }

    /**
     * Handle LDAP Login (form submission)
     */
    private function handleLDAPLogin(): void
    {
        if (!$this->ldap_provider) {
            wp_die(__('LDAP provider not configured', 'formflow-pro'), 400);
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            wp_die(__('Invalid request method', 'formflow-pro'), 400);
        }

        check_admin_referer('ffp_ldap_login');

        try {
            $username = sanitize_user($_POST['ldap_username'] ?? '');
            $password = $_POST['ldap_password'] ?? '';

            if (empty($username) || empty($password)) {
                throw new \Exception(__('Username and password are required', 'formflow-pro'));
            }

            $result = $this->ldap_provider->authenticate($username, $password);
            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            $user = $this->processAuthenticationResult('ldap', 'ldap', $result['user']);
            if ($user) {
                $this->loginUser($user, 'ldap', 'ldap', $result['user']);
                wp_safe_redirect($this->settings->redirect_after_login);
            } else {
                throw new \Exception(__('Failed to authenticate user', 'formflow-pro'));
            }
        } catch (\Exception $e) {
            $this->logError('LDAP Login Error', $e->getMessage());
            wp_safe_redirect(
                add_query_arg('sso_error', urlencode($e->getMessage()), wp_login_url())
            );
        }
    }

    /**
     * Process authentication result and get/create WordPress user
     */
    private function processAuthenticationResult(string $provider_type, string $provider_id, array $external_user): ?\WP_User
    {
        $email = $external_user['email'] ?? '';
        $external_id = $external_user['id'] ?? $external_user['external_id'] ?? '';

        if (empty($email)) {
            $this->logError('Auth Processing', 'No email in authentication result');
            return null;
        }

        // Check domain restrictions
        if (!$this->isEmailDomainAllowed($email)) {
            $this->logError('Auth Processing', "Email domain not allowed: {$email}");
            return null;
        }

        // Check for existing identity link
        $identity_link = $this->getIdentityLink($provider_type, $provider_id, $external_id);
        if ($identity_link) {
            $user = get_user_by('ID', $identity_link->user_id);
            if ($user) {
                // Update profile if enabled
                if ($this->settings->sync_profile_on_login) {
                    $this->syncUserProfile($user, $external_user);
                }
                // Update last login
                $this->updateIdentityLinkLastLogin($identity_link->id);
                return $user;
            }
        }

        // Check for existing user by email
        $user = get_user_by('email', $email);
        if ($user) {
            // Link existing user to SSO identity
            $this->createIdentityLink($user->ID, $provider_type, $provider_id, $external_id, $email, $external_user);
            if ($this->settings->sync_profile_on_login) {
                $this->syncUserProfile($user, $external_user);
            }
            return $user;
        }

        // Auto-provision new user if enabled
        if ($this->settings->auto_provision_users) {
            $user = $this->provisionUser($external_user, $provider_type, $provider_id);
            if ($user) {
                $this->createIdentityLink($user->ID, $provider_type, $provider_id, $external_id, $email, $external_user);
                return $user;
            }
        }

        $this->logError('Auth Processing', "User not found and auto-provisioning disabled: {$email}");
        return null;
    }

    /**
     * Check if email domain is allowed
     */
    private function isEmailDomainAllowed(string $email): bool
    {
        $domain = substr(strrchr($email, "@"), 1);

        // Check blocked domains first
        if (!empty($this->settings->blocked_domains)) {
            if (in_array($domain, $this->settings->blocked_domains, true)) {
                return false;
            }
        }

        // Check allowed domains (if configured, only these are allowed)
        if (!empty($this->settings->allowed_domains)) {
            return in_array($domain, $this->settings->allowed_domains, true);
        }

        return true;
    }

    /**
     * Provision a new WordPress user
     */
    private function provisionUser(array $external_user, string $provider_type, string $provider_id): ?\WP_User
    {
        $email = $external_user['email'];
        $username = $this->generateUsername($external_user);
        $display_name = $external_user['display_name'] ?? $external_user['name'] ?? $email;
        $first_name = $external_user['first_name'] ?? '';
        $last_name = $external_user['last_name'] ?? '';

        // Determine role
        $role = $this->determineUserRole($external_user);

        $user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(24, true, true),
            'display_name' => $display_name,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        ];

        $user_id = wp_insert_user($user_data);
        if (is_wp_error($user_id)) {
            $this->logError('User Provisioning', $user_id->get_error_message());
            return null;
        }

        // Store additional metadata
        update_user_meta($user_id, 'ffp_sso_provisioned', true);
        update_user_meta($user_id, 'ffp_sso_provisioned_at', current_time('mysql'));
        update_user_meta($user_id, 'ffp_sso_provider_type', $provider_type);
        update_user_meta($user_id, 'ffp_sso_provider_id', $provider_id);

        // Store custom attributes
        if (!empty($external_user['attributes'])) {
            update_user_meta($user_id, 'ffp_sso_attributes', $external_user['attributes']);
        }

        $this->logInfo('User Provisioned', "Created user {$username} via {$provider_type}/{$provider_id}");

        do_action('formflow_sso_user_provisioned', $user_id, $external_user, $provider_type, $provider_id);

        return get_user_by('ID', $user_id);
    }

    /**
     * Generate unique username from external user data
     */
    private function generateUsername(array $external_user): string
    {
        $base = '';

        // Try preferred_username first
        if (!empty($external_user['preferred_username'])) {
            $base = $external_user['preferred_username'];
        }
        // Then try email prefix
        elseif (!empty($external_user['email'])) {
            $base = substr($external_user['email'], 0, strpos($external_user['email'], '@'));
        }
        // Then try name
        elseif (!empty($external_user['first_name']) && !empty($external_user['last_name'])) {
            $base = strtolower($external_user['first_name'] . '.' . $external_user['last_name']);
        }
        // Fallback
        else {
            $base = 'sso_user';
        }

        $username = sanitize_user($base, true);

        // Ensure uniqueness
        $original = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $original . $counter;
            $counter++;
        }

        return $username;
    }

    /**
     * Determine user role based on group mappings
     */
    private function determineUserRole(array $external_user): string
    {
        $groups = $external_user['groups'] ?? [];

        // Check role mappings
        if (!empty($this->settings->role_mapping) && !empty($groups)) {
            foreach ($this->settings->role_mapping as $wp_role => $mapped_groups) {
                foreach ((array) $mapped_groups as $mapped_group) {
                    if (in_array($mapped_group, $groups, true)) {
                        return $wp_role;
                    }
                }
            }
        }

        return $this->settings->default_role;
    }

    /**
     * Sync WordPress user profile with external data
     */
    private function syncUserProfile(\WP_User $user, array $external_user): void
    {
        $update_data = ['ID' => $user->ID];
        $changed = false;

        // Sync display name
        if (!empty($external_user['display_name']) && $user->display_name !== $external_user['display_name']) {
            $update_data['display_name'] = $external_user['display_name'];
            $changed = true;
        }

        // Sync first name
        if (!empty($external_user['first_name']) && $user->first_name !== $external_user['first_name']) {
            $update_data['first_name'] = $external_user['first_name'];
            $changed = true;
        }

        // Sync last name
        if (!empty($external_user['last_name']) && $user->last_name !== $external_user['last_name']) {
            $update_data['last_name'] = $external_user['last_name'];
            $changed = true;
        }

        if ($changed) {
            wp_update_user($update_data);
        }

        // Sync groups/roles if enabled
        if ($this->settings->sync_groups_on_login && !empty($external_user['groups'])) {
            $new_role = $this->determineUserRole($external_user);
            if (!in_array($new_role, $user->roles, true)) {
                $user->set_role($new_role);
            }
        }

        // Update metadata
        update_user_meta($user->ID, 'ffp_sso_last_sync', current_time('mysql'));
        if (!empty($external_user['attributes'])) {
            update_user_meta($user->ID, 'ffp_sso_attributes', $external_user['attributes']);
        }
    }

    /**
     * Login WordPress user after SSO authentication
     */
    private function loginUser(\WP_User $user, string $provider_type, string $provider_id, array $external_user, array $tokens = []): void
    {
        // Create SSO session
        $session_id = $this->createSSOSession($user, $provider_type, $provider_id, $external_user, $tokens);

        // Set WordPress auth cookies
        wp_set_auth_cookie($user->ID, true);
        wp_set_current_user($user->ID);

        // Set SSO session cookie
        $this->setSSOSessionCookie($session_id);

        // Update last login
        update_user_meta($user->ID, 'ffp_sso_last_login', current_time('mysql'));

        // Log the login
        $this->logInfo('User Login', "User {$user->user_login} logged in via {$provider_type}/{$provider_id}");

        do_action('formflow_sso_user_logged_in', $user, $provider_type, $provider_id, $external_user);
    }

    /**
     * Create SSO session in database
     */
    private function createSSOSession(\WP_User $user, string $provider_type, string $provider_id, array $external_user, array $tokens = []): string
    {
        global $wpdb;

        $session_id = wp_generate_uuid4();
        $expires_at = date('Y-m-d H:i:s', time() + $this->settings->session_lifetime);

        $wpdb->insert(
            $wpdb->prefix . 'ffp_sso_sessions',
            [
                'user_id' => $user->ID,
                'provider_type' => $provider_type,
                'provider_id' => $provider_id,
                'session_id' => $session_id,
                'external_id' => $external_user['id'] ?? $external_user['external_id'] ?? '',
                'attributes' => wp_json_encode($external_user['attributes'] ?? []),
                'access_token' => $tokens['access_token'] ?? '',
                'refresh_token' => $tokens['refresh_token'] ?? '',
                'token_expires' => $tokens['expires_at'] ?? 0,
                'ip_address' => $this->getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at,
                'last_activity' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        return $session_id;
    }

    /**
     * Set SSO session cookie
     */
    private function setSSOSessionCookie(string $session_id): void
    {
        $expires = time() + $this->settings->session_lifetime;
        setcookie(
            self::SESSION_COOKIE,
            $session_id,
            [
                'expires' => $expires,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Validate current SSO session
     */
    public function validateSSOSession(): void
    {
        if (!is_user_logged_in()) {
            return;
        }

        $session_id = $_COOKIE[self::SESSION_COOKIE] ?? '';
        if (empty($session_id)) {
            return;
        }

        $session = $this->getSSOSession($session_id);
        if (!$session || $session->isExpired()) {
            // Session expired or invalid
            if ($session) {
                $this->deleteSSOSession($session_id);
            }
            $this->clearSSOSessionCookie();
            return;
        }

        // Update last activity
        $this->updateSessionActivity($session_id);

        // Check if token refresh needed (OAuth2)
        if ($session->provider_type === 'oauth2' && $this->shouldRefreshToken($session)) {
            $this->refreshOAuth2Token($session);
        }
    }

    /**
     * Get SSO session from database
     */
    private function getSSOSession(string $session_id): ?SSOSession
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_sso_sessions WHERE session_id = %s",
            $session_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['attributes'] = json_decode($row['attributes'] ?? '[]', true);
        return new SSOSession($row);
    }

    /**
     * Update session last activity
     */
    private function updateSessionActivity(string $session_id): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ffp_sso_sessions',
            ['last_activity' => current_time('mysql')],
            ['session_id' => $session_id],
            ['%s'],
            ['%s']
        );
    }

    /**
     * Delete SSO session
     */
    private function deleteSSOSession(string $session_id): void
    {
        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'ffp_sso_sessions',
            ['session_id' => $session_id],
            ['%s']
        );
    }

    /**
     * Clear SSO session cookie
     */
    private function clearSSOSessionCookie(): void
    {
        setcookie(
            self::SESSION_COOKIE,
            '',
            [
                'expires' => time() - 3600,
                'path' => COOKIEPATH,
                'domain' => COOKIE_DOMAIN,
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]
        );
    }

    /**
     * Check if OAuth2 token should be refreshed
     */
    private function shouldRefreshToken(SSOSession $session): bool
    {
        if (empty($session->refresh_token) || empty($session->token_expires)) {
            return false;
        }

        // Refresh if token expires in less than 5 minutes
        return ($session->token_expires - time()) < 300;
    }

    /**
     * Refresh OAuth2 token
     */
    private function refreshOAuth2Token(SSOSession $session): void
    {
        if (!$this->oauth2_provider) {
            return;
        }

        try {
            $result = $this->oauth2_provider->refreshToken($session->provider_id, $session->refresh_token);
            if ($result['success']) {
                global $wpdb;

                $wpdb->update(
                    $wpdb->prefix . 'ffp_sso_sessions',
                    [
                        'access_token' => $result['access_token'],
                        'refresh_token' => $result['refresh_token'] ?? $session->refresh_token,
                        'token_expires' => $result['expires_at']
                    ],
                    ['session_id' => $session->session_id],
                    ['%s', '%s', '%d'],
                    ['%s']
                );
            }
        } catch (\Exception $e) {
            $this->logError('Token Refresh', $e->getMessage());
        }
    }

    /**
     * Handle user logout
     */
    public function handleLogout(): void
    {
        $session_id = $_COOKIE[self::SESSION_COOKIE] ?? '';
        if (empty($session_id)) {
            return;
        }

        $session = $this->getSSOSession($session_id);
        if (!$session) {
            return;
        }

        // Perform single logout if enabled
        if ($this->settings->single_logout_enabled) {
            $this->performSingleLogout($session);
        }

        // Clean up
        $this->deleteSSOSession($session_id);
        $this->clearSSOSessionCookie();

        $this->logInfo('User Logout', "User ID {$session->user_id} logged out from {$session->provider_type}");
    }

    /**
     * Perform single logout with IdP
     */
    private function performSingleLogout(SSOSession $session): void
    {
        switch ($session->provider_type) {
            case 'saml':
                if ($this->saml_provider) {
                    try {
                        $this->saml_provider->initiateLogout($session->external_id);
                    } catch (\Exception $e) {
                        $this->logError('SAML SLO', $e->getMessage());
                    }
                }
                break;

            case 'oauth2':
                if ($this->oauth2_provider && !empty($session->access_token)) {
                    try {
                        $this->oauth2_provider->logout($session->provider_id, $session->access_token);
                    } catch (\Exception $e) {
                        $this->logError('OAuth2 Logout', $e->getMessage());
                    }
                }
                break;

            // LDAP doesn't support single logout
        }
    }

    /**
     * Clear SSO session on auth cookie clear
     */
    public function clearSSOSession(): void
    {
        $session_id = $_COOKIE[self::SESSION_COOKIE] ?? '';
        if (!empty($session_id)) {
            $this->deleteSSOSession($session_id);
            $this->clearSSOSessionCookie();
        }
    }

    /**
     * Get identity link
     */
    private function getIdentityLink(string $provider_type, string $provider_id, string $external_id): ?SSOIdentityLink
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_sso_identity_links
             WHERE provider_type = %s AND provider_id = %s AND external_id = %s",
            $provider_type,
            $provider_id,
            $external_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        $row['profile_data'] = json_decode($row['profile_data'] ?? '[]', true);
        return new SSOIdentityLink($row);
    }

    /**
     * Create identity link
     */
    private function createIdentityLink(int $user_id, string $provider_type, string $provider_id, string $external_id, string $email, array $profile_data): int
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ffp_sso_identity_links',
            [
                'user_id' => $user_id,
                'provider_type' => $provider_type,
                'provider_id' => $provider_id,
                'external_id' => $external_id,
                'email' => $email,
                'profile_data' => wp_json_encode($profile_data),
                'is_primary' => $this->isFirstIdentityLink($user_id) ? 1 : 0,
                'linked_at' => current_time('mysql'),
                'last_login' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']
        );

        return $wpdb->insert_id;
    }

    /**
     * Check if this is the first identity link for user
     */
    private function isFirstIdentityLink(int $user_id): bool
    {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ffp_sso_identity_links WHERE user_id = %d",
            $user_id
        ));

        return (int) $count === 0;
    }

    /**
     * Update identity link last login
     */
    private function updateIdentityLinkLastLogin(int $link_id): void
    {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ffp_sso_identity_links',
            ['last_login' => current_time('mysql')],
            ['id' => $link_id],
            ['%s'],
            ['%d']
        );
    }

    /**
     * Render login buttons on WordPress login form
     */
    public function renderLoginButtons(): void
    {
        if (!$this->settings->enabled) {
            return;
        }

        $enabled_providers = $this->getEnabledProviders();
        if (empty($enabled_providers)) {
            return;
        }

        echo '<div class="ffp-sso-login-buttons">';

        foreach ($enabled_providers as $provider) {
            $this->renderProviderLoginButton($provider);
        }

        // LDAP Login Form (if enabled)
        if ($this->isProviderEnabled('ldap')) {
            $this->renderLDAPLoginForm();
        }

        // Separator
        if (!$this->settings->hide_local_login) {
            echo '<div class="ffp-sso-separator">';
            echo '<span>' . esc_html__('or', 'formflow-pro') . '</span>';
            echo '</div>';
        }

        echo '</div>';

        // Hide local login form if configured
        if ($this->settings->hide_local_login) {
            echo '<style>#loginform > p:not(.ffp-sso-login-buttons), #loginform > .user-pass-wrap, #loginform > .forgetmenot, #loginform > .submit { display: none !important; }</style>';
        }
    }

    /**
     * Render single provider login button
     */
    private function renderProviderLoginButton(array $provider): void
    {
        $url = '';
        $label = '';
        $icon = '';
        $class = 'ffp-sso-btn';

        switch ($provider['type']) {
            case 'saml':
                $url = home_url('/saml/login');
                $label = $provider['button_text'] ?? __('Sign in with SAML', 'formflow-pro');
                $icon = 'dashicons-shield';
                $class .= ' ffp-sso-btn-saml';
                break;

            case 'oauth2':
                $url = home_url('/oauth2/login/' . $provider['id']);
                $label = $provider['button_text'] ?? sprintf(__('Sign in with %s', 'formflow-pro'), $provider['name']);
                $icon = $this->getOAuth2ProviderIcon($provider['id']);
                $class .= ' ffp-sso-btn-oauth2 ffp-sso-btn-' . esc_attr($provider['id']);
                break;
        }

        if (empty($url)) {
            return;
        }

        echo '<a href="' . esc_url($url) . '" class="' . esc_attr($class) . '">';
        if (!empty($icon)) {
            echo '<span class="dashicons ' . esc_attr($icon) . '"></span> ';
        }
        echo esc_html($label);
        echo '</a>';
    }

    /**
     * Get OAuth2 provider icon
     */
    private function getOAuth2ProviderIcon(string $provider_id): string
    {
        $icons = [
            'azure' => 'dashicons-microsoft',
            'okta' => 'dashicons-shield-alt',
            'auth0' => 'dashicons-lock',
            'google' => 'dashicons-google',
            'keycloak' => 'dashicons-admin-network',
            'onelogin' => 'dashicons-admin-users',
            'ping' => 'dashicons-networking'
        ];

        return $icons[$provider_id] ?? 'dashicons-admin-generic';
    }

    /**
     * Render LDAP login form
     */
    private function renderLDAPLoginForm(): void
    {
        ?>
        <div class="ffp-sso-ldap-form">
            <h3><?php esc_html_e('Corporate Login', 'formflow-pro'); ?></h3>
            <form method="post" action="<?php echo esc_url(home_url('/ldap/login')); ?>">
                <?php wp_nonce_field('ffp_ldap_login'); ?>
                <p>
                    <label for="ldap_username"><?php esc_html_e('Username', 'formflow-pro'); ?></label>
                    <input type="text" name="ldap_username" id="ldap_username" class="input" required />
                </p>
                <p>
                    <label for="ldap_password"><?php esc_html_e('Password', 'formflow-pro'); ?></label>
                    <input type="password" name="ldap_password" id="ldap_password" class="input" required />
                </p>
                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <?php esc_html_e('Sign in with LDAP', 'formflow-pro'); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Enqueue login page styles
     */
    public function enqueueLoginStyles(): void
    {
        if (!$this->settings->enabled) {
            return;
        }

        wp_enqueue_style('dashicons');

        $css = '
            .ffp-sso-login-buttons {
                margin: 20px 0;
                text-align: center;
            }
            .ffp-sso-btn {
                display: block;
                width: 100%;
                padding: 12px 20px;
                margin: 10px 0;
                background: #0073aa;
                color: #fff;
                text-decoration: none;
                border-radius: 4px;
                font-size: 14px;
                font-weight: 600;
                text-align: center;
                box-sizing: border-box;
                transition: background 0.2s;
            }
            .ffp-sso-btn:hover {
                background: #005a87;
                color: #fff;
            }
            .ffp-sso-btn .dashicons {
                vertical-align: middle;
                margin-right: 8px;
            }
            .ffp-sso-btn-azure { background: #0078d4; }
            .ffp-sso-btn-azure:hover { background: #005a9e; }
            .ffp-sso-btn-google { background: #4285f4; }
            .ffp-sso-btn-google:hover { background: #2a75f3; }
            .ffp-sso-btn-okta { background: #007dc1; }
            .ffp-sso-btn-okta:hover { background: #0066a1; }
            .ffp-sso-btn-saml { background: #2c3e50; }
            .ffp-sso-btn-saml:hover { background: #1a252f; }
            .ffp-sso-separator {
                margin: 20px 0;
                text-align: center;
                position: relative;
            }
            .ffp-sso-separator::before {
                content: "";
                position: absolute;
                left: 0;
                top: 50%;
                width: 100%;
                height: 1px;
                background: #ddd;
            }
            .ffp-sso-separator span {
                background: #fff;
                padding: 0 15px;
                position: relative;
                color: #999;
                text-transform: uppercase;
                font-size: 12px;
            }
            .ffp-sso-ldap-form {
                background: #f7f7f7;
                padding: 15px;
                border-radius: 4px;
                margin: 15px 0;
            }
            .ffp-sso-ldap-form h3 {
                margin: 0 0 15px;
                font-size: 14px;
            }
            .ffp-sso-ldap-form label {
                display: block;
                margin-bottom: 5px;
                font-weight: 600;
            }
            .ffp-sso-ldap-form .input {
                width: 100%;
            }
            .ffp-sso-error {
                background: #fbeaea;
                border-left: 4px solid #dc3232;
                padding: 12px;
                margin: 15px 0;
            }
        ';

        wp_add_inline_style('login', $css);
    }

    /**
     * Filter login message to show SSO errors
     */
    public function filterLoginMessage(string $message): string
    {
        $error = $_GET['sso_error'] ?? '';
        if (!empty($error)) {
            $message .= '<div class="ffp-sso-error">';
            $message .= '<strong>' . esc_html__('SSO Error:', 'formflow-pro') . '</strong> ';
            $message .= esc_html(urldecode($error));
            $message .= '</div>';
        }
        return $message;
    }

    /**
     * Get enabled providers
     */
    private function getEnabledProviders(): array
    {
        $providers = [];

        // SAML
        if ($this->isProviderEnabled('saml') && $this->saml_provider && $this->saml_provider->isConfigured()) {
            $providers[] = [
                'type' => 'saml',
                'id' => 'saml',
                'name' => 'SAML',
                'button_text' => $this->providers_config['saml']['button_text'] ?? null
            ];
        }

        // OAuth2 providers
        if ($this->oauth2_provider) {
            $oauth2_providers = $this->oauth2_provider->getConfiguredProviders();
            foreach ($oauth2_providers as $provider) {
                if ($this->isProviderEnabled('oauth2_' . $provider['id'])) {
                    $providers[] = [
                        'type' => 'oauth2',
                        'id' => $provider['id'],
                        'name' => $provider['name'],
                        'button_text' => $provider['button_text'] ?? null
                    ];
                }
            }
        }

        return $providers;
    }

    /**
     * Check if provider is enabled
     */
    private function isProviderEnabled(string $provider_key): bool
    {
        return in_array($provider_key, $this->settings->enabled_providers, true);
    }

    /**
     * Register admin pages
     */
    private function registerAdminPages(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'formflow-pro',
                __('SSO Settings', 'formflow-pro'),
                __('SSO', 'formflow-pro'),
                'manage_options',
                'formflow-sso',
                [$this, 'renderAdminPage']
            );
        });

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if ($hook !== 'formflow-pro_page_formflow-sso') {
            return;
        }

        wp_enqueue_style('ffp-sso-admin', plugins_url('assets/css/sso-admin.css', dirname(__DIR__)));
        wp_enqueue_script('ffp-sso-admin', plugins_url('assets/js/sso-admin.js', dirname(__DIR__)), ['jquery', 'wp-util'], '1.0.0', true);

        wp_localize_script('ffp-sso-admin', 'ffpSSOAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ffp_sso_admin'),
            'i18n' => [
                'confirmDelete' => __('Are you sure you want to delete this provider?', 'formflow-pro'),
                'testSuccess' => __('Connection test successful!', 'formflow-pro'),
                'testFailed' => __('Connection test failed:', 'formflow-pro'),
                'saving' => __('Saving...', 'formflow-pro'),
                'saved' => __('Saved!', 'formflow-pro')
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function renderAdminPage(): void
    {
        $active_tab = $_GET['tab'] ?? 'general';
        ?>
        <div class="wrap ffp-sso-admin">
            <h1><?php esc_html_e('Enterprise SSO Settings', 'formflow-pro'); ?></h1>

            <nav class="nav-tab-wrapper">
                <a href="?page=formflow-sso&tab=general" class="nav-tab <?php echo $active_tab === 'general' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('General', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-sso&tab=saml" class="nav-tab <?php echo $active_tab === 'saml' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('SAML 2.0', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-sso&tab=ldap" class="nav-tab <?php echo $active_tab === 'ldap' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('LDAP/AD', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-sso&tab=oauth2" class="nav-tab <?php echo $active_tab === 'oauth2' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('OAuth2/OIDC', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-sso&tab=users" class="nav-tab <?php echo $active_tab === 'users' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Users', 'formflow-pro'); ?>
                </a>
                <a href="?page=formflow-sso&tab=logs" class="nav-tab <?php echo $active_tab === 'logs' ? 'nav-tab-active' : ''; ?>">
                    <?php esc_html_e('Logs', 'formflow-pro'); ?>
                </a>
            </nav>

            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'saml':
                        $this->renderSAMLTab();
                        break;
                    case 'ldap':
                        $this->renderLDAPTab();
                        break;
                    case 'oauth2':
                        $this->renderOAuth2Tab();
                        break;
                    case 'users':
                        $this->renderUsersTab();
                        break;
                    case 'logs':
                        $this->renderLogsTab();
                        break;
                    default:
                        $this->renderGeneralTab();
                }
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render General settings tab
     */
    private function renderGeneralTab(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && check_admin_referer('ffp_sso_general')) {
            $this->saveGeneralSettings($_POST);
            echo '<div class="notice notice-success"><p>' . esc_html__('Settings saved.', 'formflow-pro') . '</p></div>';
            $this->loadSettings();
        }
        ?>
        <form method="post">
            <?php wp_nonce_field('ffp_sso_general'); ?>

            <h2><?php esc_html_e('SSO Configuration', 'formflow-pro'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Enable SSO', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="enabled" value="1" <?php checked($this->settings->enabled); ?> />
                            <?php esc_html_e('Enable Single Sign-On', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Force SSO', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="force_sso" value="1" <?php checked($this->settings->force_sso); ?> />
                            <?php esc_html_e('Require SSO for all users (disable local login)', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Allow Local Login', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="allow_local_login" value="1" <?php checked($this->settings->allow_local_login); ?> />
                            <?php esc_html_e('Allow username/password login alongside SSO', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Hide Local Login Form', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="hide_local_login" value="1" <?php checked($this->settings->hide_local_login); ?> />
                            <?php esc_html_e('Hide the username/password form on login page', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('User Provisioning', 'formflow-pro'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Auto-Provision Users', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="auto_provision_users" value="1" <?php checked($this->settings->auto_provision_users); ?> />
                            <?php esc_html_e('Automatically create WordPress users on first SSO login', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Default Role', 'formflow-pro'); ?></th>
                    <td>
                        <select name="default_role">
                            <?php wp_dropdown_roles($this->settings->default_role); ?>
                        </select>
                        <p class="description"><?php esc_html_e('Default role for new SSO users', 'formflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Sync Profile on Login', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sync_profile_on_login" value="1" <?php checked($this->settings->sync_profile_on_login); ?> />
                            <?php esc_html_e('Update user profile with IdP data on each login', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Sync Groups/Roles', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="sync_groups_on_login" value="1" <?php checked($this->settings->sync_groups_on_login); ?> />
                            <?php esc_html_e('Sync user roles based on IdP group membership', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Domain Restrictions', 'formflow-pro'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Allowed Domains', 'formflow-pro'); ?></th>
                    <td>
                        <textarea name="allowed_domains" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $this->settings->allowed_domains)); ?></textarea>
                        <p class="description"><?php esc_html_e('One domain per line. Leave empty to allow all domains.', 'formflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Blocked Domains', 'formflow-pro'); ?></th>
                    <td>
                        <textarea name="blocked_domains" rows="3" class="large-text"><?php echo esc_textarea(implode("\n", $this->settings->blocked_domains)); ?></textarea>
                        <p class="description"><?php esc_html_e('One domain per line. Users from these domains will be blocked.', 'formflow-pro'); ?></p>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Session Settings', 'formflow-pro'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Session Lifetime', 'formflow-pro'); ?></th>
                    <td>
                        <input type="number" name="session_lifetime" value="<?php echo esc_attr($this->settings->session_lifetime); ?>" min="300" step="300" />
                        <p class="description"><?php esc_html_e('Session lifetime in seconds (default: 28800 = 8 hours)', 'formflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Single Logout', 'formflow-pro'); ?></th>
                    <td>
                        <label>
                            <input type="checkbox" name="single_logout_enabled" value="1" <?php checked($this->settings->single_logout_enabled); ?> />
                            <?php esc_html_e('Enable Single Logout (logout from IdP when logging out of WordPress)', 'formflow-pro'); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <h2><?php esc_html_e('Login Page Customization', 'formflow-pro'); ?></h2>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e('Button Text', 'formflow-pro'); ?></th>
                    <td>
                        <input type="text" name="login_button_text" value="<?php echo esc_attr($this->settings->login_button_text); ?>" class="regular-text" />
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Redirect After Login', 'formflow-pro'); ?></th>
                    <td>
                        <input type="url" name="redirect_after_login" value="<?php echo esc_attr($this->settings->redirect_after_login); ?>" class="regular-text" />
                        <p class="description"><?php esc_html_e('URL to redirect users after successful SSO login', 'formflow-pro'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e('Redirect After Logout', 'formflow-pro'); ?></th>
                    <td>
                        <input type="url" name="redirect_after_logout" value="<?php echo esc_attr($this->settings->redirect_after_logout); ?>" class="regular-text" />
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>
        <?php
    }

    /**
     * Render SAML settings tab
     */
    private function renderSAMLTab(): void
    {
        $saml_config = $this->providers_config['saml'] ?? [];
        ?>
        <div class="ffp-sso-provider-config">
            <h2><?php esc_html_e('SAML 2.0 Configuration', 'formflow-pro'); ?></h2>

            <div class="ffp-sso-info-box">
                <h3><?php esc_html_e('Service Provider Metadata', 'formflow-pro'); ?></h3>
                <table class="ffp-sso-metadata">
                    <tr>
                        <td><strong><?php esc_html_e('Entity ID:', 'formflow-pro'); ?></strong></td>
                        <td><code><?php echo esc_html(home_url('/saml/metadata')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('ACS URL:', 'formflow-pro'); ?></strong></td>
                        <td><code><?php echo esc_html(home_url('/saml/acs')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('SLO URL:', 'formflow-pro'); ?></strong></td>
                        <td><code><?php echo esc_html(home_url('/saml/slo')); ?></code></td>
                    </tr>
                    <tr>
                        <td><strong><?php esc_html_e('Metadata URL:', 'formflow-pro'); ?></strong></td>
                        <td>
                            <a href="<?php echo esc_url(home_url('/saml/metadata')); ?>" target="_blank">
                                <?php echo esc_html(home_url('/saml/metadata')); ?>
                            </a>
                        </td>
                    </tr>
                </table>
            </div>

            <form method="post" id="ffp-saml-form">
                <?php wp_nonce_field('ffp_sso_saml'); ?>

                <h3><?php esc_html_e('Identity Provider Settings', 'formflow-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('IdP Entity ID', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="idp_entity_id" value="<?php echo esc_attr($saml_config['idp_entity_id'] ?? ''); ?>" class="large-text" />
                            <p class="description"><?php esc_html_e('The Entity ID of your Identity Provider', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('IdP SSO URL', 'formflow-pro'); ?></th>
                        <td>
                            <input type="url" name="idp_sso_url" value="<?php echo esc_attr($saml_config['idp_sso_url'] ?? ''); ?>" class="large-text" />
                            <p class="description"><?php esc_html_e('Single Sign-On Service URL', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('IdP SLO URL', 'formflow-pro'); ?></th>
                        <td>
                            <input type="url" name="idp_slo_url" value="<?php echo esc_attr($saml_config['idp_slo_url'] ?? ''); ?>" class="large-text" />
                            <p class="description"><?php esc_html_e('Single Logout Service URL (optional)', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('IdP Certificate', 'formflow-pro'); ?></th>
                        <td>
                            <textarea name="idp_certificate" rows="8" class="large-text code"><?php echo esc_textarea($saml_config['idp_certificate'] ?? ''); ?></textarea>
                            <p class="description"><?php esc_html_e('X.509 certificate from your Identity Provider (PEM format)', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e('Security Settings', 'formflow-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Sign Requests', 'formflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="sign_requests" value="1" <?php checked($saml_config['sign_requests'] ?? true); ?> />
                                <?php esc_html_e('Sign authentication requests', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Want Assertions Signed', 'formflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="want_assertions_signed" value="1" <?php checked($saml_config['want_assertions_signed'] ?? true); ?> />
                                <?php esc_html_e('Require signed assertions from IdP', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button(__('Save SAML Settings', 'formflow-pro'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" id="ffp-test-saml"><?php esc_html_e('Test Connection', 'formflow-pro'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render LDAP settings tab
     */
    private function renderLDAPTab(): void
    {
        $ldap_config = $this->providers_config['ldap'] ?? [];
        ?>
        <div class="ffp-sso-provider-config">
            <h2><?php esc_html_e('LDAP/Active Directory Configuration', 'formflow-pro'); ?></h2>

            <form method="post" id="ffp-ldap-form">
                <?php wp_nonce_field('ffp_sso_ldap'); ?>

                <h3><?php esc_html_e('Server Settings', 'formflow-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('LDAP Host', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="host" value="<?php echo esc_attr($ldap_config['host'] ?? ''); ?>" class="regular-text" placeholder="ldap.example.com" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Port', 'formflow-pro'); ?></th>
                        <td>
                            <input type="number" name="port" value="<?php echo esc_attr($ldap_config['port'] ?? 389); ?>" class="small-text" />
                            <p class="description"><?php esc_html_e('Default: 389 (LDAP) or 636 (LDAPS)', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Encryption', 'formflow-pro'); ?></th>
                        <td>
                            <label>
                                <input type="checkbox" name="use_ssl" value="1" <?php checked($ldap_config['use_ssl'] ?? false); ?> />
                                <?php esc_html_e('Use SSL (LDAPS)', 'formflow-pro'); ?>
                            </label>
                            <br />
                            <label>
                                <input type="checkbox" name="use_tls" value="1" <?php checked($ldap_config['use_tls'] ?? true); ?> />
                                <?php esc_html_e('Use StartTLS', 'formflow-pro'); ?>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Base DN', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="base_dn" value="<?php echo esc_attr($ldap_config['base_dn'] ?? ''); ?>" class="large-text" placeholder="dc=example,dc=com" />
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e('Bind Credentials', 'formflow-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('Bind DN', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="bind_dn" value="<?php echo esc_attr($ldap_config['bind_dn'] ?? ''); ?>" class="large-text" placeholder="cn=admin,dc=example,dc=com" />
                            <p class="description"><?php esc_html_e('Service account DN for searching users', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('Bind Password', 'formflow-pro'); ?></th>
                        <td>
                            <input type="password" name="bind_password" value="<?php echo esc_attr($ldap_config['bind_password'] ?? ''); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <h3><?php esc_html_e('User Search Settings', 'formflow-pro'); ?></h3>

                <table class="form-table">
                    <tr>
                        <th scope="row"><?php esc_html_e('User Filter', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="user_filter" value="<?php echo esc_attr($ldap_config['user_filter'] ?? '(&(objectClass=user)(sAMAccountName=%s))'); ?>" class="large-text" />
                            <p class="description"><?php esc_html_e('LDAP filter for user search. Use %s as placeholder for username.', 'formflow-pro'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><?php esc_html_e('User ID Attribute', 'formflow-pro'); ?></th>
                        <td>
                            <input type="text" name="user_id_attribute" value="<?php echo esc_attr($ldap_config['user_id_attribute'] ?? 'sAMAccountName'); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <?php submit_button(__('Save LDAP Settings', 'formflow-pro'), 'primary', 'submit', false); ?>
                    <button type="button" class="button" id="ffp-test-ldap"><?php esc_html_e('Test Connection', 'formflow-pro'); ?></button>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Render OAuth2 settings tab
     */
    private function renderOAuth2Tab(): void
    {
        $oauth2_providers = $this->providers_config['oauth2'] ?? [];
        ?>
        <div class="ffp-sso-provider-config">
            <h2><?php esc_html_e('OAuth2/OpenID Connect Providers', 'formflow-pro'); ?></h2>

            <div class="ffp-sso-info-box">
                <strong><?php esc_html_e('Callback URL:', 'formflow-pro'); ?></strong>
                <code><?php echo esc_html(home_url('/oauth2/callback')); ?></code>
                <p class="description"><?php esc_html_e('Use this URL as the redirect/callback URI in your OAuth2 provider configuration.', 'formflow-pro'); ?></p>
            </div>

            <h3><?php esc_html_e('Quick Setup', 'formflow-pro'); ?></h3>
            <div class="ffp-oauth2-presets">
                <button type="button" class="button ffp-oauth2-preset" data-provider="azure">
                    <span class="dashicons dashicons-cloud"></span> Azure AD
                </button>
                <button type="button" class="button ffp-oauth2-preset" data-provider="okta">
                    <span class="dashicons dashicons-shield-alt"></span> Okta
                </button>
                <button type="button" class="button ffp-oauth2-preset" data-provider="auth0">
                    <span class="dashicons dashicons-lock"></span> Auth0
                </button>
                <button type="button" class="button ffp-oauth2-preset" data-provider="google">
                    <span class="dashicons dashicons-google"></span> Google Workspace
                </button>
                <button type="button" class="button ffp-oauth2-preset" data-provider="keycloak">
                    <span class="dashicons dashicons-admin-network"></span> Keycloak
                </button>
                <button type="button" class="button ffp-oauth2-preset" data-provider="custom">
                    <span class="dashicons dashicons-admin-generic"></span> Custom
                </button>
            </div>

            <div id="ffp-oauth2-providers">
                <?php foreach ($oauth2_providers as $index => $provider) : ?>
                    <div class="ffp-oauth2-provider-card" data-index="<?php echo esc_attr($index); ?>">
                        <h4>
                            <?php echo esc_html($provider['name'] ?? 'OAuth2 Provider'); ?>
                            <button type="button" class="button-link ffp-remove-provider">&times;</button>
                        </h4>
                        <!-- Provider form fields here -->
                    </div>
                <?php endforeach; ?>
            </div>

            <div id="ffp-oauth2-form-template" style="display: none;">
                <form class="ffp-oauth2-provider-form">
                    <?php wp_nonce_field('ffp_sso_oauth2'); ?>
                    <input type="hidden" name="provider_id" value="" />

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Provider Name', 'formflow-pro'); ?></th>
                            <td><input type="text" name="name" class="regular-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Client ID', 'formflow-pro'); ?></th>
                            <td><input type="text" name="client_id" class="large-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Client Secret', 'formflow-pro'); ?></th>
                            <td><input type="password" name="client_secret" class="large-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Authorization URL', 'formflow-pro'); ?></th>
                            <td><input type="url" name="authorization_endpoint" class="large-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Token URL', 'formflow-pro'); ?></th>
                            <td><input type="url" name="token_endpoint" class="large-text" required /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('UserInfo URL', 'formflow-pro'); ?></th>
                            <td><input type="url" name="userinfo_endpoint" class="large-text" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Scopes', 'formflow-pro'); ?></th>
                            <td>
                                <input type="text" name="scopes" class="large-text" value="openid profile email" />
                                <p class="description"><?php esc_html_e('Space-separated list of scopes', 'formflow-pro'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Use PKCE', 'formflow-pro'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="use_pkce" value="1" checked />
                                    <?php esc_html_e('Enable PKCE (recommended)', 'formflow-pro'); ?>
                                </label>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php esc_html_e('Save Provider', 'formflow-pro'); ?></button>
                        <button type="button" class="button ffp-test-oauth2"><?php esc_html_e('Test', 'formflow-pro'); ?></button>
                        <button type="button" class="button ffp-cancel-oauth2"><?php esc_html_e('Cancel', 'formflow-pro'); ?></button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }

    /**
     * Render Users tab
     */
    private function renderUsersTab(): void
    {
        global $wpdb;

        $users = $wpdb->get_results(
            "SELECT u.ID, u.user_login, u.user_email, u.display_name,
                    il.provider_type, il.provider_id, il.external_id, il.last_login
             FROM {$wpdb->users} u
             INNER JOIN {$wpdb->prefix}ffp_sso_identity_links il ON u.ID = il.user_id
             ORDER BY il.last_login DESC
             LIMIT 100"
        );
        ?>
        <h2><?php esc_html_e('SSO Users', 'formflow-pro'); ?></h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Email', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Provider', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('External ID', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Last Login', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Actions', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No SSO users found.', 'formflow-pro'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($users as $user) : ?>
                        <tr>
                            <td>
                                <a href="<?php echo esc_url(get_edit_user_link($user->ID)); ?>">
                                    <?php echo esc_html($user->display_name); ?>
                                </a>
                                <br />
                                <small><?php echo esc_html($user->user_login); ?></small>
                            </td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td>
                                <span class="ffp-provider-badge ffp-provider-<?php echo esc_attr($user->provider_type); ?>">
                                    <?php echo esc_html(strtoupper($user->provider_type)); ?>
                                </span>
                                <?php if ($user->provider_id !== $user->provider_type) : ?>
                                    <br /><small><?php echo esc_html($user->provider_id); ?></small>
                                <?php endif; ?>
                            </td>
                            <td><code><?php echo esc_html(substr($user->external_id, 0, 20)); ?>...</code></td>
                            <td><?php echo esc_html($user->last_login ? human_time_diff(strtotime($user->last_login)) . ' ago' : '-'); ?></td>
                            <td>
                                <button type="button" class="button button-small ffp-unlink-identity" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                    <?php esc_html_e('Unlink', 'formflow-pro'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
        <?php
    }

    /**
     * Render Logs tab
     */
    private function renderLogsTab(): void
    {
        global $wpdb;

        $logs = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}ffp_sso_logs
             ORDER BY created_at DESC
             LIMIT 200"
        );
        ?>
        <h2><?php esc_html_e('SSO Logs', 'formflow-pro'); ?></h2>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 150px;"><?php esc_html_e('Time', 'formflow-pro'); ?></th>
                    <th style="width: 80px;"><?php esc_html_e('Level', 'formflow-pro'); ?></th>
                    <th style="width: 150px;"><?php esc_html_e('Event', 'formflow-pro'); ?></th>
                    <th><?php esc_html_e('Message', 'formflow-pro'); ?></th>
                    <th style="width: 100px;"><?php esc_html_e('User', 'formflow-pro'); ?></th>
                    <th style="width: 120px;"><?php esc_html_e('IP', 'formflow-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($logs)) : ?>
                    <tr>
                        <td colspan="6"><?php esc_html_e('No logs found.', 'formflow-pro'); ?></td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($logs as $log) : ?>
                        <tr class="ffp-log-<?php echo esc_attr($log->level); ?>">
                            <td><?php echo esc_html($log->created_at); ?></td>
                            <td>
                                <span class="ffp-log-level ffp-log-level-<?php echo esc_attr($log->level); ?>">
                                    <?php echo esc_html(strtoupper($log->level)); ?>
                                </span>
                            </td>
                            <td><?php echo esc_html($log->event); ?></td>
                            <td><?php echo esc_html($log->message); ?></td>
                            <td><?php echo $log->user_id ? esc_html($log->user_id) : '-'; ?></td>
                            <td><?php echo esc_html($log->ip_address); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <p>
            <button type="button" class="button" id="ffp-clear-logs">
                <?php esc_html_e('Clear Logs', 'formflow-pro'); ?>
            </button>
            <button type="button" class="button" id="ffp-export-logs">
                <?php esc_html_e('Export CSV', 'formflow-pro'); ?>
            </button>
        </p>
        <?php
    }

    /**
     * Save general settings
     */
    private function saveGeneralSettings(array $data): void
    {
        $settings = [
            'enabled' => !empty($data['enabled']),
            'force_sso' => !empty($data['force_sso']),
            'allow_local_login' => !empty($data['allow_local_login']),
            'hide_local_login' => !empty($data['hide_local_login']),
            'auto_provision_users' => !empty($data['auto_provision_users']),
            'default_role' => sanitize_text_field($data['default_role'] ?? 'subscriber'),
            'sync_profile_on_login' => !empty($data['sync_profile_on_login']),
            'sync_groups_on_login' => !empty($data['sync_groups_on_login']),
            'session_lifetime' => absint($data['session_lifetime'] ?? 28800),
            'single_logout_enabled' => !empty($data['single_logout_enabled']),
            'allowed_domains' => array_filter(array_map('trim', explode("\n", $data['allowed_domains'] ?? ''))),
            'blocked_domains' => array_filter(array_map('trim', explode("\n", $data['blocked_domains'] ?? ''))),
            'login_button_text' => sanitize_text_field($data['login_button_text'] ?? ''),
            'redirect_after_login' => esc_url_raw($data['redirect_after_login'] ?? admin_url()),
            'redirect_after_logout' => esc_url_raw($data['redirect_after_logout'] ?? home_url()),
            'enabled_providers' => $this->settings->enabled_providers // Preserve existing
        ];

        update_option(self::OPTION_SETTINGS, $settings);
    }

    /**
     * Register REST API routes
     */
    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('formflow-pro/v1', '/sso/providers', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'restGetProviders'],
                    'permission_callback' => [$this, 'restPermissionCheck']
                ]
            ]);

            register_rest_route('formflow-pro/v1', '/sso/sessions', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'restGetSessions'],
                    'permission_callback' => [$this, 'restPermissionCheck']
                ]
            ]);

            register_rest_route('formflow-pro/v1', '/sso/stats', [
                [
                    'methods' => 'GET',
                    'callback' => [$this, 'restGetStats'],
                    'permission_callback' => [$this, 'restPermissionCheck']
                ]
            ]);
        });
    }

    /**
     * REST permission check
     */
    public function restPermissionCheck(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * REST: Get providers
     */
    public function restGetProviders(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'providers' => $this->getEnabledProviders(),
            'config' => $this->providers_config
        ]);
    }

    /**
     * REST: Get active sessions
     */
    public function restGetSessions(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $sessions = $wpdb->get_results(
            "SELECT s.*, u.user_login, u.display_name
             FROM {$wpdb->prefix}ffp_sso_sessions s
             LEFT JOIN {$wpdb->users} u ON s.user_id = u.ID
             WHERE s.expires_at > NOW()
             ORDER BY s.last_activity DESC
             LIMIT 100"
        );

        return new \WP_REST_Response(['sessions' => $sessions]);
    }

    /**
     * REST: Get SSO statistics
     */
    public function restGetStats(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $stats = [
            'total_sso_users' => (int) $wpdb->get_var(
                "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ffp_sso_identity_links"
            ),
            'active_sessions' => (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ffp_sso_sessions WHERE expires_at > NOW()"
            ),
            'logins_today' => (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ffp_sso_logs
                 WHERE event = 'User Login' AND created_at >= %s",
                date('Y-m-d 00:00:00')
            )),
            'providers_by_usage' => $wpdb->get_results(
                "SELECT provider_type, COUNT(*) as count
                 FROM {$wpdb->prefix}ffp_sso_identity_links
                 GROUP BY provider_type"
            )
        ];

        return new \WP_REST_Response($stats);
    }

    /**
     * AJAX: Test connection
     */
    public function ajaxTestConnection(): void
    {
        check_ajax_referer('ffp_sso_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        try {
            switch ($provider) {
                case 'saml':
                    $result = $this->testSAMLConnection();
                    break;
                case 'ldap':
                    $result = $this->testLDAPConnection($_POST);
                    break;
                case 'oauth2':
                    $result = $this->testOAuth2Connection($_POST);
                    break;
                default:
                    throw new \Exception(__('Invalid provider', 'formflow-pro'));
            }

            wp_send_json_success($result);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Test SAML connection
     */
    private function testSAMLConnection(): array
    {
        if (!$this->saml_provider || !$this->saml_provider->isConfigured()) {
            throw new \Exception(__('SAML provider not configured', 'formflow-pro'));
        }

        // For SAML, we can only verify the configuration is valid
        // Actual test requires user interaction
        return [
            'status' => 'configured',
            'message' => __('SAML configuration is valid. Use the login button to test authentication.', 'formflow-pro')
        ];
    }

    /**
     * Test LDAP connection
     */
    private function testLDAPConnection(array $config): array
    {
        if (!$this->ldap_provider) {
            throw new \Exception(__('LDAP provider not available', 'formflow-pro'));
        }

        $result = $this->ldap_provider->testConnection($config);
        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        return [
            'status' => 'connected',
            'message' => __('Successfully connected to LDAP server', 'formflow-pro'),
            'details' => $result['details'] ?? []
        ];
    }

    /**
     * Test OAuth2 connection
     */
    private function testOAuth2Connection(array $config): array
    {
        if (!$this->oauth2_provider) {
            throw new \Exception(__('OAuth2 provider not available', 'formflow-pro'));
        }

        $result = $this->oauth2_provider->testConfiguration($config);
        if (!$result['success']) {
            throw new \Exception($result['error']);
        }

        return [
            'status' => 'valid',
            'message' => __('OAuth2 configuration is valid', 'formflow-pro')
        ];
    }

    /**
     * AJAX: Unlink identity
     */
    public function ajaxUnlinkIdentity(): void
    {
        check_ajax_referer('ffp_sso_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $user_id = absint($_POST['user_id'] ?? 0);
        if (!$user_id) {
            wp_send_json_error(['message' => __('Invalid user ID', 'formflow-pro')]);
        }

        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'ffp_sso_identity_links',
            ['user_id' => $user_id],
            ['%d']
        );

        // Also delete sessions
        $wpdb->delete(
            $wpdb->prefix . 'ffp_sso_sessions',
            ['user_id' => $user_id],
            ['%d']
        );

        $this->logInfo('Identity Unlinked', "User ID {$user_id} SSO identity unlinked by admin");

        wp_send_json_success(['message' => __('Identity unlinked successfully', 'formflow-pro')]);
    }

    /**
     * Render user SSO section on profile page
     */
    public function renderUserSSOSection(\WP_User $user): void
    {
        global $wpdb;

        $links = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ffp_sso_identity_links WHERE user_id = %d",
            $user->ID
        ));
        ?>
        <h2><?php esc_html_e('Single Sign-On', 'formflow-pro'); ?></h2>
        <table class="form-table">
            <tr>
                <th><?php esc_html_e('Linked Identities', 'formflow-pro'); ?></th>
                <td>
                    <?php if (empty($links)) : ?>
                        <p><?php esc_html_e('No SSO identities linked to this account.', 'formflow-pro'); ?></p>
                    <?php else : ?>
                        <ul>
                            <?php foreach ($links as $link) : ?>
                                <li>
                                    <strong><?php echo esc_html(strtoupper($link->provider_type)); ?></strong>
                                    (<?php echo esc_html($link->provider_id); ?>)
                                    - <?php echo esc_html($link->email); ?>
                                    <br />
                                    <small>
                                        <?php esc_html_e('Linked:', 'formflow-pro'); ?> <?php echo esc_html($link->linked_at); ?>
                                        | <?php esc_html_e('Last login:', 'formflow-pro'); ?> <?php echo esc_html($link->last_login ?: '-'); ?>
                                    </small>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Clean up expired sessions
     */
    public function cleanupExpiredSessions(): void
    {
        global $wpdb;

        $deleted = $wpdb->query(
            "DELETE FROM {$wpdb->prefix}ffp_sso_sessions WHERE expires_at < NOW()"
        );

        if ($deleted > 0) {
            $this->logInfo('Session Cleanup', "Deleted {$deleted} expired sessions");
        }
    }

    /**
     * Get client IP address
     */
    private function getClientIP(): string
    {
        $ip_keys = ['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Log info message
     */
    private function logInfo(string $event, string $message): void
    {
        $this->log('info', $event, $message);
    }

    /**
     * Log error message
     */
    private function logError(string $event, string $message): void
    {
        $this->log('error', $event, $message);
    }

    /**
     * Log message to database
     */
    private function log(string $level, string $event, string $message): void
    {
        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'ffp_sso_logs',
            [
                'level' => $level,
                'event' => $event,
                'message' => $message,
                'user_id' => get_current_user_id() ?: null,
                'ip_address' => $this->getClientIP(),
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'created_at' => current_time('mysql')
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s', '%s']
        );
    }

    /**
     * Create database tables
     */
    public static function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // SSO Sessions table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_sso_sessions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            provider_type VARCHAR(50) NOT NULL,
            provider_id VARCHAR(100) NOT NULL,
            session_id VARCHAR(100) NOT NULL,
            external_id VARCHAR(255) NOT NULL,
            attributes LONGTEXT,
            access_token TEXT,
            refresh_token TEXT,
            token_expires BIGINT UNSIGNED DEFAULT 0,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            created_at DATETIME NOT NULL,
            expires_at DATETIME NOT NULL,
            last_activity DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY user_id (user_id),
            KEY provider_type (provider_type),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        // SSO Identity Links table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_sso_identity_links (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            provider_type VARCHAR(50) NOT NULL,
            provider_id VARCHAR(100) NOT NULL,
            external_id VARCHAR(255) NOT NULL,
            email VARCHAR(255),
            profile_data LONGTEXT,
            is_primary TINYINT(1) DEFAULT 0,
            linked_at DATETIME NOT NULL,
            last_login DATETIME,
            PRIMARY KEY (id),
            UNIQUE KEY provider_external (provider_type, provider_id, external_id),
            KEY user_id (user_id),
            KEY email (email)
        ) {$charset_collate};";

        // SSO Logs table
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ffp_sso_logs (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            event VARCHAR(100) NOT NULL,
            message TEXT NOT NULL,
            user_id BIGINT UNSIGNED,
            ip_address VARCHAR(45),
            user_agent VARCHAR(500),
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY level (level),
            KEY event (event),
            KEY user_id (user_id),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Get settings
     */
    public function getSettings(): SSOSettings
    {
        return $this->settings;
    }

    /**
     * Get SAML provider
     */
    public function getSAMLProvider(): ?SAMLProvider
    {
        return $this->saml_provider;
    }

    /**
     * Get LDAP provider
     */
    public function getLDAPProvider(): ?LDAPProvider
    {
        return $this->ldap_provider;
    }

    /**
     * Get OAuth2 provider
     */
    public function getOAuth2Provider(): ?OAuth2EnterpriseProvider
    {
        return $this->oauth2_provider;
    }

    /**
     * AJAX: Get all SSO providers
     */
    public function ajaxGetProviders(): void
    {
        check_ajax_referer('ffp_sso_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $providers = [];

        // SAML Provider
        if ($this->saml_provider) {
            $providers['saml'] = [
                'type' => 'saml',
                'name' => 'SAML 2.0',
                'configured' => $this->saml_provider->isConfigured(),
                'enabled' => $this->settings->get('saml_enabled', false),
                'settings' => [
                    'idp_entity_id' => $this->settings->get('saml_idp_entity_id', ''),
                    'idp_sso_url' => $this->settings->get('saml_idp_sso_url', ''),
                    'idp_slo_url' => $this->settings->get('saml_idp_slo_url', ''),
                    'idp_certificate' => $this->settings->get('saml_idp_certificate', '') ? '[configured]' : '',
                    'sp_entity_id' => $this->settings->get('saml_sp_entity_id', home_url('/sso/saml/metadata')),
                ],
            ];
        }

        // LDAP Provider
        if ($this->ldap_provider) {
            $providers['ldap'] = [
                'type' => 'ldap',
                'name' => 'LDAP/Active Directory',
                'configured' => $this->ldap_provider->isConfigured(),
                'enabled' => $this->settings->get('ldap_enabled', false),
                'settings' => [
                    'host' => $this->settings->get('ldap_host', ''),
                    'port' => $this->settings->get('ldap_port', 389),
                    'base_dn' => $this->settings->get('ldap_base_dn', ''),
                    'bind_dn' => $this->settings->get('ldap_bind_dn', ''),
                    'use_tls' => $this->settings->get('ldap_use_tls', false),
                    'user_filter' => $this->settings->get('ldap_user_filter', '(sAMAccountName=%s)'),
                ],
            ];
        }

        // OAuth2 Enterprise Provider
        if ($this->oauth2_provider) {
            $providers['oauth2'] = [
                'type' => 'oauth2',
                'name' => 'OAuth2/OpenID Connect',
                'configured' => $this->oauth2_provider->isConfigured(),
                'enabled' => $this->settings->get('oauth2_enabled', false),
                'settings' => [
                    'provider_name' => $this->settings->get('oauth2_provider_name', ''),
                    'client_id' => $this->settings->get('oauth2_client_id', ''),
                    'client_secret' => $this->settings->get('oauth2_client_secret', '') ? '[configured]' : '',
                    'authorization_endpoint' => $this->settings->get('oauth2_authorization_endpoint', ''),
                    'token_endpoint' => $this->settings->get('oauth2_token_endpoint', ''),
                    'userinfo_endpoint' => $this->settings->get('oauth2_userinfo_endpoint', ''),
                    'scope' => $this->settings->get('oauth2_scope', 'openid profile email'),
                ],
            ];
        }

        wp_send_json_success([
            'providers' => $providers,
            'default_role' => $this->settings->get('default_role', 'subscriber'),
            'auto_create_users' => $this->settings->get('auto_create_users', true),
            'allow_password_login' => $this->settings->get('allow_password_login', true),
        ]);
    }

    /**
     * AJAX: Save SSO provider settings
     */
    public function ajaxSaveProvider(): void
    {
        check_ajax_referer('ffp_sso_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');
        $settings = $_POST['settings'] ?? [];

        if (!in_array($provider, ['saml', 'ldap', 'oauth2'], true)) {
            wp_send_json_error(['message' => __('Invalid provider type', 'formflow-pro')]);
        }

        try {
            switch ($provider) {
                case 'saml':
                    $this->saveSAMLSettings($settings);
                    break;
                case 'ldap':
                    $this->saveLDAPSettings($settings);
                    break;
                case 'oauth2':
                    $this->saveOAuth2Settings($settings);
                    break;
            }

            // Save enabled status
            $enabled = !empty($settings['enabled']);
            $this->settings->set("{$provider}_enabled", $enabled);

            $this->logInfo('Provider Settings Saved', "SSO provider {$provider} settings updated");

            wp_send_json_success([
                'message' => __('Settings saved successfully', 'formflow-pro'),
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    /**
     * Save SAML settings
     */
    private function saveSAMLSettings(array $settings): void
    {
        $this->settings->set('saml_idp_entity_id', sanitize_text_field($settings['idp_entity_id'] ?? ''));
        $this->settings->set('saml_idp_sso_url', esc_url_raw($settings['idp_sso_url'] ?? ''));
        $this->settings->set('saml_idp_slo_url', esc_url_raw($settings['idp_slo_url'] ?? ''));
        $this->settings->set('saml_sp_entity_id', sanitize_text_field($settings['sp_entity_id'] ?? ''));

        // Certificate - only update if provided
        if (!empty($settings['idp_certificate'])) {
            $this->settings->set('saml_idp_certificate', sanitize_textarea_field($settings['idp_certificate']));
        }

        // Attribute mappings
        if (isset($settings['attribute_mappings'])) {
            $this->settings->set('saml_attribute_mappings', array_map('sanitize_text_field', $settings['attribute_mappings']));
        }
    }

    /**
     * Save LDAP settings
     */
    private function saveLDAPSettings(array $settings): void
    {
        $this->settings->set('ldap_host', sanitize_text_field($settings['host'] ?? ''));
        $this->settings->set('ldap_port', absint($settings['port'] ?? 389));
        $this->settings->set('ldap_base_dn', sanitize_text_field($settings['base_dn'] ?? ''));
        $this->settings->set('ldap_bind_dn', sanitize_text_field($settings['bind_dn'] ?? ''));
        $this->settings->set('ldap_use_tls', !empty($settings['use_tls']));
        $this->settings->set('ldap_user_filter', sanitize_text_field($settings['user_filter'] ?? '(sAMAccountName=%s)'));

        // Password - only update if provided
        if (!empty($settings['bind_password'])) {
            $this->settings->set('ldap_bind_password', $settings['bind_password']);
        }

        // Attribute mappings
        if (isset($settings['attribute_mappings'])) {
            $this->settings->set('ldap_attribute_mappings', array_map('sanitize_text_field', $settings['attribute_mappings']));
        }
    }

    /**
     * Save OAuth2 settings
     */
    private function saveOAuth2Settings(array $settings): void
    {
        $this->settings->set('oauth2_provider_name', sanitize_text_field($settings['provider_name'] ?? ''));
        $this->settings->set('oauth2_client_id', sanitize_text_field($settings['client_id'] ?? ''));
        $this->settings->set('oauth2_authorization_endpoint', esc_url_raw($settings['authorization_endpoint'] ?? ''));
        $this->settings->set('oauth2_token_endpoint', esc_url_raw($settings['token_endpoint'] ?? ''));
        $this->settings->set('oauth2_userinfo_endpoint', esc_url_raw($settings['userinfo_endpoint'] ?? ''));
        $this->settings->set('oauth2_scope', sanitize_text_field($settings['scope'] ?? 'openid profile email'));

        // Client secret - only update if provided
        if (!empty($settings['client_secret'])) {
            $this->settings->set('oauth2_client_secret', $settings['client_secret']);
        }

        // Claim mappings
        if (isset($settings['claim_mappings'])) {
            $this->settings->set('oauth2_claim_mappings', array_map('sanitize_text_field', $settings['claim_mappings']));
        }
    }

    /**
     * AJAX: Delete/disable SSO provider
     */
    public function ajaxDeleteProvider(): void
    {
        check_ajax_referer('ffp_sso_admin', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $provider = sanitize_text_field($_POST['provider'] ?? '');

        if (!in_array($provider, ['saml', 'ldap', 'oauth2'], true)) {
            wp_send_json_error(['message' => __('Invalid provider type', 'formflow-pro')]);
        }

        // Disable the provider
        $this->settings->set("{$provider}_enabled", false);

        // Clear provider-specific settings based on type
        $settingsToDelete = [];

        switch ($provider) {
            case 'saml':
                $settingsToDelete = [
                    'saml_idp_entity_id',
                    'saml_idp_sso_url',
                    'saml_idp_slo_url',
                    'saml_idp_certificate',
                    'saml_sp_entity_id',
                    'saml_attribute_mappings',
                ];
                break;

            case 'ldap':
                $settingsToDelete = [
                    'ldap_host',
                    'ldap_port',
                    'ldap_base_dn',
                    'ldap_bind_dn',
                    'ldap_bind_password',
                    'ldap_use_tls',
                    'ldap_user_filter',
                    'ldap_attribute_mappings',
                ];
                break;

            case 'oauth2':
                $settingsToDelete = [
                    'oauth2_provider_name',
                    'oauth2_client_id',
                    'oauth2_client_secret',
                    'oauth2_authorization_endpoint',
                    'oauth2_token_endpoint',
                    'oauth2_userinfo_endpoint',
                    'oauth2_scope',
                    'oauth2_claim_mappings',
                ];
                break;
        }

        foreach ($settingsToDelete as $setting) {
            $this->settings->delete($setting);
        }

        $this->logInfo('Provider Deleted', "SSO provider {$provider} configuration deleted");

        wp_send_json_success([
            'message' => sprintf(__('%s provider configuration deleted', 'formflow-pro'), strtoupper($provider)),
        ]);
    }
}
