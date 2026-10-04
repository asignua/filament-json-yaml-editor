<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Concerns;

use Asignua\FilamentJsonYamlEditor\Support\Json;
use Asignua\FilamentJsonYamlEditor\Support\Yaml;
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

        // spatie/laravel-translatable reports an `array` cast for every translatable attribute;
        // an array handed to it would be taken for a locale map and overwrite the translations.
        if (method_exists($model, 'isTranslatableAttribute') && $model->isTranslatableAttribute($name)) {
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

    /**
     * The stored JSON of an unchanged record attribute with a JSON cast. The cast decodes to
     * arrays, which turns `{}` into `[]` (and big integers into floats): showing that would
     * change the value on the next save, so the field builds its text from this instead.
     *
     * Only when the hydrated state is what the cast makes of that column: a page that filled
     * the form with its own data (mutateFormDataBeforeFill(), an action's fillForm(), fill()
     * with custom data) gets its own state shown, not the stored column.
     */
    protected function rawColumnJson(mixed $state): ?string
    {
        if (!$this->modelCastsToArray()) {
            return null;
        }

        $record = $this->getRecord();
        $name = $this->getName();

        if (!$record instanceof Model || !$record->isClean($name)) {
            return null;
        }

        $raw = $record->getRawOriginal($name);

        // Encrypted casts store ciphertext: that is not JSON, and the array is used instead.
        if (!is_string($raw) || trim($raw) === '' || !Json::check($raw)[0]) {
            return null;
        }

        // The casts decode with json_decode() defaults, so the same column gives the same
        // scalars (big integers as the same floats); objects are compared as arrays.
        if (json_decode($raw, true) !== Yaml::normalize($state)) {
            return null;
        }

        return $raw;
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
