<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Rules;

use Asignua\FilamentJsonYamlEditor\Support\Json;
use Asignua\FilamentJsonYamlEditor\Support\LocalSchemaRetriever;
use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use JsonException;
use JsonSchema\Constraints\Factory as JustinRainbowFactory;
use JsonSchema\Exception\ResourceNotFoundException;
use JsonSchema\Validator as JustinRainbowValidator;
use LogicException;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator as OpisValidator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * Validates a JSON (or, with {@see Yaml()}, YAML) document against a JSON Schema on the
 * server. The engine is optional: `opis/json-schema` (draft 2020-12) is preferred, then
 * `justinrainbow/json-schema`. Neither installed: a {@see LogicException}, never a silent pass.
 *
 * The schema is an array, a JSON string, or a decoded object (stdClass).
 *
 * @phpstan-consistent-constructor
 */
class JsonSchemaRule implements ValidationRule
{
    protected bool $yaml = false;

    /** @var array<string, mixed>|object|string */
    protected array|object|string $schema;

    /**
     * @param array<string, mixed>|object|string $schema
     */
    public function __construct(array|object|string $schema, protected int $maxErrors = 5)
    {
        $this->schema = $schema;
    }

    /**
     * @param array<string, mixed>|object|string $schema
     */
    public static function make(array|object|string $schema): static
    {
        return new static($schema);
    }

    /**
     * The value is YAML; it is parsed first and the result is checked against the schema.
     */
    public function yaml(bool $condition = true): static
    {
        $this->yaml = $condition;

        return $this;
    }

    public function maxErrors(int $maxErrors): static
    {
        $this->maxErrors = max(1, $maxErrors);

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || (is_string($value) && trim($value) === '')) {
            return;
        }

        try {
            $data = $this->toObject($value);
        } catch (JsonException|ParseException) {
            // Syntax is JsonRule / YamlRule's business.
            return;
        }

        $errors = $this->errors($data);

        if ($errors !== []) {
            $fail('json-yaml-editor::messages.validation.schema_invalid')
                ->translate(['errors' => implode('; ', array_slice($errors, 0, $this->maxErrors))]);
        }
    }

    /**
     * @return list<string>
     */
    public function errors(mixed $data): array
    {
        if (class_exists(OpisValidator::class)) {
            return $this->opis($data);
        }

        if (class_exists(JustinRainbowValidator::class)) {
            return $this->justinrainbow($data);
        }

        throw new LogicException('JsonSchemaRule needs opis/json-schema or justinrainbow/json-schema: composer require opis/json-schema');
    }

    /**
     * @return list<string>
     */
    protected function opis(mixed $data): array
    {
        $result = (new OpisValidator)->validate($data, $this->schemaObject());

        if ($result->isValid()) {
            return [];
        }

        $error = $result->error();

        if ($error === null) {
            return [];
        }

        $errors = [];

        foreach ((new ErrorFormatter)->format($error, false) as $path => $messages) {
            foreach ((array) $messages as $message) {
                $errors[] = ($path === '/' || $path === '' ? '' : $path.': ').$message;
            }
        }

        return $errors;
    }

    /**
     * @return list<string>
     */
    protected function justinrainbow(mixed $data): array
    {
        // Its default retriever fetches remote and file:// `$ref`s; only the bundled
        // meta-schemas are allowed here (opis does not fetch unregistered URIs either).
        $validator = new JustinRainbowValidator(new JustinRainbowFactory(null, new LocalSchemaRetriever));

        try {
            $validator->validate($data, $this->schemaObject());
        } catch (ResourceNotFoundException $exception) {
            return [$exception->getMessage()];
        }

        if ($validator->isValid()) {
            return [];
        }

        $errors = [];

        foreach ($validator->getErrors() as $error) {
            $property = (string) ($error['property'] ?? '');
            $errors[] = ($property === '' ? '' : $property.': ').(string) ($error['message'] ?? '');
        }

        return $errors;
    }

    protected function schemaObject(): object|bool
    {
        if (is_object($this->schema)) {
            return $this->schema;
        }

        $json = is_string($this->schema) ? $this->schema : json_encode($this->schema, JSON_THROW_ON_ERROR);

        /** @var bool|object */
        return json_decode($json, false, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * Objects (stdClass) for maps: that is what both engines expect.
     */
    protected function toObject(mixed $value): mixed
    {
        if (is_array($value)) {
            return json_decode(json_encode($value, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR);
        }

        if (!is_string($value)) {
            return $value;
        }

        if ($this->yaml) {
            // Dates stay ISO strings (a `format: date` schema must accept `2024-01-01`), and the
            // alias expansion guard applies before an engine walks the document.
            return Yaml::parse($value, SymfonyYaml::PARSE_OBJECT_FOR_MAP);
        }

        [$valid] = Json::check($value);

        if (!$valid) {
            throw new JsonException('invalid');
        }

        return json_decode($value, false, 512, JSON_THROW_ON_ERROR);
    }
}
