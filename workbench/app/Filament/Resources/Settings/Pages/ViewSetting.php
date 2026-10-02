<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\ViewRecord;
use Workbench\App\Filament\Resources\Settings\SettingResource;

class ViewSetting extends ViewRecord
{
    protected static string $resource = SettingResource::class;
}
