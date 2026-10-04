<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Feature;

use Asignua\FilamentJsonYamlEditor\Rules\JsonRule;
use Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule;
use Asignua\FilamentJsonYamlEditor\Rules\YamlRule;
use Asignua\FilamentJsonYamlEditor\Tests\TestCase;
use Illuminate\Support\Facades\Validator;

class RulesTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $schema = [
        'type' => 'object',
        'required' => ['name'],
        'properties' => [
            'name' => ['type' => 'string'],
            'port' => ['type' => 'integer', 'minimum' => 1],
        ],
        'additionalProperties' => false,
    ];

    /**
     * @return list<string>
     */
    private function errors(mixed $value, mixed $rule): array
    {
        $validator = Validator::make(['field' => $value], ['field' => [$rule]]);

        return $validator->fails() ? $validator->errors()->get('field') : [];
    }

    public function test_json_rule(): void
    {
        $this->assertSame([], $this->errors('{"a": 1}', new JsonRule));
        $this->assertSame([], $this->errors('', new JsonRule));
        $this->assertSame([], $this->errors(null, new JsonRule));
        $this->assertSame([], $this->errors('5', new JsonRule));

        $errors = $this->errors('{"a": ', new JsonRule);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('field', $errors[0]);
        $this->assertStringContainsString('JSON', $errors[0]);
    }

    public function test_json_rule_container_only_and_depth(): void
    {
        $this->assertCount(1, $this->errors('5', JsonRule::make()->containerOnly()));
        $this->assertSame([], $this->errors('[1]', JsonRule::make()->containerOnly()));
        $this->assertCount(1, $this->errors('[[[1]]]', JsonRule::make(2)));
    }

    public function test_yaml_rule(): void
    {
        $this->assertSame([], $this->errors("a: 1\nb:\n  - x\n", new YamlRule));
        $this->assertSame([], $this->errors('', new YamlRule));

        $errors = $this->errors("a: 1\nb: [1, 2\n", new YamlRule);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('YAML', $errors[0]);
        $this->assertStringContainsString('line', $errors[0]);
    }

    public function test_schema_rule_on_json_with_the_available_engine(): void
    {
        $rule = JsonSchemaRule::make($this->schema);

        $this->assertSame([], $this->errors('{"name": "x", "port": 80}', $rule));
        $this->assertSame([], $this->errors('', $rule));
        // Syntax errors are not this rule's business.
        $this->assertSame([], $this->errors('{broken', $rule));

        $errors = $this->errors('{"port": 0}', $rule);
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('schema', $errors[0]);
    }

    public function test_schema_rule_accepts_arrays_strings_and_objects(): void
    {
        $json = (string) json_encode($this->schema);

        foreach ([$this->schema, $json, json_decode($json)] as $schema) {
            $this->assertCount(1, $this->errors('{"name": 1}', JsonSchemaRule::make($schema)));
            $this->assertSame([], $this->errors(['name' => 'ok'], JsonSchemaRule::make($schema)));
        }
    }

    public function test_schema_rule_on_yaml(): void
    {
        $rule = JsonSchemaRule::make($this->schema)->yaml();

        $this->assertSame([], $this->errors("name: demo\nport: 8080\n", $rule));
        $this->assertCount(1, $this->errors("name: demo\nextra: true\n", $rule));
    }

    public function test_schema_rule_lists_at_most_max_errors(): void
    {
        $schema = ['type' => 'object', 'properties' => ['a' => ['type' => 'string'], 'b' => ['type' => 'string'], 'c' => ['type' => 'string']]];

        $errors = $this->errors('{"a": 1, "b": 2, "c": 3}', JsonSchemaRule::make($schema)->maxErrors(1));

        $this->assertCount(1, $errors);
        $this->assertSame(0, substr_count($errors[0], ';'));
    }

    public function test_schema_rule_on_yaml_sees_dates_as_strings(): void
    {
        $rule = JsonSchemaRule::make(['type' => 'object', 'properties' => ['released' => ['type' => 'string']]])->yaml();

        $this->assertSame([], $this->errors("released: 2024-01-01\n", $rule));
    }

    public function test_yaml_rule_rejects_alias_bombs(): void
    {
        $yaml = "a0: &a0 [x, x, x, x, x, x, x, x, x, x]\n";

        for ($level = 1; $level <= 9; $level++) {
            $yaml .= "a{$level}: &a{$level} [".implode(', ', array_fill(0, 10, '*a'.($level - 1)))."]\n";
        }

        $this->assertCount(1, $this->errors($yaml, YamlRule::make()));
        // The schema rule leaves it to the syntax rule instead of walking it.
        $this->assertSame([], $this->errors($yaml, JsonSchemaRule::make(['type' => 'object'])->yaml()));
    }

    public function test_schema_rule_on_yaml_with_merge_keys_in_flow_maps(): void
    {
        $rule = JsonSchemaRule::make(['type' => 'object', 'properties' => ['item' => ['type' => 'object', 'required' => ['k', 'm']]]])->yaml();

        $this->assertSame([], $this->errors("base: &a {k: 1}\nitem: {<<: *a, m: 2}\n", $rule));
        $this->assertCount(1, $this->errors("base: &a {x: 1}\nitem: {<<: *a, m: 2}\n", $rule));
    }
}
