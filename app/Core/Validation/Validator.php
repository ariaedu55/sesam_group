<?php

declare(strict_types=1);

namespace Sesam\Core\Validation;

final class Validator
{
    /**
     * @param array<string, mixed> $input
     * @param array<string, string> $rules
     * @return array<string, mixed>
     */
    public function validate(array $input, array $rules): array
    {
        $errors = [];
        $clean = [];

        foreach ($rules as $field => $ruleString) {
            $value = $input[$field] ?? null;
            foreach (explode('|', $ruleString) as $rule) {
                $error = $this->check($field, $value, $rule);
                if ($error !== null) {
                    $errors[$field] = $error;
                    break;
                }
            }

            if (!isset($errors[$field])) {
                $clean[$field] = is_string($value) ? trim($value) : $value;
            }
        }

        if ($errors !== []) {
            throw new ValidationException($errors);
        }

        return $clean;
    }

    private function check(string $field, mixed $value, string $rule): ?string
    {
        $label = $field;

        if ($rule === 'required') {
            if ($value === null || (is_string($value) && trim($value) === '')) {
                return 'Le champ ' . $label . ' est obligatoire.';
            }

            return null;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($rule === 'string') {
            return is_string($value) ? null : 'Le champ ' . $label . ' doit être un texte.';
        }

        if ($rule === 'integer') {
            return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1)
                ? null
                : 'Le champ ' . $label . ' doit être un nombre entier.';
        }

        if ($rule === 'email') {
            return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false
                ? null
                : 'Le champ ' . $label . ' doit être une adresse e-mail valide.';
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) > $max) {
                return 'Le champ ' . $label . ' ne doit pas dépasser ' . $max . ' caractères.';
            }
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            if (is_string($value) && mb_strlen($value) < $min) {
                return 'Le champ ' . $label . ' doit contenir au moins ' . $min . ' caractères.';
            }
        }

        return null;
    }
}
