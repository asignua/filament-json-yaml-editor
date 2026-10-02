<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Infolists;

use Asignua\FilamentJsonYamlEditor\Support\Json;
use Phiki\Grammar\Grammar;

/**
 * Read-only, highlighted JSON for infolists. The state may be an array, an object or a JSON
 * string; it is shown pretty-printed.
 *
 *     JsonEntry::make('settings')->collapsed()->maxHeight('16rem')
 */
class JsonEntry extends CodeBlockEntry
{
    protected function languageName(): string
    {
        return 'JSON';
    }

    protected function defaultGrammar(): Grammar
    {
        return Grammar::Json;
    }

    protected function toText(mixed $state): string
    {
        if (is_string($state)) {
            // Valid JSON text is re-indented without changing a value (big integers keep their
            // digits); anything else is shown as it is.
            return Json::reformat($state);
        }

        return Json::encode($state);
    }
}
