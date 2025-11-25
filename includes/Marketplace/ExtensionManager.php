<?php
/**
 * Extension Manager - Marketplace Extensions System
 *
 * Manages installation, activation, updates and removal
 * of FormFlow Pro extensions from the marketplace.
 *
 * @package FormFlowPro
 * @subpackage Marketplace
 * @since 3.0.0
 */

namespace FormFlowPro\Marketplace;

use FormFlowPro\Core\SingletonTrait;

/**
 * Extension status enumeration
 */
class ExtensionStatus
{
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const INSTALLED = 'installed';
    public const UPDATE_AVAILABLE = 'update_available';
    public const NOT_INSTALLED = 'not_installed';
}

/**
 * Installed extension model
 */
class InstalledExtension
{
    public string $id;
    public string $slug;
    public string $name;
    public string $version;
    public string $description;
    public string $author;
    public string $author_uri;
    public string $category;
    public string $status;
    public string $path;
    public string $main_file;
    public string $icon;
    public bool $is_premium;
    public ?string $license_key;
    public ?string $license_expires;
    public string $installed_at;
    public string $updated_at;
    public ?string $latest_version;
    public array $settings;
    public array $manifest;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->description = $data['description'] ?? '';
        $this->author = $data['author'] ?? '';
        $this->author_uri = $data['author_uri'] ?? '';
        $this->category = $data['category'] ?? 'general';
        $this->status = $data['status'] ?? ExtensionStatus::INACTIVE;
        $this->path = $data['path'] ?? '';
        $this->main_file = $data['main_file'] ?? '';
        $this->icon = $data['icon'] ?? '';
        $this->is_premium = $data['is_premium'] ?? false;
        $this->license_key = $data['license_key'] ?? null;
        $this->license_expires = $data['license_expires'] ?? null;
        $this->installed_at = $data['installed_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
        $this->latest_version = $data['latest_version'] ?? null;
        $this->settings = $data['settings'] ?? [];
        $this->manifest = $data['manifest'] ?? [];
    }

    public function hasUpdate(): bool
    {
        if (empty($this->latest_version)) {
            return false;
        }
        return version_compare($this->latest_version, $this->version, '>');
    }

    public function isActive(): bool
    {
        return $this->status === ExtensionStatus::ACTIVE;
    }

    public function isLicenseValid(): bool
    {
        if (!$this->is_premium) {
            return true;
        }
        if (empty($this->license_key) || empty($this->license_expires)) {
            return false;
        }
        return strtotime($this->license_expires) > time();
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * Marketplace extension model (remote)
 */
class MarketplaceExtension
{
    public string $id;
    public string $slug;
    public string $name;
    public string $version;
    public string $description;
    public string $long_description;
    public string $author;
    public string $author_uri;
    public string $category;
    public array $tags;
    public string $icon;
    public array $screenshots;
    public string $download_url;
    public float $rating;
    public int $rating_count;
    public int $active_installs;
    public string $last_updated;
    public string $requires_php;
    public string $requires_wp;
    public string $requires_ffp;
    public bool $is_premium;
    public float $price;
    public string $currency;
    public array $features;
    public string $documentation_url;
    public string $support_url;
    public string $changelog;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->slug = $data['slug'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->version = $data['version'] ?? '1.0.0';
        $this->description = $data['description'] ?? '';
        $this->long_description = $data['long_description'] ?? '';
        $this->author = $data['author'] ?? '';
        $this->author_uri = $data['author_uri'] ?? '';
        $this->category = $data['category'] ?? 'general';
        $this->tags = $data['tags'] ?? [];
        $this->icon = $data['icon'] ?? '';
        $this->screenshots = $data['screenshots'] ?? [];
        $this->download_url = $data['download_url'] ?? '';
        $this->rating = $data['rating'] ?? 0.0;
        $this->rating_count = $data['rating_count'] ?? 0;
        $this->active_installs = $data['active_installs'] ?? 0;
        $this->last_updated = $data['last_updated'] ?? '';
        $this->requires_php = $data['requires_php'] ?? '8.1';
        $this->requires_wp = $data['requires_wp'] ?? '6.0';
        $this->requires_ffp = $data['requires_ffp'] ?? '2.0.0';
        $this->is_premium = $data['is_premium'] ?? false;
        $this->price = $data['price'] ?? 0.0;
        $this->currency = $data['currency'] ?? 'USD';
        $this->features = $data['features'] ?? [];
        $this->documentation_url = $data['documentation_url'] ?? '';
        $this->support_url = $data['support_url'] ?? '';
        $this->changelog = $data['changelog'] ?? '';
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * Extension Manager - Main class
 */
class ExtensionManager
{
    use SingletonTrait;

    private const OPTION_EXTENSIONS = 'formflow_extensions';
    private const OPTION_ACTIVE = 'formflow_active_extensions';
    private const MARKETPLACE_API = 'https://marketplace.formflowpro.com/api/v1';
    private const EXTENSIONS_DIR = 'formflow-extensions';
    private const CACHE_EXPIRY = 3600; // 1 hour

    private array $installed_extensions = [];
    private array $active_extensions = [];
    private string $extensions_path;

    /**
     * Initialize Extension Manager
     */
    public function init(): void
    {
        $this->extensions_path = WP_CONTENT_DIR . '/' . self::EXTENSIONS_DIR;
        $this->loadExtensions();
        $this->registerHooks();
        $this->registerAdminPages();
        $this->registerRestRoutes();
        $this->loadActiveExtensions();
    }

    /**
     * Load installed extensions from database
     */
    private function loadExtensions(): void
    {
        $extensions = get_option(self::OPTION_EXTENSIONS, []);
        foreach ($extensions as $slug => $data) {
            $this->installed_extensions[$slug] = new InstalledExtension($data);
        }

        $this->active_extensions = get_option(self::OPTION_ACTIVE, []);
    }

    /**
     * Save extensions to database
     */
    private function saveExtensions(): void
    {
        $data = [];
        foreach ($this->installed_extensions as $slug => $extension) {
            $data[$slug] = $extension->toArray();
        }
        update_option(self::OPTION_EXTENSIONS, $data);
        update_option(self::OPTION_ACTIVE, $this->active_extensions);
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Check for updates
        add_action('formflow_daily_cron', [$this, 'checkForUpdates']);

        // Extension lifecycle hooks
        add_action('formflow_extension_activated', [$this, 'onExtensionActivated'], 10, 2);
        add_action('formflow_extension_deactivated', [$this, 'onExtensionDeactivated']);

        // Admin notices
        add_action('admin_notices', [$this, 'displayUpdateNotices']);

        // AJAX handlers
        add_action('wp_ajax_ffp_extension_install', [$this, 'ajaxInstallExtension']);
        add_action('wp_ajax_ffp_extension_activate', [$this, 'ajaxActivateExtension']);
        add_action('wp_ajax_ffp_extension_deactivate', [$this, 'ajaxDeactivateExtension']);
        add_action('wp_ajax_ffp_extension_uninstall', [$this, 'ajaxUninstallExtension']);
        add_action('wp_ajax_ffp_extension_update', [$this, 'ajaxUpdateExtension']);
        add_action('wp_ajax_ffp_marketplace_search', [$this, 'ajaxMarketplaceSearch']);
        add_action('wp_ajax_ffp_activate_license', [$this, 'ajaxActivateLicense']);
    }

    /**
     * Load active extensions
     */
    private function loadActiveExtensions(): void
    {
        foreach ($this->active_extensions as $slug) {
            if (isset($this->installed_extensions[$slug])) {
                $extension = $this->installed_extensions[$slug];
                if ($extension->isLicenseValid()) {
                    $this->loadExtension($extension);
                }
            }
        }
    }

    /**
     * Load a single extension
     */
    private function loadExtension(InstalledExtension $extension): void
    {
        $main_file = $extension->path . '/' . $extension->main_file;
        if (file_exists($main_file)) {
            try {
                require_once $main_file;
                do_action('formflow_extension_loaded', $extension->slug, $extension);
            } catch (\Throwable $e) {
                $this->logError("Failed to load extension {$extension->slug}", $e->getMessage());
                // Deactivate broken extension
                $this->deactivateExtension($extension->slug);
            }
        }
    }

    /**
     * Install extension from marketplace
     */
    public function installExtension(string $slug, ?string $license_key = null): array
    {
        try {
            // Get extension info from marketplace
            $marketplace_extension = $this->getMarketplaceExtension($slug);
            if (!$marketplace_extension) {
                throw new \Exception(__('Extension not found in marketplace', 'formflow-pro'));
            }

            // Check requirements
            $this->checkRequirements($marketplace_extension);

            // Check license for premium extensions
            if ($marketplace_extension->is_premium) {
                if (empty($license_key)) {
                    throw new \Exception(__('License key required for premium extension', 'formflow-pro'));
                }
                $license_valid = $this->validateLicense($slug, $license_key);
                if (!$license_valid['valid']) {
                    throw new \Exception($license_valid['message']);
                }
            }

            // Download extension
            $download_url = $this->getDownloadUrl($slug, $license_key);
            $zip_path = $this->downloadExtension($download_url);

            // Extract to extensions directory
            $this->ensureExtensionsDirectory();
            $extracted_path = $this->extractExtension($zip_path, $slug);

            // Parse manifest
            $manifest = $this->parseManifest($extracted_path);

            // Create installed extension record
            $installed = new InstalledExtension([
                'id' => $marketplace_extension->id,
                'slug' => $slug,
                'name' => $manifest['name'] ?? $marketplace_extension->name,
                'version' => $manifest['version'] ?? $marketplace_extension->version,
                'description' => $manifest['description'] ?? $marketplace_extension->description,
                'author' => $manifest['author'] ?? $marketplace_extension->author,
                'author_uri' => $manifest['author_uri'] ?? $marketplace_extension->author_uri,
                'category' => $manifest['category'] ?? $marketplace_extension->category,
                'status' => ExtensionStatus::INACTIVE,
                'path' => $extracted_path,
                'main_file' => $manifest['main_file'] ?? "{$slug}.php",
                'icon' => $marketplace_extension->icon,
                'is_premium' => $marketplace_extension->is_premium,
                'license_key' => $license_key,
                'license_expires' => $license_valid['expires'] ?? null,
                'manifest' => $manifest
            ]);

            $this->installed_extensions[$slug] = $installed;
            $this->saveExtensions();

            // Cleanup
            @unlink($zip_path);

            // Run installation hook
            do_action('formflow_extension_installed', $slug, $installed);

            return [
                'success' => true,
                'message' => sprintf(__('Extension "%s" installed successfully', 'formflow-pro'), $installed->name),
                'extension' => $installed->toArray()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Activate extension
     */
    public function activateExtension(string $slug): array
    {
        try {
            if (!isset($this->installed_extensions[$slug])) {
                throw new \Exception(__('Extension not installed', 'formflow-pro'));
            }

            $extension = $this->installed_extensions[$slug];

            // Check license
            if (!$extension->isLicenseValid()) {
                throw new \Exception(__('Valid license required to activate premium extension', 'formflow-pro'));
            }

            // Run activation hook in extension if exists
            $activation_file = $extension->path . '/includes/activation.php';
            if (file_exists($activation_file)) {
                require_once $activation_file;
            }

            // Load the extension
            $this->loadExtension($extension);

            // Update status
            $extension->status = ExtensionStatus::ACTIVE;
            if (!in_array($slug, $this->active_extensions, true)) {
                $this->active_extensions[] = $slug;
            }
            $this->saveExtensions();

            do_action('formflow_extension_activated', $slug, $extension->toArray());

            return [
                'success' => true,
                'message' => sprintf(__('Extension "%s" activated', 'formflow-pro'), $extension->name)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Deactivate extension
     */
    public function deactivateExtension(string $slug): array
    {
        try {
            if (!isset($this->installed_extensions[$slug])) {
                throw new \Exception(__('Extension not installed', 'formflow-pro'));
            }

            $extension = $this->installed_extensions[$slug];

            // Run deactivation hook in extension if exists
            $deactivation_file = $extension->path . '/includes/deactivation.php';
            if (file_exists($deactivation_file)) {
                require_once $deactivation_file;
            }

            // Update status
            $extension->status = ExtensionStatus::INACTIVE;
            $this->active_extensions = array_diff($this->active_extensions, [$slug]);
            $this->saveExtensions();

            do_action('formflow_extension_deactivated', $slug);

            return [
                'success' => true,
                'message' => sprintf(__('Extension "%s" deactivated', 'formflow-pro'), $extension->name)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Uninstall extension
     */
    public function uninstallExtension(string $slug): array
    {
        try {
            // Deactivate first if active
            if (in_array($slug, $this->active_extensions, true)) {
                $this->deactivateExtension($slug);
            }

            if (!isset($this->installed_extensions[$slug])) {
                throw new \Exception(__('Extension not installed', 'formflow-pro'));
            }

            $extension = $this->installed_extensions[$slug];

            // Run uninstall hook if exists
            $uninstall_file = $extension->path . '/uninstall.php';
            if (file_exists($uninstall_file)) {
                require_once $uninstall_file;
            }

            // Delete extension files
            $this->deleteDirectory($extension->path);

            // Remove from database
            unset($this->installed_extensions[$slug]);
            $this->saveExtensions();

            do_action('formflow_extension_uninstalled', $slug);

            return [
                'success' => true,
                'message' => sprintf(__('Extension "%s" uninstalled', 'formflow-pro'), $extension->name)
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update extension
     */
    public function updateExtension(string $slug): array
    {
        try {
            if (!isset($this->installed_extensions[$slug])) {
                throw new \Exception(__('Extension not installed', 'formflow-pro'));
            }

            $extension = $this->installed_extensions[$slug];
            $was_active = $extension->isActive();

            // Deactivate during update
            if ($was_active) {
                $this->deactivateExtension($slug);
            }

            // Backup current version
            $backup_path = $extension->path . '_backup_' . $extension->version;
            rename($extension->path, $backup_path);

            try {
                // Download new version
                $download_url = $this->getDownloadUrl($slug, $extension->license_key);
                $zip_path = $this->downloadExtension($download_url);
                $this->extractExtension($zip_path, $slug);

                // Parse new manifest
                $manifest = $this->parseManifest($extension->path);

                // Run update routine if exists
                $update_file = $extension->path . '/includes/update.php';
                if (file_exists($update_file)) {
                    require_once $update_file;
                }

                // Update extension record
                $extension->version = $manifest['version'] ?? $extension->version;
                $extension->updated_at = current_time('mysql');
                $extension->latest_version = null;
                $extension->manifest = $manifest;
                $this->saveExtensions();

                // Cleanup backup
                $this->deleteDirectory($backup_path);
                @unlink($zip_path);

                // Reactivate if was active
                if ($was_active) {
                    $this->activateExtension($slug);
                }

                return [
                    'success' => true,
                    'message' => sprintf(__('Extension "%s" updated to version %s', 'formflow-pro'), $extension->name, $extension->version)
                ];

            } catch (\Exception $e) {
                // Restore backup on failure
                if (is_dir($backup_path)) {
                    if (is_dir($extension->path)) {
                        $this->deleteDirectory($extension->path);
                    }
                    rename($backup_path, $extension->path);
                }
                throw $e;
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Check for updates for all installed extensions
     */
    public function checkForUpdates(): void
    {
        $slugs = array_keys($this->installed_extensions);
        if (empty($slugs)) {
            return;
        }

        try {
            $response = wp_remote_post(self::MARKETPLACE_API . '/extensions/check-updates', [
                'timeout' => 30,
                'body' => wp_json_encode([
                    'extensions' => array_map(function ($slug) {
                        return [
                            'slug' => $slug,
                            'version' => $this->installed_extensions[$slug]->version
                        ];
                    }, $slugs)
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                return;
            }

            $updates = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($updates)) {
                return;
            }

            foreach ($updates as $slug => $latest_version) {
                if (isset($this->installed_extensions[$slug])) {
                    $this->installed_extensions[$slug]->latest_version = $latest_version;
                }
            }

            $this->saveExtensions();

        } catch (\Exception $e) {
            $this->logError('Update check failed', $e->getMessage());
        }
    }

    /**
     * Get marketplace extension info
     */
    public function getMarketplaceExtension(string $slug): ?MarketplaceExtension
    {
        $cache_key = 'ffp_marketplace_ext_' . $slug;
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return new MarketplaceExtension($cached);
        }

        try {
            $response = wp_remote_get(self::MARKETPLACE_API . '/extensions/' . $slug, [
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                return null;
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                return null;
            }

            set_transient($cache_key, $data, self::CACHE_EXPIRY);

            return new MarketplaceExtension($data);

        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Search marketplace
     */
    public function searchMarketplace(array $params = []): array
    {
        $defaults = [
            'query' => '',
            'category' => '',
            'tag' => '',
            'page' => 1,
            'per_page' => 20,
            'sort' => 'popular'
        ];

        $params = array_merge($defaults, $params);

        try {
            $response = wp_remote_get(self::MARKETPLACE_API . '/extensions', [
                'timeout' => 30,
                'body' => $params
            ]);

            if (is_wp_error($response)) {
                return ['extensions' => [], 'total' => 0];
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                return ['extensions' => [], 'total' => 0];
            }

            return [
                'extensions' => array_map(function ($item) {
                    return (new MarketplaceExtension($item))->toArray();
                }, $data['extensions'] ?? []),
                'total' => $data['total'] ?? 0,
                'pages' => $data['pages'] ?? 1
            ];

        } catch (\Exception $e) {
            return ['extensions' => [], 'total' => 0];
        }
    }

    /**
     * Get featured extensions
     */
    public function getFeaturedExtensions(): array
    {
        $cache_key = 'ffp_featured_extensions';
        $cached = get_transient($cache_key);

        if ($cached !== false) {
            return $cached;
        }

        try {
            $response = wp_remote_get(self::MARKETPLACE_API . '/extensions/featured', [
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                return [];
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);
            if (!is_array($data)) {
                return [];
            }

            $extensions = array_map(function ($item) {
                return (new MarketplaceExtension($item))->toArray();
            }, $data);

            set_transient($cache_key, $extensions, self::CACHE_EXPIRY);

            return $extensions;

        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get extension categories
     */
    public function getCategories(): array
    {
        return [
            'field-types' => __('Field Types', 'formflow-pro'),
            'integrations' => __('Integrations', 'formflow-pro'),
            'workflow-actions' => __('Workflow Actions', 'formflow-pro'),
            'notifications' => __('Notifications', 'formflow-pro'),
            'payments' => __('Payments', 'formflow-pro'),
            'analytics' => __('Analytics', 'formflow-pro'),
            'security' => __('Security', 'formflow-pro'),
            'templates' => __('Templates', 'formflow-pro'),
            'utilities' => __('Utilities', 'formflow-pro')
        ];
    }

    /**
     * Get installed extensions
     */
    public function getInstalledExtensions(): array
    {
        return $this->installed_extensions;
    }

    /**
     * Get active extensions
     */
    public function getActiveExtensions(): array
    {
        return array_filter($this->installed_extensions, function ($ext) {
            return $ext->isActive();
        });
    }

    /**
     * Check if extension is installed
     */
    public function isInstalled(string $slug): bool
    {
        return isset($this->installed_extensions[$slug]);
    }

    /**
     * Check if extension is active
     */
    public function isActive(string $slug): bool
    {
        return in_array($slug, $this->active_extensions, true);
    }

    /**
     * Validate license key
     */
    private function validateLicense(string $slug, string $license_key): array
    {
        try {
            $response = wp_remote_post(self::MARKETPLACE_API . '/licenses/validate', [
                'timeout' => 30,
                'body' => wp_json_encode([
                    'extension' => $slug,
                    'license_key' => $license_key,
                    'site_url' => home_url()
                ]),
                'headers' => [
                    'Content-Type' => 'application/json'
                ]
            ]);

            if (is_wp_error($response)) {
                return [
                    'valid' => false,
                    'message' => $response->get_error_message()
                ];
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            return [
                'valid' => $data['valid'] ?? false,
                'message' => $data['message'] ?? __('License validation failed', 'formflow-pro'),
                'expires' => $data['expires'] ?? null
            ];

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get download URL (with license if premium)
     */
    private function getDownloadUrl(string $slug, ?string $license_key = null): string
    {
        $url = self::MARKETPLACE_API . '/extensions/' . $slug . '/download';

        if ($license_key) {
            $url = add_query_arg([
                'license_key' => $license_key,
                'site_url' => home_url()
            ], $url);
        }

        return $url;
    }

    /**
     * Download extension zip file
     */
    private function downloadExtension(string $url): string
    {
        $response = wp_remote_get($url, [
            'timeout' => 120,
            'stream' => true,
            'filename' => wp_tempnam('ffp_ext_')
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        if ($code !== 200) {
            throw new \Exception(__('Failed to download extension', 'formflow-pro'));
        }

        return $response['filename'];
    }

    /**
     * Extract extension from zip
     */
    private function extractExtension(string $zip_path, string $slug): string
    {
        WP_Filesystem();
        $result = unzip_file($zip_path, $this->extensions_path);

        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        $extracted_path = $this->extensions_path . '/' . $slug;
        if (!is_dir($extracted_path)) {
            throw new \Exception(__('Extension extraction failed', 'formflow-pro'));
        }

        return $extracted_path;
    }

    /**
     * Parse extension manifest
     */
    private function parseManifest(string $path): array
    {
        $manifest_path = $path . '/manifest.json';
        if (!file_exists($manifest_path)) {
            return [];
        }

        $content = file_get_contents($manifest_path);
        $manifest = json_decode($content, true);

        return is_array($manifest) ? $manifest : [];
    }

    /**
     * Check requirements
     */
    private function checkRequirements(MarketplaceExtension $extension): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, $extension->requires_php, '<')) {
            throw new \Exception(sprintf(
                __('This extension requires PHP %s or higher', 'formflow-pro'),
                $extension->requires_php
            ));
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, $extension->requires_wp, '<')) {
            throw new \Exception(sprintf(
                __('This extension requires WordPress %s or higher', 'formflow-pro'),
                $extension->requires_wp
            ));
        }

        // Check FormFlow Pro version
        $ffp_version = defined('FORMFLOW_PRO_VERSION') ? FORMFLOW_PRO_VERSION : '2.0.0';
        if (version_compare($ffp_version, $extension->requires_ffp, '<')) {
            throw new \Exception(sprintf(
                __('This extension requires FormFlow Pro %s or higher', 'formflow-pro'),
                $extension->requires_ffp
            ));
        }
    }

    /**
     * Ensure extensions directory exists
     */
    private function ensureExtensionsDirectory(): void
    {
        if (!is_dir($this->extensions_path)) {
            wp_mkdir_p($this->extensions_path);

            // Add index.php for security
            file_put_contents($this->extensions_path . '/index.php', '<?php // Silence is golden');
        }
    }

    /**
     * Delete directory recursively
     */
    private function deleteDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $file_path = $path . '/' . $file;
            if (is_dir($file_path)) {
                $this->deleteDirectory($file_path);
            } else {
                unlink($file_path);
            }
        }

        return rmdir($path);
    }

    /**
     * Register admin pages
     */
    private function registerAdminPages(): void
    {
        add_action('admin_menu', function () {
            add_submenu_page(
                'formflow-pro',
                __('Marketplace', 'formflow-pro'),
                __('Marketplace', 'formflow-pro'),
                'manage_options',
                'formflow-marketplace',
                [$this, 'renderMarketplacePage']
            );

            add_submenu_page(
                'formflow-pro',
                __('Extensions', 'formflow-pro'),
                __('Extensions', 'formflow-pro'),
                'manage_options',
                'formflow-extensions',
                [$this, 'renderExtensionsPage']
            );
        });

        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets(string $hook): void
    {
        if (!in_array($hook, ['formflow-pro_page_formflow-marketplace', 'formflow-pro_page_formflow-extensions'], true)) {
            return;
        }

        wp_enqueue_style('ffp-marketplace', plugins_url('assets/css/marketplace.css', dirname(__DIR__)), [], '1.0.0');
        wp_enqueue_script('ffp-marketplace', plugins_url('assets/js/marketplace.js', dirname(__DIR__)), ['jquery', 'wp-util'], '1.0.0', true);

        wp_localize_script('ffp-marketplace', 'ffpMarketplace', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ffp_marketplace'),
            'i18n' => [
                'installing' => __('Installing...', 'formflow-pro'),
                'activating' => __('Activating...', 'formflow-pro'),
                'deactivating' => __('Deactivating...', 'formflow-pro'),
                'uninstalling' => __('Uninstalling...', 'formflow-pro'),
                'updating' => __('Updating...', 'formflow-pro'),
                'confirmUninstall' => __('Are you sure you want to uninstall this extension? This cannot be undone.', 'formflow-pro'),
                'licenseRequired' => __('Please enter your license key', 'formflow-pro')
            ]
        ]);
    }

    /**
     * Render marketplace page
     */
    public function renderMarketplacePage(): void
    {
        $categories = $this->getCategories();
        $featured = $this->getFeaturedExtensions();
        ?>
        <div class="wrap ffp-marketplace-page">
            <h1><?php esc_html_e('FormFlow Pro Marketplace', 'formflow-pro'); ?></h1>

            <div class="ffp-marketplace-header">
                <div class="ffp-marketplace-search">
                    <input type="text" id="ffp-marketplace-search" placeholder="<?php esc_attr_e('Search extensions...', 'formflow-pro'); ?>" />
                    <button type="button" class="button" id="ffp-search-btn">
                        <span class="dashicons dashicons-search"></span>
                    </button>
                </div>

                <div class="ffp-marketplace-filters">
                    <select id="ffp-category-filter">
                        <option value=""><?php esc_html_e('All Categories', 'formflow-pro'); ?></option>
                        <?php foreach ($categories as $value => $label) : ?>
                            <option value="<?php echo esc_attr($value); ?>"><?php echo esc_html($label); ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="ffp-sort-filter">
                        <option value="popular"><?php esc_html_e('Most Popular', 'formflow-pro'); ?></option>
                        <option value="newest"><?php esc_html_e('Newest', 'formflow-pro'); ?></option>
                        <option value="rating"><?php esc_html_e('Highest Rated', 'formflow-pro'); ?></option>
                        <option value="name"><?php esc_html_e('Alphabetical', 'formflow-pro'); ?></option>
                    </select>
                </div>
            </div>

            <?php if (!empty($featured)) : ?>
                <div class="ffp-featured-extensions">
                    <h2><?php esc_html_e('Featured Extensions', 'formflow-pro'); ?></h2>
                    <div class="ffp-extension-grid">
                        <?php foreach ($featured as $extension) : ?>
                            <?php $this->renderExtensionCard($extension); ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="ffp-all-extensions">
                <h2><?php esc_html_e('All Extensions', 'formflow-pro'); ?></h2>
                <div id="ffp-extension-grid" class="ffp-extension-grid">
                    <div class="ffp-loading">
                        <span class="spinner is-active"></span>
                        <?php esc_html_e('Loading extensions...', 'formflow-pro'); ?>
                    </div>
                </div>
                <div id="ffp-pagination" class="ffp-pagination"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render single extension card
     */
    private function renderExtensionCard(array $extension): void
    {
        $is_installed = $this->isInstalled($extension['slug']);
        $is_active = $this->isActive($extension['slug']);
        ?>
        <div class="ffp-extension-card" data-slug="<?php echo esc_attr($extension['slug']); ?>">
            <div class="ffp-extension-icon">
                <?php if (!empty($extension['icon'])) : ?>
                    <img src="<?php echo esc_url($extension['icon']); ?>" alt="<?php echo esc_attr($extension['name']); ?>" />
                <?php else : ?>
                    <span class="dashicons dashicons-admin-plugins"></span>
                <?php endif; ?>
            </div>

            <div class="ffp-extension-info">
                <h3><?php echo esc_html($extension['name']); ?></h3>
                <p class="ffp-extension-author">
                    <?php echo esc_html(sprintf(__('by %s', 'formflow-pro'), $extension['author'])); ?>
                </p>
                <p class="ffp-extension-desc"><?php echo esc_html($extension['description']); ?></p>

                <div class="ffp-extension-meta">
                    <span class="ffp-rating" title="<?php echo esc_attr(sprintf(__('%s out of 5 stars', 'formflow-pro'), $extension['rating'])); ?>">
                        <?php for ($i = 1; $i <= 5; $i++) : ?>
                            <span class="dashicons dashicons-star-<?php echo $i <= $extension['rating'] ? 'filled' : 'empty'; ?>"></span>
                        <?php endfor; ?>
                        <span class="rating-count">(<?php echo esc_html($extension['rating_count']); ?>)</span>
                    </span>
                    <span class="ffp-installs">
                        <?php echo esc_html(sprintf(__('%s+ active installs', 'formflow-pro'), number_format($extension['active_installs']))); ?>
                    </span>
                </div>
            </div>

            <div class="ffp-extension-actions">
                <?php if ($extension['is_premium']) : ?>
                    <span class="ffp-premium-badge"><?php esc_html_e('Premium', 'formflow-pro'); ?></span>
                    <span class="ffp-price">
                        <?php echo esc_html(sprintf('$%s', number_format($extension['price'], 2))); ?>
                    </span>
                <?php else : ?>
                    <span class="ffp-free-badge"><?php esc_html_e('Free', 'formflow-pro'); ?></span>
                <?php endif; ?>

                <?php if ($is_installed) : ?>
                    <?php if ($is_active) : ?>
                        <button type="button" class="button ffp-deactivate-btn" data-slug="<?php echo esc_attr($extension['slug']); ?>">
                            <?php esc_html_e('Deactivate', 'formflow-pro'); ?>
                        </button>
                    <?php else : ?>
                        <button type="button" class="button button-primary ffp-activate-btn" data-slug="<?php echo esc_attr($extension['slug']); ?>">
                            <?php esc_html_e('Activate', 'formflow-pro'); ?>
                        </button>
                    <?php endif; ?>
                <?php else : ?>
                    <button type="button" class="button button-primary ffp-install-btn" data-slug="<?php echo esc_attr($extension['slug']); ?>" data-premium="<?php echo $extension['is_premium'] ? '1' : '0'; ?>">
                        <?php esc_html_e('Install', 'formflow-pro'); ?>
                    </button>
                <?php endif; ?>

                <button type="button" class="button ffp-details-btn" data-slug="<?php echo esc_attr($extension['slug']); ?>">
                    <?php esc_html_e('Details', 'formflow-pro'); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Render extensions page (installed)
     */
    public function renderExtensionsPage(): void
    {
        $installed = $this->getInstalledExtensions();
        ?>
        <div class="wrap ffp-extensions-page">
            <h1>
                <?php esc_html_e('Installed Extensions', 'formflow-pro'); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-marketplace')); ?>" class="page-title-action">
                    <?php esc_html_e('Add New', 'formflow-pro'); ?>
                </a>
            </h1>

            <?php if (empty($installed)) : ?>
                <div class="ffp-no-extensions">
                    <p><?php esc_html_e('No extensions installed yet.', 'formflow-pro'); ?></p>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-marketplace')); ?>" class="button button-primary">
                        <?php esc_html_e('Browse Marketplace', 'formflow-pro'); ?>
                    </a>
                </div>
            <?php else : ?>
                <table class="wp-list-table widefat plugins">
                    <thead>
                        <tr>
                            <th scope="col" class="manage-column"><?php esc_html_e('Extension', 'formflow-pro'); ?></th>
                            <th scope="col" class="manage-column"><?php esc_html_e('Description', 'formflow-pro'); ?></th>
                            <th scope="col" class="manage-column"><?php esc_html_e('Version', 'formflow-pro'); ?></th>
                            <th scope="col" class="manage-column"><?php esc_html_e('Status', 'formflow-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($installed as $extension) : ?>
                            <tr class="<?php echo $extension->isActive() ? 'active' : 'inactive'; ?>">
                                <td class="plugin-title column-primary">
                                    <strong><?php echo esc_html($extension->name); ?></strong>
                                    <div class="row-actions">
                                        <?php if ($extension->isActive()) : ?>
                                            <span class="deactivate">
                                                <a href="#" class="ffp-deactivate-btn" data-slug="<?php echo esc_attr($extension->slug); ?>">
                                                    <?php esc_html_e('Deactivate', 'formflow-pro'); ?>
                                                </a>
                                            </span>
                                        <?php else : ?>
                                            <span class="activate">
                                                <a href="#" class="ffp-activate-btn" data-slug="<?php echo esc_attr($extension->slug); ?>">
                                                    <?php esc_html_e('Activate', 'formflow-pro'); ?>
                                                </a>
                                            </span>
                                            <span class="delete">
                                                | <a href="#" class="ffp-uninstall-btn" data-slug="<?php echo esc_attr($extension->slug); ?>">
                                                    <?php esc_html_e('Delete', 'formflow-pro'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                        <?php if ($extension->hasUpdate()) : ?>
                                            <span class="update">
                                                | <a href="#" class="ffp-update-btn" data-slug="<?php echo esc_attr($extension->slug); ?>">
                                                    <?php esc_html_e('Update', 'formflow-pro'); ?>
                                                </a>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="column-description">
                                    <p><?php echo esc_html($extension->description); ?></p>
                                    <p class="author">
                                        <?php echo esc_html(sprintf(__('By %s', 'formflow-pro'), $extension->author)); ?>
                                    </p>
                                </td>
                                <td class="column-version">
                                    <?php echo esc_html($extension->version); ?>
                                    <?php if ($extension->hasUpdate()) : ?>
                                        <br /><span class="update-message">
                                            <?php echo esc_html(sprintf(__('Update to %s available', 'formflow-pro'), $extension->latest_version)); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="column-status">
                                    <?php if ($extension->isActive()) : ?>
                                        <span class="ffp-status-active"><?php esc_html_e('Active', 'formflow-pro'); ?></span>
                                    <?php else : ?>
                                        <span class="ffp-status-inactive"><?php esc_html_e('Inactive', 'formflow-pro'); ?></span>
                                    <?php endif; ?>
                                    <?php if ($extension->is_premium) : ?>
                                        <br />
                                        <?php if ($extension->isLicenseValid()) : ?>
                                            <span class="ffp-license-valid" title="<?php echo esc_attr(sprintf(__('Expires: %s', 'formflow-pro'), $extension->license_expires)); ?>">
                                                <?php esc_html_e('Licensed', 'formflow-pro'); ?>
                                            </span>
                                        <?php else : ?>
                                            <span class="ffp-license-invalid">
                                                <?php esc_html_e('License Required', 'formflow-pro'); ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Display update notices
     */
    public function displayUpdateNotices(): void
    {
        $updates = array_filter($this->installed_extensions, function ($ext) {
            return $ext->hasUpdate();
        });

        if (empty($updates)) {
            return;
        }

        $count = count($updates);
        ?>
        <div class="notice notice-info">
            <p>
                <?php echo esc_html(sprintf(
                    _n(
                        '%d FormFlow Pro extension has an update available.',
                        '%d FormFlow Pro extensions have updates available.',
                        $count,
                        'formflow-pro'
                    ),
                    $count
                )); ?>
                <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-extensions')); ?>">
                    <?php esc_html_e('View updates', 'formflow-pro'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    /**
     * Register REST routes
     */
    private function registerRestRoutes(): void
    {
        add_action('rest_api_init', function () {
            register_rest_route('formflow-pro/v1', '/marketplace/extensions', [
                'methods' => 'GET',
                'callback' => [$this, 'restSearchExtensions'],
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('formflow-pro/v1', '/marketplace/extensions/(?P<slug>[a-z0-9-]+)', [
                'methods' => 'GET',
                'callback' => [$this, 'restGetExtension'],
                'permission_callback' => '__return_true'
            ]);

            register_rest_route('formflow-pro/v1', '/extensions', [
                'methods' => 'GET',
                'callback' => [$this, 'restGetInstalledExtensions'],
                'permission_callback' => [$this, 'restPermissionCheck']
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
     * REST: Search extensions
     */
    public function restSearchExtensions(\WP_REST_Request $request): \WP_REST_Response
    {
        $results = $this->searchMarketplace($request->get_params());
        return new \WP_REST_Response($results);
    }

    /**
     * REST: Get single extension
     */
    public function restGetExtension(\WP_REST_Request $request): \WP_REST_Response
    {
        $extension = $this->getMarketplaceExtension($request->get_param('slug'));
        if (!$extension) {
            return new \WP_REST_Response(['message' => __('Extension not found', 'formflow-pro')], 404);
        }
        return new \WP_REST_Response($extension->toArray());
    }

    /**
     * REST: Get installed extensions
     */
    public function restGetInstalledExtensions(\WP_REST_Request $request): \WP_REST_Response
    {
        $extensions = array_map(function ($ext) {
            return $ext->toArray();
        }, $this->installed_extensions);

        return new \WP_REST_Response(array_values($extensions));
    }

    /**
     * AJAX: Install extension
     */
    public function ajaxInstallExtension(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');

        $result = $this->installExtension($slug, $license_key ?: null);
        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Activate extension
     */
    public function ajaxActivateExtension(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $result = $this->activateExtension($slug);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Deactivate extension
     */
    public function ajaxDeactivateExtension(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $result = $this->deactivateExtension($slug);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Uninstall extension
     */
    public function ajaxUninstallExtension(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $result = $this->uninstallExtension($slug);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Update extension
     */
    public function ajaxUpdateExtension(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $result = $this->updateExtension($slug);

        if ($result['success']) {
            wp_send_json_success($result);
        } else {
            wp_send_json_error($result);
        }
    }

    /**
     * AJAX: Search marketplace
     */
    public function ajaxMarketplaceSearch(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        $params = [
            'query' => sanitize_text_field($_POST['query'] ?? ''),
            'category' => sanitize_text_field($_POST['category'] ?? ''),
            'page' => absint($_POST['page'] ?? 1),
            'sort' => sanitize_text_field($_POST['sort'] ?? 'popular')
        ];

        $results = $this->searchMarketplace($params);
        wp_send_json_success($results);
    }

    /**
     * AJAX: Activate license
     */
    public function ajaxActivateLicense(): void
    {
        check_ajax_referer('ffp_marketplace', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Permission denied', 'formflow-pro')]);
        }

        $slug = sanitize_text_field($_POST['slug'] ?? '');
        $license_key = sanitize_text_field($_POST['license_key'] ?? '');

        if (empty($license_key)) {
            wp_send_json_error(['message' => __('License key is required', 'formflow-pro')]);
        }

        $validation = $this->validateLicense($slug, $license_key);

        if ($validation['valid']) {
            if (isset($this->installed_extensions[$slug])) {
                $this->installed_extensions[$slug]->license_key = $license_key;
                $this->installed_extensions[$slug]->license_expires = $validation['expires'];
                $this->saveExtensions();
            }
            wp_send_json_success(['message' => __('License activated successfully', 'formflow-pro')]);
        } else {
            wp_send_json_error(['message' => $validation['message']]);
        }
    }

    /**
     * Extension activated callback
     */
    public function onExtensionActivated(string $slug, array $data): void
    {
        // Log activation
        $this->log('Extension activated: ' . $slug);
    }

    /**
     * Extension deactivated callback
     */
    public function onExtensionDeactivated(string $slug): void
    {
        // Log deactivation
        $this->log('Extension deactivated: ' . $slug);
    }

    /**
     * Log message
     */
    private function log(string $message): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FormFlow Pro Extensions] ' . $message);
        }
    }

    /**
     * Log error
     */
    private function logError(string $context, string $message): void
    {
        error_log('[FormFlow Pro Extensions Error] ' . $context . ': ' . $message);
    }
}
