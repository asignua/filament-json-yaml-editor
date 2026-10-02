<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\EditRecord;
use Workbench\App\Filament\Resources\Settings\SettingResource;

class EditSetting extends EditRecord
{
    protected static string $resource = SettingResource::class;
}
