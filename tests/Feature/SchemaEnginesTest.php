<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Feature;

use Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule;
use Asignua\FilamentJsonYamlEditor\Tests\TestCase;
use ReflectionMethod;

class SchemaEnginesTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function engines(): array
    {
        return [
            'opis' => ['opis'],
            'justinrainbow' => ['justinrainbow'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('engines')]
    public function test_each_engine_reports_the_same_verdict(string $engine): void
    {
        $schema = ['type' => 'object', 'required' => ['name'], 'properties' => ['port' => ['type' => 'integer']]];
        $rule = JsonSchemaRule::make($schema);

        $method = new ReflectionMethod($rule, $engine);
        $valid = $method->invoke($rule, json_decode('{"name":"x","port":1}'));
        $invalid = $method->invoke($rule, json_decode('{"port":"x"}'));

        $this->assertSame([], $valid);
        $this->assertNotSame([], $invalid);
        $this->assertContainsOnlyString($invalid);
    }

    public function test_justinrainbow_never_fetches_external_refs(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'jye');
        file_put_contents((string) $file, '{"type": "string"}');

        try {
            foreach (['file://'.$file, 'http://127.0.0.1:9/schema.json'] as $uri) {
                $rule = JsonSchemaRule::make(['$ref' => $uri]);
                $errors = (new ReflectionMethod($rule, 'justinrainbow'))->invoke($rule, 5);

                $this->assertCount(1, $errors);
                $this->assertStringContainsString('External $ref is not allowed', $errors[0]);
            }

            // The bundled draft meta-schemas still resolve.
            $rule = JsonSchemaRule::make(['$schema' => 'http://json-schema.org/draft-07/schema#', '$ref' => 'http://json-schema.org/draft-07/schema#']);
            $this->assertSame([], (new ReflectionMethod($rule, 'justinrainbow'))->invoke($rule, json_decode('{"type": "string"}')));
        } finally {
            @unlink((string) $file);
        }
    }

    public function test_opis_reports_every_error_up_to_max_errors(): void
    {
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'integer'], 'b' => ['type' => 'string'], 'c' => ['type' => 'string']]];
        $data = json_decode('{"a":"x","b":1,"c":3}');

        $rule = JsonSchemaRule::make($schema)->maxErrors(5);
        $errors = (new ReflectionMethod($rule, 'opis'))->invoke($rule, $data);

        $this->assertCount(3, $errors);
    }

    public function test_opis_reports_an_unresolvable_ref_as_an_error(): void
    {
        foreach (['https://example.com/x.json', 'file:///etc/passwd'] as $uri) {
            $rule = JsonSchemaRule::make(['properties' => ['a' => ['$ref' => $uri]]]);
            $errors = (new ReflectionMethod($rule, 'opis'))->invoke($rule, json_decode('{"a":1}'));

            $this->assertCount(1, $errors);
        }
    }
}
