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
     * Like decode(), but for a value that is stored as JSON: maps become associative arrays,
     * except empty ones, which stay an empty `stdClass` (so `{}` is stored as `{}`, not as
     * `[]`, the same as {@see Json::decode()}). A map with the keys 0..n still becomes a list.
     */
    public static function decodeToArrays(string $text): mixed
    {
        return Json::toArrays(self::decode($text, SymfonyYaml::PARSE_OBJECT_FOR_MAP));
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

        return $keepDates ? $value : self::datesToStrings($value, $text, $flags);
    }

    /**
     * An empty object (an empty `stdClass`) is dumped as `{}`; everything else as normalize()
     * makes it.
     */
    public static function encode(mixed $value, int $inline = 10, int $indent = 2, int $flags = 0): string
    {
        // @phpstan-ignore argument.type
        $yaml = SymfonyYaml::dump(self::normalizeValue($value, true), $inline, $indent, $flags | SymfonyYaml::DUMP_OBJECT_AS_MAP);

        return rtrim($yaml)."\n";
    }

    /**
     * Collections, ArrayObjects, JsonSerializable and plain objects become arrays: symfony
     * dumps any object as `null` otherwise. Dates are left for symfony to dump.
     */
    public static function normalize(mixed $value): mixed
    {
        return self::normalizeValue($value, false);
    }

    /**
     * @param bool $keepEmptyObjects keep an empty `stdClass` (dumped as `{}` by encode())
     */
    private static function normalizeValue(mixed $value, bool $keepEmptyObjects): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if ($keepEmptyObjects && $value instanceof stdClass && get_object_vars($value) === []) {
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
            return array_map(static fn (mixed $item): mixed => self::normalizeValue($item, $keepEmptyObjects), $value);
        }

        return $value;
    }

    /**
     * DateTime values (what `PARSE_DATETIME` produces) as ISO strings: a plain date as `Y-m-d`,
     * anything else with the time and offset. symfony parses `2024-01-01` and
     * `2024-01-01T00:00:00Z` to the same value; with the `$source` text a midnight UTC value
     * keeps its time when that very scalar was written with one (see timeMarks()). Without
     * it, every midnight UTC value is taken for a plain date.
     */
    public static function datesToStrings(mixed $value, ?string $source = null, int $flags = 0): mixed
    {
        $marks = $source !== null && self::hasMidnight($value) ? self::timeMarks($source, $flags) : null;

        return self::convertDates($value, $marks);
    }

    /**
     * The source parsed again with a fraction of a second added to every timestamp that has a
     * time. Text in comments and quoted strings changes too, but only the values at the
     * positions of parsed dates are read, so a midnight date-time is told from a plain date
     * by its own scalar, not by some other place in the text that looks the same.
     */
    private static function timeMarks(string $source, int $flags): mixed
    {
        $marked = preg_replace(
            '/(?<![\\d-])(\\d{4}-\\d\\d?-\\d\\d?(?:[Tt]|[ \\t]+)\\d\\d?:\\d\\d:\\d\\d)(?:\\.\\d*)?/',
            '$1.5',
            $source,
        );

        if (!is_string($marked)) {
            return null;
        }

        try {
            // @phpstan-ignore argument.type
            return SymfonyYaml::parse($marked, $flags | SymfonyYaml::PARSE_DATETIME);
        } catch (ParseException) {
            return null;
        }
    }

    private static function hasMidnight(mixed $value): bool
    {
        if ($value instanceof DateTimeInterface) {
            return self::isMidnightUtc($value);
        }

        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                if (self::hasMidnight($item)) {
                    return true;
                }
            }
        }

        return false;
    }

    private static function isMidnightUtc(DateTimeInterface $value): bool
    {
        return $value->format('H:i:s.u P') === '00:00:00.000000 +00:00';
    }

    /**
     * @param mixed $marks the same document parsed by timeMarks(), walked in step with $value
     */
    private static function convertDates(mixed $value, mixed $marks): mixed
    {
        if ($value instanceof DateTimeInterface) {
            if (self::isMidnightUtc($value)) {
                $hadTime = $marks instanceof DateTimeInterface && (int) $marks->format('u') !== 0;

                return $value->format($hadTime ? 'Y-m-d\\TH:i:sP' : 'Y-m-d');
            }

            return $value->format((int) $value->format('u') === 0 ? 'Y-m-d\\TH:i:sP' : 'Y-m-d\\TH:i:s.uP');
        }

        if (is_array($value)) {
            $markList = is_array($marks) && count($marks) === count($value) ? array_values($marks) : [];
            $index = 0;

            foreach ($value as $key => $item) {
                $value[$key] = self::convertDates($item, $markList[$index++] ?? null);
            }

            return $value;
        }

        if ($value instanceof stdClass) {
            $markVars = $marks instanceof stdClass ? array_values(get_object_vars($marks)) : [];
            $vars = get_object_vars($value);
            $markVars = count($markVars) === count($vars) ? $markVars : [];
            $index = 0;

            foreach ($vars as $key => $item) {
                $value->{$key} = self::convertDates($item, $markVars[$index++] ?? null);
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
