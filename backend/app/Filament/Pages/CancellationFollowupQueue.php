<?php

namespace App\Filament\Pages;

use App\Enums\BookingStatus;
use App\Enums\GiftCardLedgerType;
use App\Exceptions\BookingNotRefundableException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Resources\BookingResource;
use App\Models\Booking;
use App\Models\GiftCardLedgerEntry;
use App\Models\User;
use App\Services\BookingRefundService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Stripe\Exception\ApiErrorException;
use UnitEnum;

/**
 * Operator follow-up queue for bookings flagged by a cancelled showtime.
 * Not a resource — this is a single-purpose ops page.
 *
 * The primary `issue_refund` action routes through `BookingRefundService`
 * (admin-v2 Plan 03): real Stripe refund, gift-card restore, loyalty
 * clawback, seat release, and the customer email via the outbox — in one go.
 * The legacy `mark_resolved` action remains ONLY for rows with no
 * PaymentIntent and no gift-card redemption (nothing to move
 * programmatically; e.g. a comp booking refunded in cash).
 */
class CancellationFollowupQueue extends Page implements HasTable
{
    use FormatsCurrency;
    use InteractsWithTable;

    public const EVENT_MANUALLY_REFUNDED = 'booking.manually_marked_refunded';

    /**
     * Prefix `ShowtimeService::cancel` writes to `bookings.flag_reason` — the
     * queue scopes to rows starting with this so future flagging reasons
     * (refund disputes, fraud holds, etc.) don't bleed into this page.
     */
    public const SHOWTIME_CANCEL_FLAG_PREFIX = 'showtime_cancelled:';

    protected string $view = 'filament.pages.cancellation-followup-queue';

    protected static ?string $slug = 'cancelled-showtime-followup';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 10;

    public function getTitle(): string
    {
        return 'Cancellation Follow-up';
    }

    public static function canAccess(): bool
    {
        // Viewing the queue requires bookings.view; the resolution action
        // below is additionally gated by the narrower bookings.resolve_refund
        // so support roles with bookings.view alone cannot close cases.
        return auth('admin')->user()?->can('bookings.view') ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        if (! auth('admin')->user()?->can('bookings.view')) {
            return null;
        }

        $count = self::baseQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    /**
     * Whether `BookingRefundService` has anything to move for this booking —
     * a captured PaymentIntent or a gift-card redemption to restore. Rows
     * without either are pure manual cases (mark_resolved).
     */
    private static function hasProgrammaticRefund(Booking $booking): bool
    {
        return $booking->stripe_payment_intent_id !== null
            || GiftCardLedgerEntry::query()
                ->where('booking_id', $booking->id)
                ->where('type', GiftCardLedgerType::Redemption)
                ->exists();
    }

    /** Base query used by both the navigation badge and the table. */
    private static function baseQuery(): Builder
    {
        return Booking::query()
            ->whereNotNull('flagged_at')
            ->where('flag_reason', 'like', self::SHOWTIME_CANCEL_FLAG_PREFIX.'%')
            ->whereNotIn('status', [BookingStatus::Refunded, BookingStatus::Cancelled]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                self::baseQuery()
                    ->with(['showtime.movie', 'showtime.auditorium.location', 'user'])
            )
            ->columns([
                TextColumn::make('confirmation_code')
                    ->label('Confirmation')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('customer_email')
                    ->label('Customer')
                    ->getStateUsing(fn (Booking $record) => $record->user ? $record->user->email : ($record->guest_email ?? '—'))
                    ->searchable(
                        // Wrap the OR chain in a grouped where() so it does not
                        // widen the base flagged_at / status / flag_reason filters.
                        query: fn (Builder $q, string $search) => $q->where(fn (Builder $qq) => $qq
                            ->where('guest_email', 'ilike', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $userQuery) => $userQuery->where('email', 'ilike', "%{$search}%"))
                        )
                    ),
                TextColumn::make('showtime.movie.title')->label('Movie'),
                TextColumn::make('showtime.auditorium.location.name')->label('Location'),
                TextColumn::make('showtime.start_time')
                    ->label('Originally scheduled')
                    ->dateTime(),
                TextColumn::make('total')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
                TextColumn::make('flagged_at')
                    ->label('Flagged')
                    ->since()
                    ->sortable(),
                TextColumn::make('flag_reason')
                    ->label('Reason')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->filters([
                Filter::make('recent')
                    ->label('Last 30 days')
                    ->query(fn (Builder $q) => $q->where('flagged_at', '>=', now()->subDays(30))),
            ])
            ->recordActions([
                Action::make('issue_refund')
                    ->label('Issue refund')
                    ->icon('heroicon-o-receipt-refund')
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.resolve_refund') ?? false)
                        && self::hasProgrammaticRefund($record))
                    ->requiresConfirmation()
                    ->modalDescription(fn (Booking $record): string => BookingResource::refundSplitSummary($record))
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->minLength(10)
                            ->rows(3)
                            ->helperText('Required. Logged permanently and included in the audit trail.'),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        try {
                            app(BookingRefundService::class)->refund(
                                $record,
                                $data['reason'],
                                auth('admin')->user(),
                            );
                        } catch (BookingNotRefundableException $e) {
                            Notification::make()
                                ->title('Refund not possible')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();

                            return;
                        } catch (ApiErrorException $e) {
                            report($e);

                            Notification::make()
                                ->title('Stripe refund failed')
                                ->body('No state was changed. Wait a moment and try again; the claim was released.')
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Refund issued')
                            ->body('Seats released; the customer will be notified by email.')
                            ->success()
                            ->send();
                    }),
                Action::make('mark_resolved')
                    ->label('Mark refunded (manual)')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.resolve_refund') ?? false)
                        && ! self::hasProgrammaticRefund($record))
                    ->requiresConfirmation()
                    ->modalDescription('This records a refund handled outside the system (no card payment or gift card to restore). Use "Issue refund" when there is money to move.')
                    ->schema([
                        Textarea::make('notes')
                            ->label('Resolution notes')
                            ->required()
                            ->minLength(10)
                            ->rows(4)
                            ->helperText('Required. Include the Stripe refund reference or the reason this case is being closed without a refund.'),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        /** @var User $admin */
                        $admin = auth('admin')->user();

                        $record->update([
                            'status' => BookingStatus::Refunded,
                            'notes' => $data['notes'],
                        ]);

                        activity('admin')
                            ->causedBy($admin)
                            ->performedOn($record)
                            ->withProperties(['notes' => $data['notes']])
                            ->log(self::EVENT_MANUALLY_REFUNDED);

                        Notification::make()
                            ->title('Marked refunded')
                            ->body('Booking removed from the follow-up queue.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('flagged_at', 'desc');
    }
}
