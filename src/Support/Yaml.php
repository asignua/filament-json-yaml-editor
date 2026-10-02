<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Support;

use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;
use stdClass;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml as SymfonyYaml;
use Traversable;

/**
 * YAML conversions shared by the editor field, the entry and the rules. Parsing never
 * instantiates objects: no `PARSE_OBJECT` flag is ever passed.
 */
final class Yaml
{
    /**
     * Nodes (plus string bytes) a parsed document may expand to, on top of 10 per byte of
     * source. symfony/yaml resolves aliases by reference, so a "billion laughs" document
     * parses in a millisecond and explodes later, when it is walked, encoded or dumped.
     */
    public const int EXPANSION_BUDGET = 1_000_000;

    /**
     * @return array{bool, string|null} valid?, and the parser's message (with the line) otherwise
     */
    public static function check(string $text): array
    {
        try {
            self::parse($text);
        } catch (ParseException $exception) {
            return [false, $exception->getMessage()];
        }

        return [true, null];
    }

    /**
     * Blank text and invalid YAML give null. Timestamps stay strings (`2024-01-01`), unless
     * the caller asks for `PARSE_DATETIME` and gets `DateTimeImmutable` objects.
     */
    public static function decode(string $text, int $flags = 0): mixed
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return self::parse($text, $flags);
        } catch (ParseException) {
            return null;
        }
    }

    /**
     * Parses with the expansion guard. Without `PARSE_DATETIME` in `$flags` an unquoted
     * timestamp comes back as its ISO string, not as symfony's default unix integer.
     *
     * @throws ParseException
     */
    public static function parse(string $text, int $flags = 0): mixed
    {
        $keepDates = ($flags & SymfonyYaml::PARSE_DATETIME) !== 0;

        // @phpstan-ignore argument.type
        $value = SymfonyYaml::parse($text, $flags | SymfonyYaml::PARSE_DATETIME);

        self::guardExpansion($value, self::EXPANSION_BUDGET + 10 * strlen($text));

        return $keepDates ? $value : self::datesToStrings($value);
    }

    public static function encode(mixed $value, int $inline = 10, int $indent = 2, int $flags = 0): string
    {
        // @phpstan-ignore argument.type
        $yaml = SymfonyYaml::dump(self::normalize($value), $inline, $indent, $flags);

        return rtrim($yaml)."\n";
    }

    /**
     * Collections, ArrayObjects, JsonSerializable and plain objects become arrays: symfony
     * dumps any object as `null` otherwise. Dates are left for symfony to dump.
     */
    public static function normalize(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        } elseif ($value instanceof JsonSerializable) {
            $value = $value->jsonSerialize();
        } elseif ($value instanceof Traversable) {
            $value = iterator_to_array($value);
        } elseif (is_object($value)) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            return array_map(self::normalize(...), $value);
        }

        return $value;
    }

    /**
     * DateTime values (what `PARSE_DATETIME` produces) as ISO strings: a date at midnight UTC
     * as `Y-m-d`, anything else with the time and offset.
     */
    public static function datesToStrings(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            if ($value->format('H:i:s.u P') === '00:00:00.000000 +00:00') {
                return $value->format('Y-m-d');
            }

            return $value->format((int) $value->format('u') === 0 ? 'Y-m-d\TH:i:sP' : 'Y-m-d\TH:i:s.uP');
        }

        if (is_array($value)) {
            return array_map(self::datesToStrings(...), $value);
        }

        if ($value instanceof stdClass) {
            foreach (get_object_vars($value) as $key => $item) {
                $value->{$key} = self::datesToStrings($item);
            }
        }

        return $value;
    }

    /**
     * Walks the parsed value against a budget instead of materialising it, so the cost of a
     * hostile document is bounded by the budget, not by its expanded size.
     *
     * @throws ParseException
     */
    private static function guardExpansion(mixed $value, int $budget): void
    {
        $stack = [$value];

        while ($stack !== []) {
            $item = array_pop($stack);
            $budget -= is_string($item) ? 1 + strlen($item) : 1;

            if ($budget < 0) {
                throw new ParseException('The document expands to too much data (aliases repeated too many times).');
            }

            if (is_array($item)) {
                foreach ($item as $child) {
                    $stack[] = $child;
                }
            } elseif ($item instanceof stdClass) {
                foreach (get_object_vars($item) as $child) {
                    $stack[] = $child;
                }
            }
        }
    }
}
