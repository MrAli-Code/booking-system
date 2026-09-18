<?php
namespace BBS\Core;

class Validator
{
    private array $data;
    private array $rules;
    private array $errors = [];
    private array $validated = [];
    private array $messages = [
        'required' => 'The :field field is required.',
        'email' => 'The :field must be a valid email.',
        'min' => 'The :field must be at least :param characters.',
        'max' => 'The :field must not exceed :param characters.',
        'numeric' => 'The :field must be a number.',
        'integer' => 'The :field must be an integer.',
        'string' => 'The :field must be a string.',
        'phone' => 'The :field must be a valid phone number.',
        'url' => 'The :field must be a valid URL.',
        'in' => 'The :field must be one of: :param.',
        'matches' => 'The :field does not match :param.',
        'unique' => 'The :field has already been taken.',
        'exists' => 'The selected :field is invalid.',
        'boolean' => 'The :field must be true or false.',
        'date' => 'The :field must be a valid date.',
        'array' => 'The :field must be an array.',
        'image' => 'The :field must be an image.',
        'file' => 'The :field must be a file.',
        'regex' => 'The :field format is invalid.',
    ];

    public function __construct(array $data, array $rules, array $messages = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = array_merge($this->messages, $messages);
        $this->validate();
    }

    private function validate(): void
    {
        foreach ($this->rules as $field => $ruleString) {
            if (is_string($ruleString)) {
                $rules = explode('|', $ruleString);
            } else {
                $rules = $ruleString;
            }

            $value = $this->data[$field] ?? null;

            foreach ($rules as $rule) {
                $params = [];
                if (strpos($rule, ':') !== false) {
                    [$rule, $paramStr] = explode(':', $rule, 2);
                    $params = explode(',', $paramStr);
                }

                $methodName = 'validate' . ucfirst($rule);
                if (method_exists($this, $methodName)) {
                    $this->$methodName($field, $value, $params);
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $value;
            }
        }
    }

    private function addError(string $field, string $rule, array $params = []): void
    {
        $message = $this->messages[$rule] ?? "The {$field} field is invalid.";
        $message = str_replace(':field', $field, $message);
        if (!empty($params)) {
            $message = str_replace(':param', implode(', ', $params), $message);
        }
        $this->errors[$field][] = $message;
    }

    private function validateRequired(string $field, $value, array $params): void
    {
        if ($value === null || $value === '') {
            $this->addError($field, 'required');
        }
    }

    private function validateEmail(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email');
        }
    }

    private function validateMin(string $field, $value, array $params): void
    {
        $min = (int) ($params[0] ?? 0);
        if (is_string($value) && mb_strlen($value) < $min) {
            $this->addError($field, 'min', $params);
        }
        if (is_numeric($value) && $value < $min) {
            $this->addError($field, 'min', $params);
        }
    }

    private function validateMax(string $field, $value, array $params): void
    {
        $max = (int) ($params[0] ?? 0);
        if (is_string($value) && mb_strlen($value) > $max) {
            $this->addError($field, 'max', $params);
        }
        if (is_numeric($value) && $value > $max) {
            $this->addError($field, 'max', $params);
        }
    }

    private function validateNumeric(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'numeric');
        }
    }

    private function validateInteger(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false) {
            $this->addError($field, 'integer');
        }
    }

    private function validateString(string $field, $value, array $params): void
    {
        if ($value !== null && !is_string($value)) {
            $this->addError($field, 'string');
        }
    }

    private function validatePhone(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !preg_match('/^[\d\+\-\(\)\s]{7,20}$/', $value)) {
            $this->addError($field, 'phone');
        }
    }

    private function validateUrl(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_URL)) {
            $this->addError($field, 'url');
        }
    }

    private function validateIn(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '' && !in_array($value, $params)) {
            $this->addError($field, 'in', $params);
        }
    }

    private function validateBoolean(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '') {
            $allowed = [true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no'];
            if (!in_array($value, $allowed, true)) {
                $this->addError($field, 'boolean');
            }
        }
    }

    private function validateDate(string $field, $value, array $params): void
    {
        if ($value !== null && $value !== '') {
            $ts = strtotime($value);
            if ($ts === false) {
                $this->addError($field, 'date');
            }
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    public function errorString(string $separator = "\n"): string
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            $messages = array_merge($messages, $fieldErrors);
        }
        return implode($separator, $messages);
    }
}
