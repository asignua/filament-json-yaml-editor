<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Casts\AsEncryptedCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Options shared by {@see \Asignua\FilamentJsonYamlEditor\Forms\JsonEditor} and
 * {@see \Asignua\FilamentJsonYamlEditor\Forms\YamlEditor}.
 */
trait ConfiguresEditor
{
    protected int|string|Closure $height = '20rem';

    /** Null: decide from the model's cast (see {@see isArray()}). */
    protected bool|Closure|null $asArray = null;

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
     *
     * Not called at all, the field follows the model: a column cast to `array`, `json`,
     * `object`, `collection`, `AsArrayObject` or `AsCollection` (encrypted variants too) gets
     * an array, since handing such a cast the text would store a JSON string literal.
     * `->asArray(false)` forces text.
     */
    public function asArray(bool|Closure|null $condition = true): static
    {
        $this->asArray = $condition;

        return $this;
    }

    public function isArray(): bool
    {
        $condition = $this->evaluate($this->asArray);

        return $condition === null ? $this->modelCastsToArray() : (bool) $condition;
    }

    /**
     * Whether the attribute this field writes is cast to a structure by its Eloquent model.
     */
    protected function modelCastsToArray(): bool
    {
        if (!isset($this->container)) {
            return false;
        }

        $model = $this->getModelInstance();
        $name = $this->getName();

        if (!$model instanceof Model || str_contains($name, '.')) {
            return false;
        }

        $cast = $model->getCasts()[$name] ?? null;

        if (!is_string($cast)) {
            return false;
        }

        $cast = strtolower($cast);

        if (in_array($cast, ['array', 'json', 'json:unicode', 'object', 'collection', 'encrypted:array', 'encrypted:collection', 'encrypted:json', 'encrypted:object'], true)) {
            return true;
        }

        $class = explode(':', $cast, 2)[0];

        return in_array($class, array_map(strtolower(...), [
            AsArrayObject::class,
            AsCollection::class,
            AsEncryptedArrayObject::class,
            AsEncryptedCollection::class,
        ]), true);
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
