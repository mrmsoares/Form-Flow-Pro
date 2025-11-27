# FormBuilder Test Suite

This directory contains comprehensive PHPUnit tests for the FormBuilder modules.

## Test Files Created

1. **FormBuilderManagerTest.php** (58 tests)
   - Tests singleton pattern
   - Form submission handling
   - Template management
   - Import/export functionality
   - Form analytics
   - Field validation
   - File upload handling
   - Smart tags parsing
   - REST API endpoints

2. **FieldTypesTest.php** (44 tests)
   - Field type registry
   - Text field validation (min/max length, patterns)
   - Email field validation (format, domains)
   - Phone field validation
   - Number field validation (min/max, sanitization)
   - Select field rendering (single, multiple)
   - Checkbox field validation (min/max selections)
   - Radio field rendering
   - Date field validation (min/max dates)
   - File upload field rendering
   - Rating field validation
   - Slider field rendering
   - Address field rendering
   - Name field rendering (simple, full formats)
   - Textarea field validation
   - Required/optional field handling

3. **FormVersioningTest.php** (25 tests)
   - Version creation and retrieval
   - Version history management
   - Rollback functionality
   - Publishing versions
   - Branch creation and merging
   - Version comparison and change detection
   - Change detection for:
     - Added fields
     - Deleted fields
     - Modified fields
     - Reordered fields
   - Version cleanup
   - Export history

4. **ABTestingTest.php** (24 tests)
   - Test creation and management
   - Variant creation and assignment
   - Test lifecycle (start, pause, resume, complete)
   - Event tracking
   - Statistical significance calculation
   - Winner determination
   - Time series data
   - Variant application to forms
   - AJAX event tracking

5. **DragDropBuilderTest.php** (40 tests)
   - Form CRUD operations (create, read, update, delete)
   - Form duplication
   - Shortcode rendering
   - Multi-step form rendering
   - Conditional logic evaluation
   - Field organization by steps
   - Custom styling application
   - Form access control
   - Schedule checking
   - Submission limits
   - AJAX operations (save, duplicate, preview)

## Total Tests: 156

## Test Coverage

The test suite covers:
- ✓ All field types (text, email, select, checkbox, file upload, etc.)
- ✓ Form versioning and rollback
- ✓ A/B testing logic and statistical analysis
- ✓ Drag-drop builder functionality
- ✓ Multi-step forms
- ✓ Conditional logic
- ✓ Form rendering
- ✓ Data validation and sanitization
- ✓ REST API endpoints
- ✓ AJAX handlers

## Known Issues

### Singleton Initialization
The following classes use SingletonTrait but don't have constructors that call their init() methods:
- `ABTesting`
- `DragDropBuilder`
- `FormVersioning`
- `FieldTypesRegistry`

**Recommendation:** Add private constructors that call init():

```php
private function __construct()
{
    $this->init();
}
```

This pattern is already used in `SecurityManager` and other similar classes.

## Running the Tests

Run all FormBuilder tests:
```bash
vendor/bin/phpunit tests/unit/FormBuilder/
```

Run specific test file:
```bash
vendor/bin/phpunit tests/unit/FormBuilder/FieldTypesTest.php
```

Run specific test:
```bash
vendor/bin/phpunit --filter test_text_field_validates_min_length tests/unit/FormBuilder/FieldTypesTest.php
```

List all tests:
```bash
vendor/bin/phpunit --list-tests tests/unit/FormBuilder/
```

## Test Patterns Used

All tests follow the established patterns:
- Extend `FormFlowPro\Tests\TestCase`
- Use namespace `FormFlowPro\Tests\Unit\FormBuilder`
- Set up and tear down test fixtures properly
- Use global `$wpdb` mocking for database operations
- Use WordPress function mocks (set_nonce_verified, set_current_user_can, etc.)
- Test both success and failure scenarios
- Include comprehensive assertions

## Next Steps

1. Fix the singleton initialization issue in source files
2. Run full test suite to verify all tests pass
3. Add integration tests for complete workflows
4. Add performance tests for large forms
5. Add edge case tests for unusual form configurations
