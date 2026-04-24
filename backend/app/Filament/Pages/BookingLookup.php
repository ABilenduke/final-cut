<?php

namespace App\Filament\Pages;

use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Fast confirmation-code / email lookup for phone-based customer support.
 * Enter either a confirmation code (case-insensitive, `CVF-` prefix optional)
 * or an email (matches `guest_email` or the authenticated user's email), press
 * Enter, land on the booking view page.
 */
class BookingLookup extends Page
{
    protected string $view = 'filament.pages.booking-lookup';

    protected static ?string $slug = 'booking-lookup';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-magnifying-glass';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 1;

    public ?string $query = null;

    public function getTitle(): string
    {
        return 'Booking Lookup';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('bookings.view') ?? false;
    }

    public function search(): void
    {
        $this->validate([
            'query' => ['required', 'string', 'min:3'],
        ], [
            'query.required' => 'Enter a confirmation code or email.',
            'query.min' => 'Enter at least 3 characters.',
        ]);

        $needle = trim($this->query ?? '');

        // Codes are generated as `CVF-<suffix>`. Support users who paste just
        // the suffix by prepending the prefix when missing — docblock promises
        // this, and the DB stores the full prefixed form.
        $confirmationCode = strtoupper($needle);
        if (! str_starts_with($confirmationCode, 'CVF-')) {
            $confirmationCode = 'CVF-'.$confirmationCode;
        }

        $booking = Booking::query()
            ->where(function (Builder $q) use ($needle, $confirmationCode) {
                $q->where('confirmation_code', $confirmationCode)
                    ->orWhere('guest_email', 'ilike', $needle)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('email', 'ilike', $needle));
            })
            ->latest('created_at')
            ->first();

        if (! $booking) {
            Notification::make()
                ->title('No booking found')
                ->body('Double-check the code or email and try again.')
                ->warning()
                ->send();

            return;
        }

        $this->redirect(BookingResource::getUrl('view', ['record' => $booking]));
    }
}
