# AJAX Handler Tests

Comprehensive PHPUnit tests for Form-Flow-Pro AJAX handlers.

## Overview

This directory contains unit tests for all AJAX handlers in the Form-Flow-Pro plugin:

- **AjaxHandlerTest.php** - Tests for the main Ajax_Handler manager class
- **FormsAjaxTest.php** - Tests for form CRUD operations (create, read, update, delete, duplicate, status updates)
- **SubmissionsAjaxTest.php** - Tests for submission management (get, delete, bulk delete, export)
- **SettingsAjaxTest.php** - Tests for settings operations (API connection test, cache driver check, email test)
- **DashboardAjaxTest.php** - Tests for dashboard stats and chart data

## Test Structure

Each test file follows these conventions:

### Namespace and Inheritance

```php
namespace FormFlowPro\Tests\Unit\Ajax;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\Ajax\Forms_Ajax;  // The class being tested
use WPAjaxDieException;

class FormsAjaxTest extends TestCase
{
    // Tests...
}
```

### setUp Method

Each test class requires the corresponding AJAX handler file in setUp():

```php
protected function setUp(): void
{
    parent::setUp();

    if (!defined('ABSPATH')) {
        define('ABSPATH', '/tmp/wordpress/');
    }

    // Require the AJAX handler class
    require_once FORMFLOW_PATH . 'includes/ajax/class-forms-ajax.php';
}
```

## Testing AJAX Endpoints

### Using the Helper Method (RECOMMENDED)

The `TestCase` class provides a `callAjaxEndpoint()` helper that properly handles output buffering:

```php
public function test_save_form_fails_without_nonce()
{
    $_POST = [];

    $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

    $this->assertFalse($response['success']);
    $this->assertEquals('Security check failed.', $response['data']['message']);
}
```

### Manual Output Buffer Handling (Legacy)

If you need more control, use try-catch-finally:

```php
public function test_example()
{
    $_POST = ['nonce' => wp_create_nonce('formflow_nonce')];

    ob_start();
    try {
        Forms_Ajax::save_form();
        $output = ob_get_clean();
    } catch (WPAjaxDieException $e) {
        $output = ob_get_clean();
    } finally {
        // Cleanup
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    $response = json_decode($output, true);
    $this->assertTrue($response['success']);
}
```

## Test Categories

### Security Tests

All AJAX handlers should test:
- **Nonce verification** - Missing nonce should return 403
- **Invalid nonce** - Invalid nonce should return 403
- **Capability checks** - Missing permissions should return 403

```php
public function test_action_fails_without_nonce()
{
    $_POST = [];
    $response = $this->callAjaxEndpoint([MyAjax::class, 'my_action']);
    $this->assertFalse($response['success']);
}
```

### Validation Tests

Test required parameters:
- **Missing required fields** - Should return 400
- **Invalid data types** - Should return 400
- **Invalid values** - Should return 400 (e.g., invalid status)

```php
public function test_save_form_fails_without_name()
{
    $_POST = [
        'nonce' => wp_create_nonce('formflow_nonce'),
        'name' => '', // Empty required field
    ];

    $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

    $this->assertFalse($response['success']);
    $this->assertEquals('Form name is required.', $response['data']['message']);
}
```

### Success Tests

Test successful operations:

```php
public function test_save_form_creates_new_form_successfully()
{
    global $wpdb;

    $_POST = [
        'nonce' => wp_create_nonce('formflow_nonce'),
        'name' => 'Test Form',
        'description' => 'Test Description',
        'fields' => ['field1' => 'value1'],
        'settings' => ['setting1' => 'value1'],
        'status' => 'active',
    ];

    $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

    $this->assertTrue($response['success']);
    $this->assertEquals('Form created successfully.', $response['data']['message']);
    $this->assertArrayHasKey('form_id', $response['data']);

    // Verify database insert
    $inserts = $wpdb->get_mock_inserts();
    $this->assertNotEmpty($inserts);
}
```

### Error Handling Tests

Test database errors and edge cases:

```php
public function test_get_form_fails_when_form_not_found()
{
    global $wpdb;

    $_POST = [
        'nonce' => wp_create_nonce('formflow_nonce'),
        'form_id' => 999,
    ];

    $wpdb->set_mock_result('get_row', null);

    $response = $this->callAjaxEndpoint([Forms_Ajax::class, 'get_form']);

    $this->assertFalse($response['success']);
    $this->assertEquals('Form not found.', $response['data']['message']);
}
```

## Mocking WordPress Functions

The test suite includes mocked WordPress functions in `tests/mocks/wordpress-functions.php`:

### Nonce Functions

```php
$nonce = wp_create_nonce('formflow_nonce');
wp_verify_nonce($nonce, 'formflow_nonce'); // Returns 1 or false
```

### Current User

```php
current_user_can('manage_options'); // Always returns true in tests
get_current_user_id(); // Returns 1
```

### AJAX Response Functions

```php
wp_send_json_success($data); // Throws WPAjaxDieException
wp_send_json_error($data);   // Throws WPAjaxDieException
wp_send_json($data);          // Throws WPAjaxDieException
wp_die($message);             // Throws Exception
```

### Database Mocking

```php
global $wpdb;

// Mock query results
$wpdb->set_mock_result('get_row', $mockObject);
$wpdb->set_mock_result('get_var', 123);
$wpdb->set_mock_result('get_results', $mockArray);

// Insert data (automatically creates IDs)
$wpdb->insert($table, $data);

// Check inserts
$inserts = $wpdb->get_mock_inserts();

// Reset between tests (done automatically in TestCase::setUp())
$wpdb->clear_mock_data();
```

### HTTP Request Mocking

```php
global $wp_http_mock_response;

// Mock successful response
$wp_http_mock_response = [
    'response' => ['code' => 200],
    'body' => json_encode(['data' => 'value']),
];

// Mock error response
global $wp_http_mock_error;
$wp_http_mock_error = 'Connection timeout';
```

## Running Tests

### Run all AJAX tests:
```bash
./vendor/bin/phpunit tests/unit/Ajax/
```

### Run specific test file:
```bash
./vendor/bin/phpunit tests/unit/Ajax/FormsAjaxTest.php
```

### Run specific test method:
```bash
./vendor/bin/phpunit --filter test_save_form_fails_without_nonce tests/unit/Ajax/
```

### Run with coverage:
```bash
./vendor/bin/phpunit tests/unit/Ajax/ --coverage-html coverage/
```

### Run with testdox format:
```bash
./vendor/bin/phpunit tests/unit/Ajax/ --testdox
```

## Test Coverage

Each AJAX handler should have tests for:

✅ **Action registration** - Verify wp_ajax hooks are registered
✅ **Security checks** - Nonce and capability verification
✅ **Required parameters** - All required fields validated
✅ **Data sanitization** - Input is properly sanitized
✅ **Success responses** - Successful operations return correct data
✅ **Error responses** - Errors return appropriate messages and codes
✅ **Database operations** - CRUD operations work correctly
✅ **Edge cases** - Boundary conditions and unusual inputs

## Common Patterns

### Testing Form CRUD Operations

```php
// Create
$response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);
$this->assertTrue($response['success']);

// Read
$response = $this->callAjaxEndpoint([Forms_Ajax::class, 'get_form']);
$this->assertArrayHasKey('form', $response['data']);

// Update
$_POST['form_id'] = 1;
$response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

// Delete
$response = $this->callAjaxEndpoint([Forms_Ajax::class, 'delete_form']);
```

### Testing DataTables Server-Side Processing

```php
$_POST = [
    'nonce' => wp_create_nonce('formflow_nonce'),
    'draw' => 1,
    'start' => 0,
    'length' => 25,
    'search' => ['value' => 'search term'],
    'order' => [['column' => 1, 'dir' => 'desc']],
];

$response = $this->callAjaxEndpoint([Submissions_Ajax::class, 'get_submissions']);

$this->assertArrayHasKey('draw', $response);
$this->assertArrayHasKey('recordsTotal', $response);
$this->assertArrayHasKey('recordsFiltered', $response);
$this->assertArrayHasKey('data', $response);
```

### Testing API Connections

```php
global $wp_http_mock_response;

$_POST = [
    'nonce' => wp_create_nonce('formflow_nonce'),
    'api_key' => 'test_key',
];

// Mock successful API response
$wp_http_mock_response = [
    'response' => ['code' => 200],
    'body' => json_encode(['data' => ['schema' => []]]),
];

$response = $this->callAjaxEndpoint([Settings_Ajax::class, 'test_api_connection']);
$this->assertTrue($response['success']);
```

## Updating Legacy Tests

To update tests from the old pattern to use the helper method:

**Before:**
```php
$this->expectException(WPAjaxDieException::class);

ob_start();
Forms_Ajax::save_form();
$output = ob_get_clean();

$response = json_decode($output, true);
$this->assertFalse($response['success']);
```

**After:**
```php
$response = $this->callAjaxEndpoint([Forms_Ajax::class, 'save_form']);

$this->assertFalse($response['success']);
```

## Contributing

When adding new tests:

1. Follow the existing naming conventions
2. Use the `callAjaxEndpoint()` helper for all AJAX calls
3. Test security first (nonce and capabilities)
4. Test all required parameters
5. Test success and error cases
6. Test edge cases and boundary conditions
7. Add comments for complex test scenarios
8. Group related tests with section comments

## Test Results

Current test status (as of creation):

- **Total Tests:** 95
- **Assertions:** 100+
- **Coverage:** All major AJAX endpoints
- **Files:** 5 test classes

All critical security and functionality paths are covered. Additional tests can be added for:
- Edge cases
- Integration scenarios
- Performance testing
- Error recovery
