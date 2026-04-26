<?php

namespace App\Models;

use App\Enums\LoyaltyAdjustmentType;
use Database\Factories\LoyaltyAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * @property int $id
 * @property string $user_id
 * @property string|null $admin_user_id
 * @property int $points_delta
 * @property string $reason
 * @property LoyaltyAdjustmentType $change_type
 * @property User $user
 * @property User|null $adminUser
 */
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

    /**
     * The admin who initiated this adjustment. Returns a User; by convention
     * this column references a User who has an AdminProfile.
     */
    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['user_id', 'admin_user_id', 'points_delta', 'change_type'])
            ->dontLogEmptyChanges();
    }
}
