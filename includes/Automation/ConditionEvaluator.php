<?php
/**
 * Condition Evaluator - Expression and condition evaluation engine
 *
 * Evaluates complex conditions, expressions, and logical operators
 * for workflow branching and filtering.
 *
 * @package FormFlowPro
 * @subpackage Automation
 * @since 3.0.0
 */

namespace FormFlowPro\Automation;

use FormFlowPro\Core\SingletonTrait;

/**
 * Condition Evaluator
 */
class ConditionEvaluator
{
    use SingletonTrait;

    private array $operators = [];
    private array $functions = [];

    /**
     * Initialize the evaluator
     */
    protected function init(): void
    {
        $this->registerDefaultOperators();
        $this->registerDefaultFunctions();
    }

    /**
     * Register default comparison operators
     */
    private function registerDefaultOperators(): void
    {
        // Equality operators
        $this->operators['equals'] = fn($a, $b) => $a == $b;
        $this->operators['not_equals'] = fn($a, $b) => $a != $b;
        $this->operators['strict_equals'] = fn($a, $b) => $a === $b;
        $this->operators['strict_not_equals'] = fn($a, $b) => $a !== $b;

        // Numeric operators
        $this->operators['greater_than'] = fn($a, $b) => (float)$a > (float)$b;
        $this->operators['greater_than_or_equals'] = fn($a, $b) => (float)$a >= (float)$b;
        $this->operators['less_than'] = fn($a, $b) => (float)$a < (float)$b;
        $this->operators['less_than_or_equals'] = fn($a, $b) => (float)$a <= (float)$b;
        $this->operators['between'] = fn($a, $b) => (float)$a >= (float)$b[0] && (float)$a <= (float)$b[1];
        $this->operators['not_between'] = fn($a, $b) => (float)$a < (float)$b[0] || (float)$a > (float)$b[1];

        // String operators
        $this->operators['contains'] = fn($a, $b) => is_string($a) && str_contains($a, $b);
        $this->operators['not_contains'] = fn($a, $b) => is_string($a) && !str_contains($a, $b);
        $this->operators['starts_with'] = fn($a, $b) => is_string($a) && str_starts_with($a, $b);
        $this->operators['ends_with'] = fn($a, $b) => is_string($a) && str_ends_with($a, $b);
        $this->operators['matches'] = fn($a, $b) => is_string($a) && preg_match($b, $a);
        $this->operators['not_matches'] = fn($a, $b) => is_string($a) && !preg_match($b, $a);

        // Case-insensitive string operators
        $this->operators['contains_i'] = fn($a, $b) => is_string($a) && stripos($a, $b) !== false;
        $this->operators['equals_i'] = fn($a, $b) => is_string($a) && strcasecmp($a, $b) === 0;

        // Array operators
        $this->operators['in'] = fn($a, $b) => is_array($b) && in_array($a, $b);
        $this->operators['not_in'] = fn($a, $b) => is_array($b) && !in_array($a, $b);
        $this->operators['has_key'] = fn($a, $b) => is_array($a) && array_key_exists($b, $a);
        $this->operators['has_value'] = fn($a, $b) => is_array($a) && in_array($b, $a);
        $this->operators['array_contains'] = fn($a, $b) => is_array($a) && in_array($b, $a);
        $this->operators['array_count'] = fn($a, $b) => is_array($a) && count($a) == $b;
        $this->operators['array_count_gt'] = fn($a, $b) => is_array($a) && count($a) > $b;
        $this->operators['array_count_lt'] = fn($a, $b) => is_array($a) && count($a) < $b;

        // Type operators
        $this->operators['is_empty'] = fn($a, $b) => empty($a);
        $this->operators['is_not_empty'] = fn($a, $b) => !empty($a);
        $this->operators['is_null'] = fn($a, $b) => $a === null;
        $this->operators['is_not_null'] = fn($a, $b) => $a !== null;
        $this->operators['is_true'] = fn($a, $b) => $a === true || $a === 'true' || $a === 1 || $a === '1';
        $this->operators['is_false'] = fn($a, $b) => $a === false || $a === 'false' || $a === 0 || $a === '0';
        $this->operators['is_numeric'] = fn($a, $b) => is_numeric($a);
        $this->operators['is_string'] = fn($a, $b) => is_string($a);
        $this->operators['is_array'] = fn($a, $b) => is_array($a);

        // Date operators
        $this->operators['date_equals'] = fn($a, $b) => strtotime($a) !== false && date('Y-m-d', strtotime($a)) === date('Y-m-d', strtotime($b));
        $this->operators['date_before'] = fn($a, $b) => strtotime($a) < strtotime($b);
        $this->operators['date_after'] = fn($a, $b) => strtotime($a) > strtotime($b);
        $this->operators['date_between'] = function ($a, $b) {
            $timestamp = strtotime($a);
            return $timestamp >= strtotime($b[0]) && $timestamp <= strtotime($b[1]);
        };
        $this->operators['is_today'] = fn($a, $b) => date('Y-m-d', strtotime($a)) === date('Y-m-d');
        $this->operators['is_past'] = fn($a, $b) => strtotime($a) < time();
        $this->operators['is_future'] = fn($a, $b) => strtotime($a) > time();
        $this->operators['days_ago'] = fn($a, $b) => (time() - strtotime($a)) / 86400 <= (int)$b;
        $this->operators['days_from_now'] = fn($a, $b) => (strtotime($a) - time()) / 86400 <= (int)$b;
    }

    /**
     * Register default functions for expressions
     */
    private function registerDefaultFunctions(): void
    {
        // String functions
        $this->functions['length'] = fn($s) => is_string($s) ? strlen($s) : (is_array($s) ? count($s) : 0);
        $this->functions['upper'] = fn($s) => strtoupper($s);
        $this->functions['lower'] = fn($s) => strtolower($s);
        $this->functions['trim'] = fn($s) => trim($s);
        $this->functions['substr'] = fn($s, $start, $len = null) => $len ? substr($s, $start, $len) : substr($s, $start);
        $this->functions['replace'] = fn($s, $search, $replace) => str_replace($search, $replace, $s);
        $this->functions['split'] = fn($s, $delimiter) => explode($delimiter, $s);
        $this->functions['join'] = fn($arr, $delimiter) => implode($delimiter, $arr);
        $this->functions['concat'] = fn(...$args) => implode('', $args);

        // Numeric functions
        $this->functions['abs'] = fn($n) => abs($n);
        $this->functions['round'] = fn($n, $precision = 0) => round($n, $precision);
        $this->functions['floor'] = fn($n) => floor($n);
        $this->functions['ceil'] = fn($n) => ceil($n);
        $this->functions['min'] = fn(...$args) => min(...$args);
        $this->functions['max'] = fn(...$args) => max(...$args);
        $this->functions['sum'] = fn($arr) => array_sum($arr);
        $this->functions['avg'] = fn($arr) => count($arr) > 0 ? array_sum($arr) / count($arr) : 0;

        // Date functions
        $this->functions['now'] = fn() => current_time('mysql');
        $this->functions['today'] = fn() => current_time('Y-m-d');
        $this->functions['date'] = fn($format, $timestamp = null) => date($format, $timestamp ?? time());
        $this->functions['strtotime'] = fn($date) => strtotime($date);
        $this->functions['date_add'] = fn($date, $interval) => date('Y-m-d H:i:s', strtotime($date . ' ' . $interval));
        $this->functions['date_diff'] = fn($date1, $date2) => (strtotime($date1) - strtotime($date2)) / 86400;
        $this->functions['year'] = fn($date) => date('Y', strtotime($date));
        $this->functions['month'] = fn($date) => date('m', strtotime($date));
        $this->functions['day'] = fn($date) => date('d', strtotime($date));
        $this->functions['dayofweek'] = fn($date) => date('w', strtotime($date));

        // Array functions
        $this->functions['count'] = fn($arr) => is_array($arr) ? count($arr) : 0;
        $this->functions['first'] = fn($arr) => is_array($arr) ? reset($arr) : null;
        $this->functions['last'] = fn($arr) => is_array($arr) ? end($arr) : null;
        $this->functions['keys'] = fn($arr) => array_keys($arr);
        $this->functions['values'] = fn($arr) => array_values($arr);
        $this->functions['unique'] = fn($arr) => array_unique($arr);
        $this->functions['reverse'] = fn($arr) => array_reverse($arr);
        $this->functions['sort'] = function ($arr, $key = null) {
            if ($key) {
                usort($arr, fn($a, $b) => ($a[$key] ?? 0) <=> ($b[$key] ?? 0));
            } else {
                sort($arr);
            }
            return $arr;
        };
        $this->functions['filter'] = fn($arr, $callback) => array_filter($arr, $callback);
        $this->functions['map'] = fn($arr, $callback) => array_map($callback, $arr);
        $this->functions['pluck'] = fn($arr, $key) => array_column($arr, $key);
        $this->functions['merge'] = fn(...$arrays) => array_merge(...$arrays);
        $this->functions['slice'] = fn($arr, $offset, $length = null) => array_slice($arr, $offset, $length);

        // Logic functions
        $this->functions['if'] = fn($condition, $then, $else = null) => $condition ? $then : $else;
        $this->functions['coalesce'] = function (...$args) {
            foreach ($args as $arg) {
                if ($arg !== null && $arg !== '') {
                    return $arg;
                }
            }
            return null;
        };
        $this->functions['default'] = fn($value, $default) => $value ?? $default;

        // Type conversion
        $this->functions['int'] = fn($v) => (int)$v;
        $this->functions['float'] = fn($v) => (float)$v;
        $this->functions['string'] = fn($v) => (string)$v;
        $this->functions['bool'] = fn($v) => (bool)$v;
        $this->functions['json_encode'] = fn($v) => json_encode($v);
        $this->functions['json_decode'] = fn($v) => json_decode($v, true);

        // Utility functions
        $this->functions['isset'] = fn($v) => isset($v) && $v !== null;
        $this->functions['empty'] = fn($v) => empty($v);
        $this->functions['typeof'] = fn($v) => gettype($v);
        $this->functions['uuid'] = fn() => wp_generate_uuid4();
        $this->functions['random'] = fn($min = 0, $max = 100) => rand($min, $max);
        $this->functions['hash'] = fn($v, $algo = 'md5') => hash($algo, $v);

        // WordPress functions
        $this->functions['get_option'] = fn($key, $default = null) => get_option($key, $default);
        $this->functions['get_user_meta'] = fn($user_id, $key, $single = true) => get_user_meta($user_id, $key, $single);
        $this->functions['get_post_meta'] = fn($post_id, $key, $single = true) => get_post_meta($post_id, $key, $single);
        $this->functions['current_user_id'] = fn() => get_current_user_id();
        $this->functions['current_user_email'] = fn() => wp_get_current_user()->user_email ?? '';
        $this->functions['site_url'] = fn() => site_url();
        $this->functions['admin_email'] = fn() => get_option('admin_email');
    }

    /**
     * Register a custom operator
     */
    public function registerOperator(string $name, callable $callback): void
    {
        $this->operators[$name] = $callback;
    }

    /**
     * Register a custom function
     */
    public function registerFunction(string $name, callable $callback): void
    {
        $this->functions[$name] = $callback;
    }

    /**
     * Evaluate a single condition
     */
    public function evaluate(array|string $condition, array $context = []): bool
    {
        if (is_string($condition)) {
            return $this->evaluateExpression($condition, $context);
        }

        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? null;

        // Get field value from context
        $field_value = $this->getFieldValue($field, $context);

        // Resolve value if it's a variable reference
        if (is_string($value) && str_starts_with($value, '{{')) {
            $value = $this->resolveVariable($value, $context);
        }

        // Get operator function
        $operator_fn = $this->operators[$operator] ?? null;

        if (!$operator_fn) {
            throw new \Exception("Unknown operator: {$operator}");
        }

        return (bool)$operator_fn($field_value, $value);
    }

    /**
     * Evaluate a group of conditions
     */
    public function evaluateGroup(array $conditions, array $context = [], string $logic = 'and'): bool
    {
        if (empty($conditions)) {
            return true;
        }

        // Check if it's a condition group with explicit logic
        if (isset($conditions['logic']) && isset($conditions['conditions'])) {
            $logic = $conditions['logic'];
            $conditions = $conditions['conditions'];
        }

        $results = [];

        foreach ($conditions as $condition) {
            // Nested group
            if (isset($condition['conditions'])) {
                $results[] = $this->evaluateGroup(
                    $condition['conditions'],
                    $context,
                    $condition['logic'] ?? 'and'
                );
            } else {
                $results[] = $this->evaluate($condition, $context);
            }
        }

        return match (strtolower($logic)) {
            'or' => in_array(true, $results, true),
            'xor' => array_sum(array_map('intval', $results)) === 1,
            'nor' => !in_array(true, $results, true),
            'nand' => in_array(false, $results, true),
            default => !in_array(false, $results, true) // AND
        };
    }

    /**
     * Evaluate a string expression
     */
    public function evaluateExpression(string $expression, array $context = []): mixed
    {
        // Handle simple boolean values
        $expression = trim($expression);
        if ($expression === 'true') return true;
        if ($expression === 'false') return false;

        // Replace variables
        $expression = preg_replace_callback(
            '/\{\{([^}]+)\}\}/',
            function ($matches) use ($context) {
                $path = trim($matches[1]);
                $value = $this->getFieldValue($path, $context);
                return $this->valueToExpression($value);
            },
            $expression
        );

        // Handle function calls
        $expression = preg_replace_callback(
            '/([a-z_][a-z0-9_]*)\s*\(/i',
            function ($matches) {
                $fn_name = $matches[1];
                if (isset($this->functions[$fn_name])) {
                    return "__fn_{$fn_name}(";
                }
                return $matches[0];
            },
            $expression
        );

        // Simple expression evaluation (comparison and logical)
        try {
            return $this->parseAndEvaluate($expression, $context);
        } catch (\Exception $e) {
            // Fallback: try direct PHP eval (sandboxed)
            return $this->safeEval($expression, $context);
        }
    }

    /**
     * Parse and evaluate expression
     */
    private function parseAndEvaluate(string $expression, array $context): mixed
    {
        // Handle comparison operators
        $comparisons = ['===', '!==', '==', '!=', '>=', '<=', '>', '<'];

        foreach ($comparisons as $op) {
            if (strpos($expression, $op) !== false) {
                $parts = explode($op, $expression, 2);
                if (count($parts) === 2) {
                    $left = $this->parseAndEvaluate(trim($parts[0]), $context);
                    $right = $this->parseAndEvaluate(trim($parts[1]), $context);

                    return match ($op) {
                        '===' => $left === $right,
                        '!==' => $left !== $right,
                        '==' => $left == $right,
                        '!=' => $left != $right,
                        '>=' => $left >= $right,
                        '<=' => $left <= $right,
                        '>' => $left > $right,
                        '<' => $left < $right,
                    };
                }
            }
        }

        // Handle logical operators
        if (preg_match('/\s+(&&|and)\s+/i', $expression)) {
            $parts = preg_split('/\s+(&&|and)\s+/i', $expression);
            foreach ($parts as $part) {
                if (!$this->parseAndEvaluate(trim($part), $context)) {
                    return false;
                }
            }
            return true;
        }

        if (preg_match('/\s+(\|\||or)\s+/i', $expression)) {
            $parts = preg_split('/\s+(\|\||or)\s+/i', $expression);
            foreach ($parts as $part) {
                if ($this->parseAndEvaluate(trim($part), $context)) {
                    return true;
                }
            }
            return false;
        }

        // Handle NOT operator
        if (str_starts_with($expression, '!') || str_starts_with(strtolower($expression), 'not ')) {
            $inner = ltrim($expression, '!');
            $inner = preg_replace('/^not\s+/i', '', $inner);
            return !$this->parseAndEvaluate(trim($inner), $context);
        }

        // Handle function calls
        if (preg_match('/^__fn_([a-z_][a-z0-9_]*)\((.*)\)$/is', $expression, $matches)) {
            $fn_name = $matches[1];
            $args_str = $matches[2];

            if (isset($this->functions[$fn_name])) {
                $args = $this->parseArguments($args_str, $context);
                return call_user_func_array($this->functions[$fn_name], $args);
            }
        }

        // Handle literals
        if (preg_match('/^"([^"]*)"$/', $expression, $matches)) {
            return $matches[1];
        }
        if (preg_match("/^'([^']*)'$/", $expression, $matches)) {
            return $matches[1];
        }
        if (is_numeric($expression)) {
            return strpos($expression, '.') !== false ? (float)$expression : (int)$expression;
        }
        if ($expression === 'null') {
            return null;
        }

        // Handle array literals
        if (str_starts_with($expression, '[') && str_ends_with($expression, ']')) {
            $inner = substr($expression, 1, -1);
            return $this->parseArguments($inner, $context);
        }

        // Variable reference
        return $this->getFieldValue($expression, $context);
    }

    /**
     * Parse function arguments
     */
    private function parseArguments(string $args_str, array $context): array
    {
        if (trim($args_str) === '') {
            return [];
        }

        $args = [];
        $current = '';
        $depth = 0;
        $in_string = false;
        $string_char = '';

        for ($i = 0; $i < strlen($args_str); $i++) {
            $char = $args_str[$i];

            if (!$in_string && ($char === '"' || $char === "'")) {
                $in_string = true;
                $string_char = $char;
                $current .= $char;
            } elseif ($in_string && $char === $string_char && ($args_str[$i - 1] ?? '') !== '\\') {
                $in_string = false;
                $current .= $char;
            } elseif (!$in_string && ($char === '(' || $char === '[' || $char === '{')) {
                $depth++;
                $current .= $char;
            } elseif (!$in_string && ($char === ')' || $char === ']' || $char === '}')) {
                $depth--;
                $current .= $char;
            } elseif (!$in_string && $depth === 0 && $char === ',') {
                $args[] = $this->parseAndEvaluate(trim($current), $context);
                $current = '';
            } else {
                $current .= $char;
            }
        }

        if (trim($current) !== '') {
            $args[] = $this->parseAndEvaluate(trim($current), $context);
        }

        return $args;
    }

    /**
     * Safe expression evaluation (sandboxed)
     */
    private function safeEval(string $expression, array $context): mixed
    {
        // Whitelist allowed operations
        $allowed_functions = ['abs', 'round', 'floor', 'ceil', 'min', 'max', 'strlen', 'count'];
        $allowed_operators = ['+', '-', '*', '/', '%', '==', '!=', '===', '!==', '<', '>', '<=', '>=', '&&', '||', '!'];

        // Simple arithmetic evaluation
        if (preg_match('/^[\d\s\+\-\*\/\%\.\(\)]+$/', $expression)) {
            // Safe arithmetic expression
            return eval("return {$expression};");
        }

        // Default to false for complex expressions
        return false;
    }

    /**
     * Get field value from context using dot notation
     */
    public function getFieldValue(string $path, array $context): mixed
    {
        if (empty($path)) {
            return null;
        }

        $keys = explode('.', $path);
        $value = $context;

        foreach ($keys as $key) {
            // Handle array index access [0]
            if (preg_match('/^(\w+)\[(\d+)\]$/', $key, $matches)) {
                $key = $matches[1];
                $index = (int)$matches[2];

                if (!is_array($value) || !isset($value[$key])) {
                    return null;
                }
                $value = $value[$key];

                if (!is_array($value) || !isset($value[$index])) {
                    return null;
                }
                $value = $value[$index];
            } else {
                if (!is_array($value) || !array_key_exists($key, $value)) {
                    return null;
                }
                $value = $value[$key];
            }
        }

        return $value;
    }

    /**
     * Resolve variable reference {{var.path}}
     */
    private function resolveVariable(string $reference, array $context): mixed
    {
        $path = trim($reference, '{}');
        return $this->getFieldValue($path, $context);
    }

    /**
     * Convert value to expression string
     */
    private function valueToExpression($value): string
    {
        if ($value === null) {
            return 'null';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_numeric($value)) {
            return (string)$value;
        }
        if (is_string($value)) {
            return '"' . addslashes($value) . '"';
        }
        if (is_array($value)) {
            return json_encode($value);
        }
        return 'null';
    }

    /**
     * Get all available operators
     */
    public function getOperators(): array
    {
        return array_keys($this->operators);
    }

    /**
     * Get all available functions
     */
    public function getFunctions(): array
    {
        return array_keys($this->functions);
    }

    /**
     * Get operator info for UI
     */
    public function getOperatorInfo(): array
    {
        return [
            'equality' => [
                'equals' => ['label' => __('Equals', 'form-flow-pro'), 'value_type' => 'any'],
                'not_equals' => ['label' => __('Not Equals', 'form-flow-pro'), 'value_type' => 'any'],
                'strict_equals' => ['label' => __('Strictly Equals', 'form-flow-pro'), 'value_type' => 'any'],
            ],
            'numeric' => [
                'greater_than' => ['label' => __('Greater Than', 'form-flow-pro'), 'value_type' => 'number'],
                'greater_than_or_equals' => ['label' => __('Greater Than or Equals', 'form-flow-pro'), 'value_type' => 'number'],
                'less_than' => ['label' => __('Less Than', 'form-flow-pro'), 'value_type' => 'number'],
                'less_than_or_equals' => ['label' => __('Less Than or Equals', 'form-flow-pro'), 'value_type' => 'number'],
                'between' => ['label' => __('Between', 'form-flow-pro'), 'value_type' => 'range'],
            ],
            'string' => [
                'contains' => ['label' => __('Contains', 'form-flow-pro'), 'value_type' => 'string'],
                'not_contains' => ['label' => __('Does Not Contain', 'form-flow-pro'), 'value_type' => 'string'],
                'starts_with' => ['label' => __('Starts With', 'form-flow-pro'), 'value_type' => 'string'],
                'ends_with' => ['label' => __('Ends With', 'form-flow-pro'), 'value_type' => 'string'],
                'matches' => ['label' => __('Matches Regex', 'form-flow-pro'), 'value_type' => 'regex'],
            ],
            'array' => [
                'in' => ['label' => __('In List', 'form-flow-pro'), 'value_type' => 'array'],
                'not_in' => ['label' => __('Not In List', 'form-flow-pro'), 'value_type' => 'array'],
                'has_key' => ['label' => __('Has Key', 'form-flow-pro'), 'value_type' => 'string'],
                'array_contains' => ['label' => __('Array Contains', 'form-flow-pro'), 'value_type' => 'any'],
            ],
            'type' => [
                'is_empty' => ['label' => __('Is Empty', 'form-flow-pro'), 'value_type' => 'none'],
                'is_not_empty' => ['label' => __('Is Not Empty', 'form-flow-pro'), 'value_type' => 'none'],
                'is_null' => ['label' => __('Is Null', 'form-flow-pro'), 'value_type' => 'none'],
                'is_true' => ['label' => __('Is True', 'form-flow-pro'), 'value_type' => 'none'],
                'is_false' => ['label' => __('Is False', 'form-flow-pro'), 'value_type' => 'none'],
            ],
            'date' => [
                'date_equals' => ['label' => __('Date Equals', 'form-flow-pro'), 'value_type' => 'date'],
                'date_before' => ['label' => __('Date Before', 'form-flow-pro'), 'value_type' => 'date'],
                'date_after' => ['label' => __('Date After', 'form-flow-pro'), 'value_type' => 'date'],
                'is_today' => ['label' => __('Is Today', 'form-flow-pro'), 'value_type' => 'none'],
                'is_past' => ['label' => __('Is in Past', 'form-flow-pro'), 'value_type' => 'none'],
                'is_future' => ['label' => __('Is in Future', 'form-flow-pro'), 'value_type' => 'none'],
            ]
        ];
    }
}
