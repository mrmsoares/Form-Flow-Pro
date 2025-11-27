<?php
/**
 * Tests for AIProviderInterface compliance.
 */

namespace FormFlowPro\Tests\Unit\AI;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\AI\AIProviderInterface;
use FormFlowPro\AI\OpenAIProvider;
use FormFlowPro\AI\LocalAIProvider;

class AIProviderInterfaceTest extends TestCase
{
    public function test_openai_provider_implements_interface()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function test_local_provider_implements_interface()
    {
        $provider = new LocalAIProvider();

        $this->assertInstanceOf(AIProviderInterface::class, $provider);
    }

    public function test_openai_provider_has_get_id_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'getId'));
        $this->assertIsString($provider->getId());
    }

    public function test_local_provider_has_get_id_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'getId'));
        $this->assertIsString($provider->getId());
    }

    public function test_openai_provider_has_get_name_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'getName'));
        $this->assertIsString($provider->getName());
    }

    public function test_local_provider_has_get_name_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'getName'));
        $this->assertIsString($provider->getName());
    }

    public function test_openai_provider_has_is_configured_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'isConfigured'));
        $this->assertIsBool($provider->isConfigured());
    }

    public function test_local_provider_has_is_configured_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'isConfigured'));
        $this->assertIsBool($provider->isConfigured());
    }

    public function test_openai_provider_has_complete_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'complete'));

        // Mock successful API call
        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'choices' => [
                    ['message' => ['content' => 'Response']],
                ],
            ]),
        ];

        $result = $provider->complete('Test');
        $this->assertIsString($result);
    }

    public function test_local_provider_has_complete_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'complete'));

        $result = $provider->complete('Analyze spam rate: test');
        $this->assertIsString($result);
    }

    public function test_openai_provider_has_embed_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'embed'));

        global $wp_remote_post_response;
        $wp_remote_post_response = [
            'response' => ['code' => 200],
            'body' => json_encode([
                'data' => [
                    ['embedding' => [0.1, 0.2, 0.3]],
                ],
            ]),
        ];

        $result = $provider->embed('Test');
        $this->assertIsArray($result);
    }

    public function test_local_provider_has_embed_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'embed'));

        $result = $provider->embed('Test');
        $this->assertIsArray($result);
    }

    public function test_openai_provider_has_get_models_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'getModels'));

        $result = $provider->getModels();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_local_provider_has_get_models_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'getModels'));

        $result = $provider->getModels();
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    public function test_openai_provider_has_get_usage_method()
    {
        $provider = new OpenAIProvider('test_key');

        $this->assertTrue(method_exists($provider, 'getUsage'));

        $result = $provider->getUsage();
        $this->assertIsArray($result);
    }

    public function test_local_provider_has_get_usage_method()
    {
        $provider = new LocalAIProvider();

        $this->assertTrue(method_exists($provider, 'getUsage'));

        $result = $provider->getUsage();
        $this->assertIsArray($result);
    }

    public function test_providers_return_unique_ids()
    {
        $openai = new OpenAIProvider('test_key');
        $local = new LocalAIProvider();

        $this->assertNotEquals($openai->getId(), $local->getId());
    }

    public function test_providers_return_different_names()
    {
        $openai = new OpenAIProvider('test_key');
        $local = new LocalAIProvider();

        $this->assertNotEquals($openai->getName(), $local->getName());
    }

    public function test_get_models_returns_proper_structure()
    {
        $providers = [
            new OpenAIProvider('test_key'),
            new LocalAIProvider(),
        ];

        foreach ($providers as $provider) {
            $models = $provider->getModels();

            foreach ($models as $model) {
                $this->assertIsArray($model);
                $this->assertArrayHasKey('id', $model);
                $this->assertArrayHasKey('name', $model);
                $this->assertArrayHasKey('description', $model);
            }
        }
    }

    public function test_get_usage_returns_proper_structure()
    {
        $providers = [
            new OpenAIProvider('test_key'),
            new LocalAIProvider(),
        ];

        foreach ($providers as $provider) {
            $usage = $provider->getUsage();

            $this->assertIsArray($usage);
            $this->assertArrayHasKey('prompt_tokens', $usage);
            $this->assertArrayHasKey('completion_tokens', $usage);
            $this->assertArrayHasKey('total_tokens', $usage);
            $this->assertArrayHasKey('requests', $usage);
        }
    }

    public function test_complete_accepts_options_parameter()
    {
        $provider = new LocalAIProvider();

        $result = $provider->complete('Analyze spam rate: test', [
            'max_tokens' => 100,
            'temperature' => 0.5,
        ]);

        $this->assertIsString($result);
    }

    public function test_complete_works_without_options()
    {
        $provider = new LocalAIProvider();

        $result = $provider->complete('Analyze spam rate: test');

        $this->assertIsString($result);
    }

    public function test_embed_returns_numeric_array()
    {
        $provider = new LocalAIProvider();

        $result = $provider->embed('Test');

        $this->assertIsArray($result);

        foreach ($result as $value) {
            $this->assertIsNumeric($value);
        }
    }

    public function test_providers_have_consistent_interface()
    {
        $openai = new OpenAIProvider('test_key');
        $local = new LocalAIProvider();

        $openaiMethods = get_class_methods($openai);
        $localMethods = get_class_methods($local);

        $interfaceMethods = ['getId', 'getName', 'isConfigured', 'complete', 'embed', 'getModels', 'getUsage'];

        foreach ($interfaceMethods as $method) {
            $this->assertContains($method, $openaiMethods);
            $this->assertContains($method, $localMethods);
        }
    }

    public function test_openai_complete_method_signature()
    {
        $provider = new OpenAIProvider('test_key');
        $reflection = new \ReflectionMethod($provider, 'complete');

        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('prompt', $params[0]->getName());
        $this->assertEquals('options', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
    }

    public function test_local_complete_method_signature()
    {
        $provider = new LocalAIProvider();
        $reflection = new \ReflectionMethod($provider, 'complete');

        $this->assertEquals(2, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('prompt', $params[0]->getName());
        $this->assertEquals('options', $params[1]->getName());
        $this->assertTrue($params[1]->isOptional());
    }

    public function test_openai_embed_method_signature()
    {
        $provider = new OpenAIProvider('test_key');
        $reflection = new \ReflectionMethod($provider, 'embed');

        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('text', $params[0]->getName());
    }

    public function test_local_embed_method_signature()
    {
        $provider = new LocalAIProvider();
        $reflection = new \ReflectionMethod($provider, 'embed');

        $this->assertEquals(1, $reflection->getNumberOfParameters());

        $params = $reflection->getParameters();
        $this->assertEquals('text', $params[0]->getName());
    }

    public function test_interface_defines_required_methods()
    {
        $reflection = new \ReflectionClass(AIProviderInterface::class);
        $methods = $reflection->getMethods();

        $methodNames = array_map(function ($method) {
            return $method->getName();
        }, $methods);

        $this->assertContains('getId', $methodNames);
        $this->assertContains('getName', $methodNames);
        $this->assertContains('isConfigured', $methodNames);
        $this->assertContains('complete', $methodNames);
        $this->assertContains('embed', $methodNames);
        $this->assertContains('getModels', $methodNames);
        $this->assertContains('getUsage', $methodNames);
    }

    public function test_all_interface_methods_return_correct_types()
    {
        $provider = new LocalAIProvider();

        // getId returns string
        $this->assertIsString($provider->getId());

        // getName returns string
        $this->assertIsString($provider->getName());

        // isConfigured returns bool
        $this->assertIsBool($provider->isConfigured());

        // complete returns string
        $this->assertIsString($provider->complete('test'));

        // embed returns array
        $this->assertIsArray($provider->embed('test'));

        // getModels returns array
        $this->assertIsArray($provider->getModels());

        // getUsage returns array
        $this->assertIsArray($provider->getUsage());
    }

    public function test_providers_can_be_type_hinted()
    {
        $testFunction = function (AIProviderInterface $provider) {
            return $provider->getId();
        };

        $openai = new OpenAIProvider('test_key');
        $local = new LocalAIProvider();

        $this->assertIsString($testFunction($openai));
        $this->assertIsString($testFunction($local));
    }
}
