<?php

declare(strict_types=1);

/**
 * Conditional Logic Builder
 *
 * Visual builder for form conditional logic rules.
 *
 * @package FormFlowPro\UX
 * @since 2.2.1
 */

namespace FormFlowPro\UX;

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Conditional Logic Builder Class
 */
class ConditionalLogicBuilder
{
    /**
     * Available operators
     *
     * @var array
     */
    private array $operators = [];

    /**
     * Available actions
     *
     * @var array
     */
    private array $actions = [];

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->initOperators();
        $this->initActions();
    }

    /**
     * Initialize operators
     *
     * @return void
     */
    private function initOperators(): void
    {
        $this->operators = [
            'equals' => [
                'label' => __('equals', 'formflow-pro'),
                'types' => ['text', 'select', 'radio', 'number'],
            ],
            'not_equals' => [
                'label' => __('does not equal', 'formflow-pro'),
                'types' => ['text', 'select', 'radio', 'number'],
            ],
            'contains' => [
                'label' => __('contains', 'formflow-pro'),
                'types' => ['text', 'textarea', 'email'],
            ],
            'not_contains' => [
                'label' => __('does not contain', 'formflow-pro'),
                'types' => ['text', 'textarea', 'email'],
            ],
            'starts_with' => [
                'label' => __('starts with', 'formflow-pro'),
                'types' => ['text', 'email'],
            ],
            'ends_with' => [
                'label' => __('ends with', 'formflow-pro'),
                'types' => ['text', 'email'],
            ],
            'is_empty' => [
                'label' => __('is empty', 'formflow-pro'),
                'types' => ['text', 'textarea', 'select', 'checkbox'],
                'no_value' => true,
            ],
            'is_not_empty' => [
                'label' => __('is not empty', 'formflow-pro'),
                'types' => ['text', 'textarea', 'select', 'checkbox'],
                'no_value' => true,
            ],
            'greater_than' => [
                'label' => __('is greater than', 'formflow-pro'),
                'types' => ['number', 'date'],
            ],
            'less_than' => [
                'label' => __('is less than', 'formflow-pro'),
                'types' => ['number', 'date'],
            ],
            'between' => [
                'label' => __('is between', 'formflow-pro'),
                'types' => ['number', 'date'],
                'two_values' => true,
            ],
            'is_checked' => [
                'label' => __('is checked', 'formflow-pro'),
                'types' => ['checkbox'],
                'no_value' => true,
            ],
            'is_not_checked' => [
                'label' => __('is not checked', 'formflow-pro'),
                'types' => ['checkbox'],
                'no_value' => true,
            ],
            'matches_pattern' => [
                'label' => __('matches pattern', 'formflow-pro'),
                'types' => ['text', 'email', 'tel'],
            ],
        ];
    }

    /**
     * Initialize actions
     *
     * @return void
     */
    private function initActions(): void
    {
        $this->actions = [
            'show' => [
                'label' => __('Show field', 'formflow-pro'),
                'icon' => 'visibility',
            ],
            'hide' => [
                'label' => __('Hide field', 'formflow-pro'),
                'icon' => 'hidden',
            ],
            'enable' => [
                'label' => __('Enable field', 'formflow-pro'),
                'icon' => 'yes',
            ],
            'disable' => [
                'label' => __('Disable field', 'formflow-pro'),
                'icon' => 'no',
            ],
            'require' => [
                'label' => __('Make required', 'formflow-pro'),
                'icon' => 'warning',
            ],
            'unrequire' => [
                'label' => __('Make optional', 'formflow-pro'),
                'icon' => 'minus',
            ],
            'set_value' => [
                'label' => __('Set value', 'formflow-pro'),
                'icon' => 'edit',
                'has_value' => true,
            ],
            'clear_value' => [
                'label' => __('Clear value', 'formflow-pro'),
                'icon' => 'dismiss',
            ],
            'show_message' => [
                'label' => __('Show message', 'formflow-pro'),
                'icon' => 'format-status',
                'has_value' => true,
            ],
            'redirect' => [
                'label' => __('Redirect to URL', 'formflow-pro'),
                'icon' => 'external',
                'has_value' => true,
            ],
            'calculate' => [
                'label' => __('Calculate value', 'formflow-pro'),
                'icon' => 'performance',
                'has_value' => true,
            ],
        ];
    }

    /**
     * Get available operators
     *
     * @param string|null $fieldType Filter by field type
     * @return array
     */
    public function getOperators(?string $fieldType = null): array
    {
        if (!$fieldType) {
            return $this->operators;
        }

        return array_filter($this->operators, function ($operator) use ($fieldType) {
            return in_array($fieldType, $operator['types']);
        });
    }

    /**
     * Get available actions
     *
     * @return array
     */
    public function getActions(): array
    {
        return $this->actions;
    }

    /**
     * Validate rule
     *
     * @param array $rule Rule configuration
     * @return array{valid: bool, errors: array}
     */
    public function validateRule(array $rule): array
    {
        $errors = [];

        // Check required fields
        if (empty($rule['field'])) {
            $errors[] = __('Source field is required.', 'formflow-pro');
        }

        if (empty($rule['operator'])) {
            $errors[] = __('Operator is required.', 'formflow-pro');
        }

        if (empty($rule['action'])) {
            $errors[] = __('Action is required.', 'formflow-pro');
        }

        if (empty($rule['target'])) {
            $errors[] = __('Target field is required.', 'formflow-pro');
        }

        // Validate operator
        if (!empty($rule['operator']) && !isset($this->operators[$rule['operator']])) {
            $errors[] = __('Invalid operator.', 'formflow-pro');
        }

        // Check if value is required
        if (!empty($rule['operator'])) {
            $operator = $this->operators[$rule['operator']] ?? [];
            if (empty($operator['no_value']) && empty($rule['value'])) {
                $errors[] = __('Comparison value is required.', 'formflow-pro');
            }
        }

        // Validate action
        if (!empty($rule['action']) && !isset($this->actions[$rule['action']])) {
            $errors[] = __('Invalid action.', 'formflow-pro');
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Evaluate rule against data
     *
     * @param array $rule Rule configuration
     * @param array $data Form data
     * @return bool
     */
    public function evaluateRule(array $rule, array $data): bool
    {
        $fieldValue = $data[$rule['field']] ?? null;
        $compareValue = $rule['value'] ?? null;
        $operator = $rule['operator'] ?? 'equals';

        switch ($operator) {
            case 'equals':
                return $fieldValue == $compareValue;

            case 'not_equals':
                return $fieldValue != $compareValue;

            case 'contains':
                return is_string($fieldValue) && strpos($fieldValue, $compareValue) !== false;

            case 'not_contains':
                return is_string($fieldValue) && strpos($fieldValue, $compareValue) === false;

            case 'starts_with':
                return is_string($fieldValue) && strpos($fieldValue, $compareValue) === 0;

            case 'ends_with':
                return is_string($fieldValue) && substr($fieldValue, -strlen($compareValue)) === $compareValue;

            case 'is_empty':
                return empty($fieldValue);

            case 'is_not_empty':
                return !empty($fieldValue);

            case 'greater_than':
                return is_numeric($fieldValue) && $fieldValue > $compareValue;

            case 'less_than':
                return is_numeric($fieldValue) && $fieldValue < $compareValue;

            case 'between':
                $min = $rule['value_min'] ?? $compareValue;
                $max = $rule['value_max'] ?? $compareValue;
                return is_numeric($fieldValue) && $fieldValue >= $min && $fieldValue <= $max;

            case 'is_checked':
                return !empty($fieldValue) && $fieldValue !== '0' && $fieldValue !== 'false';

            case 'is_not_checked':
                return empty($fieldValue) || $fieldValue === '0' || $fieldValue === 'false';

            case 'matches_pattern':
                return is_string($fieldValue) && preg_match('/' . $compareValue . '/', $fieldValue);

            default:
                return apply_filters("formflow_evaluate_operator_{$operator}", false, $fieldValue, $compareValue, $rule);
        }
    }

    /**
     * Evaluate rule group (multiple rules with AND/OR)
     *
     * @param array $rules Rules array
     * @param string $logic Logic type (and/or)
     * @param array $data Form data
     * @return bool
     */
    public function evaluateRuleGroup(array $rules, string $logic, array $data): bool
    {
        if (empty($rules)) {
            return true;
        }

        $results = array_map(function ($rule) use ($data) {
            return $this->evaluateRule($rule, $data);
        }, $rules);

        if ($logic === 'or') {
            return in_array(true, $results);
        }

        return !in_array(false, $results);
    }

    /**
     * Apply rule action
     *
     * @param array $rule Rule configuration
     * @param array $formState Current form state
     * @return array Modified form state
     */
    public function applyAction(array $rule, array $formState): array
    {
        $target = $rule['target'];
        $action = $rule['action'];
        $actionValue = $rule['action_value'] ?? null;

        switch ($action) {
            case 'show':
                $formState['visibility'][$target] = true;
                break;

            case 'hide':
                $formState['visibility'][$target] = false;
                break;

            case 'enable':
                $formState['disabled'][$target] = false;
                break;

            case 'disable':
                $formState['disabled'][$target] = true;
                break;

            case 'require':
                $formState['required'][$target] = true;
                break;

            case 'unrequire':
                $formState['required'][$target] = false;
                break;

            case 'set_value':
                $formState['values'][$target] = $actionValue;
                break;

            case 'clear_value':
                $formState['values'][$target] = '';
                break;

            case 'show_message':
                $formState['messages'][$target] = $actionValue;
                break;

            case 'calculate':
                $formState['values'][$target] = $this->evaluateCalculation($actionValue, $formState['values'] ?? []);
                break;

            default:
                $formState = apply_filters("formflow_apply_action_{$action}", $formState, $rule);
        }

        return $formState;
    }

    /**
     * Evaluate calculation expression
     *
     * @param string $expression Calculation expression
     * @param array $values Form values
     * @return mixed
     */
    private function evaluateCalculation(string $expression, array $values)
    {
        // Replace field references with values
        $expression = preg_replace_callback('/\{([^}]+)\}/', function ($matches) use ($values) {
            $field = $matches[1];
            return is_numeric($values[$field] ?? '') ? $values[$field] : 0;
        }, $expression);

        // Sanitize expression (only allow numbers and math operators)
        $expression = preg_replace('/[^0-9+\-*\/().%\s]/', '', $expression);

        if (empty($expression)) {
            return 0;
        }

        // Evaluate safely
        try {
            // Use bc math for precision if available
            if (function_exists('bcadd')) {
                // Parse and evaluate simple expressions
                return $this->evaluateSimpleExpression($expression);
            }

            // Fallback to eval with strict sanitization
            $result = @eval("return {$expression};");
            return is_numeric($result) ? $result : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Evaluate simple math expression
     *
     * @param string $expression Expression
     * @return float
     */
    private function evaluateSimpleExpression(string $expression): float
    {
        // Handle parentheses first
        while (preg_match('/\(([^()]+)\)/', $expression, $matches)) {
            $result = $this->evaluateSimpleExpression($matches[1]);
            $expression = str_replace($matches[0], $result, $expression);
        }

        // Handle multiplication and division
        while (preg_match('/([\d.]+)\s*([*\/])\s*([\d.]+)/', $expression, $matches)) {
            $result = $matches[2] === '*'
                ? $matches[1] * $matches[3]
                : ($matches[3] != 0 ? $matches[1] / $matches[3] : 0);
            $expression = str_replace($matches[0], $result, $expression);
        }

        // Handle addition and subtraction
        while (preg_match('/([\d.]+)\s*([+\-])\s*([\d.]+)/', $expression, $matches)) {
            $result = $matches[2] === '+'
                ? $matches[1] + $matches[3]
                : $matches[1] - $matches[3];
            $expression = str_replace($matches[0], $result, $expression);
        }

        return (float) $expression;
    }

    /**
     * Generate JavaScript for frontend evaluation
     *
     * @param array $rules Rules array
     * @return string
     */
    public function generateJavaScript(array $rules): string
    {
        $rulesJson = wp_json_encode($rules);

        return <<<JS
(function() {
    var rules = {$rulesJson};

    function evaluateRule(rule, data) {
        var fieldValue = data[rule.field];
        var compareValue = rule.value;

        switch (rule.operator) {
            case 'equals': return fieldValue == compareValue;
            case 'not_equals': return fieldValue != compareValue;
            case 'contains': return String(fieldValue).indexOf(compareValue) !== -1;
            case 'not_contains': return String(fieldValue).indexOf(compareValue) === -1;
            case 'is_empty': return !fieldValue || fieldValue === '';
            case 'is_not_empty': return fieldValue && fieldValue !== '';
            case 'greater_than': return parseFloat(fieldValue) > parseFloat(compareValue);
            case 'less_than': return parseFloat(fieldValue) < parseFloat(compareValue);
            case 'is_checked': return fieldValue && fieldValue !== '0';
            case 'is_not_checked': return !fieldValue || fieldValue === '0';
            default: return false;
        }
    }

    function applyAction(rule, show) {
        var target = document.querySelector('[name="' + rule.target + '"]');
        if (!target) target = document.getElementById(rule.target);
        if (!target) return;

        var container = target.closest('.elementor-field-group, .form-field, .field-wrapper');
        if (!container) container = target.parentElement;

        switch (rule.action) {
            case 'show':
                if (container) container.style.display = show ? '' : 'none';
                break;
            case 'hide':
                if (container) container.style.display = show ? 'none' : '';
                break;
            case 'enable':
                target.disabled = !show;
                break;
            case 'disable':
                target.disabled = show;
                break;
            case 'require':
                target.required = show;
                break;
            case 'set_value':
                if (show) target.value = rule.action_value || '';
                break;
        }
    }

    function evaluateAllRules() {
        var form = document.querySelector('form');
        if (!form) return;

        var formData = {};
        var elements = form.elements;
        for (var i = 0; i < elements.length; i++) {
            var el = elements[i];
            if (el.name) {
                if (el.type === 'checkbox') {
                    formData[el.name] = el.checked;
                } else if (el.type === 'radio') {
                    if (el.checked) formData[el.name] = el.value;
                } else {
                    formData[el.name] = el.value;
                }
            }
        }

        rules.forEach(function(rule) {
            var result = evaluateRule(rule, formData);
            applyAction(rule, result);
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        evaluateAllRules();

        var form = document.querySelector('form');
        if (form) {
            form.addEventListener('change', evaluateAllRules);
            form.addEventListener('input', evaluateAllRules);
        }
    });
})();
JS;
    }
}
