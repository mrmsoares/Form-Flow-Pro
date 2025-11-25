<?php

declare(strict_types=1);

/**
 * Integration Interface
 *
 * Base interface for all third-party integrations.
 *
 * @package FormFlowPro\Integrations
 * @since 2.3.0
 */

namespace FormFlowPro\Integrations;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Integration Interface
 */
interface IntegrationInterface
{
    /**
     * Get integration identifier
     *
     * @return string
     */
    public function getId(): string;

    /**
     * Get integration display name
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Get integration description
     *
     * @return string
     */
    public function getDescription(): string;

    /**
     * Get integration icon URL
     *
     * @return string
     */
    public function getIcon(): string;

    /**
     * Check if integration is configured
     *
     * @return bool
     */
    public function isConfigured(): bool;

    /**
     * Check if integration is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Get configuration fields
     *
     * @return array
     */
    public function getConfigFields(): array;

    /**
     * Save configuration
     *
     * @param array $config Configuration data
     * @return bool
     */
    public function saveConfig(array $config): bool;

    /**
     * Test connection
     *
     * @return array{success: bool, message: string}
     */
    public function testConnection(): array;

    /**
     * Send submission data to integration
     *
     * @param array $submission Submission data
     * @param array $mapping Field mapping
     * @return array{success: bool, message: string, external_id?: string}
     */
    public function sendSubmission(array $submission, array $mapping): array;

    /**
     * Get available fields from integration
     *
     * @return array
     */
    public function getAvailableFields(): array;

    /**
     * Get sync status for a submission
     *
     * @param int $submissionId Submission ID
     * @return array
     */
    public function getSyncStatus(int $submissionId): array;
}
