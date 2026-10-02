# Changelog

All notable changes to `asignua/filament-json-yaml-editor` are documented here.

## v1.0.0 - unreleased

- `JsonEditor` form field: code view (CodeMirror 6: highlighting, line numbers, folding) and tree view (expand / collapse, edit values, rename keys, change types, add and remove), live validation with the error line, `->format()` button, `->indent()`, `->height()`, `->modes()`, `->defaultMode()`, read-only when disabled, dark mode follows Filament.
- `YamlEditor` form field: CodeMirror 6 with the YAML language and a live `js-yaml` lint with the error line; `->inline()`, `->indent()`, `->dumpFlags()` control how an array from the model is dumped.
- Both fields keep the state as text by default; `->asArray()` hands the model a decoded array instead. Arrays coming from the model are always shown as text.
- Server-side rules: `JsonRule`, `YamlRule` (symfony/yaml, never instantiates objects) and `JsonSchemaRule` (`opis/json-schema` or `justinrainbow/json-schema`, JSON or YAML input); fields add the syntax rule automatically and `->schema()` adds the schema rule.
- `JsonEntry` / `YamlEntry` infolist entries: server-side Phiki highlighting (light and dark), collapsible, copy button, height cap.
- The editor bundle is an Alpine component loaded on request (`x-load`); a tiny stylesheet is registered with Filament's assets.
- Translations: English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish.
- Laravel Boost guidelines.
- Without `->asArray()` the fields follow the model cast (`array`, `json`, `object`, `collection`, `AsArrayObject`, `AsCollection`, encrypted variants) and hand it an array; `->asArray(false)` forces text.
- With `->asArray()` the syntax rule stays on even after `->validateSyntax(false)`, YAML timestamps stay ISO strings (a midnight UTC timestamp keeps its time) and `{}` stays an object, also after the form is reopened (the text is built from the stored column). Integers beyond `PHP_INT_MAX` keep their digits but become strings; `JsonEntry` shows them unchanged.
- Translatable attributes (spatie/laravel-translatable) are not taken for array casts.
- Collections, `ArrayObject`s and plain objects are dumped as YAML maps (field and entry).
- Security: YAML documents whose aliases expand past a budget are rejected ("billion laughs"); with `justinrainbow/json-schema`, `JsonSchemaRule` refuses remote and `file://` `$ref`s (only the bundled draft meta-schemas resolve).
