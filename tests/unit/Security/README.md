# Security Module Tests

This directory contains comprehensive PHPUnit tests for all Security modules in Form-Flow-Pro.

## Test Files Created

### 1. SecurityManagerTest.php (28 tests)
Tests for the main security manager orchestrating all security features.

**Key Areas Covered:**
- Singleton instance creation
- Component initialization (2FA, GDPR, Audit, Access Control)
- Installation and default options
- Permission checks
- REST API endpoints (overview, 2FA status, sessions, IP rules, audit logs, GDPR requests, settings)
- Security headers (CSP, general security headers)
- Admin menu and asset enqueuing

### 2. TwoFactorAuthTest.php (48 tests)
Tests for the two-factor authentication system.

**Key Areas Covered:**
- TOTP generation and verification
- Secret key generation (base32 encoding)
- QR code URL generation
- Backup codes (generation, verification, usage tracking)
- Email 2FA codes
- Device management (remember device, device revocation)
- User 2FA settings (enable, disable, check status)
- Session management for 2FA verification
- Rate limiting and brute force protection
- Security key support (WebAuthn)
- Statistics and cleanup

### 3. AccessControlTest.php (39 tests)
Tests for access control, IP filtering, and session management.

**Key Areas Covered:**
- IP rule management (add, remove, whitelist, blacklist, CIDR)
- IP blocking (temporary blocks, unblocking)
- Geo-blocking rules (country-based restrictions)
- Session management (create, validate, terminate)
- Concurrent session limits
- Login attempt tracking
- Statistics and reporting
- Cleanup operations (expired sessions, old attempts, blocked IPs)
- Filter operations (by IP, type, status)

### 4. GDPRComplianceTest.php (38 tests)
Tests for GDPR compliance and data protection.

**Key Areas Covered:**
- GDPR requests (export, erasure)
- Request verification and processing
- Consent management (record, withdraw, history)
- Current consents tracking
- Processing activities registration
- Data inventory management
- WordPress privacy hooks integration
- Statistics and reporting
- Duplicate request prevention
- Request expiration handling

### 5. AuditLoggerTest.php (41 tests)
Tests for comprehensive audit logging system.

**Key Areas Covered:**
- Event logging (info, warning, error, critical)
- Buffer management and flushing
- Query and filtering (by event type, category, severity, user)
- Export functionality (JSON, CSV)
- Statistics and analytics
- Specific event logging (login, logout, password reset, profile update)
- 2FA event logging
- GDPR event logging
- Settings change logging
- Form and submission logging
- API request logging
- Log cleanup

## Total Test Coverage

- **Total Test Files:** 5
- **Total Test Methods:** 194
- **Namespace:** FormFlowPro\Tests\Unit\Security
- **Base Class:** FormFlowPro\Tests\TestCase

## Testing Patterns Used

All tests follow WordPress and PHPUnit best practices:

1. **setUp/tearDown:** Proper initialization and cleanup
2. **Mocking:** WordPress functions mocked via TestCase
3. **Database:** Uses in-memory WordPress database simulation
4. **Isolation:** Each test is independent
5. **Assertions:** Comprehensive assertions for all scenarios
6. **Edge Cases:** Invalid inputs, boundary conditions, error states
7. **Integration Points:** AJAX handlers and WordPress hooks (marked for integration testing)

## Running the Tests

```bash
# Run all Security tests
vendor/bin/phpunit tests/unit/Security/

# Run specific test file
vendor/bin/phpunit tests/unit/Security/SecurityManagerTest.php

# Run specific test method
vendor/bin/phpunit --filter test_get_instance_returns_singleton tests/unit/Security/SecurityManagerTest.php

# Run with coverage
vendor/bin/phpunit --coverage-html coverage/ tests/unit/Security/
```

## Test Structure

Each test file includes:
- Singleton pattern testing
- CRUD operations
- Data validation
- Error handling
- Edge case coverage
- Statistics and reporting
- Cleanup operations
- Integration point placeholders

## Notes

- All tests use mocked WordPress functions from `tests/mocks/wordpress-functions.php`
- Database operations use the test database infrastructure
- AJAX and WordPress hook tests are marked as mocks for integration test suite
- All files pass PHP syntax validation
- Tests follow the same patterns as existing Form-Flow-Pro tests
