<?php
/**
 * FormFlow Pro - A/B Testing System
 *
 * Comprehensive A/B testing for forms with statistical analysis,
 * multivariate testing, and automatic winner detection.
 *
 * @package FormFlowPro
 * @subpackage FormBuilder
 * @since 2.4.0
 */

namespace FormFlowPro\FormBuilder;

use FormFlowPro\Traits\SingletonTrait;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * A/B Test Model
 */
class ABTest
{
    public int $id;
    public int $form_id;
    public string $name;
    public string $description;
    public string $status; // draft, running, paused, completed
    public string $test_type; // ab, multivariate, split_url
    public string $goal_type; // submission, conversion, engagement
    public array $goal_config;
    public array $variants;
    public string $traffic_allocation; // equal, weighted, bandit
    public array $allocation_weights;
    public int $minimum_sample;
    public float $confidence_level;
    public string $winner_variant_id;
    public string $start_date;
    public string $end_date;
    public string $created_at;
    public int $created_by;
    public array $results;

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * Test Variant Model
 */
class TestVariant
{
    public string $id;
    public string $name;
    public array $changes; // Modifications from control
    public int $views;
    public int $conversions;
    public float $conversion_rate;
    public float $weight;
    public bool $is_control;
    public ?int $version_id; // Link to form version

    public function __construct(array $data = [])
    {
        $this->id = $data['id'] ?? uniqid('var_');
        $this->name = $data['name'] ?? 'Variant';
        $this->changes = $data['changes'] ?? [];
        $this->views = $data['views'] ?? 0;
        $this->conversions = $data['conversions'] ?? 0;
        $this->conversion_rate = $data['conversion_rate'] ?? 0.0;
        $this->weight = $data['weight'] ?? 50.0;
        $this->is_control = $data['is_control'] ?? false;
        $this->version_id = $data['version_id'] ?? null;
    }

    public function toArray(): array
    {
        return get_object_vars($this);
    }
}

/**
 * A/B Testing Manager
 */
class ABTesting
{
    use SingletonTrait;

    private string $tests_table;
    private string $variants_table;
    private string $results_table;
    private string $events_table;

    protected function init(): void
    {
        global $wpdb;

        $this->tests_table = $wpdb->prefix . 'ffp_ab_tests';
        $this->variants_table = $wpdb->prefix . 'ffp_ab_variants';
        $this->results_table = $wpdb->prefix . 'ffp_ab_results';
        $this->events_table = $wpdb->prefix . 'ffp_ab_events';

        $this->createTables();
        $this->registerHooks();
    }

    private function createTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->tests_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            form_id BIGINT UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('draft', 'running', 'paused', 'completed') DEFAULT 'draft',
            test_type ENUM('ab', 'multivariate', 'split_url') DEFAULT 'ab',
            goal_type ENUM('submission', 'conversion', 'engagement', 'custom') DEFAULT 'submission',
            goal_config LONGTEXT,
            traffic_allocation ENUM('equal', 'weighted', 'bandit') DEFAULT 'equal',
            allocation_weights TEXT,
            minimum_sample INT UNSIGNED DEFAULT 100,
            confidence_level FLOAT DEFAULT 0.95,
            winner_variant_id VARCHAR(50) DEFAULT NULL,
            start_date DATETIME DEFAULT NULL,
            end_date DATETIME DEFAULT NULL,
            auto_end_on_winner TINYINT(1) DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_by BIGINT UNSIGNED NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY form_id (form_id),
            KEY status (status),
            KEY start_date (start_date)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->variants_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            variant_id VARCHAR(50) NOT NULL,
            name VARCHAR(255) NOT NULL,
            changes LONGTEXT,
            weight FLOAT DEFAULT 50.0,
            is_control TINYINT(1) DEFAULT 0,
            version_id BIGINT UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY test_variant (test_id, variant_id),
            KEY test_id (test_id)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->results_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            variant_id VARCHAR(50) NOT NULL,
            date DATE NOT NULL,
            views INT UNSIGNED DEFAULT 0,
            conversions INT UNSIGNED DEFAULT 0,
            bounces INT UNSIGNED DEFAULT 0,
            time_on_form INT UNSIGNED DEFAULT 0,
            field_interactions INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY test_variant_date (test_id, variant_id, date),
            KEY test_id (test_id),
            KEY date (date)
        ) {$charset_collate};

        CREATE TABLE IF NOT EXISTS {$this->events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            test_id BIGINT UNSIGNED NOT NULL,
            variant_id VARCHAR(50) NOT NULL,
            visitor_id VARCHAR(64) NOT NULL,
            event_type ENUM('view', 'start', 'interact', 'submit', 'convert', 'bounce') NOT NULL,
            event_data TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY test_id (test_id),
            KEY visitor_id (visitor_id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    private function registerHooks(): void
    {
        add_action('rest_api_init', [$this, 'registerRestRoutes']);
        add_action('wp_ajax_ffp_ab_track_event', [$this, 'ajaxTrackEvent']);
        add_action('wp_ajax_nopriv_ffp_ab_track_event', [$this, 'ajaxTrackEvent']);
        add_action('ffp_form_submission', [$this, 'handleFormSubmission'], 10, 2);
        add_action('ffp_daily_cron', [$this, 'checkTestsForWinners']);

        // Filter form rendering to apply variant
        add_filter('ffp_render_form', [$this, 'applyTestVariant'], 10, 2);
    }

    /**
     * Create a new A/B test
     */
    public function createTest(int $form_id, array $data): ?ABTest
    {
        global $wpdb;

        $test_data = [
            'form_id' => $form_id,
            'name' => sanitize_text_field($data['name'] ?? 'New Test'),
            'description' => sanitize_textarea_field($data['description'] ?? ''),
            'status' => 'draft',
            'test_type' => $data['test_type'] ?? 'ab',
            'goal_type' => $data['goal_type'] ?? 'submission',
            'goal_config' => json_encode($data['goal_config'] ?? []),
            'traffic_allocation' => $data['traffic_allocation'] ?? 'equal',
            'allocation_weights' => json_encode($data['allocation_weights'] ?? []),
            'minimum_sample' => $data['minimum_sample'] ?? 100,
            'confidence_level' => $data['confidence_level'] ?? 0.95,
            'auto_end_on_winner' => $data['auto_end_on_winner'] ?? false,
            'created_at' => current_time('mysql'),
            'created_by' => get_current_user_id(),
        ];

        $inserted = $wpdb->insert($this->tests_table, $test_data);

        if (!$inserted) {
            return null;
        }

        $test_id = $wpdb->insert_id;

        // Create variants
        $variants = $data['variants'] ?? [];

        if (empty($variants)) {
            // Create default control and variant
            $variants = [
                ['name' => 'Control', 'is_control' => true, 'changes' => []],
                ['name' => 'Variant B', 'is_control' => false, 'changes' => []],
            ];
        }

        foreach ($variants as $index => $variant_data) {
            $this->createVariant($test_id, $variant_data);
        }

        do_action('ffp_ab_test_created', $test_id, $form_id);

        return $this->getTest($test_id);
    }

    /**
     * Create a variant
     */
    public function createVariant(int $test_id, array $data): ?TestVariant
    {
        global $wpdb;

        $variant_id = $data['id'] ?? uniqid('var_');

        $variant_data = [
            'test_id' => $test_id,
            'variant_id' => $variant_id,
            'name' => sanitize_text_field($data['name'] ?? 'Variant'),
            'changes' => json_encode($data['changes'] ?? []),
            'weight' => floatval($data['weight'] ?? 50.0),
            'is_control' => $data['is_control'] ?? false,
            'version_id' => $data['version_id'] ?? null,
        ];

        $wpdb->insert($this->variants_table, $variant_data);

        return new TestVariant($data);
    }

    /**
     * Get test by ID
     */
    public function getTest(int $test_id): ?ABTest
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tests_table} WHERE id = %d",
            $test_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToTest($row);
    }

    /**
     * Get active test for a form
     */
    public function getActiveTest(int $form_id): ?ABTest
    {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->tests_table}
             WHERE form_id = %d AND status = 'running'
             ORDER BY start_date DESC LIMIT 1",
            $form_id
        ), ARRAY_A);

        if (!$row) {
            return null;
        }

        return $this->rowToTest($row);
    }

    /**
     * Get all tests for a form
     */
    public function getTests(int $form_id, array $options = []): array
    {
        global $wpdb;

        $status = $options['status'] ?? null;
        $limit = $options['limit'] ?? 50;
        $offset = $options['offset'] ?? 0;

        $sql = "SELECT * FROM {$this->tests_table} WHERE form_id = %d";
        $params = [$form_id];

        if ($status) {
            $sql .= " AND status = %s";
            $params[] = $status;
        }

        $sql .= " ORDER BY created_at DESC LIMIT %d OFFSET %d";
        $params[] = $limit;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$params), ARRAY_A);

        return array_map([$this, 'rowToTest'], $rows);
    }

    /**
     * Start a test
     */
    public function startTest(int $test_id): bool
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test || $test->status !== 'draft') {
            return false;
        }

        // Check if there's already an active test for this form
        $active = $this->getActiveTest($test->form_id);

        if ($active && $active->id !== $test_id) {
            // Pause the existing test
            $this->pauseTest($active->id);
        }

        $updated = $wpdb->update(
            $this->tests_table,
            [
                'status' => 'running',
                'start_date' => current_time('mysql'),
            ],
            ['id' => $test_id]
        );

        if ($updated !== false) {
            do_action('ffp_ab_test_started', $test_id);
            return true;
        }

        return false;
    }

    /**
     * Pause a test
     */
    public function pauseTest(int $test_id): bool
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test || $test->status !== 'running') {
            return false;
        }

        $updated = $wpdb->update(
            $this->tests_table,
            ['status' => 'paused'],
            ['id' => $test_id]
        );

        if ($updated !== false) {
            do_action('ffp_ab_test_paused', $test_id);
            return true;
        }

        return false;
    }

    /**
     * Resume a paused test
     */
    public function resumeTest(int $test_id): bool
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test || $test->status !== 'paused') {
            return false;
        }

        $updated = $wpdb->update(
            $this->tests_table,
            ['status' => 'running'],
            ['id' => $test_id]
        );

        if ($updated !== false) {
            do_action('ffp_ab_test_resumed', $test_id);
            return true;
        }

        return false;
    }

    /**
     * Complete a test
     */
    public function completeTest(int $test_id, string $winner_variant_id = null): bool
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test) {
            return false;
        }

        // Calculate winner if not specified
        if (!$winner_variant_id) {
            $results = $this->calculateResults($test_id);
            $winner = $this->determineWinner($results, $test->confidence_level);
            $winner_variant_id = $winner['variant_id'] ?? null;
        }

        $updated = $wpdb->update(
            $this->tests_table,
            [
                'status' => 'completed',
                'end_date' => current_time('mysql'),
                'winner_variant_id' => $winner_variant_id,
            ],
            ['id' => $test_id]
        );

        if ($updated !== false) {
            do_action('ffp_ab_test_completed', $test_id, $winner_variant_id);
            return true;
        }

        return false;
    }

    /**
     * Delete a test
     */
    public function deleteTest(int $test_id): bool
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test || $test->status === 'running') {
            return false;
        }

        // Delete related data
        $wpdb->delete($this->variants_table, ['test_id' => $test_id]);
        $wpdb->delete($this->results_table, ['test_id' => $test_id]);
        $wpdb->delete($this->events_table, ['test_id' => $test_id]);

        // Delete test
        $deleted = $wpdb->delete($this->tests_table, ['id' => $test_id]);

        if ($deleted) {
            do_action('ffp_ab_test_deleted', $test_id);
            return true;
        }

        return false;
    }

    /**
     * Assign variant to visitor
     */
    public function assignVariant(int $test_id, string $visitor_id = null): ?TestVariant
    {
        $test = $this->getTest($test_id);

        if (!$test || $test->status !== 'running') {
            return null;
        }

        $visitor_id = $visitor_id ?? $this->getVisitorId();

        // Check for existing assignment
        $existing = $this->getVisitorVariant($test_id, $visitor_id);

        if ($existing) {
            return $existing;
        }

        // Assign based on traffic allocation
        $variant = $this->selectVariant($test);

        // Store assignment in cookie
        $this->storeVariantAssignment($test_id, $variant->id, $visitor_id);

        return $variant;
    }

    /**
     * Select variant based on allocation strategy
     */
    private function selectVariant(ABTest $test): TestVariant
    {
        $variants = $test->variants;

        switch ($test->traffic_allocation) {
            case 'weighted':
                return $this->weightedRandomSelection($variants);

            case 'bandit':
                return $this->banditSelection($test, $variants);

            case 'equal':
            default:
                return $variants[array_rand($variants)];
        }
    }

    /**
     * Weighted random selection
     */
    private function weightedRandomSelection(array $variants): TestVariant
    {
        $total_weight = array_sum(array_column($variants, 'weight'));
        $random = mt_rand() / mt_getrandmax() * $total_weight;

        $cumulative = 0;
        foreach ($variants as $variant) {
            $cumulative += $variant->weight;
            if ($random <= $cumulative) {
                return $variant;
            }
        }

        return end($variants);
    }

    /**
     * Multi-armed bandit selection (Thompson Sampling)
     */
    private function banditSelection(ABTest $test, array $variants): TestVariant
    {
        $samples = [];

        foreach ($variants as $variant) {
            // Beta distribution sampling
            $alpha = $variant->conversions + 1;
            $beta = $variant->views - $variant->conversions + 1;

            $samples[$variant->id] = $this->betaSample($alpha, $beta);
        }

        // Select variant with highest sample
        arsort($samples);
        $selected_id = array_key_first($samples);

        foreach ($variants as $variant) {
            if ($variant->id === $selected_id) {
                return $variant;
            }
        }

        return $variants[0];
    }

    /**
     * Beta distribution sampling approximation
     */
    private function betaSample(float $alpha, float $beta): float
    {
        // Using approximation for Beta distribution
        $u1 = mt_rand() / mt_getrandmax();
        $u2 = mt_rand() / mt_getrandmax();

        // Box-Muller transform for normal approximation
        $z = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);

        // Approximate beta mean and variance
        $mean = $alpha / ($alpha + $beta);
        $variance = ($alpha * $beta) / (pow($alpha + $beta, 2) * ($alpha + $beta + 1));

        return max(0, min(1, $mean + $z * sqrt($variance)));
    }

    /**
     * Get visitor's assigned variant
     */
    private function getVisitorVariant(int $test_id, string $visitor_id): ?TestVariant
    {
        $cookie_name = 'ffp_ab_' . $test_id;

        if (isset($_COOKIE[$cookie_name])) {
            $variant_id = sanitize_text_field($_COOKIE[$cookie_name]);
            $test = $this->getTest($test_id);

            foreach ($test->variants as $variant) {
                if ($variant->id === $variant_id) {
                    return $variant;
                }
            }
        }

        return null;
    }

    /**
     * Store variant assignment
     */
    private function storeVariantAssignment(int $test_id, string $variant_id, string $visitor_id): void
    {
        $cookie_name = 'ffp_ab_' . $test_id;
        $expiry = time() + (30 * DAY_IN_SECONDS); // 30 days

        setcookie($cookie_name, $variant_id, $expiry, '/', '', is_ssl(), true);
        $_COOKIE[$cookie_name] = $variant_id;
    }

    /**
     * Track event
     */
    public function trackEvent(int $test_id, string $variant_id, string $event_type, array $data = []): bool
    {
        global $wpdb;

        $visitor_id = $this->getVisitorId();

        // Insert event
        $inserted = $wpdb->insert($this->events_table, [
            'test_id' => $test_id,
            'variant_id' => $variant_id,
            'visitor_id' => $visitor_id,
            'event_type' => $event_type,
            'event_data' => json_encode($data),
            'created_at' => current_time('mysql'),
        ]);

        if (!$inserted) {
            return false;
        }

        // Update daily results
        $this->updateDailyResults($test_id, $variant_id, $event_type);

        return true;
    }

    /**
     * Update daily results aggregate
     */
    private function updateDailyResults(int $test_id, string $variant_id, string $event_type): void
    {
        global $wpdb;

        $date = current_time('Y-m-d');

        // Check if record exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$this->results_table}
             WHERE test_id = %d AND variant_id = %s AND date = %s",
            $test_id,
            $variant_id,
            $date
        ));

        $column = match ($event_type) {
            'view' => 'views',
            'submit', 'convert' => 'conversions',
            'bounce' => 'bounces',
            default => null,
        };

        if (!$column) {
            return;
        }

        if ($existing) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$this->results_table} SET {$column} = {$column} + 1
                 WHERE test_id = %d AND variant_id = %s AND date = %s",
                $test_id,
                $variant_id,
                $date
            ));
        } else {
            $wpdb->insert($this->results_table, [
                'test_id' => $test_id,
                'variant_id' => $variant_id,
                'date' => $date,
                $column => 1,
            ]);
        }
    }

    /**
     * Calculate test results
     */
    public function calculateResults(int $test_id): array
    {
        global $wpdb;

        $test = $this->getTest($test_id);

        if (!$test) {
            return [];
        }

        $results = [];

        foreach ($test->variants as $variant) {
            $totals = $wpdb->get_row($wpdb->prepare(
                "SELECT
                    SUM(views) as total_views,
                    SUM(conversions) as total_conversions,
                    SUM(bounces) as total_bounces,
                    SUM(time_on_form) as total_time,
                    SUM(field_interactions) as total_interactions
                 FROM {$this->results_table}
                 WHERE test_id = %d AND variant_id = %s",
                $test_id,
                $variant->id
            ), ARRAY_A);

            $views = (int) ($totals['total_views'] ?? 0);
            $conversions = (int) ($totals['total_conversions'] ?? 0);
            $bounces = (int) ($totals['total_bounces'] ?? 0);

            $conversion_rate = $views > 0 ? ($conversions / $views) * 100 : 0;
            $bounce_rate = $views > 0 ? ($bounces / $views) * 100 : 0;

            $results[$variant->id] = [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'is_control' => $variant->is_control,
                'views' => $views,
                'conversions' => $conversions,
                'bounces' => $bounces,
                'conversion_rate' => round($conversion_rate, 2),
                'bounce_rate' => round($bounce_rate, 2),
                'average_time' => $views > 0 ? round(($totals['total_time'] ?? 0) / $views, 1) : 0,
            ];
        }

        // Calculate statistical significance
        $control = null;
        foreach ($results as $result) {
            if ($result['is_control']) {
                $control = $result;
                break;
            }
        }

        if ($control) {
            foreach ($results as $id => &$result) {
                if (!$result['is_control']) {
                    $result['improvement'] = $control['conversion_rate'] > 0
                        ? round((($result['conversion_rate'] - $control['conversion_rate']) / $control['conversion_rate']) * 100, 2)
                        : 0;

                    $result['statistical_significance'] = $this->calculateStatisticalSignificance(
                        $control['views'],
                        $control['conversions'],
                        $result['views'],
                        $result['conversions']
                    );

                    $result['is_significant'] = $result['statistical_significance'] >= ($test->confidence_level * 100);
                }
            }
        }

        return $results;
    }

    /**
     * Calculate statistical significance using Z-test
     */
    public function calculateStatisticalSignificance(
        int $control_views,
        int $control_conversions,
        int $variant_views,
        int $variant_conversions
    ): float {
        if ($control_views < 1 || $variant_views < 1) {
            return 0;
        }

        $p1 = $control_conversions / $control_views;
        $p2 = $variant_conversions / $variant_views;

        // Pooled probability
        $p_pool = ($control_conversions + $variant_conversions) / ($control_views + $variant_views);

        // Standard error
        $se = sqrt($p_pool * (1 - $p_pool) * (1 / $control_views + 1 / $variant_views));

        if ($se == 0) {
            return 0;
        }

        // Z-score
        $z = abs($p2 - $p1) / $se;

        // Convert to confidence level (approximation of 1 - p-value)
        // Using error function approximation
        $confidence = $this->normalCDF($z) * 2 - 1;

        return round($confidence * 100, 2);
    }

    /**
     * Normal CDF approximation
     */
    private function normalCDF(float $z): float
    {
        $a1 = 0.254829592;
        $a2 = -0.284496736;
        $a3 = 1.421413741;
        $a4 = -1.453152027;
        $a5 = 1.061405429;
        $p = 0.3275911;

        $sign = $z < 0 ? -1 : 1;
        $z = abs($z) / sqrt(2);

        $t = 1.0 / (1.0 + $p * $z);
        $y = 1.0 - ((((($a5 * $t + $a4) * $t) + $a3) * $t + $a2) * $t + $a1) * $t * exp(-$z * $z);

        return 0.5 * (1.0 + $sign * $y);
    }

    /**
     * Determine winner based on results
     */
    public function determineWinner(array $results, float $confidence_level = 0.95): array
    {
        $required_confidence = $confidence_level * 100;
        $winner = null;
        $best_rate = -1;

        foreach ($results as $result) {
            if ($result['is_control']) {
                continue;
            }

            if (isset($result['is_significant']) && $result['is_significant']) {
                if ($result['conversion_rate'] > $best_rate) {
                    $best_rate = $result['conversion_rate'];
                    $winner = $result;
                }
            }
        }

        // If no significant winner, check if control is best
        if (!$winner) {
            foreach ($results as $result) {
                if ($result['is_control'] && $result['conversion_rate'] > $best_rate) {
                    $best_rate = $result['conversion_rate'];
                    $winner = $result;
                }
            }
        }

        return $winner ?? [];
    }

    /**
     * Check tests for automatic winner detection
     */
    public function checkTestsForWinners(): void
    {
        global $wpdb;

        $running_tests = $wpdb->get_col(
            "SELECT id FROM {$this->tests_table}
             WHERE status = 'running' AND auto_end_on_winner = 1"
        );

        foreach ($running_tests as $test_id) {
            $test = $this->getTest($test_id);

            if (!$test) {
                continue;
            }

            $results = $this->calculateResults($test_id);

            // Check minimum sample size
            $total_views = array_sum(array_column($results, 'views'));

            if ($total_views < $test->minimum_sample) {
                continue;
            }

            // Check for winner
            $winner = $this->determineWinner($results, $test->confidence_level);

            if (!empty($winner)) {
                $this->completeTest($test_id, $winner['variant_id']);

                // Notify
                do_action('ffp_ab_test_winner_found', $test_id, $winner);
            }
        }
    }

    /**
     * Apply test variant to form rendering
     */
    public function applyTestVariant(array $form_data, int $form_id): array
    {
        $test = $this->getActiveTest($form_id);

        if (!$test) {
            return $form_data;
        }

        $variant = $this->assignVariant($test->id);

        if (!$variant || $variant->is_control) {
            return $form_data;
        }

        // Track view
        $this->trackEvent($test->id, $variant->id, 'view');

        // Apply variant changes
        return $this->applyVariantChanges($form_data, $variant->changes);
    }

    /**
     * Apply variant changes to form data
     */
    private function applyVariantChanges(array $form_data, array $changes): array
    {
        foreach ($changes as $change) {
            $path = $change['path'] ?? '';
            $value = $change['value'] ?? null;
            $action = $change['action'] ?? 'set';

            switch ($action) {
                case 'set':
                    $form_data = $this->setNestedValue($form_data, $path, $value);
                    break;

                case 'remove':
                    $form_data = $this->removeNestedValue($form_data, $path);
                    break;

                case 'hide':
                    $form_data = $this->setNestedValue($form_data, $path . '.hidden', true);
                    break;

                case 'reorder':
                    if (strpos($path, 'fields') === 0) {
                        $form_data['fields'] = $this->reorderArray($form_data['fields'], $value);
                    }
                    break;
            }
        }

        return $form_data;
    }

    /**
     * Set nested value in array using dot notation
     */
    private function setNestedValue(array $array, string $path, $value): array
    {
        $keys = explode('.', $path);
        $current = &$array;

        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }

        $current = $value;

        return $array;
    }

    /**
     * Remove nested value from array
     */
    private function removeNestedValue(array $array, string $path): array
    {
        $keys = explode('.', $path);
        $current = &$array;

        for ($i = 0; $i < count($keys) - 1; $i++) {
            if (!isset($current[$keys[$i]])) {
                return $array;
            }
            $current = &$current[$keys[$i]];
        }

        unset($current[end($keys)]);

        return $array;
    }

    /**
     * Reorder array by new order
     */
    private function reorderArray(array $array, array $new_order): array
    {
        $indexed = [];
        foreach ($array as $item) {
            $id = $item['id'] ?? $item['name'] ?? '';
            $indexed[$id] = $item;
        }

        $reordered = [];
        foreach ($new_order as $id) {
            if (isset($indexed[$id])) {
                $reordered[] = $indexed[$id];
                unset($indexed[$id]);
            }
        }

        // Add any remaining items
        foreach ($indexed as $item) {
            $reordered[] = $item;
        }

        return $reordered;
    }

    /**
     * Get unique visitor ID
     */
    private function getVisitorId(): string
    {
        $cookie_name = 'ffp_visitor_id';

        if (isset($_COOKIE[$cookie_name])) {
            return sanitize_text_field($_COOKIE[$cookie_name]);
        }

        $visitor_id = wp_generate_uuid4();
        $expiry = time() + (365 * DAY_IN_SECONDS);

        setcookie($cookie_name, $visitor_id, $expiry, '/', '', is_ssl(), true);
        $_COOKIE[$cookie_name] = $visitor_id;

        return $visitor_id;
    }

    /**
     * Handle form submission for conversion tracking
     */
    public function handleFormSubmission(int $form_id, array $submission_data): void
    {
        $test = $this->getActiveTest($form_id);

        if (!$test) {
            return;
        }

        $variant = $this->getVisitorVariant($test->id, $this->getVisitorId());

        if ($variant) {
            $this->trackEvent($test->id, $variant->id, 'convert', [
                'submission_id' => $submission_data['submission_id'] ?? 0,
            ]);
        }
    }

    /**
     * Convert database row to ABTest object
     */
    private function rowToTest(array $row): ABTest
    {
        $test = new ABTest([
            'id' => (int) $row['id'],
            'form_id' => (int) $row['form_id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'status' => $row['status'],
            'test_type' => $row['test_type'],
            'goal_type' => $row['goal_type'],
            'goal_config' => json_decode($row['goal_config'] ?? '{}', true) ?? [],
            'traffic_allocation' => $row['traffic_allocation'],
            'allocation_weights' => json_decode($row['allocation_weights'] ?? '[]', true) ?? [],
            'minimum_sample' => (int) $row['minimum_sample'],
            'confidence_level' => (float) $row['confidence_level'],
            'winner_variant_id' => $row['winner_variant_id'],
            'start_date' => $row['start_date'],
            'end_date' => $row['end_date'],
            'created_at' => $row['created_at'],
            'created_by' => (int) $row['created_by'],
        ]);

        // Load variants
        $test->variants = $this->getVariants($test->id);

        // Load results
        $test->results = $this->calculateResults($test->id);

        return $test;
    }

    /**
     * Get variants for a test
     */
    private function getVariants(int $test_id): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->variants_table} WHERE test_id = %d ORDER BY is_control DESC",
            $test_id
        ), ARRAY_A);

        return array_map(function($row) {
            return new TestVariant([
                'id' => $row['variant_id'],
                'name' => $row['name'],
                'changes' => json_decode($row['changes'] ?? '[]', true) ?? [],
                'weight' => (float) $row['weight'],
                'is_control' => (bool) $row['is_control'],
                'version_id' => $row['version_id'] ? (int) $row['version_id'] : null,
            ]);
        }, $rows);
    }

    /**
     * Get time series data for reporting
     */
    public function getTimeSeriesData(int $test_id, string $start_date = null, string $end_date = null): array
    {
        global $wpdb;

        $start_date = $start_date ?? date('Y-m-d', strtotime('-30 days'));
        $end_date = $end_date ?? date('Y-m-d');

        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT date, variant_id, views, conversions
             FROM {$this->results_table}
             WHERE test_id = %d AND date BETWEEN %s AND %s
             ORDER BY date ASC",
            $test_id,
            $start_date,
            $end_date
        ), ARRAY_A);

        // Organize by date and variant
        $series = [];

        foreach ($results as $row) {
            $date = $row['date'];
            $variant_id = $row['variant_id'];

            if (!isset($series[$date])) {
                $series[$date] = ['date' => $date];
            }

            $series[$date][$variant_id . '_views'] = (int) $row['views'];
            $series[$date][$variant_id . '_conversions'] = (int) $row['conversions'];
            $series[$date][$variant_id . '_rate'] = $row['views'] > 0
                ? round(($row['conversions'] / $row['views']) * 100, 2)
                : 0;
        }

        return array_values($series);
    }

    /**
     * Register REST API routes
     */
    public function registerRestRoutes(): void
    {
        register_rest_route('form-flow-pro/v1', '/forms/(?P<form_id>\d+)/tests', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetTests'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'POST',
                'callback' => [$this, 'restCreateTest'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/tests/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [$this, 'restGetTest'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'PUT',
                'callback' => [$this, 'restUpdateTest'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
            [
                'methods' => 'DELETE',
                'callback' => [$this, 'restDeleteTest'],
                'permission_callback' => function() {
                    return current_user_can('edit_posts');
                },
            ],
        ]);

        register_rest_route('form-flow-pro/v1', '/tests/(?P<id>\d+)/(?P<action>start|pause|resume|complete)', [
            'methods' => 'POST',
            'callback' => [$this, 'restTestAction'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);

        register_rest_route('form-flow-pro/v1', '/tests/(?P<id>\d+)/results', [
            'methods' => 'GET',
            'callback' => [$this, 'restGetResults'],
            'permission_callback' => function() {
                return current_user_can('edit_posts');
            },
        ]);
    }

    public function restGetTests(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('form_id');
        $tests = $this->getTests($form_id);

        return new \WP_REST_Response(array_map(function($t) { return $t->toArray(); }, $tests));
    }

    public function restCreateTest(\WP_REST_Request $request): \WP_REST_Response
    {
        $form_id = (int) $request->get_param('form_id');
        $data = $request->get_json_params();

        $test = $this->createTest($form_id, $data);

        if (!$test) {
            return new \WP_REST_Response(['error' => 'Failed to create test'], 500);
        }

        return new \WP_REST_Response($test->toArray(), 201);
    }

    public function restGetTest(\WP_REST_Request $request): \WP_REST_Response
    {
        $test_id = (int) $request->get_param('id');
        $test = $this->getTest($test_id);

        if (!$test) {
            return new \WP_REST_Response(['error' => 'Test not found'], 404);
        }

        return new \WP_REST_Response($test->toArray());
    }

    public function restUpdateTest(\WP_REST_Request $request): \WP_REST_Response
    {
        global $wpdb;

        $test_id = (int) $request->get_param('id');
        $test = $this->getTest($test_id);

        if (!$test) {
            return new \WP_REST_Response(['error' => 'Test not found'], 404);
        }

        if ($test->status === 'running') {
            return new \WP_REST_Response(['error' => 'Cannot update running test'], 400);
        }

        $data = $request->get_json_params();

        $wpdb->update(
            $this->tests_table,
            [
                'name' => sanitize_text_field($data['name'] ?? $test->name),
                'description' => sanitize_textarea_field($data['description'] ?? $test->description),
                'goal_type' => $data['goal_type'] ?? $test->goal_type,
                'goal_config' => json_encode($data['goal_config'] ?? $test->goal_config),
                'traffic_allocation' => $data['traffic_allocation'] ?? $test->traffic_allocation,
                'minimum_sample' => $data['minimum_sample'] ?? $test->minimum_sample,
                'confidence_level' => $data['confidence_level'] ?? $test->confidence_level,
            ],
            ['id' => $test_id]
        );

        return new \WP_REST_Response($this->getTest($test_id)->toArray());
    }

    public function restDeleteTest(\WP_REST_Request $request): \WP_REST_Response
    {
        $test_id = (int) $request->get_param('id');

        if ($this->deleteTest($test_id)) {
            return new \WP_REST_Response(['success' => true]);
        }

        return new \WP_REST_Response(['error' => 'Cannot delete test'], 400);
    }

    public function restTestAction(\WP_REST_Request $request): \WP_REST_Response
    {
        $test_id = (int) $request->get_param('id');
        $action = $request->get_param('action');

        $result = match ($action) {
            'start' => $this->startTest($test_id),
            'pause' => $this->pauseTest($test_id),
            'resume' => $this->resumeTest($test_id),
            'complete' => $this->completeTest($test_id),
            default => false,
        };

        if ($result) {
            return new \WP_REST_Response($this->getTest($test_id)->toArray());
        }

        return new \WP_REST_Response(['error' => 'Action failed'], 400);
    }

    public function restGetResults(\WP_REST_Request $request): \WP_REST_Response
    {
        $test_id = (int) $request->get_param('id');
        $test = $this->getTest($test_id);

        if (!$test) {
            return new \WP_REST_Response(['error' => 'Test not found'], 404);
        }

        $results = $this->calculateResults($test_id);
        $time_series = $this->getTimeSeriesData($test_id);
        $winner = $this->determineWinner($results, $test->confidence_level);

        return new \WP_REST_Response([
            'results' => $results,
            'time_series' => $time_series,
            'winner' => $winner,
            'has_winner' => !empty($winner),
        ]);
    }

    /**
     * AJAX track event handler
     */
    public function ajaxTrackEvent(): void
    {
        $test_id = (int) ($_POST['test_id'] ?? 0);
        $variant_id = sanitize_text_field($_POST['variant_id'] ?? '');
        $event_type = sanitize_text_field($_POST['event_type'] ?? '');
        $event_data = json_decode(stripslashes($_POST['event_data'] ?? '{}'), true);

        if (!$test_id || !$variant_id || !$event_type) {
            wp_send_json_error(['message' => 'Invalid parameters']);
        }

        $valid_events = ['view', 'start', 'interact', 'submit', 'convert', 'bounce'];

        if (!in_array($event_type, $valid_events)) {
            wp_send_json_error(['message' => 'Invalid event type']);
        }

        $result = $this->trackEvent($test_id, $variant_id, $event_type, $event_data ?? []);

        if ($result) {
            wp_send_json_success(['tracked' => true]);
        } else {
            wp_send_json_error(['message' => 'Failed to track event']);
        }
    }
}
