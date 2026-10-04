<?php

declare(strict_types=1);

namespace Asignua\FilamentJsonYamlEditor\Tests\Feature;

use Asignua\FilamentJsonYamlEditor\Enums\EditorMode;
use Asignua\FilamentJsonYamlEditor\Forms\JsonEditor;
use Asignua\FilamentJsonYamlEditor\Forms\YamlEditor;
use Asignua\FilamentJsonYamlEditor\Infolists\JsonEntry;
use Asignua\FilamentJsonYamlEditor\Rules\JsonSchemaRule;
use Asignua\FilamentJsonYamlEditor\Tests\TestCase;
use Filament\Schemas\Schema;
use Livewire\Livewire;
use ReflectionMethod;
use Workbench\App\Filament\Resources\Settings\Pages\CreateSetting;
use Workbench\App\Filament\Resources\Settings\Pages\EditSetting;
use Workbench\App\Models\Setting;

class FieldsTest extends TestCase
{
    public function test_create_dehydrates_text_and_arrays(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm([
                'name' => 'x',
                'settings' => '{"a": {"b": [1, 2]}, "t": "Привіт"}',
                'settings_text' => '{"keep":   "as typed"}',
                'config' => "# kept\nserver: {host: x}\n",
                'config_data' => "server:\n  host: x\n  ports: [80]\n",
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $setting = Setting::query()->firstOrFail();

        $this->assertSame(['a' => ['b' => [1, 2]], 't' => 'Привіт'], $setting->settings);
        $this->assertSame('{"keep":   "as typed"}', $setting->settings_text);
        $this->assertSame("# kept\nserver: {host: x}\n", $setting->config);
        $this->assertSame(['server' => ['host' => 'x', 'ports' => [80]]], $setting->config_data);
    }

    public function test_blank_state_is_stored_as_null(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['settings' => '  ', 'settings_text' => '', 'config' => '', 'config_data' => ''])
            ->call('create')
            ->assertHasNoFormErrors();

        $setting = Setting::query()->firstOrFail();

        $this->assertNull($setting->getRawOriginal('settings'));
        $this->assertNull($setting->settings_text);
        $this->assertNull($setting->config);
        $this->assertNull($setting->config_data);
    }

    public function test_arrays_from_the_model_are_shown_as_text(): void
    {
        $setting = $this->setting();

        $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);

        $settings = $component->get('data.settings');
        $this->assertIsString($settings);
        $this->assertStringContainsString("\n  \"site\": {", $settings);
        $this->assertStringContainsString('Демо', $settings);
        $this->assertSame($setting->settings, json_decode($settings, true));

        // inline(2): the list two levels deep is dumped as a flow sequence.
        $this->assertSame("server:\n  host: localhost\n  ports: [80, 443]\n", $component->get('data.config_data'));
        $this->assertSame("# comment\nserver:\n  host: localhost\n", $component->get('data.config'));
    }

    public function test_save_round_trip_keeps_the_data(): void
    {
        $setting = $this->setting();

        Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $setting->refresh();

        $this->assertSame(['site' => ['name' => 'Демо', 'tags' => ['a', 'b']]], $setting->settings);
        $this->assertSame(['server' => ['host' => 'localhost', 'ports' => [80, 443]]], $setting->config_data);
    }

    public function test_invalid_json_and_yaml_fail_validation(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['settings' => '{"a": ', 'settings_text' => '[1,]', 'config' => "a: [1\n", 'config_data' => "a: [1\n"])
            ->call('create')
            ->assertHasFormErrors(['settings', 'settings_text', 'config', 'config_data']);

        $this->assertSame(0, Setting::query()->count());
    }

    public function test_the_rules_can_be_switched_off_and_schema_added(): void
    {
        $objects = static fn (array $rules): array => array_values(array_filter($rules, is_object(...)));

        $this->assertCount(1, $objects(JsonEditor::make('x')->getValidationRules()));
        $this->assertCount(1, $objects(YamlEditor::make('x')->getValidationRules()));
        $this->assertSame([], $objects(JsonEditor::make('x')->validateSyntax(false)->getValidationRules()));
        $this->assertSame([], $objects(YamlEditor::make('x')->validateSyntax(false)->getValidationRules()));

        $with = $objects(JsonEditor::make('x')->schema(['type' => 'object'])->getValidationRules());
        $this->assertCount(2, $with);
        $this->assertInstanceOf(JsonSchemaRule::class, $with[1]);

        $yaml = $objects(YamlEditor::make('x')->schema(['type' => 'object'])->getValidationRules());
        $this->assertInstanceOf(JsonSchemaRule::class, $yaml[1]);
    }

    public function test_options(): void
    {
        $field = JsonEditor::make('x');
        $this->assertSame('20rem', $field->getHeight());
        $this->assertSame('300px', $field->height(300)->getHeight());
        $this->assertSame([EditorMode::Code, EditorMode::Tree], $field->getModes());
        $this->assertSame(EditorMode::Code, $field->getDefaultMode());
        $this->assertSame(EditorMode::Code, $field->modes([EditorMode::Code])->defaultMode(EditorMode::Tree)->getDefaultMode());
        $this->assertSame([EditorMode::Code], $field->modes([])->getModes());
        $this->assertSame(2, $field->getIndent());
        $this->assertFalse($field->isArray());
        $this->assertTrue($field->asArray()->isArray());
    }

    public function test_yaml_dump_options(): void
    {
        $field = YamlEditor::make('x')->inline(1)->indent(4);
        $this->assertSame(1, $field->getInline());
        $this->assertSame(4, $field->getIndent());
        $this->assertSame(0, $field->getDumpFlags());
    }

    public function test_object_casts_open_as_yaml_and_round_trip(): void
    {
        $setting = $this->setting();
        $setting->meta = collect(['tags' => ['a', 'b'], 'count' => 1]);
        $setting->options = (object) ['debug' => true, 'level' => 'info'];
        $setting->save();

        $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);

        $this->assertSame("tags:\n  - a\n  - b\ncount: 1\n", $component->get('data.meta'));
        $this->assertSame("debug: true\nlevel: info\n", $component->get('data.options'));

        $component->call('save')->assertHasNoFormErrors();
        $setting->refresh();

        $this->assertSame(['tags' => ['a', 'b'], 'count' => 1], $setting->meta?->all());
        $this->assertEquals((object) ['debug' => true, 'level' => 'info'], $setting->options);
    }

    public function test_yaml_dates_stay_strings(): void
    {
        $setting = $this->setting();

        $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->fillForm(['config_data' => "released: 2024-01-01\nat: 2024-01-01T10:30:00+02:00\n"])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['released' => '2024-01-01', 'at' => '2024-01-01T10:30:00+02:00'], $setting->refresh()->config_data);

        $text = Livewire::test(EditSetting::class, ['record' => $setting->getKey()])->get('data.config_data');
        $this->assertStringContainsString("released: '2024-01-01'", $text);
    }

    public function test_an_array_cast_without_as_array_still_stores_an_array(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['extras' => '{"a": 1}', 'meta' => "x: 1\n"])
            ->call('create')
            ->assertHasNoFormErrors();

        $setting = Setting::query()->firstOrFail();

        $this->assertSame(['a' => 1], $setting->extras);
        $this->assertSame('{"a":1}', $setting->getRawOriginal('extras'));
        $this->assertSame(['x' => 1], $setting->meta?->all());

        $this->assertFalse(JsonEditor::make('x')->isArray());
        $this->assertFalse(JsonEditor::make('x')->asArray(false)->isArray());
    }

    public function test_as_array_keeps_the_syntax_rule_after_validate_syntax_false(): void
    {
        $setting = $this->setting();
        $setting->strict = ['keep' => 1];
        $setting->save();

        Livewire::test(EditSetting::class, ['record' => $setting->getKey()])
            ->fillForm(['strict' => '{"broken": '])
            ->call('save')
            ->assertHasFormErrors(['strict']);

        $this->assertSame(['keep' => 1], $setting->refresh()->strict);
    }

    public function test_as_array_keeps_empty_objects(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['settings' => '{"a": {}, "b": []}'])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('{"a":{},"b":[]}', Setting::query()->firstOrFail()->getRawOriginal('settings'));
    }

    public function test_empty_objects_survive_reopening_an_array_cast(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['settings' => '{"a": {}, "b": [], "id": 12345678901234567890}'])
            ->call('create')
            ->assertHasNoFormErrors();

        $setting = Setting::query()->firstOrFail();
        $stored = '{"a":{},"b":[],"id":"12345678901234567890"}';
        $this->assertSame($stored, $setting->getRawOriginal('settings'));

        // Reopened, the text comes from the column, not from the cast's arrays.
        $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);
        $this->assertSame("{\n  \"a\": {},\n  \"b\": [],\n  \"id\": \"12345678901234567890\"\n}", $component->get('data.settings'));

        $component->call('save')->assertHasNoFormErrors();
        $this->assertSame($stored, $setting->refresh()->getRawOriginal('settings'));

        // A big integer written by another system is shown with all its digits.
        Setting::query()->whereKey($setting->getKey())->update(['settings' => '{"id":12345678901234567890}']);
        $this->assertStringContainsString('"id": 12345678901234567890', Livewire::test(EditSetting::class, ['record' => $setting->getKey()])->get('data.settings'));
    }

    public function test_yaml_as_array_keeps_empty_maps(): void
    {
        Livewire::test(CreateSetting::class)
            ->fillForm(['config_data' => "a: {}\nb: []\nc:\n  d: {}\n"])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertSame('{"a":{},"b":[],"c":{"d":{}}}', Setting::query()->firstOrFail()->getRawOriginal('config_data'));
    }

    public function test_yaml_empty_maps_survive_reopening_a_json_cast(): void
    {
        $setting = $this->setting();
        Setting::query()->whereKey($setting->getKey())->update([
            'config_data' => '{"meta":{},"list":[],"b":1,"n":{"x":{}}}',
            'options' => '{"m":{},"l":[]}',
        ]);

        $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);
        $this->assertSame("meta: {}\nlist: []\nb: 1\n'n':\n  x: {}\n", $component->get('data.config_data'));
        $this->assertSame("m: {}\nl: []\n", $component->get('data.options'));

        $component->call('save')->assertHasNoFormErrors();
        $setting->refresh();

        $this->assertSame('{"meta":{},"list":[],"b":1,"n":{"x":{}}}', $setting->getRawOriginal('config_data'));
        $this->assertSame('{"m":{},"l":[]}', $setting->getRawOriginal('options'));
    }

    public function test_data_the_page_fills_in_wins_over_the_stored_column(): void
    {
        $setting = $this->setting();

        EditSetting::$mutateBeforeFill = static fn (array $data): array => [...$data, 'settings' => ['b' => 2]];

        try {
            $component = Livewire::test(EditSetting::class, ['record' => $setting->getKey()]);
        } finally {
            EditSetting::$mutateBeforeFill = null;
        }

        $this->assertSame("{\n  \"b\": 2\n}", $component->get('data.settings'));

        // fill() with custom data on a page bound to the same record.
        $component->instance()->form->fill(['settings' => ['c' => 3]]);
        $this->assertSame("{\n  \"c\": 3\n}", $component->instance()->data['settings']);
    }

    public function test_translatable_attributes_are_not_taken_for_array_casts(): void
    {
        $model = new class extends Setting
        {
            public function getCasts(): array
            {
                return [...parent::getCasts(), 'name' => 'array'];
            }

            public function isTranslatableAttribute(string $key): bool
            {
                return $key === 'name';
            }
        };

        $field = JsonEditor::make('name')->container(Schema::make()->model($model));
        $this->assertFalse($field->isArray());

        $field = JsonEditor::make('extras')->container(Schema::make()->model($model));
        $this->assertTrue($field->isArray());
    }

    public function test_the_json_entry_keeps_big_integers(): void
    {
        $text = (new ReflectionMethod(JsonEntry::class, 'toText'))->invoke(JsonEntry::make('x'), '{"id":12345678901234567890}');

        $this->assertSame("{\n  \"id\": 12345678901234567890\n}", $text);
    }
}
