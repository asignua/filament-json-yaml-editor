<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Concerns;

use Closure;

/**
 * Options shared by {@see \Asignua\FilamentJsonYamlEditor\Forms\JsonEditor} and
 * {@see \Asignua\FilamentJsonYamlEditor\Forms\YamlEditor}.
 */
trait ConfiguresEditor
{
    protected int|string|Closure $height = '20rem';

    protected bool|Closure $asArray = false;

    protected bool|Closure $wrapLines = false;

    /**
     * Height of the editing area: a CSS length (`'30rem'`) or a number of pixels.
     */
    public function height(int|string|Closure $height): static
    {
        $this->height = $height;

        return $this;
    }

    public function getHeight(): string
    {
        $height = $this->evaluate($this->height);

        return is_int($height) ? $height.'px' : (string) $height;
    }

    /**
     * Cast the state: the form hands the model an array (decoded on save) instead of the
     * raw text. An array coming from the model is always shown as text, whatever this says.
     */
    public function asArray(bool|Closure $condition = true): static
    {
        $this->asArray = $condition;

        return $this;
    }

    public function isArray(): bool
    {
        return (bool) $this->evaluate($this->asArray);
    }

    public function wrapLines(bool|Closure $condition = true): static
    {
        $this->wrapLines = $condition;

        return $this;
    }

    public function shouldWrapLines(): bool
    {
        return (bool) $this->evaluate($this->wrapLines);
    }

    /**
     * @return array<string, mixed>
     */
    protected function commonAlpineConfig(string $language): array
    {
        return [
            'language' => $language,
            'isDisabled' => $this->isDisabled(),
            'isLive' => $this->isLive(),
            'isLiveDebounced' => $this->isLiveDebounced(),
            'isLiveOnBlur' => $this->isLiveOnBlur(),
            'label' => $this->getLabel(),
            'liveDebounce' => $this->getLiveDebounce(),
            'canWrap' => $this->shouldWrapLines(),
        ];
    }

    /**
     * Strings the browser component needs, in the current locale.
     *
     * @return array<string, mixed>
     */
    protected function editorLabels(): array
    {
        /** @var array<string, mixed> $labels */
        $labels = __('json-yaml-editor::messages.editor');

        return $labels;
    }
}
