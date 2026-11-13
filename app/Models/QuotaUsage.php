<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $scope_key
 * @property Carbon $date
 * @property int $units_used
 */
#[Fillable(['provider', 'scope_key', 'date', 'units_used'])]
class QuotaUsage extends Model
{
    protected $table = 'quota_usage';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
            'units_used' => 'integer',
        ];
    }
}
