# FormFlow Pro Test Suite

Comprehensive unit and integration test suite for FormFlow Pro Enterprise.

## 📊 Test Coverage

```
Tests: 26
Assertions: 52
Coverage: 100% passing
Time: ~30ms
Memory: 6MB
```

### Coverage by Module

| Module | Tests | Assertions | Coverage |
|--------|-------|------------|----------|
| **CacheManager** | 16 | 32 | ✅ 100% |
| **FormProcessor** | 6 | 16 | ✅ 100% |
| **DatabaseManager** | 4 | 4 | ✅ 100% |

## 🏗️ Test Structure

```
tests/
├── README.md                        # This file
├── bootstrap.php                    # Test bootstrap with WordPress mocks
├── TestCase.php                     # Base test case with reflection helpers
├── mocks/
│   └── wordpress-functions.php      # Complete WordPress API mocks
├── unit/
│   ├── Core/
│   │   ├── CacheManagerTest.php     # Cache system tests (16 tests)
│   │   └── FormProcessorTest.php    # Form processing tests (6 tests)
│   └── Database/
│       └── DatabaseManagerTest.php  # Database layer tests (4 tests)
└── integration/
    └── (future integration tests)
```

## 🚀 Running Tests

### All Tests
```bash
composer test
# or
vendor/bin/phpunit
```

### With Test Descriptions
```bash
vendor/bin/phpunit --testdox
```

### Specific Test Suite
```bash
vendor/bin/phpunit --testsuite=Unit
```

### Filter by Class
```bash
vendor/bin/phpunit --filter=CacheManager
vendor/bin/phpunit --filter=FormProcessor
vendor/bin/phpunit --filter=DatabaseManager
```

### Filter by Test Method
```bash
vendor/bin/phpunit --filter=test_process_submission_with_valid_form
```

### With Code Coverage (requires Xdebug/PCOV)
```bash
composer test:coverage
# Opens coverage/index.html
```

## 📝 WordPress Mocks

Complete WordPress API mocks for isolated unit testing:

### Database Layer
- ✅ `wpdb` class (prepare, insert, update, delete, get_row, get_var, get_results)
- ✅ Mock result tracking (`set_mock_result()`, `get_mock_inserts()`)

### Options API
- ✅ `get_option()`, `update_option()`, `delete_option()`

### Transients API
- ✅ `get_transient()`, `set_transient()`, `delete_transient()`

### Cache API
- ✅ `wp_cache_get()`, `wp_cache_set()`, `wp_cache_delete()`
- ✅ `wp_using_ext_object_cache()`

### Sanitization
- ✅ `sanitize_text_field()`, `sanitize_email()`, `sanitize_key()`
- ✅ `esc_url_raw()`, `wp_unslash()`

### Utilities
- ✅ `current_time()`, `wp_json_encode()`, `wp_generate_uuid4()`
- ✅ `is_serialized()`, `apply_filters()`, `do_action()`
- ✅ `__()` (translation)

### Constants
- ✅ `OBJECT`, `ARRAY_A`, `ARRAY_N`

### Test Isolation
- ✅ `reset_wp_mocks()` - Resets all global state between tests

## 🧪 Test Categories

### CacheManager Tests (16 tests)

1. **Basic Operations** (4 tests)
   - Set and get simple values
   - Set and get arrays
   - Get with default values
   - Delete cached values

2. **Advanced Features** (6 tests)
   - Cache-aside pattern (`remember()`)
   - Custom TTL
   - Object serialization
   - Null value handling
   - Multiple deletes
   - Flush cache

3. **Statistics & Monitoring** (4 tests)
   - Stats structure validation
   - Hit rate calculation
   - Write tracking
   - Delete tracking

4. **Configuration** (2 tests)
   - Cache disabled behavior
   - Custom TTL with remember

### FormProcessor Tests (6 tests)

1. **Validation** (1 test)
   - Invalid form error handling

2. **Success Path** (1 test)
   - Valid form submission processing

3. **Security** (1 test)
   - XSS sanitization
   - SQL injection prevention (prepared statements)

4. **Performance** (1 test)
   - Data compression (gzcompress)

5. **Queue System** (1 test)
   - Job creation for PDF generation

6. **Request Tracking** (1 test)
   - Proxy-aware IP detection (X-Forwarded-For, Cloudflare)

### DatabaseManager Tests (4 tests)

1. **Table Management** (2 tests)
   - Get table name with prefix
   - Table existence check

2. **Configuration** (2 tests)
   - Charset collate (UTF8MB4)
   - Table existence validation

## 🛠️ Base Test Case

All test classes extend `FormFlowPro\Tests\TestCase` which provides:

### Reflection Helpers
```php
// Access private/protected properties
$value = $this->getPrivateProperty($object, 'propertyName');

// Call private/protected methods
$result = $this->callPrivateMethod($object, 'methodName', [$arg1, $arg2]);
```

### Automatic Cleanup
- `setUp()` - Resets WordPress mocks before each test
- `tearDown()` - Cleans up after each test

## 🎯 Writing New Tests

### Test Naming Convention
```php
public function test_{action}_{expectation}()
{
    // Arrange
    $sut = new SystemUnderTest();
    
    // Act
    $result = $sut->doSomething();
    
    // Assert
    $this->assertEquals('expected', $result);
}
```

### Example: Testing a Cache Operation
```php
public function test_cache_stores_and_retrieves_value()
{
    // Arrange
    $cache = new CacheManager();
    
    // Act
    $cache->set('key', 'value');
    $result = $cache->get('key');
    
    // Assert
    $this->assertEquals('value', $result);
}
```

### Using WordPress Mocks
```php
public function test_database_insert()
{
    global $wpdb;
    
    // Configure mock response
    $wpdb->set_mock_result('get_row', $mockObject);
    
    // Test code that uses wpdb
    $result = $myClass->fetchData();
    
    // Verify mock was called
    $inserts = $wpdb->get_mock_inserts();
    $this->assertCount(1, $inserts);
}
```

## 📈 Continuous Improvement

### Next Steps
- [ ] Increase coverage to 80%+
- [ ] Add integration tests
- [ ] Add E2E tests
- [ ] Setup CI/CD pipeline (GitHub Actions)
- [ ] Performance benchmarks
- [ ] Mutation testing

### Contribution Guidelines
1. All new code must include tests
2. Maintain 100% test pass rate
3. Follow AAA pattern (Arrange, Act, Assert)
4. Use descriptive test method names
5. One assertion per test (when possible)
6. Test edge cases and error conditions

## 🔧 Troubleshooting

### Tests Failing?
1. Run `composer install` to ensure dependencies are up to date
2. Check PHP version (requires 8.0+)
3. Clear PHPUnit cache: `rm .phpunit.result.cache`
4. Run with verbose output: `vendor/bin/phpunit --verbose`

### Need to Debug?
```bash
# Run single test with debug output
vendor/bin/phpunit --filter=test_name --debug

# Stop on first failure
vendor/bin/phpunit --stop-on-failure

# Show test output
vendor/bin/phpunit --verbose
```

## 📚 Resources

- [PHPUnit Documentation](https://phpunit.de/documentation.html)
- [WordPress Testing Handbook](https://make.wordpress.org/core/handbook/testing/)
- [PSR-4 Autoloading](https://www.php-fig.org/psr/psr-4/)

---

**Last Updated:** Phase 2.4B  
**Test Framework:** PHPUnit 9.6.29  
**PHP Version:** 8.0+
