<?php
/**
 * Base Integration Test Case for FormFlow Pro.
 *
 * Integration tests require a WordPress test environment.
 * See tests/integration/README.md for setup instructions.
 *
 * @package FormFlowPro
 * @subpackage Tests
 */

namespace FormFlowPro\Tests\Integration;

use PHPUnit\Framework\TestCase;

/**
 * Base class for integration tests.
 *
 * Integration tests interact with a real WordPress installation
 * and test the complete plugin functionality in context.
 */
abstract class IntegrationTestCase extends TestCase
{
    /**
     * Setup WordPress test environment.
     *
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        // Check if WordPress test environment is available
        if (!defined('ABSPATH')) {
            self::markTestSkipped(
                'WordPress test environment not configured. ' .
                'See tests/integration/README.md for setup instructions.'
            );
        }
    }

    /**
     * Setup before each test.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        // Reset WordPress state if available
        if (function_exists('\_delete_all_posts')) {
            \_delete_all_posts();
        }
    }

    /**
     * Teardown after each test.
     *
     * @return void
     */
    protected function tearDown(): void
    {
        // Clean up test data
        parent::tearDown();
    }

    /**
     * Create a test form.
     *
     * @param array $args Form arguments.
     * @return string Form ID.
     */
    protected function createTestForm(array $args = []): string
    {
        $defaults = [
            'name' => 'Test Form',
            'elementor_form_id' => 'test-form-' . uniqid(),
            'status' => 'active',
            'settings' => json_encode([]),
        ];

        $form_data = array_merge($defaults, $args);

        global $wpdb;
        $wpdb->insert(
            $wpdb->prefix . 'formflow_forms',
            $form_data
        );

        return $wpdb->insert_id;
    }

    /**
     * Assert that a form exists in database.
     *
     * @param string $form_id Form ID.
     * @return void
     */
    protected function assertFormExists(string $form_id): void
    {
        global $wpdb;
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %s",
                $form_id
            )
        );

        $this->assertNotNull($form, "Form {$form_id} should exist in database");
    }

    /**
     * Assert that a submission exists in database.
     *
     * @param string $submission_id Submission ID.
     * @return void
     */
    protected function assertSubmissionExists(string $submission_id): void
    {
        global $wpdb;
        $submission = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formflow_submissions WHERE id = %s",
                $submission_id
            )
        );

        $this->assertNotNull($submission, "Submission {$submission_id} should exist in database");
    }
}
