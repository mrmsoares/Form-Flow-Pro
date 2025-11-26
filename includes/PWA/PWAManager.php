<?php
/**
 * FormFlow Pro - PWA Manager
 *
 * Central manager for Progressive Web App features including manifest,
 * mobile preview, and installability.
 *
 * @package FormFlowPro
 * @subpackage PWA
 * @since 3.0.0
 */

namespace FormFlowPro\PWA;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * PWA Manager
 */
class PWAManager
{
    use SingletonTrait;

    private ServiceWorkerManager $sw_manager;
    private MobilePreview $mobile_preview;

    protected function init(): void
    {
        $this->sw_manager = ServiceWorkerManager::getInstance();
        $this->mobile_preview = MobilePreview::getInstance();

        $this->registerHooks();
    }

    private function registerHooks(): void
    {
        // Admin menu
        add_action('admin_menu', [$this, 'registerAdminMenu']);

        // REST API
        add_action('rest_api_init', [$this, 'registerRestRoutes']);

        // Admin assets
        add_action('admin_enqueue_scripts', [$this, 'enqueueAdminAssets']);

        // Frontend
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontendAssets']);

        // AJAX
        add_action('wp_ajax_ffp_pwa_save_settings', [$this, 'ajaxSaveSettings']);
        add_action('wp_ajax_ffp_pwa_test_manifest', [$this, 'ajaxTestManifest']);
        add_action('wp_ajax_ffp_pwa_clear_cache', [$this, 'ajaxClearCache']);
    }

    /**
     * Get Service Worker Manager
     */
    public function getServiceWorkerManager(): ServiceWorkerManager
    {
        return $this->sw_manager;
    }

    /**
     * Get Mobile Preview
     */
    public function getMobilePreview(): MobilePreview
    {
        return $this->mobile_preview;
    }

    /**
     * Check if PWA is enabled
     */
    public function isEnabled(): bool
    {
        return $this->sw_manager->isPWAEnabled();
    }

    /**
     * Enable PWA
     */
    public function enable(): void
    {
        update_option('ffp_pwa_enabled', true);

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Disable PWA
     */
    public function disable(): void
    {
        update_option('ffp_pwa_enabled', false);
    }

    /**
     * Get manifest data
     */
    public function getManifest(): array
    {
        $site_name = get_bloginfo('name');
        $site_description = get_bloginfo('description');

        return [
            'name' => get_option('ffp_pwa_name', $site_name . ' Forms'),
            'short_name' => get_option('ffp_pwa_short_name', substr($site_name, 0, 12)),
            'description' => get_option('ffp_pwa_description', $site_description ?: 'Fill out forms easily'),
            'start_url' => home_url('/?utm_source=pwa'),
            'scope' => '/',
            'display' => get_option('ffp_pwa_display', 'standalone'),
            'orientation' => get_option('ffp_pwa_orientation', 'any'),
            'theme_color' => get_option('ffp_pwa_theme_color', '#3b82f6'),
            'background_color' => get_option('ffp_pwa_background_color', '#ffffff'),
            'icons' => $this->getIcons(),
            'screenshots' => $this->getScreenshots(),
            'categories' => ['productivity', 'business'],
            'prefer_related_applications' => false,
            'shortcuts' => $this->getShortcuts(),
            'share_target' => $this->getShareTarget(),
        ];
    }

    /**
     * Get icons array for manifest
     */
    private function getIcons(): array
    {
        $icons = [];
        $custom_icon = get_option('ffp_pwa_icon', '');
        $default_path = plugins_url('assets/images/', dirname(__DIR__));

        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

        foreach ($sizes as $size) {
            $icons[] = [
                'src' => $custom_icon ?: $default_path . "icon-{$size}.png",
                'sizes' => "{$size}x{$size}",
                'type' => 'image/png',
                'purpose' => 'any maskable',
            ];
        }

        return $icons;
    }

    /**
     * Get screenshots for manifest
     */
    private function getScreenshots(): array
    {
        $screenshots = [];

        // Add custom screenshots if configured
        $custom_screenshots = get_option('ffp_pwa_screenshots', []);

        if (!empty($custom_screenshots)) {
            foreach ($custom_screenshots as $screenshot) {
                $screenshots[] = [
                    'src' => $screenshot['url'],
                    'sizes' => $screenshot['sizes'] ?? '1280x720',
                    'type' => 'image/png',
                    'form_factor' => $screenshot['form_factor'] ?? 'wide',
                    'label' => $screenshot['label'] ?? 'Screenshot',
                ];
            }
        }

        return $screenshots;
    }

    /**
     * Get shortcuts for manifest
     */
    private function getShortcuts(): array
    {
        $shortcuts = [];

        // Get popular forms for shortcuts
        global $wpdb;
        $table = $wpdb->prefix . 'ffp_forms';

        $forms = $wpdb->get_results(
            "SELECT id, title FROM {$table}
             WHERE status = 'published'
             ORDER BY submissions DESC
             LIMIT 4",
            ARRAY_A
        );

        foreach ($forms as $form) {
            $shortcuts[] = [
                'name' => $form['title'],
                'short_name' => substr($form['title'], 0, 20),
                'url' => home_url('/?ffp_form=' . $form['id']),
                'icons' => [[
                    'src' => plugins_url('assets/images/shortcut-icon.png', dirname(__DIR__)),
                    'sizes' => '96x96',
                ]],
            ];
        }

        return $shortcuts;
    }

    /**
     * Get share target configuration
     */
    private function getShareTarget(): array
    {
        return [
            'action' => home_url('/ffp-share-target/'),
            'method' => 'POST',
            'enctype' => 'multipart/form-data',
            'params' => [
                'title' => 'title',
                'text' => 'text',
                'url' => 'url',
            ],
        ];
    }

    /**
     * Get PWA status
     */
    public function getStatus(): array
    {
        $status = [
            'enabled' => $this->isEnabled(),
            'service_worker_registered' => false,
            'manifest_valid' => true,
            'https' => is_ssl(),
            'icons_present' => $this->checkIcons(),
            'installable' => false,
            'issues' => [],
        ];

        // Check issues
        if (!$status['https']) {
            $status['issues'][] = [
                'type' => 'error',
                'message' => __('PWA requires HTTPS. Your site is not using a secure connection.', 'form-flow-pro'),
            ];
        }

        if (!$status['icons_present']) {
            $status['issues'][] = [
                'type' => 'warning',
                'message' => __('PWA icons are missing. Upload icons for best experience.', 'form-flow-pro'),
            ];
        }

        // Check manifest validity
        $manifest = $this->getManifest();
        if (empty($manifest['name']) || strlen($manifest['name']) < 2) {
            $status['manifest_valid'] = false;
            $status['issues'][] = [
                'type' => 'error',
                'message' => __('App name is required and must be at least 2 characters.', 'form-flow-pro'),
            ];
        }

        $status['installable'] = $status['enabled'] &&
                                  $status['https'] &&
                                  $status['manifest_valid'] &&
                                  empty(array_filter($status['issues'], fn($i) => $i['type'] === 'error'));

        return $status;
    }

    /**
     * Check if icons are present
     */
    private function checkIcons(): bool
    {
        $custom_icon = get_option('ffp_pwa_icon', '');

        if ($custom_icon) {
            return true;
        }

        // Check for default icons
        $icon_path = dirname(__DIR__) . '/assets/images/icon-192.png';
        return file_exists($icon_path);
    }

    /**
     * Generate icons from uploaded image
     */
    public function generateIcons(int $attachment_id): array
    {
        $generated = [];
        $sizes = [72, 96, 128, 144, 152, 192, 384, 512];

        $upload_dir = wp_upload_dir();
        $icon_dir = $upload_dir['basedir'] . '/ffp-pwa-icons/';

        if (!file_exists($icon_dir)) {
            wp_mkdir_p($icon_dir);
        }

        $source = get_attached_file($attachment_id);

        if (!$source || !file_exists($source)) {
            return $generated;
        }

        $editor = wp_get_image_editor($source);

        if (is_wp_error($editor)) {
            return $generated;
        }

        foreach ($sizes as $size) {
            $resized = wp_get_image_editor($source);

            if (is_wp_error($resized)) {
                continue;
            }

            $resized->resize($size, $size, true);
            $filename = $icon_dir . "icon-{$size}.png";
            $resized->save($filename, 'image/png');

            $generated[$size] = $upload_dir['baseurl'] . '/ffp-pwa-icons/icon-' . $size . '.png';
        }

        // Store icon URLs
        update_option('ffp_pwa_generated_icons', $generated);

        return $generated;
    }

    /**
     * Get install banner HTML
     */
    public function getInstallBanner(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        $app_name = get_option('ffp_pwa_short_name', get_bloginfo('name'));
        $icon = get_option('ffp_pwa_icon', plugins_url('assets/images/icon-192.png', dirname(__DIR__)));

        ob_start();
        ?>
        <div id="ffp-install-banner" class="ffp-install-banner" style="display: none;">
            <div class="ffp-install-banner-content">
                <img src="<?php echo esc_url($icon); ?>" alt="" class="ffp-install-icon">
                <div class="ffp-install-text">
                    <strong><?php echo esc_html($app_name); ?></strong>
                    <span><?php _e('Install for quick access', 'form-flow-pro'); ?></span>
                </div>
            </div>
            <div class="ffp-install-actions">
                <button type="button" class="ffp-install-dismiss"><?php _e('Not now', 'form-flow-pro'); ?></button>
                <button type="button" class="ffp-install-accept"><?php _e('Install', 'form-flow-pro'); ?></button>
            </div>
        </div>
        <style>
            .ffp-install-banner {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: white;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.15);
                padding: 16px;
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: space-between;
                animation: ffp-slide-up 0.3s ease;
            }

            @keyframes ffp-slide-up {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }

            .ffp-install-banner-content {
                display: flex;
                align-items: center;
                gap: 12px;
            }

            .ffp-install-icon {
                width: 48px;
                height: 48px;
                border-radius: 12px;
            }

            .ffp-install-text {
                display: flex;
                flex-direction: column;
            }

            .ffp-install-text strong {
                font-size: 16px;
                color: #1e293b;
            }

            .ffp-install-text span {
                font-size: 14px;
                color: #64748b;
            }

            .ffp-install-actions {
                display: flex;
                gap: 8px;
            }

            .ffp-install-dismiss {
                background: none;
                border: none;
                color: #64748b;
                font-size: 14px;
                cursor: pointer;
                padding: 8px 16px;
            }

            .ffp-install-accept {
                background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                color: white;
                border: none;
                padding: 10px 24px;
                border-radius: 8px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: transform 0.2s;
            }

            .ffp-install-accept:hover {
                transform: scale(1.02);
            }

            @media (max-width: 480px) {
                .ffp-install-banner {
                    flex-direction: column;
                    gap: 12px;
                }

                .ffp-install-actions {
                    width: 100%;
                    justify-content: flex-end;
                }
            }
        </style>
        <script>
        (function() {
            let deferredPrompt;
            const banner = document.getElementById('ffp-install-banner');

            if (!banner) return;

            // Check if already installed
            if (window.matchMedia('(display-mode: standalone)').matches) {
                return;
            }

            // Check if dismissed recently
            const dismissed = localStorage.getItem('ffp_install_dismissed');
            if (dismissed && Date.now() - parseInt(dismissed) < 7 * 24 * 60 * 60 * 1000) {
                return;
            }

            window.addEventListener('beforeinstallprompt', (e) => {
                e.preventDefault();
                deferredPrompt = e;
                banner.style.display = 'flex';
            });

            banner.querySelector('.ffp-install-accept').addEventListener('click', async () => {
                if (!deferredPrompt) return;

                deferredPrompt.prompt();
                const { outcome } = await deferredPrompt.userChoice;

                if (outcome === 'accepted') {
                    console.log('[FFP PWA] App installed');
                }

                deferredPrompt = null;
                banner.style.display = 'none';
            });

            banner.querySelector('.ffp-install-dismiss').addEventListener('click', () => {
                banner.style.display = 'none';
                localStorage.setItem('ffp_install_dismissed', Date.now().toString());
            });

            window.addEventListener('appinstalled', () => {
                banner.style.display = 'none';
                console.log('[FFP PWA] App installed successfully');
            });
        })();
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * Register admin menu
     */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            'formflow-pro',
            __('Mobile & PWA', 'form-flow-pro'),
            __('Mobile & PWA', 'form-flow-pro'),
            'manage_options',
            'formflow-pro-pwa',
            [$this, 'renderAdminPage']
        );
    }

    /**
     * Register REST routes
     */
    public function registerRestRoutes(): void
    {
        // Manifest endpoint
        register_rest_route('form-flow-pro/v1', '/manifest.json', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetManifest'],
            'permission_callback' => '__return_true',
        ]);

        // PWA status
        register_rest_route('form-flow-pro/v1', '/pwa/status', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetStatus'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        // PWA settings
        register_rest_route('form-flow-pro/v1', '/pwa/settings', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetSettings'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restSaveSettings'],
                'permission_callback' => function() {
                    return current_user_can('manage_options');
                },
            ],
        ]);
    }

    /**
     * REST: Get manifest
     */
    public function restGetManifest(): \WP_REST_Response
    {
        $response = new \WP_REST_Response($this->getManifest());
        $response->header('Content-Type', 'application/manifest+json');
        return $response;
    }

    /**
     * REST: Get status
     */
    public function restGetStatus(): \WP_REST_Response
    {
        return new \WP_REST_Response($this->getStatus());
    }

    /**
     * REST: Get settings
     */
    public function restGetSettings(): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'enabled' => $this->isEnabled(),
            'name' => get_option('ffp_pwa_name', get_bloginfo('name') . ' Forms'),
            'short_name' => get_option('ffp_pwa_short_name', substr(get_bloginfo('name'), 0, 12)),
            'description' => get_option('ffp_pwa_description', ''),
            'theme_color' => get_option('ffp_pwa_theme_color', '#3b82f6'),
            'background_color' => get_option('ffp_pwa_background_color', '#ffffff'),
            'display' => get_option('ffp_pwa_display', 'standalone'),
            'orientation' => get_option('ffp_pwa_orientation', 'any'),
            'icon' => get_option('ffp_pwa_icon', ''),
            'offline_forms' => get_option('ffp_pwa_offline_forms', true),
            'background_sync' => get_option('ffp_pwa_background_sync', true),
            'push_notifications' => get_option('ffp_pwa_push_notifications', true),
            'install_banner' => get_option('ffp_pwa_install_banner', true),
            'offline_message' => get_option('ffp_pwa_offline_message', ''),
        ]);
    }

    /**
     * REST: Save settings
     */
    public function restSaveSettings(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();

        if (isset($data['enabled'])) {
            update_option('ffp_pwa_enabled', (bool) $data['enabled']);
        }

        $text_fields = ['name', 'short_name', 'description', 'theme_color', 'background_color', 'display', 'orientation', 'icon', 'offline_message'];

        foreach ($text_fields as $field) {
            if (isset($data[$field])) {
                update_option('ffp_pwa_' . $field, sanitize_text_field($data[$field]));
            }
        }

        $bool_fields = ['offline_forms', 'background_sync', 'push_notifications', 'install_banner'];

        foreach ($bool_fields as $field) {
            if (isset($data[$field])) {
                update_option('ffp_pwa_' . $field, (bool) $data[$field]);
            }
        }

        // Bump SW version to apply changes
        $this->sw_manager->bumpVersion();

        return new \WP_REST_Response(['success' => true]);
    }

    /**
     * Enqueue admin assets
     */
    public function enqueueAdminAssets($hook): void
    {
        if ($hook !== 'formflow-pro_page_formflow-pro-pwa') {
            return;
        }

        wp_enqueue_media();

        wp_enqueue_style(
            'ffp-pwa-admin',
            plugins_url('assets/css/pwa-admin.css', dirname(__DIR__)),
            [],
            FORMFLOW_VERSION
        );

        wp_enqueue_script(
            'ffp-pwa-admin',
            plugins_url('assets/js/pwa-admin.js', dirname(__DIR__)),
            ['jquery', 'wp-color-picker'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('ffp-pwa-admin', 'ffpPWA', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'restUrl' => rest_url('form-flow-pro/v1'),
            'nonce' => wp_create_nonce('ffp_nonce'),
            'restNonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('wp-color-picker');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueueFrontendAssets(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Add install banner
        if (get_option('ffp_pwa_install_banner', true)) {
            add_action('wp_footer', function() {
                echo $this->getInstallBanner();
            }, 100);
        }

        // Enqueue PWA frontend script
        wp_enqueue_script(
            'ffp-pwa-frontend',
            plugins_url('assets/js/pwa-frontend.js', dirname(__DIR__)),
            ['jquery'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('ffp-pwa-frontend', 'ffpPWAConfig', [
            'offlineFormsEnabled' => get_option('ffp_pwa_offline_forms', true),
            'backgroundSyncEnabled' => get_option('ffp_pwa_background_sync', true),
        ]);
    }

    /**
     * Render admin page
     */
    public function renderAdminPage(): void
    {
        $status = $this->getStatus();
        $settings = $this->restGetSettings()->get_data();

        ?>
        <div class="wrap ffp-pwa-settings">
            <h1><?php _e('Mobile & PWA Settings', 'form-flow-pro'); ?></h1>

            <div class="ffp-pwa-status-card <?php echo $status['installable'] ? 'installable' : 'not-installable'; ?>">
                <div class="ffp-pwa-status-icon">
                    <?php if ($status['installable']): ?>
                        <span class="dashicons dashicons-yes-alt"></span>
                    <?php else: ?>
                        <span class="dashicons dashicons-warning"></span>
                    <?php endif; ?>
                </div>
                <div class="ffp-pwa-status-content">
                    <h3>
                        <?php echo $status['installable']
                            ? __('Your site is installable as a PWA', 'form-flow-pro')
                            : __('PWA configuration needed', 'form-flow-pro'); ?>
                    </h3>
                    <?php if (!empty($status['issues'])): ?>
                        <ul class="ffp-pwa-issues">
                            <?php foreach ($status['issues'] as $issue): ?>
                                <li class="issue-<?php echo esc_attr($issue['type']); ?>">
                                    <?php echo esc_html($issue['message']); ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <form method="post" action="" id="ffp-pwa-form">
                <?php wp_nonce_field('ffp_pwa_settings'); ?>

                <div class="ffp-pwa-section">
                    <h2><?php _e('General Settings', 'form-flow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Enable PWA', 'form-flow-pro'); ?></th>
                            <td>
                                <label class="ffp-toggle">
                                    <input type="checkbox" name="ffp_pwa_enabled" value="1"
                                           <?php checked($settings['enabled']); ?>>
                                    <span class="ffp-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Enable Progressive Web App features for your forms.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('App Name', 'form-flow-pro'); ?></th>
                            <td>
                                <input type="text" name="ffp_pwa_name" class="regular-text"
                                       value="<?php echo esc_attr($settings['name']); ?>">
                                <p class="description">
                                    <?php _e('Full name of your app (displayed in app stores and install dialogs).', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Short Name', 'form-flow-pro'); ?></th>
                            <td>
                                <input type="text" name="ffp_pwa_short_name" class="regular-text" maxlength="12"
                                       value="<?php echo esc_attr($settings['short_name']); ?>">
                                <p class="description">
                                    <?php _e('Short name (max 12 chars) shown under the app icon.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Description', 'form-flow-pro'); ?></th>
                            <td>
                                <textarea name="ffp_pwa_description" rows="3" class="large-text"><?php
                                    echo esc_textarea($settings['description']);
                                ?></textarea>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ffp-pwa-section">
                    <h2><?php _e('Appearance', 'form-flow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('App Icon', 'form-flow-pro'); ?></th>
                            <td>
                                <div class="ffp-icon-upload">
                                    <input type="hidden" name="ffp_pwa_icon" id="ffp_pwa_icon"
                                           value="<?php echo esc_attr($settings['icon']); ?>">
                                    <div class="ffp-icon-preview">
                                        <?php if ($settings['icon']): ?>
                                            <img src="<?php echo esc_url($settings['icon']); ?>" alt="">
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" class="button ffp-upload-icon">
                                        <?php _e('Upload Icon', 'form-flow-pro'); ?>
                                    </button>
                                    <button type="button" class="button ffp-remove-icon" <?php echo $settings['icon'] ? '' : 'style="display:none;"'; ?>>
                                        <?php _e('Remove', 'form-flow-pro'); ?>
                                    </button>
                                </div>
                                <p class="description">
                                    <?php _e('Square icon, at least 512x512 pixels. PNG recommended.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Theme Color', 'form-flow-pro'); ?></th>
                            <td>
                                <input type="text" name="ffp_pwa_theme_color" class="ffp-color-picker"
                                       value="<?php echo esc_attr($settings['theme_color']); ?>">
                                <p class="description">
                                    <?php _e('Color of the browser toolbar and task switcher.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Background Color', 'form-flow-pro'); ?></th>
                            <td>
                                <input type="text" name="ffp_pwa_background_color" class="ffp-color-picker"
                                       value="<?php echo esc_attr($settings['background_color']); ?>">
                                <p class="description">
                                    <?php _e('Splash screen background color.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Display Mode', 'form-flow-pro'); ?></th>
                            <td>
                                <select name="ffp_pwa_display">
                                    <option value="standalone" <?php selected($settings['display'], 'standalone'); ?>>
                                        <?php _e('Standalone (app-like)', 'form-flow-pro'); ?>
                                    </option>
                                    <option value="fullscreen" <?php selected($settings['display'], 'fullscreen'); ?>>
                                        <?php _e('Fullscreen', 'form-flow-pro'); ?>
                                    </option>
                                    <option value="minimal-ui" <?php selected($settings['display'], 'minimal-ui'); ?>>
                                        <?php _e('Minimal UI', 'form-flow-pro'); ?>
                                    </option>
                                    <option value="browser" <?php selected($settings['display'], 'browser'); ?>>
                                        <?php _e('Browser', 'form-flow-pro'); ?>
                                    </option>
                                </select>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ffp-pwa-section">
                    <h2><?php _e('Offline & Sync', 'form-flow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Offline Forms', 'form-flow-pro'); ?></th>
                            <td>
                                <label class="ffp-toggle">
                                    <input type="checkbox" name="ffp_pwa_offline_forms" value="1"
                                           <?php checked($settings['offline_forms']); ?>>
                                    <span class="ffp-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Allow users to fill forms while offline.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Background Sync', 'form-flow-pro'); ?></th>
                            <td>
                                <label class="ffp-toggle">
                                    <input type="checkbox" name="ffp_pwa_background_sync" value="1"
                                           <?php checked($settings['background_sync']); ?>>
                                    <span class="ffp-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Automatically sync offline submissions when back online.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>

                        <tr>
                            <th><?php _e('Offline Message', 'form-flow-pro'); ?></th>
                            <td>
                                <textarea name="ffp_pwa_offline_message" rows="2" class="large-text"><?php
                                    echo esc_textarea($settings['offline_message']);
                                ?></textarea>
                                <p class="description">
                                    <?php _e('Message shown on the offline page.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="ffp-pwa-section">
                    <h2><?php _e('Install Prompt', 'form-flow-pro'); ?></h2>

                    <table class="form-table">
                        <tr>
                            <th><?php _e('Install Banner', 'form-flow-pro'); ?></th>
                            <td>
                                <label class="ffp-toggle">
                                    <input type="checkbox" name="ffp_pwa_install_banner" value="1"
                                           <?php checked($settings['install_banner']); ?>>
                                    <span class="ffp-toggle-slider"></span>
                                </label>
                                <p class="description">
                                    <?php _e('Show install banner to users on mobile devices.', 'form-flow-pro'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>

                <p class="submit">
                    <button type="submit" class="button button-primary"><?php _e('Save Settings', 'form-flow-pro'); ?></button>
                    <button type="button" class="button ffp-clear-cache"><?php _e('Clear Cache', 'form-flow-pro'); ?></button>
                </p>
            </form>

            <div class="ffp-pwa-preview">
                <h2><?php _e('Mobile Preview', 'form-flow-pro'); ?></h2>
                <div class="ffp-mobile-frame">
                    <div class="ffp-mobile-screen">
                        <div class="ffp-mobile-status-bar">
                            <span>9:41</span>
                        </div>
                        <div class="ffp-mobile-content">
                            <iframe src="<?php echo esc_url(add_query_arg('ffp_preview', '1', home_url())); ?>"></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .ffp-pwa-settings { max-width: 1200px; }
            .ffp-pwa-status-card { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; display: flex; align-items: flex-start; gap: 16px; border-left: 4px solid #22c55e; }
            .ffp-pwa-status-card.not-installable { border-left-color: #f59e0b; }
            .ffp-pwa-status-icon .dashicons { font-size: 32px; width: 32px; height: 32px; }
            .ffp-pwa-status-card.installable .dashicons { color: #22c55e; }
            .ffp-pwa-status-card.not-installable .dashicons { color: #f59e0b; }
            .ffp-pwa-issues { margin: 10px 0 0; padding-left: 20px; }
            .ffp-pwa-issues .issue-error { color: #dc2626; }
            .ffp-pwa-issues .issue-warning { color: #d97706; }
            .ffp-pwa-section { background: white; padding: 20px; border-radius: 8px; margin: 20px 0; }
            .ffp-pwa-section h2 { margin-top: 0; padding-bottom: 10px; border-bottom: 1px solid #e5e7eb; }
            .ffp-toggle { position: relative; display: inline-block; width: 50px; height: 26px; }
            .ffp-toggle input { opacity: 0; width: 0; height: 0; }
            .ffp-toggle-slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background: #cbd5e1; border-radius: 26px; transition: .3s; }
            .ffp-toggle-slider:before { content: ""; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px; background: white; border-radius: 50%; transition: .3s; }
            .ffp-toggle input:checked + .ffp-toggle-slider { background: #3b82f6; }
            .ffp-toggle input:checked + .ffp-toggle-slider:before { transform: translateX(24px); }
            .ffp-icon-upload { display: flex; align-items: center; gap: 12px; }
            .ffp-icon-preview { width: 64px; height: 64px; border: 2px dashed #cbd5e1; border-radius: 12px; display: flex; align-items: center; justify-content: center; overflow: hidden; }
            .ffp-icon-preview img { width: 100%; height: 100%; object-fit: cover; }
            .ffp-pwa-preview { position: fixed; right: 40px; top: 100px; }
            .ffp-mobile-frame { width: 280px; height: 560px; background: #1e293b; border-radius: 36px; padding: 10px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
            .ffp-mobile-screen { width: 100%; height: 100%; background: white; border-radius: 28px; overflow: hidden; }
            .ffp-mobile-status-bar { height: 44px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: 600; }
            .ffp-mobile-content { height: calc(100% - 44px); }
            .ffp-mobile-content iframe { width: 100%; height: 100%; border: none; }
            @media (max-width: 1400px) { .ffp-pwa-preview { display: none; } }
        </style>
        <?php
    }

    /**
     * AJAX save settings
     */
    public function ajaxSaveSettings(): void
    {
        check_ajax_referer('ffp_pwa_settings');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        $fields = [
            'ffp_pwa_enabled' => 'bool',
            'ffp_pwa_name' => 'text',
            'ffp_pwa_short_name' => 'text',
            'ffp_pwa_description' => 'textarea',
            'ffp_pwa_theme_color' => 'text',
            'ffp_pwa_background_color' => 'text',
            'ffp_pwa_display' => 'text',
            'ffp_pwa_orientation' => 'text',
            'ffp_pwa_icon' => 'url',
            'ffp_pwa_offline_forms' => 'bool',
            'ffp_pwa_background_sync' => 'bool',
            'ffp_pwa_push_notifications' => 'bool',
            'ffp_pwa_install_banner' => 'bool',
            'ffp_pwa_offline_message' => 'textarea',
        ];

        foreach ($fields as $field => $type) {
            $value = $_POST[$field] ?? null;

            switch ($type) {
                case 'bool':
                    update_option($field, !empty($value));
                    break;
                case 'text':
                    update_option($field, sanitize_text_field($value));
                    break;
                case 'textarea':
                    update_option($field, sanitize_textarea_field($value));
                    break;
                case 'url':
                    update_option($field, esc_url_raw($value));
                    break;
            }
        }

        // Bump version
        $this->sw_manager->bumpVersion();

        // Flush rewrite rules
        flush_rewrite_rules();

        wp_send_json_success(['message' => __('Settings saved', 'form-flow-pro')]);
    }

    /**
     * AJAX test manifest
     */
    public function ajaxTestManifest(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        $manifest = $this->getManifest();
        $status = $this->getStatus();

        wp_send_json_success([
            'manifest' => $manifest,
            'status' => $status,
        ]);
    }

    /**
     * AJAX clear cache
     */
    public function ajaxClearCache(): void
    {
        check_ajax_referer('ffp_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Permission denied']);
        }

        // Bump version to invalidate all caches
        $this->sw_manager->bumpVersion();

        wp_send_json_success(['message' => __('Cache cleared. Users will receive updated content.', 'form-flow-pro')]);
    }
}
