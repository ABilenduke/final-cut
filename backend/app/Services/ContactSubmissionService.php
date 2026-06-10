<?php

namespace App\Services;

use App\Exceptions\ContactSubmissionException;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Operator triage for contact-form submissions (admin-v2 Plan 10).
 */
class ContactSubmissionService
{
    use LogsAdminActivity;

    public const EVENT_HANDLED = 'contact_submission.handled';

    /**
     * @throws ContactSubmissionException When already handled.
     */
    public function markHandled(ContactSubmission $submission, User $actor): void
    {
        DB::transaction(function () use ($submission, $actor): void {
            /** @var ContactSubmission $locked */
            $locked = ContactSubmission::whereKey($submission->id)->lockForUpdate()->firstOrFail();

            if ($locked->handled_at !== null) {
                throw new ContactSubmissionException(ContactSubmissionException::REASON_ALREADY_HANDLED);
            }

            $locked->update([
                'handled_at' => now(),
                'handled_by' => $actor->id,
            ]);

            $this->logIfAdmin(self::EVENT_HANDLED, $locked, $actor, [
                'email' => $locked->email,
                'subject' => $locked->subject,
            ]);
        });
    }
}
