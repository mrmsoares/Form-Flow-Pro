<?php
/**
 * SAML 2.0 Provider - Enterprise Single Sign-On via SAML
 *
 * Implements SAML 2.0 Service Provider (SP) for integration
 * with enterprise Identity Providers (IdP) like Okta, Azure AD, etc.
 *
 * @package FormFlowPro
 * @subpackage SSO
 * @since 3.0.0
 */

namespace FormFlowPro\SSO;

use FormFlowPro\Core\SingletonTrait;

/**
 * SAML Configuration model
 */
class SAMLConfig
{
    public string $entity_id;
    public string $acs_url;
    public string $slo_url;
    public string $idp_entity_id;
    public string $idp_sso_url;
    public string $idp_slo_url;
    public string $idp_certificate;
    public string $sp_private_key;
    public string $sp_certificate;
    public string $name_id_format;
    public bool $sign_requests;
    public bool $want_assertions_signed;
    public bool $want_assertions_encrypted;
    public array $attribute_mapping;

    public function __construct(array $data = [])
    {
        $this->entity_id = $data['entity_id'] ?? home_url('/saml/metadata');
        $this->acs_url = $data['acs_url'] ?? home_url('/saml/acs');
        $this->slo_url = $data['slo_url'] ?? home_url('/saml/slo');
        $this->idp_entity_id = $data['idp_entity_id'] ?? '';
        $this->idp_sso_url = $data['idp_sso_url'] ?? '';
        $this->idp_slo_url = $data['idp_slo_url'] ?? '';
        $this->idp_certificate = $data['idp_certificate'] ?? '';
        $this->sp_private_key = $data['sp_private_key'] ?? '';
        $this->sp_certificate = $data['sp_certificate'] ?? '';
        $this->name_id_format = $data['name_id_format'] ?? 'urn:oasis:names:tc:SAML:1.1:nameid-format:emailAddress';
        $this->sign_requests = $data['sign_requests'] ?? true;
        $this->want_assertions_signed = $data['want_assertions_signed'] ?? true;
        $this->want_assertions_encrypted = $data['want_assertions_encrypted'] ?? false;
        $this->attribute_mapping = $data['attribute_mapping'] ?? $this->getDefaultMapping();
    }

    private function getDefaultMapping(): array
    {
        return [
            'email' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress',
                'urn:oid:0.9.2342.19200300.100.1.3',
                'email',
                'mail'
            ],
            'first_name' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/givenname',
                'urn:oid:2.5.4.42',
                'firstName',
                'givenName'
            ],
            'last_name' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/surname',
                'urn:oid:2.5.4.4',
                'lastName',
                'sn'
            ],
            'display_name' => [
                'http://schemas.xmlsoap.org/ws/2005/05/identity/claims/name',
                'urn:oid:2.16.840.1.113730.3.1.241',
                'displayName'
            ],
            'groups' => [
                'http://schemas.microsoft.com/ws/2008/06/identity/claims/groups',
                'memberOf',
                'groups'
            ]
        ];
    }

    public function toArray(): array
    {
        return [
            'entity_id' => $this->entity_id,
            'acs_url' => $this->acs_url,
            'slo_url' => $this->slo_url,
            'idp_entity_id' => $this->idp_entity_id,
            'idp_sso_url' => $this->idp_sso_url,
            'idp_slo_url' => $this->idp_slo_url,
            'idp_certificate' => $this->idp_certificate,
            'sp_private_key' => $this->sp_private_key,
            'sp_certificate' => $this->sp_certificate,
            'name_id_format' => $this->name_id_format,
            'sign_requests' => $this->sign_requests,
            'want_assertions_signed' => $this->want_assertions_signed,
            'want_assertions_encrypted' => $this->want_assertions_encrypted,
            'attribute_mapping' => $this->attribute_mapping
        ];
    }

    public function isConfigured(): bool
    {
        return !empty($this->idp_entity_id) &&
               !empty($this->idp_sso_url) &&
               !empty($this->idp_certificate);
    }
}

/**
 * SAML Response model
 */
class SAMLResponse
{
    public bool $valid;
    public ?string $name_id;
    public ?string $session_index;
    public array $attributes;
    public ?string $error;
    public array $raw_response;

    public function __construct(array $data = [])
    {
        $this->valid = $data['valid'] ?? false;
        $this->name_id = $data['name_id'] ?? null;
        $this->session_index = $data['session_index'] ?? null;
        $this->attributes = $data['attributes'] ?? [];
        $this->error = $data['error'] ?? null;
        $this->raw_response = $data['raw_response'] ?? [];
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->attributes[$name] ?? $default;
    }

    public function getAttributeValue(string $name, $default = null)
    {
        $attr = $this->getAttribute($name);
        if (is_array($attr)) {
            return $attr[0] ?? $default;
        }
        return $attr ?? $default;
    }
}

/**
 * SAML 2.0 Provider
 */
class SAMLProvider
{
    use SingletonTrait;

    private ?SAMLConfig $config = null;
    private string $session_prefix = 'ffp_saml_';

    /**
     * Initialize SAML provider
     */
    protected function init(): void
    {
        $this->loadConfig();
        $this->registerHooks();
    }

    /**
     * Load configuration from database
     */
    private function loadConfig(): void
    {
        $data = get_option('ffp_saml_config', []);
        $this->config = new SAMLConfig($data);
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('template_redirect', [$this, 'handleSAMLEndpoints']);
        add_action('wp_logout', [$this, 'handleLogout']);

        add_filter('authenticate', [$this, 'authenticateViaSSO'], 30, 3);
        add_filter('login_url', [$this, 'modifyLoginUrl'], 10, 3);
    }

    /**
     * Get configuration
     */
    public function getConfig(): SAMLConfig
    {
        return $this->config;
    }

    /**
     * Save configuration
     */
    public function saveConfig(array $data): void
    {
        $this->config = new SAMLConfig($data);
        update_option('ffp_saml_config', $this->config->toArray());
    }

    /**
     * Register rewrite rules
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule('^saml/metadata/?$', 'index.php?ffp_saml=metadata', 'top');
        add_rewrite_rule('^saml/login/?$', 'index.php?ffp_saml=login', 'top');
        add_rewrite_rule('^saml/acs/?$', 'index.php?ffp_saml=acs', 'top');
        add_rewrite_rule('^saml/slo/?$', 'index.php?ffp_saml=slo', 'top');

        add_rewrite_tag('%ffp_saml%', '([^&]+)');
    }

    /**
     * Handle SAML endpoints
     */
    public function handleSAMLEndpoints(): void
    {
        $action = get_query_var('ffp_saml');

        if (empty($action)) {
            return;
        }

        switch ($action) {
            case 'metadata':
                $this->handleMetadata();
                break;
            case 'login':
                $this->handleLogin();
                break;
            case 'acs':
                $this->handleACS();
                break;
            case 'slo':
                $this->handleSLO();
                break;
        }

        exit;
    }

    /**
     * Generate and output SP metadata
     */
    public function handleMetadata(): void
    {
        header('Content-Type: application/xml');

        echo $this->generateMetadata();
    }

    /**
     * Generate SP metadata XML
     */
    public function generateMetadata(): string
    {
        $entity_id = $this->config->entity_id;
        $acs_url = $this->config->acs_url;
        $slo_url = $this->config->slo_url;
        $name_id_format = $this->config->name_id_format;
        $certificate = $this->config->sp_certificate;

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<md:EntityDescriptor xmlns:md="urn:oasis:names:tc:SAML:2.0:metadata"
                     xmlns:ds="http://www.w3.org/2000/09/xmldsig#"
                     entityID="{$entity_id}">
    <md:SPSSODescriptor AuthnRequestsSigned="true"
                        WantAssertionsSigned="true"
                        protocolSupportEnumeration="urn:oasis:names:tc:SAML:2.0:protocol">
XML;

        // Add certificate if available
        if (!empty($certificate)) {
            $cert_clean = $this->cleanCertificate($certificate);
            $xml .= <<<XML
        <md:KeyDescriptor use="signing">
            <ds:KeyInfo>
                <ds:X509Data>
                    <ds:X509Certificate>{$cert_clean}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
        <md:KeyDescriptor use="encryption">
            <ds:KeyInfo>
                <ds:X509Data>
                    <ds:X509Certificate>{$cert_clean}</ds:X509Certificate>
                </ds:X509Data>
            </ds:KeyInfo>
        </md:KeyDescriptor>
XML;
        }

        $xml .= <<<XML
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-Redirect"
                                Location="{$slo_url}"/>
        <md:SingleLogoutService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                                Location="{$slo_url}"/>
        <md:NameIDFormat>{$name_id_format}</md:NameIDFormat>
        <md:AssertionConsumerService Binding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST"
                                     Location="{$acs_url}"
                                     index="0"
                                     isDefault="true"/>
    </md:SPSSODescriptor>
</md:EntityDescriptor>
XML;

        return $xml;
    }

    /**
     * Handle login initiation
     */
    public function handleLogin(): void
    {
        if (!$this->config->isConfigured()) {
            wp_die(__('SAML SSO is not configured', 'form-flow-pro'));
        }

        $relay_state = sanitize_text_field($_GET['redirect_to'] ?? admin_url());

        // Generate AuthnRequest
        $authn_request = $this->generateAuthnRequest();

        // Store request ID for validation
        $this->storeRequestId($authn_request['id']);

        // Redirect to IdP
        $redirect_url = $this->config->idp_sso_url;
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&');
        $redirect_url .= http_build_query([
            'SAMLRequest' => $authn_request['encoded'],
            'RelayState' => $relay_state
        ]);

        // Sign request if configured
        if ($this->config->sign_requests && !empty($this->config->sp_private_key)) {
            $redirect_url = $this->signRedirectUrl($redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Generate SAML AuthnRequest
     */
    private function generateAuthnRequest(): array
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issue_instant = gmdate('Y-m-d\TH:i:s\Z');

        $request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="{$id}"
                    Version="2.0"
                    IssueInstant="{$issue_instant}"
                    Destination="{$this->config->idp_sso_url}"
                    AssertionConsumerServiceURL="{$this->config->acs_url}"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>{$this->config->entity_id}</saml:Issuer>
    <samlp:NameIDPolicy Format="{$this->config->name_id_format}"
                        AllowCreate="true"/>
</samlp:AuthnRequest>
XML;

        // Deflate and base64 encode for HTTP-Redirect binding
        $deflated = gzdeflate($request);
        $encoded = base64_encode($deflated);

        return [
            'id' => $id,
            'xml' => $request,
            'encoded' => $encoded
        ];
    }

    /**
     * Handle Assertion Consumer Service (ACS) - Process IdP response
     */
    public function handleACS(): void
    {
        if (!isset($_POST['SAMLResponse'])) {
            wp_die(__('Invalid SAML response', 'form-flow-pro'));
        }

        $response = $this->processResponse($_POST['SAMLResponse']);

        if (!$response->valid) {
            $this->logError('SAML response validation failed', ['error' => $response->error]);
            wp_die(__('SAML authentication failed: ', 'form-flow-pro') . esc_html($response->error));
        }

        // Get or create user
        $user = $this->getOrCreateUser($response);

        if (is_wp_error($user)) {
            $this->logError('User creation failed', ['error' => $user->get_error_message()]);
            wp_die(__('User provisioning failed: ', 'form-flow-pro') . $user->get_error_message());
        }

        // Log the user in
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);

        // Store session info
        $this->storeSessionInfo($user->ID, $response);

        // Get relay state (redirect URL)
        $redirect_to = sanitize_url($_POST['RelayState'] ?? admin_url());

        // Fire action for custom handling
        do_action('ffp_saml_login_success', $user, $response);

        wp_redirect($redirect_to);
        exit;
    }

    /**
     * Process SAML Response
     */
    public function processResponse(string $saml_response): SAMLResponse
    {
        try {
            // Decode response
            $xml = base64_decode($saml_response);

            // Parse XML
            $dom = new \DOMDocument();
            $dom->preserveWhiteSpace = false;
            $dom->formatOutput = true;

            if (!$dom->loadXML($xml)) {
                return new SAMLResponse(['valid' => false, 'error' => 'Invalid XML']);
            }

            // Validate signature if required
            if ($this->config->want_assertions_signed) {
                if (!$this->validateSignature($dom)) {
                    return new SAMLResponse(['valid' => false, 'error' => 'Invalid signature']);
                }
            }

            // Extract data from response
            $xpath = new \DOMXPath($dom);
            $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
            $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

            // Check status
            $status_code = $xpath->query('//samlp:StatusCode/@Value')->item(0);
            if (!$status_code || $status_code->nodeValue !== 'urn:oasis:names:tc:SAML:2.0:status:Success') {
                return new SAMLResponse(['valid' => false, 'error' => 'Authentication failed at IdP']);
            }

            // Validate conditions (time, audience)
            if (!$this->validateConditions($xpath)) {
                return new SAMLResponse(['valid' => false, 'error' => 'Response conditions not met']);
            }

            // Extract NameID
            $name_id_node = $xpath->query('//saml:NameID')->item(0);
            $name_id = $name_id_node ? $name_id_node->nodeValue : null;

            // Extract SessionIndex
            $session_index_node = $xpath->query('//saml:AuthnStatement/@SessionIndex')->item(0);
            $session_index = $session_index_node ? $session_index_node->nodeValue : null;

            // Extract attributes
            $attributes = $this->extractAttributes($xpath);

            return new SAMLResponse([
                'valid' => true,
                'name_id' => $name_id,
                'session_index' => $session_index,
                'attributes' => $attributes,
                'raw_response' => [
                    'xml' => $xml,
                    'status' => 'Success'
                ]
            ]);

        } catch (\Exception $e) {
            return new SAMLResponse([
                'valid' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Validate XML signature
     */
    private function validateSignature(\DOMDocument $dom): bool
    {
        if (empty($this->config->idp_certificate)) {
            return false;
        }

        // Find signature
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

        $signature_node = $xpath->query('//ds:Signature')->item(0);
        if (!$signature_node) {
            return false;
        }

        // Extract signed info and signature value
        $signed_info = $xpath->query('.//ds:SignedInfo', $signature_node)->item(0);
        $signature_value = $xpath->query('.//ds:SignatureValue', $signature_node)->item(0);

        if (!$signed_info || !$signature_value) {
            return false;
        }

        // Canonicalize signed info
        $canonical = $signed_info->C14N(true, false);

        // Decode signature
        $signature = base64_decode(preg_replace('/\s+/', '', $signature_value->nodeValue));

        // Get certificate
        $cert = $this->formatCertificate($this->config->idp_certificate);
        $public_key = openssl_pkey_get_public($cert);

        if (!$public_key) {
            return false;
        }

        // Verify signature
        $result = openssl_verify($canonical, $signature, $public_key, OPENSSL_ALGO_SHA256);

        return $result === 1;
    }

    /**
     * Validate response conditions
     */
    private function validateConditions(\DOMXPath $xpath): bool
    {
        $conditions = $xpath->query('//saml:Conditions')->item(0);

        if (!$conditions) {
            return true; // No conditions to validate
        }

        $now = time();

        // Check NotBefore
        $not_before = $conditions->getAttribute('NotBefore');
        if ($not_before) {
            $not_before_time = strtotime($not_before);
            if ($now < $not_before_time - 120) { // 2 minute clock skew
                return false;
            }
        }

        // Check NotOnOrAfter
        $not_on_or_after = $conditions->getAttribute('NotOnOrAfter');
        if ($not_on_or_after) {
            $not_on_or_after_time = strtotime($not_on_or_after);
            if ($now >= $not_on_or_after_time + 120) { // 2 minute clock skew
                return false;
            }
        }

        // Check Audience
        $audience = $xpath->query('.//saml:Audience', $conditions)->item(0);
        if ($audience && $audience->nodeValue !== $this->config->entity_id) {
            return false;
        }

        return true;
    }

    /**
     * Extract attributes from SAML response
     */
    private function extractAttributes(\DOMXPath $xpath): array
    {
        $attributes = [];

        $attribute_nodes = $xpath->query('//saml:Attribute');

        foreach ($attribute_nodes as $node) {
            $name = $node->getAttribute('Name');
            $values = [];

            $value_nodes = $xpath->query('.//saml:AttributeValue', $node);
            foreach ($value_nodes as $value_node) {
                $values[] = $value_node->nodeValue;
            }

            $attributes[$name] = count($values) === 1 ? $values[0] : $values;
        }

        return $attributes;
    }

    /**
     * Get or create WordPress user from SAML response
     */
    private function getOrCreateUser(SAMLResponse $response): \WP_User|\WP_Error
    {
        // Map attributes
        $user_data = $this->mapAttributes($response);

        // Get email (required)
        $email = $user_data['email'] ?? $response->name_id;

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', __('Invalid email address in SAML response', 'form-flow-pro'));
        }

        // Check if user exists
        $user = get_user_by('email', $email);

        if ($user) {
            // Update existing user
            $this->updateUserFromSAML($user, $user_data, $response);
            return $user;
        }

        // Check if auto-provisioning is enabled
        $auto_provision = get_option('ffp_saml_auto_provision', true);

        if (!$auto_provision) {
            return new \WP_Error('user_not_found', __('User does not exist and auto-provisioning is disabled', 'form-flow-pro'));
        }

        // Create new user
        return $this->createUserFromSAML($user_data, $response);
    }

    /**
     * Map SAML attributes to user data
     */
    private function mapAttributes(SAMLResponse $response): array
    {
        $user_data = [];
        $mapping = $this->config->attribute_mapping;

        foreach ($mapping as $wp_field => $saml_fields) {
            foreach ($saml_fields as $saml_field) {
                $value = $response->getAttribute($saml_field);
                if ($value !== null) {
                    $user_data[$wp_field] = is_array($value) ? $value[0] : $value;
                    break;
                }
            }
        }

        return $user_data;
    }

    /**
     * Create new user from SAML data
     */
    private function createUserFromSAML(array $user_data, SAMLResponse $response): \WP_User|\WP_Error
    {
        $email = $user_data['email'] ?? $response->name_id;

        // Generate username
        $username = sanitize_user(explode('@', $email)[0]);
        $base_username = $username;
        $counter = 1;

        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Determine role
        $default_role = get_option('ffp_saml_default_role', 'subscriber');
        $role = $this->determineRole($response, $default_role);

        // Create user
        $wp_user_data = [
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => wp_generate_password(32),
            'first_name' => $user_data['first_name'] ?? '',
            'last_name' => $user_data['last_name'] ?? '',
            'display_name' => $user_data['display_name'] ?? $user_data['first_name'] ?? $username,
            'role' => $role
        ];

        $user_id = wp_insert_user($wp_user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Store SAML metadata
        update_user_meta($user_id, 'ffp_saml_name_id', $response->name_id);
        update_user_meta($user_id, 'ffp_saml_provider', $this->config->idp_entity_id);
        update_user_meta($user_id, 'ffp_saml_created', current_time('mysql'));

        do_action('ffp_saml_user_created', $user_id, $response);

        return get_user_by('id', $user_id);
    }

    /**
     * Update existing user from SAML data
     */
    private function updateUserFromSAML(\WP_User $user, array $user_data, SAMLResponse $response): void
    {
        $update_data = ['ID' => $user->ID];

        if (!empty($user_data['first_name'])) {
            $update_data['first_name'] = $user_data['first_name'];
        }
        if (!empty($user_data['last_name'])) {
            $update_data['last_name'] = $user_data['last_name'];
        }
        if (!empty($user_data['display_name'])) {
            $update_data['display_name'] = $user_data['display_name'];
        }

        wp_update_user($update_data);

        // Update role if role sync is enabled
        if (get_option('ffp_saml_sync_roles', false)) {
            $role = $this->determineRole($response, $user->roles[0] ?? 'subscriber');
            $user->set_role($role);
        }

        // Update metadata
        update_user_meta($user->ID, 'ffp_saml_last_login', current_time('mysql'));
        update_user_meta($user->ID, 'ffp_saml_name_id', $response->name_id);

        do_action('ffp_saml_user_updated', $user->ID, $response);
    }

    /**
     * Determine WordPress role from SAML groups
     */
    private function determineRole(SAMLResponse $response, string $default): string
    {
        $role_mapping = get_option('ffp_saml_role_mapping', []);

        if (empty($role_mapping)) {
            return $default;
        }

        // Get groups from response
        $groups = [];
        foreach ($this->config->attribute_mapping['groups'] ?? [] as $group_attr) {
            $group_value = $response->getAttribute($group_attr);
            if ($group_value) {
                $groups = is_array($group_value) ? $group_value : [$group_value];
                break;
            }
        }

        // Map groups to roles
        foreach ($role_mapping as $saml_group => $wp_role) {
            if (in_array($saml_group, $groups)) {
                return $wp_role;
            }
        }

        return $default;
    }

    /**
     * Handle Single Logout (SLO)
     */
    public function handleSLO(): void
    {
        // Process logout request from IdP
        if (isset($_REQUEST['SAMLRequest'])) {
            $this->processLogoutRequest($_REQUEST['SAMLRequest']);
            return;
        }

        // Process logout response from IdP
        if (isset($_REQUEST['SAMLResponse'])) {
            $this->processLogoutResponse($_REQUEST['SAMLResponse']);
            return;
        }

        // Initiate logout
        $this->initiateLogout();
    }

    /**
     * Initiate Single Logout
     */
    public function initiateLogout(): void
    {
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_redirect(home_url());
            exit;
        }

        $session_info = get_user_meta($user_id, 'ffp_saml_session', true);

        if (empty($session_info) || empty($this->config->idp_slo_url)) {
            // No SAML session, just logout locally
            wp_logout();
            wp_redirect(home_url());
            exit;
        }

        // Generate LogoutRequest
        $logout_request = $this->generateLogoutRequest($session_info);

        // Redirect to IdP SLO
        $redirect_url = $this->config->idp_slo_url;
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&');
        $redirect_url .= http_build_query([
            'SAMLRequest' => $logout_request['encoded'],
            'RelayState' => home_url()
        ]);

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Generate LogoutRequest
     */
    private function generateLogoutRequest(array $session_info): array
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issue_instant = gmdate('Y-m-d\TH:i:s\Z');
        $name_id = $session_info['name_id'] ?? '';
        $session_index = $session_info['session_index'] ?? '';

        $request = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                     xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                     ID="{$id}"
                     Version="2.0"
                     IssueInstant="{$issue_instant}"
                     Destination="{$this->config->idp_slo_url}">
    <saml:Issuer>{$this->config->entity_id}</saml:Issuer>
    <saml:NameID>{$name_id}</saml:NameID>
    <samlp:SessionIndex>{$session_index}</samlp:SessionIndex>
</samlp:LogoutRequest>
XML;

        $deflated = gzdeflate($request);
        $encoded = base64_encode($deflated);

        return [
            'id' => $id,
            'xml' => $request,
            'encoded' => $encoded
        ];
    }

    /**
     * Process logout request from IdP
     */
    private function processLogoutRequest(string $request): void
    {
        $xml = gzinflate(base64_decode($request));

        // Extract NameID
        preg_match('/<saml:NameID[^>]*>([^<]+)<\/saml:NameID>/', $xml, $matches);
        $name_id = $matches[1] ?? '';

        if ($name_id) {
            // Find and logout user
            $users = get_users([
                'meta_key' => 'ffp_saml_name_id',
                'meta_value' => $name_id
            ]);

            foreach ($users as $user) {
                // Destroy sessions
                $sessions = \WP_Session_Tokens::get_instance($user->ID);
                $sessions->destroy_all();
            }
        }

        // Send LogoutResponse
        $this->sendLogoutResponse();
    }

    /**
     * Process logout response from IdP
     */
    private function processLogoutResponse(string $response): void
    {
        // Logout locally
        wp_logout();

        // Redirect to home
        $relay_state = sanitize_url($_REQUEST['RelayState'] ?? home_url());
        wp_redirect($relay_state);
        exit;
    }

    /**
     * Send logout response to IdP
     */
    private function sendLogoutResponse(): void
    {
        $id = '_' . bin2hex(random_bytes(16));
        $issue_instant = gmdate('Y-m-d\TH:i:s\Z');
        $in_response_to = sanitize_text_field($_REQUEST['ID'] ?? '');

        $response = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<samlp:LogoutResponse xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                      ID="{$id}"
                      Version="2.0"
                      IssueInstant="{$issue_instant}"
                      Destination="{$this->config->idp_slo_url}"
                      InResponseTo="{$in_response_to}">
    <saml:Issuer xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">{$this->config->entity_id}</saml:Issuer>
    <samlp:Status>
        <samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
    </samlp:Status>
</samlp:LogoutResponse>
XML;

        $deflated = gzdeflate($response);
        $encoded = base64_encode($deflated);

        $redirect_url = $this->config->idp_slo_url;
        $redirect_url .= (strpos($redirect_url, '?') === false ? '?' : '&');
        $redirect_url .= http_build_query(['SAMLResponse' => $encoded]);

        wp_redirect($redirect_url);
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

        // Check if user logged in via SAML
        $session_info = get_user_meta($user_id, 'ffp_saml_session', true);

        if (!empty($session_info) && get_option('ffp_saml_slo_on_wp_logout', true)) {
            // Clean up session info
            delete_user_meta($user_id, 'ffp_saml_session');

            // Optionally redirect to IdP SLO
            if (!empty($this->config->idp_slo_url)) {
                add_action('wp_logout', function () use ($session_info) {
                    $this->initiateLogout();
                }, 999);
            }
        }
    }

    /**
     * Authenticate via SSO
     */
    public function authenticateViaSSO($user, $username, $password)
    {
        // Only intercept if SSO is forced and no credentials provided
        if (!get_option('ffp_saml_force_sso', false) || !empty($username) || !empty($password)) {
            return $user;
        }

        // Check if accessing login page directly
        if (isset($_GET['ffp_bypass_sso'])) {
            return $user;
        }

        // Redirect to SAML login
        wp_redirect(home_url('/saml/login?redirect_to=' . urlencode(admin_url())));
        exit;
    }

    /**
     * Modify login URL
     */
    public function modifyLoginUrl($login_url, $redirect, $force_reauth)
    {
        if (get_option('ffp_saml_force_sso', false)) {
            return home_url('/saml/login?redirect_to=' . urlencode($redirect ?: admin_url()));
        }

        return $login_url;
    }

    /**
     * Store request ID for validation
     */
    private function storeRequestId(string $id): void
    {
        set_transient($this->session_prefix . 'request_' . $id, time(), 600);
    }

    /**
     * Store session info
     */
    private function storeSessionInfo(int $user_id, SAMLResponse $response): void
    {
        update_user_meta($user_id, 'ffp_saml_session', [
            'name_id' => $response->name_id,
            'session_index' => $response->session_index,
            'login_time' => current_time('mysql'),
            'idp' => $this->config->idp_entity_id
        ]);
    }

    /**
     * Sign redirect URL
     */
    private function signRedirectUrl(string $url): string
    {
        if (empty($this->config->sp_private_key)) {
            return $url;
        }

        $parsed = parse_url($url);
        $query = $parsed['query'] ?? '';

        // Add signature algorithm
        $query .= '&SigAlg=' . urlencode('http://www.w3.org/2001/04/xmldsig-more#rsa-sha256');

        // Sign the query string
        $private_key = openssl_pkey_get_private($this->formatPrivateKey($this->config->sp_private_key));

        if ($private_key) {
            openssl_sign($query, $signature, $private_key, OPENSSL_ALGO_SHA256);
            $query .= '&Signature=' . urlencode(base64_encode($signature));
        }

        return $parsed['scheme'] . '://' . $parsed['host'] . ($parsed['port'] ?? '') . $parsed['path'] . '?' . $query;
    }

    /**
     * Format certificate for use
     */
    private function formatCertificate(string $cert): string
    {
        $cert = $this->cleanCertificate($cert);
        return "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64, "\n") . "-----END CERTIFICATE-----";
    }

    /**
     * Clean certificate (remove headers/whitespace)
     */
    private function cleanCertificate(string $cert): string
    {
        $cert = preg_replace('/-----BEGIN CERTIFICATE-----/', '', $cert);
        $cert = preg_replace('/-----END CERTIFICATE-----/', '', $cert);
        return preg_replace('/\s+/', '', $cert);
    }

    /**
     * Format private key
     */
    private function formatPrivateKey(string $key): string
    {
        $key = preg_replace('/-----BEGIN (RSA )?PRIVATE KEY-----/', '', $key);
        $key = preg_replace('/-----END (RSA )?PRIVATE KEY-----/', '', $key);
        $key = preg_replace('/\s+/', '', $key);
        return "-----BEGIN PRIVATE KEY-----\n" . chunk_split($key, 64, "\n") . "-----END PRIVATE KEY-----";
    }

    /**
     * Log error
     */
    private function logError(string $message, array $context = []): void
    {
        if (class_exists('\FormFlowPro\Security\AuditLogger')) {
            \FormFlowPro\Security\AuditLogger::getInstance()->log('saml_error', $message, $context);
        } else {
            error_log("FormFlow Pro SAML: {$message} " . json_encode($context));
        }
    }

    /**
     * Generate SP certificates (for setup)
     */
    public static function generateCertificates(): array
    {
        $config = [
            'digest_alg' => 'sha256',
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA
        ];

        $dn = [
            'countryName' => 'BR',
            'stateOrProvinceName' => 'SP',
            'localityName' => 'Sao Paulo',
            'organizationName' => get_bloginfo('name'),
            'commonName' => parse_url(home_url(), PHP_URL_HOST)
        ];

        // Generate private key
        $private_key = openssl_pkey_new($config);

        // Generate CSR
        $csr = openssl_csr_new($dn, $private_key, $config);

        // Self-sign certificate (valid for 1 year)
        $cert = openssl_csr_sign($csr, null, $private_key, 365, $config);

        // Export
        openssl_pkey_export($private_key, $private_key_pem);
        openssl_x509_export($cert, $cert_pem);

        return [
            'private_key' => $private_key_pem,
            'certificate' => $cert_pem
        ];
    }
}
