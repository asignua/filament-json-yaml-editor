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
}
