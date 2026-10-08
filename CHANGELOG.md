# Changelog

All notable changes to `asignua/filament-json-yaml-editor` are documented here.

## v1.1.0 - 2026-10-08

- Dependencies: js-yaml 4 to 5 (bundled; YAML timestamps now load as strings, `!!set` and other non-core tags are no longer accepted by default), esbuild 0.28, jsdom 30 (dev).
- Security: an alias bomb combined with a merge key in a flow map can no longer exhaust memory; the expansion guard now also runs on the fallback parse (`Yaml::parse()`, `Yaml::decodeToArrays()`, `YamlRule`, `JsonSchemaRule::yaml()`).
- A top-level string stored by an array cast (built-in or custom, including nested field names) (`"123"`, `"hello"`, `a: b`) is shown as JSON / YAML text and saved back as the same string, in `JsonEditor` and `YamlEditor`.
- The `Format` button only re-indents: big integers, `1.0`, duplicate keys and key order are kept as written.
- Unquoted YAML date keys (`2024-12-25: x`) stay strings instead of becoming unix timestamps. Block-scalar text and the continuation lines of multi-line quoted scalars (also after an anchor or tag) or flow collections are not mistaken for a key; a comment ending in `: |` does not start a block scalar (also a trailing one after a key); a quote after a spaced `-`, `:` or `?` inside plain text opens no scalar. A date key inside a flow map (`{2024-01-01: a}`) is not covered: it is still rejected when parsing without `PARSE_DATETIME`.
- `JsonSchemaRule` with `opis/json-schema` reports up to `maxErrors()` messages, and an unresolvable `$ref` or a malformed schema keyword is a validation error, not a 500.
- The editor's error line is no longer garbled by `$&` / `$'` in the parser message.

## v1.0.0 - 2026-10-05

- `JsonEditor` form field: code view (CodeMirror 6: highlighting, line numbers, folding) and tree view (expand / collapse, edit values, rename keys, change types, add and remove), live validation with the error line, `->format()` button, `->indent()`, `->height()`, `->modes()`, `->defaultMode()`, read-only when disabled, dark mode follows Filament.
- `YamlEditor` form field: CodeMirror 6 with the YAML language and a live `js-yaml` lint with the error line; `->inline()`, `->indent()`, `->dumpFlags()` control how an array from the model is dumped.
- Both fields keep the state as text by default; `->asArray()` hands the model a decoded array instead. Arrays coming from the model are always shown as text.
- Server-side rules: `JsonRule`, `YamlRule` (symfony/yaml, never instantiates objects) and `JsonSchemaRule` (`opis/json-schema` or `justinrainbow/json-schema`, JSON or YAML input); fields add the syntax rule automatically and `->schema()` adds the schema rule.
- `JsonEntry` / `YamlEntry` infolist entries: server-side Phiki highlighting (light and dark), collapsible, copy button, height cap.
- The editor bundle is an Alpine component loaded on request (`x-load`); a tiny stylesheet is registered with Filament's assets.
- Translations: English, Ukrainian, German, Spanish, French, Italian, Dutch, Polish, Brazilian Portuguese and Turkish.
- Laravel Boost guidelines.
- Without `->asArray()` the fields follow the model cast (`array`, `json`, `object`, `collection`, `AsArrayObject`, `AsCollection`, encrypted variants) and hand it an array; `->asArray(false)` forces text.
- With `->asArray()` the syntax rule stays on even after `->validateSyntax(false)`, YAML timestamps stay ISO strings (a midnight UTC timestamp keeps its time) and `{}` stays an object in both fields (an empty YAML map `{}` too), also after the form is reopened (the text is built from the stored column, unless the page filled the form with its own data). A plain YAML date stays a date even when the same day is written with a time elsewhere in the text. An object with the keys `0..n` is stored as a list. A merge key inside a flow map (`item: {<<: *base, m: 2}`) is saved and schema-validated like any other valid YAML (symfony/yaml cannot parse it into objects, so that document falls back to arrays and only its empty maps become `[]`). Integers beyond `PHP_INT_MAX` keep their digits but become strings; `JsonEntry` shows them unchanged.
- Translatable attributes (spatie/laravel-translatable) are not taken for array casts.
- An empty editor leaves its `null` state alone on load, and a state the server replaced is not sent back: no false unsaved-changes prompt, no extra request for live fields.
- Collections, `ArrayObject`s and plain objects are dumped as YAML maps (field and entry).
- Security: YAML documents whose aliases expand past a budget are rejected ("billion laughs"); with `justinrainbow/json-schema`, `JsonSchemaRule` refuses remote and `file://` `$ref`s (only the bundled draft meta-schemas resolve).
