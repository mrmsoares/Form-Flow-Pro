<?php

declare(strict_types=1);

/**
 * FormFlow Form Widget for Elementor
 *
 * Widget to display FormFlow forms in Elementor pages.
 *
 * @package FormFlowPro\Integrations\Elementor\Widgets
 * @since 2.0.0
 */

namespace FormFlowPro\Integrations\Elementor\Widgets;

use Elementor\Controls_Manager;
use Elementor\Group_Control_Typography;
use Elementor\Group_Control_Border;
use Elementor\Group_Control_Box_Shadow;
use Elementor\Core\Kits\Documents\Tabs\Global_Typography;
use Elementor\Core\Kits\Documents\Tabs\Global_Colors;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Form Widget Class
 */
class Form_Widget extends Widget_Base
{
    /**
     * Get widget name
     *
     * @return string Widget name.
     */
    public function get_name(): string
    {
        return 'formflow-form';
    }

    /**
     * Get widget title
     *
     * @return string Widget title.
     */
    public function get_title(): string
    {
        return __('FormFlow Form', 'formflow-pro');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon.
     */
    public function get_icon(): string
    {
        return 'eicon-mail';
    }

    /**
     * Register widget controls
     *
     * @return void
     */
    protected function register_controls(): void
    {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content controls
     *
     * @return void
     */
    private function register_content_controls(): void
    {
        // Form Selection Section
        $this->start_controls_section(
            'section_form',
            [
                'label' => __('Form', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'form_id',
            [
                'label' => __('Select Form', 'formflow-pro'),
                'type' => Controls_Manager::SELECT,
                'options' => $this->get_available_forms(),
                'default' => '',
                'description' => __('Choose the form you want to display', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'show_title',
            [
                'label' => __('Show Form Title', 'formflow-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'formflow-pro'),
                'label_off' => __('Hide', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_description',
            [
                'label' => __('Show Form Description', 'formflow-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'formflow-pro'),
                'label_off' => __('Hide', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'ajax_submit',
            [
                'label' => __('AJAX Submission', 'formflow-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'formflow-pro'),
                'label_off' => __('No', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Submit form without page reload', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'enable_autentique',
            [
                'label' => __('Enable Digital Signature', 'formflow-pro'),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'formflow-pro'),
                'label_off' => __('No', 'formflow-pro'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Require Autentique digital signature', 'formflow-pro'),
            ]
        );

        $this->end_controls_section();

        // Success Message Section
        $this->start_controls_section(
            'section_messages',
            [
                'label' => __('Messages', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label' => __('Success Message', 'formflow-pro'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Thank you! Your submission has been received.', 'formflow-pro'),
                'placeholder' => __('Enter your success message', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'error_message',
            [
                'label' => __('Error Message', 'formflow-pro'),
                'type' => Controls_Manager::TEXTAREA,
                'default' => __('Something went wrong. Please try again.', 'formflow-pro'),
                'placeholder' => __('Enter your error message', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'redirect_url',
            [
                'label' => __('Redirect URL', 'formflow-pro'),
                'type' => Controls_Manager::URL,
                'placeholder' => __('https://your-link.com', 'formflow-pro'),
                'description' => __('Redirect to this URL after successful submission (leave empty to stay on page)', 'formflow-pro'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     *
     * @return void
     */
    private function register_style_controls(): void
    {
        // Form Container Style
        $this->start_controls_section(
            'section_form_style',
            [
                'label' => __('Form Container', 'formflow-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'form_padding',
            [
                'label' => __('Padding', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form-container' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'form_border',
                'selector' => '{{WRAPPER}} .formflow-form-container',
            ]
        );

        $this->add_control(
            'form_border_radius',
            [
                'label' => __('Border Radius', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'form_box_shadow',
                'selector' => '{{WRAPPER}} .formflow-form-container',
            ]
        );

        $this->add_control(
            'form_background_color',
            [
                'label' => __('Background Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form-container' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Labels Style
        $this->start_controls_section(
            'section_labels_style',
            [
                'label' => __('Labels', 'formflow-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form label' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'label_typography',
                'selector' => '{{WRAPPER}} .formflow-form label',
            ]
        );

        $this->add_responsive_control(
            'label_spacing',
            [
                'label' => __('Spacing', 'formflow-pro'),
                'type' => Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form label' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Input Fields Style
        $this->start_controls_section(
            'section_input_style',
            [
                'label' => __('Input Fields', 'formflow-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'input_text_color',
            [
                'label' => __('Text Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form input[type="text"],
                     {{WRAPPER}} .formflow-form input[type="email"],
                     {{WRAPPER}} .formflow-form input[type="tel"],
                     {{WRAPPER}} .formflow-form textarea,
                     {{WRAPPER}} .formflow-form select' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'input_placeholder_color',
            [
                'label' => __('Placeholder Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form input::placeholder' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .formflow-form textarea::placeholder' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'input_typography',
                'selector' => '{{WRAPPER}} .formflow-form input, {{WRAPPER}} .formflow-form textarea, {{WRAPPER}} .formflow-form select',
            ]
        );

        $this->add_control(
            'input_background_color',
            [
                'label' => __('Background Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form input[type="text"],
                     {{WRAPPER}} .formflow-form input[type="email"],
                     {{WRAPPER}} .formflow-form input[type="tel"],
                     {{WRAPPER}} .formflow-form textarea,
                     {{WRAPPER}} .formflow-form select' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'input_padding',
            [
                'label' => __('Padding', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form input[type="text"],
                     {{WRAPPER}} .formflow-form input[type="email"],
                     {{WRAPPER}} .formflow-form input[type="tel"],
                     {{WRAPPER}} .formflow-form textarea,
                     {{WRAPPER}} .formflow-form select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'input_border',
                'selector' => '{{WRAPPER}} .formflow-form input, {{WRAPPER}} .formflow-form textarea, {{WRAPPER}} .formflow-form select',
            ]
        );

        $this->add_control(
            'input_border_radius',
            [
                'label' => __('Border Radius', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form input, {{WRAPPER}} .formflow-form textarea, {{WRAPPER}} .formflow-form select' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Submit Button Style
        $this->start_controls_section(
            'section_button_style',
            [
                'label' => __('Submit Button', 'formflow-pro'),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('button_style_tabs');

        // Normal State
        $this->start_controls_tab(
            'button_normal',
            [
                'label' => __('Normal', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'button_text_color',
            [
                'label' => __('Text Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_background_color',
            [
                'label' => __('Background Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        // Hover State
        $this->start_controls_tab(
            'button_hover',
            [
                'label' => __('Hover', 'formflow-pro'),
            ]
        );

        $this->add_control(
            'button_hover_text_color',
            [
                'label' => __('Text Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'button_hover_background_color',
            [
                'label' => __('Background Color', 'formflow-pro'),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]:hover' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->add_group_control(
            Group_Control_Typography::get_type(),
            [
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .formflow-form button[type="submit"]',
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'button_padding',
            [
                'label' => __('Padding', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Border::get_type(),
            [
                'name' => 'button_border',
                'selector' => '{{WRAPPER}} .formflow-form button[type="submit"]',
            ]
        );

        $this->add_control(
            'button_border_radius',
            [
                'label' => __('Border Radius', 'formflow-pro'),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .formflow-form button[type="submit"]' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'button_box_shadow',
                'selector' => '{{WRAPPER}} .formflow-form button[type="submit"]',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     *
     * @return void
     */
    protected function render(): void
    {
        $settings = $this->get_settings_for_display();
        $form_id = $settings['form_id'] ?? '';

        if (empty($form_id)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo esc_html__('Please select a form to display.', 'formflow-pro');
                echo '</div>';
            }
            return;
        }

        // Get form data
        global $wpdb;
        $form = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}formflow_forms WHERE id = %d",
            $form_id
        ));

        if (!$form) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-danger">';
                echo esc_html__('Form not found.', 'formflow-pro');
                echo '</div>';
            }
            return;
        }

        $form_fields = json_decode($form->fields, true);
        $ajax_class = $settings['ajax_submit'] === 'yes' ? 'formflow-ajax-form' : '';
        ?>

        <div class="formflow-form-container">
            <?php if ($settings['show_title'] === 'yes' && !empty($form->name)) : ?>
                <h2 class="formflow-form-title"><?php echo esc_html($form->name); ?></h2>
            <?php endif; ?>

            <?php if ($settings['show_description'] === 'yes' && !empty($form->description)) : ?>
                <div class="formflow-form-description"><?php echo wp_kses_post($form->description); ?></div>
            <?php endif; ?>

            <form class="formflow-form <?php echo esc_attr($ajax_class); ?>"
                  data-form-id="<?php echo esc_attr($form_id); ?>"
                  data-autentique="<?php echo esc_attr($settings['enable_autentique']); ?>"
                  data-redirect="<?php echo esc_url($settings['redirect_url']['url'] ?? ''); ?>"
                  data-success-message="<?php echo esc_attr($settings['success_message']); ?>"
                  data-error-message="<?php echo esc_attr($settings['error_message']); ?>">

                <?php wp_nonce_field('formflow_submit_form', 'formflow_nonce'); ?>

                <input type="hidden" name="action" value="formflow_submit_form">
                <input type="hidden" name="form_id" value="<?php echo esc_attr($form_id); ?>">

                <div class="formflow-form-fields">
                    <?php foreach ($form_fields as $field) : ?>
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
    }

    /**
     * Render individual form field
     *
     * @param array $field Field configuration.
     * @return void
     */
    private function render_field(array $field): void
    {
        $type = $field['type'] ?? 'text';
        $label = $field['label'] ?? '';
        $name = $field['name'] ?? '';
        $required = isset($field['required']) && $field['required'] ? 'required' : '';
        $placeholder = $field['placeholder'] ?? '';

        ?>
        <div class="formflow-field formflow-field-<?php echo esc_attr($type); ?>">
            <?php if (!empty($label)) : ?>
                <label for="<?php echo esc_attr($name); ?>">
                    <?php echo esc_html($label); ?>
                    <?php if ($required) : ?>
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
                        <?php echo esc_attr($required); ?>
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
                        <?php echo esc_attr($required); ?>
                    >
                        <option value=""><?php echo esc_html__('Select...', 'formflow-pro'); ?></option>
                        <?php foreach ($options as $option) : ?>
                            <option value="<?php echo esc_attr($option); ?>">
                                <?php echo esc_html($option); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php
                    break;

                default:
                    ?>
                    <input
                        type="<?php echo esc_attr($type); ?>"
                        name="<?php echo esc_attr($name); ?>"
                        id="<?php echo esc_attr($name); ?>"
                        placeholder="<?php echo esc_attr($placeholder); ?>"
                        <?php echo esc_attr($required); ?>
                    >
                    <?php
                    break;
            }
            ?>
        </div>
        <?php
    }

    /**
     * Get available forms
     *
     * @return array Available forms.
     */
    private function get_available_forms(): array
    {
        global $wpdb;

        $forms = $wpdb->get_results(
            "SELECT id, name FROM {$wpdb->prefix}formflow_forms WHERE status = 'active' ORDER BY name ASC"
        );

        $options = ['' => __('Select a form', 'formflow-pro')];

        if ($forms) {
            foreach ($forms as $form) {
                $options[$form->id] = $form->name;
            }
        }

        return $options;
    }
}
