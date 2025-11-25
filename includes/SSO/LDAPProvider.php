<?php
/**
 * LDAP Provider - Enterprise LDAP/Active Directory Authentication
 *
 * Implements LDAP authentication for integration with
 * Active Directory and other LDAP servers.
 *
 * @package FormFlowPro
 * @subpackage SSO
 * @since 3.0.0
 */

namespace FormFlowPro\SSO;

use FormFlowPro\Core\SingletonTrait;

/**
 * LDAP Configuration model
 */
class LDAPConfig
{
    public string $host;
    public int $port;
    public bool $use_ssl;
    public bool $use_tls;
    public string $base_dn;
    public string $bind_dn;
    public string $bind_password;
    public string $user_filter;
    public string $user_id_attribute;
    public string $group_base_dn;
    public string $group_filter;
    public string $group_member_attribute;
    public array $attribute_mapping;
    public int $network_timeout;
    public int $search_timeout;
    public bool $referrals;

    public function __construct(array $data = [])
    {
        $this->host = $data['host'] ?? '';
        $this->port = $data['port'] ?? 389;
        $this->use_ssl = $data['use_ssl'] ?? false;
        $this->use_tls = $data['use_tls'] ?? true;
        $this->base_dn = $data['base_dn'] ?? '';
        $this->bind_dn = $data['bind_dn'] ?? '';
        $this->bind_password = $data['bind_password'] ?? '';
        $this->user_filter = $data['user_filter'] ?? '(&(objectClass=user)(sAMAccountName=%s))';
        $this->user_id_attribute = $data['user_id_attribute'] ?? 'sAMAccountName';
        $this->group_base_dn = $data['group_base_dn'] ?? '';
        $this->group_filter = $data['group_filter'] ?? '(&(objectClass=group)(member=%s))';
        $this->group_member_attribute = $data['group_member_attribute'] ?? 'member';
        $this->attribute_mapping = $data['attribute_mapping'] ?? $this->getDefaultMapping();
        $this->network_timeout = $data['network_timeout'] ?? 10;
        $this->search_timeout = $data['search_timeout'] ?? 30;
        $this->referrals = $data['referrals'] ?? false;
    }

    private function getDefaultMapping(): array
    {
        return [
            'email' => 'mail',
            'first_name' => 'givenName',
            'last_name' => 'sn',
            'display_name' => 'displayName',
            'username' => 'sAMAccountName',
            'phone' => 'telephoneNumber',
            'title' => 'title',
            'department' => 'department',
            'company' => 'company'
        ];
    }

    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'use_ssl' => $this->use_ssl,
            'use_tls' => $this->use_tls,
            'base_dn' => $this->base_dn,
            'bind_dn' => $this->bind_dn,
            'bind_password' => $this->bind_password,
            'user_filter' => $this->user_filter,
            'user_id_attribute' => $this->user_id_attribute,
            'group_base_dn' => $this->group_base_dn,
            'group_filter' => $this->group_filter,
            'group_member_attribute' => $this->group_member_attribute,
            'attribute_mapping' => $this->attribute_mapping,
            'network_timeout' => $this->network_timeout,
            'search_timeout' => $this->search_timeout,
            'referrals' => $this->referrals
        ];
    }

    public function isConfigured(): bool
    {
        return !empty($this->host) && !empty($this->base_dn);
    }

    public function getConnectionUri(): string
    {
        $protocol = $this->use_ssl ? 'ldaps' : 'ldap';
        return "{$protocol}://{$this->host}:{$this->port}";
    }
}

/**
 * LDAP User model
 */
class LDAPUser
{
    public string $dn;
    public string $username;
    public string $email;
    public string $first_name;
    public string $last_name;
    public string $display_name;
    public array $groups;
    public array $raw_attributes;

    public function __construct(array $data = [])
    {
        $this->dn = $data['dn'] ?? '';
        $this->username = $data['username'] ?? '';
        $this->email = $data['email'] ?? '';
        $this->first_name = $data['first_name'] ?? '';
        $this->last_name = $data['last_name'] ?? '';
        $this->display_name = $data['display_name'] ?? '';
        $this->groups = $data['groups'] ?? [];
        $this->raw_attributes = $data['raw_attributes'] ?? [];
    }

    public function getAttribute(string $name, $default = null)
    {
        return $this->raw_attributes[$name] ?? $default;
    }
}

/**
 * LDAP Provider
 */
class LDAPProvider
{
    use SingletonTrait;

    private ?LDAPConfig $config = null;
    private $connection = null;

    /**
     * Initialize LDAP provider
     */
    protected function init(): void
    {
        $this->loadConfig();
        $this->registerHooks();
    }

    /**
     * Load configuration
     */
    private function loadConfig(): void
    {
        $data = get_option('ffp_ldap_config', []);
        $this->config = new LDAPConfig($data);
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_filter('authenticate', [$this, 'authenticate'], 20, 3);
    }

    /**
     * Get configuration
     */
    public function getConfig(): LDAPConfig
    {
        return $this->config;
    }

    /**
     * Save configuration
     */
    public function saveConfig(array $data): void
    {
        $this->config = new LDAPConfig($data);
        update_option('ffp_ldap_config', $this->config->toArray());
    }

    /**
     * Authenticate user via LDAP
     */
    public function authenticate($user, $username, $password)
    {
        // Skip if LDAP not configured or already authenticated
        if (!$this->config->isConfigured() || ($user instanceof \WP_User)) {
            return $user;
        }

        // Skip if empty credentials
        if (empty($username) || empty($password)) {
            return $user;
        }

        // Check if LDAP auth is enabled
        if (!get_option('ffp_ldap_enabled', false)) {
            return $user;
        }

        // Try LDAP authentication
        $ldap_user = $this->authenticateLDAP($username, $password);

        if (!$ldap_user) {
            // LDAP auth failed, check fallback setting
            if (get_option('ffp_ldap_fallback_to_wp', true)) {
                return $user; // Let WordPress try
            }
            return new \WP_Error('ldap_auth_failed', __('Invalid credentials', 'form-flow-pro'));
        }

        // Get or create WordPress user
        $wp_user = $this->getOrCreateUser($ldap_user);

        if (is_wp_error($wp_user)) {
            return $wp_user;
        }

        return $wp_user;
    }

    /**
     * Authenticate against LDAP server
     */
    public function authenticateLDAP(string $username, string $password): ?LDAPUser
    {
        try {
            // Connect to LDAP
            if (!$this->connect()) {
                $this->logError('Failed to connect to LDAP server');
                return null;
            }

            // Search for user
            $user_dn = $this->findUserDN($username);

            if (!$user_dn) {
                $this->logError('User not found in LDAP', ['username' => $username]);
                return null;
            }

            // Try to bind with user credentials
            $bind_result = @ldap_bind($this->connection, $user_dn, $password);

            if (!$bind_result) {
                $this->logError('LDAP bind failed', [
                    'username' => $username,
                    'error' => ldap_error($this->connection)
                ]);
                return null;
            }

            // Re-bind as service account to fetch user details
            $this->bindServiceAccount();

            // Get user attributes
            $ldap_user = $this->getUserDetails($user_dn);

            if ($ldap_user) {
                // Get user groups
                $ldap_user->groups = $this->getUserGroups($user_dn);
            }

            $this->disconnect();

            return $ldap_user;

        } catch (\Exception $e) {
            $this->logError('LDAP authentication error', ['error' => $e->getMessage()]);
            $this->disconnect();
            return null;
        }
    }

    /**
     * Connect to LDAP server
     */
    private function connect(): bool
    {
        if (!function_exists('ldap_connect')) {
            $this->logError('LDAP extension not installed');
            return false;
        }

        $this->connection = @ldap_connect($this->config->getConnectionUri());

        if (!$this->connection) {
            return false;
        }

        // Set options
        ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
        ldap_set_option($this->connection, LDAP_OPT_REFERRALS, $this->config->referrals ? 1 : 0);
        ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, $this->config->network_timeout);
        ldap_set_option($this->connection, LDAP_OPT_TIMELIMIT, $this->config->search_timeout);

        // Start TLS if configured
        if ($this->config->use_tls && !$this->config->use_ssl) {
            if (!@ldap_start_tls($this->connection)) {
                $this->logError('Failed to start TLS', ['error' => ldap_error($this->connection)]);
                return false;
            }
        }

        return $this->bindServiceAccount();
    }

    /**
     * Bind with service account
     */
    private function bindServiceAccount(): bool
    {
        if (empty($this->config->bind_dn)) {
            // Anonymous bind
            return @ldap_bind($this->connection);
        }

        return @ldap_bind(
            $this->connection,
            $this->config->bind_dn,
            $this->config->bind_password
        );
    }

    /**
     * Disconnect from LDAP
     */
    private function disconnect(): void
    {
        if ($this->connection) {
            @ldap_unbind($this->connection);
            $this->connection = null;
        }
    }

    /**
     * Find user DN by username
     */
    private function findUserDN(string $username): ?string
    {
        // Escape username for LDAP
        $escaped_username = ldap_escape($username, '', LDAP_ESCAPE_FILTER);

        // Build filter
        $filter = sprintf($this->config->user_filter, $escaped_username);

        // Search
        $search = @ldap_search(
            $this->connection,
            $this->config->base_dn,
            $filter,
            ['dn'],
            0,
            1
        );

        if (!$search) {
            return null;
        }

        $entries = ldap_get_entries($this->connection, $search);

        if ($entries['count'] === 0) {
            return null;
        }

        return $entries[0]['dn'];
    }

    /**
     * Get user details from LDAP
     */
    private function getUserDetails(string $user_dn): ?LDAPUser
    {
        $attributes = array_values($this->config->attribute_mapping);
        $attributes[] = 'dn';
        $attributes[] = $this->config->user_id_attribute;

        $search = @ldap_read(
            $this->connection,
            $user_dn,
            '(objectClass=*)',
            $attributes
        );

        if (!$search) {
            return null;
        }

        $entries = ldap_get_entries($this->connection, $search);

        if ($entries['count'] === 0) {
            return null;
        }

        $entry = $entries[0];

        // Map attributes
        $user_data = [
            'dn' => $user_dn,
            'raw_attributes' => $entry
        ];

        foreach ($this->config->attribute_mapping as $wp_field => $ldap_attr) {
            $ldap_attr_lower = strtolower($ldap_attr);
            if (isset($entry[$ldap_attr_lower])) {
                $value = $entry[$ldap_attr_lower];
                $user_data[$wp_field] = is_array($value) ? ($value[0] ?? '') : $value;
            }
        }

        // Get username
        $user_id_attr = strtolower($this->config->user_id_attribute);
        if (isset($entry[$user_id_attr])) {
            $user_data['username'] = $entry[$user_id_attr][0] ?? '';
        }

        return new LDAPUser($user_data);
    }

    /**
     * Get user's group memberships
     */
    private function getUserGroups(string $user_dn): array
    {
        $groups = [];

        // Method 1: Check memberOf attribute on user
        $search = @ldap_read(
            $this->connection,
            $user_dn,
            '(objectClass=*)',
            ['memberOf']
        );

        if ($search) {
            $entries = ldap_get_entries($this->connection, $search);
            if (isset($entries[0]['memberof'])) {
                unset($entries[0]['memberof']['count']);
                foreach ($entries[0]['memberof'] as $group_dn) {
                    // Extract CN from DN
                    if (preg_match('/^CN=([^,]+)/i', $group_dn, $matches)) {
                        $groups[] = $matches[1];
                    }
                }
            }
        }

        // Method 2: Search groups for user membership
        if (empty($groups) && !empty($this->config->group_base_dn)) {
            $escaped_dn = ldap_escape($user_dn, '', LDAP_ESCAPE_FILTER);
            $filter = sprintf($this->config->group_filter, $escaped_dn);

            $search = @ldap_search(
                $this->connection,
                $this->config->group_base_dn ?: $this->config->base_dn,
                $filter,
                ['cn']
            );

            if ($search) {
                $entries = ldap_get_entries($this->connection, $search);
                for ($i = 0; $i < $entries['count']; $i++) {
                    if (isset($entries[$i]['cn'][0])) {
                        $groups[] = $entries[$i]['cn'][0];
                    }
                }
            }
        }

        return $groups;
    }

    /**
     * Get or create WordPress user from LDAP user
     */
    private function getOrCreateUser(LDAPUser $ldap_user): \WP_User|\WP_Error
    {
        // Try to find by email
        $user = null;

        if (!empty($ldap_user->email)) {
            $user = get_user_by('email', $ldap_user->email);
        }

        // Try by username
        if (!$user && !empty($ldap_user->username)) {
            $user = get_user_by('login', $ldap_user->username);
        }

        // Try by LDAP DN stored in meta
        if (!$user) {
            $users = get_users([
                'meta_key' => 'ffp_ldap_dn',
                'meta_value' => $ldap_user->dn,
                'number' => 1
            ]);
            $user = !empty($users) ? $users[0] : null;
        }

        if ($user) {
            // Update existing user
            $this->updateUser($user, $ldap_user);
            return $user;
        }

        // Check if auto-provisioning is enabled
        if (!get_option('ffp_ldap_auto_provision', true)) {
            return new \WP_Error('user_not_found', __('User does not exist', 'form-flow-pro'));
        }

        // Create new user
        return $this->createUser($ldap_user);
    }

    /**
     * Create WordPress user from LDAP
     */
    private function createUser(LDAPUser $ldap_user): \WP_User|\WP_Error
    {
        $username = $ldap_user->username ?: sanitize_user(explode('@', $ldap_user->email)[0]);

        // Ensure unique username
        $base_username = $username;
        $counter = 1;
        while (username_exists($username)) {
            $username = $base_username . $counter;
            $counter++;
        }

        // Determine role
        $role = $this->determineRole($ldap_user);

        $user_data = [
            'user_login' => $username,
            'user_email' => $ldap_user->email,
            'user_pass' => wp_generate_password(32),
            'first_name' => $ldap_user->first_name,
            'last_name' => $ldap_user->last_name,
            'display_name' => $ldap_user->display_name ?: ($ldap_user->first_name . ' ' . $ldap_user->last_name),
            'role' => $role
        ];

        $user_id = wp_insert_user($user_data);

        if (is_wp_error($user_id)) {
            return $user_id;
        }

        // Store LDAP metadata
        update_user_meta($user_id, 'ffp_ldap_dn', $ldap_user->dn);
        update_user_meta($user_id, 'ffp_ldap_username', $ldap_user->username);
        update_user_meta($user_id, 'ffp_ldap_groups', $ldap_user->groups);
        update_user_meta($user_id, 'ffp_ldap_created', current_time('mysql'));

        // Store additional attributes
        foreach ($ldap_user->raw_attributes as $attr => $value) {
            if (!is_numeric($attr) && $attr !== 'count' && $attr !== 'dn') {
                $val = is_array($value) ? ($value[0] ?? '') : $value;
                update_user_meta($user_id, 'ffp_ldap_' . $attr, $val);
            }
        }

        do_action('ffp_ldap_user_created', $user_id, $ldap_user);

        return get_user_by('id', $user_id);
    }

    /**
     * Update WordPress user from LDAP
     */
    private function updateUser(\WP_User $user, LDAPUser $ldap_user): void
    {
        $update_data = ['ID' => $user->ID];

        if (!empty($ldap_user->first_name)) {
            $update_data['first_name'] = $ldap_user->first_name;
        }
        if (!empty($ldap_user->last_name)) {
            $update_data['last_name'] = $ldap_user->last_name;
        }
        if (!empty($ldap_user->display_name)) {
            $update_data['display_name'] = $ldap_user->display_name;
        }
        if (!empty($ldap_user->email)) {
            $update_data['user_email'] = $ldap_user->email;
        }

        wp_update_user($update_data);

        // Update role if sync enabled
        if (get_option('ffp_ldap_sync_roles', false)) {
            $role = $this->determineRole($ldap_user);
            $user->set_role($role);
        }

        // Update metadata
        update_user_meta($user->ID, 'ffp_ldap_dn', $ldap_user->dn);
        update_user_meta($user->ID, 'ffp_ldap_groups', $ldap_user->groups);
        update_user_meta($user->ID, 'ffp_ldap_last_login', current_time('mysql'));

        do_action('ffp_ldap_user_updated', $user->ID, $ldap_user);
    }

    /**
     * Determine WordPress role from LDAP groups
     */
    private function determineRole(LDAPUser $ldap_user): string
    {
        $default_role = get_option('ffp_ldap_default_role', 'subscriber');
        $role_mapping = get_option('ffp_ldap_role_mapping', []);

        if (empty($role_mapping)) {
            return $default_role;
        }

        foreach ($role_mapping as $ldap_group => $wp_role) {
            if (in_array($ldap_group, $ldap_user->groups)) {
                return $wp_role;
            }
        }

        return $default_role;
    }

    /**
     * Test LDAP connection
     */
    public function testConnection(): array
    {
        $result = [
            'success' => false,
            'message' => '',
            'details' => []
        ];

        if (!function_exists('ldap_connect')) {
            $result['message'] = __('LDAP PHP extension is not installed', 'form-flow-pro');
            return $result;
        }

        if (!$this->config->isConfigured()) {
            $result['message'] = __('LDAP is not configured', 'form-flow-pro');
            return $result;
        }

        try {
            // Test connection
            $result['details']['connect'] = false;
            $this->connection = @ldap_connect($this->config->getConnectionUri());

            if (!$this->connection) {
                $result['message'] = __('Failed to connect to LDAP server', 'form-flow-pro');
                return $result;
            }

            $result['details']['connect'] = true;

            // Set options
            ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, 3);
            ldap_set_option($this->connection, LDAP_OPT_REFERRALS, 0);
            ldap_set_option($this->connection, LDAP_OPT_NETWORK_TIMEOUT, 10);

            // Test TLS
            $result['details']['tls'] = true;
            if ($this->config->use_tls && !$this->config->use_ssl) {
                if (!@ldap_start_tls($this->connection)) {
                    $result['details']['tls'] = false;
                    $result['details']['tls_error'] = ldap_error($this->connection);
                }
            }

            // Test bind
            $result['details']['bind'] = false;
            if ($this->bindServiceAccount()) {
                $result['details']['bind'] = true;
            } else {
                $result['details']['bind_error'] = ldap_error($this->connection);
                $result['message'] = __('Service account bind failed', 'form-flow-pro');
                $this->disconnect();
                return $result;
            }

            // Test search
            $result['details']['search'] = false;
            $search = @ldap_search(
                $this->connection,
                $this->config->base_dn,
                '(objectClass=*)',
                ['dn'],
                0,
                1
            );

            if ($search) {
                $entries = ldap_get_entries($this->connection, $search);
                $result['details']['search'] = true;
                $result['details']['entries_found'] = $entries['count'];
            } else {
                $result['details']['search_error'] = ldap_error($this->connection);
            }

            $this->disconnect();

            $result['success'] = $result['details']['connect'] &&
                                 $result['details']['bind'] &&
                                 $result['details']['search'];

            $result['message'] = $result['success']
                ? __('LDAP connection successful', 'form-flow-pro')
                : __('LDAP connection partially successful', 'form-flow-pro');

        } catch (\Exception $e) {
            $result['message'] = $e->getMessage();
            $this->disconnect();
        }

        return $result;
    }

    /**
     * Search users in LDAP
     */
    public function searchUsers(string $query, int $limit = 20): array
    {
        $users = [];

        if (!$this->connect()) {
            return $users;
        }

        $escaped_query = ldap_escape($query, '', LDAP_ESCAPE_FILTER);

        // Search by various attributes
        $filter = "(|(sAMAccountName=*{$escaped_query}*)(mail=*{$escaped_query}*)(cn=*{$escaped_query}*)(displayName=*{$escaped_query}*))";

        $search = @ldap_search(
            $this->connection,
            $this->config->base_dn,
            $filter,
            array_values($this->config->attribute_mapping),
            0,
            $limit
        );

        if ($search) {
            $entries = ldap_get_entries($this->connection, $search);

            for ($i = 0; $i < $entries['count']; $i++) {
                $entry = $entries[$i];
                $user_data = ['dn' => $entry['dn'], 'raw_attributes' => $entry];

                foreach ($this->config->attribute_mapping as $wp_field => $ldap_attr) {
                    $ldap_attr_lower = strtolower($ldap_attr);
                    if (isset($entry[$ldap_attr_lower])) {
                        $user_data[$wp_field] = $entry[$ldap_attr_lower][0] ?? '';
                    }
                }

                $users[] = new LDAPUser($user_data);
            }
        }

        $this->disconnect();

        return $users;
    }

    /**
     * Log error
     */
    private function logError(string $message, array $context = []): void
    {
        if (class_exists('\FormFlowPro\Security\AuditLogger')) {
            \FormFlowPro\Security\AuditLogger::getInstance()->log('ldap_error', $message, $context);
        } else {
            error_log("FormFlow Pro LDAP: {$message} " . json_encode($context));
        }
    }
}
