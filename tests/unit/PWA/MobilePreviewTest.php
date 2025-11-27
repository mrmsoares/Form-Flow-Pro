<?php
/**
 * Tests for MobilePreview class.
 */

namespace FormFlowPro\Tests\Unit\PWA;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\PWA\MobilePreview;
use FormFlowPro\PWA\DeviceProfile;
use FormFlowPro\PWA\PreviewSession;
use FormFlowPro\PWA\ResponsiveBreakpoint;

class MobilePreviewTest extends TestCase
{
    private $mobilePreview;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mobilePreview = MobilePreview::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = MobilePreview::getInstance();
        $instance2 = MobilePreview::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(MobilePreview::class, $instance1);
    }

    public function test_get_devices_returns_array()
    {
        $devices = $this->mobilePreview->getDevices();

        $this->assertIsArray($devices);
        $this->assertGreaterThan(0, count($devices));
    }

    public function test_get_devices_contains_default_devices()
    {
        $devices = $this->mobilePreview->getDevices();

        $deviceIds = array_keys($devices);

        $this->assertContains('iphone-15-pro', $deviceIds);
        $this->assertContains('iphone-14', $deviceIds);
        $this->assertContains('pixel-8-pro', $deviceIds);
        $this->assertContains('samsung-s24-ultra', $deviceIds);
        $this->assertContains('ipad-pro-12', $deviceIds);
    }

    public function test_get_devices_filters_by_type()
    {
        $phones = $this->mobilePreview->getDevices('phone');

        $this->assertIsArray($phones);
        $this->assertGreaterThan(0, count($phones));

        foreach ($phones as $device) {
            $this->assertEquals('phone', $device->type);
        }
    }

    public function test_get_devices_returns_tablets_only()
    {
        $tablets = $this->mobilePreview->getDevices('tablet');

        $this->assertIsArray($tablets);

        foreach ($tablets as $device) {
            $this->assertEquals('tablet', $device->type);
        }
    }

    public function test_get_device_returns_specific_device()
    {
        $device = $this->mobilePreview->getDevice('iphone-15-pro');

        $this->assertInstanceOf(DeviceProfile::class, $device);
        $this->assertEquals('iphone-15-pro', $device->id);
        $this->assertEquals('iPhone 15 Pro', $device->name);
        $this->assertEquals('phone', $device->type);
    }

    public function test_get_device_returns_null_for_invalid_id()
    {
        $device = $this->mobilePreview->getDevice('non-existent-device');

        $this->assertNull($device);
    }

    public function test_register_device_adds_custom_device()
    {
        $customDevice = new DeviceProfile([
            'id' => 'custom-phone',
            'name' => 'Custom Phone',
            'type' => 'phone',
            'width' => 400,
            'height' => 800,
        ]);

        $this->mobilePreview->registerDevice($customDevice);

        $device = $this->mobilePreview->getDevice('custom-phone');

        $this->assertInstanceOf(DeviceProfile::class, $device);
        $this->assertEquals('custom-phone', $device->id);
        $this->assertEquals('Custom Phone', $device->name);
    }

    public function test_get_preview_url_returns_valid_url()
    {
        $url = $this->mobilePreview->getPreviewUrl(123);

        $this->assertIsString($url);
        $this->assertStringContainsString('formflow-preview', $url);
        $this->assertStringContainsString('123', $url);
    }

    public function test_get_preview_url_includes_device_id()
    {
        $url = $this->mobilePreview->getPreviewUrl(123, 'iphone-15-pro');

        $this->assertStringContainsString('iphone-15-pro', $url);
    }

    public function test_get_preview_url_includes_token_when_requested()
    {
        $url = $this->mobilePreview->getPreviewUrl(123, '', true);

        $this->assertStringContainsString('preview_token', $url);
    }

    public function test_generate_preview_token_returns_string()
    {
        $token = $this->mobilePreview->generatePreviewToken(123);

        $this->assertIsString($token);
        $this->assertNotEmpty($token);
    }

    public function test_generate_preview_token_stores_in_transient()
    {
        $token = $this->mobilePreview->generatePreviewToken(123, 3600);

        $tokens = get_transient('ffp_preview_tokens');

        $this->assertIsArray($tokens);
        $this->assertArrayHasKey($token, $tokens);
        $this->assertEquals(123, $tokens[$token]['form_id']);
    }

    public function test_generate_preview_token_cleans_expired_tokens()
    {
        // Set expired token
        $oldTokens = [
            'expired_token' => [
                'form_id' => 123,
                'created' => time() - 10000,
                'expiry' => time() - 5000,
            ],
        ];
        set_transient('ffp_preview_tokens', $oldTokens, DAY_IN_SECONDS);

        $newToken = $this->mobilePreview->generatePreviewToken(456);

        $tokens = get_transient('ffp_preview_tokens');

        $this->assertArrayNotHasKey('expired_token', $tokens);
        $this->assertArrayHasKey($newToken, $tokens);
    }

    public function test_is_mobile_device_detects_mobile()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)';

        $result = $this->mobilePreview->isMobileDevice();

        $this->assertTrue($result);
    }

    public function test_is_mobile_device_detects_android()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Linux; Android 11; Pixel 5)';

        $result = $this->mobilePreview->isMobileDevice();

        $this->assertTrue($result);
    }

    public function test_is_mobile_device_returns_false_for_desktop()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

        $result = $this->mobilePreview->isMobileDevice();

        $this->assertFalse($result);
    }

    public function test_detect_device_type_returns_phone()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X)';

        $type = $this->mobilePreview->detectDeviceType();

        $this->assertEquals('phone', $type);
    }

    public function test_detect_device_type_returns_tablet()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPad; CPU OS 14_0 like Mac OS X)';

        $type = $this->mobilePreview->detectDeviceType();

        $this->assertEquals('tablet', $type);
    }

    public function test_detect_device_type_returns_desktop()
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';

        $type = $this->mobilePreview->detectDeviceType();

        $this->assertEquals('desktop', $type);
    }

    public function test_get_mobile_input_types_returns_array()
    {
        $types = $this->mobilePreview->getMobileInputTypes();

        $this->assertIsArray($types);
        $this->assertArrayHasKey('email', $types);
        $this->assertArrayHasKey('phone', $types);
        $this->assertArrayHasKey('number', $types);
        $this->assertArrayHasKey('date', $types);
    }

    public function test_get_mobile_input_types_maps_correctly()
    {
        $types = $this->mobilePreview->getMobileInputTypes();

        $this->assertEquals('email', $types['email']);
        $this->assertEquals('tel', $types['phone']);
        $this->assertEquals('number', $types['number']);
        $this->assertEquals('url', $types['url']);
    }

    public function test_get_touch_config_returns_configuration()
    {
        $config = $this->mobilePreview->getTouchConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('min_tap_target', $config);
        $this->assertArrayHasKey('input_font_size', $config);
        $this->assertArrayHasKey('button_min_height', $config);
        $this->assertArrayHasKey('enable_haptic', $config);
    }

    public function test_get_touch_config_meets_ios_standards()
    {
        $config = $this->mobilePreview->getTouchConfig();

        // iOS minimum tap target is 44px
        $this->assertGreaterThanOrEqual(44, $config['min_tap_target']);
        // iOS prevents zoom on inputs with font-size >= 16px
        $this->assertGreaterThanOrEqual(16, $config['input_font_size']);
    }

    public function test_generate_responsive_styles_returns_css()
    {
        $css = $this->mobilePreview->generateResponsiveStyles(123);

        $this->assertIsString($css);
        $this->assertStringContainsString('@media', $css);
        $this->assertStringContainsString('ffp-form', $css);
    }

    public function test_generate_responsive_styles_includes_breakpoints()
    {
        $css = $this->mobilePreview->generateResponsiveStyles(123);

        $this->assertStringContainsString('min-width', $css);
        $this->assertStringContainsString('max-width', $css);
    }

    public function test_generate_responsive_styles_includes_touch_styles()
    {
        $css = $this->mobilePreview->generateResponsiveStyles(123);

        $this->assertStringContainsString('hover: none', $css);
        $this->assertStringContainsString('pointer: coarse', $css);
        $this->assertStringContainsString('min-height', $css);
    }

    public function test_generate_responsive_styles_includes_safe_area_insets()
    {
        $css = $this->mobilePreview->generateResponsiveStyles(123);

        $this->assertStringContainsString('safe-area-inset', $css);
        $this->assertStringContainsString('env(safe-area-inset-bottom)', $css);
    }

    public function test_rest_get_devices_returns_response()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/preview/devices');

        $response = $this->mobilePreview->restGetDevices($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('devices', $data);
        $this->assertIsArray($data['devices']);
    }

    public function test_rest_get_devices_filters_by_type()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/preview/devices');
        $request->set_param('type', 'phone');

        $response = $this->mobilePreview->restGetDevices($request);

        $data = $response->get_data();

        foreach ($data['devices'] as $device) {
            $this->assertEquals('phone', $device['type']);
        }
    }

    public function test_rest_add_device_creates_custom_device()
    {
        $request = new \WP_REST_Request('POST', '/formflow/v1/preview/devices');
        $request->set_param('name', 'Test Device');
        $request->set_param('type', 'phone');
        $request->set_param('width', 400);
        $request->set_param('height', 800);
        $request->set_param('pixel_ratio', 2.0);

        $response = $this->mobilePreview->restAddDevice($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('device', $data);
        $this->assertEquals('Test Device', $data['device']['name']);
    }

    public function test_rest_get_preview_url_returns_url()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/preview/123/url');
        $request->set_param('form_id', 123);

        $response = $this->mobilePreview->restGetPreviewUrl($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('url', $data);
        $this->assertStringContainsString('123', $data['url']);
    }

    public function test_rest_get_breakpoints_returns_breakpoints()
    {
        $request = new \WP_REST_Request('GET', '/formflow/v1/preview/breakpoints');

        $response = $this->mobilePreview->restGetBreakpoints($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);

        $data = $response->get_data();
        $this->assertArrayHasKey('breakpoints', $data);
        $this->assertIsArray($data['breakpoints']);
    }

    public function test_ajax_sync_preview_returns_url()
    {
        $_POST['nonce'] = wp_create_nonce('ffp_preview_sync');
        $_POST['form_id'] = 123;
        $_POST['device'] = 'iphone-15-pro';

        ob_start();
        try {
            $this->mobilePreview->ajaxSyncPreview();
        } catch (\WPAjaxDieException $e) {
            // Expected
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('url', $data['data']);
    }

    public function test_ajax_log_interaction_stores_interaction()
    {
        $_POST['nonce'] = wp_create_nonce('ffp_preview_interaction');
        $_POST['session_id'] = 'test_session';
        $_POST['type'] = 'click';
        $_POST['data'] = json_encode(['element' => 'button']);

        // Initialize session
        $sessions = [
            'test_session' => [
                'form_id' => 123,
                'interactions' => [],
            ],
        ];
        set_transient('ffp_preview_sessions', $sessions, HOUR_IN_SECONDS);

        ob_start();
        try {
            $this->mobilePreview->ajaxLogInteraction();
        } catch (\WPAjaxDieException $e) {
            // Expected
        }
        ob_end_clean();

        $updatedSessions = get_transient('ffp_preview_sessions');
        $this->assertGreaterThan(0, count($updatedSessions['test_session']['interactions']));
    }

    public function test_ajax_generate_qr_code_returns_qr_url()
    {
        $_POST['nonce'] = wp_create_nonce('ffp_preview_qr');
        $_POST['form_id'] = 123;

        ob_start();
        try {
            $this->mobilePreview->ajaxGenerateQRCode();
        } catch (\WPAjaxDieException $e) {
            // Expected
        }
        $output = ob_get_clean();

        $data = json_decode($output, true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('qr_url', $data['data']);
        $this->assertArrayHasKey('preview_url', $data['data']);
    }

    public function test_device_profile_to_array()
    {
        $device = new DeviceProfile([
            'id' => 'test-device',
            'name' => 'Test Device',
            'type' => 'phone',
            'width' => 375,
            'height' => 667,
            'pixel_ratio' => 2.0,
            'platform' => 'ios',
        ]);

        $array = $device->toArray();

        $this->assertIsArray($array);
        $this->assertEquals('test-device', $array['id']);
        $this->assertEquals('Test Device', $array['name']);
        $this->assertEquals('phone', $array['type']);
        $this->assertEquals(375, $array['width']);
    }

    public function test_preview_session_add_interaction()
    {
        $session = new PreviewSession([
            'form_id' => 123,
            'device_id' => 'iphone-15-pro',
        ]);

        $session->addInteraction('click', ['element' => 'submit']);

        $this->assertCount(1, $session->interactions);
        $this->assertEquals('click', $session->interactions[0]['type']);
    }

    public function test_preview_session_add_error()
    {
        $session = new PreviewSession([
            'form_id' => 123,
        ]);

        $session->addError('validation', 'Field is required', ['field' => 'email']);

        $this->assertCount(1, $session->errors);
        $this->assertEquals('validation', $session->errors[0]['type']);
        $this->assertEquals('Field is required', $session->errors[0]['message']);
    }

    public function test_preview_session_set_performance()
    {
        $session = new PreviewSession([
            'form_id' => 123,
        ]);

        $session->setPerformance(['load_time' => 250, 'render_time' => 100]);

        $this->assertArrayHasKey('load_time', $session->performance);
        $this->assertEquals(250, $session->performance['load_time']);
    }

    public function test_responsive_breakpoint_matches()
    {
        $breakpoint = new ResponsiveBreakpoint('md', 768, 991, []);

        $this->assertTrue($breakpoint->matches(800));
        $this->assertFalse($breakpoint->matches(700));
        $this->assertFalse($breakpoint->matches(1000));
    }

    public function test_responsive_breakpoint_matches_with_no_min()
    {
        $breakpoint = new ResponsiveBreakpoint('xs', 0, 575, []);

        $this->assertTrue($breakpoint->matches(400));
        $this->assertTrue($breakpoint->matches(575));
        $this->assertFalse($breakpoint->matches(600));
    }

    public function test_responsive_breakpoint_matches_with_no_max()
    {
        $breakpoint = new ResponsiveBreakpoint('xl', 1200, 0, []);

        $this->assertTrue($breakpoint->matches(1200));
        $this->assertTrue($breakpoint->matches(1500));
        $this->assertFalse($breakpoint->matches(1000));
    }
}
