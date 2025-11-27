<?php
/**
 * Tests for AIService class.
 */

namespace FormFlowPro\Tests\Unit\AI;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\AI\AIService;
use FormFlowPro\AI\OpenAIProvider;
use FormFlowPro\AI\LocalAIProvider;

class AIServiceTest extends TestCase
{
    private $aiService;

    protected function setUp(): void
    {
        parent::setUp();

        // Set default options
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'provider' => 'openai',
            'api_key' => 'test_api_key',
            'model' => 'gpt-4o-mini',
            'features' => [
                'spam_detection' => true,
                'smart_validation' => true,
                'auto_suggestions' => true,
                'document_classification' => true,
                'sentiment_analysis' => true,
            ],
            'spam_threshold' => 0.7,
            'cache_responses' => true,
            'cache_ttl' => 3600,
            'rate_limit' => 100,
            'log_requests' => false,
        ]);

        // Reset singleton
        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $this->aiService = AIService::getInstance();
    }

    public function test_get_instance_returns_singleton()
    {
        $instance1 = AIService::getInstance();
        $instance2 = AIService::getInstance();

        $this->assertSame($instance1, $instance2);
        $this->assertInstanceOf(AIService::class, $instance1);
    }

    public function test_is_enabled_returns_true_when_configured()
    {
        $this->assertTrue($this->aiService->isEnabled());
    }

    public function test_is_enabled_returns_false_when_disabled()
    {
        update_option('formflow_ai_settings', ['enabled' => false]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $this->assertFalse($service->isEnabled());
    }

    public function test_is_enabled_returns_false_without_api_key()
    {
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'api_key' => '',
        ]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $this->assertFalse($service->isEnabled());
    }

    public function test_is_feature_enabled_returns_true_for_enabled_feature()
    {
        $this->assertTrue($this->aiService->isFeatureEnabled('spam_detection'));
        $this->assertTrue($this->aiService->isFeatureEnabled('smart_validation'));
        $this->assertTrue($this->aiService->isFeatureEnabled('auto_suggestions'));
    }

    public function test_is_feature_enabled_returns_false_for_disabled_feature()
    {
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'api_key' => 'test_key',
            'features' => [
                'spam_detection' => false,
            ],
        ]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $this->assertFalse($service->isFeatureEnabled('spam_detection'));
    }

    public function test_get_config_returns_configuration()
    {
        $config = $this->aiService->getConfig();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled', $config);
        $this->assertArrayHasKey('provider', $config);
        $this->assertArrayHasKey('api_key', $config);
        $this->assertArrayHasKey('model', $config);
        $this->assertArrayHasKey('features', $config);
        $this->assertTrue($config['enabled']);
        $this->assertEquals('openai', $config['provider']);
    }

    public function test_save_config_updates_settings()
    {
        $newConfig = [
            'enabled' => true,
            'provider' => 'local',
            'api_key' => 'new_api_key',
            'model' => 'gpt-4o',
            'features' => [
                'spam_detection' => true,
                'smart_validation' => false,
                'auto_suggestions' => false,
                'document_classification' => true,
                'sentiment_analysis' => false,
            ],
            'spam_threshold' => 0.8,
            'cache_responses' => false,
            'cache_ttl' => 7200,
            'rate_limit' => 50,
            'log_requests' => true,
        ];

        $result = $this->aiService->saveConfig($newConfig);

        $this->assertTrue($result);

        $savedConfig = get_option('formflow_ai_settings');
        $this->assertEquals('local', $savedConfig['provider']);
        $this->assertEquals('new_api_key', $savedConfig['api_key']);
        $this->assertEquals('gpt-4o', $savedConfig['model']);
        $this->assertEquals(0.8, $savedConfig['spam_threshold']);
        $this->assertFalse($savedConfig['cache_responses']);
        $this->assertEquals(7200, $savedConfig['cache_ttl']);
        $this->assertEquals(50, $savedConfig['rate_limit']);
        $this->assertTrue($savedConfig['log_requests']);
    }

    public function test_save_config_sanitizes_spam_threshold()
    {
        // Test upper bound
        $result = $this->aiService->saveConfig(['spam_threshold' => 1.5]);
        $config = get_option('formflow_ai_settings');
        $this->assertEquals(1, $config['spam_threshold']);

        // Test lower bound
        $result = $this->aiService->saveConfig(['spam_threshold' => -0.5]);
        $config = get_option('formflow_ai_settings');
        $this->assertEquals(0, $config['spam_threshold']);

        // Test valid value
        $result = $this->aiService->saveConfig(['spam_threshold' => 0.75]);
        $config = get_option('formflow_ai_settings');
        $this->assertEquals(0.75, $config['spam_threshold']);
    }

    public function test_analyze_for_spam_returns_score()
    {
        $submission = [
            'form_data' => [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'message' => 'This is a legitimate message',
            ],
        ];

        $score = $this->aiService->analyzeForSpam($submission);

        $this->assertIsFloat($score);
        $this->assertGreaterThanOrEqual(0, $score);
        $this->assertLessThanOrEqual(1, $score);
    }

    public function test_analyze_for_spam_detects_spam_keywords()
    {
        $submission = [
            'form_data' => [
                'name' => 'Spammer',
                'email' => 'spam@example.com',
                'message' => 'Buy viagra and cialis now! Click here for free money!',
            ],
        ];

        $score = $this->aiService->analyzeForSpam($submission);

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_analyze_for_spam_detects_excessive_links()
    {
        $submission = [
            'form_data' => [
                'message' => 'Visit https://spam1.com and https://spam2.com and https://spam3.com and https://spam4.com',
            ],
        ];

        $score = $this->aiService->analyzeForSpam($submission);

        $this->assertGreaterThan(0.3, $score);
    }

    public function test_analyze_for_spam_detects_all_caps()
    {
        $submission = [
            'form_data' => [
                'message' => 'THIS IS ALL CAPS MESSAGE THAT LOOKS LIKE SPAM',
            ],
        ];

        $score = $this->aiService->analyzeForSpam($submission);

        $this->assertGreaterThan(0.1, $score);
    }

    public function test_detect_spam_adds_error_when_spam_detected()
    {
        $submission = [
            'form_data' => [
                'message' => 'viagra casino lottery winner prize money click here free money',
            ],
        ];

        $errors = $this->aiService->detectSpam([], $submission);

        $this->assertArrayHasKey('spam', $errors);
        $this->assertStringContainsString('spam', strtolower($errors['spam']));
    }

    public function test_detect_spam_returns_empty_when_legitimate()
    {
        $submission = [
            'form_data' => [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'message' => 'I would like to inquire about your services.',
            ],
        ];

        $errors = $this->aiService->detectSpam([], $submission);

        $this->assertArrayNotHasKey('spam', $errors);
    }

    public function test_detect_spam_skipped_when_feature_disabled()
    {
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'api_key' => 'test_key',
            'features' => [
                'spam_detection' => false,
            ],
        ]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $submission = [
            'form_data' => [
                'message' => 'viagra casino lottery',
            ],
        ];

        $errors = $service->detectSpam([], $submission);

        $this->assertEmpty($errors);
    }

    public function test_smart_validation_validates_email_field()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'email', 'invalid-email');

        $this->assertArrayHasKey('email', $result);
    }

    public function test_smart_validation_accepts_valid_email()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'email', 'valid@example.com');

        $this->assertArrayNotHasKey('email', $result);
    }

    public function test_smart_validation_detects_disposable_email()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'user_email', 'test@tempmail.com');

        $this->assertArrayHasKey('user_email', $result);
        $this->assertStringContainsString('permanent', strtolower($result['user_email']));
    }

    public function test_smart_validation_suggests_typo_correction()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'email', 'user@gmal.com');

        $this->assertArrayHasKey('email', $result);
        $this->assertStringContainsString('gmail.com', $result['email']);
    }

    public function test_smart_validation_validates_phone_number()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'phone', '123');

        $this->assertArrayHasKey('phone', $result);
    }

    public function test_smart_validation_accepts_valid_phone()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'phone', '+1 (555) 123-4567');

        $this->assertArrayNotHasKey('phone', $result);
    }

    public function test_smart_validation_rejects_fake_phone()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'phone_number', '1111111111');

        $this->assertArrayHasKey('phone_number', $result);
    }

    public function test_smart_validation_validates_name_field()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'name', 'A');

        $this->assertArrayHasKey('name', $result);
    }

    public function test_smart_validation_rejects_numbers_in_name()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'first_name', 'John123');

        $this->assertArrayHasKey('first_name', $result);
    }

    public function test_smart_validation_rejects_fake_names()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'name', 'asdf');

        $this->assertArrayHasKey('name', $result);
    }

    public function test_smart_validation_validates_address_length()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'address', 'Short');

        $this->assertArrayHasKey('address', $result);
    }

    public function test_smart_validation_accepts_complete_address()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'street_address', '123 Main Street, Suite 100');

        $this->assertArrayNotHasKey('street_address', $result);
    }

    public function test_smart_validation_skips_empty_values()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'email', '');

        $this->assertEmpty($result);
    }

    public function test_smart_validation_skips_non_string_values()
    {
        $errors = [];
        $result = $this->aiService->smartValidation($errors, 'field', 123);

        $this->assertEmpty($result);
    }

    public function test_classify_content_returns_classification()
    {
        // Mock provider to return classification
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willReturn(json_encode([
            'category' => 'inquiry',
            'priority' => 'medium',
            'sentiment' => 'neutral',
            'topics' => ['product', 'pricing'],
        ]));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->classifyContent('I would like to know about your pricing');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('category', $result);
        $this->assertArrayHasKey('priority', $result);
        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('topics', $result);
    }

    public function test_classify_content_caches_results()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->expects($this->once())
            ->method('complete')
            ->willReturn(json_encode([
                'category' => 'support',
                'priority' => 'high',
                'sentiment' => 'negative',
                'topics' => ['technical'],
            ]));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        // First call - should hit API
        $result1 = $this->aiService->classifyContent('Help! Something is broken');

        // Second call - should use cache
        $result2 = $this->aiService->classifyContent('Help! Something is broken');

        $this->assertEquals($result1, $result2);
    }

    public function test_classify_content_returns_null_on_error()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willThrowException(new \Exception('API Error'));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->classifyContent('Test content');

        $this->assertNull($result);
    }

    public function test_get_auto_suggestions_returns_array()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willReturn(json_encode([
            'New York, NY',
            'New York, USA',
            'New York City',
        ]));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->getAutoSuggestions('city', 'New York');

        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function test_get_auto_suggestions_returns_empty_when_disabled()
    {
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'api_key' => 'test_key',
            'features' => [
                'auto_suggestions' => false,
            ],
        ]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $result = $service->getAutoSuggestions('field', 'value');

        $this->assertEmpty($result);
    }

    public function test_get_auto_suggestions_caches_results()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->expects($this->once())
            ->method('complete')
            ->willReturn(json_encode(['suggestion1', 'suggestion2']));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        // First call
        $result1 = $this->aiService->getAutoSuggestions('field', 'value');

        // Second call - should use cache
        $result2 = $this->aiService->getAutoSuggestions('field', 'value');

        $this->assertEquals($result1, $result2);
    }

    public function test_analyze_sentiment_returns_analysis()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willReturn(json_encode([
            'sentiment' => 'positive',
            'score' => 0.85,
            'emotions' => ['happy', 'excited'],
        ]));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->analyzeSentiment('I love this product!');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('sentiment', $result);
        $this->assertArrayHasKey('score', $result);
        $this->assertArrayHasKey('emotions', $result);
        $this->assertEquals('positive', $result['sentiment']);
    }

    public function test_analyze_sentiment_returns_null_when_disabled()
    {
        update_option('formflow_ai_settings', [
            'enabled' => true,
            'api_key' => 'test_key',
            'features' => [
                'sentiment_analysis' => false,
            ],
        ]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $result = $service->analyzeSentiment('Test text');

        $this->assertNull($result);
    }

    public function test_test_connection_returns_success()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willReturn('Hello from FormFlow Pro!');

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->testConnection();

        $this->assertIsArray($result);
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_test_connection_returns_failure_on_error()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willThrowException(new \Exception('Connection failed'));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $result = $this->aiService->testConnection();

        $this->assertIsArray($result);
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('failed', strtolower($result['message']));
    }

    public function test_test_connection_returns_failure_without_provider()
    {
        update_option('formflow_ai_settings', ['enabled' => false]);

        $reflection = new \ReflectionClass(AIService::class);
        $instance = $reflection->getProperty('instance');
        $instance->setAccessible(true);
        $instance->setValue(null, null);

        $service = AIService::getInstance();

        $result = $service->testConnection();

        $this->assertFalse($result['success']);
    }

    public function test_classify_submission_saves_classification()
    {
        global $wpdb;

        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->method('complete')->willReturn(json_encode([
            'category' => 'support',
            'priority' => 'high',
            'sentiment' => 'negative',
            'topics' => ['technical'],
        ]));

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $submission = [
            'form_data' => [
                'message' => 'I need help with a technical issue',
            ],
        ];

        $this->aiService->classifySubmission($submission, 123);

        $classification = get_post_meta(123, '_formflow_classification', true);

        $this->assertIsArray($classification);
        $this->assertEquals('support', $classification['category']);
    }

    public function test_classify_submission_skipped_for_empty_content()
    {
        $mockProvider = $this->createMock(OpenAIProvider::class);
        $mockProvider->expects($this->never())->method('complete');

        $reflection = new \ReflectionProperty(AIService::class, 'provider');
        $reflection->setAccessible(true);
        $reflection->setValue($this->aiService, $mockProvider);

        $submission = [
            'form_data' => [],
        ];

        $this->aiService->classifySubmission($submission, 123);

        // Should not save anything
        $this->assertTrue(true);
    }
}
