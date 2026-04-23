<?php

namespace App\Models;

use App\Enums\LoyaltyAdjustmentType;
use Database\Factories\LoyaltyAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class LoyaltyAdjustment extends Model
{
    /** @use HasFactory<LoyaltyAdjustmentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = [
        'user_id', 'admin_user_id', 'points_delta', 'reason', 'change_type',
    ];

    protected function casts(): array
    {
        return [
            'change_type' => LoyaltyAdjustmentType::class,
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(AdminUser::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'admin_user_id', 'points_delta', 'change_type'])
            ->dontLogEmptyChanges();
    }
}
