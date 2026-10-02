<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Rules;

use Asignua\FilamentJsonYamlEditor\Support\Json;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * The value must be valid JSON (a blank value passes: combine with `required`).
 *
 * @phpstan-consistent-constructor
 */
class JsonRule implements ValidationRule
{
    public function __construct(protected int $depth = 512, protected bool $containerOnly = false) {}

    public static function make(int $depth = 512): static
    {
        return new static($depth);
    }

    /**
     * Reject top-level scalars (`5`, `"text"`, `null`): only an object or an array passes.
     */
    public function containerOnly(bool $condition = true): static
    {
        $this->containerOnly = $condition;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_array($value) || $value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        if (!is_string($value)) {
            $fail('json-yaml-editor::messages.validation.invalid_json')->translate(['message' => 'not a string']);

            return;
        }

        [$valid, $message] = Json::check($value, $this->depth);

        if (!$valid) {
            $fail('json-yaml-editor::messages.validation.invalid_json')->translate(['message' => (string) $message]);

            return;
        }

        if ($this->containerOnly && !in_array(ltrim($value)[0], ['{', '['], true)) {
            $fail('json-yaml-editor::messages.validation.container_only')->translate();
        }
    }
}
