<?php
/**
 * Tests for LocalAIProvider class.
 */

namespace FormFlowPro\Tests\Unit\AI;

use FormFlowPro\Tests\TestCase;
use FormFlowPro\AI\LocalAIProvider;

class LocalAIProviderTest extends TestCase
{
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->provider = new LocalAIProvider();
    }

    public function test_get_id_returns_local()
    {
        $this->assertEquals('local', $this->provider->getId());
    }

    public function test_get_name_returns_local_ai()
    {
        $name = $this->provider->getName();

        $this->assertStringContainsString('Local', $name);
    }

    public function test_is_configured_always_returns_true()
    {
        $this->assertTrue($this->provider->isConfigured());
    }

    public function test_complete_detects_spam_analysis_request()
    {
        $prompt = 'Analyze the following form submission for spam. Rate from 0 to 1. viagra casino';

        $result = $this->provider->complete($prompt);

        $this->assertIsString($result);
        $score = (float) $result;
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(1, $score);
    }

    public function test_complete_analyzes_spam_with_high_risk_keywords()
    {
        $prompt = 'Analyze spam rate: viagra cialis casino lottery winner prize money';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_complete_analyzes_spam_with_medium_risk_keywords()
    {
        $prompt = 'Analyze spam rate: bitcoin crypto investment opportunity guaranteed';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0.2, $score);
    }

    public function test_complete_analyzes_spam_with_low_risk_keywords()
    {
        $prompt = 'Analyze spam rate: subscribe newsletter marketing promotion';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0, $score);
    }

    public function test_complete_analyzes_spam_with_links()
    {
        $prompt = 'Analyze spam rate: Visit https://spam1.com and https://spam2.com and https://spam3.com';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0.2, $score);
    }

    public function test_complete_analyzes_spam_with_all_caps()
    {
        $prompt = 'Analyze spam rate: THIS IS ALL CAPS CONTENT THAT LOOKS SPAMMY';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0, $score);
    }

    public function test_complete_analyzes_clean_content_as_low_spam()
    {
        $prompt = 'Analyze spam rate: I would like to inquire about your services. Thank you.';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertLessThan(0.3, $score);
    }

    public function test_complete_detects_sentiment_analysis_request()
    {
        $prompt = 'Analyze the sentiment of the following text: I love this product!';

        $result = $this->provider->complete($prompt);

        $this->assertJson($result);
        $data = json_decode($result, true);
        $this->assertArrayHasKey('sentiment', $data);
        $this->assertArrayHasKey('score', $data);
        $this->assertArrayHasKey('emotions', $data);
    }

    public function test_complete_analyzes_positive_sentiment()
    {
        $prompt = 'Analyze sentiment: This is great! I love it. Excellent and wonderful product. Happy customer.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('positive', $data['sentiment']);
        $this->assertGreaterThan(0.5, $data['score']);
    }

    public function test_complete_analyzes_negative_sentiment()
    {
        $prompt = 'Analyze sentiment: This is terrible. Worst product ever. Hate it. Disappointed and frustrated.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('negative', $data['sentiment']);
        $this->assertGreaterThan(0.5, $data['score']);
    }

    public function test_complete_analyzes_neutral_sentiment()
    {
        $prompt = 'Analyze sentiment: The product arrived on time. It works as described.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('neutral', $data['sentiment']);
    }

    public function test_complete_detects_emotions_in_sentiment()
    {
        $prompt = 'Analyze sentiment: I am so happy and grateful for your help. Thank you!';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertIsArray($data['emotions']);
        $this->assertContains('happy', $data['emotions']);
        $this->assertContains('grateful', $data['emotions']);
    }

    public function test_complete_detects_classification_request()
    {
        $prompt = 'Classify the following form submission content: I need help with a technical issue';

        $result = $this->provider->complete($prompt);

        $this->assertJson($result);
        $data = json_decode($result, true);
        $this->assertArrayHasKey('category', $data);
        $this->assertArrayHasKey('priority', $data);
        $this->assertArrayHasKey('sentiment', $data);
        $this->assertArrayHasKey('topics', $data);
    }

    public function test_complete_classifies_inquiry_category()
    {
        $prompt = 'Classify category: I have a question about how this works. What can you tell me?';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('inquiry', $data['category']);
    }

    public function test_complete_classifies_support_category()
    {
        $prompt = 'Classify category: I need help with an issue. Something is broken and not working.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('support', $data['category']);
    }

    public function test_complete_classifies_feedback_category()
    {
        $prompt = 'Classify category: Here is my feedback and suggestion for improvement.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('feedback', $data['category']);
    }

    public function test_complete_classifies_sales_category()
    {
        $prompt = 'Classify category: What is the price and cost? I want to buy and get a quote.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('sales', $data['category']);
    }

    public function test_complete_classifies_complaint_category()
    {
        $prompt = 'Classify category: I am very unhappy and disappointed. I want a refund. Worst service.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('complaint', $data['category']);
    }

    public function test_complete_classifies_other_category()
    {
        $prompt = 'Classify category: Random text without specific indicators.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('other', $data['category']);
    }

    public function test_complete_detects_high_priority()
    {
        $prompt = 'Classify: This is urgent! Need immediate help ASAP. Critical emergency.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('high', $data['priority']);
    }

    public function test_complete_detects_medium_priority()
    {
        $prompt = 'Classify: I need some help with this issue when you have time.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('medium', $data['priority']);
    }

    public function test_complete_detects_topics()
    {
        $prompt = 'Classify: I have a billing issue with my invoice and payment.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertIsArray($data['topics']);
        $this->assertContains('billing', $data['topics']);
    }

    public function test_complete_detects_technical_topic()
    {
        $prompt = 'Classify: There is an error in the code and API is not working.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertContains('technical', $data['topics']);
    }

    public function test_complete_detects_account_topic()
    {
        $prompt = 'Classify: I cannot login to my account. Need to reset password.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertContains('account', $data['topics']);
    }

    public function test_complete_detects_shipping_topic()
    {
        $prompt = 'Classify: Where is my order? Need tracking for delivery.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertContains('shipping', $data['topics']);
    }

    public function test_complete_detects_product_topic()
    {
        $prompt = 'Classify: Tell me about the product features and functionality.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertContains('product', $data['topics']);
    }

    public function test_complete_detects_multiple_topics()
    {
        $prompt = 'Classify: Error with billing invoice and account login issue.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertGreaterThan(1, count($data['topics']));
    }

    public function test_complete_returns_error_for_unknown_request()
    {
        $prompt = 'Do something completely different that is not supported.';

        $result = $this->provider->complete($prompt);

        $this->assertJson($result);
        $data = json_decode($result, true);
        $this->assertArrayHasKey('error', $data);
    }

    public function test_embed_generates_vector()
    {
        $result = $this->provider->embed('Test text for embedding');

        $this->assertIsArray($result);
        $this->assertCount(256, $result);
    }

    public function test_embed_normalizes_vector()
    {
        $result = $this->provider->embed('Test text');

        // Check that vector is normalized (magnitude should be 1)
        $magnitude = sqrt(array_sum(array_map(function ($v) {
            return $v * $v;
        }, $result)));

        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function test_embed_creates_different_vectors_for_different_text()
    {
        $vector1 = $this->provider->embed('First text');
        $vector2 = $this->provider->embed('Completely different text');

        $this->assertNotEquals($vector1, $vector2);
    }

    public function test_embed_handles_empty_text()
    {
        $result = $this->provider->embed('');

        $this->assertIsArray($result);
        $this->assertCount(256, $result);
    }

    public function test_get_models_returns_local_model()
    {
        $models = $this->provider->getModels();

        $this->assertIsArray($models);
        $this->assertCount(1, $models);
        $this->assertEquals('local', $models[0]['id']);
        $this->assertArrayHasKey('name', $models[0]);
        $this->assertArrayHasKey('description', $models[0]);
        $this->assertEquals(0, $models[0]['cost_per_1k']);
    }

    public function test_get_usage_returns_zero_usage()
    {
        $usage = $this->provider->getUsage();

        $this->assertIsArray($usage);
        $this->assertEquals(0, $usage['prompt_tokens']);
        $this->assertEquals(0, $usage['completion_tokens']);
        $this->assertEquals(0, $usage['total_tokens']);
        $this->assertEquals(0, $usage['requests']);
        $this->assertEquals(0, $usage['cost']);
    }

    public function test_sentiment_includes_multiple_emotions()
    {
        $prompt = 'Analyze sentiment: I am happy but also confused and a bit sad. Thanks for help though!';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertGreaterThan(1, count($data['emotions']));
    }

    public function test_spam_score_never_exceeds_one()
    {
        $prompt = 'Analyze spam rate: ' . str_repeat('viagra casino lottery ', 100);

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertLessThanOrEqual(1, $score);
    }

    public function test_spam_score_never_below_zero()
    {
        $prompt = 'Analyze spam rate: Clean content';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThanOrEqual(0, $score);
    }

    public function test_classification_includes_sentiment()
    {
        $prompt = 'Classify: I am so frustrated with this terrible service!';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('negative', $data['sentiment']);
    }

    public function test_embed_same_text_produces_same_vector()
    {
        $vector1 = $this->provider->embed('Consistent text');
        $vector2 = $this->provider->embed('Consistent text');

        $this->assertEquals($vector1, $vector2);
    }

    public function test_complete_handles_mixed_case_keywords()
    {
        $prompt = 'Analyze spam rate: VIAGRA CaSiNo LoTtErY';

        $result = $this->provider->complete($prompt);
        $score = (float) $result;

        $this->assertGreaterThan(0.5, $score);
    }

    public function test_sentiment_handles_no_emotional_words()
    {
        $prompt = 'Analyze sentiment: The meeting is at 3pm on Tuesday.';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        $this->assertEquals('neutral', $data['sentiment']);
        $this->assertEquals(0.5, $data['score']);
    }

    public function test_classification_detects_unique_topics()
    {
        $prompt = 'Classify: billing billing invoice invoice payment';

        $result = $this->provider->complete($prompt);
        $data = json_decode($result, true);

        // Should only include "billing" once despite multiple mentions
        $this->assertCount(1, $data['topics']);
    }
}
