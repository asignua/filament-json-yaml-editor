<?php

declare(strict_types=1);

namespace Workbench\App\Filament\Resources\Settings\Pages;

use Filament\Resources\Pages\ListRecords;
use Workbench\App\Filament\Resources\Settings\SettingResource;

class ListSettings extends ListRecords
{
    protected static string $resource = SettingResource::class;
}
