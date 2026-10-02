<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\CreateRecord;
use Workbench\App\Filament\Resources\Settings\SettingResource;

class CreateSetting extends CreateRecord
{
    protected static string $resource = SettingResource::class;
}
