<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Forms;

use Asignua\FilamentJsonYamlEditor\Concerns\ConfiguresEditor;
use Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule;
use Asignua\FilamentJsonYamlEditor\Rules\YamlRule;
use Asignua\FilamentJsonYamlEditor\Support\Yaml;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

/**
 * A YAML field: highlighting, folding, line numbers and a live lint (js-yaml) that shows
 * the error line. The server checks the same text with symfony/yaml.
 *
 *     YamlEditor::make('config')->asArray()->inline(4)->indent(2)
 */
class YamlEditor extends Field implements HasEmbeddedView
{
    use ConfiguresEditor;
    use HasExtraAlpineAttributes;

    protected int|Closure $inline = 10;

    protected int|Closure $indent = 2;

    protected int|Closure $dumpFlags = 0;

    protected bool|Closure $validatesSyntax = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (YamlEditor $component, mixed $state): void {
            if ($state === null || is_string($state)) {
                return;
            }

            $component->state(Yaml::encode($state, $component->getInline(), $component->getIndent(), $component->getDumpFlags()));
        });

        $this->dehydrateStateUsing(function (YamlEditor $component, mixed $state): mixed {
            if (!is_string($state)) {
                return $state;
            }

            if (trim($state) === '') {
                return null;
            }

            return $component->isArray() ? Yaml::decode($state) : $state;
        });

        // With ->asArray() the syntax rule stays even after validateSyntax(false): invalid text
        // would decode to null on save and overwrite the stored value without a word.
        $this->rule(fn (): array => $this->shouldValidateSyntax() || $this->isArray() ? [YamlRule::make()] : []);
    }

    /**
     * The nesting level from which `Yaml::dump()` switches to the inline `{a: 1}` style,
     * used when an array from the model is shown as text. Default 10 (block style).
     */
    public function inline(int|Closure $level): static
    {
        $this->inline = $level;

        return $this;
    }

    public function getInline(): int
    {
        return (int) $this->evaluate($this->inline);
    }

    /**
     * Spaces per level when an array from the model is shown as text.
     */
    public function indent(int|Closure $spaces): static
    {
        $this->indent = $spaces;

        return $this;
    }

    public function getIndent(): int
    {
        return max(1, (int) $this->evaluate($this->indent));
    }

    /**
     * `Symfony\Component\Yaml\Yaml::DUMP_*` flags for the same conversion.
     */
    public function dumpFlags(int|Closure $flags): static
    {
        $this->dumpFlags = $flags;

        return $this;
    }

    public function getDumpFlags(): int
    {
        return (int) $this->evaluate($this->dumpFlags);
    }

    /**
     * Turn the automatic "is valid YAML" rule off (the lint in the browser stays). Ignored
     * with ->asArray(): text that does not parse cannot be stored as an array.
     */
    public function validateSyntax(bool|Closure $condition = true): static
    {
        $this->validatesSyntax = $condition;

        return $this;
    }

    public function shouldValidateSyntax(): bool
    {
        return (bool) $this->evaluate($this->validatesSyntax);
    }

    /**
     * Validate the parsed document against a JSON Schema on the server (needs
     * `opis/json-schema` or `justinrainbow/json-schema`).
     *
     * @param array<string, mixed>|Closure|object|string $schema
     */
    public function schema(mixed $schema): static
    {
        $this->rule(fn (): JsonSchemaRule => JsonSchemaRule::make($this->evaluate($schema))->yaml());

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        /** @var view-string $view */
        $view = 'json-yaml-editor::editor';

        $config = [
            ...$this->commonAlpineConfig('yaml'),
            'modes' => ['code'],
            'mode' => 'code',
            'indent' => $this->getIndent(),
            'labels' => $this->editorLabels(),
        ];

        return $this->wrapEmbeddedHtml(
            $this->wrapInputHtml(
                view($view, [
                    'field' => $this,
                    'config' => $config,
                    'modes' => [],
                    'showFormat' => false,
                    'height' => $this->getHeight(),
                    'language' => 'yaml',
                ])->render(),
                attributes: $this->getExtraAttributeBag()->class(['fi-fo-yaml-editor']),
            ),
            labelTag: 'div',
        );
    }
}
