<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Infolists;

use Closure;
use Filament\Infolists\Components\CodeEntry;
use Illuminate\Support\Collection;
use Illuminate\Support\Js;
use Phiki\Grammar\Grammar;
use Phiki\Phiki;
use Phiki\Theme\Theme;

/**
 * Shared behaviour of {@see JsonEntry} and {@see YamlEntry}: server-side highlighting with
 * Filament's own Phiki set-up (so both themes follow dark mode), a collapsible block, a
 * copy button and a height cap. No JavaScript bundle is needed.
 */
abstract class CodeBlockEntry extends CodeEntry
{
    protected bool|Closure $isCollapsible = true;

    protected bool|Closure $isCollapsed = false;

    protected string|Closure|null $maxHeight = '24rem';

    /** Text shown in the summary line; defaults to the entry's language name. */
    protected string|Closure|null $summary = null;

    abstract protected function languageName(): string;

    abstract protected function defaultGrammar(): Grammar;

    /**
     * The state as text in this entry's language.
     */
    abstract protected function toText(mixed $state): string;

    protected function setUp(): void
    {
        parent::setUp();

        $this->copyable();
    }

    public function collapsible(bool|Closure $condition = true): static
    {
        $this->isCollapsible = $condition;

        return $this;
    }

    public function collapsed(bool|Closure $condition = true): static
    {
        $this->isCollapsed = $condition;

        return $this;
    }

    public function isCollapsible(): bool
    {
        return (bool) $this->evaluate($this->isCollapsible);
    }

    public function isCollapsed(): bool
    {
        return $this->isCollapsible() && (bool) $this->evaluate($this->isCollapsed);
    }

    /**
     * A CSS length; the block scrolls past it. Null removes the cap.
     */
    public function maxHeight(string|Closure|null $height): static
    {
        $this->maxHeight = $height;

        return $this;
    }

    public function getMaxHeight(): ?string
    {
        $height = $this->evaluate($this->maxHeight);

        return is_string($height) && $height !== '' ? $height : null;
    }

    public function summary(string|Closure|null $summary): static
    {
        $this->summary = $summary;

        return $this;
    }

    public function getSummary(): string
    {
        return (string) ($this->evaluate($this->summary) ?? $this->languageName());
    }

    public function toEmbeddedHtml(): string
    {
        $state = $this->getState();

        if ($state instanceof Collection) {
            $state = $state->all();
        }

        if (blank($state) && $state !== 0 && $state !== '0' && $state !== false) {
            $placeholder = $this->getPlaceholder();

            ob_start(); ?>

            <div <?= $this->getExtraAttributeBag()->class(['fi-in-code'])->toHtml() ?>>
                <?php if (filled($placeholder)) { ?>
                    <p class="fi-in-placeholder"><?= e($placeholder) ?></p>
                <?php } ?>
            </div>

            <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
        }

        $text = $this->toText($state);

        $html = (string) (new Phiki)->codeToHtml($text, $this->getGrammar() ?? $this->defaultGrammar(), [
            'light' => $this->getLightTheme() ?? Theme::GithubLight,
            'dark' => $this->getDarkTheme() ?? Theme::GithubDarkHighContrast,
        ]);

        $isCopyable = $this->isCopyable($state);
        $maxHeight = $this->getMaxHeight();
        $summary = $this->getSummary();

        $attributes = $this->getExtraAttributeBag()
            ->class(['fi-in-code', 'fi-in-jye-code'])
            ->style([$maxHeight !== null ? '--jye-max-height: '.$maxHeight : null]);

        ob_start(); ?>

        <?php $head = function () use ($isCopyable, $text, $state, $summary): void { ?>
            <span><?= e($summary) ?></span>
            <?php if ($isCopyable) { ?>
                <button
                    type="button"
                    class="jye-copy"
                    x-data="{ copied: false }"
                    x-on:click.prevent.stop="
                        window.navigator.clipboard.writeText(<?= e(Js::from($this->getCopyableState($state) ?? $text)) ?>)
                        copied = true
                        setTimeout(() => (copied = false), <?= (int) $this->getCopyMessageDuration($state) ?>)
                    "
                    x-text="copied ? <?= e(Js::from($this->getCopyMessage($state))) ?> : <?= e(Js::from(__('json-yaml-editor::messages.entry.copy'))) ?>"
                ><?= e(__('json-yaml-editor::messages.entry.copy')) ?></button>
            <?php } ?>
        <?php }; ?>

        <?php if ($this->isCollapsible()) { ?>
            <details <?= $attributes->toHtml() ?> <?= $this->isCollapsed() ? '' : 'open' ?>>
                <summary><?php $head(); ?></summary>
                <?= $html ?>
            </details>
        <?php } else { ?>
            <div <?= $attributes->toHtml() ?>>
                <div class="jye-entry-head" style="display: flex; justify-content: space-between; margin-bottom: 0.25rem"><?php $head(); ?></div>
                <?= $html ?>
            </div>
        <?php } ?>

        <?php return $this->wrapEmbeddedHtml((string) ob_get_clean());
    }
}
