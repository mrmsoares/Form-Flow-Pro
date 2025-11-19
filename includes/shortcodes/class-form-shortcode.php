<?php

declare(strict_types=1);

namespace FormFlowPro\Shortcodes;

if (!defined('ABSPATH')) exit;

/**
 * Form Shortcode System
 * Usage: [formflow id="1"] or [formflow id="1" title="false"]
 */
class Form_Shortcode
{
    public function __construct()
    {
        add_shortcode('formflow', [$this, 'render_form']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Render form shortcode
     */
    public function render_form($atts): string
    {
        $atts = shortcode_atts([
            'id' => '',
            'title' => 'true',
            'description' => 'true',
            'ajax' => 'true',
            'redirect' => '',
            'class' => '',
        ], $atts);

        $form_id = intval($atts['id']);

        if (!$form_id) {
            return '<div class="formflow-error">' . __('Please specify a form ID', 'formflow-pro') . '</div>';
        }

        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d AND status = 'active'",
            $form_id
        ));

        if (!$form) {
            return '<div class="formflow-error">' . __('Form not found or inactive', 'formflow-pro') . '</div>';
        }

        $form_fields = json_decode($form->fields, true);
        $show_title = filter_var($atts['title'], FILTER_VALIDATE_BOOLEAN);
        $show_description = filter_var($atts['description'], FILTER_VALIDATE_BOOLEAN);
        $ajax_enabled = filter_var($atts['ajax'], FILTER_VALIDATE_BOOLEAN);

        // Enqueue scripts for this form
        wp_enqueue_script('formflow-frontend');
        wp_enqueue_style('formflow-frontend');

        ob_start();
        ?>
        <div class="formflow-shortcode-wrapper <?php echo esc_attr($atts['class']); ?>">
            <?php if ($show_title && !empty($form->name)): ?>
                <h2 class="formflow-form-title"><?php echo esc_html($form->name); ?></h2>
            <?php endif; ?>

            <?php if ($show_description && !empty($form->description)): ?>
                <div class="formflow-form-description"><?php echo wp_kses_post($form->description); ?></div>
            <?php endif; ?>

            <form class="formflow-form <?php echo $ajax_enabled ? 'formflow-ajax-form' : ''; ?>"
                  method="post"
                  data-form-id="<?php echo esc_attr($form_id); ?>"
                  data-redirect="<?php echo esc_url($atts['redirect']); ?>"
                  action="<?php echo esc_url(admin_url('admin-post.php')); ?>">

                <?php wp_nonce_field('formflow_submit_form', 'formflow_nonce'); ?>
                <input type="hidden" name="action" value="formflow_submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">

                <div class="formflow-form-fields">
                    <?php foreach ($form_fields as $field): ?>
                        <?php $this->render_field($field); ?>
                    <?php endforeach; ?>
                </div>

                <div class="formflow-form-submit">
                    <button type="submit" class="formflow-submit-button">
                        <?php echo esc_html__('Submit', 'formflow-pro'); ?>
                    </button>
                </div>

                <div class="formflow-form-messages"></div>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render individual field
     */
    private function render_field(array $field): void
    {
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $name = $field['name'] ?? '';
        $required = isset($field['required']) && $field['required'];
        $placeholder = $field['placeholder'] ?? '';
        $description = $field['description'] ?? '';

        ?>
        <div class="formflow-field formflow-field-<?php echo esc_attr($type); ?>">
            <?php if (!empty($label)): ?>
                <label for="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($label); ?>
                    <?php if ($required): ?>
                        <span class="required">*</span>
                    <?php endif; ?>
                </label>
            <?php endif; ?>

            <?php
            switch ($type) {
                case 'textarea':
                    ?>
                    <textarea
                        name="<?php echo esc_attr($name); ?>"
                        id="<?php echo esc_attr($name); ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                        rows="5"
                    ></textarea>
                    <?php
                    break;

                case 'select':
                    $options = $field['options'] ?? [];
                    ?>
                    <select
                        name="<?php echo esc_attr($name); ?>"
                        id="<?php echo esc_attr($name); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                    >
                        <option value=""><?php esc_html_e('Select...', 'formflow-pro'); ?></option>
                        <?php foreach ($options as $option): ?>
                            <option value="<?php echo esc_attr($option); ?>">
                                <?php echo esc_html($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                case 'checkbox':
                    ?>
                    <label class="formflow-checkbox-label">
                        <input
                            type="checkbox"
                            name="<?php echo esc_attr($name); ?>"
                            id="<?php echo esc_attr($name); ?>"
                            value="1"
                            <?php echo $required ? 'required' : ''; ?>
                        >
                        <span><?php echo esc_html($description); ?></span>
                    </label>
                    <?php
                    break;

                case 'radio':
                    $options = $field['options'] ?? [];
                    ?>
                    <div class="formflow-radio-group">
                        <?php foreach ($options as $option): ?>
                            <label class="formflow-radio-label">
                                <input
                                    type="radio"
                                    name="<?php echo esc_attr($name); ?>"
                                    value="<?php echo esc_attr($option); ?>"
                                    <?php echo $required ? 'required' : ''; ?>
                                >
                                <span><?php echo esc_html($option); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <?php
                    break;

                default:
                    ?>
                    <input
                        type="<?php echo esc_attr($type); ?>"
                        name="<?php echo esc_attr($name); ?>"
                        id="<?php echo esc_attr($name); ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php echo $required ? 'required' : ''; ?>
                    >
                    <?php
                    break;
            }
            ?>

            <?php if (!empty($description) && $type !== 'checkbox'): ?>
                <p class="formflow-field-description"><?php echo esc_html($description); ?></p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts(): void
    {
        wp_register_style(
            'formflow-frontend',
            FORMFLOW_URL . 'assets/css/elementor-style.min.css',
            [],
            FORMFLOW_VERSION
        );

        wp_register_script(
            'formflow-frontend',
            FORMFLOW_URL . 'assets/js/elementor.min.js',
            ['jquery'],
            FORMFLOW_VERSION,
            true
        );

        wp_localize_script('formflow-frontend', 'formflowData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('formflow_nonce'),
            'strings' => [
                'error' => __('An error occurred. Please try again.', 'formflow-pro'),
                'success' => __('Form submitted successfully!', 'formflow-pro'),
            ],
        ]);
    }
}
