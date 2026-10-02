<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings;

use Asignua\FilamentJsonYamlEditor\Enums\EditorMode;
use Asignua\FilamentJsonYamlEditor\Forms\JsonEditor;
use Asignua\FilamentJsonYamlEditor\Forms\YamlEditor;
use Asignua\FilamentJsonYamlEditor\Infolists\JsonEntry;
use Asignua\FilamentJsonYamlEditor\Infolists\YamlEntry;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Workbench\App\Filament\Resources\Settings\Pages\CreateSetting;
use Workbench\App\Filament\Resources\Settings\Pages\EditSetting;
use Workbench\App\Filament\Resources\Settings\Pages\ListSettings;
use Workbench\App\Filament\Resources\Settings\Pages\ViewSetting;
use Workbench\App\Models\Setting;

class SettingResource extends Resource
{
    protected static ?string $model = Setting::class;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name'),
            JsonEditor::make('settings')->asArray()->height(300)->modes([EditorMode::Code, EditorMode::Tree]),
            JsonEditor::make('settings_text')->format(false)->modes([EditorMode::Code]),
            YamlEditor::make('config'),
            YamlEditor::make('config_data')->asArray()->inline(2),
            YamlEditor::make('meta'),
            YamlEditor::make('options')->asArray(),
            JsonEditor::make('extras')->modes([EditorMode::Code]),
            JsonEditor::make('strict')->asArray()->validateSyntax(false)->modes([EditorMode::Code]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            JsonEntry::make('settings'),
            YamlEntry::make('config_data')->collapsed(),
            YamlEntry::make('meta'),
            YamlEntry::make('options'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSettings::route('/'),
            'create' => CreateSetting::route('/create'),
            'view' => ViewSetting::route('/{record}'),
            'edit' => EditSetting::route('/{record}/edit'),
        ];
    }
}
