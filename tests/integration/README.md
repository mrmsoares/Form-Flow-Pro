# Integration Tests

Integration tests for FormFlow Pro Enterprise that test the plugin with a real WordPress installation.

## ⚠️ Status: Setup Required

Integration tests require a WordPress test environment. This is **not yet configured** but the structure is in place.

## 🎯 Purpose

Integration tests verify:
- ✅ Database operations with real WordPress `wpdb`
- ✅ WordPress hooks and filters
- ✅ Complete plugin activation/deactivation
- ✅ Real-world data flow
- ✅ Multisite compatibility
- ✅ Plugin interactions

## 📋 Prerequisites

1. **WordPress Test Library**
2. **MySQL/MariaDB** test database
3. **WP-CLI** (recommended)

## 🚀 Setup Instructions

### 1. Install WordPress Test Suite

```bash
# Install WordPress test suite (one-time setup)
bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### 2. Configure Test Database

The script above will:
- Download WordPress
- Create test database (`wordpress_test`)
- Configure wp-tests-config.php

### 3. Update composer.json (if needed)

```json
{
    "require-dev": {
        "yoast/phpunit-polyfills": "^2.0"
    }
}
```

### 4. Run Integration Tests

```bash
# Run all integration tests
vendor/bin/phpunit --testsuite=Integration

# Run with WordPress installed
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --testsuite=Integration
```

## 📁 Structure

```
tests/integration/
├── README.md                        # This file
├── IntegrationTestCase.php          # Base test case
├── .gitkeep                         # Ensure directory exists
└── (future test files)
    ├── DatabaseTest.php             # Database operations
    ├── ActivationTest.php           # Plugin activation
    ├── FormProcessingTest.php       # Complete form workflow
    └── CacheIntegrationTest.php     # Cache with real WP
```

## ✍️ Writing Integration Tests

### Example: Testing Form Creation

```php
<?php
namespace FormFlowPro\Tests\Integration;

class FormCreationTest extends IntegrationTestCase
{
    public function test_form_can_be_created_in_database()
    {
        // Arrange
        $form_data = [
            'name' => 'Contact Form',
            'elementor_form_id' => 'contact-form-1',
            'status' => 'active',
        ];

        // Act
        $form_id = $this->createTestForm($form_data);

        // Assert
        $this->assertFormExists($form_id);

        global $wpdb;
        $form = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %s",
                $form_id
            )
        );

        $this->assertEquals('Contact Form', $form->name);
        $this->assertEquals('active', $form->status);
    }
}
```

### Example: Testing Complete Submission Flow

```php
public function test_complete_submission_workflow()
{
    // Create form
    $form_id = $this->createTestForm();

    // Create submission
    $processor = new \FormFlowPro\Core\FormProcessor();
    $result = $processor->process_submission($form_id, [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

    // Assert submission was created
    $this->assertTrue($result['success']);
    $this->assertSubmissionExists($result['submission_id']);

    // Assert jobs were queued
    global $wpdb;
    $jobs = $wpdb->get_results(
        "SELECT * FROM {$wpdb->prefix}formflow_queue WHERE submission_id = '{$result['submission_id']}'"
    );

    $this->assertNotEmpty($jobs);
}
```

## 🔧 Helper Methods

### IntegrationTestCase Provides:

```php
// Create test form
$form_id = $this->createTestForm([
    'name' => 'My Form',
    'status' => 'active',
]);

// Assert form exists
$this->assertFormExists($form_id);

// Assert submission exists
$this->assertSubmissionExists($submission_id);
```

## 📊 Running on CI/CD

Integration tests are **skipped** on CI/CD until WordPress test environment is configured.

To enable on GitHub Actions:

```yaml
- name: Setup WordPress Test Environment
  run: |
    bash bin/install-wp-tests.sh wordpress_test root '' localhost latest

- name: Run Integration Tests
  run: vendor/bin/phpunit --testsuite=Integration
```

## 🐛 Troubleshooting

### Tests Skipped?
Integration tests are automatically skipped if WordPress is not detected:
```
S - WordPress test environment not configured
```

This is expected. Unit tests will still run.

### Database Connection Error?
```bash
# Check MySQL is running
mysql -u root -p

# Recreate test database
mysql -u root -p -e "DROP DATABASE IF EXISTS wordpress_test"
bash bin/install-wp-tests.sh wordpress_test root '' localhost latest
```

### WordPress Not Found?
```bash
# Set WP_TESTS_DIR environment variable
export WP_TESTS_DIR=/tmp/wordpress-tests-lib

# Or use inline
WP_TESTS_DIR=/tmp/wordpress-tests-lib vendor/bin/phpunit --testsuite=Integration
```

## 🎯 Next Steps

1. [ ] Install WordPress test suite
2. [ ] Write database integration tests
3. [ ] Test plugin activation/deactivation
4. [ ] Test complete form submission workflow
5. [ ] Test cache integration
6. [ ] Test multisite compatibility

## 📚 Resources

- [WordPress Plugin Handbook - Testing](https://developer.wordpress.org/plugins/testing/)
- [WordPress Automated Testing](https://make.wordpress.org/core/handbook/testing/automated-testing/)
- [WP-CLI Testing Commands](https://developer.wordpress.org/cli/commands/scaffold/plugin-tests/)

---

**Status:** Structure ready, WordPress test suite installation pending
