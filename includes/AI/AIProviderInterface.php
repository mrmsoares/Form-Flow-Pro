<?php

declare(strict_types=1);

/**
 * AI Provider Interface
 *
 * Interface for AI service providers.
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
 * AI Provider Interface
 */
interface AIProviderInterface
{
    /**
     * Get provider identifier
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get provider name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Check if provider is configured
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Complete a prompt
     *
     * @param string $prompt Prompt text
     * @param array $options Additional options
     * @return string Response text
     * @throws \Exception On API error
     */
    public function complete(string $prompt, array $options = []): string;

    /**
     * Get embeddings for text
     *
     * @param string $text Text to embed
     * @return array Vector embeddings
     * @throws \Exception On API error
     */
    public function embed(string $text): array;

    /**
     * Get available models
     *
     * @return array
     */
    public function getModels(): array;

    /**
     * Get usage statistics
     *
     * @return array
     */
    public function getUsage(): array;
}
