# FormFlow Pro - Developer Guide

## Table of Contents

1. [Getting Started](#getting-started)
2. [Architecture Overview](#architecture-overview)
3. [Module Reference](#module-reference)
4. [Hooks and Filters](#hooks-and-filters)
5. [Testing](#testing)
6. [Coding Standards](#coding-standards)

---

## Getting Started

### Requirements

- PHP 8.1 or higher
- WordPress 6.0 or higher
- Composer for dependency management
- Node.js 16+ (for frontend assets)

### Installation for Development

```bash
# Clone the repository
git clone https://github.com/your-org/formflow-pro.git
cd formflow-pro

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Build assets
npm run build
```

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suite
vendor/bin/phpunit tests/unit/

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/

# Run code quality checks
composer phpstan
composer phpcs
```

---

## Architecture Overview

FormFlow Pro follows a modular architecture with clear separation of concerns:

```
includes/
├── Admin/              # Admin UI controllers
├── API/                # REST API endpoints
├── Core/               # Core functionality (activation, tables)
├── FormBuilder/        # Form builder, A/B testing, versioning
├── Integrations/       # Third-party integrations (Autentique, etc.)
├── Payments/           # Payment providers (Stripe, PayPal)
├── Reporting/          # Analytics and reporting
├── Security/           # Access control, audit logging
└── Traits/             # Shared traits (SingletonTrait)
```

### Key Design Patterns

#### Singleton Pattern
Most manager classes use `SingletonTrait` for single-instance management:

```php
use FormFlowPro\Traits\SingletonTrait;

class MyManager {
    use SingletonTrait;

    // Access via MyManager::getInstance()
}
```

#### Service Providers
Payment and integration providers implement interfaces for consistency:

```php
interface PaymentProviderInterface {
    public function createPayment(array $data): array;
    public function capturePayment(string $payment_id): array;
    public function refundPayment(string $payment_id, float $amount = null): array;
    // ...
}
```

---

## Module Reference

### FormBuilder Module

#### ABTesting

Create and manage A/B tests for forms:

```php
use FormFlowPro\FormBuilder\ABTesting;

$abTesting = ABTesting::getInstance();

// Create a test
$testId = $abTesting->createTest([
    'form_id' => 123,
    'name' => 'Button Color Test',
    'goal' => 'submission',
    'variants' => [
        ['name' => 'Control', 'changes' => [], 'weight' => 50, 'is_control' => true],
        ['name' => 'Red Button', 'changes' => ['button_color' => '#ff0000'], 'weight' => 50],
    ],
]);

// Start the test
$abTesting->startTest($testId);

// Assign variant to visitor
$variant = $abTesting->assignVariant($testId);

// Track conversion
$abTesting->trackConversion($testId, $variant->id);
```

#### FormVersioning

Manage form versions with drafts and publishing:

```php
use FormFlowPro\FormBuilder\FormVersioning;

$versioning = FormVersioning::getInstance();

// Create a new version
$versionId = $versioning->createVersion(123, [
    'fields' => [...],
    'settings' => [...],
]);

// Publish a version
$versioning->publishVersion($versionId);

// Get version history
$history = $versioning->getVersionHistory(123);

// Rollback to previous version
$versioning->rollbackVersion(123, $previousVersionId);
```

### Payments Module

#### StripeProvider

```php
use FormFlowPro\Payments\StripeProvider;

$stripe = new StripeProvider();

// Create a payment intent
$result = $stripe->createPayment([
    'amount' => 99.99,
    'currency' => 'usd',
    'customer_id' => 'cus_xxx',
    'description' => 'Form submission fee',
    'metadata' => ['form_id' => 123],
]);

if ($result['success']) {
    $clientSecret = $result['client_secret'];
    // Send to frontend for Stripe.js
}

// Create checkout session
$session = $stripe->createCheckoutSession([
    'mode' => 'payment',
    'success_url' => home_url('/success'),
    'cancel_url' => home_url('/cancel'),
    'line_items' => [
        ['name' => 'Premium Form', 'price' => 49.99, 'quantity' => 1],
    ],
]);

// Handle webhook
$result = $stripe->handleWebhook($payload, $signature);
```

#### PayPalProvider

```php
use FormFlowPro\Payments\PayPalProvider;

$paypal = new PayPalProvider();

// Create an order
$result = $paypal->createPayment([
    'amount' => 99.99,
    'currency' => 'USD',
    'description' => 'Form submission',
]);

// Capture payment
$capture = $paypal->capturePayment($orderId);
```

### Security Module

#### AccessControl

```php
use FormFlowPro\Security\AccessControl;

$access = AccessControl::getInstance();

// Check permission
if ($access->userCan('edit_forms', $userId)) {
    // Allow access
}

// Check form-specific permission
if ($access->userCanAccessForm($formId, 'edit')) {
    // Allow form editing
}

// Get user's role
$role = $access->getUserRole($userId);

// Check if user is admin
if ($access->isAdmin($userId)) {
    // Admin-only functionality
}
```

#### AuditLogger

```php
use FormFlowPro\Security\AuditLogger;

$logger = AuditLogger::getInstance();

// Log an event
$eventId = $logger->log(
    'form_submitted',
    AuditLogger::CATEGORY_DATA,
    AuditLogger::SEVERITY_INFO,
    [
        'user_id' => get_current_user_id(),
        'object_type' => 'form',
        'object_id' => 123,
        'description' => 'User submitted form',
    ]
);

// Shortcut methods
$logger->info('settings_changed', 'User updated settings');
$logger->warning('login_failed', 'Failed login attempt');
$logger->error('payment_failed', 'Payment processing error');

// Query logs
$logs = $logger->query([
    'category' => AuditLogger::CATEGORY_AUTH,
    'severity' => AuditLogger::SEVERITY_WARNING,
    'date_from' => date('Y-m-d', strtotime('-7 days')),
], 50, 0);

// Get statistics
$stats = $logger->getStatistics('day');
```

---

## Hooks and Filters

### Actions

```php
// Form lifecycle
do_action('ffp_form_created', $form_id, $form_data);
do_action('ffp_form_updated', $form_id, $form_data);
do_action('ffp_form_deleted', $form_id);
do_action('ffp_form_submitted', $form_id, $submission_id, $data);

// A/B Testing
do_action('ffp_ab_test_started', $test_id);
do_action('ffp_ab_test_paused', $test_id);
do_action('ffp_ab_test_completed', $test_id, $winner_variant_id);
do_action('ffp_ab_conversion_tracked', $test_id, $variant_id);

// Payments
do_action('ffp_payment_completed', $payment_id, $form_id, $amount);
do_action('ffp_payment_failed', $payment_id, $error);
do_action('ffp_subscription_created', $subscription_id, $customer_id);

// Security
do_action('ffp_user_login', $user_id);
do_action('ffp_user_logout', $user_id);
do_action('ffp_permission_denied', $user_id, $permission);
```

### Filters

```php
// Form data
$form_data = apply_filters('ffp_form_data', $form_data, $form_id);
$submission_data = apply_filters('ffp_submission_data', $data, $form_id);

// Validation
$is_valid = apply_filters('ffp_validate_submission', true, $data, $form_id);
$errors = apply_filters('ffp_validation_errors', $errors, $data);

// A/B Testing
$variant = apply_filters('ffp_assigned_variant', $variant, $test_id, $visitor_id);
$confidence = apply_filters('ffp_ab_confidence_level', 0.95, $test_id);

// Payments
$amount = apply_filters('ffp_payment_amount', $amount, $form_id);
$metadata = apply_filters('ffp_payment_metadata', $metadata, $form_id);

// Security
$permissions = apply_filters('ffp_user_permissions', $permissions, $user_id);
$can_access = apply_filters('ffp_can_access_form', true, $form_id, $user_id);
```

---

## Testing

### Test Structure

```
tests/
├── bootstrap.php           # Test bootstrap
├── TestCase.php           # Base test class
└── unit/
    ├── FormBuilder/
    │   ├── ABTestingTest.php
    │   └── FormVersioningTest.php
    ├── Payments/
    │   ├── PaymentManagerTest.php
    │   ├── StripeProviderTest.php
    │   └── PayPalProviderTest.php
    └── Security/
        ├── AccessControlTest.php
        ├── AuditLoggerTest.php
        └── SecurityManagerTest.php
```

### Writing Tests

```php
<?php
namespace FormFlowPro\Tests\Unit;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\FormBuilder\ABTesting;

class ABTestingTest extends TestCase
{
    private ABTesting $abTesting;

    protected function setUp(): void
    {
        parent::setUp();
        $this->abTesting = ABTesting::getInstance();
    }

    public function testCreateTest(): void
    {
        $testId = $this->abTesting->createTest([
            'form_id' => 1,
            'name' => 'Test',
            'goal' => 'submission',
        ]);

        $this->assertIsInt($testId);
        $this->assertGreaterThan(0, $testId);
    }

    // For database-dependent tests
    public function testWithDatabase(): void
    {
        $this->requireDatabase(); // Skips if DB not available

        // Test that requires database
    }
}
```

### Test Helpers

The base `TestCase` class provides useful helpers:

```php
// Access private methods
$result = $this->callPrivateMethod($object, 'methodName', [$arg1, $arg2]);

// Access private properties
$value = $this->getPrivateProperty($object, 'propertyName');
$this->setPrivateProperty($object, 'propertyName', $newValue);

// Skip if database not available
$this->requireDatabase();
```

---

## Coding Standards

### PHP Standards

- Follow PSR-12 coding style
- Use strict types: `declare(strict_types=1);`
- Use PHP 8.1+ features (typed properties, union types, named arguments)
- Always use explicit nullable types: `?string` not `string = null`

### Documentation

- Use PHPDoc blocks for all public methods
- Include `@param`, `@return`, `@throws` annotations
- Add `@since` version tags

```php
/**
 * Create a new A/B test for a form.
 *
 * @since 2.4.0
 *
 * @param array{
 *     form_id: int,
 *     name: string,
 *     goal: string,
 *     variants?: array
 * } $data Test configuration data.
 *
 * @return int|false Test ID on success, false on failure.
 *
 * @throws \InvalidArgumentException If required data is missing.
 */
public function createTest(array $data): int|false
```

### Error Handling

- Return arrays with `success` boolean for API methods
- Use WordPress error handling (`WP_Error`) for admin functions
- Log errors using `AuditLogger` for security events

```php
// API method pattern
public function processPayment(array $data): array
{
    if (!$this->validate($data)) {
        return [
            'success' => false,
            'error' => 'Invalid data',
            'code' => 'validation_error',
        ];
    }

    try {
        $result = $this->doProcess($data);
        return [
            'success' => true,
            'data' => $result,
        ];
    } catch (\Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'code' => 'processing_error',
        ];
    }
}
```

---

## Additional Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [PSR-12 Coding Standard](https://www.php-fig.org/psr/psr-12/)
- [PHPUnit Documentation](https://phpunit.de/documentation.html)
