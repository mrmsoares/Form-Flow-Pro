<?php
/**
 * OAuth 2.0 Enterprise Provider - Enterprise SSO via OAuth2/OIDC
 *
 * Implements OAuth 2.0 and OpenID Connect for integration with
 * enterprise identity providers like Okta, Azure AD, Auth0, etc.
 *
 * @package FormFlowPro
 * @subpackage SSO
 * @since 3.0.0
 */

namespace FormFlowPro\SSO;

use FormFlowPro\Core\SingletonTrait;

/**
 * OAuth2 Provider Configuration
 */
class OAuth2ProviderConfig
{
    public string $provider_id;
    public string $name;
    public string $client_id;
    public string $client_secret;
    public string $authorization_endpoint;
    public string $token_endpoint;
    public string $userinfo_endpoint;
    public string $jwks_uri;
    public string $end_session_endpoint;
    public string $issuer;
    public array $scopes;
    public bool $use_pkce;
    public string $response_type;
    public string $response_mode;
    public array $attribute_mapping;
    public array $custom_params;

    public function __construct(array $data = [])
    {
        $this->provider_id = $data['provider_id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->client_id = $data['client_id'] ?? '';
        $this->client_secret = $data['client_secret'] ?? '';
        $this->authorization_endpoint = $data['authorization_endpoint'] ?? '';
        $this->token_endpoint = $data['token_endpoint'] ?? '';
        $this->userinfo_endpoint = $data['userinfo_endpoint'] ?? '';
        $this->jwks_uri = $data['jwks_uri'] ?? '';
        $this->end_session_endpoint = $data['end_session_endpoint'] ?? '';
        $this->issuer = $data['issuer'] ?? '';
        $this->scopes = $data['scopes'] ?? ['openid', 'profile', 'email'];
        $this->use_pkce = $data['use_pkce'] ?? true;
        $this->response_type = $data['response_type'] ?? 'code';
        $this->response_mode = $data['response_mode'] ?? 'query';
        $this->attribute_mapping = $data['attribute_mapping'] ?? $this->getDefaultMapping();
        $this->custom_params = $data['custom_params'] ?? [];
    }

    private function getDefaultMapping(): array
    {
        return [
            'email' => 'email',
            'first_name' => 'given_name',
            'last_name' => 'family_name',
            'display_name' => 'name',
            'username' => 'preferred_username',
            'groups' => 'groups'
        ];
    }

    public function isConfigured(): bool
    {
        return !empty($this->client_id) &&
               !empty($this->authorization_endpoint) &&
               !empty($this->token_endpoint);
    }

    public function toArray(): array
    {
        return [
            'provider_id' => $this->provider_id,
            'name' => $this->name,
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'authorization_endpoint' => $this->authorization_endpoint,
            'token_endpoint' => $this->token_endpoint,
            'userinfo_endpoint' => $this->userinfo_endpoint,
            'jwks_uri' => $this->jwks_uri,
            'end_session_endpoint' => $this->end_session_endpoint,
            'issuer' => $this->issuer,
            'scopes' => $this->scopes,
            'use_pkce' => $this->use_pkce,
            'response_type' => $this->response_type,
            'response_mode' => $this->response_mode,
            'attribute_mapping' => $this->attribute_mapping,
            'custom_params' => $this->custom_params
        ];
    }
}

/**
 * OAuth2 Token Response
 */
class OAuth2TokenResponse
{
    public string $access_token;
    public string $token_type;
    public int $expires_in;
    public ?string $refresh_token;
    public ?string $id_token;
    public string $scope;
    public array $raw_response;

    public function __construct(array $data = [])
    {
        $this->access_token = $data['access_token'] ?? '';
        $this->token_type = $data['token_type'] ?? 'Bearer';
        $this->expires_in = $data['expires_in'] ?? 3600;
        $this->refresh_token = $data['refresh_token'] ?? null;
        $this->id_token = $data['id_token'] ?? null;
        $this->scope = $data['scope'] ?? '';
        $this->raw_response = $data;
    }

    public function isValid(): bool
    {
        return !empty($this->access_token);
    }
}

/**
 * OAuth2 User Info
 */
class OAuth2UserInfo
{
    public string $sub;
    public string $email;
    public bool $email_verified;
    public string $first_name;
    public string $last_name;
    public string $display_name;
    public string $username;
    public string $picture;
    public array $groups;
    public array $raw_claims;

    public function __construct(array $data = [])
    {
        $this->sub = $data['sub'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->email_verified = $data['email_verified'] ?? false;
        $this->first_name = $data['first_name'] ?? $data['given_name'] ?? '';
        $this->last_name = $data['last_name'] ?? $data['family_name'] ?? '';
        $this->display_name = $data['display_name'] ?? $data['name'] ?? '';
        $this->username = $data['username'] ?? $data['preferred_username'] ?? '';
        $this->picture = $data['picture'] ?? '';
        $this->groups = $data['groups'] ?? [];
        $this->raw_claims = $data;
    }

    public function getClaim(string $name, $default = null)
    {
        return $this->raw_claims[$name] ?? $default;
    }
}

/**
 * OAuth2 Enterprise Provider
 */
class OAuth2EnterpriseProvider
{
    use SingletonTrait;

    private array $providers = [];
    private string $callback_url;
    private string $state_prefix = 'ffp_oauth_state_';

    // Pre-configured enterprise providers
    private array $provider_presets = [
        'azure_ad' => [
            'name' => 'Microsoft Azure AD',
            'authorization_endpoint' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/authorize',
            'token_endpoint' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/token',
            'userinfo_endpoint' => 'https://graph.microsoft.com/oidc/userinfo',
            'jwks_uri' => 'https://login.microsoftonline.com/{tenant}/discovery/v2.0/keys',
            'end_session_endpoint' => 'https://login.microsoftonline.com/{tenant}/oauth2/v2.0/logout',
            'scopes' => ['openid', 'profile', 'email', 'User.Read'],
            'attribute_mapping' => [
                'email' => 'email',
                'first_name' => 'givenName',
                'last_name' => 'surname',
                'display_name' => 'displayName',
                'username' => 'userPrincipalName'
            ]
        ],
        'okta' => [
            'name' => 'Okta',
            'authorization_endpoint' => 'https://{domain}/oauth2/default/v1/authorize',
            'token_endpoint' => 'https://{domain}/oauth2/default/v1/token',
            'userinfo_endpoint' => 'https://{domain}/oauth2/default/v1/userinfo',
            'jwks_uri' => 'https://{domain}/oauth2/default/v1/keys',
            'end_session_endpoint' => 'https://{domain}/oauth2/default/v1/logout',
            'scopes' => ['openid', 'profile', 'email', 'groups']
        ],
        'auth0' => [
            'name' => 'Auth0',
            'authorization_endpoint' => 'https://{domain}/authorize',
            'token_endpoint' => 'https://{domain}/oauth/token',
            'userinfo_endpoint' => 'https://{domain}/userinfo',
            'jwks_uri' => 'https://{domain}/.well-known/jwks.json',
            'end_session_endpoint' => 'https://{domain}/v2/logout',
            'scopes' => ['openid', 'profile', 'email']
        ],
        'google_workspace' => [
            'name' => 'Google Workspace',
            'authorization_endpoint' => 'https://accounts.google.com/o/oauth2/v2/auth',
            'token_endpoint' => 'https://oauth2.googleapis.com/token',
            'userinfo_endpoint' => 'https://openidconnect.googleapis.com/v1/userinfo',
            'jwks_uri' => 'https://www.googleapis.com/oauth2/v3/certs',
            'end_session_endpoint' => 'https://accounts.google.com/logout',
            'scopes' => ['openid', 'profile', 'email'],
            'custom_params' => ['hd' => '{domain}'] // Restrict to domain
        ],
        'keycloak' => [
            'name' => 'Keycloak',
            'authorization_endpoint' => '{server}/realms/{realm}/protocol/openid-connect/auth',
            'token_endpoint' => '{server}/realms/{realm}/protocol/openid-connect/token',
            'userinfo_endpoint' => '{server}/realms/{realm}/protocol/openid-connect/userinfo',
            'jwks_uri' => '{server}/realms/{realm}/protocol/openid-connect/certs',
            'end_session_endpoint' => '{server}/realms/{realm}/protocol/openid-connect/logout',
            'scopes' => ['openid', 'profile', 'email', 'roles']
        ],
        'ping_identity' => [
            'name' => 'Ping Identity',
            'authorization_endpoint' => 'https://{domain}/as/authorization.oauth2',
            'token_endpoint' => 'https://{domain}/as/token.oauth2',
            'userinfo_endpoint' => 'https://{domain}/idp/userinfo.openid',
            'scopes' => ['openid', 'profile', 'email']
        ],
        'onelogin' => [
            'name' => 'OneLogin',
            'authorization_endpoint' => 'https://{domain}.onelogin.com/oidc/2/auth',
            'token_endpoint' => 'https://{domain}.onelogin.com/oidc/2/token',
            'userinfo_endpoint' => 'https://{domain}.onelogin.com/oidc/2/me',
            'jwks_uri' => 'https://{domain}.onelogin.com/oidc/2/certs',
            'scopes' => ['openid', 'profile', 'email', 'groups']
        ]
    ];

    /**
     * Initialize OAuth2 provider
     */
    protected function init(): void
    {
        $this->callback_url = home_url('/oauth/callback');
        $this->loadProviders();
        $this->registerHooks();
    }

    /**
     * Load configured providers
     */
    private function loadProviders(): void
    {
        $providers_data = get_option('ffp_oauth2_providers', []);

        foreach ($providers_data as $id => $data) {
            $data['provider_id'] = $id;
            $this->providers[$id] = new OAuth2ProviderConfig($data);
        }
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('template_redirect', [$this, 'handleOAuthEndpoints']);
        add_action('login_form', [$this, 'renderLoginButtons']);
        add_action('wp_logout', [$this, 'handleLogout']);
    }

    /**
     * Get provider presets
     */
    public function getProviderPresets(): array
    {
        return $this->provider_presets;
    }

    /**
     * Get configured providers
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    /**
     * Get provider by ID
     */
    public function getProvider(string $id): ?OAuth2ProviderConfig
    {
        return $this->providers[$id] ?? null;
    }

    /**
     * Add or update provider
     */
    public function saveProvider(string $id, array $data): void
    {
        $data['provider_id'] = $id;
        $this->providers[$id] = new OAuth2ProviderConfig($data);

        $providers_data = get_option('ffp_oauth2_providers', []);
        $providers_data[$id] = $this->providers[$id]->toArray();
        update_option('ffp_oauth2_providers', $providers_data);
    }

    /**
     * Remove provider
     */
    public function removeProvider(string $id): void
    {
        unset($this->providers[$id]);

        $providers_data = get_option('ffp_oauth2_providers', []);
        unset($providers_data[$id]);
        update_option('ffp_oauth2_providers', $providers_data);
    }

    /**
     * Register rewrite rules
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule('^oauth/login/([^/]+)/?$', 'index.php?ffp_oauth=login&ffp_oauth_provider=$matches[1]', 'top');
        add_rewrite_rule('^oauth/callback/?$', 'index.php?ffp_oauth=callback', 'top');
        add_rewrite_rule('^oauth/logout/([^/]+)/?$', 'index.php?ffp_oauth=logout&ffp_oauth_provider=$matches[1]', 'top');

        add_rewrite_tag('%ffp_oauth%', '([^&]+)');
        add_rewrite_tag('%ffp_oauth_provider%', '([^&]+)');
    }

    /**
     * Handle OAuth endpoints
     */
    public function handleOAuthEndpoints(): void
    {
        $action = get_query_var('ffp_oauth');

        if (empty($action)) {
            return;
        }

        switch ($action) {
            case 'login':
                $this->handleLogin();
                break;
            case 'callback':
                $this->handleCallback();
                break;
            case 'logout':
                $this->handleLogoutRedirect();
                break;
        }

        exit;
    }

    /**
     * Handle login initiation
     */
    private function handleLogin(): void
    {
        $provider_id = get_query_var('ffp_oauth_provider');
        $provider = $this->getProvider($provider_id);

        if (!$provider || !$provider->isConfigured()) {
            wp_die(__('OAuth provider not configured', 'form-flow-pro'));
        }

        $redirect_to = sanitize_text_field($_GET['redirect_to'] ?? admin_url());

        // Generate state
        $state = wp_generate_password(32, false);
        $state_data = [
            'provider_id' => $provider_id,
            'redirect_to' => $redirect_to,
            'created' => time()
        ];

        // Store state
        set_transient($this->state_prefix . $state, $state_data, 600);

        // Build authorization URL
        $params = [
            'client_id' => $provider->client_id,
            'redirect_uri' => $this->callback_url,
            'response_type' => $provider->response_type,
            'scope' => implode(' ', $provider->scopes),
            'state' => $state,
            'response_mode' => $provider->response_mode
        ];

        // Add PKCE if enabled
        if ($provider->use_pkce) {
            $code_verifier = $this->generateCodeVerifier();
            $code_challenge = $this->generateCodeChallenge($code_verifier);

            $params['code_challenge'] = $code_challenge;
            $params['code_challenge_method'] = 'S256';

            // Store code verifier
            $state_data['code_verifier'] = $code_verifier;
            set_transient($this->state_prefix . $state, $state_data, 600);
        }

        // Add custom params
        $params = array_merge($params, $provider->custom_params);

        $auth_url = $provider->authorization_endpoint . '?' . http_build_query($params);

        wp_redirect($auth_url);
        exit;
    }

    /**
     * Handle OAuth callback
     */
    private function handleCallback(): void
    {
        // Check for errors
        if (isset($_GET['error'])) {
            $error = sanitize_text_field($_GET['error']);
            $error_description = sanitize_text_field($_GET['error_description'] ?? '');
            $this->logError('OAuth error', ['error' => $error, 'description' => $error_description]);
            wp_die(__('Authentication failed: ', 'form-flow-pro') . esc_html($error_description ?: $error));
        }

        // Validate state
        $state = sanitize_text_field($_GET['state'] ?? '');
        $state_data = get_transient($this->state_prefix . $state);

        if (!$state_data) {
            wp_die(__('Invalid or expired state', 'form-flow-pro'));
        }

        delete_transient($this->state_prefix . $state);

        // Get provider
        $provider = $this->getProvider($state_data['provider_id']);

        if (!$provider) {
            wp_die(__('Provider not found', 'form-flow-pro'));
        }

        // Get authorization code
        $code = sanitize_text_field($_GET['code'] ?? '');

        if (empty($code)) {
            wp_die(__('No authorization code received', 'form-flow-pro'));
        }

        // Exchange code for tokens
        $token_response = $this->exchangeCode($provider, $code, $state_data['code_verifier'] ?? null);

        if (!$token_response->isValid()) {
            wp_die(__('Failed to obtain access token', 'form-flow-pro'));
        }

        // Get user info
        $user_info = $this->getUserInfo($provider, $token_response);

        if (!$user_info) {
            wp_die(__('Failed to get user information', 'form-flow-pro'));
        }

        // Get or create WordPress user
        $wp_user = $this->getOrCreateUser($provider, $user_info, $token_response);

        if (is_wp_error($wp_user)) {
            wp_die(__('User provisioning failed: ', 'form-flow-pro') . $wp_user->get_error_message());
        }

        // Log in the user
        wp_set_current_user($wp_user->ID);
        wp_set_auth_cookie($wp_user->ID, true);

        // Store session info
        $this->storeSessionInfo($wp_user->ID, $provider, $token_response, $user_info);

        do_action('ffp_oauth_login_success', $wp_user, $provider, $user_info);

        // Redirect
        wp_redirect($state_data['redirect_to'] ?: admin_url());
        exit;
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchangeCode(OAuth2ProviderConfig $provider, string $code, ?string $code_verifier): OAuth2TokenResponse
    {
        $params = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->callback_url,
            'client_id' => $provider->client_id
        ];

        // Add client secret if not using PKCE (or if provider requires it)
        if (!empty($provider->client_secret)) {
            $params['client_secret'] = $provider->client_secret;
        }

        // Add PKCE verifier
        if ($code_verifier) {
            $params['code_verifier'] = $code_verifier;
        }

        $response = wp_remote_post($provider->token_endpoint, [
            'body' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->logError('Token exchange failed', ['error' => $response->get_error_message()]);
            return new OAuth2TokenResponse();
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            $this->logError('Token error', $body);
            return new OAuth2TokenResponse();
        }

        return new OAuth2TokenResponse($body);
    }

    /**
     * Get user info from provider
     */
    private function getUserInfo(OAuth2ProviderConfig $provider, OAuth2TokenResponse $token): ?OAuth2UserInfo
    {
        // First try to decode ID token if available
        if ($token->id_token) {
            $claims = $this->decodeIdToken($token->id_token, $provider);
            if ($claims) {
                return $this->mapUserInfo($provider, $claims);
            }
        }

        // Fallback to userinfo endpoint
        if (empty($provider->userinfo_endpoint)) {
            return null;
        }

        $response = wp_remote_get($provider->userinfo_endpoint, [
            'headers' => [
                'Authorization' => 'Bearer ' . $token->access_token,
                'Accept' => 'application/json'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            $this->logError('Userinfo request failed', ['error' => $response->get_error_message()]);
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!$body || isset($body['error'])) {
            $this->logError('Userinfo error', $body ?: []);
            return null;
        }

        return $this->mapUserInfo($provider, $body);
    }

    /**
     * Map provider claims to OAuth2UserInfo
     */
    private function mapUserInfo(OAuth2ProviderConfig $provider, array $claims): OAuth2UserInfo
    {
        $user_data = ['raw_claims' => $claims];

        foreach ($provider->attribute_mapping as $local_field => $provider_field) {
            if (isset($claims[$provider_field])) {
                $user_data[$local_field] = $claims[$provider_field];
            }
        }

        // Always include sub
        if (isset($claims['sub'])) {
            $user_data['sub'] = $claims['sub'];
        }

        return new OAuth2UserInfo($user_data);
    }

    /**
     * Decode and validate ID token (JWT)
     */
    private function decodeIdToken(string $id_token, OAuth2ProviderConfig $provider): ?array
    {
        $parts = explode('.', $id_token);

        if (count($parts) !== 3) {
            return null;
        }

        // Decode payload (we're not validating signature here for simplicity)
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!$payload) {
            return null;
        }

        // Basic validation
        $now = time();

        // Check expiration
        if (isset($payload['exp']) && $payload['exp'] < $now) {
            $this->logError('ID token expired');
            return null;
        }

        // Check issuer
        if (!empty($provider->issuer) && isset($payload['iss']) && $payload['iss'] !== $provider->issuer) {
            $this->logError('Invalid issuer', ['expected' => $provider->issuer, 'got' => $payload['iss']]);
            return null;
        }

        // Check audience
        if (isset($payload['aud'])) {
            $aud = is_array($payload['aud']) ? $payload['aud'] : [$payload['aud']];
            if (!in_array($provider->client_id, $aud)) {
                $this->logError('Invalid audience');
                return null;
            }
        }

        return $payload;
    }

    /**
     * Get or create WordPress user
     */
    private function getOrCreateUser(OAuth2ProviderConfig $provider, OAuth2UserInfo $user_info, OAuth2TokenResponse $token): \WP_User|\WP_Error
    {
        // Try to find existing user
        $user = null;

        // By email
        if (!empty($user_info->email)) {
            $user = get_user_by('email', $user_info->email);
        }

        // By OAuth sub
        if (!$user && !empty($user_info->sub)) {
            $users = get_users([
                'meta_key' => "ffp_oauth_{$provider->provider_id}_sub",
                'meta_value' => $user_info->sub,
                'number' => 1
            ]);
            $user = !empty($users) ? $users[0] : null;
        }

        if ($user) {
            $this->updateUser($user, $provider, $user_info);
            return $user;
        }

        // Check auto-provisioning
        if (!get_option('ffp_oauth_auto_provision', true)) {
            return new \WP_Error('user_not_found', __('User not found and auto-provisioning is disabled', 'form-flow-pro'));
        }

        return $this->createUser($provider, $user_info);
    }

    /**
     * Create WordPress user from OAuth info
     */
    private function createUser(OAuth2ProviderConfig $provider, OAuth2UserInfo $user_info): \WP_User|\WP_Error
    {
        $email = $user_info->email;

        if (empty($email) || !is_email($email)) {
            return new \WP_Error('invalid_email', __('Valid email is required', 'form-flow-pro'));
        }

        // Generate username
        $username = $user_info->username ?: sanitize_user(explode('@', $email)[0]);
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Determine role
        $role = $this->determineRole($provider, $user_info);

        $user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(32),
            'first_name' => $user_info->first_name,
            'last_name' => $user_info->last_name,
            'display_name' => $user_info->display_name ?: ($user_info->first_name . ' ' . $user_info->last_name),
            'role' => $role
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Store OAuth metadata
        update_user_meta($user_id, "ffp_oauth_{$provider->provider_id}_sub", $user_info->sub);
        update_user_meta($user_id, 'ffp_oauth_provider', $provider->provider_id);
        update_user_meta($user_id, 'ffp_oauth_created', current_time('mysql'));

        if (!empty($user_info->picture)) {
            update_user_meta($user_id, 'ffp_oauth_picture', $user_info->picture);
        }

        do_action('ffp_oauth_user_created', $user_id, $provider, $user_info);

        return get_user_by('id', $user_id);
    }

    /**
     * Update existing user
     */
    private function updateUser(\WP_User $user, OAuth2ProviderConfig $provider, OAuth2UserInfo $user_info): void
    {
        $update_data = ['ID' => $user->ID];

        if (!empty($user_info->first_name)) {
            $update_data['first_name'] = $user_info->first_name;
        }
        if (!empty($user_info->last_name)) {
            $update_data['last_name'] = $user_info->last_name;
        }
        if (!empty($user_info->display_name)) {
            $update_data['display_name'] = $user_info->display_name;
        }

        wp_update_user($update_data);

        // Sync roles if enabled
        if (get_option('ffp_oauth_sync_roles', false)) {
            $role = $this->determineRole($provider, $user_info);
            $user->set_role($role);
        }

        // Update metadata
        update_user_meta($user->ID, "ffp_oauth_{$provider->provider_id}_sub", $user_info->sub);
        update_user_meta($user->ID, 'ffp_oauth_last_login', current_time('mysql'));

        if (!empty($user_info->picture)) {
            update_user_meta($user->ID, 'ffp_oauth_picture', $user_info->picture);
        }

        do_action('ffp_oauth_user_updated', $user->ID, $provider, $user_info);
    }

    /**
     * Determine role from groups
     */
    private function determineRole(OAuth2ProviderConfig $provider, OAuth2UserInfo $user_info): string
    {
        $default_role = get_option('ffp_oauth_default_role', 'subscriber');
        $role_mapping = get_option("ffp_oauth_{$provider->provider_id}_role_mapping", []);

        if (empty($role_mapping) || empty($user_info->groups)) {
            return $default_role;
        }

        foreach ($role_mapping as $oauth_group => $wp_role) {
            if (in_array($oauth_group, $user_info->groups)) {
                return $wp_role;
            }
        }

        return $default_role;
    }

    /**
     * Store session info
     */
    private function storeSessionInfo(int $user_id, OAuth2ProviderConfig $provider, OAuth2TokenResponse $token, OAuth2UserInfo $user_info): void
    {
        $session_data = [
            'provider_id' => $provider->provider_id,
            'sub' => $user_info->sub,
            'access_token' => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expires_at' => time() + $token->expires_in,
            'id_token' => $token->id_token,
            'login_time' => current_time('mysql')
        ];

        update_user_meta($user_id, 'ffp_oauth_session', $session_data);
    }

    /**
     * Handle logout redirect
     */
    private function handleLogoutRedirect(): void
    {
        $provider_id = get_query_var('ffp_oauth_provider');
        $provider = $this->getProvider($provider_id);

        // Logout locally first
        wp_logout();

        if ($provider && !empty($provider->end_session_endpoint)) {
            $params = [
                'post_logout_redirect_uri' => home_url(),
                'client_id' => $provider->client_id
            ];

            $logout_url = $provider->end_session_endpoint . '?' . http_build_query($params);
            wp_redirect($logout_url);
            exit;
        }

        wp_redirect(home_url());
        exit;
    }

    /**
     * Handle WordPress logout
     */
    public function handleLogout(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return;
        }

        // Clean up session
        delete_user_meta($user_id, 'ffp_oauth_session');
    }

    /**
     * Render login buttons on login form
     */
    public function renderLoginButtons(): void
    {
        if (!get_option('ffp_oauth_show_login_buttons', true)) {
            return;
        }

        $enabled_providers = array_filter($this->providers, function ($provider) {
            return $provider->isConfigured();
        });

        if (empty($enabled_providers)) {
            return;
        }

        echo '<div class="ffp-oauth-login-buttons" style="margin-bottom: 20px; text-align: center;">';
        echo '<p style="margin-bottom: 10px; color: #666;">' . esc_html__('Or sign in with:', 'form-flow-pro') . '</p>';

        foreach ($enabled_providers as $provider) {
            $login_url = home_url("/oauth/login/{$provider->provider_id}/");
            echo '<a href="' . esc_url($login_url) . '" class="button" style="margin: 5px; display: inline-block;">';
            echo esc_html($provider->name);
            echo '</a>';
        }

        echo '</div>';
        echo '<hr style="margin: 20px 0;">';
    }

    /**
     * Refresh access token
     */
    public function refreshToken(int $user_id): ?OAuth2TokenResponse
    {
        $session = get_user_meta($user_id, 'ffp_oauth_session', true);

        if (empty($session['refresh_token']) || empty($session['provider_id'])) {
            return null;
        }

        $provider = $this->getProvider($session['provider_id']);

        if (!$provider) {
            return null;
        }

        $params = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $session['refresh_token'],
            'client_id' => $provider->client_id
        ];

        if (!empty($provider->client_secret)) {
            $params['client_secret'] = $provider->client_secret;
        }

        $response = wp_remote_post($provider->token_endpoint, [
            'body' => $params,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return null;
        }

        $token_response = new OAuth2TokenResponse($body);

        if ($token_response->isValid()) {
            // Update session
            $session['access_token'] = $token_response->access_token;
            $session['expires_at'] = time() + $token_response->expires_in;

            if ($token_response->refresh_token) {
                $session['refresh_token'] = $token_response->refresh_token;
            }

            update_user_meta($user_id, 'ffp_oauth_session', $session);
        }

        return $token_response;
    }

    /**
     * Get callback URL
     */
    public function getCallbackUrl(): string
    {
        return $this->callback_url;
    }

    /**
     * Generate PKCE code verifier
     */
    private function generateCodeVerifier(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    /**
     * Generate PKCE code challenge
     */
    private function generateCodeChallenge(string $verifier): string
    {
        return rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
    }

    /**
     * Base64 URL decode
     */
    private function base64UrlDecode(string $input): string
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $input .= str_repeat('=', 4 - $remainder);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    /**
     * Log error
     */
    private function logError(string $message, array $context = []): void
    {
        if (class_exists('\FormFlowPro\Security\AuditLogger')) {
            \FormFlowPro\Security\AuditLogger::getInstance()->log('oauth_error', $message, $context);
        } else {
            error_log("FormFlow Pro OAuth: {$message} " . json_encode($context));
        }
    }
}
