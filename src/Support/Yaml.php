<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Support;

use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;

/**
 * YAML conversions shared by the editor field, the entry and the rules. Parsing never
 * instantiates objects: no `PARSE_OBJECT` flag is ever passed.
 */
final class Yaml
{
    /**
     * @return array{bool, string|null} valid?, and the parser's message (with the line) otherwise
     */
    public static function check(string $text): array
    {
        try {
            SymfonyYaml::parse($text);
        } catch (ParseException $exception) {
            return [false, $exception->getMessage()];
        }

        return [true, null];
    }

    /**
     * Blank text and invalid YAML give null.
     */
    public static function decode(string $text, int $flags = 0): mixed
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            // @phpstan-ignore argument.type
            return SymfonyYaml::parse($text, $flags);
        } catch (ParseException) {
            return null;
        }
    }

    public static function encode(mixed $value, int $inline = 10, int $indent = 2, int $flags = 0): string
    {
        // @phpstan-ignore argument.type
        $yaml = SymfonyYaml::dump($value, $inline, $indent, $flags);

        return rtrim($yaml)."\n";
    }
}
