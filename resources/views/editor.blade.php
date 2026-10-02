@php
    use Filament\Support\Facades\FilamentAsset;
    use Illuminate\Support\Js;

    $statePath = $field->getStatePath();
@endphp

<div
    x-load
    x-load-src="{{ FilamentAsset::getAlpineComponentSrc('json-yaml-editor', 'asignua/filament-json-yaml-editor') }}"
    x-data="jsonYamlEditorFormComponent({
        ...{!! Js::from($config) !!},
        state: $wire.{{ $field->applyStateBindingModifiers("\$entangle('{$statePath}')", isOptimisticallyLive: false) }},
    })"
    wire:ignore
    wire:key="{{ $field->getLivewireKey() }}.{{ substr(md5(serialize([$field->isDisabled(), $config['mode'], $language])), 0, 32) }}"
    id="{{ $field->getId() }}"
    role="group"
    aria-labelledby="{{ $field->getId() }}-label"
    class="jye"
    {{ $field->getExtraAlpineAttributeBag() }}
>
    @if (count($modes) > 1 || $showFormat)
        <div class="jye-toolbar">
            @if (count($modes) > 1)
                <div class="jye-modes" role="group" aria-label="{{ __('json-yaml-editor::messages.editor.view') }}">
                    @foreach ($modes as $mode)
                        <x-filament::button
                            type="button"
                            size="xs"
                            color="gray"
                            x-on:click="setView('{{ $mode->value }}')"
                            x-bind:aria-pressed="view === '{{ $mode->value }}'"
                            x-bind:class="{ 'jye-active': view === '{{ $mode->value }}' }"
                        >
                            {{ __('json-yaml-editor::messages.editor.mode_'.$mode->value) }}
                        </x-filament::button>
                    @endforeach
                </div>
            @endif

            @if ($showFormat)
                <x-filament::button
                    type="button"
                    size="xs"
                    color="gray"
                    x-on:click="format()"
                    x-bind:disabled="! canFormat()"
                >
                    {{ __('json-yaml-editor::messages.editor.format') }}
                </x-filament::button>
            @endif
        </div>
    @endif

    <div class="jye-body" style="height: {{ $height }}">
        <div x-ref="editor" x-show="view === 'code'" class="jye-code" x-cloak></div>

        @if (in_array('tree', $config['modes'], true))
            <div x-show="view === 'tree'" class="jye-tree-wrap" x-cloak>
                <p x-show="! treeValid" class="jye-tree-invalid" x-text="labels.treeInvalid"></p>
                <div x-ref="tree"></div>
            </div>
        @endif
    </div>

    <p class="jye-status" x-show="error" x-text="errorText()" role="status" x-cloak></p>
</div>
