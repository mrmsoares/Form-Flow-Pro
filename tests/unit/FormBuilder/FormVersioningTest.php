<?php
/**
 * Tests for FormVersioning class.
 *
 * @package FormFlowPro\Tests\Unit\FormBuilder
 */

namespace FormFlowPro\Tests\Unit\FormBuilder;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\FormVersioning;
use FormFlowPro\FormBuilder\FormVersion;

class FormVersioningTest extends TestCase
{
    private ?FormVersioning $versioning = null;
    private bool $databaseAvailable = false;

    protected function setUp(): void
    {
        parent::setUp();

        // Try to get instance and check if database is available
        try {
            $this->versioning = FormVersioning::getInstance();

            // Check if tables are initialized by trying to access a property
            $reflection = new \ReflectionClass($this->versioning);
            $property = $reflection->getProperty('versions_table');
            $property->setAccessible(true);

            if ($property->isInitialized($this->versioning)) {
                $this->databaseAvailable = true;
            }
        } catch (\Throwable $e) {
            $this->databaseAvailable = false;
        }
    }

    /**
     * Skip test if database is not available
     */
    private function requireDatabase(): void
    {
        if (!$this->databaseAvailable) {
            $this->markTestSkipped('Database not available for this test');
        }
    }

    // ==================== Singleton Tests ====================

    public function testSingletonInstance(): void
    {
        $instance1 = FormVersioning::getInstance();
        $instance2 = FormVersioning::getInstance();

        $this->assertSame($instance1, $instance2);
    }

    // ==================== FormVersion Model Tests ====================

    public function testFormVersionConstruction(): void
    {
        $data = [
            'id' => 1,
            'form_id' => 123,
            'version_number' => 5,
            'version_label' => 'v5',
            'form_data' => ['fields' => []],
            'settings' => ['theme' => 'dark'],
            'is_published' => true,
            'branch_name' => 'main',
        ];

        $version = new FormVersion($data);

        $this->assertEquals(1, $version->id);
        $this->assertEquals(123, $version->form_id);
        $this->assertEquals(5, $version->version_number);
        $this->assertEquals('v5', $version->version_label);
        $this->assertTrue($version->is_published);
        $this->assertEquals('main', $version->branch_name);
    }

    public function testFormVersionToArray(): void
    {
        $version = new FormVersion([
            'id' => 1,
            'form_id' => 100,
            'version_number' => 1,
            'version_label' => 'Initial',
        ]);

        $array = $version->toArray();

        $this->assertIsArray($array);
        $this->assertEquals(1, $array['id']);
        $this->assertEquals(100, $array['form_id']);
        $this->assertEquals(1, $array['version_number']);
    }

    public function testFormVersionToArrayDefaultValues(): void
    {
        $version = new FormVersion([]);

        $array = $version->toArray();

        $this->assertEquals(0, $array['id']);
        $this->assertEquals(0, $array['form_id']);
        $this->assertEquals(1, $array['version_number']);
        $this->assertEquals('', $array['version_label']);
        $this->assertEquals([], $array['form_data']);
        $this->assertEquals('main', $array['branch_name']);
        $this->assertFalse($array['is_published']);
    }

    // ==================== Version Creation Tests ====================

    public function testCreateVersion(): void
    {
        $this->requireDatabase();

        $formData = [
            'fields' => [
                ['id' => 'field_1', 'type' => 'text', 'label' => 'Name'],
            ],
            'settings' => ['submitLabel' => 'Send'],
        ];

        $version = $this->versioning->createVersion(1, $formData);

        $this->assertInstanceOf(FormVersion::class, $version);
        $this->assertEquals(1, $version->form_id);
        $this->assertGreaterThanOrEqual(1, $version->version_number);
        $this->assertNotEmpty($version->checksum);
    }

    public function testCreateVersionWithOptions(): void
    {
        $this->requireDatabase();

        $formData = ['fields' => [], 'settings' => []];

        $version = $this->versioning->createVersion(2, $formData, [
            'label' => 'Release 1.0',
            'summary' => 'Initial release',
            'branch' => 'main',
            'publish' => true,
        ]);

        $this->assertInstanceOf(FormVersion::class, $version);
        $this->assertEquals('Release 1.0', $version->version_label);
        $this->assertEquals('Initial release', $version->change_summary);
    }

    public function testCreateVersionIncrementsVersionNumber(): void
    {
        $this->requireDatabase();

        $formData = ['fields' => [], 'settings' => []];

        $version1 = $this->versioning->createVersion(3, $formData);
        $version2 = $this->versioning->createVersion(3, $formData);

        $this->assertEquals($version1->version_number + 1, $version2->version_number);
    }

    public function testCreateVersionGeneratesChecksum(): void
    {
        $this->requireDatabase();

        $formData = ['fields' => [['id' => 'f1']], 'settings' => []];

        $version = $this->versioning->createVersion(4, $formData);

        $this->assertNotEmpty($version->checksum);
        $this->assertEquals(64, strlen($version->checksum)); // SHA256
    }

    // ==================== Version Retrieval Tests ====================

    public function testGetVersionById(): void
    {
        $this->requireDatabase();

        $created = $this->versioning->createVersion(5, ['fields' => []]);
        $retrieved = $this->versioning->getVersionById($created->id);

        $this->assertInstanceOf(FormVersion::class, $retrieved);
        $this->assertEquals($created->id, $retrieved->id);
    }

    public function testGetVersionByIdReturnsNullForInvalid(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->getVersionById(999999);
        $this->assertNull($version);
    }

    public function testGetVersion(): void
    {
        $this->requireDatabase();

        $formData = ['fields' => []];
        $created = $this->versioning->createVersion(6, $formData);

        $retrieved = $this->versioning->getVersion(6, $created->version_number, 'main');

        $this->assertInstanceOf(FormVersion::class, $retrieved);
        $this->assertEquals($created->id, $retrieved->id);
    }

    public function testGetLatestVersion(): void
    {
        $this->requireDatabase();

        $this->versioning->createVersion(7, ['fields' => ['old' => true]]);
        $latest = $this->versioning->createVersion(7, ['fields' => ['new' => true]]);

        $retrieved = $this->versioning->getLatestVersion(7);

        $this->assertInstanceOf(FormVersion::class, $retrieved);
        $this->assertEquals($latest->id, $retrieved->id);
    }

    public function testGetLatestVersionReturnsNullForNoVersions(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->getLatestVersion(999999);
        $this->assertNull($version);
    }

    public function testGetPublishedVersion(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(8, ['fields' => []], ['publish' => true]);

        $published = $this->versioning->getPublishedVersion(8);

        $this->assertInstanceOf(FormVersion::class, $published);
        $this->assertTrue($published->is_published);
    }

    public function testGetVersionHistory(): void
    {
        $this->requireDatabase();

        $this->versioning->createVersion(9, ['fields' => []]);
        $this->versioning->createVersion(9, ['fields' => []]);
        $this->versioning->createVersion(9, ['fields' => []]);

        $history = $this->versioning->getVersionHistory(9);

        $this->assertIsArray($history);
        $this->assertGreaterThanOrEqual(3, count($history));

        // Should be in descending order
        $this->assertGreaterThanOrEqual(
            $history[1]->version_number,
            $history[0]->version_number
        );
    }

    public function testGetVersionHistoryWithLimit(): void
    {
        $this->requireDatabase();

        for ($i = 0; $i < 5; $i++) {
            $this->versioning->createVersion(10, ['fields' => []]);
        }

        $history = $this->versioning->getVersionHistory(10, ['limit' => 2]);

        $this->assertCount(2, $history);
    }

    public function testGetVersionCount(): void
    {
        $this->requireDatabase();

        $formId = 11;
        $this->versioning->createVersion($formId, ['fields' => []]);
        $this->versioning->createVersion($formId, ['fields' => []]);

        $count = $this->versioning->getVersionCount($formId);

        $this->assertGreaterThanOrEqual(2, $count);
    }

    // ==================== Rollback Tests ====================

    public function testRollback(): void
    {
        $this->requireDatabase();

        $originalData = ['fields' => [['id' => 'original']], 'settings' => []];
        $modifiedData = ['fields' => [['id' => 'modified']], 'settings' => []];

        $original = $this->versioning->createVersion(12, $originalData);
        $modified = $this->versioning->createVersion(12, $modifiedData);

        $rollback = $this->versioning->rollback(12, $original->id);

        $this->assertInstanceOf(FormVersion::class, $rollback);
        $this->assertGreaterThan($modified->version_number, $rollback->version_number);
        $this->assertEquals($originalData, $rollback->form_data);
    }

    public function testRollbackReturnsNullForInvalidVersion(): void
    {
        $this->requireDatabase();

        $result = $this->versioning->rollback(999, 999999);
        $this->assertNull($result);
    }

    public function testRollbackReturnsNullForWrongForm(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(13, ['fields' => []]);

        // Try to rollback with wrong form_id
        $result = $this->versioning->rollback(999, $version->id);
        $this->assertNull($result);
    }

    // ==================== Publish Tests ====================

    public function testPublish(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(14, ['fields' => []]);

        $result = $this->versioning->publish($version->id);

        $this->assertTrue($result);

        $updated = $this->versioning->getVersionById($version->id);
        $this->assertTrue($updated->is_published);
    }

    public function testPublishUnpublishesPreviousVersion(): void
    {
        $this->requireDatabase();

        $version1 = $this->versioning->createVersion(15, ['fields' => []], ['publish' => true]);
        $version2 = $this->versioning->createVersion(15, ['fields' => []]);

        $this->versioning->publish($version2->id);

        $updated1 = $this->versioning->getVersionById($version1->id);
        $updated2 = $this->versioning->getVersionById($version2->id);

        $this->assertFalse($updated1->is_published);
        $this->assertTrue($updated2->is_published);
    }

    public function testPublishReturnsfalseForInvalidVersion(): void
    {
        $this->requireDatabase();

        $result = $this->versioning->publish(999999);
        $this->assertFalse($result);
    }

    // ==================== Branching Tests ====================

    public function testCreateBranch(): void
    {
        $this->requireDatabase();

        $mainVersion = $this->versioning->createVersion(16, ['fields' => []]);

        $branchVersion = $this->versioning->createBranch($mainVersion->id, 'feature-branch');

        $this->assertInstanceOf(FormVersion::class, $branchVersion);
        $this->assertEquals('feature-branch', $branchVersion->branch_name);
        $this->assertEquals(1, $branchVersion->version_number);
    }

    public function testCreateBranchReturnsNullForExistingBranch(): void
    {
        $this->requireDatabase();

        $mainVersion = $this->versioning->createVersion(17, ['fields' => []]);
        $this->versioning->createBranch($mainVersion->id, 'existing-branch');

        // Try to create same branch again
        $result = $this->versioning->createBranch($mainVersion->id, 'existing-branch');
        $this->assertNull($result);
    }

    public function testGetBranches(): void
    {
        $this->requireDatabase();

        $formId = 18;
        $version = $this->versioning->createVersion($formId, ['fields' => []], ['branch' => 'main']);
        $this->versioning->createBranch($version->id, 'develop');
        $this->versioning->createBranch($version->id, 'feature-x');

        $branches = $this->versioning->getBranches($formId);

        $this->assertIsArray($branches);
        $this->assertContains('main', $branches);
        $this->assertContains('develop', $branches);
        $this->assertContains('feature-x', $branches);
    }

    // ==================== Merge Tests ====================

    public function testMergeBranch(): void
    {
        $this->requireDatabase();

        $formId = 19;
        $main = $this->versioning->createVersion($formId, ['fields' => [['id' => 'main_field']]]);
        $branch = $this->versioning->createBranch($main->id, 'feature');

        // Make changes in feature branch
        $this->versioning->createVersion($formId, ['fields' => [['id' => 'feature_field']]], ['branch' => 'feature']);

        $merged = $this->versioning->mergeBranch($formId, 'feature', 'main');

        $this->assertInstanceOf(FormVersion::class, $merged);
        $this->assertEquals('main', $merged->branch_name);
    }

    public function testMergeBranchReturnsNullForInvalidSource(): void
    {
        $this->requireDatabase();

        $result = $this->versioning->mergeBranch(999, 'nonexistent', 'main');
        $this->assertNull($result);
    }

    // ==================== Comparison Tests ====================

    public function testCompareVersions(): void
    {
        $this->requireDatabase();

        $formId = 20;
        $v1 = $this->versioning->createVersion($formId, [
            'fields' => [['id' => 'f1', 'label' => 'Original']],
            'settings' => ['theme' => 'light'],
        ]);

        $v2 = $this->versioning->createVersion($formId, [
            'fields' => [['id' => 'f1', 'label' => 'Modified']],
            'settings' => ['theme' => 'dark'],
        ]);

        $comparison = $this->versioning->compareVersions($v1->id, $v2->id);

        $this->assertIsArray($comparison);
        $this->assertArrayHasKey('version_a', $comparison);
        $this->assertArrayHasKey('version_b', $comparison);
        $this->assertArrayHasKey('changes', $comparison);
        $this->assertArrayHasKey('summary', $comparison);
        $this->assertArrayHasKey('statistics', $comparison);
    }

    public function testCompareVersionsReturnsErrorForInvalid(): void
    {
        $this->requireDatabase();

        $comparison = $this->versioning->compareVersions(999999, 888888);

        $this->assertArrayHasKey('error', $comparison);
    }

    public function testGetVersionChanges(): void
    {
        $this->requireDatabase();

        $formId = 21;
        $v1 = $this->versioning->createVersion($formId, ['fields' => []]);
        $v2 = $this->versioning->createVersion($formId, ['fields' => [['id' => 'new']]]);

        $changes = $this->versioning->getVersionChanges($v2->id);

        $this->assertIsArray($changes);
    }

    // ==================== Change Detection Tests ====================

    public function testDetectChangesAddedField(): void
    {
        $oldData = ['fields' => [], 'settings' => []];
        $newData = ['fields' => [['id' => 'new_field', 'label' => 'New']], 'settings' => []];

        $changes = $this->versioning->detectChanges($oldData, $newData);

        $this->assertIsArray($changes);

        $addChanges = array_filter($changes, fn($c) => $c['type'] === 'add');
        $this->assertNotEmpty($addChanges);
    }

    public function testDetectChangesDeletedField(): void
    {
        $oldData = ['fields' => [['id' => 'old_field', 'label' => 'Old']], 'settings' => []];
        $newData = ['fields' => [], 'settings' => []];

        $changes = $this->versioning->detectChanges($oldData, $newData);

        $deleteChanges = array_filter($changes, fn($c) => $c['type'] === 'delete');
        $this->assertNotEmpty($deleteChanges);
    }

    public function testDetectChangesModifiedField(): void
    {
        $oldData = ['fields' => [['id' => 'field_1', 'label' => 'Original']], 'settings' => []];
        $newData = ['fields' => [['id' => 'field_1', 'label' => 'Modified']], 'settings' => []];

        $changes = $this->versioning->detectChanges($oldData, $newData);

        $modifyChanges = array_filter($changes, fn($c) => $c['type'] === 'modify');
        $this->assertNotEmpty($modifyChanges);
    }

    public function testDetectChangesReorderedFields(): void
    {
        $oldData = [
            'fields' => [
                ['id' => 'a', 'label' => 'A'],
                ['id' => 'b', 'label' => 'B'],
            ],
            'settings' => [],
        ];
        $newData = [
            'fields' => [
                ['id' => 'b', 'label' => 'B'],
                ['id' => 'a', 'label' => 'A'],
            ],
            'settings' => [],
        ];

        $changes = $this->versioning->detectChanges($oldData, $newData);

        $reorderChanges = array_filter($changes, fn($c) => $c['type'] === 'reorder');
        $this->assertNotEmpty($reorderChanges);
    }

    public function testDetectChangesSettingsModified(): void
    {
        $oldData = ['fields' => [], 'settings' => ['theme' => 'light']];
        $newData = ['fields' => [], 'settings' => ['theme' => 'dark']];

        $changes = $this->versioning->detectChanges($oldData, $newData);

        $settingChanges = array_filter($changes, fn($c) => ($c['element_type'] ?? '') === 'setting');
        $this->assertNotEmpty($settingChanges);
    }

    // ==================== Delete Tests ====================

    public function testDeleteVersion(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(22, ['fields' => []]);

        $result = $this->versioning->deleteVersion($version->id);

        $this->assertTrue($result);
        $this->assertNull($this->versioning->getVersionById($version->id));
    }

    public function testDeleteVersionFailsForPublished(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(23, ['fields' => []], ['publish' => true]);

        $result = $this->versioning->deleteVersion($version->id);

        $this->assertFalse($result);
        $this->assertNotNull($this->versioning->getVersionById($version->id));
    }

    public function testDeleteVersionReturnsfalseForInvalid(): void
    {
        $this->requireDatabase();

        $result = $this->versioning->deleteVersion(999999);
        $this->assertFalse($result);
    }

    // ==================== Cleanup Tests ====================

    public function testCleanupVersions(): void
    {
        $this->requireDatabase();

        $formId = 24;

        // Create 10 versions
        for ($i = 0; $i < 10; $i++) {
            $this->versioning->createVersion($formId, ['fields' => ['v' => $i]]);
        }

        // Keep only 5
        $deleted = $this->versioning->cleanupVersions($formId, 5);

        $this->assertGreaterThanOrEqual(0, $deleted);

        $remaining = $this->versioning->getVersionCount($formId);
        $this->assertLessThanOrEqual(5, $remaining);
    }

    // ==================== Export Tests ====================

    public function testExportHistoryJSON(): void
    {
        $this->requireDatabase();

        $formId = 25;
        $this->versioning->createVersion($formId, ['fields' => []]);
        $this->versioning->createVersion($formId, ['fields' => []]);

        $json = $this->versioning->exportHistory($formId, 'json');

        $this->assertIsString($json);

        $decoded = json_decode($json, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('form_id', $decoded);
        $this->assertArrayHasKey('exported_at', $decoded);
        $this->assertArrayHasKey('version_count', $decoded);
        $this->assertArrayHasKey('versions', $decoded);
    }

    public function testExportHistorySerialized(): void
    {
        $this->requireDatabase();

        $formId = 26;
        $this->versioning->createVersion($formId, ['fields' => []]);

        $serialized = $this->versioning->exportHistory($formId, 'php');

        $this->assertIsString($serialized);

        $decoded = unserialize($serialized);
        $this->assertIsArray($decoded);
    }

    // ==================== REST API Tests ====================

    public function testRestGetVersionsReturnsResponse(): void
    {
        $this->requireDatabase();

        $formId = 27;
        $this->versioning->createVersion($formId, ['fields' => []]);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/forms/' . $formId . '/versions');
        $request->set_param('form_id', $formId);

        $response = $this->versioning->restGetVersions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('versions', $data);
        $this->assertArrayHasKey('total', $data);
        $this->assertArrayHasKey('pages', $data);
    }

    public function testRestCreateVersionReturnsResponse(): void
    {
        $this->requireDatabase();

        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/forms/28/versions');
        $request->set_param('form_id', 28);
        $request->set_body(json_encode([
            'form_data' => ['fields' => []],
            'options' => ['label' => 'REST Created'],
        ]));

        $response = $this->versioning->restCreateVersion($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $this->assertEquals(201, $response->get_status());
    }

    public function testRestGetVersionReturnsResponse(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(29, ['fields' => []]);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/versions/' . $version->id);
        $request->set_param('id', $version->id);

        $response = $this->versioning->restGetVersion($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertEquals($version->id, $data['id']);
        $this->assertArrayHasKey('changes', $data);
    }

    public function testRestGetVersionReturns404ForInvalid(): void
    {
        $this->requireDatabase();

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/versions/999999');
        $request->set_param('id', 999999);

        $response = $this->versioning->restGetVersion($request);

        $this->assertEquals(404, $response->get_status());
    }

    public function testRestDeleteVersion(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(30, ['fields' => []]);

        $request = new \WP_REST_Request('DELETE', '/form-flow-pro/v1/versions/' . $version->id);
        $request->set_param('id', $version->id);

        $response = $this->versioning->restDeleteVersion($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testRestPublishVersion(): void
    {
        $this->requireDatabase();

        $version = $this->versioning->createVersion(31, ['fields' => []]);

        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/versions/' . $version->id . '/publish');
        $request->set_param('id', $version->id);

        $response = $this->versioning->restPublishVersion($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    public function testRestRollback(): void
    {
        $this->requireDatabase();

        $formId = 32;
        $v1 = $this->versioning->createVersion($formId, ['fields' => []]);
        $v2 = $this->versioning->createVersion($formId, ['fields' => []]);

        $request = new \WP_REST_Request('POST', '/form-flow-pro/v1/versions/' . $v1->id . '/rollback');
        $request->set_param('id', $v1->id);

        $response = $this->versioning->restRollback($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
    }

    public function testRestCompareVersions(): void
    {
        $this->requireDatabase();

        $formId = 33;
        $v1 = $this->versioning->createVersion($formId, ['fields' => []]);
        $v2 = $this->versioning->createVersion($formId, ['fields' => [['id' => 'new']]]);

        $request = new \WP_REST_Request('GET', '/form-flow-pro/v1/versions/compare');
        $request->set_param('version_a', $v1->id);
        $request->set_param('version_b', $v2->id);

        $response = $this->versioning->restCompareVersions($request);

        $this->assertInstanceOf(\WP_REST_Response::class, $response);
        $data = $response->get_data();
        $this->assertArrayHasKey('changes', $data);
    }

    // ==================== Checksum Tests ====================

    public function testChecksumIsConsistent(): void
    {
        $formData = ['fields' => [['id' => 'test']], 'settings' => ['theme' => 'light']];

        $method = new \ReflectionMethod($this->versioning, 'calculateChecksum');
        $method->setAccessible(true);

        $checksum1 = $method->invoke($this->versioning, $formData);
        $checksum2 = $method->invoke($this->versioning, $formData);

        $this->assertEquals($checksum1, $checksum2);
    }

    public function testChecksumDiffersForDifferentData(): void
    {
        $method = new \ReflectionMethod($this->versioning, 'calculateChecksum');
        $method->setAccessible(true);

        $checksum1 = $method->invoke($this->versioning, ['fields' => [['id' => 'a']]]);
        $checksum2 = $method->invoke($this->versioning, ['fields' => [['id' => 'b']]]);

        $this->assertNotEquals($checksum1, $checksum2);
    }

    // ==================== Index By ID Tests ====================

    public function testIndexById(): void
    {
        $method = new \ReflectionMethod($this->versioning, 'indexById');
        $method->setAccessible(true);

        $items = [
            ['id' => 'a', 'value' => 1],
            ['id' => 'b', 'value' => 2],
            ['id' => 'c', 'value' => 3],
        ];

        $indexed = $method->invoke($this->versioning, $items);

        $this->assertArrayHasKey('a', $indexed);
        $this->assertArrayHasKey('b', $indexed);
        $this->assertArrayHasKey('c', $indexed);
        $this->assertEquals(1, $indexed['a']['value']);
    }

    // ==================== Compare Arrays Tests ====================

    public function testCompareArrays(): void
    {
        $method = new \ReflectionMethod($this->versioning, 'compareArrays');
        $method->setAccessible(true);

        $old = ['a' => 1, 'b' => 2, 'c' => 3];
        $new = ['a' => 1, 'b' => 5, 'd' => 4]; // b modified, c deleted, d added

        $changes = $method->invoke($this->versioning, $old, $new, 'test');

        // Should have add, delete, modify
        $types = array_column($changes, 'type');
        $this->assertContains('add', $types);
        $this->assertContains('delete', $types);
        $this->assertContains('modify', $types);
    }

    // ==================== Merge Form Data Tests ====================

    public function testMergeFormData(): void
    {
        $method = new \ReflectionMethod($this->versioning, 'mergeFormData');
        $method->setAccessible(true);

        $target = [
            'fields' => [['id' => 'a', 'label' => 'A']],
            'settings' => ['theme' => 'light'],
        ];
        $source = [
            'fields' => [['id' => 'b', 'label' => 'B']],
            'settings' => ['theme' => 'dark', 'layout' => 'wide'],
        ];

        $merged = $method->invoke($this->versioning, $target, $source);

        $this->assertCount(2, $merged['fields']);
        $this->assertEquals('dark', $merged['settings']['theme']);
        $this->assertEquals('wide', $merged['settings']['layout']);
    }
}
