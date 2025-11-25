<?php

declare(strict_types=1);

/**
 * OpenAI Provider
 *
 * OpenAI API integration for AI features.
 *
 * @package FormFlowPro\AI
 * @since 2.3.0
 */

namespace FormFlowPro\AI;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * OpenAI Provider Class
 */
class OpenAIProvider implements AIProviderInterface
{
    /**
     * API base URL
     */
    private const API_BASE = 'https://api.openai.com/v1';

    /**
     * API key
     *
     * @var string
     */
    private string $apiKey;

    /**
     * Model to use
     *
     * @var string
     */
    private string $model;

    /**
     * Usage tracking
     *
     * @var array
     */
    private array $usage = [
        'prompt_tokens' => 0,
        'completion_tokens' => 0,
        'total_tokens' => 0,
        'requests' => 0,
    ];

    /**
     * Constructor
     *
     * @param string $apiKey API key
     * @param string $model Model name
     */
    public function __construct(string $apiKey, string $model = 'gpt-4o-mini')
    {
        $this->apiKey = $apiKey;
        $this->model = $model;
    }

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'openai';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'OpenAI';
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * {@inheritdoc}
     */
    public function complete(string $prompt, array $options = []): string
    {
        if (!$this->isConfigured()) {
            throw new \Exception(__('OpenAI API key not configured.', 'formflow-pro'));
        }

        // Rate limiting
        if (!$this->checkRateLimit()) {
            throw new \Exception(__('AI rate limit exceeded. Please try again later.', 'formflow-pro'));
        }

        $model = $options['model'] ?? $this->model;
        $maxTokens = $options['max_tokens'] ?? 500;
        $temperature = $options['temperature'] ?? 0.7;

        $body = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a helpful assistant for FormFlow Pro, a WordPress form plugin. Provide concise, accurate responses.',
                ],
                [
                    'role' => 'user',
                    'content' => $prompt,
                ],
            ],
            'max_tokens' => $maxTokens,
            'temperature' => $temperature,
        ];

        $response = $this->makeRequest('/chat/completions', $body);

        // Track usage
        if (isset($response['usage'])) {
            $this->usage['prompt_tokens'] += $response['usage']['prompt_tokens'] ?? 0;
            $this->usage['completion_tokens'] += $response['usage']['completion_tokens'] ?? 0;
            $this->usage['total_tokens'] += $response['usage']['total_tokens'] ?? 0;
        }
        $this->usage['requests']++;

        // Update rate limit counter
        $this->incrementRateLimit();

        return $response['choices'][0]['message']['content'] ?? '';
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string $text): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception(__('OpenAI API key not configured.', 'formflow-pro'));
        }

        $body = [
            'model' => 'text-embedding-3-small',
            'input' => $text,
        ];

        $response = $this->makeRequest('/embeddings', $body);

        return $response['data'][0]['embedding'] ?? [];
    }

    /**
     * {@inheritdoc}
     */
    public function getModels(): array
    {
        return [
            [
                'id' => 'gpt-4o-mini',
                'name' => 'GPT-4o Mini',
                'description' => __('Fast and cost-effective for most tasks.', 'formflow-pro'),
                'context_length' => 128000,
                'cost_per_1k' => 0.00015,
            ],
            [
                'id' => 'gpt-4o',
                'name' => 'GPT-4o',
                'description' => __('Most capable model for complex tasks.', 'formflow-pro'),
                'context_length' => 128000,
                'cost_per_1k' => 0.005,
            ],
            [
                'id' => 'gpt-4-turbo',
                'name' => 'GPT-4 Turbo',
                'description' => __('High capability with vision support.', 'formflow-pro'),
                'context_length' => 128000,
                'cost_per_1k' => 0.01,
            ],
            [
                'id' => 'gpt-3.5-turbo',
                'name' => 'GPT-3.5 Turbo',
                'description' => __('Legacy model, cost-effective for simple tasks.', 'formflow-pro'),
                'context_length' => 16385,
                'cost_per_1k' => 0.0005,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUsage(): array
    {
        return $this->usage;
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint
     * @param array $body Request body
     * @return array Response data
     * @throws \Exception On API error
     */
    private function makeRequest(string $endpoint, array $body): array
    {
        $url = self::API_BASE . $endpoint;

        $response = wp_remote_post($url, [
            'timeout' => 60,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            throw new \Exception($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);
        $data = json_decode($responseBody, true);

        if ($code >= 400) {
            $error = $data['error']['message'] ?? "HTTP {$code}";
            throw new \Exception($error);
        }

        return $data;
    }

    /**
     * Check rate limit
     *
     * @return bool
     */
    private function checkRateLimit(): bool
    {
        $config = get_option('formflow_ai_settings', []);
        $limit = (int) ($config['rate_limit'] ?? 100);

        $count = (int) get_transient('formflow_ai_rate_count');

        return $count < $limit;
    }

    /**
     * Increment rate limit counter
     *
     * @return void
     */
    private function incrementRateLimit(): void
    {
        $count = (int) get_transient('formflow_ai_rate_count');
        set_transient('formflow_ai_rate_count', $count + 1, 3600); // Reset hourly
    }

    /**
     * Moderate content
     *
     * @param string $input Content to moderate
     * @return array Moderation results
     */
    public function moderate(string $input): array
    {
        if (!$this->isConfigured()) {
            return ['flagged' => false];
        }

        try {
            $response = $this->makeRequest('/moderations', [
                'input' => $input,
            ]);

            return $response['results'][0] ?? ['flagged' => false];
        } catch (\Exception $e) {
            return ['flagged' => false, 'error' => $e->getMessage()];
        }
    }
}
