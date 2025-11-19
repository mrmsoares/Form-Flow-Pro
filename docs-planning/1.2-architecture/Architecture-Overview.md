# FormFlow Pro Enterprise - Architecture Overview
**Version:** 2.0.0
**Date:** November 19, 2025
**Status:** Architecture Design
**Target Release:** V2.0.0

---

## 📋 Executive Summary

### Architecture Vision
FormFlow Pro Enterprise uses a **modern, modular, and scalable architecture** designed for enterprise-grade performance, maintainability, and extensibility.

### Core Architectural Principles
1. **Modularity** - Loosely coupled components with clear boundaries
2. **Scalability** - Horizontal scaling via queue workers and caching layers
3. **Performance** - Async processing, multi-layer caching, optimized queries
4. **Security** - Defense in depth, input validation, output sanitization
5. **Testability** - Dependency injection, interface-based design
6. **Extensibility** - 300+ hooks, REST API, webhook system

### Technology Stack
- **Backend:** PHP 8.0+ (optimized for 8.2)
- **Database:** MySQL 5.7+ / MariaDB 10.3+
- **Cache:** Redis (primary), WordPress Object Cache (fallback)
- **Queue:** WordPress Action Scheduler / Custom queue system
- **Frontend:** Vanilla JavaScript (ES6+), minimal framework overhead
- **Build Tools:** Webpack 5, PostCSS, Babel
- **Testing:** PHPUnit, Jest, Cypress

---

## 🏗️ High-Level System Architecture

```
┌─────────────────────────────────────────────────────────────────────┐
│                        PRESENTATION LAYER                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐   │
│  │  WordPress │  │    REST    │  │  Webhooks  │  │   Admin    │   │
│  │   Admin    │  │    API     │  │            │  │   Dashboard│   │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘   │
│                                                                      │
└──────────────────────────┬───────────────────────────────────────────┘
                           │
┌──────────────────────────┴───────────────────────────────────────────┐
│                        APPLICATION LAYER                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────────────────────────────────────────────┐       │
│  │                   CORE MODULES                           │       │
│  ├─────────────────────────────────────────────────────────┤       │
│  │                                                          │       │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐│       │
│  │  │   Form   │  │   PDF    │  │Autentique│  │  Email  ││       │
│  │  │Processor │  │Generator │  │    API   │  │ System  ││       │
│  │  └──────────┘  └──────────┘  └──────────┘  └─────────┘│       │
│  │                                                          │       │
│  │  ┌──────────┐  ┌──────────┐  ┌──────────┐  ┌─────────┐│       │
│  │  │  Queue   │  │Analytics │  │   UX     │  │Security ││       │
│  │  │  System  │  │ Engine   │  │  Manager │  │ Manager ││       │
│  │  └──────────┘  └──────────┘  └──────────┘  └─────────┘│       │
│  │                                                          │       │
│  └─────────────────────────────────────────────────────────┘       │
│                                                                      │
└──────────────────────────┬───────────────────────────────────────────┘
                           │
┌──────────────────────────┴───────────────────────────────────────────┐
│                        INFRASTRUCTURE LAYER                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐             │
│  │   Database   │  │    Cache     │  │    Queue     │             │
│  │   (MySQL)    │  │   (Redis)    │  │   Storage    │             │
│  └──────────────┘  └──────────────┘  └──────────────┘             │
│                                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐             │
│  │   File       │  │   Logging    │  │  Monitoring  │             │
│  │   Storage    │  │   System     │  │   Metrics    │             │
│  └──────────────┘  └──────────────┘  └──────────────┘             │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📁 Plugin Directory Structure

```
formflow-pro-enterprise/
│
├── formflow-pro.php                    # Main plugin file
├── uninstall.php                       # Uninstall cleanup
├── readme.txt                          # WordPress.org readme
├── composer.json                       # PHP dependencies
├── package.json                        # Node dependencies
├── webpack.config.js                   # Build configuration
│
├── assets/                             # Compiled frontend assets
│   ├── css/
│   │   ├── admin.min.css              # Admin styles (minified)
│   │   ├── critical.min.css           # Critical CSS (inlined)
│   │   └── themes/                     # Theme variations
│   ├── js/
│   │   ├── admin.min.js               # Admin scripts (bundled)
│   │   ├── vendor.min.js              # Third-party libraries
│   │   └── modules/                    # Lazy-loaded modules
│   ├── images/
│   │   ├── icons.svg                   # SVG sprite
│   │   └── logos/                      # Brand assets
│   └── fonts/
│       └── inter-var.woff2            # Variable font
│
├── includes/                           # Core PHP code
│   ├── class-formflow-plugin.php      # Main plugin class
│   ├── class-activator.php            # Activation handler
│   ├── class-deactivator.php          # Deactivation handler
│   ├── class-loader.php               # Hooks loader
│   │
│   ├── core/                           # Core functionality
│   │   ├── class-form-processor.php
│   │   ├── class-pdf-generator.php
│   │   ├── class-email-system.php
│   │   ├── class-queue-manager.php
│   │   ├── class-cache-manager.php
│   │   └── class-data-validator.php
│   │
│   ├── api/                            # API integrations
│   │   ├── class-autentique-api.php
│   │   ├── class-rest-api.php
│   │   ├── class-webhook-handler.php
│   │   └── class-rate-limiter.php
│   │
│   ├── admin/                          # Admin interface
│   │   ├── class-admin.php
│   │   ├── class-dashboard.php
│   │   ├── class-submissions-list.php
│   │   ├── class-settings.php
│   │   ├── class-analytics.php
│   │   └── views/                      # Admin templates
│   │       ├── dashboard.php
│   │       ├── submissions.php
│   │       ├── settings.php
│   │       └── analytics.php
│   │
│   ├── ux/                             # UX enhancements
│   │   ├── class-ux-manager.php
│   │   ├── class-navigation.php
│   │   ├── class-notifications.php
│   │   ├── class-accessibility.php
│   │   └── class-theme-manager.php
│   │
│   ├── security/                       # Security layer
│   │   ├── class-security-manager.php
│   │   ├── class-input-sanitizer.php
│   │   ├── class-nonce-handler.php
│   │   └── class-rate-limiter.php
│   │
│   ├── performance/                    # Performance optimization
│   │   ├── class-performance-monitor.php
│   │   ├── class-query-optimizer.php
│   │   ├── class-asset-optimizer.php
│   │   └── class-memory-manager.php
│   │
│   ├── analytics/                      # Analytics engine
│   │   ├── class-analytics-engine.php
│   │   ├── class-metrics-collector.php
│   │   ├── class-funnel-analyzer.php
│   │   └── class-report-generator.php
│   │
│   ├── database/                       # Database layer
│   │   ├── class-database-manager.php
│   │   ├── class-query-builder.php
│   │   ├── class-migration.php
│   │   └── migrations/                 # Version migrations
│   │       ├── v1.0.0.php
│   │       └── v2.0.0.php
│   │
│   ├── integrations/                   # Third-party integrations
│   │   ├── class-elementor.php
│   │   ├── class-smtp-handler.php
│   │   └── class-storage-provider.php
│   │
│   └── utils/                          # Utility classes
│       ├── class-logger.php
│       ├── class-error-handler.php
│       ├── class-file-manager.php
│       └── class-string-helper.php
│
├── src/                                # Source files (pre-compiled)
│   ├── admin/                          # Admin JavaScript
│   │   ├── index.js                    # Entry point
│   │   ├── components/                 # React/Vue components (if used)
│   │   ├── modules/                    # Feature modules
│   │   └── services/                   # API clients
│   │
│   ├── scss/                           # SCSS source files
│   │   ├── admin.scss
│   │   ├── critical.scss
│   │   ├── components/
│   │   ├── utilities/
│   │   └── themes/
│   │
│   └── templates/                      # Email/PDF templates
│       ├── email/
│       │   ├── confirmation.html
│       │   └── notification.html
│       └── pdf/
│           ├── default.php
│           └── professional.php
│
├── tests/                              # Test suites
│   ├── phpunit.xml                     # PHPUnit configuration
│   ├── bootstrap.php                   # Test bootstrap
│   ├── unit/                           # Unit tests
│   │   ├── CoreTest.php
│   │   ├── SecurityTest.php
│   │   └── PerformanceTest.php
│   ├── integration/                    # Integration tests
│   │   ├── APITest.php
│   │   └── DatabaseTest.php
│   └── e2e/                            # End-to-end tests (Cypress)
│       ├── submission-flow.spec.js
│       └── admin-interface.spec.js
│
├── languages/                          # Translations
│   ├── formflow-pro-pt_BR.po
│   ├── formflow-pro-pt_BR.mo
│   └── formflow-pro.pot               # Template
│
├── docs/                               # Public documentation
│   ├── installation.md
│   ├── configuration.md
│   ├── api-reference.md
│   └── hooks-filters.md
│
└── vendor/                             # Composer dependencies (gitignored)
    └── autoload.php
```

---

## 🔧 Core Module Architecture

### 1. Form Processor Module

**Responsibility:** Process form submissions from Elementor

**Class Diagram:**
```
┌────────────────────────────────┐
│      FormProcessor             │
├────────────────────────────────┤
│ - validator: DataValidator     │
│ - sanitizer: InputSanitizer    │
│ - queue: QueueManager          │
│ - cache: CacheManager          │
├────────────────────────────────┤
│ + processSubmission($data)     │
│ + validateData($data)          │
│ + sanitizeData($data)          │
│ + storeSubmission($data)       │
│ + queueJobs($submission_id)    │
└────────────────────────────────┘
```

**Processing Flow:**
```
1. Receive webhook from Elementor
   ↓
2. Validate nonce & permissions
   ↓
3. Sanitize all input data
   ↓
4. Validate required fields
   ↓
5. Store submission in database
   ↓
6. Queue background jobs:
   ├─ Generate PDF
   ├─ Send to Autentique
   └─ Send email notification
   ↓
7. Return success response (< 100ms)
   ↓
8. Background workers process jobs
```

**Code Example:**
```php
<?php
namespace FormFlowPro\Core;

class FormProcessor {
    private $validator;
    private $sanitizer;
    private $queue;
    private $cache;

    public function __construct(
        DataValidator $validator,
        InputSanitizer $sanitizer,
        QueueManager $queue,
        CacheManager $cache
    ) {
        $this->validator = $validator;
        $this->sanitizer = $sanitizer;
        $this->queue = $queue;
        $this->cache = $cache;
    }

    public function processSubmission(array $data): array {
        // Validate nonce
        if (!wp_verify_nonce($data['_wpnonce'], 'formflow_submit')) {
            throw new SecurityException('Invalid nonce');
        }

        // Sanitize data
        $clean_data = $this->sanitizer->sanitize($data);

        // Validate data
        $validation = $this->validator->validate($clean_data);
        if (!$validation->isValid()) {
            throw new ValidationException($validation->getErrors());
        }

        // Store submission
        $submission_id = $this->storeSubmission($clean_data);

        // Queue background jobs
        $this->queueJobs($submission_id, $clean_data);

        // Return immediate success
        return [
            'success' => true,
            'submission_id' => $submission_id,
            'message' => __('Form submitted successfully', 'formflow-pro')
        ];
    }

    private function storeSubmission(array $data): string {
        global $wpdb;

        $submission_id = wp_generate_uuid4();

        $wpdb->insert(
            $wpdb->prefix . 'formflow_submissions',
            [
                'id' => $submission_id,
                'form_id' => $data['form_id'],
                'status' => 'pending',
                'data' => wp_json_encode($data),
                'ip_address' => $this->getClientIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            ]
        );

        // Clear list cache
        $this->cache->delete('submissions_list');

        return $submission_id;
    }

    private function queueJobs(string $submission_id, array $data): void {
        // High priority: Generate PDF
        $this->queue->addJob('generate_pdf', [
            'submission_id' => $submission_id,
            'data' => $data
        ], 'high');

        // Medium priority: Send to Autentique
        $this->queue->addJob('send_autentique', [
            'submission_id' => $submission_id,
            'data' => $data
        ], 'medium');

        // Low priority: Send email
        $this->queue->addJob('send_email', [
            'submission_id' => $submission_id,
            'data' => $data
        ], 'low');
    }
}
```

---

### 2. PDF Generator Module

**Responsibility:** Generate professional PDFs from form data

**Class Diagram:**
```
┌────────────────────────────────┐
│       PDFGenerator             │
├────────────────────────────────┤
│ - library: FPDF/TCPDF          │
│ - template: TemplateEngine     │
│ - compressor: PDFCompressor    │
│ - cache: CacheManager          │
├────────────────────────────────┤
│ + generate($data)              │
│ + loadTemplate($template_id)   │
│ + mapFields($data, $template)  │
│ + compress($pdf)               │
│ + validate($pdf)               │
└────────────────────────────────┘
```

**Generation Flow:**
```
1. Load template from cache/database
   ↓
2. Map form fields to PDF fields
   ↓
3. Populate template with data
   ↓
4. Add images, logos, signatures
   ↓
5. Generate PDF binary
   ↓
6. Compress PDF (optimize size)
   ↓
7. Validate PDF integrity
   ↓
8. Store PDF file
   ↓
9. Return file path/URL
```

**Performance Optimizations:**
- Template caching (avoid re-parsing)
- Font subsetting (reduce file size)
- Image optimization (compress, resize)
- PDF compression (gzip-like)
- Lazy loading of libraries

---

### 3. Queue System Module

**Responsibility:** Asynchronous background job processing

**Architecture:**
```
┌─────────────────────────────────────────────────────────┐
│                    QUEUE SYSTEM                          │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ┌──────────────┐                                       │
│  │   Producer   │ ───┐                                  │
│  │  (Add Jobs)  │    │                                  │
│  └──────────────┘    │                                  │
│                       ↓                                  │
│  ┌────────────────────────────────┐                     │
│  │       PRIORITY QUEUES          │                     │
│  ├────────────────────────────────┤                     │
│  │ High Priority   │ Medium │ Low │                     │
│  │ [Job1][Job2]... │ [...] │ [...] │                     │
│  └────────────────────────────────┘                     │
│                       ↓                                  │
│  ┌──────────────┐   ┌──────────────┐   ┌──────────────┐│
│  │  Worker 1    │   │  Worker 2    │   │  Worker 3    ││
│  │ (PDF Gen)    │   │ (Autentique) │   │ (Email)      ││
│  └──────────────┘   └──────────────┘   └──────────────┘│
│         │                   │                   │        │
│         ↓                   ↓                   ↓        │
│  ┌──────────────────────────────────────────────────┐  │
│  │              Success / Retry / DLQ               │  │
│  └──────────────────────────────────────────────────┘  │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Job Lifecycle:**
```
PENDING → PROCESSING → COMPLETED
   ↓           ↓
   ↓        FAILED → RETRY (with backoff)
   ↓           ↓
   └────────→ DEAD LETTER QUEUE (after 3 retries)
```

**Implementation:**
```php
<?php
namespace FormFlowPro\Core;

class QueueManager {
    const PRIORITY_HIGH = 'high';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_LOW = 'low';

    public function addJob(string $type, array $data, string $priority = self::PRIORITY_MEDIUM): int {
        global $wpdb;

        return $wpdb->insert(
            $wpdb->prefix . 'formflow_queue',
            [
                'job_type' => $type,
                'job_data' => wp_json_encode($data),
                'priority' => $priority,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => current_time('mysql'),
                'scheduled_at' => current_time('mysql')
            ]
        );
    }

    public function processNextJob(): bool {
        global $wpdb;

        // Get next job (priority order)
        $job = $wpdb->get_row("
            SELECT * FROM {$wpdb->prefix}formflow_queue
            WHERE status = 'pending'
            AND scheduled_at <= NOW()
            ORDER BY
                FIELD(priority, 'high', 'medium', 'low'),
                created_at ASC
            LIMIT 1
            FOR UPDATE
        ");

        if (!$job) {
            return false;
        }

        try {
            // Mark as processing
            $this->updateJobStatus($job->id, 'processing');

            // Execute job
            $this->executeJob($job);

            // Mark as completed
            $this->updateJobStatus($job->id, 'completed');

            return true;

        } catch (\Exception $e) {
            $this->handleJobFailure($job, $e);
            return false;
        }
    }

    private function executeJob($job): void {
        $data = json_decode($job->job_data, true);

        switch ($job->job_type) {
            case 'generate_pdf':
                $this->executePDFJob($data);
                break;

            case 'send_autentique':
                $this->executeAutentiqueJob($data);
                break;

            case 'send_email':
                $this->executeEmailJob($data);
                break;

            default:
                throw new \InvalidArgumentException("Unknown job type: {$job->job_type}");
        }
    }

    private function handleJobFailure($job, \Exception $e): void {
        $job->attempts++;

        if ($job->attempts >= 3) {
            // Move to dead letter queue
            $this->moveToDeadLetterQueue($job, $e);
        } else {
            // Retry with exponential backoff
            $delay_seconds = pow(2, $job->attempts) * 60;  // 2min, 4min, 8min
            $scheduled_at = date('Y-m-d H:i:s', time() + $delay_seconds);

            $wpdb->update(
                $wpdb->prefix . 'formflow_queue',
                [
                    'status' => 'pending',
                    'attempts' => $job->attempts,
                    'scheduled_at' => $scheduled_at,
                    'last_error' => $e->getMessage()
                ],
                ['id' => $job->id]
            );
        }
    }
}
```

---

### 4. Cache System Module

**Multi-Layer Caching Strategy:**

```
┌─────────────────────────────────────────────────────────┐
│                   CACHE HIERARCHY                        │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  L1: Object Cache (In-Memory, Per Request)              │
│  ├─ Speed: ~0.1ms                                       │
│  ├─ TTL: Request lifetime                               │
│  └─ Size: Unlimited (limited by PHP memory)             │
│                                                          │
│  L2: Redis (Persistent, Shared)                         │
│  ├─ Speed: ~1-2ms                                       │
│  ├─ TTL: Configurable (default 1 hour)                  │
│  └─ Size: Configurable (default 256MB)                  │
│                                                          │
│  L3: Database (Persistent, Authoritative)               │
│  ├─ Speed: ~30-50ms                                     │
│  ├─ TTL: Permanent                                      │
│  └─ Size: Unlimited                                     │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

**Cache Keys Strategy:**
```php
// Cache key patterns
$keys = [
    // Submissions
    'submission:{id}' => 'Single submission data',
    'submissions:list:{page}:{filters_hash}' => 'Paginated list',
    'submissions:count:{form_id}' => 'Total count',

    // Forms
    'form:{id}' => 'Form configuration',
    'forms:list' => 'All forms',

    // Templates
    'template:pdf:{id}' => 'PDF template',
    'template:email:{id}' => 'Email template',

    // Analytics
    'analytics:daily:{date}:{form_id}' => 'Daily metrics',
    'analytics:funnel:{form_id}' => 'Conversion funnel',

    // Settings
    'settings:global' => 'Global plugin settings',
    'settings:form:{id}' => 'Form-specific settings'
];
```

**Cache Invalidation Strategy:**
```php
<?php
class CacheManager {
    public function invalidateSubmission(string $submission_id): void {
        // Invalidate specific submission
        $this->delete("submission:{$submission_id}");

        // Invalidate related lists
        $this->deletePattern('submissions:list:*');
        $this->deletePattern('submissions:count:*');

        // Invalidate analytics
        $this->deletePattern('analytics:*');
    }

    public function invalidateAll(): void {
        $this->flush();
    }

    private function deletePattern(string $pattern): void {
        if ($this->redis) {
            $keys = $this->redis->keys($pattern);
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        }

        // Also clear object cache
        wp_cache_flush();
    }
}
```

---

## 🔐 Security Architecture

### Defense in Depth Strategy

```
┌─────────────────────────────────────────────────────────┐
│                  SECURITY LAYERS                         │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Layer 1: Input Validation & Sanitization               │
│  ├─ Nonce verification                                  │
│  ├─ CSRF token validation                               │
│  ├─ Input sanitization (XSS prevention)                 │
│  └─ Data type validation                                │
│                                                          │
│  Layer 2: Authentication & Authorization                │
│  ├─ WordPress capability checks                         │
│  ├─ Role-based access control (RBAC)                    │
│  ├─ API key validation                                  │
│  └─ IP whitelisting (optional)                          │
│                                                          │
│  Layer 3: Data Protection                               │
│  ├─ Encryption at rest (sensitive fields)               │
│  ├─ TLS/SSL for transport                               │
│  ├─ SQL injection prevention (prepared statements)      │
│  └─ File upload validation                              │
│                                                          │
│  Layer 4: Rate Limiting & DoS Protection                │
│  ├─ Request rate limiting (per IP, per user)            │
│  ├─ Queue throttling                                    │
│  ├─ Resource limits (memory, execution time)            │
│  └─ Circuit breaker for external APIs                   │
│                                                          │
│  Layer 5: Monitoring & Logging                          │
│  ├─ Security event logging                              │
│  ├─ Failed login tracking                               │
│  ├─ Suspicious activity detection                       │
│  └─ Audit trail for sensitive operations                │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

---

## 📊 Data Flow Diagram

### Complete Submission Flow

```
┌──────────────┐
│  User fills  │
│Elementor Form│
└──────┬───────┘
       │
       ↓ (HTTP POST)
┌──────────────────────────────────────────────────────┐
│           FORMFLOW PRO ENTERPRISE                    │
├──────────────────────────────────────────────────────┤
│                                                      │
│  1. RECEIVE & VALIDATE (80ms)                        │
│     ├─ Verify nonce                                 │
│     ├─ Check permissions                            │
│     ├─ Sanitize inputs                              │
│     └─ Validate fields                              │
│                                                      │
│  2. STORE SUBMISSION (20ms)                          │
│     ├─ Generate UUID                                │
│     ├─ Insert to database                           │
│     ├─ Store metadata                               │
│     └─ Clear cache                                  │
│                                                      │
│  3. QUEUE JOBS (30ms)                                │
│     ├─ Queue PDF generation (high priority)         │
│     ├─ Queue Autentique API (medium priority)       │
│     └─ Queue email send (low priority)              │
│                                                      │
│  4. RETURN SUCCESS (10ms)                            │
│     └─ Send JSON response to user                   │
│                                                      │
└──────────────┬───────────────────────────────────────┘
               │
               ↓ (User sees success)
               │
               ↓ (Background processing)
               │
┌──────────────┴───────────────────────────────────────┐
│           BACKGROUND WORKERS                          │
├──────────────────────────────────────────────────────┤
│                                                      │
│  Worker 1: PDF Generation (2s)                       │
│  ├─ Load template from cache                        │
│  ├─ Map form data to PDF fields                     │
│  ├─ Generate PDF binary                             │
│  ├─ Compress PDF                                    │
│  ├─ Store file on disk                              │
│  └─ Update submission record                        │
│                                                      │
│  Worker 2: Autentique API (1.5s)                     │
│  ├─ Prepare API request                             │
│  ├─ Upload PDF to Autentique                        │
│  ├─ Configure signers                               │
│  ├─ Send for signature                              │
│  ├─ Store document ID                               │
│  └─ Update submission record                        │
│                                                      │
│  Worker 3: Email Send (0.5s)                         │
│  ├─ Load email template                             │
│  ├─ Populate with form data                         │
│  ├─ Attach PDF file                                 │
│  ├─ Send via SMTP                                   │
│  ├─ Track email status                              │
│  └─ Update submission record                        │
│                                                      │
└──────────────┬───────────────────────────────────────┘
               │
               ↓
┌──────────────┴───────────────────────────────────────┐
│              FINAL STATE                             │
├──────────────────────────────────────────────────────┤
│  Submission Status: COMPLETED                        │
│  PDF: Generated & Stored                             │
│  Autentique: Document sent for signature             │
│  Email: Sent successfully                            │
│  Total Time: 4.1s (background)                       │
│  User Wait Time: 140ms (perceived)                   │
└──────────────────────────────────────────────────────┘
```

---

## 🔌 API Architecture

### REST API Endpoints

```
/wp-json/formflow/v1/
├── submissions/
│   ├── GET    /                     # List submissions (paginated)
│   ├── GET    /{id}                 # Get submission details
│   ├── POST   /                     # Create submission
│   ├── PUT    /{id}                 # Update submission
│   ├── DELETE /{id}                 # Delete submission
│   └── GET    /{id}/pdf             # Download PDF
│
├── forms/
│   ├── GET    /                     # List forms
│   ├── GET    /{id}                 # Get form config
│   ├── POST   /                     # Create form
│   ├── PUT    /{id}                 # Update form
│   └── DELETE /{id}                 # Delete form
│
├── analytics/
│   ├── GET    /dashboard            # Dashboard metrics
│   ├── GET    /funnel/{form_id}     # Conversion funnel
│   ├── GET    /reports              # Custom reports
│   └── POST   /export               # Export data
│
├── webhooks/
│   ├── POST   /elementor            # Elementor webhook
│   ├── POST   /autentique           # Autentique callback
│   └── POST   /custom               # Custom webhooks
│
└── admin/
    ├── GET    /health               # Health check
    ├── GET    /metrics              # Performance metrics
    └── POST   /cache/clear          # Clear cache
```

---

## 📚 Next Steps

This Architecture Overview provides the foundation. The following documents expand on specific areas:

1. **Design System** (`Design-System.md`) - UI/UX components and patterns
2. **Component Library** (`Component-Library.md`) - Reusable code components
3. **Database Schema** (`Database-Schema.md`) - Complete database design
4. **Performance Budget** (`Performance-Budget.md`) - Performance specifications
5. **UX Analytics** (`UX-Analytics.md`) - Analytics implementation
6. **Technical Specifications** (`Technical-Specifications.md`) - Comprehensive technical docs

---

**End of Architecture Overview**

*This architecture is designed for scale, security, and maintainability.*
