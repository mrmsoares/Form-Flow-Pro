<?php

declare(strict_types=1);

/**
 * Local AI Provider
 *
 * Local heuristic-based AI features for offline operation.
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
 * Local AI Provider Class
 */
class LocalAIProvider implements AIProviderInterface
{
    /**
     * Spam keywords database
     *
     * @var array
     */
    private array $spamKeywords = [
        'high' => [
            'viagra', 'cialis', 'casino', 'lottery', 'winner', 'prize money',
            'nigerian prince', 'million dollars', 'bank transfer', 'urgent response',
            'click here now', 'act now', 'limited time', 'free money', 'make money fast',
            'work from home', 'double your income', 'get rich quick',
        ],
        'medium' => [
            'bitcoin', 'crypto', 'investment', 'opportunity', 'guaranteed',
            'no risk', 'special offer', 'congratulations', 'selected',
            'exclusive deal', 'discount', 'free gift',
        ],
        'low' => [
            'subscribe', 'newsletter', 'marketing', 'promotion',
            'limited offer', 'sale', 'buy now',
        ],
    ];

    /**
     * Sentiment words database
     *
     * @var array
     */
    private array $sentimentWords = [
        'positive' => [
            'great', 'excellent', 'amazing', 'wonderful', 'fantastic', 'love',
            'happy', 'pleased', 'satisfied', 'helpful', 'thank', 'appreciate',
            'perfect', 'awesome', 'brilliant', 'outstanding', 'recommend',
        ],
        'negative' => [
            'terrible', 'awful', 'horrible', 'worst', 'hate', 'angry',
            'disappointed', 'frustrated', 'annoyed', 'problem', 'issue',
            'broken', 'useless', 'waste', 'refund', 'complaint', 'never',
        ],
    ];

    /**
     * {@inheritdoc}
     */
    public function getId(): string
    {
        return 'local';
    }

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return __('Local AI (Offline)', 'formflow-pro');
    }

    /**
     * {@inheritdoc}
     */
    public function isConfigured(): bool
    {
        return true; // Always available
    }

    /**
     * {@inheritdoc}
     */
    public function complete(string $prompt, array $options = []): string
    {
        // Parse prompt to determine what's being asked
        $promptLower = strtolower($prompt);

        // Spam detection request
        if (strpos($promptLower, 'spam') !== false && strpos($promptLower, 'rate') !== false) {
            return $this->analyzeSpamLocal($prompt);
        }

        // Sentiment analysis request
        if (strpos($promptLower, 'sentiment') !== false) {
            return $this->analyzeSentimentLocal($prompt);
        }

        // Classification request
        if (strpos($promptLower, 'classify') !== false || strpos($promptLower, 'category') !== false) {
            return $this->classifyLocal($prompt);
        }

        // Default response
        return '{"error": "Local AI cannot process this request type. Please use OpenAI for advanced features."}';
    }

    /**
     * Analyze spam locally
     *
     * @param string $prompt Prompt containing content
     * @return string JSON score
     */
    private function analyzeSpamLocal(string $prompt): string
    {
        $score = 0;

        // Extract content from prompt
        $content = strtolower($prompt);

        // Check high-risk keywords
        foreach ($this->spamKeywords['high'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 0.3;
            }
        }

        // Check medium-risk keywords
        foreach ($this->spamKeywords['medium'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 0.15;
            }
        }

        // Check low-risk keywords
        foreach ($this->spamKeywords['low'] as $keyword) {
            if (strpos($content, $keyword) !== false) {
                $score += 0.05;
            }
        }

        // Check for excessive links
        $linkCount = preg_match_all('/https?:\/\//', $content);
        $score += min(0.3, $linkCount * 0.1);

        // Check for all caps
        $upperContent = preg_replace('/[^A-Z]/', '', $content);
        $totalAlpha = preg_replace('/[^a-zA-Z]/', '', $content);
        if (strlen($totalAlpha) > 0 && strlen($upperContent) / strlen($totalAlpha) > 0.5) {
            $score += 0.15;
        }

        return (string) min(1, $score);
    }

    /**
     * Analyze sentiment locally
     *
     * @param string $prompt Prompt containing text
     * @return string JSON sentiment result
     */
    private function analyzeSentimentLocal(string $prompt): string
    {
        $contentLower = strtolower($prompt);

        $positiveCount = 0;
        $negativeCount = 0;

        foreach ($this->sentimentWords['positive'] as $word) {
            $positiveCount += substr_count($contentLower, $word);
        }

        foreach ($this->sentimentWords['negative'] as $word) {
            $negativeCount += substr_count($contentLower, $word);
        }

        $total = $positiveCount + $negativeCount;

        if ($total === 0) {
            return wp_json_encode([
                'sentiment' => 'neutral',
                'score' => 0.5,
                'emotions' => [],
            ]);
        }

        $positiveRatio = $positiveCount / $total;

        if ($positiveRatio > 0.6) {
            $sentiment = 'positive';
            $score = $positiveRatio;
        } elseif ($positiveRatio < 0.4) {
            $sentiment = 'negative';
            $score = 1 - $positiveRatio;
        } else {
            $sentiment = 'neutral';
            $score = 0.5;
        }

        return wp_json_encode([
            'sentiment' => $sentiment,
            'score' => round($score, 2),
            'emotions' => $this->detectEmotions($contentLower),
        ]);
    }

    /**
     * Detect emotions in text
     *
     * @param string $text Text to analyze
     * @return array
     */
    private function detectEmotions(string $text): array
    {
        $emotions = [];
        $emotionKeywords = [
            'happy' => ['happy', 'joy', 'pleased', 'delighted', 'glad'],
            'angry' => ['angry', 'furious', 'mad', 'outraged', 'annoyed'],
            'sad' => ['sad', 'disappointed', 'unhappy', 'upset', 'depressed'],
            'excited' => ['excited', 'thrilled', 'eager', 'enthusiastic'],
            'confused' => ['confused', 'unclear', 'puzzled', "don't understand"],
            'grateful' => ['thank', 'grateful', 'appreciate', 'thanks'],
        ];

        foreach ($emotionKeywords as $emotion => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $emotions[] = $emotion;
                    break;
                }
            }
        }

        return array_unique($emotions);
    }

    /**
     * Classify content locally
     *
     * @param string $prompt Prompt containing content
     * @return string JSON classification
     */
    private function classifyLocal(string $prompt): string
    {
        $contentLower = strtolower($prompt);

        // Determine category
        $categories = [
            'inquiry' => ['question', 'how', 'what', 'when', 'where', 'why', 'can you', 'could you'],
            'support' => ['help', 'issue', 'problem', 'error', 'bug', "doesn't work", 'broken'],
            'feedback' => ['feedback', 'suggestion', 'review', 'opinion', 'think'],
            'sales' => ['price', 'cost', 'buy', 'purchase', 'quote', 'pricing', 'demo'],
            'complaint' => ['complaint', 'unhappy', 'disappointed', 'refund', 'cancel', 'worst'],
        ];

        $category = 'other';
        $maxScore = 0;

        foreach ($categories as $cat => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (strpos($contentLower, $keyword) !== false) {
                    $score++;
                }
            }
            if ($score > $maxScore) {
                $maxScore = $score;
                $category = $cat;
            }
        }

        // Determine priority
        $urgentKeywords = ['urgent', 'asap', 'immediately', 'emergency', 'critical'];
        $priority = 'medium';

        foreach ($urgentKeywords as $keyword) {
            if (strpos($contentLower, $keyword) !== false) {
                $priority = 'high';
                break;
            }
        }

        // Detect topics
        $topics = $this->detectTopics($contentLower);

        return wp_json_encode([
            'category' => $category,
            'priority' => $priority,
            'sentiment' => $this->quickSentiment($contentLower),
            'topics' => $topics,
        ]);
    }

    /**
     * Detect topics in content
     *
     * @param string $text Text to analyze
     * @return array
     */
    private function detectTopics(string $text): array
    {
        $topicKeywords = [
            'billing' => ['invoice', 'payment', 'charge', 'bill', 'subscription'],
            'technical' => ['error', 'bug', 'code', 'api', 'server', 'database'],
            'account' => ['account', 'login', 'password', 'profile', 'settings'],
            'shipping' => ['delivery', 'shipping', 'order', 'tracking', 'package'],
            'product' => ['product', 'feature', 'functionality', 'service'],
        ];

        $topics = [];

        foreach ($topicKeywords as $topic => $keywords) {
            foreach ($keywords as $keyword) {
                if (strpos($text, $keyword) !== false) {
                    $topics[] = $topic;
                    break;
                }
            }
        }

        return array_unique($topics);
    }

    /**
     * Quick sentiment check
     *
     * @param string $text Text to check
     * @return string
     */
    private function quickSentiment(string $text): string
    {
        $positive = 0;
        $negative = 0;

        foreach ($this->sentimentWords['positive'] as $word) {
            if (strpos($text, $word) !== false) {
                $positive++;
            }
        }

        foreach ($this->sentimentWords['negative'] as $word) {
            if (strpos($text, $word) !== false) {
                $negative++;
            }
        }

        if ($positive > $negative) {
            return 'positive';
        }
        if ($negative > $positive) {
            return 'negative';
        }
        return 'neutral';
    }

    /**
     * {@inheritdoc}
     */
    public function embed(string $text): array
    {
        // Local embeddings not supported
        // Return simple bag-of-words vector as fallback
        $words = str_word_count(strtolower($text), 1);
        $uniqueWords = array_unique($words);

        // Create a simple hash-based vector
        $vector = array_fill(0, 256, 0);

        foreach ($uniqueWords as $word) {
            $hash = crc32($word) % 256;
            $vector[$hash] += 1;
        }

        // Normalize
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $vector)));

        if ($magnitude > 0) {
            $vector = array_map(function ($v) use ($magnitude) {
                return $v / $magnitude;
            }, $vector);
        }

        return $vector;
    }

    /**
     * {@inheritdoc}
     */
    public function getModels(): array
    {
        return [
            [
                'id' => 'local',
                'name' => __('Local Heuristics', 'formflow-pro'),
                'description' => __('Rule-based analysis without API calls.', 'formflow-pro'),
                'context_length' => 0,
                'cost_per_1k' => 0,
            ],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function getUsage(): array
    {
        return [
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'total_tokens' => 0,
            'requests' => 0,
            'cost' => 0,
        ];
    }
}
