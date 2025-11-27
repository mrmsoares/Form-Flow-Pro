<?php

/**
 * Form Templates Page
 *
 * @package FormFlowPro
 * @since 2.4.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Available template categories
$categories = [
    'all' => __('All Templates', 'formflow-pro'),
    'contact' => __('Contact', 'formflow-pro'),
    'registration' => __('Registration', 'formflow-pro'),
    'surveys' => __('Surveys', 'formflow-pro'),
    'orders' => __('Orders & Payments', 'formflow-pro'),
    'appointments' => __('Appointments', 'formflow-pro'),
    'applications' => __('Applications', 'formflow-pro'),
];

// Pre-built templates
$templates = [
    [
        'id' => 'contact-simple',
        'name' => __('Simple Contact Form', 'formflow-pro'),
        'description' => __('Basic contact form with name, email, and message fields.', 'formflow-pro'),
        'category' => 'contact',
        'icon' => 'email-alt',
        'fields' => 4,
    ],
    [
        'id' => 'contact-advanced',
        'name' => __('Advanced Contact Form', 'formflow-pro'),
        'description' => __('Full contact form with company, phone, and subject selection.', 'formflow-pro'),
        'category' => 'contact',
        'icon' => 'email',
        'fields' => 8,
    ],
    [
        'id' => 'user-registration',
        'name' => __('User Registration', 'formflow-pro'),
        'description' => __('New user registration with profile fields.', 'formflow-pro'),
        'category' => 'registration',
        'icon' => 'admin-users',
        'fields' => 6,
    ],
    [
        'id' => 'event-registration',
        'name' => __('Event Registration', 'formflow-pro'),
        'description' => __('Event signup with session selection and dietary preferences.', 'formflow-pro'),
        'category' => 'registration',
        'icon' => 'calendar-alt',
        'fields' => 10,
    ],
    [
        'id' => 'customer-survey',
        'name' => __('Customer Satisfaction Survey', 'formflow-pro'),
        'description' => __('NPS-style survey with ratings and feedback.', 'formflow-pro'),
        'category' => 'surveys',
        'icon' => 'chart-bar',
        'fields' => 8,
    ],
    [
        'id' => 'feedback-form',
        'name' => __('Feedback Form', 'formflow-pro'),
        'description' => __('Product/service feedback collection form.', 'formflow-pro'),
        'category' => 'surveys',
        'icon' => 'format-chat',
        'fields' => 6,
    ],
    [
        'id' => 'order-form',
        'name' => __('Order Form', 'formflow-pro'),
        'description' => __('Product order form with quantity and payment.', 'formflow-pro'),
        'category' => 'orders',
        'icon' => 'cart',
        'fields' => 12,
    ],
    [
        'id' => 'donation-form',
        'name' => __('Donation Form', 'formflow-pro'),
        'description' => __('Charity donation form with amount selection.', 'formflow-pro'),
        'category' => 'orders',
        'icon' => 'heart',
        'fields' => 7,
    ],
    [
        'id' => 'appointment-booking',
        'name' => __('Appointment Booking', 'formflow-pro'),
        'description' => __('Service appointment scheduling form.', 'formflow-pro'),
        'category' => 'appointments',
        'icon' => 'clock',
        'fields' => 8,
    ],
    [
        'id' => 'job-application',
        'name' => __('Job Application', 'formflow-pro'),
        'description' => __('Employment application with resume upload.', 'formflow-pro'),
        'category' => 'applications',
        'icon' => 'businessman',
        'fields' => 15,
    ],
    [
        'id' => 'quote-request',
        'name' => __('Quote Request', 'formflow-pro'),
        'description' => __('Service quote request form for businesses.', 'formflow-pro'),
        'category' => 'contact',
        'icon' => 'media-text',
        'fields' => 10,
    ],
    [
        'id' => 'newsletter-signup',
        'name' => __('Newsletter Signup', 'formflow-pro'),
        'description' => __('Simple email newsletter subscription form.', 'formflow-pro'),
        'category' => 'registration',
        'icon' => 'email-alt2',
        'fields' => 3,
    ],
];

$active_category = isset($_GET['category']) ? sanitize_text_field($_GET['category']) : 'all';

?>

<div class="wrap formflow-admin formflow-templates">
    <h1 class="wp-heading-inline">
        <span class="dashicons dashicons-layout"></span>
        <?php esc_html_e('Form Templates', 'formflow-pro'); ?>
    </h1>

    <hr class="wp-header-end">

    <!-- Category Filter -->
    <div class="category-filter" style="margin: 20px 0; display: flex; gap: 10px; flex-wrap: wrap;">
        <?php foreach ($categories as $cat_slug => $cat_name) : ?>
            <a href="<?php echo esc_url(admin_url('admin.php?page=formflow-templates&category=' . $cat_slug)); ?>"
               class="button <?php echo $active_category === $cat_slug ? 'button-primary' : ''; ?>">
                <?php echo esc_html($cat_name); ?>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Templates Grid -->
    <div class="templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
        <?php foreach ($templates as $template) : ?>
            <?php if ($active_category === 'all' || $active_category === $template['category']) : ?>
                <div class="template-card card" style="padding: 0; overflow: hidden;">
                    <div class="template-preview" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 40px 20px; text-align: center;">
                        <span class="dashicons dashicons-<?php echo esc_attr($template['icon']); ?>" style="font-size: 48px; width: 48px; height: 48px; color: rgba(255,255,255,0.9);"></span>
                    </div>
                    <div class="template-info" style="padding: 20px;">
                        <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($template['name']); ?></h3>
                        <p style="color: #666; margin: 0 0 15px 0; font-size: 13px;">
                            <?php echo esc_html($template['description']); ?>
                        </p>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span style="color: #999; font-size: 12px;">
                                <span class="dashicons dashicons-forms" style="font-size: 14px; width: 14px; height: 14px;"></span>
                                <?php printf(esc_html__('%d fields', 'formflow-pro'), $template['fields']); ?>
                            </span>
                            <div>
                                <a href="#" class="button preview-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                    <?php esc_html_e('Preview', 'formflow-pro'); ?>
                                </a>
                                <a href="#" class="button button-primary use-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                    <?php esc_html_e('Use', 'formflow-pro'); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <!-- Custom Templates Section -->
    <div style="margin-top: 40px;">
        <h2>
            <span class="dashicons dashicons-saved" style="color: #0073aa;"></span>
            <?php esc_html_e('Your Saved Templates', 'formflow-pro'); ?>
        </h2>

        <?php
        $custom_templates = get_option('formflow_custom_templates', []);
        ?>

        <?php if (empty($custom_templates)) : ?>
            <div class="card" style="padding: 40px; text-align: center;">
                <p style="color: #666; margin: 0;">
                    <?php esc_html_e('No custom templates saved yet. Create a form and save it as a template to see it here.', 'formflow-pro'); ?>
                </p>
            </div>
        <?php else : ?>
            <div class="templates-grid" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;">
                <?php foreach ($custom_templates as $template) : ?>
                    <div class="template-card card" style="padding: 20px;">
                        <h3 style="margin: 0 0 10px 0;"><?php echo esc_html($template['name']); ?></h3>
                        <p style="color: #666; margin: 0 0 15px 0; font-size: 13px;">
                            <?php echo esc_html($template['description'] ?? __('Custom template', 'formflow-pro')); ?>
                        </p>
                        <div style="display: flex; gap: 10px;">
                            <a href="#" class="button button-primary use-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                <?php esc_html_e('Use', 'formflow-pro'); ?>
                            </a>
                            <a href="#" class="button delete-template" data-template="<?php echo esc_attr($template['id']); ?>">
                                <?php esc_html_e('Delete', 'formflow-pro'); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
