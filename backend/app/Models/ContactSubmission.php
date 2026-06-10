<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Database\Factories\ContactSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A persisted contact-form message (admin-v2 Plan 10 — submissions were
 * previously only logged). `handled_at`/`handled_by` mark operator triage.
 *
 * @property string $id
 * @property string $name
 * @property string $email
 * @property string $subject
 * @property string $message
 * @property CarbonInterface|null $handled_at
 * @property string|null $handled_by
 * @property User|null $handler
 */
#[Fillable(['name', 'email', 'subject', 'message', 'handled_at', 'handled_by'])]
class ContactSubmission extends Model
{
    /** @use HasFactory<ContactSubmissionFactory> */
    use HasFactory, HasUuids;

    protected function casts(): array
    {
        return [
            'handled_at' => 'datetime',
        ];
    }

    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
