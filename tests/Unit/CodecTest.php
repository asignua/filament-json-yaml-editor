<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Unit;

use ArrayObject;
use Asignua\FilamentJsonYamlEditor\Support\Json;
use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use stdClass;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

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

    public function test_yaml_encode_dumps_objects_as_maps(): void
    {
        $this->assertSame("a: 1\n", Yaml::encode(new Collection(['a' => 1])));
        $this->assertSame("a: 1\n", Yaml::encode(new ArrayObject(['a' => 1])));
        $this->assertSame("a:\n  b: x\n", Yaml::encode((object) ['a' => (object) ['b' => 'x']]));
    }

    public function test_yaml_dates_are_strings_unless_asked_for(): void
    {
        $this->assertSame(['d' => '2024-01-01', 't' => '2001-12-14T21:59:43.100000-05:00'], Yaml::decode("d: 2024-01-01\nt: 2001-12-14t21:59:43.10-05:00\n"));
        $this->assertInstanceOf(DateTimeImmutable::class, Yaml::decode('d: 2024-01-01', SymfonyYaml::PARSE_DATETIME)['d']);
    }

    public function test_yaml_alias_bombs_are_rejected(): void
    {
        $yaml = "a0: &a0 [x, x, x, x, x, x, x, x, x, x]\n";

        for ($level = 1; $level <= 9; $level++) {
            $yaml .= "a{$level}: &a{$level} [".implode(', ', array_fill(0, 10, '*a'.($level - 1)))."]\n";
        }

        [$valid, $message] = Yaml::check($yaml);
        $this->assertFalse($valid);
        $this->assertStringContainsString('aliases', (string) $message);
        $this->assertNull(Yaml::decode($yaml));

        // Ordinary alias use passes.
        $this->assertSame(['base' => ['a' => 1], 'x' => ['a' => 1, 'b' => 2]], Yaml::decode("base: &b {a: 1}\nx:\n  <<: *b\n  b: 2\n"));
    }

    public function test_json_decode_keeps_empty_objects_and_big_integers(): void
    {
        $decoded = Json::decode('{"a":{},"b":[],"n":12345678901234567890}');

        $this->assertIsArray($decoded);
        $this->assertEquals(new stdClass, $decoded['a']);
        $this->assertSame([], $decoded['b']);
        $this->assertSame('12345678901234567890', $decoded['n']);
        $this->assertSame('{"a":{},"b":[],"n":"12345678901234567890"}', json_encode($decoded));
    }

    public function test_json_encode_never_blanks_unencodable_values(): void
    {
        $this->assertStringContainsString('"name": "a', Json::encode(['name' => "a\xB1b"]));
        $this->assertNotSame('', Json::encode(['x' => INF]));
    }

    public function test_json_reformat_keeps_values_as_written(): void
    {
        $text = '{"id":12345678901234567890,"f":1.0,"e":{},"l":[ ],"s":"При\/x \"q\"","n":[1,{"a":null}]}';

        $this->assertSame(
            "{\n  \"id\": 12345678901234567890,\n  \"f\": 1.0,\n  \"e\": {},\n  \"l\": [],\n  \"s\": \"При/x \\\"q\\\"\",\n  \"n\": [\n    1,\n    {\n      \"a\": null\n    }\n  ]\n}",
            Json::reformat($text),
        );

        // Same output as encode() for values PHP holds without loss.
        $data = ['title' => 'Привіт', 'url' => 'a/b', 'list' => [1, 2.0], 'empty' => [], 'nested' => ['x' => [true]]];
        $this->assertSame(Json::encode($data, 4), Json::reformat((string) json_encode($data, JSON_PRESERVE_ZERO_FRACTION), 4));
        $this->assertSame('{broken', Json::reformat('{broken'));
        $this->assertSame('"a"', Json::reformat(' "a" '));
    }

    public function test_yaml_midnight_timestamps_keep_their_time(): void
    {
        $this->assertSame(
            ['d' => '2024-01-01', 'h' => '2024-01-01T10:30:00+00:00', 't' => '2024-05-01T00:00:00+00:00', 'u' => '2024-02-03T00:00:00+00:00', 'z' => '2024-06-07T00:00:00+00:00'],
            Yaml::decode("d: 2024-01-01\nh: 2024-01-01T10:30:00Z\nt: 2024-05-01T00:00:00Z\nu: 2024-2-3 00:00:00\nz: 2024-06-07t00:00:00.0+00:00\n"),
        );
        $this->assertSame(['d' => '2024-03-05'], Yaml::decode('d: 2024-03-05'));
    }

    public function test_a_plain_date_stays_a_date_when_the_same_day_appears_with_a_time_elsewhere(): void
    {
        $this->assertSame(
            ['q' => '2024-01-01T00:00:00Z', 'd' => '2024-01-01', 'l' => ['2024-01-01', '2024-01-01T00:00:00+00:00']],
            Yaml::decode("q: '2024-01-01T00:00:00Z'\nd: 2024-01-01 # was 2024-01-01 00:00:00\nl: [2024-01-01, 2024-01-01 00:00:00]\n"),
        );
        $this->assertSame(['d' => '2024-01-01'], Yaml::datesToStrings(Yaml::parse('d: 2024-01-01', SymfonyYaml::PARSE_DATETIME), "d: 2024-01-01\n# 2024-01-01T00:00:00Z"));
    }

    public function test_yaml_decode_to_arrays_keeps_empty_maps_and_dates(): void
    {
        $value = Yaml::decodeToArrays("a: {}\nb: []\nc:\n  at: 2024-01-01T00:00:00Z\n  on: 2024-01-01\nd:\n  - {}\n");

        $this->assertEquals(['a' => new stdClass, 'b' => [], 'c' => ['at' => '2024-01-01T00:00:00+00:00', 'on' => '2024-01-01'], 'd' => [new stdClass]], $value);
        $this->assertSame('{"a":{},"b":[],"c":{"at":"2024-01-01T00:00:00+00:00","on":"2024-01-01"},"d":[{}]}', json_encode($value));
        $this->assertSame("a: {}\nb: []\n", Yaml::encode(['a' => new stdClass, 'b' => []], flags: SymfonyYaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE));
        $this->assertNull(Yaml::decodeToArrays('a: ['));
    }
}
