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
        // symfony/yaml 7 dumps an empty map as `{  }`, 8 as `{}` — both are the same YAML.
        $this->assertSame("a: {}\nb: []\n", str_replace('{  }', '{}', Yaml::encode(['a' => new stdClass, 'b' => []], flags: SymfonyYaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE)));
        $this->assertNull(Yaml::decodeToArrays('a: ['));
    }

    public function test_yaml_decode_to_arrays_survives_merge_keys_in_flow_maps(): void
    {
        $expected = ['base' => ['k' => 1], 'item' => ['k' => 1, 'm' => 2]];

        // symfony/yaml merges an object into an array here and throws a TypeError with
        // PARSE_OBJECT_FOR_MAP; the text is valid, so it must decode like decode() does.
        $this->assertSame($expected, Yaml::decodeToArrays("base: &a {k: 1}\nitem: {<<: *a, m: 2}\n"));
        $this->assertSame($expected, Yaml::decodeToArrays("base: &a\n  k: 1\nitem: {<<: *a, m: 2}\n"));
        $this->assertSame(
            ['base' => ['at' => '2024-01-01T00:00:00+00:00'], 'item' => ['at' => '2024-01-01T00:00:00+00:00', 'on' => '2024-01-01']],
            Yaml::decodeToArrays("base: &a {at: 2024-01-01T00:00:00Z}\nitem: {<<: *a, on: 2024-01-01}\n"),
        );
    }

    public function test_a_merge_key_fallback_cannot_expand_an_alias_bomb(): void
    {
        $yaml = "m: &m {z: 1}\nl0: &l0 [x, x, x, x, x, x, x, x, x, x]\n";

        for ($level = 1; $level <= 9; $level++) {
            $yaml .= "l{$level}: &l{$level} [".implode(', ', array_fill(0, 10, '*l'.($level - 1)))."]\n";
        }

        $yaml .= "t: {<<: *m, k: 1}\n";

        $this->assertFalse(Yaml::check($yaml)[0]);
        $this->assertNull(Yaml::decodeToArrays($yaml));

        // The fallback itself still works for an ordinary document.
        $parsed = Yaml::parse("m: &m {z: 1}\nt: {<<: *m, k: 1}\n", SymfonyYaml::PARSE_OBJECT_FOR_MAP);
        $this->assertSame(['z' => 1, 'k' => 1], (array) $parsed->t);
    }

    public function test_unquoted_date_keys_stay_strings(): void
    {
        $this->assertSame(['2024-01-01' => 'x'], Yaml::decodeToArrays("2024-01-01: x\n"));
        $this->assertSame(
            ['holidays' => ['2024-12-25' => 'Christmas', '2025-01-01' => 'New year'], 'list' => [['2024-02-02T10:00:00Z' => 1]]],
            Yaml::decodeToArrays("holidays:\n  2024-12-25: Christmas\n  2025-01-01: New year\nlist:\n  - 2024-02-02T10:00:00Z: 1\n"),
        );
        // Text inside a block scalar is not a key, and a real integer key stays an integer.
        $this->assertSame(
            ['a' => "2024-01-01: not a key\n", 'b' => ['1704067200' => 'z']],
            Yaml::decodeToArrays("a: |\n  2024-01-01: not a key\nb:\n  1704067200: z\n"),
        );
        // A quoted key was a string all along.
        $this->assertSame(['2024-01-01' => 'x'], Yaml::decodeToArrays("'2024-01-01': x\n"));
    }

    public function test_date_keys_survive_a_comment_ending_in_a_block_indicator_and_a_quote_in_plain_text(): void
    {
        $this->assertSame(
            ['a' => ['2024-01-01' => 'x']],
            Yaml::decodeToArrays("a: # note: |\n  2024-01-01: x\n"),
        );
        $this->assertSame(
            ['a' => ['b' => 1, '2024-01-01' => 'x']],
            Yaml::decodeToArrays("a: # note: |\n  b: 1\n  2024-01-01: x\n"),
        );
        $this->assertSame(
            ['text' => "it - 'quote", 'b' => ['2024-01-01' => 'x']],
            Yaml::decodeToArrays("text: it - 'quote\nb:\n  2024-01-01: x\n"),
        );
        $this->assertSame(
            ['text' => "a - &b 'c", 'b' => ['2024-01-01' => 'x']],
            Yaml::decodeToArrays("text: a - &b 'c\nb:\n  2024-01-01: x\n"),
        );
    }

    public function test_date_keys_do_not_touch_integer_keys_or_lists(): void
    {
        // 1735084800 is 2024-12-25 as a timestamp: a genuine integer key stays one.
        $this->assertEquals(
            ['d' => ['2024-12-25' => 'a'], 'other' => ['1735084800' => 'b']],
            Yaml::decodeToArrays("d:\n  2024-12-25: a\nother:\n  1735084800: b\n"),
        );
        // 1970-01-01 is 0: it must not turn the list below into a map.
        $this->assertSame(
            ['1970-01-01' => 'x', 'list' => ['a', 'b', 'c']],
            Yaml::decodeToArrays("1970-01-01: x\nlist: [a, b, c]\n"),
        );
        $this->assertSame(
            ['1970-01-01' => 'x', 'list' => ['a', 'b', 'c']],
            Yaml::decodeToArrays("1970-01-01: x\nlist:\n  - a\n  - b\n  - c\n"),
        );
    }

    public function test_date_keys_are_not_quoted_inside_multi_line_quoted_scalars(): void
    {
        $this->assertSame(['desc' => 'foo 2024-01-01: bar'], Yaml::decodeToArrays("desc: \"foo\n  2024-01-01: bar\"\n"));
        $this->assertSame(['desc' => 'foo 2024-01-01: bar'], Yaml::decodeToArrays("desc: 'foo\n  2024-01-01: bar'\n"));
        $this->assertSame(['desc' => 'foo 2024-01-01: bar'], Yaml::decodeToArrays("desc: &a \"foo\n  2024-01-01: bar\"\n"));
        $this->assertSame(['desc' => 'foo 2024-01-01: bar'], Yaml::decodeToArrays("desc: &a 'foo\n  2024-01-01: bar'\n"));
        $this->assertSame(['a' => ['2024-01-01' => 'x']], Yaml::decodeToArrays("a:\n# note: |\n  2024-01-01: x\n"));
        $this->assertSame(['a' => "it's", 'd' => ['2024-01-01' => 1]], Yaml::decodeToArrays("a: it's\nd:\n  2024-01-01: 1\n"));
        $this->assertSame(['a' => 'x', 'd' => ['2024-01-01' => 1]], Yaml::decodeToArrays("a: 'x'\nd:\n  2024-01-01: 1\n"));
        $this->assertSame(['a' => 'a >', 'd' => ['2024-01-01' => 1]], Yaml::decodeToArrays("a: a >\nd:\n  2024-01-01: 1\n"));
    }

    public function test_nested_list_date_keys_stay_strings(): void
    {
        $this->assertSame([[['2024-01-01' => 'x']]], Yaml::decodeToArrays("- - 2024-01-01: x\n"));
    }
}
