<?php

namespace App\Models;

use Database\Factories\AdminProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * One-to-one admin entitlement on top of `App\Models\User`. Identity (name,
 * email, password) lives on `users`. This row says "this user is an admin"
 * and tracks admin-side metadata: last login attribution and a disabled flag.
 *
 * @property string $user_id
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $disabled_at
 * @property User $user
 */
class AdminProfile extends Model
{
    /** @use HasFactory<AdminProfileFactory> */
    use HasFactory, LogsActivity;

    protected $table = 'admin_users';

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['user_id', 'last_login_at', 'last_login_ip', 'disabled_at'];

    protected function casts(): array
    {
        return [
            'last_login_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['disabled_at'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
