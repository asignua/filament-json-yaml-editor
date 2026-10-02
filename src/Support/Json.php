<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Support;

use JsonException;

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
     * Decodes to arrays (or scalars). Blank text and invalid JSON give null.
     */
    public static function decode(string $text): mixed
    {
        if (trim($text) === '') {
            return null;
        }

        try {
            return json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }
    }

    /**
     * Pretty-printed, unescaped unicode and slashes: readable in the editor and in the database.
     */
    public static function encode(mixed $value, int $indent = 2): string
    {
        $json = json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION);

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
