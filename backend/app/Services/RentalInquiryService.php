<?php

namespace App\Services;

use App\Enums\InquiryStatus;
use App\Exceptions\InquiryTransitionException;
use App\Models\RentalInquiry;
use App\Models\User;
use App\Services\Concerns\LogsAdminActivity;
use Illuminate\Support\Facades\DB;

/**
 * Status transitions for rental inquiries (admin-v2 Plan 10). One explicit
 * map drives both the service guard and the UI's offered options, so the
 * Filament action can never present an illegal move.
 */
class RentalInquiryService
{
    use LogsAdminActivity;

    public const EVENT_TRANSITIONED = 'rental_inquiry.transitioned';

    /** @return list<InquiryStatus> */
    public static function allowedTransitions(InquiryStatus $from): array
    {
        return match ($from) {
            InquiryStatus::Pending => [InquiryStatus::Contacted, InquiryStatus::Confirmed, InquiryStatus::Declined],
            InquiryStatus::Contacted => [InquiryStatus::Confirmed, InquiryStatus::Declined],
            // Terminal — confirmed/declined inquiries are immutable history.
            InquiryStatus::Confirmed, InquiryStatus::Declined => [],
        };
    }

    /**
     * @throws InquiryTransitionException
     */
    public function transition(RentalInquiry $inquiry, InquiryStatus $to, User $actor): void
    {
        DB::transaction(function () use ($inquiry, $to, $actor): void {
            /** @var RentalInquiry $locked */
            $locked = RentalInquiry::whereKey($inquiry->id)->lockForUpdate()->firstOrFail();

            if (! in_array($to, self::allowedTransitions($locked->status), true)) {
                throw new InquiryTransitionException($locked->status, $to);
            }

            $from = $locked->status;
            $locked->update(['status' => $to]);

            $this->logIfAdmin(self::EVENT_TRANSITIONED, $locked, $actor, [
                'from' => $from->value,
                'to' => $to->value,
                'email' => $locked->email,
            ]);
        });
    }
}
