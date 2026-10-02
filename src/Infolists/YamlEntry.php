<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Infolists;

use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use Closure;
use Phiki\Grammar\Grammar;

/**
 * Read-only, highlighted YAML for infolists. The state may be an array or a YAML string
 * (shown as it is, comments included).
 *
 *     YamlEntry::make('config')->inline(4)->collapsed()
 */
class YamlEntry extends CodeBlockEntry
{
    protected int|Closure $inline = 10;

    protected int|Closure $indent = 2;

    /**
     * The nesting level from which an array state is dumped in the inline `{a: 1}` style.
     */
    public function inline(int|Closure $level): static
    {
        $this->inline = $level;

        return $this;
    }

    public function indent(int|Closure $spaces): static
    {
        $this->indent = $spaces;

        return $this;
    }

    protected function languageName(): string
    {
        return 'YAML';
    }

    protected function defaultGrammar(): Grammar
    {
        return Grammar::Yaml;
    }

    protected function toText(mixed $state): string
    {
        return is_string($state)
            ? $state
            : Yaml::encode($state, (int) $this->evaluate($this->inline), max(1, (int) $this->evaluate($this->indent)));
    }
}
