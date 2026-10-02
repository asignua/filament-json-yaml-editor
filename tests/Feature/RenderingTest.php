<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Feature;

use Asignua\FilamentJsonYamlEditor\JsonYamlEditorServiceProvider;
use Asignua\FilamentJsonYamlEditor\Tests\TestCase;
use Filament\Support\Facades\FilamentAsset;
use Livewire\Livewire;
use Workbench\App\Filament\Resources\Settings\Pages\CreateSetting;
use Workbench\App\Filament\Resources\Settings\Pages\ViewSetting;

class RenderingTest extends TestCase
{
    public function test_the_alpine_component_is_registered_and_shipped(): void
    {
        $this->assertFileExists(__DIR__.'/../../resources/dist/json-yaml-editor.js');
        $this->assertFileExists(__DIR__.'/../../resources/dist/filament-json-yaml-editor.css');

        $src = FilamentAsset::getAlpineComponentSrc(JsonYamlEditorServiceProvider::COMPONENT, JsonYamlEditorServiceProvider::PACKAGE);

        $this->assertStringContainsString('json-yaml-editor', $src);
    }

    public function test_the_editor_font_does_not_depend_on_filaments_mono_variable(): void
    {
        // Filament defines --mono-font-family as the QUOTED 'ui-monospace', which the browser treats as a family
        // name that does not exist: the editor fell back to a serif font.
        $css = (string) file_get_contents(__DIR__.'/../../resources/dist/filament-json-yaml-editor.css');

        $this->assertStringNotContainsString('--mono-font-family', $css);
        $this->assertMatchesRegularExpression('/font-family:\s*ui-monospace[^;]*monospace;/', $css);
    }

    public function test_the_bundle_is_a_default_export_alpine_component(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../resources/dist/json-yaml-editor.js');

        $this->assertMatchesRegularExpression('/export\s*\{[^}]*as default\}|export default/', $js);
    }

    public function test_the_form_lazy_loads_the_bundle(): void
    {
        $html = Livewire::test(CreateSetting::class)->html();

        $this->assertStringContainsString('x-load', $html);
        $this->assertStringContainsString('x-load-src', $html);
        $this->assertStringContainsString('json-yaml-editor', $html);
        $this->assertStringContainsString('jsonYamlEditorFormComponent', $html);
        $this->assertStringContainsString('"language":"json"', str_replace(['\\u0022', '\\'], ['"', ''], $html));
        $this->assertStringContainsString('fi-fo-json-editor', $html);
        $this->assertStringContainsString('fi-fo-yaml-editor', $html);
        // Mode switch + format button for the first (two-mode) field only.
        $this->assertStringContainsString("setView('tree')", $html);
        $this->assertStringContainsString('format()', $html);
        $this->assertSame(1, substr_count($html, "setView('tree')"));
    }

    public function test_the_stylesheet_is_linked_in_the_panel(): void
    {
        $html = $this->get('/admin/settings/create')->assertOk()->getContent();

        $this->assertStringContainsString(
            e(FilamentAsset::getStyleHref(JsonYamlEditorServiceProvider::STYLESHEET, JsonYamlEditorServiceProvider::PACKAGE)),
            (string) $html,
        );
    }

    public function test_entries_render_highlighted_and_collapsible(): void
    {
        $setting = $this->setting();

        $html = Livewire::test(ViewSetting::class, ['record' => $setting->getKey()])->html();

        $this->assertStringContainsString('fi-in-jye-code', $html);
        $this->assertStringContainsString('<details', $html);
        $this->assertStringContainsString('phiki', $html);
        $this->assertStringContainsString('Демо', $html);
        $this->assertStringContainsString('jye-copy', $html);
        $this->assertStringContainsString('JSON', $html);
        $this->assertStringContainsString('YAML', $html);
        $this->assertStringContainsString(__('json-yaml-editor::messages.entry.copy'), $html);
        // The YAML entry is ->collapsed(): its <details> has no `open`, the JSON one has.
        $this->assertSame(1, preg_match_all('/<details[^>]*\sopen/', $html));
    }
}
