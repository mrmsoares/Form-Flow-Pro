<?php

declare(strict_types=1);

/**
 * AI Service
 *
 * Central service for AI-powered features.
 *
 * @package FormFlowPro\AI
 * @since 2.3.0
 */

namespace FormFlowPro\AI;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * AI Service Class
 */
class AIService
{
    /**
     * Singleton instance
     *
     * @var self|null
     */
    private static ?self $instance = null;

    /**
     * AI Provider
     *
     * @var AIProviderInterface|null
     */
    private ?AIProviderInterface $provider = null;

    /**
     * Configuration
     *
     * @var array
     */
    private array $config = [];

    /**
     * Get singleton instance
     *
     * @return self
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct()
    {
        $this->loadConfig();
        $this->initProvider();
        $this->setupHooks();
    }

    /**
     * Load configuration
     *
     * @return void
     */
    private function loadConfig(): void
    {
        $defaults = [
            'enabled' => false,
            'provider' => 'openai',
            'api_key' => '',
            'model' => 'gpt-4o-mini',
            'features' => [
                'spam_detection' => true,
                'smart_validation' => true,
                'auto_suggestions' => false,
                'document_classification' => false,
                'sentiment_analysis' => false,
            ],
            'spam_threshold' => 0.7,
            'cache_responses' => true,
            'cache_ttl' => 3600,
            'rate_limit' => 100, // requests per hour
            'log_requests' => false,
        ];

        $this->config = wp_parse_args(
            get_option('formflow_ai_settings', []),
            $defaults
        );
    }

    /**
     * Initialize AI provider
     *
     * @return void
     */
    private function initProvider(): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        // Load provider classes
        require_once FORMFLOW_PATH . 'includes/AI/AIProviderInterface.php';
        require_once FORMFLOW_PATH . 'includes/AI/OpenAIProvider.php';
        require_once FORMFLOW_PATH . 'includes/AI/LocalAIProvider.php';

        switch ($this->config['provider']) {
            case 'openai':
                $this->provider = new OpenAIProvider($this->config['api_key'], $this->config['model']);
                break;
            case 'local':
                $this->provider = new LocalAIProvider();
                break;
            default:
                $this->provider = apply_filters('formflow_ai_provider', null, $this->config);
        }
    }

    /**
     * Setup WordPress hooks
     *
     * @return void
     */
    private function setupHooks(): void
    {
        // Spam detection on form submission
        add_filter('formflow_validate_submission', [$this, 'detectSpam'], 10, 2);

        // Smart validation suggestions
        add_filter('formflow_field_validation', [$this, 'smartValidation'], 10, 3);

        // Document classification
        add_action('formflow_submission_created', [$this, 'classifySubmission'], 10, 2);
    }

    /**
     * Check if AI is enabled
     *
     * @return bool
     */
    public function isEnabled(): bool
    {
        return !empty($this->config['enabled']) && !empty($this->config['api_key']);
    }

    /**
     * Check if feature is enabled
     *
     * @param string $feature Feature name
     * @return bool
     */
    public function isFeatureEnabled(string $feature): bool
    {
        return $this->isEnabled() && !empty($this->config['features'][$feature]);
    }

    /**
     * Get configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return $this->config;
    }

    /**
     * Save configuration
     *
     * @param array $config Configuration data
     * @return bool
     */
    public function saveConfig(array $config): bool
    {
        $sanitized = [
            'enabled' => !empty($config['enabled']),
            'provider' => sanitize_text_field($config['provider'] ?? 'openai'),
            'api_key' => sanitize_text_field($config['api_key'] ?? ''),
            'model' => sanitize_text_field($config['model'] ?? 'gpt-4o-mini'),
            'features' => [
                'spam_detection' => !empty($config['features']['spam_detection']),
                'smart_validation' => !empty($config['features']['smart_validation']),
                'auto_suggestions' => !empty($config['features']['auto_suggestions']),
                'document_classification' => !empty($config['features']['document_classification']),
                'sentiment_analysis' => !empty($config['features']['sentiment_analysis']),
            ],
            'spam_threshold' => min(1, max(0, (float) ($config['spam_threshold'] ?? 0.7))),
            'cache_responses' => !empty($config['cache_responses']),
            'cache_ttl' => (int) ($config['cache_ttl'] ?? 3600),
            'rate_limit' => (int) ($config['rate_limit'] ?? 100),
            'log_requests' => !empty($config['log_requests']),
        ];

        $result = update_option('formflow_ai_settings', $sanitized);

        if ($result) {
            $this->config = $sanitized;
            $this->initProvider();
        }

        return $result;
    }

    /**
     * Detect spam in submission
     *
     * @param array $errors Current validation errors
     * @param array $submission Submission data
     * @return array
     */
    public function detectSpam(array $errors, array $submission): array
    {
        if (!$this->isFeatureEnabled('spam_detection')) {
            return $errors;
        }

        $spamScore = $this->analyzeForSpam($submission);

        if ($spamScore >= $this->config['spam_threshold']) {
            $errors['spam'] = __('This submission has been flagged as potential spam.', 'formflow-pro');

            // Log spam detection
            $this->logAIAction('spam_detected', [
                'score' => $spamScore,
                'submission' => $this->sanitizeForLog($submission),
            ]);
        }

        return $errors;
    }

    /**
     * Analyze submission for spam
     *
     * @param array $submission Submission data
     * @return float Spam score (0-1)
     */
    public function analyzeForSpam(array $submission): float
    {
        // First use local heuristics
        $localScore = $this->localSpamAnalysis($submission);

        // If clearly spam or clearly not spam, skip AI
        if ($localScore >= 0.9 || $localScore <= 0.1) {
            return $localScore;
        }

        // Use AI for uncertain cases
        if ($this->provider && $this->isFeatureEnabled('spam_detection')) {
            $aiScore = $this->aiSpamAnalysis($submission);

            // Combine scores (weighted average)
            return ($localScore * 0.4) + ($aiScore * 0.6);
        }

        return $localScore;
    }

    /**
     * Local spam analysis using heuristics
     *
     * @param array $submission Submission data
     * @return float
     */
    private function localSpamAnalysis(array $submission): float
    {
        $score = 0;
        $checks = 0;

        $formData = $submission['form_data'] ?? $submission['data'] ?? [];
        $content = implode(' ', array_values(array_filter($formData, 'is_string')));

        // Check for spam keywords
        $spamKeywords = [
            'viagra', 'cialis', 'casino', 'lottery', 'winner', 'prize',
            'click here', 'free money', 'make money fast', 'work from home',
            'bitcoin', 'crypto', 'investment opportunity', 'double your',
        ];

        foreach ($spamKeywords as $keyword) {
            if (stripos($content, $keyword) !== false) {
                $score += 0.3;
            }
        }
        $checks++;

        // Check for excessive links
        $linkCount = preg_match_all('/https?:\/\//', $content);
        if ($linkCount > 3) {
            $score += min(0.5, $linkCount * 0.1);
        }
        $checks++;

        // Check for all caps
        $capsRatio = $this->calculateCapsRatio($content);
        if ($capsRatio > 0.5) {
            $score += 0.2;
        }
        $checks++;

        // Check for suspicious email patterns
        if (!empty($formData['email'])) {
            if (preg_match('/@(mail\.ru|yandex\.|qq\.com)/', $formData['email'])) {
                $score += 0.1;
            }
            if (preg_match('/\d{5,}/', $formData['email'])) {
                $score += 0.1;
            }
        }
        $checks++;

        // Check submission speed (honeypot-like)
        if (isset($submission['form_load_time'], $submission['submit_time'])) {
            $duration = $submission['submit_time'] - $submission['form_load_time'];
            if ($duration < 3) { // Less than 3 seconds
                $score += 0.4;
            }
        }
        $checks++;

        // Check for repeated characters
        if (preg_match('/(.)\1{5,}/', $content)) {
            $score += 0.2;
        }
        $checks++;

        return min(1, $score);
    }

    /**
     * AI-powered spam analysis
     *
     * @param array $submission Submission data
     * @return float
     */
    private function aiSpamAnalysis(array $submission): float
    {
        if (!$this->provider) {
            return 0.5;
        }

        // Check cache
        $cacheKey = 'formflow_spam_' . md5(serialize($submission['form_data'] ?? []));
        if ($this->config['cache_responses']) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return (float) $cached;
            }
        }

        // Build prompt
        $formData = $submission['form_data'] ?? $submission['data'] ?? [];
        $prompt = "Analyze the following form submission for spam. Rate from 0 (not spam) to 1 (definitely spam). Return ONLY a number.\n\n";
        $prompt .= "Form data:\n" . wp_json_encode($formData, JSON_PRETTY_PRINT);

        try {
            $response = $this->provider->complete($prompt, [
                'max_tokens' => 10,
                'temperature' => 0,
            ]);

            $score = (float) trim($response);
            $score = max(0, min(1, $score));

            // Cache result
            if ($this->config['cache_responses']) {
                set_transient($cacheKey, $score, $this->config['cache_ttl']);
            }

            return $score;
        } catch (\Exception $e) {
            $this->logAIAction('spam_analysis_error', ['error' => $e->getMessage()]);
            return 0.5;
        }
    }

    /**
     * Calculate ratio of uppercase characters
     *
     * @param string $text Text to analyze
     * @return float
     */
    private function calculateCapsRatio(string $text): float
    {
        $text = preg_replace('/[^a-zA-Z]/', '', $text);
        if (empty($text)) {
            return 0;
        }

        $upper = preg_replace('/[^A-Z]/', '', $text);
        return strlen($upper) / strlen($text);
    }

    /**
     * Smart validation for form fields
     *
     * @param array $errors Current errors
     * @param string $fieldName Field name
     * @param mixed $value Field value
     * @return array
     */
    public function smartValidation(array $errors, string $fieldName, $value): array
    {
        if (!$this->isFeatureEnabled('smart_validation') || !$this->provider) {
            return $errors;
        }

        // Only validate non-empty string values
        if (!is_string($value) || empty($value)) {
            return $errors;
        }

        // Get field type hints
        $fieldType = $this->inferFieldType($fieldName);

        // Validate based on inferred type
        switch ($fieldType) {
            case 'email':
                $validation = $this->validateEmailIntelligent($value);
                break;
            case 'phone':
                $validation = $this->validatePhoneIntelligent($value);
                break;
            case 'name':
                $validation = $this->validateNameIntelligent($value);
                break;
            case 'address':
                $validation = $this->validateAddressIntelligent($value);
                break;
            default:
                $validation = ['valid' => true];
        }

        if (!$validation['valid'] && !empty($validation['message'])) {
            $errors[$fieldName] = $validation['message'];
        }

        return $errors;
    }

    /**
     * Infer field type from name
     *
     * @param string $fieldName Field name
     * @return string
     */
    private function inferFieldType(string $fieldName): string
    {
        $fieldName = strtolower($fieldName);

        if (preg_match('/email|e-mail|mail/', $fieldName)) {
            return 'email';
        }
        if (preg_match('/phone|tel|mobile|cell/', $fieldName)) {
            return 'phone';
        }
        if (preg_match('/name|first|last|full/', $fieldName)) {
            return 'name';
        }
        if (preg_match('/address|street|city|zip|postal/', $fieldName)) {
            return 'address';
        }

        return 'text';
    }

    /**
     * Intelligent email validation
     *
     * @param string $email Email address
     * @return array
     */
    private function validateEmailIntelligent(string $email): array
    {
        // Basic format check
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [
                'valid' => false,
                'message' => __('Please enter a valid email address.', 'formflow-pro'),
            ];
        }

        // Check for disposable email domains
        $disposableDomains = [
            'tempmail.com', 'throwaway.com', 'mailinator.com', '10minutemail.com',
            'guerrillamail.com', 'sharklasers.com', 'yopmail.com',
        ];

        $domain = substr($email, strpos($email, '@') + 1);
        if (in_array(strtolower($domain), $disposableDomains)) {
            return [
                'valid' => false,
                'message' => __('Please use a permanent email address.', 'formflow-pro'),
            ];
        }

        // Check for typos in common domains
        $commonDomains = [
            'gmail.com' => ['gmal.com', 'gmial.com', 'gnail.com', 'gmail.co'],
            'yahoo.com' => ['yaho.com', 'yahooo.com', 'yahoo.co'],
            'hotmail.com' => ['hotmal.com', 'hotmial.com', 'hotmail.co'],
            'outlook.com' => ['outlok.com', 'outloo.com'],
        ];

        foreach ($commonDomains as $correct => $typos) {
            if (in_array(strtolower($domain), $typos)) {
                return [
                    'valid' => false,
                    'message' => sprintf(
                        /* translators: %s: correct domain */
                        __('Did you mean @%s?', 'formflow-pro'),
                        $correct
                    ),
                    'suggestion' => str_replace('@' . $domain, '@' . $correct, $email),
                ];
            }
        }

        return ['valid' => true];
    }

    /**
     * Intelligent phone validation
     *
     * @param string $phone Phone number
     * @return array
     */
    private function validatePhoneIntelligent(string $phone): array
    {
        // Remove formatting
        $digits = preg_replace('/[^0-9]/', '', $phone);

        // Check length
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return [
                'valid' => false,
                'message' => __('Please enter a valid phone number.', 'formflow-pro'),
            ];
        }

        // Check for obviously fake numbers
        if (preg_match('/^0{5,}|1{5,}|2{5,}|3{5,}|4{5,}|5{5,}|6{5,}|7{5,}|8{5,}|9{5,}/', $digits)) {
            return [
                'valid' => false,
                'message' => __('Please enter a real phone number.', 'formflow-pro'),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Intelligent name validation
     *
     * @param string $name Name
     * @return array
     */
    private function validateNameIntelligent(string $name): array
    {
        // Check minimum length
        if (strlen(trim($name)) < 2) {
            return [
                'valid' => false,
                'message' => __('Please enter your full name.', 'formflow-pro'),
            ];
        }

        // Check for numbers in name
        if (preg_match('/\d/', $name)) {
            return [
                'valid' => false,
                'message' => __('Name should not contain numbers.', 'formflow-pro'),
            ];
        }

        // Check for obviously fake names
        $fakeNames = ['test', 'asdf', 'qwerty', 'xxx', 'aaa', 'abc'];
        if (in_array(strtolower(trim($name)), $fakeNames)) {
            return [
                'valid' => false,
                'message' => __('Please enter your real name.', 'formflow-pro'),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Intelligent address validation
     *
     * @param string $address Address
     * @return array
     */
    private function validateAddressIntelligent(string $address): array
    {
        // Check minimum length
        if (strlen(trim($address)) < 10) {
            return [
                'valid' => false,
                'message' => __('Please enter a complete address.', 'formflow-pro'),
            ];
        }

        return ['valid' => true];
    }

    /**
     * Classify submission
     *
     * @param array $submission Submission data
     * @param int $submissionId Submission ID
     * @return void
     */
    public function classifySubmission(array $submission, int $submissionId): void
    {
        if (!$this->isFeatureEnabled('document_classification') || !$this->provider) {
            return;
        }

        $formData = $submission['form_data'] ?? $submission['data'] ?? [];
        $content = implode(' ', array_values(array_filter($formData, 'is_string')));

        // Skip empty content
        if (empty(trim($content))) {
            return;
        }

        $classification = $this->classifyContent($content);

        if ($classification) {
            // Save classification
            update_post_meta($submissionId, '_formflow_classification', $classification);

            $this->logAIAction('submission_classified', [
                'submission_id' => $submissionId,
                'classification' => $classification,
            ]);
        }
    }

    /**
     * Classify content using AI
     *
     * @param string $content Content to classify
     * @return array|null
     */
    public function classifyContent(string $content): ?array
    {
        if (!$this->provider) {
            return null;
        }

        // Check cache
        $cacheKey = 'formflow_classify_' . md5($content);
        if ($this->config['cache_responses']) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $prompt = <<<PROMPT
Classify the following form submission content. Return a JSON object with:
- category: main category (inquiry, support, feedback, sales, complaint, other)
- priority: urgency level (low, medium, high)
- sentiment: (positive, neutral, negative)
- topics: array of detected topics

Content:
{$content}

Return ONLY the JSON object, no other text.
PROMPT;

        try {
            $response = $this->provider->complete($prompt, [
                'max_tokens' => 200,
                'temperature' => 0.3,
            ]);

            $classification = json_decode($response, true);

            if (!$classification) {
                return null;
            }

            // Cache result
            if ($this->config['cache_responses']) {
                set_transient($cacheKey, $classification, $this->config['cache_ttl']);
            }

            return $classification;
        } catch (\Exception $e) {
            $this->logAIAction('classification_error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get auto-suggestions for field
     *
     * @param string $fieldName Field name
     * @param string $partialValue Partial value typed
     * @param array $context Form context
     * @return array
     */
    public function getAutoSuggestions(string $fieldName, string $partialValue, array $context = []): array
    {
        if (!$this->isFeatureEnabled('auto_suggestions') || !$this->provider) {
            return [];
        }

        // Check cache
        $cacheKey = 'formflow_suggest_' . md5($fieldName . $partialValue);
        if ($this->config['cache_responses']) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $prompt = <<<PROMPT
Provide 3-5 autocomplete suggestions for a form field.
Field name: {$fieldName}
Current value: {$partialValue}
Context: {form_type: contact}

Return a JSON array of suggestion strings only.
PROMPT;

        try {
            $response = $this->provider->complete($prompt, [
                'max_tokens' => 100,
                'temperature' => 0.5,
            ]);

            $suggestions = json_decode($response, true);

            if (!is_array($suggestions)) {
                return [];
            }

            // Cache result
            if ($this->config['cache_responses']) {
                set_transient($cacheKey, $suggestions, 300); // 5 min cache
            }

            return $suggestions;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Analyze sentiment of text
     *
     * @param string $text Text to analyze
     * @return array|null
     */
    public function analyzeSentiment(string $text): ?array
    {
        if (!$this->isFeatureEnabled('sentiment_analysis') || !$this->provider) {
            return null;
        }

        // Check cache
        $cacheKey = 'formflow_sentiment_' . md5($text);
        if ($this->config['cache_responses']) {
            $cached = get_transient($cacheKey);
            if ($cached !== false) {
                return $cached;
            }
        }

        $prompt = <<<PROMPT
Analyze the sentiment of the following text. Return a JSON object with:
- sentiment: (positive, neutral, negative)
- score: confidence score 0-1
- emotions: array of detected emotions

Text:
{$text}

Return ONLY the JSON object.
PROMPT;

        try {
            $response = $this->provider->complete($prompt, [
                'max_tokens' => 100,
                'temperature' => 0.3,
            ]);

            $analysis = json_decode($response, true);

            if ($analysis && $this->config['cache_responses']) {
                set_transient($cacheKey, $analysis, $this->config['cache_ttl']);
            }

            return $analysis;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Test AI connection
     *
     * @return array
     */
    public function testConnection(): array
    {
        if (!$this->provider) {
            return [
                'success' => false,
                'message' => __('No AI provider configured.', 'formflow-pro'),
            ];
        }

        try {
            $response = $this->provider->complete('Say "Hello from FormFlow Pro!" in exactly those words.', [
                'max_tokens' => 20,
            ]);

            if (stripos($response, 'hello') !== false) {
                return [
                    'success' => true,
                    'message' => __('AI connection successful!', 'formflow-pro'),
                    'response' => $response,
                ];
            }

            return [
                'success' => false,
                'message' => __('Unexpected response from AI.', 'formflow-pro'),
                'response' => $response,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log AI action
     *
     * @param string $action Action name
     * @param array $data Action data
     * @return void
     */
    private function logAIAction(string $action, array $data = []): void
    {
        if (!$this->config['log_requests']) {
            return;
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'formflow_logs',
            [
                'level' => 'info',
                'context' => 'ai_service',
                'message' => $action,
                'data' => wp_json_encode($data),
                'created_at' => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s']
        );
    }

    /**
     * Sanitize data for logging (remove sensitive info)
     *
     * @param array $data Data to sanitize
     * @return array
     */
    private function sanitizeForLog(array $data): array
    {
        $sensitiveFields = ['password', 'card', 'cvv', 'ssn', 'credit', 'secret'];

        $sanitized = [];
        foreach ($data as $key => $value) {
            $isSensitive = false;
            foreach ($sensitiveFields as $field) {
                if (stripos($key, $field) !== false) {
                    $isSensitive = true;
                    break;
                }
            }

            $sanitized[$key] = $isSensitive ? '[REDACTED]' : $value;
        }

        return $sanitized;
    }
}
