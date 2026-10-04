<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Support;

use JsonException;
use stdClass;

/**
 * JSON conversions shared by the editor field, the entry and the rules.
 */
final class Json
{
    /**
     * Whether the text is valid JSON; the second element is the engine's message otherwise.
     *
     * @return array{bool, string|null}
     */
    public static function check(string $text, int $depth = 512): array
    {
        try {
            json_decode($text, false, max(1, $depth), JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            return [false, $exception->getMessage()];
        }

        return [true, null];
    }

    /**
     * Decodes to arrays (or scalars). Blank text and invalid JSON give null. An empty object
     * `{}` stays an object (an empty stdClass), so it is stored as `{}`, not as `[]`; integers
     * beyond PHP_INT_MAX come back as numeric strings instead of losing digits to a float (so
     * their type changes: they are stored, shown and validated as strings from then on).
     */
    public static function decode(string $text): mixed
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return self::toArrays(json_decode($text, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Objects to associative arrays, except empty ones: an empty `stdClass` is kept, so it is
     * encoded as `{}`, not as `[]`.
     */
    public static function toArrays(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $vars = get_object_vars($value);

            return $vars === [] ? $value : array_map(self::toArrays(...), $vars);
        }

        return is_array($value) ? array_map(self::toArrays(...), $value) : $value;
    }

    /**
     * Re-indents valid JSON text the way encode() prints, without a decode / encode round trip:
     * numbers are kept as written (`12345678901234567890` keeps its digits, `1.0` its fraction),
     * `{}` stays an object. Strings are re-escaped like encode() does. Invalid JSON is returned
     * as it is.
     */
    public static function reformat(string $text, int $indent = 2): string
    {
        if (!self::check($text)[0]) {
            return $text;
        }

        $out = '';
        $depth = 0;
        $length = strlen($text);
        $newline = static fn (int $depth): string => "\n".str_repeat(' ', $depth * max(1, $indent));

        for ($i = 0; $i < $length; $i++) {
            $char = $text[$i];

            if ($char === '"') {
                $end = $i + 1;

                while ($text[$end] !== '"') {
                    $end += $text[$end] === '\\' ? 2 : 1;
                }

                $out .= (string) json_encode(
                    json_decode(substr($text, $i, $end - $i + 1)),
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
                );
                $i = $end;
            } elseif ($char === '{' || $char === '[') {
                $next = $i + 1 + strspn($text, " \t\r\n", $i + 1);

                if ($text[$next] === ($char === '{' ? '}' : ']')) {
                    $out .= $char.$text[$next];
                    $i = $next;
                } else {
                    $out .= $char.$newline(++$depth);
                }
            } elseif ($char === '}' || $char === ']') {
                $out .= $newline(--$depth).$char;
            } elseif ($char === ',') {
                $out .= ','.$newline($depth);
            } elseif ($char === ':') {
                $out .= ': ';
            } elseif (!in_array($char, [' ', "\t", "\r", "\n"], true)) {
                $out .= $char;
            }
        }

        return $out;
    }

    /**
     * Pretty-printed, unescaped unicode and slashes: readable in the editor and in the database.
     */
    public static function encode(mixed $value, int $indent = 2): string
    {
        // A legacy row with broken UTF-8 (or an INF) must not open as a blank field: blank is
        // saved as null and would wipe the record. Substitute what cannot be encoded instead.
        $json = json_encode(
            $value,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
            | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );

        if ($json === false) {
            return '';
        }

        // PHP pretty-prints with four spaces.
        return $indent === 4 ? $json : (string) preg_replace_callback(
            '/^( +)/m',
            static fn (array $match): string => str_repeat(' ', intdiv(strlen($match[1]), 4) * $indent),
            $json,
        );
    }
}
