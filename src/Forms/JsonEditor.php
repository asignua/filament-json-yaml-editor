<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Forms;

use Asignua\FilamentJsonYamlEditor\Concerns\ConfiguresEditor;
use Asignua\FilamentJsonYamlEditor\Enums\EditorMode;
use Asignua\FilamentJsonYamlEditor\Rules\JsonRule;
use Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule;
use Asignua\FilamentJsonYamlEditor\Support\Json;
use Closure;
use Filament\Forms\Components\Field;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Concerns\HasExtraAlpineAttributes;

/**
 * A JSON field with a code view (highlighting, line numbers, folding, live lint with the
 * error line) and a tree view (expand / collapse, edit values, add and remove keys).
 *
 *     JsonEditor::make('settings')->height('24rem')->asArray()->schema($schema)
 */
class JsonEditor extends Field implements HasEmbeddedView
{
    use ConfiguresEditor;
    use HasExtraAlpineAttributes;

    /** @var Closure|list<EditorMode> */
    protected array|Closure $modes = [EditorMode::Code, EditorMode::Tree];

    protected EditorMode|Closure $defaultMode = EditorMode::Code;

    protected bool|Closure $showFormatButton = true;

    protected bool|Closure $validatesSyntax = true;

    protected int|Closure $indent = 2;

    protected bool|Closure $onlyContainers = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->afterStateHydrated(function (JsonEditor $component, mixed $state): void {
            if ($state === null) {
                return;
            }

            // A top-level string stored by an array cast (`"123"`, `"hello"`) must come back
            // as JSON text with its quotes, not as the bare string, which would change type.
            if (is_string($state)) {
                $text = $component->isArray() ? ($component->rawColumnText($state) ?? $component->storedStringJson($state)) : null;

                if ($text !== null) {
                    $component->state($text);
                }

                return;
            }

            $component->state($component->rawColumnText($state) ?? Json::encode($state, $component->getIndent()));
        });

        $this->dehydrateStateUsing(function (JsonEditor $component, mixed $state): mixed {
            if (!is_string($state)) {
                return $state;
            }

            if (trim($state) === '') {
                return null;
            }

            return $component->isArray() ? Json::decode($state) : $state;
        });

        // With ->asArray() the syntax rule stays even after validateSyntax(false): invalid text
        // would decode to null on save and overwrite the stored value without a word.
        $this->rule(fn (): array => $this->shouldValidateSyntax() || $this->isArray()
            ? [JsonRule::make()->containerOnly((bool) $this->evaluate($this->onlyContainers))]
            : []);
    }

    /**
     * The stored JSON of an unchanged record attribute with a JSON cast, re-indented (see
     * {@see ConfiguresEditor::rawColumnJson()}).
     */
    protected function rawColumnText(mixed $state): ?string
    {
        $raw = $this->rawColumnJson($state);

        return $raw === null ? null : Json::reformat($raw, $this->getIndent());
    }

    /**
     * Which views the user can switch between. One mode hides the switch.
     *
     * @param Closure|list<EditorMode> $modes
     */
    public function modes(array|Closure $modes): static
    {
        $this->modes = $modes;

        return $this;
    }

    /**
     * @return list<EditorMode>
     */
    public function getModes(): array
    {
        $modes = array_values(array_unique($this->evaluate($this->modes), SORT_REGULAR));

        return $modes === [] ? [EditorMode::Code] : $modes;
    }

    public function defaultMode(EditorMode|Closure $mode): static
    {
        $this->defaultMode = $mode;

        return $this;
    }

    public function getDefaultMode(): EditorMode
    {
        $mode = $this->evaluate($this->defaultMode);
        $modes = $this->getModes();

        return in_array($mode, $modes, true) ? $mode : $modes[0];
    }

    /**
     * Show the "Format" button that pretty-prints the document.
     */
    public function format(bool|Closure $condition = true): static
    {
        $this->showFormatButton = $condition;

        return $this;
    }

    public function hasFormatButton(): bool
    {
        return (bool) $this->evaluate($this->showFormatButton);
    }

    /**
     * Spaces per level, used by the Format button, the tree view and when an array is shown.
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
     * Turn the automatic "is valid JSON" rule off (the lint in the browser stays). Ignored
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
     * Only an object or an array is accepted at the top level.
     */
    public function onlyContainers(bool|Closure $condition = true): static
    {
        $this->onlyContainers = $condition;

        return $this;
    }

    /**
     * Validate against a JSON Schema on the server (needs `opis/json-schema` or
     * `justinrainbow/json-schema`).
     *
     * @param array<string, mixed>|Closure|object|string $schema
     */
    public function schema(mixed $schema): static
    {
        $this->rule(fn (): JsonSchemaRule => JsonSchemaRule::make($this->evaluate($schema)));

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        /** @var view-string $view */
        $view = 'json-yaml-editor::editor';

        $modes = $this->getModes();

        $config = [
            ...$this->commonAlpineConfig('json'),
            'modes' => array_map(static fn (EditorMode $mode): string => $mode->value, $modes),
            'mode' => $this->getDefaultMode()->value,
            'indent' => $this->getIndent(),
            'labels' => $this->editorLabels(),
        ];

        return $this->wrapEmbeddedHtml(
            $this->wrapInputHtml(
                view($view, [
                    'field' => $this,
                    'config' => $config,
                    'modes' => $modes,
                    'showFormat' => $this->hasFormatButton() && !$this->isDisabled(),
                    'height' => $this->getHeight(),
                    'language' => 'json',
                ])->render(),
                attributes: $this->getExtraAttributeBag()->class(['fi-fo-json-editor']),
            ),
            labelTag: 'div',
        );
    }
}
