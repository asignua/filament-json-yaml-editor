<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Rules;

use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be valid YAML according to symfony/yaml (a blank value passes). The
 * message carries the line number the parser reports.
 *
 * @phpstan-consistent-constructor
 */
class YamlRule implements ValidationRule
{
    public static function make(): static
    {
        return new static;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) || $value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (!is_string($value)) {
            $fail('json-yaml-editor::messages.validation.invalid_yaml')->translate(['message' => 'not a string']);

            return;
        }

        [$valid, $message] = Yaml::check($value);

        if (!$valid) {
            $fail('json-yaml-editor::messages.validation.invalid_yaml')->translate(['message' => (string) $message]);
        }
    }
}
