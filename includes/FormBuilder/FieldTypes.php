<?php
/**
 * FormFlow Pro - Advanced Field Types Library
 *
 * Provides 50+ custom field types with validation, conditional logic,
 * and advanced rendering capabilities.
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
 * Field Type Interface
 */
interface FieldTypeInterface
{
    public function getType(): string;
    public function getLabel(): string;
    public function getCategory(): string;
    public function getIcon(): string;
    public function getDefaultSettings(): array;
    public function render(array $field, $value = null): string;
    public function validate($value, array $field): array;
    public function sanitize($value, array $field);
    public function getSchema(): array;
}

/**
 * Abstract Base Field Type
 */
abstract class AbstractFieldType implements FieldTypeInterface
{
    protected string $type;
    protected string $label;
    protected string $category;
    protected string $icon;

    public function getType(): string
    {
        return $this->type;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function getIcon(): string
    {
        return $this->icon;
    }

    public function getDefaultSettings(): array
    {
        return [
            'label' => '',
            'placeholder' => '',
            'description' => '',
            'required' => false,
            'readonly' => false,
            'disabled' => false,
            'css_class' => '',
            'css_id' => '',
            'conditional_logic' => [],
            'default_value' => '',
        ];
    }

    protected function getBaseAttributes(array $field): string
    {
        $attrs = [];

        if (!empty($field['name'])) {
            $attrs[] = 'name="' . esc_attr($field['name']) . '"';
        }

        if (!empty($field['css_id'])) {
            $attrs[] = 'id="' . esc_attr($field['css_id']) . '"';
        } else {
            $attrs[] = 'id="field_' . esc_attr($field['id'] ?? uniqid()) . '"';
        }

        if (!empty($field['placeholder'])) {
            $attrs[] = 'placeholder="' . esc_attr($field['placeholder']) . '"';
        }

        if (!empty($field['required'])) {
            $attrs[] = 'required';
        }

        if (!empty($field['readonly'])) {
            $attrs[] = 'readonly';
        }

        if (!empty($field['disabled'])) {
            $attrs[] = 'disabled';
        }

        if (!empty($field['css_class'])) {
            $attrs[] = 'class="ffp-field ' . esc_attr($field['css_class']) . '"';
        } else {
            $attrs[] = 'class="ffp-field"';
        }

        // Data attributes for JS
        $attrs[] = 'data-field-type="' . esc_attr($this->type) . '"';
        $attrs[] = 'data-field-id="' . esc_attr($field['id'] ?? '') . '"';

        return implode(' ', $attrs);
    }

    protected function wrapField(string $input, array $field): string
    {
        $wrapper_class = 'ffp-field-wrapper ffp-field-type-' . $this->type;

        if (!empty($field['required'])) {
            $wrapper_class .= ' ffp-required';
        }

        if (!empty($field['css_class'])) {
            $wrapper_class .= ' ' . $field['css_class'] . '-wrapper';
        }

        $html = '<div class="' . esc_attr($wrapper_class) . '"';

        // Conditional logic data
        if (!empty($field['conditional_logic'])) {
            $html .= ' data-conditional="' . esc_attr(json_encode($field['conditional_logic'])) . '"';
        }

        $html .= '>';

        // Label
        if (!empty($field['label'])) {
            $html .= '<label class="ffp-field-label">';
            $html .= esc_html($field['label']);
            if (!empty($field['required'])) {
                $html .= ' <span class="ffp-required-marker">*</span>';
            }
            $html .= '</label>';
        }

        // Input wrapper
        $html .= '<div class="ffp-field-input">' . $input . '</div>';

        // Description
        if (!empty($field['description'])) {
            $html .= '<div class="ffp-field-description">' . esc_html($field['description']) . '</div>';
        }

        // Error container
        $html .= '<div class="ffp-field-error" style="display:none;"></div>';

        $html .= '</div>';

        return $html;
    }

    public function validate($value, array $field): array
    {
        $errors = [];

        if (!empty($field['required']) && $this->isEmpty($value)) {
            $errors[] = sprintf(__('%s is required.', 'form-flow-pro'), $field['label'] ?? 'This field');
        }

        return $errors;
    }

    protected function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }

    public function sanitize($value, array $field)
    {
        if (is_array($value)) {
            return array_map('sanitize_text_field', $value);
        }
        return sanitize_text_field($value);
    }

    public function getSchema(): array
    {
        return [
            'type' => $this->type,
            'label' => $this->label,
            'category' => $this->category,
            'icon' => $this->icon,
            'settings' => $this->getDefaultSettings(),
        ];
    }
}

/**
 * Text Input Field
 */
class TextField extends AbstractFieldType
{
    protected string $type = 'text';
    protected string $label = 'Text';
    protected string $category = 'basic';
    protected string $icon = 'text';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'min_length' => null,
            'max_length' => null,
            'pattern' => '',
            'input_mask' => '',
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);

        if (!empty($field['min_length'])) {
            $attrs .= ' minlength="' . intval($field['min_length']) . '"';
        }

        if (!empty($field['max_length'])) {
            $attrs .= ' maxlength="' . intval($field['max_length']) . '"';
        }

        if (!empty($field['pattern'])) {
            $attrs .= ' pattern="' . esc_attr($field['pattern']) . '"';
        }

        if (!empty($field['input_mask'])) {
            $attrs .= ' data-mask="' . esc_attr($field['input_mask']) . '"';
        }

        $input = '<input type="text" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value)) {
            if (!empty($field['min_length']) && strlen($value) < $field['min_length']) {
                $errors[] = sprintf(__('%s must be at least %d characters.', 'form-flow-pro'), $field['label'], $field['min_length']);
            }

            if (!empty($field['max_length']) && strlen($value) > $field['max_length']) {
                $errors[] = sprintf(__('%s must not exceed %d characters.', 'form-flow-pro'), $field['label'], $field['max_length']);
            }

            if (!empty($field['pattern']) && !preg_match('/' . $field['pattern'] . '/', $value)) {
                $errors[] = sprintf(__('%s format is invalid.', 'form-flow-pro'), $field['label']);
            }
        }

        return $errors;
    }
}

/**
 * Email Field
 */
class EmailField extends AbstractFieldType
{
    protected string $type = 'email';
    protected string $label = 'Email';
    protected string $category = 'basic';
    protected string $icon = 'email';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'confirm_email' => false,
            'allowed_domains' => [],
            'blocked_domains' => [],
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $input = '<input type="email" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';

        if (!empty($field['confirm_email'])) {
            $confirm_attrs = str_replace(
                'name="' . $field['name'] . '"',
                'name="' . $field['name'] . '_confirm"',
                $attrs
            );
            $input .= '<div class="ffp-confirm-email-wrapper" style="margin-top:10px;">';
            $input .= '<label class="ffp-field-label">' . __('Confirm Email', 'form-flow-pro') . '</label>';
            $input .= '<input type="email" ' . $confirm_attrs . ' data-confirm-for="' . esc_attr($field['name']) . '">';
            $input .= '</div>';
        }

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value)) {
            if (!is_email($value)) {
                $errors[] = sprintf(__('%s must be a valid email address.', 'form-flow-pro'), $field['label']);
            } else {
                $domain = substr(strrchr($value, '@'), 1);

                if (!empty($field['allowed_domains']) && !in_array($domain, $field['allowed_domains'])) {
                    $errors[] = sprintf(__('Email domain %s is not allowed.', 'form-flow-pro'), $domain);
                }

                if (!empty($field['blocked_domains']) && in_array($domain, $field['blocked_domains'])) {
                    $errors[] = sprintf(__('Email domain %s is not allowed.', 'form-flow-pro'), $domain);
                }
            }
        }

        return $errors;
    }

    public function sanitize($value, array $field)
    {
        return sanitize_email($value);
    }
}

/**
 * Phone Field with International Format
 */
class PhoneField extends AbstractFieldType
{
    protected string $type = 'phone';
    protected string $label = 'Phone';
    protected string $category = 'basic';
    protected string $icon = 'phone';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'default_country' => 'US',
            'allowed_countries' => [],
            'format' => 'international', // international, national, E164
            'validate_format' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $attrs .= ' data-phone-field="true"';
        $attrs .= ' data-default-country="' . esc_attr($field['default_country'] ?? 'US') . '"';
        $attrs .= ' data-format="' . esc_attr($field['format'] ?? 'international') . '"';

        if (!empty($field['allowed_countries'])) {
            $attrs .= ' data-allowed-countries="' . esc_attr(json_encode($field['allowed_countries'])) . '"';
        }

        $input = '<div class="ffp-phone-input-wrapper">';
        $input .= '<input type="tel" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';
        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value) && !empty($field['validate_format'])) {
            // Basic phone validation - remove non-digits and check length
            $digits = preg_replace('/[^0-9]/', '', $value);
            if (strlen($digits) < 7 || strlen($digits) > 15) {
                $errors[] = sprintf(__('%s must be a valid phone number.', 'form-flow-pro'), $field['label']);
            }
        }

        return $errors;
    }
}

/**
 * Textarea Field
 */
class TextareaField extends AbstractFieldType
{
    protected string $type = 'textarea';
    protected string $label = 'Textarea';
    protected string $category = 'basic';
    protected string $icon = 'text-area';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'rows' => 4,
            'cols' => 50,
            'min_length' => null,
            'max_length' => null,
            'auto_grow' => false,
            'rich_editor' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $attrs .= ' rows="' . intval($field['rows'] ?? 4) . '"';
        $attrs .= ' cols="' . intval($field['cols'] ?? 50) . '"';

        if (!empty($field['min_length'])) {
            $attrs .= ' minlength="' . intval($field['min_length']) . '"';
        }

        if (!empty($field['max_length'])) {
            $attrs .= ' maxlength="' . intval($field['max_length']) . '"';
        }

        if (!empty($field['auto_grow'])) {
            $attrs .= ' data-auto-grow="true"';
        }

        if (!empty($field['rich_editor'])) {
            $attrs .= ' data-rich-editor="true"';
        }

        $input = '<textarea ' . $attrs . '>' . esc_textarea($value ?? $field['default_value'] ?? '') . '</textarea>';

        if (!empty($field['max_length'])) {
            $input .= '<div class="ffp-char-counter"><span class="ffp-char-count">0</span> / ' . intval($field['max_length']) . '</div>';
        }

        return $this->wrapField($input, $field);
    }

    public function sanitize($value, array $field)
    {
        if (!empty($field['rich_editor'])) {
            return wp_kses_post($value);
        }
        return sanitize_textarea_field($value);
    }
}

/**
 * Number Field
 */
class NumberField extends AbstractFieldType
{
    protected string $type = 'number';
    protected string $label = 'Number';
    protected string $category = 'basic';
    protected string $icon = 'number';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'min' => null,
            'max' => null,
            'step' => 1,
            'prefix' => '',
            'suffix' => '',
            'decimal_places' => 0,
            'thousand_separator' => ',',
            'decimal_separator' => '.',
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);

        if (isset($field['min'])) {
            $attrs .= ' min="' . floatval($field['min']) . '"';
        }

        if (isset($field['max'])) {
            $attrs .= ' max="' . floatval($field['max']) . '"';
        }

        $attrs .= ' step="' . floatval($field['step'] ?? 1) . '"';

        $input = '';

        if (!empty($field['prefix'])) {
            $input .= '<span class="ffp-input-prefix">' . esc_html($field['prefix']) . '</span>';
        }

        $input .= '<input type="number" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';

        if (!empty($field['suffix'])) {
            $input .= '<span class="ffp-input-suffix">' . esc_html($field['suffix']) . '</span>';
        }

        return $this->wrapField('<div class="ffp-number-wrapper">' . $input . '</div>', $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value)) {
            if (!is_numeric($value)) {
                $errors[] = sprintf(__('%s must be a number.', 'form-flow-pro'), $field['label']);
            } else {
                $num = floatval($value);

                if (isset($field['min']) && $num < $field['min']) {
                    $errors[] = sprintf(__('%s must be at least %s.', 'form-flow-pro'), $field['label'], $field['min']);
                }

                if (isset($field['max']) && $num > $field['max']) {
                    $errors[] = sprintf(__('%s must not exceed %s.', 'form-flow-pro'), $field['label'], $field['max']);
                }
            }
        }

        return $errors;
    }

    public function sanitize($value, array $field)
    {
        return floatval($value);
    }
}

/**
 * Currency Field
 */
class CurrencyField extends AbstractFieldType
{
    protected string $type = 'currency';
    protected string $label = 'Currency';
    protected string $category = 'advanced';
    protected string $icon = 'currency';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'currency' => 'USD',
            'min' => 0,
            'max' => null,
            'decimal_places' => 2,
            'show_currency_symbol' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $currency = $field['currency'] ?? 'USD';
        $symbol = $this->getCurrencySymbol($currency);

        $attrs = $this->getBaseAttributes($field);
        $attrs .= ' data-currency="' . esc_attr($currency) . '"';
        $attrs .= ' step="0.01"';

        if (isset($field['min'])) {
            $attrs .= ' min="' . floatval($field['min']) . '"';
        }

        if (isset($field['max'])) {
            $attrs .= ' max="' . floatval($field['max']) . '"';
        }

        $input = '<div class="ffp-currency-wrapper">';

        if (!empty($field['show_currency_symbol'])) {
            $input .= '<span class="ffp-currency-symbol">' . esc_html($symbol) . '</span>';
        }

        $input .= '<input type="number" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';
        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    private function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'USD' => '$', 'EUR' => '€', 'GBP' => '£', 'JPY' => '¥',
            'BRL' => 'R$', 'CAD' => 'C$', 'AUD' => 'A$', 'CHF' => 'CHF',
            'CNY' => '¥', 'INR' => '₹', 'MXN' => '$', 'KRW' => '₩',
        ];

        return $symbols[$currency] ?? $currency;
    }

    public function sanitize($value, array $field)
    {
        return round(floatval($value), $field['decimal_places'] ?? 2);
    }
}

/**
 * Select/Dropdown Field
 */
class SelectField extends AbstractFieldType
{
    protected string $type = 'select';
    protected string $label = 'Dropdown';
    protected string $category = 'choice';
    protected string $icon = 'dropdown';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'options' => [],
            'multiple' => false,
            'searchable' => false,
            'allow_custom' => false,
            'option_source' => 'custom', // custom, posts, terms, users
            'source_config' => [],
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);

        if (!empty($field['multiple'])) {
            $attrs .= ' multiple';
            $attrs = str_replace('name="' . $field['name'] . '"', 'name="' . $field['name'] . '[]"', $attrs);
        }

        if (!empty($field['searchable'])) {
            $attrs .= ' data-searchable="true"';
        }

        $options = $this->getOptions($field);

        $input = '<select ' . $attrs . '>';

        if (empty($field['multiple'])) {
            $input .= '<option value="">' . esc_html($field['placeholder'] ?? __('Select an option', 'form-flow-pro')) . '</option>';
        }

        foreach ($options as $option) {
            $selected = $this->isSelected($option['value'], $value) ? ' selected' : '';
            $input .= '<option value="' . esc_attr($option['value']) . '"' . $selected . '>';
            $input .= esc_html($option['label']);
            $input .= '</option>';
        }

        $input .= '</select>';

        return $this->wrapField($input, $field);
    }

    protected function getOptions(array $field): array
    {
        $source = $field['option_source'] ?? 'custom';

        switch ($source) {
            case 'posts':
                return $this->getPostOptions($field['source_config'] ?? []);
            case 'terms':
                return $this->getTermOptions($field['source_config'] ?? []);
            case 'users':
                return $this->getUserOptions($field['source_config'] ?? []);
            default:
                return $field['options'] ?? [];
        }
    }

    protected function getPostOptions(array $config): array
    {
        $postType = $config['post_type'] ?? 'post';
        $limit = $config['limit'] ?? 100;
        $orderby = $config['orderby'] ?? 'title';
        $order = $config['order'] ?? 'ASC';

        $cacheKey = 'ffp_posts_' . md5("{$postType}_{$limit}_{$orderby}_{$order}");
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $args = [
            'post_type' => $postType,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => $orderby,
            'order' => $order,
        ];

        $posts = get_posts($args);
        $options = [];

        foreach ($posts as $post) {
            $options[] = [
                'value' => $post->ID,
                'label' => $post->post_title,
            ];
        }

        // Cache for 1 hour
        set_transient($cacheKey, $options, HOUR_IN_SECONDS);

        return $options;
    }

    protected function getTermOptions(array $config): array
    {
        $taxonomy = $config['taxonomy'] ?? 'category';
        $hideEmpty = $config['hide_empty'] ?? false;
        $limit = $config['limit'] ?? 100;

        $cacheKey = 'ffp_terms_' . md5("{$taxonomy}_{$hideEmpty}_{$limit}");
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $args = [
            'taxonomy' => $taxonomy,
            'hide_empty' => $hideEmpty,
            'number' => $limit,
        ];

        $terms = get_terms($args);
        $options = [];

        if (!is_wp_error($terms)) {
            foreach ($terms as $term) {
                $options[] = [
                    'value' => $term->term_id,
                    'label' => $term->name,
                ];
            }
        }

        // Cache for 1 hour
        set_transient($cacheKey, $options, HOUR_IN_SECONDS);

        return $options;
    }

    protected function getUserOptions(array $config): array
    {
        $roles = $config['roles'] ?? [];
        $limit = $config['limit'] ?? 100;

        $cacheKey = 'ffp_users_' . md5(serialize($roles) . "_{$limit}");
        $cached = get_transient($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $args = [
            'role__in' => $roles,
            'number' => $limit,
            'orderby' => 'display_name',
        ];

        $users = get_users($args);
        $options = [];

        foreach ($users as $user) {
            $options[] = [
                'value' => $user->ID,
                'label' => $user->display_name,
            ];
        }

        // Cache for 30 minutes
        set_transient($cacheKey, $options, 30 * MINUTE_IN_SECONDS);

        return $options;
    }

    protected function isSelected($option_value, $value): bool
    {
        if (is_array($value)) {
            return in_array($option_value, $value);
        }
        return $option_value == $value;
    }
}

/**
 * Radio Buttons Field
 */
class RadioField extends AbstractFieldType
{
    protected string $type = 'radio';
    protected string $label = 'Radio Buttons';
    protected string $category = 'choice';
    protected string $icon = 'radio';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'options' => [],
            'layout' => 'vertical', // vertical, horizontal, grid
            'columns' => 2,
            'other_option' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $layout_class = 'ffp-radio-layout-' . ($field['layout'] ?? 'vertical');
        $options = $field['options'] ?? [];

        $input = '<div class="ffp-radio-group ' . $layout_class . '"';

        if (($field['layout'] ?? '') === 'grid') {
            $input .= ' style="grid-template-columns: repeat(' . intval($field['columns'] ?? 2) . ', 1fr);"';
        }

        $input .= '>';

        foreach ($options as $index => $option) {
            $checked = ($option['value'] == $value) ? ' checked' : '';
            $option_id = 'field_' . ($field['id'] ?? uniqid()) . '_' . $index;

            $input .= '<div class="ffp-radio-option">';
            $input .= '<input type="radio" id="' . esc_attr($option_id) . '" ';
            $input .= 'name="' . esc_attr($field['name']) . '" ';
            $input .= 'value="' . esc_attr($option['value']) . '"' . $checked . '>';
            $input .= '<label for="' . esc_attr($option_id) . '">' . esc_html($option['label']) . '</label>';
            $input .= '</div>';
        }

        // Other option
        if (!empty($field['other_option'])) {
            $other_checked = !empty($value) && !in_array($value, array_column($options, 'value')) ? ' checked' : '';
            $other_id = 'field_' . ($field['id'] ?? uniqid()) . '_other';

            $input .= '<div class="ffp-radio-option ffp-radio-other">';
            $input .= '<input type="radio" id="' . esc_attr($other_id) . '" ';
            $input .= 'name="' . esc_attr($field['name']) . '" value="__other__"' . $other_checked . '>';
            $input .= '<label for="' . esc_attr($other_id) . '">' . __('Other', 'form-flow-pro') . '</label>';
            $input .= '<input type="text" class="ffp-radio-other-input" name="' . esc_attr($field['name']) . '_other" ';
            $input .= 'placeholder="' . __('Please specify', 'form-flow-pro') . '">';
            $input .= '</div>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Checkbox Field
 */
class CheckboxField extends AbstractFieldType
{
    protected string $type = 'checkbox';
    protected string $label = 'Checkboxes';
    protected string $category = 'choice';
    protected string $icon = 'checkbox';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'options' => [],
            'layout' => 'vertical',
            'columns' => 2,
            'min_selections' => null,
            'max_selections' => null,
            'select_all' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $layout_class = 'ffp-checkbox-layout-' . ($field['layout'] ?? 'vertical');
        $options = $field['options'] ?? [];
        $values = is_array($value) ? $value : [$value];

        $input = '<div class="ffp-checkbox-group ' . $layout_class . '"';

        if (($field['layout'] ?? '') === 'grid') {
            $input .= ' style="grid-template-columns: repeat(' . intval($field['columns'] ?? 2) . ', 1fr);"';
        }

        $input .= '>';

        // Select all option
        if (!empty($field['select_all'])) {
            $input .= '<div class="ffp-checkbox-select-all">';
            $input .= '<input type="checkbox" id="field_' . ($field['id'] ?? uniqid()) . '_all" class="ffp-select-all">';
            $input .= '<label>' . __('Select All', 'form-flow-pro') . '</label>';
            $input .= '</div>';
        }

        foreach ($options as $index => $option) {
            $checked = in_array($option['value'], $values) ? ' checked' : '';
            $option_id = 'field_' . ($field['id'] ?? uniqid()) . '_' . $index;

            $input .= '<div class="ffp-checkbox-option">';
            $input .= '<input type="checkbox" id="' . esc_attr($option_id) . '" ';
            $input .= 'name="' . esc_attr($field['name']) . '[]" ';
            $input .= 'value="' . esc_attr($option['value']) . '"' . $checked . '>';
            $input .= '<label for="' . esc_attr($option_id) . '">' . esc_html($option['label']) . '</label>';
            $input .= '</div>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);
        $values = is_array($value) ? $value : [$value];
        $count = count(array_filter($values));

        if (!empty($field['min_selections']) && $count < $field['min_selections']) {
            $errors[] = sprintf(__('Please select at least %d options.', 'form-flow-pro'), $field['min_selections']);
        }

        if (!empty($field['max_selections']) && $count > $field['max_selections']) {
            $errors[] = sprintf(__('Please select no more than %d options.', 'form-flow-pro'), $field['max_selections']);
        }

        return $errors;
    }
}

/**
 * Date Picker Field
 */
class DateField extends AbstractFieldType
{
    protected string $type = 'date';
    protected string $label = 'Date Picker';
    protected string $category = 'datetime';
    protected string $icon = 'calendar';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'date_format' => 'Y-m-d',
            'min_date' => '',
            'max_date' => '',
            'disabled_dates' => [],
            'disabled_days' => [], // 0=Sunday, 6=Saturday
            'show_time' => false,
            'time_format' => 'H:i',
            'time_interval' => 30,
            'default_to_today' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $attrs .= ' data-date-format="' . esc_attr($field['date_format'] ?? 'Y-m-d') . '"';

        if (!empty($field['min_date'])) {
            $attrs .= ' min="' . esc_attr($field['min_date']) . '"';
        }

        if (!empty($field['max_date'])) {
            $attrs .= ' max="' . esc_attr($field['max_date']) . '"';
        }

        if (!empty($field['disabled_dates'])) {
            $attrs .= ' data-disabled-dates="' . esc_attr(json_encode($field['disabled_dates'])) . '"';
        }

        if (!empty($field['disabled_days'])) {
            $attrs .= ' data-disabled-days="' . esc_attr(json_encode($field['disabled_days'])) . '"';
        }

        $type = !empty($field['show_time']) ? 'datetime-local' : 'date';

        if (empty($value) && !empty($field['default_to_today'])) {
            $value = date($field['date_format'] ?? 'Y-m-d');
        }

        $input = '<input type="' . $type . '" ' . $attrs . ' value="' . esc_attr($value ?? '') . '">';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value)) {
            $date = strtotime($value);

            if ($date === false) {
                $errors[] = sprintf(__('%s must be a valid date.', 'form-flow-pro'), $field['label']);
            } else {
                if (!empty($field['min_date'])) {
                    $min = strtotime($field['min_date']);
                    if ($date < $min) {
                        $errors[] = sprintf(__('%s cannot be before %s.', 'form-flow-pro'), $field['label'], $field['min_date']);
                    }
                }

                if (!empty($field['max_date'])) {
                    $max = strtotime($field['max_date']);
                    if ($date > $max) {
                        $errors[] = sprintf(__('%s cannot be after %s.', 'form-flow-pro'), $field['label'], $field['max_date']);
                    }
                }
            }
        }

        return $errors;
    }
}

/**
 * Time Picker Field
 */
class TimeField extends AbstractFieldType
{
    protected string $type = 'time';
    protected string $label = 'Time Picker';
    protected string $category = 'datetime';
    protected string $icon = 'clock';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'time_format' => 'H:i',
            'min_time' => '',
            'max_time' => '',
            'step' => 60, // seconds
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $attrs .= ' step="' . intval($field['step'] ?? 60) . '"';

        if (!empty($field['min_time'])) {
            $attrs .= ' min="' . esc_attr($field['min_time']) . '"';
        }

        if (!empty($field['max_time'])) {
            $attrs .= ' max="' . esc_attr($field['max_time']) . '"';
        }

        $input = '<input type="time" ' . $attrs . ' value="' . esc_attr($value ?? $field['default_value'] ?? '') . '">';

        return $this->wrapField($input, $field);
    }
}

/**
 * Date Range Field
 */
class DateRangeField extends AbstractFieldType
{
    protected string $type = 'date_range';
    protected string $label = 'Date Range';
    protected string $category = 'datetime';
    protected string $icon = 'calendar-range';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'date_format' => 'Y-m-d',
            'min_date' => '',
            'max_date' => '',
            'min_duration' => null, // days
            'max_duration' => null, // days
            'separator' => ' to ',
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $start_value = $value['start'] ?? '';
        $end_value = $value['end'] ?? '';

        $input = '<div class="ffp-date-range-wrapper" data-separator="' . esc_attr($field['separator'] ?? ' to ') . '">';

        // Start date
        $input .= '<div class="ffp-date-range-start">';
        $input .= '<label>' . __('Start Date', 'form-flow-pro') . '</label>';
        $input .= '<input type="date" name="' . esc_attr($field['name']) . '[start]" ';
        $input .= 'value="' . esc_attr($start_value) . '"';
        if (!empty($field['min_date'])) $input .= ' min="' . esc_attr($field['min_date']) . '"';
        if (!empty($field['max_date'])) $input .= ' max="' . esc_attr($field['max_date']) . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        $input .= '<span class="ffp-date-range-separator">' . esc_html($field['separator'] ?? ' to ') . '</span>';

        // End date
        $input .= '<div class="ffp-date-range-end">';
        $input .= '<label>' . __('End Date', 'form-flow-pro') . '</label>';
        $input .= '<input type="date" name="' . esc_attr($field['name']) . '[end]" ';
        $input .= 'value="' . esc_attr($end_value) . '"';
        if (!empty($field['min_date'])) $input .= ' min="' . esc_attr($field['min_date']) . '"';
        if (!empty($field['max_date'])) $input .= ' max="' . esc_attr($field['max_date']) . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = [];

        if (!empty($field['required'])) {
            if (empty($value['start']) || empty($value['end'])) {
                $errors[] = sprintf(__('%s is required.', 'form-flow-pro'), $field['label']);
                return $errors;
            }
        }

        if (!empty($value['start']) && !empty($value['end'])) {
            $start = strtotime($value['start']);
            $end = strtotime($value['end']);

            if ($end < $start) {
                $errors[] = __('End date must be after start date.', 'form-flow-pro');
            }

            $duration = ($end - $start) / 86400; // days

            if (!empty($field['min_duration']) && $duration < $field['min_duration']) {
                $errors[] = sprintf(__('Duration must be at least %d days.', 'form-flow-pro'), $field['min_duration']);
            }

            if (!empty($field['max_duration']) && $duration > $field['max_duration']) {
                $errors[] = sprintf(__('Duration cannot exceed %d days.', 'form-flow-pro'), $field['max_duration']);
            }
        }

        return $errors;
    }
}

/**
 * File Upload Field
 */
class FileUploadField extends AbstractFieldType
{
    protected string $type = 'file';
    protected string $label = 'File Upload';
    protected string $category = 'upload';
    protected string $icon = 'upload';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
            'max_size' => 5 * 1024 * 1024, // 5MB
            'max_files' => 1,
            'multiple' => false,
            'drag_drop' => true,
            'preview' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $accept = '.' . implode(',.', $field['allowed_types'] ?? []);

        if (!empty($field['multiple'])) {
            $attrs .= ' multiple';
            $attrs = str_replace('name="' . $field['name'] . '"', 'name="' . $field['name'] . '[]"', $attrs);
        }

        $attrs .= ' accept="' . esc_attr($accept) . '"';
        $attrs .= ' data-max-size="' . intval($field['max_size'] ?? 5242880) . '"';
        $attrs .= ' data-max-files="' . intval($field['max_files'] ?? 1) . '"';

        $input = '<div class="ffp-file-upload-wrapper"';

        if (!empty($field['drag_drop'])) {
            $input .= ' data-drag-drop="true"';
        }

        $input .= '>';

        if (!empty($field['drag_drop'])) {
            $input .= '<div class="ffp-file-dropzone">';
            $input .= '<div class="ffp-dropzone-content">';
            $input .= '<svg class="ffp-dropzone-icon" viewBox="0 0 24 24" width="48" height="48"><path fill="currentColor" d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z"/></svg>';
            $input .= '<p class="ffp-dropzone-text">' . __('Drag and drop files here', 'form-flow-pro') . '</p>';
            $input .= '<p class="ffp-dropzone-text-small">' . __('or', 'form-flow-pro') . '</p>';
            $input .= '<label class="ffp-dropzone-button">' . __('Browse Files', 'form-flow-pro');
            $input .= '<input type="file" ' . $attrs . ' style="display:none;">';
            $input .= '</label>';
            $input .= '</div>';
            $input .= '</div>';
        } else {
            $input .= '<input type="file" ' . $attrs . '>';
        }

        // File preview area
        if (!empty($field['preview'])) {
            $input .= '<div class="ffp-file-preview-list"></div>';
        }

        // File info
        $max_size_mb = round(($field['max_size'] ?? 5242880) / 1048576, 1);
        $input .= '<div class="ffp-file-info">';
        $input .= sprintf(__('Allowed: %s | Max size: %sMB', 'form-flow-pro'),
            implode(', ', $field['allowed_types'] ?? []),
            $max_size_mb
        );
        $input .= '</div>';

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = [];

        if (!empty($field['required']) && empty($value)) {
            $errors[] = sprintf(__('%s is required.', 'form-flow-pro'), $field['label']);
        }

        // File validation is typically handled during upload

        return $errors;
    }
}

/**
 * Image Upload Field with Preview
 */
class ImageUploadField extends FileUploadField
{
    protected string $type = 'image';
    protected string $label = 'Image Upload';
    protected string $icon = 'image';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'allowed_types' => ['jpg', 'jpeg', 'png', 'gif', 'webp'],
            'min_width' => null,
            'max_width' => null,
            'min_height' => null,
            'max_height' => null,
            'aspect_ratio' => null, // e.g., '16:9', '1:1'
            'crop' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $field['allowed_types'] = $field['allowed_types'] ?? ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        $attrs = $this->getBaseAttributes($field);
        $accept = 'image/' . implode(',image/', $field['allowed_types']);

        $attrs .= ' accept="' . esc_attr($accept) . '"';
        $attrs .= ' data-max-size="' . intval($field['max_size'] ?? 5242880) . '"';
        $attrs .= ' data-image-field="true"';

        if (!empty($field['min_width'])) $attrs .= ' data-min-width="' . intval($field['min_width']) . '"';
        if (!empty($field['max_width'])) $attrs .= ' data-max-width="' . intval($field['max_width']) . '"';
        if (!empty($field['min_height'])) $attrs .= ' data-min-height="' . intval($field['min_height']) . '"';
        if (!empty($field['max_height'])) $attrs .= ' data-max-height="' . intval($field['max_height']) . '"';
        if (!empty($field['aspect_ratio'])) $attrs .= ' data-aspect-ratio="' . esc_attr($field['aspect_ratio']) . '"';
        if (!empty($field['crop'])) $attrs .= ' data-crop="true"';

        $input = '<div class="ffp-image-upload-wrapper">';

        $input .= '<div class="ffp-image-dropzone">';
        $input .= '<div class="ffp-image-preview-container">';

        if (!empty($value)) {
            $input .= '<img src="' . esc_url($value) . '" class="ffp-image-preview">';
        } else {
            $input .= '<div class="ffp-image-placeholder">';
            $input .= '<svg viewBox="0 0 24 24" width="64" height="64"><path fill="currentColor" d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/></svg>';
            $input .= '<p>' . __('Click or drag image here', 'form-flow-pro') . '</p>';
            $input .= '</div>';
        }

        $input .= '</div>';
        $input .= '<input type="file" ' . $attrs . '>';
        $input .= '</div>';

        $input .= '<div class="ffp-image-actions" style="display:none;">';
        $input .= '<button type="button" class="ffp-image-remove">' . __('Remove', 'form-flow-pro') . '</button>';
        if (!empty($field['crop'])) {
            $input .= '<button type="button" class="ffp-image-crop">' . __('Crop', 'form-flow-pro') . '</button>';
        }
        $input .= '</div>';

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Signature Field
 */
class SignatureField extends AbstractFieldType
{
    protected string $type = 'signature';
    protected string $label = 'Signature';
    protected string $category = 'advanced';
    protected string $icon = 'signature';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'width' => 400,
            'height' => 150,
            'pen_color' => '#000000',
            'background_color' => '#ffffff',
            'pen_width' => 2,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $width = $field['width'] ?? 400;
        $height = $field['height'] ?? 150;

        $input = '<div class="ffp-signature-wrapper">';
        $input .= '<canvas class="ffp-signature-pad" ';
        $input .= 'width="' . intval($width) . '" height="' . intval($height) . '" ';
        $input .= 'data-pen-color="' . esc_attr($field['pen_color'] ?? '#000000') . '" ';
        $input .= 'data-bg-color="' . esc_attr($field['background_color'] ?? '#ffffff') . '" ';
        $input .= 'data-pen-width="' . intval($field['pen_width'] ?? 2) . '">';
        $input .= '</canvas>';

        $input .= '<input type="hidden" name="' . esc_attr($field['name']) . '" class="ffp-signature-data" value="' . esc_attr($value ?? '') . '">';

        $input .= '<div class="ffp-signature-actions">';
        $input .= '<button type="button" class="ffp-signature-clear">' . __('Clear', 'form-flow-pro') . '</button>';
        $input .= '</div>';

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Rating Field (Stars)
 */
class RatingField extends AbstractFieldType
{
    protected string $type = 'rating';
    protected string $label = 'Rating';
    protected string $category = 'advanced';
    protected string $icon = 'star';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'max_rating' => 5,
            'icon' => 'star', // star, heart, thumbs
            'allow_half' => false,
            'show_value' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $max = intval($field['max_rating'] ?? 5);
        $current = floatval($value ?? 0);
        $icon_type = $field['icon'] ?? 'star';

        $icons = [
            'star' => '★',
            'heart' => '♥',
            'thumbs' => '👍',
        ];

        $icon = $icons[$icon_type] ?? '★';
        $step = !empty($field['allow_half']) ? 0.5 : 1;

        $input = '<div class="ffp-rating-wrapper" data-max="' . $max . '" data-step="' . $step . '">';

        for ($i = 1; $i <= $max; $i++) {
            $filled = $current >= $i ? 'filled' : ($current >= $i - 0.5 ? 'half' : '');
            $input .= '<span class="ffp-rating-icon ' . $filled . '" data-value="' . $i . '">' . $icon . '</span>';
        }

        $input .= '<input type="hidden" name="' . esc_attr($field['name']) . '" class="ffp-rating-value" value="' . esc_attr($current) . '">';

        if (!empty($field['show_value'])) {
            $input .= '<span class="ffp-rating-display">' . $current . '/' . $max . '</span>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    public function validate($value, array $field): array
    {
        $errors = parent::validate($value, $field);

        if (!$this->isEmpty($value)) {
            $max = $field['max_rating'] ?? 5;
            if ($value < 0 || $value > $max) {
                $errors[] = sprintf(__('%s must be between 0 and %d.', 'form-flow-pro'), $field['label'], $max);
            }
        }

        return $errors;
    }

    public function sanitize($value, array $field)
    {
        return floatval($value);
    }
}

/**
 * Slider/Range Field
 */
class SliderField extends AbstractFieldType
{
    protected string $type = 'slider';
    protected string $label = 'Slider';
    protected string $category = 'advanced';
    protected string $icon = 'slider';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'min' => 0,
            'max' => 100,
            'step' => 1,
            'show_value' => true,
            'show_labels' => true,
            'prefix' => '',
            'suffix' => '',
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $min = $field['min'] ?? 0;
        $max = $field['max'] ?? 100;
        $step = $field['step'] ?? 1;
        $current = $value ?? $field['default_value'] ?? $min;

        $input = '<div class="ffp-slider-wrapper">';

        if (!empty($field['show_labels'])) {
            $input .= '<div class="ffp-slider-labels">';
            $input .= '<span class="ffp-slider-min">' . esc_html($field['prefix'] . $min . $field['suffix']) . '</span>';
            $input .= '<span class="ffp-slider-max">' . esc_html($field['prefix'] . $max . $field['suffix']) . '</span>';
            $input .= '</div>';
        }

        $input .= '<input type="range" name="' . esc_attr($field['name']) . '" ';
        $input .= 'min="' . esc_attr($min) . '" max="' . esc_attr($max) . '" step="' . esc_attr($step) . '" ';
        $input .= 'value="' . esc_attr($current) . '" class="ffp-slider-input">';

        if (!empty($field['show_value'])) {
            $input .= '<div class="ffp-slider-value">';
            $input .= '<span class="ffp-slider-prefix">' . esc_html($field['prefix']) . '</span>';
            $input .= '<span class="ffp-slider-current">' . esc_html($current) . '</span>';
            $input .= '<span class="ffp-slider-suffix">' . esc_html($field['suffix']) . '</span>';
            $input .= '</div>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Color Picker Field
 */
class ColorPickerField extends AbstractFieldType
{
    protected string $type = 'color';
    protected string $label = 'Color Picker';
    protected string $category = 'advanced';
    protected string $icon = 'color';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'format' => 'hex', // hex, rgb, hsl
            'alpha' => false,
            'swatches' => [],
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $attrs = $this->getBaseAttributes($field);
        $default = $value ?? $field['default_value'] ?? '#000000';

        $input = '<div class="ffp-color-picker-wrapper">';
        $input .= '<input type="color" ' . $attrs . ' value="' . esc_attr($default) . '">';
        $input .= '<input type="text" class="ffp-color-text" value="' . esc_attr($default) . '" pattern="^#[0-9A-Fa-f]{6}$">';

        if (!empty($field['swatches'])) {
            $input .= '<div class="ffp-color-swatches">';
            foreach ($field['swatches'] as $swatch) {
                $input .= '<span class="ffp-color-swatch" data-color="' . esc_attr($swatch) . '" style="background-color:' . esc_attr($swatch) . '"></span>';
            }
            $input .= '</div>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Address Field (Composite)
 */
class AddressField extends AbstractFieldType
{
    protected string $type = 'address';
    protected string $label = 'Address';
    protected string $category = 'composite';
    protected string $icon = 'location';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'show_line2' => true,
            'show_country' => true,
            'default_country' => 'US',
            'autocomplete' => false,
            'google_places' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $value = is_array($value) ? $value : [];
        $name = $field['name'];

        $input = '<div class="ffp-address-wrapper"';

        if (!empty($field['google_places'])) {
            $input .= ' data-google-places="true"';
        }

        $input .= '>';

        // Street Line 1
        $input .= '<div class="ffp-address-field ffp-address-line1">';
        $input .= '<label>' . __('Street Address', 'form-flow-pro') . '</label>';
        $input .= '<input type="text" name="' . esc_attr($name) . '[line1]" value="' . esc_attr($value['line1'] ?? '') . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        // Street Line 2
        if (!empty($field['show_line2'])) {
            $input .= '<div class="ffp-address-field ffp-address-line2">';
            $input .= '<label>' . __('Address Line 2', 'form-flow-pro') . '</label>';
            $input .= '<input type="text" name="' . esc_attr($name) . '[line2]" value="' . esc_attr($value['line2'] ?? '') . '" placeholder="' . __('Apt, Suite, etc.', 'form-flow-pro') . '">';
            $input .= '</div>';
        }

        // City and State row
        $input .= '<div class="ffp-address-row">';

        // City
        $input .= '<div class="ffp-address-field ffp-address-city">';
        $input .= '<label>' . __('City', 'form-flow-pro') . '</label>';
        $input .= '<input type="text" name="' . esc_attr($name) . '[city]" value="' . esc_attr($value['city'] ?? '') . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        // State/Province
        $input .= '<div class="ffp-address-field ffp-address-state">';
        $input .= '<label>' . __('State/Province', 'form-flow-pro') . '</label>';
        $input .= '<input type="text" name="' . esc_attr($name) . '[state]" value="' . esc_attr($value['state'] ?? '') . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        $input .= '</div>';

        // Postal and Country row
        $input .= '<div class="ffp-address-row">';

        // Postal Code
        $input .= '<div class="ffp-address-field ffp-address-postal">';
        $input .= '<label>' . __('ZIP/Postal Code', 'form-flow-pro') . '</label>';
        $input .= '<input type="text" name="' . esc_attr($name) . '[postal]" value="' . esc_attr($value['postal'] ?? '') . '"';
        if (!empty($field['required'])) $input .= ' required';
        $input .= '>';
        $input .= '</div>';

        // Country
        if (!empty($field['show_country'])) {
            $input .= '<div class="ffp-address-field ffp-address-country">';
            $input .= '<label>' . __('Country', 'form-flow-pro') . '</label>';
            $input .= '<select name="' . esc_attr($name) . '[country]"';
            if (!empty($field['required'])) $input .= ' required';
            $input .= '>';
            $input .= $this->getCountryOptions($value['country'] ?? $field['default_country'] ?? 'US');
            $input .= '</select>';
            $input .= '</div>';
        }

        $input .= '</div>';
        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    private function getCountryOptions(string $selected): string
    {
        $countries = [
            'US' => 'United States', 'CA' => 'Canada', 'GB' => 'United Kingdom',
            'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France',
            'ES' => 'Spain', 'IT' => 'Italy', 'BR' => 'Brazil',
            'MX' => 'Mexico', 'JP' => 'Japan', 'CN' => 'China',
            'IN' => 'India', 'NL' => 'Netherlands', 'SE' => 'Sweden',
            'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland',
            'PT' => 'Portugal', 'PL' => 'Poland', 'BE' => 'Belgium',
            'AT' => 'Austria', 'CH' => 'Switzerland', 'IE' => 'Ireland',
            'NZ' => 'New Zealand', 'SG' => 'Singapore', 'HK' => 'Hong Kong',
            'KR' => 'South Korea', 'AR' => 'Argentina', 'CL' => 'Chile',
        ];

        $options = '<option value="">' . __('Select Country', 'form-flow-pro') . '</option>';

        foreach ($countries as $code => $name) {
            $sel = $code === $selected ? ' selected' : '';
            $options .= '<option value="' . esc_attr($code) . '"' . $sel . '>' . esc_html($name) . '</option>';
        }

        return $options;
    }
}

/**
 * Name Field (Composite)
 */
class NameField extends AbstractFieldType
{
    protected string $type = 'name';
    protected string $label = 'Name';
    protected string $category = 'composite';
    protected string $icon = 'user';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'format' => 'first_last', // simple, first_last, full
            'show_prefix' => false,
            'show_middle' => false,
            'show_suffix' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $value = is_array($value) ? $value : [];
        $name = $field['name'];
        $format = $field['format'] ?? 'first_last';

        $input = '<div class="ffp-name-wrapper ffp-name-format-' . esc_attr($format) . '">';

        if ($format === 'simple') {
            $input .= '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr(is_string($value) ? $value : ($value['full'] ?? '')) . '"';
            if (!empty($field['required'])) $input .= ' required';
            $input .= ' placeholder="' . __('Full Name', 'form-flow-pro') . '">';
        } else {
            // Prefix
            if (!empty($field['show_prefix'])) {
                $input .= '<div class="ffp-name-field ffp-name-prefix">';
                $input .= '<label>' . __('Prefix', 'form-flow-pro') . '</label>';
                $input .= '<select name="' . esc_attr($name) . '[prefix]">';
                $input .= '<option value="">--</option>';
                foreach (['Mr.', 'Mrs.', 'Ms.', 'Miss', 'Dr.', 'Prof.'] as $prefix) {
                    $sel = ($value['prefix'] ?? '') === $prefix ? ' selected' : '';
                    $input .= '<option value="' . esc_attr($prefix) . '"' . $sel . '>' . esc_html($prefix) . '</option>';
                }
                $input .= '</select>';
                $input .= '</div>';
            }

            // First Name
            $input .= '<div class="ffp-name-field ffp-name-first">';
            $input .= '<label>' . __('First Name', 'form-flow-pro') . '</label>';
            $input .= '<input type="text" name="' . esc_attr($name) . '[first]" value="' . esc_attr($value['first'] ?? '') . '"';
            if (!empty($field['required'])) $input .= ' required';
            $input .= '>';
            $input .= '</div>';

            // Middle Name
            if (!empty($field['show_middle'])) {
                $input .= '<div class="ffp-name-field ffp-name-middle">';
                $input .= '<label>' . __('Middle Name', 'form-flow-pro') . '</label>';
                $input .= '<input type="text" name="' . esc_attr($name) . '[middle]" value="' . esc_attr($value['middle'] ?? '') . '">';
                $input .= '</div>';
            }

            // Last Name
            $input .= '<div class="ffp-name-field ffp-name-last">';
            $input .= '<label>' . __('Last Name', 'form-flow-pro') . '</label>';
            $input .= '<input type="text" name="' . esc_attr($name) . '[last]" value="' . esc_attr($value['last'] ?? '') . '"';
            if (!empty($field['required'])) $input .= ' required';
            $input .= '>';
            $input .= '</div>';

            // Suffix
            if (!empty($field['show_suffix'])) {
                $input .= '<div class="ffp-name-field ffp-name-suffix">';
                $input .= '<label>' . __('Suffix', 'form-flow-pro') . '</label>';
                $input .= '<input type="text" name="' . esc_attr($name) . '[suffix]" value="' . esc_attr($value['suffix'] ?? '') . '" placeholder="Jr., Sr., III">';
                $input .= '</div>';
            }
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Repeater Field (Dynamic rows)
 */
class RepeaterField extends AbstractFieldType
{
    protected string $type = 'repeater';
    protected string $label = 'Repeater';
    protected string $category = 'layout';
    protected string $icon = 'repeat';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'sub_fields' => [],
            'min_rows' => 0,
            'max_rows' => null,
            'button_label' => __('Add Row', 'form-flow-pro'),
            'layout' => 'table', // table, block, row
            'collapsible' => false,
            'sortable' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $values = is_array($value) ? $value : [];
        $min_rows = $field['min_rows'] ?? 0;
        $max_rows = $field['max_rows'] ?? null;
        $sub_fields = $field['sub_fields'] ?? [];
        $layout = $field['layout'] ?? 'table';

        $input = '<div class="ffp-repeater-wrapper ffp-repeater-layout-' . esc_attr($layout) . '" ';
        $input .= 'data-min-rows="' . intval($min_rows) . '" ';
        if ($max_rows) $input .= 'data-max-rows="' . intval($max_rows) . '" ';
        $input .= 'data-sortable="' . (!empty($field['sortable']) ? 'true' : 'false') . '">';

        // Template for new rows (hidden)
        $input .= '<script type="text/template" class="ffp-repeater-template">';
        $input .= $this->renderRow($field, $sub_fields, [], '{{INDEX}}');
        $input .= '</script>';

        // Existing rows
        $input .= '<div class="ffp-repeater-rows">';

        if (empty($values) && $min_rows > 0) {
            for ($i = 0; $i < $min_rows; $i++) {
                $input .= $this->renderRow($field, $sub_fields, [], $i);
            }
        } else {
            foreach ($values as $index => $row_value) {
                $input .= $this->renderRow($field, $sub_fields, $row_value, $index);
            }
        }

        $input .= '</div>';

        // Add button
        $input .= '<div class="ffp-repeater-actions">';
        $input .= '<button type="button" class="ffp-repeater-add">';
        $input .= '<span class="ffp-icon">+</span> ';
        $input .= esc_html($field['button_label'] ?? __('Add Row', 'form-flow-pro'));
        $input .= '</button>';
        $input .= '</div>';

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    private function renderRow(array $field, array $sub_fields, array $values, $index): string
    {
        $row = '<div class="ffp-repeater-row" data-index="' . esc_attr($index) . '">';

        // Handle/drag
        if (!empty($field['sortable'])) {
            $row .= '<div class="ffp-repeater-handle">☰</div>';
        }

        // Fields
        $row .= '<div class="ffp-repeater-fields">';

        foreach ($sub_fields as $sub_field) {
            $sub_field['name'] = $field['name'] . '[' . $index . '][' . $sub_field['name'] . ']';
            $sub_field['id'] = $field['id'] . '_' . $index . '_' . $sub_field['id'];
            $sub_value = $values[$sub_field['name']] ?? null;

            // This would normally call the appropriate field type renderer
            $row .= '<div class="ffp-repeater-field">';
            $row .= '<label>' . esc_html($sub_field['label'] ?? '') . '</label>';
            $row .= '<input type="text" name="' . esc_attr($sub_field['name']) . '" value="' . esc_attr($sub_value ?? '') . '">';
            $row .= '</div>';
        }

        $row .= '</div>';

        // Remove button
        $row .= '<button type="button" class="ffp-repeater-remove" title="' . __('Remove', 'form-flow-pro') . '">×</button>';

        $row .= '</div>';

        return $row;
    }
}

/**
 * Section/Divider Field
 */
class SectionField extends AbstractFieldType
{
    protected string $type = 'section';
    protected string $label = 'Section';
    protected string $category = 'layout';
    protected string $icon = 'section';

    public function getDefaultSettings(): array
    {
        return [
            'title' => '',
            'subtitle' => '',
            'collapsible' => false,
            'collapsed' => false,
            'show_divider' => true,
        ];
    }

    public function render(array $field, $value = null): string
    {
        $html = '<div class="ffp-section-wrapper';

        if (!empty($field['collapsible'])) {
            $html .= ' ffp-collapsible';
            if (!empty($field['collapsed'])) {
                $html .= ' ffp-collapsed';
            }
        }

        $html .= '">';

        if (!empty($field['title'])) {
            $html .= '<div class="ffp-section-header">';
            $html .= '<h3 class="ffp-section-title">' . esc_html($field['title']) . '</h3>';

            if (!empty($field['collapsible'])) {
                $html .= '<span class="ffp-section-toggle">▼</span>';
            }

            $html .= '</div>';
        }

        if (!empty($field['subtitle'])) {
            $html .= '<p class="ffp-section-subtitle">' . esc_html($field['subtitle']) . '</p>';
        }

        if (!empty($field['show_divider'])) {
            $html .= '<hr class="ffp-section-divider">';
        }

        $html .= '</div>';

        return $html;
    }

    public function validate($value, array $field): array
    {
        return []; // Section fields don't validate
    }
}

/**
 * HTML/Content Field
 */
class HTMLField extends AbstractFieldType
{
    protected string $type = 'html';
    protected string $label = 'HTML Content';
    protected string $category = 'layout';
    protected string $icon = 'code';

    public function getDefaultSettings(): array
    {
        return [
            'content' => '',
            'container_class' => '',
        ];
    }

    public function render(array $field, $value = null): string
    {
        $class = 'ffp-html-content';
        if (!empty($field['container_class'])) {
            $class .= ' ' . $field['container_class'];
        }

        return '<div class="' . esc_attr($class) . '">' . wp_kses_post($field['content'] ?? '') . '</div>';
    }

    public function validate($value, array $field): array
    {
        return [];
    }
}

/**
 * Hidden Field
 */
class HiddenField extends AbstractFieldType
{
    protected string $type = 'hidden';
    protected string $label = 'Hidden';
    protected string $category = 'advanced';
    protected string $icon = 'hidden';

    public function getDefaultSettings(): array
    {
        return [
            'default_value' => '',
            'dynamic_value' => '', // user_id, post_id, url_param, etc.
        ];
    }

    public function render(array $field, $value = null): string
    {
        $field_value = $value ?? $field['default_value'] ?? '';

        // Handle dynamic values
        if (!empty($field['dynamic_value'])) {
            $field_value = $this->getDynamicValue($field['dynamic_value'], $field);
        }

        return '<input type="hidden" name="' . esc_attr($field['name']) . '" value="' . esc_attr($field_value) . '">';
    }

    private function getDynamicValue(string $type, array $field): string
    {
        switch ($type) {
            case 'user_id':
                return (string) get_current_user_id();
            case 'user_email':
                $user = wp_get_current_user();
                return $user->user_email ?? '';
            case 'post_id':
                return (string) get_the_ID();
            case 'url':
                return isset($_SERVER['REQUEST_URI']) ? esc_url(home_url($_SERVER['REQUEST_URI'])) : '';
            case 'referrer':
                return isset($_SERVER['HTTP_REFERER']) ? esc_url($_SERVER['HTTP_REFERER']) : '';
            case 'timestamp':
                return (string) time();
            case 'url_param':
                $param = $field['url_param_name'] ?? '';
                return isset($_GET[$param]) ? sanitize_text_field($_GET[$param]) : '';
            default:
                return '';
        }
    }
}

/**
 * Calculation Field
 */
class CalculationField extends AbstractFieldType
{
    protected string $type = 'calculation';
    protected string $label = 'Calculation';
    protected string $category = 'advanced';
    protected string $icon = 'calculator';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'formula' => '',
            'decimal_places' => 2,
            'prefix' => '',
            'suffix' => '',
            'hide_zero' => false,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $input = '<div class="ffp-calculation-wrapper">';

        if (!empty($field['prefix'])) {
            $input .= '<span class="ffp-calc-prefix">' . esc_html($field['prefix']) . '</span>';
        }

        $input .= '<span class="ffp-calculation-result" data-formula="' . esc_attr($field['formula'] ?? '') . '" ';
        $input .= 'data-decimals="' . intval($field['decimal_places'] ?? 2) . '">';
        $input .= esc_html($value ?? '0');
        $input .= '</span>';

        if (!empty($field['suffix'])) {
            $input .= '<span class="ffp-calc-suffix">' . esc_html($field['suffix']) . '</span>';
        }

        $input .= '<input type="hidden" name="' . esc_attr($field['name']) . '" class="ffp-calculation-value" value="' . esc_attr($value ?? '') . '">';
        $input .= '</div>';

        return $this->wrapField($input, $field);
    }
}

/**
 * Payment Field (Credit Card)
 */
class PaymentField extends AbstractFieldType
{
    protected string $type = 'payment';
    protected string $label = 'Payment';
    protected string $category = 'payment';
    protected string $icon = 'credit-card';

    public function getDefaultSettings(): array
    {
        return array_merge(parent::getDefaultSettings(), [
            'provider' => 'stripe', // stripe, paypal, square
            'amount_field' => '',
            'currency' => 'USD',
            'show_card_icons' => true,
        ]);
    }

    public function render(array $field, $value = null): string
    {
        $provider = $field['provider'] ?? 'stripe';

        $input = '<div class="ffp-payment-wrapper" data-provider="' . esc_attr($provider) . '">';

        switch ($provider) {
            case 'stripe':
                $input .= '<div id="ffp-stripe-element" class="ffp-stripe-card-element"></div>';
                $input .= '<div id="ffp-stripe-errors" class="ffp-payment-errors"></div>';
                break;

            case 'paypal':
                $input .= '<div id="ffp-paypal-buttons"></div>';
                break;

            default:
                $input .= $this->renderManualCardFields($field);
        }

        if (!empty($field['show_card_icons'])) {
            $input .= '<div class="ffp-card-icons">';
            $input .= '<span class="ffp-card-visa">Visa</span>';
            $input .= '<span class="ffp-card-mastercard">MC</span>';
            $input .= '<span class="ffp-card-amex">Amex</span>';
            $input .= '</div>';
        }

        $input .= '</div>';

        return $this->wrapField($input, $field);
    }

    private function renderManualCardFields(array $field): string
    {
        $html = '<div class="ffp-card-fields">';

        $html .= '<div class="ffp-card-number">';
        $html .= '<label>' . __('Card Number', 'form-flow-pro') . '</label>';
        $html .= '<input type="text" name="' . esc_attr($field['name']) . '[number]" data-card-number autocomplete="cc-number">';
        $html .= '</div>';

        $html .= '<div class="ffp-card-row">';
        $html .= '<div class="ffp-card-expiry">';
        $html .= '<label>' . __('Expiry', 'form-flow-pro') . '</label>';
        $html .= '<input type="text" name="' . esc_attr($field['name']) . '[expiry]" placeholder="MM/YY" data-card-expiry autocomplete="cc-exp">';
        $html .= '</div>';

        $html .= '<div class="ffp-card-cvc">';
        $html .= '<label>' . __('CVC', 'form-flow-pro') . '</label>';
        $html .= '<input type="text" name="' . esc_attr($field['name']) . '[cvc]" data-card-cvc autocomplete="cc-csc">';
        $html .= '</div>';
        $html .= '</div>';

        $html .= '</div>';

        return $html;
    }
}

/**
 * Captcha Field
 */
class CaptchaField extends AbstractFieldType
{
    protected string $type = 'captcha';
    protected string $label = 'CAPTCHA';
    protected string $category = 'security';
    protected string $icon = 'shield';

    public function getDefaultSettings(): array
    {
        return [
            'provider' => 'recaptcha', // recaptcha, hcaptcha, turnstile
            'version' => 'v3', // v2, v3 (for reCAPTCHA)
            'theme' => 'light',
            'size' => 'normal', // normal, compact
        ];
    }

    public function render(array $field, $value = null): string
    {
        $provider = $field['provider'] ?? 'recaptcha';
        $version = $field['version'] ?? 'v3';

        $input = '<div class="ffp-captcha-wrapper" data-provider="' . esc_attr($provider) . '">';

        switch ($provider) {
            case 'recaptcha':
                if ($version === 'v2') {
                    $input .= '<div class="g-recaptcha" data-sitekey="' . esc_attr(get_option('ffp_recaptcha_site_key', '')) . '" ';
                    $input .= 'data-theme="' . esc_attr($field['theme'] ?? 'light') . '" ';
                    $input .= 'data-size="' . esc_attr($field['size'] ?? 'normal') . '"></div>';
                } else {
                    $input .= '<input type="hidden" name="g-recaptcha-response" class="ffp-recaptcha-v3">';
                }
                break;

            case 'hcaptcha':
                $input .= '<div class="h-captcha" data-sitekey="' . esc_attr(get_option('ffp_hcaptcha_site_key', '')) . '"></div>';
                break;

            case 'turnstile':
                $input .= '<div class="cf-turnstile" data-sitekey="' . esc_attr(get_option('ffp_turnstile_site_key', '')) . '"></div>';
                break;
        }

        $input .= '</div>';

        return $input;
    }
}

/**
 * Field Types Registry
 */
class FieldTypesRegistry
{
    use SingletonTrait;

    private array $field_types = [];

    protected function init(): void
    {
        $this->registerCoreFieldTypes();

        // Allow plugins to register custom field types
        do_action('ffp_register_field_types', $this);
    }

    private function registerCoreFieldTypes(): void
    {
        // Basic fields
        $this->register(new TextField());
        $this->register(new EmailField());
        $this->register(new PhoneField());
        $this->register(new TextareaField());
        $this->register(new NumberField());
        $this->register(new CurrencyField());

        // Choice fields
        $this->register(new SelectField());
        $this->register(new RadioField());
        $this->register(new CheckboxField());

        // Date/Time fields
        $this->register(new DateField());
        $this->register(new TimeField());
        $this->register(new DateRangeField());

        // Upload fields
        $this->register(new FileUploadField());
        $this->register(new ImageUploadField());

        // Advanced fields
        $this->register(new SignatureField());
        $this->register(new RatingField());
        $this->register(new SliderField());
        $this->register(new ColorPickerField());
        $this->register(new HiddenField());
        $this->register(new CalculationField());

        // Composite fields
        $this->register(new AddressField());
        $this->register(new NameField());

        // Layout fields
        $this->register(new RepeaterField());
        $this->register(new SectionField());
        $this->register(new HTMLField());

        // Payment & Security
        $this->register(new PaymentField());
        $this->register(new CaptchaField());
    }

    public function register(FieldTypeInterface $field_type): void
    {
        $this->field_types[$field_type->getType()] = $field_type;
    }

    public function get(string $type): ?FieldTypeInterface
    {
        return $this->field_types[$type] ?? null;
    }

    public function getAll(): array
    {
        return $this->field_types;
    }

    public function getByCategory(string $category): array
    {
        return array_filter($this->field_types, function($field) use ($category) {
            return $field->getCategory() === $category;
        });
    }

    public function getCategories(): array
    {
        return [
            'basic' => __('Basic Fields', 'form-flow-pro'),
            'choice' => __('Choice Fields', 'form-flow-pro'),
            'datetime' => __('Date & Time', 'form-flow-pro'),
            'upload' => __('File Upload', 'form-flow-pro'),
            'advanced' => __('Advanced', 'form-flow-pro'),
            'composite' => __('Composite', 'form-flow-pro'),
            'layout' => __('Layout', 'form-flow-pro'),
            'payment' => __('Payment', 'form-flow-pro'),
            'security' => __('Security', 'form-flow-pro'),
        ];
    }

    public function render(string $type, array $field, $value = null): string
    {
        $field_type = $this->get($type);

        if (!$field_type) {
            return '<p class="ffp-error">' . sprintf(__('Unknown field type: %s', 'form-flow-pro'), $type) . '</p>';
        }

        return $field_type->render($field, $value);
    }

    public function validate(string $type, $value, array $field): array
    {
        $field_type = $this->get($type);

        if (!$field_type) {
            return [__('Unknown field type', 'form-flow-pro')];
        }

        return $field_type->validate($value, $field);
    }

    public function sanitize(string $type, $value, array $field)
    {
        $field_type = $this->get($type);

        if (!$field_type) {
            return sanitize_text_field($value);
        }

        return $field_type->sanitize($value, $field);
    }

    public function getSchemas(): array
    {
        $schemas = [];

        foreach ($this->field_types as $type => $field_type) {
            $schemas[$type] = $field_type->getSchema();
        }

        return $schemas;
    }
}
