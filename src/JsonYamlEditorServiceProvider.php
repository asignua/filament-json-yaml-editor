<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor;

use Filament\Support\Assets\AlpineComponent;
use Filament\Support\Assets\Css;
use Filament\Support\Facades\FilamentAsset;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class JsonYamlEditorServiceProvider extends PackageServiceProvider
{
    public const string PACKAGE = 'asignua/filament-json-yaml-editor';

    public const string COMPONENT = 'json-yaml-editor';

    public const string STYLESHEET = 'filament-json-yaml-editor';

    public static string $name = 'filament-json-yaml-editor';

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)->hasViews('json-yaml-editor');
    }

    public function packageBooted(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'json-yaml-editor');

        $this->publishes([
            __DIR__.'/../resources/lang' => $this->app->langPath('vendor/json-yaml-editor'),
        ], 'filament-json-yaml-editor-translations');

        FilamentAsset::register([
            // The editor bundle (CodeMirror 6, js-yaml, the tree view) is fetched only by a page
            // that renders an editor: Alpine's x-load requests it on demand.
            AlpineComponent::make(self::COMPONENT, __DIR__.'/../resources/dist/json-yaml-editor.js'),
            Css::make(self::STYLESHEET, __DIR__.'/../resources/dist/filament-json-yaml-editor.css'),
        ], self::PACKAGE);
    }
}
