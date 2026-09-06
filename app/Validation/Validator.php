<?php

declare(strict_types=1);

namespace App\Validation;

use InvalidArgumentException;

final class Validator
{
    /** @var array<string, list<string>> */
    private array $errors = [];

    /** @var array<string, mixed> */
    private array $validated = [];

    /** @param array<string, mixed> $input
     *  @param array<string, string|list<string>> $rules
     */
    public function validate(array $input, array $rules): bool
    {
        $this->errors = [];
        $this->validated = [];

        foreach ($rules as $field => $fieldRules) {
            $fieldRules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;
            $present = array_key_exists($field, $input);
            $value = $input[$field] ?? null;

            if (in_array('nullable', $fieldRules, true) && ($value === null || $value === '')) {
                if ($present) {
                    $this->validated[$field] = null;
                }
                continue;
            }

            foreach ($fieldRules as $ruleDefinition) {
                [$rule, $parameter] = array_pad(explode(':', $ruleDefinition, 2), 2, null);
                $this->applyRule($field, $value, $present, $rule, $parameter, $input);
            }

            if ($present && !isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }

        return $this->errors === [];
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    /** @return array<string, mixed> */
    public function validated(): array
    {
        return $this->validated;
    }

    /** @param array<string, mixed> $input */
    private function applyRule(
        string $field,
        mixed $value,
        bool $present,
        string $rule,
        ?string $parameter,
        array $input,
    ): void {
        $valid = match ($rule) {
            'required' => $present && $value !== null && $value !== '',
            'string' => !$present || is_string($value),
            'integer' => !$present || filter_var($value, FILTER_VALIDATE_INT) !== false,
            'numeric' => !$present || is_numeric($value),
            'boolean' => !$present || filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) !== null,
            'email' => !$present || filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
            'min' => !$present || $this->size($value) >= $this->numericParameter($rule, $parameter),
            'max' => !$present || $this->size($value) <= $this->numericParameter($rule, $parameter),
            'in' => !$present || in_array((string) $value, explode(',', (string) $parameter), true),
            'confirmed' => !$present || hash_equals((string) $value, (string) ($input[$field . '_confirmation'] ?? '')),
            'nullable' => true,
            default => throw new InvalidArgumentException(sprintf('Unknown validation rule: %s.', $rule)),
        };

        if (!$valid) {
            $this->errors[$field][] = sprintf('The %s field failed the %s rule.', str_replace('_', ' ', $field), $rule);
        }
    }

    private function size(mixed $value): int|float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }

        if (is_string($value)) {
            return mb_strlen($value);
        }

        if (is_array($value)) {
            return count($value);
        }

        return 0;
    }

    private function numericParameter(string $rule, ?string $parameter): float
    {
        if ($parameter === null || !is_numeric($parameter)) {
            throw new InvalidArgumentException(sprintf('Validation rule %s requires a numeric parameter.', $rule));
        }

        return (float) $parameter;
    }
}

