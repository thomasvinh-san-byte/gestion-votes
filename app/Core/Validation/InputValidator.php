<?php

declare(strict_types=1);

namespace AgVote\Core\Validation;

use DateTimeImmutable;

/**
 * InputValidator - Centralized validation and sanitization.
 *
 * Provides a fluent, chainable API for validating user input,
 * similar to JavaScript libraries like Zod or Joi.
 *
 * Features:
 * - Type coercion and validation (string, integer, number, boolean, email, uuid, enum, array, datetime)
 * - Required/optional fields with default values
 * - Min/max length and value constraints
 * - Pattern matching with regex
 * - Enum validation
 * - XSS sanitization by default for strings
 * - Nullable field support
 *
 * Usage:
 * ```php
 * $result = InputValidator::schema()
 *     ->uuid('meeting_id')->required()
 *     ->string('title')->required()->minLength(1)->maxLength(255)
 *     ->email('email')->optional()
 *     ->enum('status', ['draft', 'live', 'closed'])->default('draft')
 *     ->validate($_POST);
 *
 * if (!$result->isValid()) {
 *     return api_fail($result->firstError(), 422);
 * }
 *
 * $data = $result->data();
 * ```
 *
 * @package AgVote\Core\Validation
 */
final class InputValidator {
    private array $fields = [];
    private array $validated = [];
    private array $errors = [];

    public static function schema(): self {
        return new self();
    }

    public function string(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'string');
    }

    public function integer(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'integer');
    }

    public function number(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'number');
    }

    public function boolean(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'boolean');
    }

    public function email(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'email');
    }

    public function uuid(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'uuid');
    }

    public function enum(string $name, array $values): FieldBuilder {
        $builder = new FieldBuilder($this, $name, 'enum');
        $builder->in($values);
        return $builder;
    }

    public function array(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'array');
    }

    public function datetime(string $name): FieldBuilder {
        return new FieldBuilder($this, $name, 'datetime');
    }

    public function registerField(string $name, array $definition): self {
        $this->fields[$name] = $definition;
        return $this;
    }

    public function validate(array $input): ValidationResult {
        $this->validated = [];
        $this->errors = [];

        foreach ($this->fields as $name => $def) {
            $value = $input[$name] ?? null;
            $this->validateField($name, $value, $def);
        }

        return new ValidationResult($this->validated, $this->errors);
    }

    private function validateField(string $name, mixed $value, array $def): void {
        $type = $def['type'];
        $required = $def['required'] ?? false;
        $default = $def['default'] ?? null;
        $nullable = $def['nullable'] ?? false;

        if ($value === null || $value === '') {
            if ($required) {
                $this->errors[$name] = "Le champ '{$name}' est requis.";
                return;
            }
            if ($default !== null) {
                $this->validated[$name] = $default;
                return;
            }
            if ($nullable) {
                $this->validated[$name] = null;
                return;
            }
            return;
        }

        $result = match ($type) {
            'string' => $this->validateString($name, $value, $def),
            'integer' => $this->validateInteger($name, $value, $def),
            'number' => $this->validateNumber($name, $value, $def),
            'boolean' => $this->validateBoolean($name, $value, $def),
            'email' => $this->validateEmail($name, $value, $def),
            'uuid' => $this->validateUuid($name, $value, $def),
            'enum' => $this->validateEnum($name, $value, $def),
            'array' => $this->validateArray($name, $value, $def),
            'datetime' => $this->validateDatetime($name, $value, $def),
            default => ['valid' => false, 'error' => "Type inconnu: {$type}"],
        };

        if (!$result['valid']) {
            $this->errors[$name] = $result['error'];
            return;
        }

        $this->validated[$name] = $result['value'];
    }

    private function validateString(string $name, mixed $value, array $def): array {
        if (!is_string($value) && !is_numeric($value)) {
            return ['valid' => false, 'error' => "'{$name}' doit être une chaîne."];
        }

        $value = trim((string) $value);

        // Sanitize XSS by default
        if (!($def['raw'] ?? false)) {
            $value = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        if (isset($def['minLength']) && mb_strlen($value) < $def['minLength']) {
            return ['valid' => false, 'error' => "'{$name}' doit contenir au moins {$def['minLength']} caractères."];
        }
        if (isset($def['maxLength']) && mb_strlen($value) > $def['maxLength']) {
            return ['valid' => false, 'error' => "'{$name}' ne doit pas dépasser {$def['maxLength']} caractères."];
        }
        if (isset($def['pattern']) && !preg_match($def['pattern'], $value)) {
            return ['valid' => false, 'error' => "'{$name}' ne correspond pas au format attendu."];
        }
        if (isset($def['in']) && !in_array($value, $def['in'], true)) {
            return ['valid' => false, 'error' => "'{$name}' doit être l'une des valeurs: " . implode(', ', $def['in'])];
        }

        return ['valid' => true, 'value' => $value];
    }

    private function validateInteger(string $name, mixed $value, array $def): array {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => "'{$name}' doit être un entier."];
        }

        $intVal = (int) $value;

        if (isset($def['min']) && $intVal < $def['min']) {
            return ['valid' => false, 'error' => "'{$name}' doit être au minimum {$def['min']}."];
        }
        if (isset($def['max']) && $intVal > $def['max']) {
            return ['valid' => false, 'error' => "'{$name}' ne doit pas dépasser {$def['max']}."];
        }

        return ['valid' => true, 'value' => $intVal];
    }

    private function validateNumber(string $name, mixed $value, array $def): array {
        if (!is_numeric($value)) {
            return ['valid' => false, 'error' => "'{$name}' doit être un nombre."];
        }

        $floatVal = (float) $value;

        if (isset($def['min']) && $floatVal < $def['min']) {
            return ['valid' => false, 'error' => "'{$name}' doit être au minimum {$def['min']}."];
        }
        if (isset($def['max']) && $floatVal > $def['max']) {
            return ['valid' => false, 'error' => "'{$name}' ne doit pas dépasser {$def['max']}."];
        }

        return ['valid' => true, 'value' => $floatVal];
    }

    private function validateBoolean(string $name, mixed $value, array $def): array {
        if (is_bool($value)) {
            return ['valid' => true, 'value' => $value];
        }
        if (in_array($value, ['1', 1, 'true', 'yes', 'on'], true)) {
            return ['valid' => true, 'value' => true];
        }
        if (in_array($value, ['0', 0, 'false', 'no', 'off', ''], true)) {
            return ['valid' => true, 'value' => false];
        }

        return ['valid' => false, 'error' => "'{$name}' doit être un booléen."];
    }

    private function validateEmail(string $name, mixed $value, array $def): array {
        $value = trim((string) $value);

        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'error' => "'{$name}' doit être une adresse email valide."];
        }

        return ['valid' => true, 'value' => mb_strtolower($value)];
    }

    private function validateUuid(string $name, mixed $value, array $def): array {
        $value = trim((string) $value);
        $pattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

        if (!preg_match($pattern, $value)) {
            return ['valid' => false, 'error' => "'{$name}' doit être un UUID valide."];
        }

        return ['valid' => true, 'value' => strtolower($value)];
    }

    private function validateEnum(string $name, mixed $value, array $def): array {
        $value = trim((string) $value);
        $allowed = $def['in'] ?? [];

        if (!in_array($value, $allowed, true)) {
            return ['valid' => false, 'error' => "'{$name}' doit être l'une des valeurs: " . implode(', ', $allowed)];
        }

        return ['valid' => true, 'value' => $value];
    }

    private function validateArray(string $name, mixed $value, array $def): array {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $value = $decoded;
            }
        }

        if (!is_array($value)) {
            return ['valid' => false, 'error' => "'{$name}' doit être un tableau."];
        }

        return ['valid' => true, 'value' => $value];
    }

    private function validateDatetime(string $name, mixed $value, array $def): array {
        $value = trim((string) $value);

        $formats = ['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i', 'Y-m-d'];

        $datetime = null;
        foreach ($formats as $format) {
            $dt = DateTimeImmutable::createFromFormat($format, $value);
            if ($dt !== false) {
                $datetime = $dt;
                break;
            }
        }

        if ($datetime === null) {
            return ['valid' => false, 'error' => "'{$name}' doit être une date/heure valide."];
        }

        return ['valid' => true, 'value' => $datetime->format('Y-m-d H:i:s')];
    }
}

/**
 * FieldBuilder - Fluent field definition builder for InputValidator.
 *
 * Provides chainable methods to configure validation rules for a single field.
 * Methods return $this to allow chaining, or the parent validator to define
 * additional fields.
 *
 * @package AgVote\Core\Validation
 */
final class FieldBuilder {
    private InputValidator $validator;
    private string $name;
    private array $definition;

    public function __construct(InputValidator $validator, string $name, string $type) {
        $this->validator = $validator;
        $this->name = $name;
        $this->definition = ['type' => $type];
    }

    public function required(): self {
        $this->definition['required'] = true;
        return $this;
    }
    public function optional(): self {
        $this->definition['required'] = false;
        return $this;
    }
    public function nullable(): self {
        $this->definition['nullable'] = true;
        return $this;
    }
    public function default(mixed $value): self {
        $this->definition['default'] = $value;
        return $this;
    }
    public function minLength(int $length): self {
        $this->definition['minLength'] = $length;
        return $this;
    }
    public function maxLength(int $length): self {
        $this->definition['maxLength'] = $length;
        return $this;
    }
    public function min(int|float $value): self {
        $this->definition['min'] = $value;
        return $this;
    }
    public function max(int|float $value): self {
        $this->definition['max'] = $value;
        return $this;
    }
    public function pattern(string $regex): self {
        $this->definition['pattern'] = $regex;
        return $this;
    }
    public function in(array $values): self {
        $this->definition['in'] = $values;
        return $this;
    }
    public function raw(): self {
        $this->definition['raw'] = true;
        return $this;
    }

    // Pass-through methods to allow chaining multiple fields
    public function string(string $name): FieldBuilder {
        return $this->build()->string($name);
    }
    public function integer(string $name): FieldBuilder {
        return $this->build()->integer($name);
    }
    public function number(string $name): FieldBuilder {
        return $this->build()->number($name);
    }
    public function boolean(string $name): FieldBuilder {
        return $this->build()->boolean($name);
    }
    public function email(string $name): FieldBuilder {
        return $this->build()->email($name);
    }
    public function uuid(string $name): FieldBuilder {
        return $this->build()->uuid($name);
    }
    public function enum(string $name, array $values): FieldBuilder {
        return $this->build()->enum($name, $values);
    }
    public function array(string $name): FieldBuilder {
        return $this->build()->array($name);
    }
    public function datetime(string $name): FieldBuilder {
        return $this->build()->datetime($name);
    }

    private bool $registered = false;

    private function ensureRegistered(): void {
        if (!$this->registered) {
            $this->validator->registerField($this->name, $this->definition);
            $this->registered = true;
        }
    }

    public function build(): InputValidator {
        $this->ensureRegistered();
        return $this->validator;
    }

    /**
     * Validates data by calling the parent validator.
     * Allows calling validate() directly on the FieldBuilder chain.
     */
    public function validate(array $input): ValidationResult {
        $this->ensureRegistered();
        return $this->validator->validate($input);
    }

    public function __destruct() {
        $this->ensureRegistered();
    }
}

/**
 * ValidationResult - Result container for InputValidator validation.
 *
 * Contains validated data and any validation errors.
 * Provides helper methods for checking validity and accessing results.
 *
 * @package AgVote\Core\Validation
 */
final class ValidationResult {
    /** @var array<string, mixed> Validated and sanitized data */
    private array $data;

    /** @var array<string, string> Validation errors by field name */
    private array $errors;

    /**
     * @param array<string, mixed> $data Validated data
     * @param array<string, string> $errors Validation errors
     */
    public function __construct(array $data, array $errors) {
        $this->data = $data;
        $this->errors = $errors;
    }

    public function isValid(): bool {
        return empty($this->errors);
    }
    public function data(): array {
        return $this->data;
    }
    public function get(string $key, mixed $default = null): mixed {
        return $this->data[$key] ?? $default;
    }
    public function errors(): array {
        return $this->errors;
    }
    public function firstError(): ?string {
        return reset($this->errors) ?: null;
    }

    public function failIfInvalid(): self {
        if (!$this->isValid()) {
            throw new \AgVote\Core\Http\ApiResponseException(
                \AgVote\Core\Http\JsonResponse::fail('validation_failed', 422, ['details' => $this->errors]),
            );
        }
        return $this;
    }
}
