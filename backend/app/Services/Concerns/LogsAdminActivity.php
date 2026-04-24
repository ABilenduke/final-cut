<?php

namespace App\Services\Concerns;

use App\Models\AdminUser;
use Illuminate\Database\Eloquent\Model;

trait LogsAdminActivity
{
    /** Writes nothing when `$actor === null` — customer-initiated writes bypass admin attribution this way. */
    protected function logIfAdmin(string $event, Model $subject, ?AdminUser $actor, array $properties = []): void
    {
        if ($actor === null) {
            return;
        }

        activity('admin')
            ->causedBy($actor)
            ->performedOn($subject)
            ->withProperties($properties)
            ->log($event);
    }
}
