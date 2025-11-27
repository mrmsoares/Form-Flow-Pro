<?php
/**
 * Tests for OpenAIProvider class.
 */

namespace FormFlowPro\Tests\Unit\AI;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\AI\OpenAIProvider;

class OpenAIProviderTest extends TestCase
{
    private $provider;
    private $apiKey = 'test_api_key_123';
    private $model = 'gpt-4o-mini';

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new OpenAIProvider($this->apiKey, $this->model);

        // Reset rate limit
        delete_transient('formflow_ai_rate_count');
    }

    public function test_constructor_sets_api_key_and_model()
    {
        $provider = new OpenAIProvider('my_api_key', 'gpt-4o');

        $this->assertInstanceOf(OpenAIProvider::class, $provider);
    }

    public function test_get_id_returns_openai()
    {
        $this->assertEquals('openai', $this->provider->getId());
    }

    public function test_get_name_returns_openai()
    {
        $name = $this->provider->getName();

        $this->assertEquals('OpenAI', $name);
    }

    public function test_is_configured_returns_true_with_api_key()
    {
        $this->assertTrue($this->provider->isConfigured());
    }

    public function test_is_configured_returns_false_without_api_key()
    {
        $provider = new OpenAIProvider('');

        $this->assertFalse($provider->isConfigured());
    }

    public function test_complete_throws_exception_when_not_configured()
    {
        $provider = new OpenAIProvider('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API key not configured');

        $provider->complete('Test prompt');
    }

    public function test_complete_makes_successful_api_call()
    {
        // Mock wp_remote_post
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'AI response here']],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ]),
        ];

        $result = $this->provider->complete('Test prompt');

        $this->assertEquals('AI response here', $result);
    }

    public function test_complete_uses_custom_options()
    {
        global $wp_remote_post_response, $wp_remote_post_args;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Custom response']],
                ],
                'usage' => ['total_tokens' => 50],
            ]),
        ];

        $result = $this->provider->complete('Test', [
            'model' => 'gpt-4o',
            'max_tokens' => 100,
            'temperature' => 0.5,
        ]);

        $this->assertEquals('Custom response', $result);

        // Verify request body contains custom options
        $body = json_decode($wp_remote_post_args['body'], true);
        $this->assertEquals('gpt-4o', $body['model']);
        $this->assertEquals(100, $body['max_tokens']);
        $this->assertEquals(0.5, $body['temperature']);
    }

    public function test_complete_includes_system_message()
    {
        global $wp_remote_post_response, $wp_remote_post_args;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $this->provider->complete('Test prompt');

        $body = json_decode($wp_remote_post_args['body'], true);
        $this->assertCount(2, $body['messages']);
        $this->assertEquals('system', $body['messages'][0]['role']);
        $this->assertEquals('user', $body['messages'][1]['role']);
        $this->assertStringContainsString('FormFlow Pro', $body['messages'][0]['content']);
    }

    public function test_complete_tracks_usage()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
                'usage' => [
                    'prompt_tokens' => 15,
                    'completion_tokens' => 25,
                    'total_tokens' => 40,
                ],
            ]),
        ];

        $this->provider->complete('Test');

        $usage = $this->provider->getUsage();

        $this->assertEquals(15, $usage['prompt_tokens']);
        $this->assertEquals(25, $usage['completion_tokens']);
        $this->assertEquals(40, $usage['total_tokens']);
        $this->assertEquals(1, $usage['requests']);
    }

    public function test_complete_accumulates_usage_across_calls()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
                'usage' => [
                    'prompt_tokens' => 10,
                    'completion_tokens' => 20,
                    'total_tokens' => 30,
                ],
            ]),
        ];

        $this->provider->complete('First');
        $this->provider->complete('Second');

        $usage = $this->provider->getUsage();

        $this->assertEquals(20, $usage['prompt_tokens']);
        $this->assertEquals(40, $usage['completion_tokens']);
        $this->assertEquals(60, $usage['total_tokens']);
        $this->assertEquals(2, $usage['requests']);
    }

    public function test_complete_throws_exception_on_api_error()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 401],
            'body' => json_encode([
                'error' => [
                    'message' => 'Invalid API key',
                ],
            ]),
        ];

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid API key');

        $this->provider->complete('Test');
    }

    public function test_complete_throws_exception_on_network_error()
    {
        global $wp_remote_post_error;
        $wp_remote_post_error = 'Connection timeout';

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Connection timeout');

        $this->provider->complete('Test');
    }

    public function test_complete_respects_rate_limit()
    {
        update_option('formflow_ai_settings', ['rate_limit' => 5]);
        set_transient('formflow_ai_rate_count', 5, 3600);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('rate limit exceeded');

        $this->provider->complete('Test');
    }

    public function test_complete_increments_rate_limit_counter()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $this->assertEquals(0, (int) get_transient('formflow_ai_rate_count'));

        $this->provider->complete('Test');

        $this->assertEquals(1, (int) get_transient('formflow_ai_rate_count'));
    }

    public function test_embed_generates_embeddings()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3, 0.4, 0.5]],
                ],
            ]),
        ];

        $result = $this->provider->embed('Test text');

        $this->assertIsArray($result);
        $this->assertCount(5, $result);
        $this->assertEquals([0.1, 0.2, 0.3, 0.4, 0.5], $result);
    }

    public function test_embed_throws_exception_when_not_configured()
    {
        $provider = new OpenAIProvider('');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API key not configured');

        $provider->embed('Test text');
    }

    public function test_embed_uses_correct_model()
    {
        global $wp_remote_post_response, $wp_remote_post_args;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    ['embedding' => [0.1, 0.2]],
                ],
            ]),
        ];

        $this->provider->embed('Test');

        $body = json_decode($wp_remote_post_args['body'], true);
        $this->assertEquals('text-embedding-3-small', $body['model']);
        $this->assertEquals('Test', $body['input']);
    }

    public function test_get_models_returns_available_models()
    {
        $models = $this->provider->getModels();

        $this->assertIsArray($models);
        $this->assertNotEmpty($models);

        foreach ($models as $model) {
            $this->assertArrayHasKey('id', $model);
            $this->assertArrayHasKey('name', $model);
            $this->assertArrayHasKey('description', $model);
            $this->assertArrayHasKey('context_length', $model);
            $this->assertArrayHasKey('cost_per_1k', $model);
        }
    }

    public function test_get_models_includes_gpt_4o_mini()
    {
        $models = $this->provider->getModels();
        $ids = array_column($models, 'id');

        $this->assertContains('gpt-4o-mini', $ids);
    }

    public function test_get_models_includes_gpt_4o()
    {
        $models = $this->provider->getModels();
        $ids = array_column($models, 'id');

        $this->assertContains('gpt-4o', $ids);
    }

    public function test_get_usage_returns_initial_zeros()
    {
        $usage = $this->provider->getUsage();

        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
        $this->assertEquals(0, $usage['requests']);
    }

    public function test_moderate_detects_harmful_content()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'results' => [
                    [
                        'flagged' => true,
                        'categories' => [
                            'violence' => true,
                            'hate' => false,
                        ],
                    ],
                ],
            ]),
        ];

        $result = $this->provider->moderate('Harmful content here');

        $this->assertTrue($result['flagged']);
        $this->assertTrue($result['categories']['violence']);
    }

    public function test_moderate_returns_safe_for_clean_content()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'results' => [
                    ['flagged' => false],
                ],
            ]),
        ];

        $result = $this->provider->moderate('Clean content');

        $this->assertFalse($result['flagged']);
    }

    public function test_moderate_returns_unflagged_when_not_configured()
    {
        $provider = new OpenAIProvider('');

        $result = $provider->moderate('Test content');

        $this->assertFalse($result['flagged']);
    }

    public function test_moderate_handles_api_errors()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 500],
            'body' => json_encode([
                'error' => ['message' => 'Server error'],
            ]),
        ];

        $result = $this->provider->moderate('Test');

        $this->assertFalse($result['flagged']);
        $this->assertArrayHasKey('error', $result);
    }

    public function test_complete_sends_authorization_header()
    {
        global $wp_remote_post_response, $wp_remote_post_args;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $this->provider->complete('Test');

        $this->assertArrayHasKey('headers', $wp_remote_post_args);
        $this->assertArrayHasKey('Authorization', $wp_remote_post_args['headers']);
        $this->assertEquals('Bearer ' . $this->apiKey, $wp_remote_post_args['headers']['Authorization']);
    }

    public function test_complete_sends_correct_content_type()
    {
        global $wp_remote_post_response, $wp_remote_post_args;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $this->provider->complete('Test');

        $this->assertEquals('application/json', $wp_remote_post_args['headers']['Content-Type']);
    }

    public function test_complete_uses_correct_endpoint()
    {
        global $wp_remote_post_response, $wp_remote_post_url;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $this->provider->complete('Test');

        $this->assertEquals('https://api.openai.com/v1/chat/completions', $wp_remote_post_url);
    }

    public function test_embed_uses_correct_endpoint()
    {
        global $wp_remote_post_response, $wp_remote_post_url;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    ['embedding' => [0.1]],
                ],
            ]),
        ];

        $this->provider->embed('Test');

        $this->assertEquals('https://api.openai.com/v1/embeddings', $wp_remote_post_url);
    }

    public function test_complete_handles_missing_response_content()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => []],
                ],
            ]),
        ];

        $result = $this->provider->complete('Test');

        $this->assertEquals('', $result);
    }

    public function test_embed_handles_missing_embedding_data()
    {
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    [],
                ],
            ]),
        ];

        $result = $this->provider->embed('Test');

        $this->assertEquals([], $result);
    }
}
