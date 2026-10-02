<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string|null $name
 * @property array<string, mixed>|null $settings
 * @property string|null $settings_text
 * @property string|null $config
 * @property array<string, mixed>|null $config_data
 */
class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'config_data' => 'array',
        ];
    }
}
