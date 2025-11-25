<?php
/**
 * Mobile Preview - Real-time mobile form preview and testing
 *
 * Advanced mobile preview system with device simulation,
 * touch interaction testing, and responsive debugging.
 *
 * @package FormFlowPro
 * @subpackage PWA
 * @since 3.0.0
 */

namespace FormFlowPro\PWA;

use FormFlowPro\Core\SingletonTrait;

/**
 * Device configuration for preview
 */
class DeviceProfile
{
    public string $id;
    public string $name;
    public string $type; // phone, tablet, desktop
    public int $width;
    public int $height;
    public float $pixel_ratio;
    public string $user_agent;
    public bool $touch_enabled;
    public string $platform; // ios, android, windows
    public array $features;

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? '';
        $this->name = $data['name'] ?? '';
        $this->type = $data['type'] ?? 'phone';
        $this->width = $data['width'] ?? 375;
        $this->height = $data['height'] ?? 667;
        $this->pixel_ratio = $data['pixel_ratio'] ?? 2.0;
        $this->user_agent = $data['user_agent'] ?? '';
        $this->touch_enabled = $data['touch_enabled'] ?? true;
        $this->platform = $data['platform'] ?? 'ios';
        $this->features = $data['features'] ?? [];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'width' => $this->width,
            'height' => $this->height,
            'pixel_ratio' => $this->pixel_ratio,
            'user_agent' => $this->user_agent,
            'touch_enabled' => $this->touch_enabled,
            'platform' => $this->platform,
            'features' => $this->features
        ];
    }
}

/**
 * Preview session tracking
 */
class PreviewSession
{
    public string $session_id;
    public int $form_id;
    public string $device_id;
    public int $user_id;
    public array $interactions;
    public array $errors;
    public array $performance;
    public string $created_at;
    public string $updated_at;

    public function __construct(array $data = [])
    {
        $this->session_id = $data['session_id'] ?? wp_generate_uuid4();
        $this->form_id = $data['form_id'] ?? 0;
        $this->device_id = $data['device_id'] ?? '';
        $this->user_id = $data['user_id'] ?? get_current_user_id();
        $this->interactions = $data['interactions'] ?? [];
        $this->errors = $data['errors'] ?? [];
        $this->performance = $data['performance'] ?? [];
        $this->created_at = $data['created_at'] ?? current_time('mysql');
        $this->updated_at = $data['updated_at'] ?? current_time('mysql');
    }

    public function addInteraction(string $type, array $data): void
    {
        $this->interactions[] = [
            'type' => $type,
            'data' => $data,
            'timestamp' => microtime(true)
        ];
        $this->updated_at = current_time('mysql');
    }

    public function addError(string $type, string $message, array $context = []): void
    {
        $this->errors[] = [
            'type' => $type,
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true)
        ];
    }

    public function setPerformance(array $metrics): void
    {
        $this->performance = array_merge($this->performance, $metrics);
    }

    public function toArray(): array
    {
        return [
            'session_id' => $this->session_id,
            'form_id' => $this->form_id,
            'device_id' => $this->device_id,
            'user_id' => $this->user_id,
            'interactions' => $this->interactions,
            'errors' => $this->errors,
            'performance' => $this->performance,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }
}

/**
 * Responsive breakpoint configuration
 */
class ResponsiveBreakpoint
{
    public string $name;
    public int $min_width;
    public int $max_width;
    public array $styles;

    public function __construct(string $name, int $min, int $max, array $styles = [])
    {
        $this->name = $name;
        $this->min_width = $min;
        $this->max_width = $max;
        $this->styles = $styles;
    }

    public function matches(int $width): bool
    {
        if ($this->min_width > 0 && $width < $this->min_width) {
            return false;
        }
        if ($this->max_width > 0 && $width > $this->max_width) {
            return false;
        }
        return true;
    }
}

/**
 * Mobile Preview Manager
 */
class MobilePreview
{
    use SingletonTrait;

    private array $devices = [];
    private array $breakpoints = [];
    private array $sessions = [];
    private string $preview_url_base;

    /**
     * Initialize mobile preview
     */
    protected function init(): void
    {
        $this->preview_url_base = home_url('/formflow-preview/');

        $this->registerDefaultDevices();
        $this->registerDefaultBreakpoints();
        $this->registerHooks();
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_action('template_redirect', [$this, 'handlePreviewRequest']);
        add_action('wp_ajax_ffp_preview_sync', [$this, 'ajaxSyncPreview']);
        add_action('wp_ajax_ffp_preview_interaction', [$this, 'ajaxLogInteraction']);
        add_action('wp_ajax_ffp_preview_qr', [$this, 'ajaxGenerateQRCode']);
        add_action('wp_ajax_ffp_preview_performance', [$this, 'ajaxLogPerformance']);

        add_action('rest_api_init', [$this, 'registerRestRoutes']);
    }

    /**
     * Register default device profiles
     */
    private function registerDefaultDevices(): void
    {
        // iPhone devices
        $this->registerDevice(new DeviceProfile([
            'id' => 'iphone-15-pro',
            'name' => 'iPhone 15 Pro',
            'type' => 'phone',
            'width' => 393,
            'height' => 852,
            'pixel_ratio' => 3.0,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'touch_enabled' => true,
            'platform' => 'ios',
            'features' => ['notch', 'dynamic_island', 'face_id', 'haptic']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'iphone-14',
            'name' => 'iPhone 14',
            'type' => 'phone',
            'width' => 390,
            'height' => 844,
            'pixel_ratio' => 3.0,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'touch_enabled' => true,
            'platform' => 'ios',
            'features' => ['notch', 'face_id', 'haptic']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'iphone-se',
            'name' => 'iPhone SE',
            'type' => 'phone',
            'width' => 375,
            'height' => 667,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/16.0 Mobile/15E148 Safari/604.1',
            'touch_enabled' => true,
            'platform' => 'ios',
            'features' => ['touch_id', 'haptic']
        ]));

        // Android devices
        $this->registerDevice(new DeviceProfile([
            'id' => 'pixel-8-pro',
            'name' => 'Google Pixel 8 Pro',
            'type' => 'phone',
            'width' => 412,
            'height' => 915,
            'pixel_ratio' => 2.625,
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; Pixel 8 Pro) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
            'touch_enabled' => true,
            'platform' => 'android',
            'features' => ['fingerprint', 'face_unlock', 'haptic']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'samsung-s24-ultra',
            'name' => 'Samsung Galaxy S24 Ultra',
            'type' => 'phone',
            'width' => 412,
            'height' => 915,
            'pixel_ratio' => 3.5,
            'user_agent' => 'Mozilla/5.0 (Linux; Android 14; SM-S928B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
            'touch_enabled' => true,
            'platform' => 'android',
            'features' => ['s_pen', 'fingerprint', 'face_unlock', 'haptic']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'samsung-a54',
            'name' => 'Samsung Galaxy A54',
            'type' => 'phone',
            'width' => 360,
            'height' => 800,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-A546B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Mobile Safari/537.36',
            'touch_enabled' => true,
            'platform' => 'android',
            'features' => ['fingerprint', 'haptic']
        ]));

        // Tablets
        $this->registerDevice(new DeviceProfile([
            'id' => 'ipad-pro-12',
            'name' => 'iPad Pro 12.9"',
            'type' => 'tablet',
            'width' => 1024,
            'height' => 1366,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'touch_enabled' => true,
            'platform' => 'ios',
            'features' => ['apple_pencil', 'face_id', 'split_view']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'ipad-air',
            'name' => 'iPad Air',
            'type' => 'tablet',
            'width' => 820,
            'height' => 1180,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            'touch_enabled' => true,
            'platform' => 'ios',
            'features' => ['apple_pencil', 'touch_id', 'split_view']
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'samsung-tab-s9',
            'name' => 'Samsung Galaxy Tab S9',
            'type' => 'tablet',
            'width' => 800,
            'height' => 1280,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (Linux; Android 13; SM-X710) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'touch_enabled' => true,
            'platform' => 'android',
            'features' => ['s_pen', 'fingerprint', 'dex']
        ]));

        // Desktop viewports for comparison
        $this->registerDevice(new DeviceProfile([
            'id' => 'desktop-1080',
            'name' => 'Desktop 1080p',
            'type' => 'desktop',
            'width' => 1920,
            'height' => 1080,
            'pixel_ratio' => 1.0,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36',
            'touch_enabled' => false,
            'platform' => 'windows',
            'features' => []
        ]));

        $this->registerDevice(new DeviceProfile([
            'id' => 'macbook-pro',
            'name' => 'MacBook Pro 14"',
            'type' => 'desktop',
            'width' => 1512,
            'height' => 982,
            'pixel_ratio' => 2.0,
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 14_0) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15',
            'touch_enabled' => false,
            'platform' => 'macos',
            'features' => ['retina', 'touch_bar']
        ]));

        // Allow custom devices
        $custom_devices = get_option('ffp_custom_devices', []);
        foreach ($custom_devices as $device_data) {
            $this->registerDevice(new DeviceProfile($device_data));
        }
    }

    /**
     * Register default responsive breakpoints
     */
    private function registerDefaultBreakpoints(): void
    {
        $this->breakpoints = [
            new ResponsiveBreakpoint('xs', 0, 575, [
                'container_padding' => '16px',
                'font_scale' => 0.875,
                'columns' => 1
            ]),
            new ResponsiveBreakpoint('sm', 576, 767, [
                'container_padding' => '20px',
                'font_scale' => 0.9,
                'columns' => 1
            ]),
            new ResponsiveBreakpoint('md', 768, 991, [
                'container_padding' => '24px',
                'font_scale' => 1.0,
                'columns' => 2
            ]),
            new ResponsiveBreakpoint('lg', 992, 1199, [
                'container_padding' => '32px',
                'font_scale' => 1.0,
                'columns' => 2
            ]),
            new ResponsiveBreakpoint('xl', 1200, 1399, [
                'container_padding' => '40px',
                'font_scale' => 1.0,
                'columns' => 3
            ]),
            new ResponsiveBreakpoint('xxl', 1400, 0, [
                'container_padding' => '48px',
                'font_scale' => 1.0,
                'columns' => 4
            ])
        ];
    }

    /**
     * Register a device profile
     */
    public function registerDevice(DeviceProfile $device): void
    {
        $this->devices[$device->id] = $device;
    }

    /**
     * Get all registered devices
     */
    public function getDevices(string $type = ''): array
    {
        if (empty($type)) {
            return $this->devices;
        }

        return array_filter($this->devices, function ($device) use ($type) {
            return $device->type === $type;
        });
    }

    /**
     * Get device by ID
     */
    public function getDevice(string $device_id): ?DeviceProfile
    {
        return $this->devices[$device_id] ?? null;
    }

    /**
     * Register rewrite rules for preview URLs
     */
    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            '^formflow-preview/([0-9]+)/?$',
            'index.php?ffp_preview=1&ffp_form_id=$matches[1]',
            'top'
        );

        add_rewrite_rule(
            '^formflow-preview/([0-9]+)/([a-zA-Z0-9-]+)/?$',
            'index.php?ffp_preview=1&ffp_form_id=$matches[1]&ffp_device=$matches[2]',
            'top'
        );

        add_rewrite_tag('%ffp_preview%', '([0-9]+)');
        add_rewrite_tag('%ffp_form_id%', '([0-9]+)');
        add_rewrite_tag('%ffp_device%', '([a-zA-Z0-9-]+)');
    }

    /**
     * Handle preview request
     */
    public function handlePreviewRequest(): void
    {
        if (!get_query_var('ffp_preview')) {
            return;
        }

        $form_id = intval(get_query_var('ffp_form_id'));
        $device_id = sanitize_text_field(get_query_var('ffp_device', 'iphone-15-pro'));

        if (!$form_id) {
            wp_die(__('Form ID is required', 'form-flow-pro'));
        }

        // Check permissions
        if (!current_user_can('edit_posts') && !$this->isValidPreviewToken()) {
            wp_die(__('You do not have permission to preview this form', 'form-flow-pro'));
        }

        $device = $this->getDevice($device_id);

        $this->renderPreview($form_id, $device);
        exit;
    }

    /**
     * Check if preview token is valid
     */
    private function isValidPreviewToken(): bool
    {
        $token = sanitize_text_field($_GET['preview_token'] ?? '');
        if (empty($token)) {
            return false;
        }

        $stored_tokens = get_transient('ffp_preview_tokens');
        if (!is_array($stored_tokens)) {
            return false;
        }

        return in_array($token, $stored_tokens, true);
    }

    /**
     * Generate preview token for external access
     */
    public function generatePreviewToken(int $form_id, int $expiry = 3600): string
    {
        $token = wp_generate_password(32, false);

        $tokens = get_transient('ffp_preview_tokens') ?: [];
        $tokens[$token] = [
            'form_id' => $form_id,
            'created' => time(),
            'expiry' => time() + $expiry
        ];

        // Clean expired tokens
        $tokens = array_filter($tokens, function ($data) {
            return $data['expiry'] > time();
        });

        set_transient('ffp_preview_tokens', $tokens, DAY_IN_SECONDS);

        return $token;
    }

    /**
     * Get preview URL
     */
    public function getPreviewUrl(int $form_id, string $device_id = '', bool $with_token = false): string
    {
        $url = home_url("/formflow-preview/{$form_id}/");

        if (!empty($device_id)) {
            $url .= "{$device_id}/";
        }

        if ($with_token) {
            $token = $this->generatePreviewToken($form_id);
            $url = add_query_arg('preview_token', $token, $url);
        }

        return $url;
    }

    /**
     * Render preview page
     */
    private function renderPreview(int $form_id, ?DeviceProfile $device): void
    {
        $form = get_post($form_id);
        if (!$form || $form->post_type !== 'ffp_form') {
            wp_die(__('Form not found', 'form-flow-pro'));
        }

        $form_settings = get_post_meta($form_id, '_ffp_form_settings', true) ?: [];
        $form_structure = get_post_meta($form_id, '_ffp_form_structure', true) ?: [];

        // Create preview session
        $session = new PreviewSession([
            'form_id' => $form_id,
            'device_id' => $device ? $device->id : 'responsive'
        ]);
        $this->sessions[$session->session_id] = $session;

        // Get breakpoint for device
        $breakpoint = null;
        if ($device) {
            foreach ($this->breakpoints as $bp) {
                if ($bp->matches($device->width)) {
                    $breakpoint = $bp;
                    break;
                }
            }
        }

        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
            <meta name="robots" content="noindex, nofollow">
            <title><?php echo esc_html($form->post_title); ?> - <?php esc_html_e('Preview', 'form-flow-pro'); ?></title>

            <?php if ($device): ?>
            <meta name="device-id" content="<?php echo esc_attr($device->id); ?>">
            <meta name="device-type" content="<?php echo esc_attr($device->type); ?>">
            <?php endif; ?>

            <style>
                :root {
                    --device-width: <?php echo $device ? $device->width . 'px' : '100%'; ?>;
                    --device-height: <?php echo $device ? $device->height . 'px' : '100vh'; ?>;
                    --pixel-ratio: <?php echo $device ? $device->pixel_ratio : 1; ?>;
                    --safe-area-inset-top: <?php echo ($device && in_array('notch', $device->features)) ? '47px' : '0'; ?>;
                    --safe-area-inset-bottom: <?php echo ($device && $device->platform === 'ios') ? '34px' : '0'; ?>;
                }

                * {
                    box-sizing: border-box;
                    -webkit-tap-highlight-color: transparent;
                }

                html, body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
                    background: #1a1a2e;
                    overflow: hidden;
                }

                .preview-container {
                    display: flex;
                    flex-direction: column;
                    height: 100vh;
                }

                .preview-toolbar {
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 12px 20px;
                    background: #16213e;
                    border-bottom: 1px solid #0f3460;
                    color: #fff;
                    flex-shrink: 0;
                }

                .preview-toolbar-left,
                .preview-toolbar-right {
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }

                .preview-toolbar h1 {
                    margin: 0;
                    font-size: 16px;
                    font-weight: 600;
                }

                .device-selector {
                    padding: 8px 12px;
                    border-radius: 6px;
                    border: 1px solid #0f3460;
                    background: #1a1a2e;
                    color: #fff;
                    font-size: 14px;
                    cursor: pointer;
                }

                .toolbar-btn {
                    padding: 8px 16px;
                    border-radius: 6px;
                    border: none;
                    background: #e94560;
                    color: #fff;
                    font-size: 14px;
                    cursor: pointer;
                    transition: background 0.2s;
                }

                .toolbar-btn:hover {
                    background: #ff6b6b;
                }

                .toolbar-btn.secondary {
                    background: #0f3460;
                }

                .toolbar-btn.secondary:hover {
                    background: #1a5276;
                }

                .preview-workspace {
                    flex: 1;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 40px;
                    overflow: auto;
                }

                .device-frame {
                    position: relative;
                    background: #000;
                    border-radius: <?php echo ($device && $device->type === 'phone') ? '44px' : '20px'; ?>;
                    padding: <?php echo ($device && $device->type === 'phone') ? '14px' : '10px'; ?>;
                    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
                }

                <?php if ($device && in_array('notch', $device->features)): ?>
                .device-frame::before {
                    content: '';
                    position: absolute;
                    top: 14px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 150px;
                    height: 30px;
                    background: #000;
                    border-radius: 0 0 20px 20px;
                    z-index: 100;
                }
                <?php endif; ?>

                <?php if ($device && in_array('dynamic_island', $device->features)): ?>
                .device-frame::before {
                    content: '';
                    position: absolute;
                    top: 24px;
                    left: 50%;
                    transform: translateX(-50%);
                    width: 126px;
                    height: 37px;
                    background: #000;
                    border-radius: 20px;
                    z-index: 100;
                }
                <?php endif; ?>

                .device-screen {
                    width: var(--device-width);
                    height: var(--device-height);
                    background: #fff;
                    border-radius: <?php echo ($device && $device->type === 'phone') ? '38px' : '14px'; ?>;
                    overflow: hidden;
                    position: relative;
                }

                .form-preview-frame {
                    width: 100%;
                    height: 100%;
                    border: none;
                }

                .preview-info-panel {
                    position: fixed;
                    right: 20px;
                    top: 80px;
                    width: 280px;
                    background: #16213e;
                    border-radius: 12px;
                    padding: 16px;
                    color: #fff;
                    font-size: 13px;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                }

                .preview-info-panel h3 {
                    margin: 0 0 12px 0;
                    font-size: 14px;
                    color: #e94560;
                }

                .info-row {
                    display: flex;
                    justify-content: space-between;
                    padding: 8px 0;
                    border-bottom: 1px solid #0f3460;
                }

                .info-row:last-child {
                    border-bottom: none;
                }

                .info-label {
                    color: #8892b0;
                }

                .info-value {
                    font-weight: 600;
                }

                .qr-modal {
                    display: none;
                    position: fixed;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.8);
                    z-index: 1000;
                    align-items: center;
                    justify-content: center;
                }

                .qr-modal.active {
                    display: flex;
                }

                .qr-content {
                    background: #fff;
                    padding: 32px;
                    border-radius: 16px;
                    text-align: center;
                }

                .qr-content h3 {
                    margin: 0 0 16px 0;
                    color: #1a1a2e;
                }

                .qr-content img {
                    width: 200px;
                    height: 200px;
                }

                .qr-content p {
                    margin: 16px 0 0 0;
                    color: #666;
                    font-size: 14px;
                }

                .rotation-toggle {
                    display: flex;
                    gap: 4px;
                    background: #0f3460;
                    padding: 4px;
                    border-radius: 6px;
                }

                .rotation-btn {
                    padding: 6px 10px;
                    border: none;
                    background: transparent;
                    color: #8892b0;
                    cursor: pointer;
                    border-radius: 4px;
                    transition: all 0.2s;
                }

                .rotation-btn.active {
                    background: #e94560;
                    color: #fff;
                }

                .interaction-log {
                    position: fixed;
                    left: 20px;
                    bottom: 20px;
                    width: 300px;
                    max-height: 200px;
                    overflow-y: auto;
                    background: #16213e;
                    border-radius: 12px;
                    padding: 12px;
                    font-family: monospace;
                    font-size: 11px;
                    color: #8892b0;
                }

                .interaction-log-item {
                    padding: 4px 0;
                    border-bottom: 1px solid #0f3460;
                }

                .interaction-log-item .type {
                    color: #e94560;
                }

                .interaction-log-item .time {
                    color: #4a5568;
                }

                @media (max-width: 1200px) {
                    .preview-info-panel {
                        display: none;
                    }
                }
            </style>
        </head>
        <body>
            <div class="preview-container">
                <div class="preview-toolbar">
                    <div class="preview-toolbar-left">
                        <h1><?php echo esc_html($form->post_title); ?></h1>
                        <select class="device-selector" id="deviceSelector" onchange="changeDevice(this.value)">
                            <optgroup label="<?php esc_attr_e('Phones', 'form-flow-pro'); ?>">
                                <?php foreach ($this->getDevices('phone') as $d): ?>
                                <option value="<?php echo esc_attr($d->id); ?>" <?php selected($device && $device->id === $d->id); ?>>
                                    <?php echo esc_html($d->name); ?> (<?php echo $d->width; ?>x<?php echo $d->height; ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('Tablets', 'form-flow-pro'); ?>">
                                <?php foreach ($this->getDevices('tablet') as $d): ?>
                                <option value="<?php echo esc_attr($d->id); ?>" <?php selected($device && $device->id === $d->id); ?>>
                                    <?php echo esc_html($d->name); ?> (<?php echo $d->width; ?>x<?php echo $d->height; ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="<?php esc_attr_e('Desktop', 'form-flow-pro'); ?>">
                                <?php foreach ($this->getDevices('desktop') as $d): ?>
                                <option value="<?php echo esc_attr($d->id); ?>" <?php selected($device && $device->id === $d->id); ?>>
                                    <?php echo esc_html($d->name); ?> (<?php echo $d->width; ?>x<?php echo $d->height; ?>)
                                </option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>

                        <div class="rotation-toggle">
                            <button class="rotation-btn active" data-orientation="portrait" onclick="setOrientation('portrait')">
                                <svg width="14" height="18" viewBox="0 0 14 18" fill="currentColor"><rect x="1" y="1" width="12" height="16" rx="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            </button>
                            <button class="rotation-btn" data-orientation="landscape" onclick="setOrientation('landscape')">
                                <svg width="18" height="14" viewBox="0 0 18 14" fill="currentColor"><rect x="1" y="1" width="16" height="12" rx="2" stroke="currentColor" stroke-width="2" fill="none"/></svg>
                            </button>
                        </div>
                    </div>

                    <div class="preview-toolbar-right">
                        <button class="toolbar-btn secondary" onclick="showQRCode()">
                            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" style="vertical-align: middle; margin-right: 6px;">
                                <path d="M0 0h7v7H0V0zm2 2v3h3V2H2zm7-2h7v7H9V0zm2 2v3h3V2h-3zM0 9h7v7H0V9zm2 2v3h3v-3H2zm10-2h1v1h-1V9zm2 0h2v3h-1v-1h-1V9zm-2 3h1v1h-1v-1zm0 2h3v1h-1v1h-1v-1h-1v-1zm2 0h1v1h-1v-1z"/>
                            </svg>
                            <?php esc_html_e('QR Code', 'form-flow-pro'); ?>
                        </button>
                        <button class="toolbar-btn secondary" onclick="refreshPreview()">
                            <?php esc_html_e('Refresh', 'form-flow-pro'); ?>
                        </button>
                        <button class="toolbar-btn" onclick="window.close()">
                            <?php esc_html_e('Close Preview', 'form-flow-pro'); ?>
                        </button>
                    </div>
                </div>

                <div class="preview-workspace">
                    <div class="device-frame" id="deviceFrame">
                        <div class="device-screen" id="deviceScreen">
                            <iframe
                                id="formPreviewFrame"
                                class="form-preview-frame"
                                src="<?php echo esc_url(add_query_arg(['ffp_render' => 1, 'form_id' => $form_id], home_url())); ?>"
                                sandbox="allow-scripts allow-forms allow-same-origin allow-popups"
                            ></iframe>
                        </div>
                    </div>
                </div>

                <div class="preview-info-panel" id="infoPanel">
                    <h3><?php esc_html_e('Device Info', 'form-flow-pro'); ?></h3>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Device', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoDevice"><?php echo $device ? esc_html($device->name) : 'Responsive'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Resolution', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoResolution"><?php echo $device ? "{$device->width}x{$device->height}" : 'Auto'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Pixel Ratio', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoPixelRatio"><?php echo $device ? $device->pixel_ratio . 'x' : '1x'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Platform', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoPlatform"><?php echo $device ? ucfirst($device->platform) : 'Web'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Touch', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoTouch"><?php echo ($device && $device->touch_enabled) ? 'Yes' : 'No'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label"><?php esc_html_e('Breakpoint', 'form-flow-pro'); ?></span>
                        <span class="info-value" id="infoBreakpoint"><?php echo $breakpoint ? strtoupper($breakpoint->name) : '-'; ?></span>
                    </div>
                </div>

                <div class="interaction-log" id="interactionLog">
                    <div class="interaction-log-item">
                        <span class="time">[<?php echo current_time('H:i:s'); ?>]</span>
                        <span class="type">INIT</span>
                        Preview session started
                    </div>
                </div>
            </div>

            <div class="qr-modal" id="qrModal" onclick="hideQRCode()">
                <div class="qr-content" onclick="event.stopPropagation()">
                    <h3><?php esc_html_e('Scan to preview on mobile', 'form-flow-pro'); ?></h3>
                    <div id="qrCodeContainer"></div>
                    <p><?php esc_html_e('Open camera app and point at QR code', 'form-flow-pro'); ?></p>
                </div>
            </div>

            <script>
            const formId = <?php echo $form_id; ?>;
            const sessionId = '<?php echo esc_js($session->session_id); ?>';
            const devices = <?php echo wp_json_encode(array_map(function($d) { return $d->toArray(); }, $this->devices)); ?>;
            let currentOrientation = 'portrait';

            function changeDevice(deviceId) {
                window.location.href = '<?php echo home_url('/formflow-preview/' . $form_id . '/'); ?>' + deviceId + '/';
            }

            function setOrientation(orientation) {
                currentOrientation = orientation;
                const deviceScreen = document.getElementById('deviceScreen');
                const device = devices[document.getElementById('deviceSelector').value];

                document.querySelectorAll('.rotation-btn').forEach(btn => {
                    btn.classList.toggle('active', btn.dataset.orientation === orientation);
                });

                if (device) {
                    if (orientation === 'landscape') {
                        deviceScreen.style.width = device.height + 'px';
                        deviceScreen.style.height = device.width + 'px';
                        document.getElementById('infoResolution').textContent = device.height + 'x' + device.width;
                    } else {
                        deviceScreen.style.width = device.width + 'px';
                        deviceScreen.style.height = device.height + 'px';
                        document.getElementById('infoResolution').textContent = device.width + 'x' + device.height;
                    }
                }

                logInteraction('orientation', { orientation: orientation });
            }

            function refreshPreview() {
                const frame = document.getElementById('formPreviewFrame');
                frame.src = frame.src;
                logInteraction('refresh', {});
            }

            function showQRCode() {
                const modal = document.getElementById('qrModal');
                const container = document.getElementById('qrCodeContainer');

                // Generate QR code
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=ffp_preview_qr&form_id=' + formId + '&nonce=<?php echo wp_create_nonce('ffp_preview_qr'); ?>'
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        container.innerHTML = '<img src="' + data.data.qr_url + '" alt="QR Code">';
                    }
                });

                modal.classList.add('active');
            }

            function hideQRCode() {
                document.getElementById('qrModal').classList.remove('active');
            }

            function logInteraction(type, data) {
                const log = document.getElementById('interactionLog');
                const time = new Date().toLocaleTimeString();

                const item = document.createElement('div');
                item.className = 'interaction-log-item';
                item.innerHTML = '<span class="time">[' + time + ']</span> <span class="type">' + type.toUpperCase() + '</span> ' + JSON.stringify(data);
                log.appendChild(item);
                log.scrollTop = log.scrollHeight;

                // Send to server
                fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=ffp_preview_interaction&session_id=' + sessionId + '&type=' + type + '&data=' + encodeURIComponent(JSON.stringify(data)) + '&nonce=<?php echo wp_create_nonce('ffp_preview_interaction'); ?>'
                });
            }

            // Listen for messages from iframe
            window.addEventListener('message', function(event) {
                if (event.data && event.data.type === 'ffp_interaction') {
                    logInteraction(event.data.action, event.data.data || {});
                }
            });

            // Performance monitoring
            window.addEventListener('load', function() {
                setTimeout(function() {
                    const frame = document.getElementById('formPreviewFrame');
                    try {
                        const perf = frame.contentWindow.performance;
                        if (perf && perf.timing) {
                            const timing = perf.timing;
                            const metrics = {
                                dns: timing.domainLookupEnd - timing.domainLookupStart,
                                connect: timing.connectEnd - timing.connectStart,
                                ttfb: timing.responseStart - timing.requestStart,
                                dom_load: timing.domContentLoadedEventEnd - timing.navigationStart,
                                full_load: timing.loadEventEnd - timing.navigationStart
                            };

                            fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=ffp_preview_performance&session_id=' + sessionId + '&metrics=' + encodeURIComponent(JSON.stringify(metrics)) + '&nonce=<?php echo wp_create_nonce('ffp_preview_performance'); ?>'
                            });
                        }
                    } catch (e) {
                        // Cross-origin restriction
                    }
                }, 2000);
            });
            </script>
        </body>
        </html>
        <?php
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('formflow/v1', '/preview/devices', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetDevices'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route('formflow/v1', '/preview/devices', [
            'methods' => 'POST',
            'callback' => [$this, 'restAddDevice'],
            'permission_callback' => function () {
                return current_user_can('manage_options');
            }
        ]);

        register_rest_route('formflow/v1', '/preview/(?P<form_id>\d+)/url', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetPreviewUrl'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route('formflow/v1', '/preview/(?P<form_id>\d+)/sessions', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetSessions'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);

        register_rest_route('formflow/v1', '/preview/breakpoints', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetBreakpoints'],
            'permission_callback' => function () {
                return current_user_can('edit_posts');
            }
        ]);
    }

    /**
     * REST: Get devices
     */
    public function restGetDevices(\WP_REST_Request $request): \WP_REST_Response
    {
        $type = sanitize_text_field($request->get_param('type') ?? '');
        $devices = $this->getDevices($type);

        return new \WP_REST_Response([
            'devices' => array_map(function ($d) {
                return $d->toArray();
            }, $devices)
        ]);
    }

    /**
     * REST: Add custom device
     */
    public function restAddDevice(\WP_REST_Request $request): \WP_REST_Response
    {
        $data = $request->get_json_params();

        $device = new DeviceProfile([
            'id' => sanitize_key($data['id'] ?? wp_generate_uuid4()),
            'name' => sanitize_text_field($data['name'] ?? ''),
            'type' => sanitize_text_field($data['type'] ?? 'phone'),
            'width' => intval($data['width'] ?? 375),
            'height' => intval($data['height'] ?? 667),
            'pixel_ratio' => floatval($data['pixel_ratio'] ?? 2.0),
            'user_agent' => sanitize_text_field($data['user_agent'] ?? ''),
            'touch_enabled' => !empty($data['touch_enabled']),
            'platform' => sanitize_text_field($data['platform'] ?? 'custom'),
            'features' => array_map('sanitize_text_field', $data['features'] ?? [])
        ]);

        // Save to options
        $custom_devices = get_option('ffp_custom_devices', []);
        $custom_devices[$device->id] = $device->toArray();
        update_option('ffp_custom_devices', $custom_devices);

        $this->registerDevice($device);

        return new \WP_REST_Response([
            'success' => true,
            'device' => $device->toArray()
        ]);
    }

    /**
     * REST: Get preview URL
     */
    public function restGetPreviewUrl(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = intval($request->get_param('form_id'));
        $device_id = sanitize_text_field($request->get_param('device') ?? '');
        $with_token = !empty($request->get_param('token'));

        return new \WP_REST_Response([
            'url' => $this->getPreviewUrl($form_id, $device_id, $with_token)
        ]);
    }

    /**
     * REST: Get breakpoints
     */
    public function restGetBreakpoints(\WP_REST_Request $request): \WP_REST_Response
    {
        return new \WP_REST_Response([
            'breakpoints' => array_map(function ($bp) {
                return [
                    'name' => $bp->name,
                    'min_width' => $bp->min_width,
                    'max_width' => $bp->max_width,
                    'styles' => $bp->styles
                ];
            }, $this->breakpoints)
        ]);
    }

    /**
     * REST: Get preview sessions
     */
    public function restGetSessions(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = intval($request->get_param('form_id'));

        // Get sessions from transient storage
        $all_sessions = get_transient('ffp_preview_sessions') ?: [];

        $form_sessions = array_filter($all_sessions, function ($session) use ($form_id) {
            return $session['form_id'] === $form_id;
        });

        return new \WP_REST_Response([
            'sessions' => array_values($form_sessions)
        ]);
    }

    /**
     * AJAX: Sync preview
     */
    public function ajaxSyncPreview(): void
    {
        check_ajax_referer('ffp_preview_sync', 'nonce');

        $form_id = intval($_POST['form_id'] ?? 0);
        $device_id = sanitize_text_field($_POST['device'] ?? '');

        wp_send_json_success([
            'url' => $this->getPreviewUrl($form_id, $device_id)
        ]);
    }

    /**
     * AJAX: Log interaction
     */
    public function ajaxLogInteraction(): void
    {
        check_ajax_referer('ffp_preview_interaction', 'nonce');

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $type = sanitize_text_field($_POST['type'] ?? '');
        $data = json_decode(stripslashes($_POST['data'] ?? '{}'), true);

        // Store interaction
        $sessions = get_transient('ffp_preview_sessions') ?: [];

        if (isset($sessions[$session_id])) {
            $sessions[$session_id]['interactions'][] = [
                'type' => $type,
                'data' => $data,
                'timestamp' => microtime(true)
            ];
            set_transient('ffp_preview_sessions', $sessions, HOUR_IN_SECONDS);
        }

        wp_send_json_success();
    }

    /**
     * AJAX: Generate QR code
     */
    public function ajaxGenerateQRCode(): void
    {
        check_ajax_referer('ffp_preview_qr', 'nonce');

        $form_id = intval($_POST['form_id'] ?? 0);
        $preview_url = $this->getPreviewUrl($form_id, '', true);

        // Use Google Charts API for QR code generation
        $qr_url = 'https://chart.googleapis.com/chart?' . http_build_query([
            'cht' => 'qr',
            'chs' => '200x200',
            'chl' => $preview_url,
            'choe' => 'UTF-8'
        ]);

        wp_send_json_success([
            'qr_url' => $qr_url,
            'preview_url' => $preview_url
        ]);
    }

    /**
     * AJAX: Log performance metrics
     */
    public function ajaxLogPerformance(): void
    {
        check_ajax_referer('ffp_preview_performance', 'nonce');

        $session_id = sanitize_text_field($_POST['session_id'] ?? '');
        $metrics = json_decode(stripslashes($_POST['metrics'] ?? '{}'), true);

        // Store performance data
        $sessions = get_transient('ffp_preview_sessions') ?: [];

        if (isset($sessions[$session_id])) {
            $sessions[$session_id]['performance'] = $metrics;
            set_transient('ffp_preview_sessions', $sessions, HOUR_IN_SECONDS);
        }

        wp_send_json_success();
    }

    /**
     * Generate responsive styles for form
     */
    public function generateResponsiveStyles(int $form_id): string
    {
        $form_settings = get_post_meta($form_id, '_ffp_form_settings', true) ?: [];
        $custom_styles = $form_settings['custom_styles'] ?? [];

        $css = "/* FormFlow Pro Responsive Styles */\n";

        foreach ($this->breakpoints as $breakpoint) {
            $media_query = $this->buildMediaQuery($breakpoint);

            $css .= "\n{$media_query} {\n";
            $css .= "  .ffp-form {\n";
            $css .= "    padding: {$breakpoint->styles['container_padding']};\n";
            $css .= "  }\n";

            if ($breakpoint->styles['font_scale'] !== 1.0) {
                $css .= "  .ffp-form {\n";
                $css .= "    font-size: " . ($breakpoint->styles['font_scale'] * 100) . "%;\n";
                $css .= "  }\n";
            }

            $columns = $breakpoint->styles['columns'];
            $css .= "  .ffp-form-row {\n";
            $css .= "    grid-template-columns: repeat({$columns}, 1fr);\n";
            $css .= "  }\n";

            // Custom breakpoint styles
            if (isset($custom_styles[$breakpoint->name])) {
                $css .= "  " . $custom_styles[$breakpoint->name] . "\n";
            }

            $css .= "}\n";
        }

        // Touch device styles
        $css .= "\n@media (hover: none) and (pointer: coarse) {\n";
        $css .= "  .ffp-field-input {\n";
        $css .= "    min-height: 44px;\n";
        $css .= "    font-size: 16px;\n";
        $css .= "  }\n";
        $css .= "  .ffp-button {\n";
        $css .= "    min-height: 48px;\n";
        $css .= "    padding: 12px 24px;\n";
        $css .= "  }\n";
        $css .= "}\n";

        // Safe area insets for modern phones
        $css .= "\n@supports (padding: env(safe-area-inset-bottom)) {\n";
        $css .= "  .ffp-form {\n";
        $css .= "    padding-bottom: calc(16px + env(safe-area-inset-bottom));\n";
        $css .= "  }\n";
        $css .= "}\n";

        return $css;
    }

    /**
     * Build media query string for breakpoint
     */
    private function buildMediaQuery(ResponsiveBreakpoint $breakpoint): string
    {
        $conditions = [];

        if ($breakpoint->min_width > 0) {
            $conditions[] = "(min-width: {$breakpoint->min_width}px)";
        }

        if ($breakpoint->max_width > 0) {
            $conditions[] = "(max-width: {$breakpoint->max_width}px)";
        }

        if (empty($conditions)) {
            return "@media all";
        }

        return "@media " . implode(' and ', $conditions);
    }

    /**
     * Get touch-friendly field configuration
     */
    public function getTouchConfig(): array
    {
        return [
            'min_tap_target' => 44, // iOS minimum
            'input_font_size' => 16, // Prevents zoom on iOS
            'button_min_height' => 48,
            'spacing_multiplier' => 1.5,
            'enable_haptic' => true,
            'swipe_threshold' => 50,
            'long_press_duration' => 500,
            'double_tap_delay' => 300
        ];
    }

    /**
     * Check if current request is from mobile device
     */
    public function isMobileDevice(): bool
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return false;
        }

        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        $mobile_keywords = [
            'mobile', 'android', 'iphone', 'ipad', 'ipod',
            'blackberry', 'windows phone', 'opera mini', 'opera mobi'
        ];

        foreach ($mobile_keywords as $keyword) {
            if (strpos($user_agent, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Detect current device type
     */
    public function detectDeviceType(): string
    {
        if (!isset($_SERVER['HTTP_USER_AGENT'])) {
            return 'desktop';
        }

        $user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);

        if (strpos($user_agent, 'tablet') !== false || strpos($user_agent, 'ipad') !== false) {
            return 'tablet';
        }

        if ($this->isMobileDevice()) {
            return 'phone';
        }

        return 'desktop';
    }

    /**
     * Get recommended input types for mobile
     */
    public function getMobileInputTypes(): array
    {
        return [
            'email' => 'email',
            'phone' => 'tel',
            'number' => 'number',
            'url' => 'url',
            'search' => 'search',
            'date' => 'date',
            'time' => 'time',
            'datetime' => 'datetime-local',
            'month' => 'month',
            'week' => 'week',
            'color' => 'color'
        ];
    }
}
