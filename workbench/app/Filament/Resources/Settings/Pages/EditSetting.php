<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings\Pages;

use Closure;
use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Settings\SettingResource;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;

    /** Tests set it to change the data the form is filled with. */
    public static ?Closure $mutateBeforeFill = null;

    /**
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return static::$mutateBeforeFill !== null ? (static::$mutateBeforeFill)($data) : $data;
    }
}
