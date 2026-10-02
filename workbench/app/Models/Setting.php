<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * @property int $id
 * @property string|null $name
 * @property array<string, mixed>|null $settings
 * @property string|null $settings_text
 * @property string|null $config
 * @property array<string, mixed>|null $config_data
 * @property Collection<array-key, mixed>|null $meta
 * @property object|null $options
 * @property array<array-key, mixed>|null $extras
 * @property array<array-key, mixed>|null $strict
 */
class Setting extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'config_data' => 'array',
            'meta' => AsCollection::class,
            'options' => 'object',
            'extras' => 'array',
            'strict' => 'array',
        ];
    }
}
