# FormFlow Pro - Production Gaps by File

## Critical Integration Gaps

### 1. Elementor Ajax Handler - Missing Autentique Integration
**File**: `/includes/integrations/elementor/class-ajax-handler.php`

| Line | Function | Gap | Severity | Fix |
|------|----------|-----|----------|-----|
| 158-175 | `handle_form_submission()` | Checks `enable_autentique` flag but calls stub | CRITICAL | Call actual Autentique_Service |
| 320-327 | `create_signature_document()` | Returns null with TODO comment | CRITICAL | Implement service call |
| 162-165 | (same method) | Signature URL never returned to user | HIGH | Pass signature_url in response |

**Code to Fix**:
```php
// Lines 320-327 - Currently:
private static function create_signature_document(int $submission_id, object $form, array $form_data): ?string
{
    // This would integrate with your Autentique service
    // For now, return null
    // TODO: Implement Autentique integration
    return apply_filters('formflow_signature_url', null, $submission_id, $form, $form_data);
}

// Should be:
private static function create_signature_document(int $submission_id, object $form, array $form_data): ?string
{
    try {
        $service = new Autentique_Service();
        $signature_url = $service->create_document($submission_id, $form_data);
        
        // Queue job for status checking
        $queue = \FormFlowPro\Queue\Queue_Manager::get_instance();
        $queue->add_job('check_signature_status', [
            'submission_id' => $submission_id,
            'document_id' => $service->get_last_document_id(),
        ]);
        
        return $signature_url;
    } catch (\Exception $e) {
        error_log('Autentique error: ' . $e->getMessage());
        return apply_filters('formflow_signature_url', null, $submission_id, $form, $form_data);
    }
}
```

---

### 2. Elementor Form Action - Missing Signature Processor
**File**: `/includes/integrations/elementor/actions/class-formflow-action.php`

| Line | Function | Gap | Severity | Fix |
|------|----------|-----|----------|-----|
| 204-206 | `run()` | Calls process_signature() which only fires action | HIGH | Add queue job or service call |
| 358-362 | `process_signature()` | Only calls do_action with no handler | CRITICAL | Add actual service integration |

**Search**: No `add_action('formflow_process_signature')` exists anywhere

**Code to Fix**:
```php
// Lines 358-362 - Currently:
private function process_signature(int $submission_id, array $fields): void
{
    // This would integrate with your Autentique service
    do_action('formflow_process_signature', $submission_id, $fields);
}

// Should be:
private function process_signature(int $submission_id, array $fields): void
{
    try {
        // Get submission and form
        global $wpdb;
        $submission = $wpdb->get_row($wpdb->prepare(
            "SELECT form_id FROM {$wpdb->prefix}formflow_submissions WHERE id = %d",
            $submission_id
        ));
        
        if (!$submission) return;
        
        // Queue signature job
        $queue = Queue\Queue_Manager::get_instance();
        $queue->add_job('create_autentique_document', [
            'submission_id' => $submission_id,
            'form_data' => $fields,
        ], 5);
        
    } catch (\Exception $e) {
        error_log('Signature processing error: ' . $e->getMessage());
        do_action('formflow_signature_error', $submission_id, $e->getMessage());
    }
}
```

---

## Cron Schedule Gaps

### 3. Missing Custom Cron Interval Registration
**File**: `/includes/class-activator.php`

| Line | Issue | Severity | Impact |
|------|-------|----------|--------|
| 147 | Schedules with 'five_minutes' interval | CRITICAL | WordPress default intervals: hourly, twicedaily, daily, weekly |
| 152 | 'daily' exists - OK | - | - |
| 162 | 'weekly' NOT built-in | HIGH | Must register custom 'weekly' |

**Current Code (Line 147)**:
```php
wp_schedule_event(time(), 'five_minutes', 'formflow_process_queue');
```

**Fix**: Create `/includes/class-cron-schedules.php`:
```php
<?php
namespace FormFlowPro;

class Cron_Schedules {
    public static function register_schedules($schedules) {
        // 5 minutes
        if (!isset($schedules['five_minutes'])) {
            $schedules['five_minutes'] = [
                'interval' => 300,
                'display'  => __('Every 5 minutes', 'formflow-pro'),
            ];
        }
        
        // 1 week
        if (!isset($schedules['weekly'])) {
            $schedules['weekly'] = [
                'interval' => 604800,
                'display'  => __('Weekly', 'formflow-pro'),
            ];
        }
        
        return $schedules;
    }
}
```

Then in `/includes/class-activator.php`:
```php
private static function schedule_events()
{
    // Register custom intervals FIRST
    add_filter('cron_schedules', [\FormFlowPro\Cron_Schedules::class, 'register_schedules'], 10);
    
    // Then schedule
    if (!wp_next_scheduled('formflow_process_queue')) {
        wp_schedule_event(time(), 'five_minutes', 'formflow_process_queue');
    }
    // ... rest
}
```

---

### 4. Queue Schedule Conflict - Two Places Scheduling Same Job
**File 1**: `/includes/class-activator.php` (Line 147)
```php
wp_schedule_event(time(), 'five_minutes', 'formflow_process_queue');
```

**File 2**: `/includes/queue/class-queue-manager.php` (Line 33)
```php
wp_schedule_event(time(), 'hourly', 'formflow_process_queue');
```

**Problem**: One schedules for 5 minutes, other for 1 hour. Conflicts on update.

**Fix**: Remove line 33 from Queue_Manager, let Activator handle all scheduling.

---

## Missing Default Options

### 5. Incomplete Default Options Setup
**File**: `/includes/class-activator.php` (Lines 122-135)

**Current Options**:
```php
$defaults = [
    'formflow_cache_enabled' => true,
    'formflow_cache_ttl' => 3600,
    'formflow_debug_mode' => false,
    'formflow_performance_mode' => 'balanced',
    'formflow_queue_enabled' => true,
    'formflow_queue_batch_size' => 10,
];
```

**Missing but used by code**:

| Option | Used In | Line | Default | Issue |
|--------|---------|------|---------|-------|
| `formflow_log_retention_days` | `/includes/logs/class-log-manager.php` | 133 | 30 | UNDEFINED |
| `formflow_archive_after_days` | `/includes/class-archive-manager.php` | 37 | 90 | UNDEFINED |
| `formflow_auto_archive_enabled` | `/includes/class-archive-manager.php` | 38 | false | UNDEFINED |
| `formflow_queue_max_attempts` | `/includes/queue/class-queue-manager.php` | 58 | 3 | UNDEFINED |
| `autentique_api_key` | `/includes/autentique/class-autentique-service.php` | 19 | '' | REQUIRED |

**Add to activator** (lines 135-140):
```php
'formflow_log_retention_days' => 30,
'formflow_archive_after_days' => 90,
'formflow_auto_archive_enabled' => false,
'formflow_queue_max_attempts' => 3,
'autentique_api_key' => '',
'formflow_queue_enabled' => true,
'formflow_queue_batch_size' => 10,
'formflow_company_email' => get_option('admin_email'),
```

---

## Duplicate Code Issues

### 6. Two Incompatible Autentique Implementations

**OLD Implementation** (Currently Used):
- Location: `/includes/autentique/class-autentique-service.php`
- Location: `/includes/autentique/class-webhook-handler.php`
- Class: `Autentique_Service` (snake_case)
- Namespace: `FormFlowPro\Autentique`
- Used by: `/includes/autentique/class-webhook-handler.php`
- Size: ~9.6 KB + 6.8 KB = 16.4 KB

**NEW Implementation** (Not Integrated):
- Location: `/includes/Integrations/Autentique/AutentiqueService.php`
- Location: `/includes/Integrations/Autentique/AutentiqueClient.php`
- Location: `/includes/Integrations/Autentique/WebhookHandler.php`
- Class: `AutentiqueService` (camelCase)
- Namespace: `FormFlowPro\Integrations\Autentique`
- Used by: Tests only
- Size: ~28.3 KB + 8.0 KB + 13.7 KB = 50 KB

**Action**: Remove old implementation, migrate to new PSR-4 compliant version

---

## Missing Cron Handlers

### 7. Cache Cleanup Cron Has No Handler
**File**: `/includes/class-activator.php`

**Line 157 - Schedules event**:
```php
wp_schedule_event(time(), 'hourly', 'formflow_cleanup_cache');
```

**Problem**: No handler registered. 

**Handler exists** in `/includes/cache/class-cache-manager.php` but never hooked:
```php
// Should have in constructor:
add_action('formflow_cleanup_cache', [$this, 'cleanup_expired_cache']);
```

---

## Outdated Documentation

### 8. README.md Has False Claims
**File**: `/README.md`

| Line Range | Claim | Reality | Issue |
|------------|-------|---------|-------|
| 7-10 | Phase 2 Complete | Phase 8 code exists | Misleading |
| 250 | Phase 3 is "[ ] PDF generation" | Phase 3+ code exists | Misleading |
| 254 | Phase 3 is "[ ] Autentique API integration" | Autentique code exists (incomplete) | Incomplete |
| N/A | No phase 4-8 documentation | Tests for phases 3-8 exist | Missing |
| N/A | No deployment checklist | Critical issues not documented | Critical gap |

---

## Empty Language Files

### 9. No Translation Files
**File**: `/languages/` directory

**Status**: Empty
**Required**:
- `formflow-pro.pot` - Translation template
- `pt_BR.po` + `pt_BR.mo` - Portuguese Brazilian (for Brazil deployment)

---

## Summary Table - All Gaps by File

| File | Lines | Gap Type | Severity | Effort |
|------|-------|----------|----------|--------|
| `/includes/integrations/elementor/class-ajax-handler.php` | 320-327 | Missing Autentique call | CRITICAL | 2 hrs |
| `/includes/integrations/elementor/actions/class-formflow-action.php` | 358-362 | Missing signature processor | CRITICAL | 1 hr |
| `/includes/class-activator.php` | 147, 152, 162 | Cron schedule issues | CRITICAL | 1 hr |
| `/includes/queue/class-queue-manager.php` | 33 | Schedule conflict | MEDIUM | 30 min |
| `/includes/class-activator.php` | 122-135 | Missing default options | MEDIUM | 30 min |
| `/includes/autentique/` | All | Duplicate code | HIGH | 2 hrs |
| `/includes/cache/class-cache-manager.php` | Constructor | Missing cron hook | MEDIUM | 15 min |
| `/README.md` | Various | Outdated docs | MEDIUM | 1 hr |
| `/languages/` | All | Empty | LOW | 2 hrs |

---

## Fix Priority Queue

1. **URGENT** (Before committing):
   - [ ] Fix missing Autentique calls in Elementor handlers
   - [ ] Register custom cron intervals
   - [ ] Add missing default options

2. **CRITICAL** (Before deployment):
   - [ ] Fix cron schedule conflicts
   - [ ] Add cache cleanup handler
   - [ ] Update failing tests
   - [ ] Consolidate Autentique code

3. **IMPORTANT** (Before release):
   - [ ] Update README.md documentation
   - [ ] Create translation template

4. **NICE TO HAVE**:
   - [ ] Create admin config UI
   - [ ] Add cron monitor dashboard
