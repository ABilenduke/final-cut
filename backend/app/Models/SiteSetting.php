<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One keyed editorial blob (admin-v2 Plan 15). Values are free-form JSON
 * whose shape is owned by the consuming frontend surface; the
 * SiteSettingsService is the only writer.
 *
 * @property string $key
 * @property array $value
 * @property string|null $updated_by
 */
#[Fillable(['key', 'value', 'updated_by'])]
class SiteSetting extends Model
{
    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }
}
