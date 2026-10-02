<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Unit;

use Asignua\FilamentJsonYamlEditor\Support\Json;
use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use PHPUnit\Framework\TestCase;

class CodecTest extends TestCase
{
    public function test_json_encode_is_pretty_unescaped_and_honours_indent(): void
    {
        $data = ['title' => 'Привіт', 'url' => 'a/b', 'list' => [1, 2.0], 'empty' => []];

        $this->assertSame(
            "{\n  \"title\": \"Привіт\",\n  \"url\": \"a/b\",\n  \"list\": [\n    1,\n    2.0\n  ],\n  \"empty\": []\n}",
            Json::encode($data, 2),
        );
        $this->assertStringContainsString("\n    \"title\"", Json::encode($data, 4));
        $this->assertStringContainsString("\n\t", str_replace('    ', "\t", Json::encode($data, 4)));
    }

    public function test_json_decode_and_check(): void
    {
        $this->assertSame(['a' => [1]], Json::decode('{"a":[1]}'));
        $this->assertNull(Json::decode('  '));
        $this->assertNull(Json::decode('{broken'));
        $this->assertSame([true, null], Json::check('{}'));
        $this->assertFalse(Json::check('{')[0]);
    }

    public function test_yaml_round_trip_and_check(): void
    {
        $data = ['server' => ['host' => 'x', 'ports' => [80, 443]]];

        $this->assertSame("server:\n  host: x\n  ports:\n    - 80\n    - 443\n", Yaml::encode($data));
        $this->assertSame("server:\n  host: x\n  ports: [80, 443]\n", Yaml::encode($data, 2));
        $this->assertSame("server:\n    host: x\n", Yaml::encode(['server' => ['host' => 'x']], 10, 4));
        $this->assertSame($data, Yaml::decode(Yaml::encode($data)));
        $this->assertNull(Yaml::decode(''));
        $this->assertNull(Yaml::decode("a: [1\n"));

        [$valid, $message] = Yaml::check("a: 1\nb: [1, 2\n");
        $this->assertFalse($valid);
        $this->assertStringContainsString('line', (string) $message);
    }

    public function test_yaml_never_instantiates_objects(): void
    {
        $this->assertNull(Yaml::decode('!php/object "O:8:\"stdClass\":0:{}"'));
    }
}
