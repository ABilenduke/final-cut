<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Exceptions\BookingAmendmentException;
use App\Exceptions\BookingFlagException;
use App\Exceptions\BookingNotRefundableException;
use App\Exceptions\BookingNotResendableException;
use App\Exceptions\SeatConflictException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Seat;
use App\Models\Showtime;
use App\Services\BookingAmendmentService;
use App\Services\BookingFlagService;
use App\Services\BookingNotificationService;
use App\Services\BookingRefundService;
use App\Services\SeatAvailabilityService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Stripe\Exception\ApiErrorException;
use UnitEnum;

/**
 * Booking resource: read-only list/view plus narrowly-scoped header actions
 * (refund, resend confirmation, flag/unflag — admin-v2 Plan 03), each gated
 * by its own permission and routed through a domain service. No edit/delete
 * surfaces — bookings are financial records. Customer columns synthesize
 * display because authed bookings store `user_id` (UUID) while guest bookings
 * store `guest_email` instead of `customer_email`/`customer_name`.
 */
class BookingResource extends BaseResource
{
    use FormatsCurrency;

    protected static ?string $model = Booking::class;

    protected static ?string $permissionPrefix = 'bookings';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 20;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'showtime.movie', 'showtime.auditorium.location']);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBookings::route('/'),
            'walkup' => Pages\CreateWalkUpBooking::route('/walk-up'),
            'view' => Pages\ViewBooking::route('/{record}'),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('confirmation_code')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        'confirmation_code',
                        'ilike',
                        '%'.strtoupper($search).'%',
                    ))
                    ->copyable()
                    ->sortable(),
                TextColumn::make('customer_email')
                    ->label('Email')
                    ->getStateUsing(fn (Booking $r): string => $r->user ? $r->user->email : ($r->guest_email ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(function (Builder $q) use ($search) {
                        $q->where('guest_email', 'ilike', "%{$search}%")
                            ->orWhereHas('user', fn (Builder $u) => $u
                                ->where('email', 'ilike', "%{$search}%")
                                ->orWhere('name', 'ilike', "%{$search}%"));
                    })),
                TextColumn::make('showtime.movie.title')
                    ->label('Movie')
                    ->searchable(),
                TextColumn::make('showtime.start_time')
                    ->label('Showtime')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('showtime.auditorium.location.name')
                    ->label('Location'),
                TextColumn::make('total')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (Booking $r): string => $r->displayStatus())
                    ->color(fn (string $state): string => match ($state) {
                        BookingStatus::Confirmed->value => 'success',
                        'flagged' => 'warning',
                        BookingStatus::Refunded->value, BookingStatus::Cancelled->value => 'danger',
                        BookingStatus::Held->value, BookingStatus::RefundPending->value => 'info',
                        default => 'gray',
                    }),
                ...TimestampColumns::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BookingStatus::cases())
                        ->mapWithKeys(fn (BookingStatus $c) => [$c->value => ucfirst(str_replace('_', ' ', $c->value))])
                        ->all()),
                Filter::make('date_range')
                    ->schema([
                        DatePicker::make('created_from')->label('Created from'),
                        DatePicker::make('created_until')->label('Created until'),
                    ])
                    ->query(function (Builder $q, array $data) {
                        return $q
                            ->when($data['created_from'] ?? null, fn (Builder $qq, $d) => $qq->whereDate('created_at', '>=', $d))
                            ->when($data['created_until'] ?? null, fn (Builder $qq, $d) => $qq->whereDate('created_at', '<=', $d));
                    }),
                SelectFilter::make('location_id')
                    ->label('Location')
                    ->options(fn () => Location::orderBy('name')->pluck('name', 'id'))
                    ->query(function (Builder $q, array $data) {
                        $id = $data['value'] ?? null;
                        if (! $id) {
                            return $q;
                        }

                        // Subquery instead of nested whereHas — dot-notation on
                        // polymorphic UUID relations hit "showtime() on null"
                        // under the Filament filter context in tests.
                        return $q->whereIn('showtime_id', Showtime::query()
                            ->whereIn('auditorium_id', Auditorium::query()
                                ->where('location_id', $id)
                                ->select('id'))
                            ->select('id'));
                    }),
                SelectFilter::make('showtime_id')
                    ->label('Showtime')
                    ->options(fn () => Showtime::query()
                        ->with('movie')
                        ->orderByDesc('start_time')
                        ->limit(100)
                        ->get()
                        ->mapWithKeys(fn (Showtime $s) => [
                            $s->id => "{$s->movie->title} — {$s->start_time->format('M j g:i A')}",
                        ])
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                self::bulkRefundAction(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Booking')
                ->schema([
                    Placeholder::make('confirmation_code')
                        ->label('Confirmation code')
                        ->content(fn (Booking $record): string => $record->confirmation_code),
                    Placeholder::make('status')
                        ->label('Status')
                        ->content(function (Booking $record): string {
                            return $record->flagged_at !== null
                                ? 'Flagged'
                                : ucfirst(str_replace('_', ' ', $record->status->value));
                        }),
                    Placeholder::make('flag_reason')
                        ->label('Flag reason')
                        ->content(fn (Booking $record): ?string => $record->flag_reason)
                        ->visible(fn (Booking $record): bool => $record->flagged_at !== null),
                ])
                ->columns(2),

            Section::make('Customer')
                ->schema([
                    Placeholder::make('customer_name')
                        ->label('Name')
                        ->content(fn (Booking $record): string => $record->user ? $record->user->name : 'Guest'),
                    Placeholder::make('customer_email')
                        ->label('Email')
                        ->content(fn (Booking $record): ?string => $record->user ? $record->user->email : $record->guest_email),
                    Placeholder::make('customer_link')
                        ->label('Account')
                        ->content(function (Booking $record): HtmlString|string {
                            if (! $record->user_id) {
                                return 'Guest checkout';
                            }

                            try {
                                $url = UserResource::getUrl('view', ['record' => $record->user_id]);
                            } catch (\Throwable) {
                                return 'User #'.$record->user_id;
                            }

                            return new HtmlString(sprintf(
                                '<a href="%s" class="fi-link underline">View user profile</a>',
                                e($url),
                            ));
                        }),
                ])
                ->columns(2),

            Section::make('Showtime')
                ->schema([
                    Placeholder::make('movie')
                        ->label('Movie')
                        ->content(fn (Booking $record): string => $record->showtime->movie->title),
                    Placeholder::make('start_time')
                        ->label('Start')
                        ->content(fn (Booking $record): string => $record->showtime->start_time->format('M j, Y g:i A')),
                    Placeholder::make('auditorium')
                        ->label('Auditorium')
                        ->content(fn (Booking $record): string => $record->showtime->auditorium->name),
                    Placeholder::make('location')
                        ->label('Location')
                        ->content(fn (Booking $record): string => $record->showtime->auditorium->location->name),
                ])
                ->columns(2),

            Section::make('Seats')
                ->schema([
                    Placeholder::make('seats_list')
                        ->label('')
                        ->content(function (Booking $record): HtmlString {
                            $rows = [];
                            foreach ($record->seats as $seat) {
                                assert($seat instanceof BookingSeat);
                                $rows[] = sprintf(
                                    '%s — %s (%s)',
                                    $seat->seat_id,
                                    $seat->section ?? 'Standard',
                                    self::centsToDisplay($seat->price),
                                );
                            }

                            return new HtmlString(
                                $rows === []
                                    ? '<em>No seats on this booking.</em>'
                                    : '<ul class="list-disc pl-5 space-y-1"><li>'.implode('</li><li>', array_map('e', $rows)).'</li></ul>'
                            );
                        }),
                ]),

            Section::make('Food & drink')
                ->schema([
                    Placeholder::make('food_items')
                        ->label('')
                        ->content(function (Booking $record): HtmlString {
                            $rows = [];
                            foreach ($record->foodItems as $item) {
                                assert($item instanceof BookingFoodItem);
                                $rows[] = sprintf(
                                    '%s × %d — %s',
                                    $item->name,
                                    $item->quantity,
                                    self::centsToDisplay($item->total_price),
                                );
                            }

                            return new HtmlString(
                                $rows === []
                                    ? '<em>No food items pre-ordered.</em>'
                                    : '<ul class="list-disc pl-5 space-y-1"><li>'.implode('</li><li>', array_map('e', $rows)).'</li></ul>'
                            );
                        }),
                ]),

            Section::make('Payment')
                ->schema([
                    Placeholder::make('subtotal')
                        ->label('Subtotal')
                        ->content(fn (Booking $record): string => self::centsToDisplay($record->subtotal)),
                    Placeholder::make('discount')
                        ->label('Discount')
                        ->content(fn (Booking $record): string => self::centsToDisplay($record->discount)),
                    Placeholder::make('total')
                        ->label('Total')
                        ->content(fn (Booking $record): string => self::centsToDisplay($record->total)),
                    Placeholder::make('payment_method')
                        ->label('Method')
                        ->content(fn (Booking $record): ?string => $record->payment_method?->value),
                    Placeholder::make('stripe_payment_intent_id')
                        ->label('Stripe PaymentIntent')
                        ->content(fn (Booking $record): ?string => $record->stripe_payment_intent_id)
                        ->visible(fn (): bool => (bool) (auth('admin')->user()?->can('bookings.resolve_refund'))),
                    Placeholder::make('refund_initiated_at')
                        ->label('Refund initiated')
                        ->content(fn (Booking $record): ?string => $record->refund_initiated_at?->toDateTimeString())
                        ->visible(fn (Booking $record): bool => $record->refund_initiated_at !== null
                            && (bool) auth('admin')->user()?->can('bookings.resolve_refund')),
                    Placeholder::make('refunded_at')
                        ->label('Refunded at')
                        ->content(fn (Booking $record): ?string => $record->refunded_at?->toDateTimeString())
                        ->visible(fn (Booking $record): bool => $record->refunded_at !== null
                            && (bool) auth('admin')->user()?->can('bookings.resolve_refund')),
                    Placeholder::make('stripe_refund_id')
                        ->label('Stripe refund')
                        ->content(fn (Booking $record): ?string => $record->stripe_refund_id)
                        ->visible(fn (Booking $record): bool => $record->stripe_refund_id !== null
                            && (bool) auth('admin')->user()?->can('bookings.resolve_refund')),
                ])
                ->columns(2),

            // B7 — the audit trail for this booking (refunds, flags, notes,
            // email corrections, …). Data already lives in activity_log; this
            // just surfaces it inline so support doesn't need the separate log.
            Section::make('History')
                ->visible(fn (): bool => (bool) auth('admin')->user()?->can('activity.view'))
                ->schema([
                    Placeholder::make('activity_timeline')
                        ->label('')
                        ->content(function (Booking $record): HtmlString {
                            $activities = self::recentActivityFor($record);

                            if ($activities->isEmpty()) {
                                return new HtmlString('<em>No recorded activity for this booking yet.</em>');
                            }

                            $rows = $activities->map(function (Activity $a): string {
                                $event = str((string) $a->description)
                                    ->replace('booking.', '')
                                    ->headline()
                                    ->toString();
                                $who = $a->causer?->email ?? $a->causer?->name ?? 'system';
                                $when = $a->created_at?->diffForHumans() ?? '';

                                return sprintf('%s — %s · %s', e($event), e($who), e($when));
                            })->all();

                            return new HtmlString(
                                '<ul class="list-disc pl-5 space-y-1"><li>'.implode('</li><li>', $rows).'</li></ul>'
                            );
                        }),
                ]),
        ]);
    }

    /**
     * B7 — the recent `admin` activity-log entries whose subject is this
     * booking, newest first. Booking doesn't use the LogsActivity trait
     * (events are written explicitly by the booking services), so this matches
     * on the morph subject directly rather than via a relation.
     *
     * @return Collection<int, Activity>
     */
    public static function recentActivityFor(Booking $booking, int $limit = 25): Collection
    {
        return Activity::query()
            ->where('subject_type', $booking->getMorphClass())
            ->where('subject_id', $booking->getKey())
            ->with('causer')
            // id desc (monotonic insertion order) is a stable newest-first sort
            // even when several events share a created_at second.
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Human-readable preview of what `BookingRefundService::refund()` would
     * move — shared by the view-page refund modal and the cancellation
     * follow-up queue's issue_refund modal.
     */
    public static function refundSplitSummary(Booking $booking): string
    {
        $split = app(BookingRefundService::class)->previewSplit($booking);

        if ($split['target_status'] === BookingStatus::Cancelled) {
            return 'Releases this held booking and frees its seats. No money was captured, so nothing is refunded.';
        }

        $parts = ['Card refund: '.self::centsToDisplay($split['card_refund'])];

        $giftTotal = array_sum(array_column($split['gift_restores'], 'amount'));
        if ($giftTotal > 0) {
            $parts[] = 'Gift card restore: '.self::centsToDisplay($giftTotal);
        }

        if ($split['loyalty_clawback'] > 0) {
            $parts[] = "Loyalty clawback: {$split['loyalty_clawback']} points";
        }

        return implode(' · ', $parts).'. This issues a real Stripe refund and cannot be undone.';
    }

    /**
     * Full refunds only — bookings are atomic (all seats or none), so a
     * partial amount would desync booking.total from the money actually
     * returned and break the gift/loyalty clawback math. Per-seat refunds
     * would need a booking-split model first.
     */
    public static function refundAction(): Action
    {
        return Action::make('refund')
            ->label(fn (Booking $record): string => $record->status === BookingStatus::Held
                ? 'Release hold'
                : 'Refund booking')
            ->icon('heroicon-o-receipt-refund')
            ->color('danger')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.resolve_refund') ?? false)
                && in_array($record->status, BookingStatus::occupyingStatuses(), true))
            ->requiresConfirmation()
            ->modalDescription(fn (Booking $record): string => self::refundSplitSummary($record))
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
                    ->title($record->refresh()->status === BookingStatus::Cancelled
                        ? 'Hold released'
                        : 'Booking refunded')
                    ->body('Seats released; the customer will be notified by email.')
                    ->success()
                    ->send();
            });
    }

    public static function resendConfirmationAction(): Action
    {
        return Action::make('resend_confirmation')
            ->label('Resend confirmation')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.resend_confirmation') ?? false)
                && $record->status === BookingStatus::Confirmed)
            ->requiresConfirmation()
            ->modalDescription(fn (Booking $record): string => sprintf(
                'Sends the booking confirmation for %s to %s.',
                $record->confirmation_code,
                $record->user ? $record->user->email : ($record->guest_email ?? '—'),
            ))
            ->action(function (Booking $record): void {
                try {
                    app(BookingNotificationService::class)->resendConfirmation(
                        $record,
                        auth('admin')->user(),
                    );
                } catch (BookingNotResendableException $e) {
                    Notification::make()
                        ->title('Cannot resend confirmation')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Confirmation queued')
                    ->body('The email will be delivered by the outbox worker within a minute.')
                    ->success()
                    ->send();
            });
    }

    public static function flagAction(): Action
    {
        return Action::make('flag')
            ->label('Flag booking')
            ->icon('heroicon-o-flag')
            ->color('warning')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.flag') ?? false)
                && $record->flagged_at === null)
            ->schema([
                Textarea::make('reason')
                    ->label('Flag reason')
                    ->required()
                    ->minLength(10)
                    ->rows(3)
                    // Defense at the form layer; BookingFlagService enforces
                    // the same guard for any non-UI caller.
                    ->rules(['not_regex:/^showtime_cancelled:/'])
                    ->validationMessages([
                        'not_regex' => "The 'showtime_cancelled:' prefix is reserved for automatic showtime-cancellation flags.",
                    ])
                    ->helperText('Shown on the booking and logged permanently.'),
            ])
            ->requiresConfirmation()
            ->action(function (Booking $record, array $data): void {
                try {
                    app(BookingFlagService::class)->flag(
                        $record,
                        $data['reason'],
                        auth('admin')->user(),
                    );
                } catch (BookingFlagException $e) {
                    Notification::make()
                        ->title('Cannot flag booking')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Booking flagged')
                    ->success()
                    ->send();
            });
    }

    public static function unflagAction(): Action
    {
        return Action::make('unflag')
            ->label('Remove flag')
            ->icon('heroicon-o-flag')
            ->color('gray')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.flag') ?? false)
                && $record->flagged_at !== null)
            ->requiresConfirmation()
            ->modalDescription(fn (Booking $record): string => sprintf(
                'Removes the flag ("%s") from %s. The previous reason stays in the activity log.',
                $record->flag_reason,
                $record->confirmation_code,
            ))
            ->action(function (Booking $record): void {
                try {
                    app(BookingFlagService::class)->unflag(
                        $record,
                        auth('admin')->user(),
                    );
                } catch (BookingFlagException $e) {
                    Notification::make()
                        ->title('Cannot remove flag')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Flag removed')
                    ->success()
                    ->send();
            });
    }

    /**
     * B2 — edit the internal booking notes. Always available to permitted
     * admins (notes are documentation, not a state-machine field).
     */
    public static function editNotesAction(): Action
    {
        return Action::make('edit_notes')
            ->label('Edit notes')
            ->icon('heroicon-o-pencil-square')
            ->color('gray')
            ->visible(fn (): bool => auth('admin')->user()?->can('bookings.edit_notes') ?? false)
            ->fillForm(fn (Booking $record): array => ['notes' => $record->notes])
            ->schema([
                Textarea::make('notes')
                    ->label('Internal notes')
                    ->rows(4)
                    ->maxLength(2000)
                    ->helperText('Support-facing only — never shown to the customer. Logged on save.'),
            ])
            ->action(function (Booking $record, array $data): void {
                app(BookingAmendmentService::class)->updateNotes(
                    $record,
                    $data['notes'] ?? null,
                    auth('admin')->user(),
                );

                Notification::make()
                    ->title('Notes updated')
                    ->success()
                    ->send();
            });
    }

    /**
     * B4 — correct a mistyped guest email. Guest bookings only; a registered
     * user's contact email lives on the User and is corrected there.
     */
    public static function correctGuestEmailAction(): Action
    {
        return Action::make('correct_guest_email')
            ->label('Correct guest email')
            ->icon('heroicon-o-envelope')
            ->color('gray')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.correct_email') ?? false)
                && $record->user_id === null)
            ->fillForm(fn (Booking $record): array => ['email' => $record->guest_email])
            ->schema([
                TextInput::make('email')
                    ->label('Guest email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->helperText('Where the confirmation and any future notices are sent. The change is logged.'),
            ])
            ->requiresConfirmation()
            ->modalDescription('Resend the confirmation afterwards if the customer needs a fresh copy.')
            ->action(function (Booking $record, array $data): void {
                try {
                    app(BookingAmendmentService::class)->correctGuestEmail(
                        $record,
                        $data['email'],
                        auth('admin')->user(),
                    );
                } catch (BookingAmendmentException $e) {
                    Notification::make()
                        ->title('Cannot correct email')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Guest email corrected')
                    ->success()
                    ->send();
            });
    }

    /**
     * B3 — move a booking to different seats in the same showtime, money-neutral.
     * The picker offers every seat in the auditorium that's currently selectable
     * (available, in-service, in an open section) PLUS this booking's own current
     * seats (so a partial move can keep some). The heavy lifting — availability
     * re-check, occupancy-index TOCTOU guard, equal-price enforcement, and the
     * atomic release+reserve — lives in BookingAmendmentService::reassignSeats;
     * here we just surface its exceptions as notifications.
     */
    public static function reassignSeatsAction(): Action
    {
        return Action::make('reassign_seats')
            ->label('Reassign seats')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (Booking $record): bool => (auth('admin')->user()?->can('bookings.reassign_seats') ?? false)
                && in_array($record->status, [BookingStatus::Confirmed, BookingStatus::Held], true))
            ->fillForm(fn (Booking $record): array => ['seat_ids' => $record->seats()->pluck('seat_id')->all()])
            ->schema(fn (Booking $record): array => [
                CheckboxList::make('seat_ids')
                    ->label('Seats')
                    ->options(static::selectableSeatOptions($record))
                    ->required()
                    ->columns(3)
                    ->helperText('The new seats must cost exactly what the current seats cost — a price change needs a refund and rebooking instead. Seats taken by other bookings, flagged out of service, or in a closed section are not listed.'),
            ])
            ->action(function (Booking $record, array $data): void {
                try {
                    app(BookingAmendmentService::class)->reassignSeats(
                        $record,
                        array_values((array) ($data['seat_ids'] ?? [])),
                        auth('admin')->user(),
                    );
                } catch (BookingAmendmentException|SeatConflictException|ValidationException $e) {
                    Notification::make()
                        ->title('Cannot reassign seats')
                        ->body($e->getMessage())
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Seats reassigned')
                    ->success()
                    ->send();
            });
    }

    /**
     * Seats the admin may move this booking onto: every seat in the showtime's
     * auditorium that's selectable right now, plus the booking's own current
     * seats (which `checkAvailability` reports as taken — by this very booking).
     * Labels carry the per-seat price so a price mismatch is self-explanatory.
     *
     * @return array<string, string> seat id => "A1 · $12.00 · Standard"
     */
    protected static function selectableSeatOptions(Booking $record): array
    {
        $showtime = $record->showtime;
        $seats = Seat::with('section')
            ->where('auditorium_id', $showtime->auditorium_id)
            ->orderBy('row')
            ->orderBy('number')
            ->get();

        $currentSeatIds = $record->seats()->pluck('seat_id')->all();
        $unavailable = app(SeatAvailabilityService::class)
            ->checkAvailability($showtime->id, $seats->pluck('id')->all());

        return $seats
            ->filter(fn ($seat): bool => ! in_array($seat->id, $unavailable, true)
                || in_array($seat->id, $currentSeatIds, true))
            ->mapWithKeys(function ($seat) use ($showtime): array {
                $price = SeatAvailabilityService::priceForSeat($showtime, $seat);
                $section = $seat->section?->name ?? $seat->type->value;

                return [$seat->id => $seat->label.' · $'.number_format($price / 100, 2).' · '.$section];
            })
            ->all();
    }

    /**
     * B6 — refund several bookings at once (e.g. recovering a whole cancelled
     * showtime). Each booking goes through the same row-locked, idempotent
     * `BookingRefundService::refund()` as the single-booking action, so
     * already-refunded / cancelled / in-flight bookings are skipped cleanly
     * and a Stripe failure on one booking never rolls back the others. The
     * result is reported as a refunded / skipped / failed tally.
     */
    public static function bulkRefundAction(): BulkAction
    {
        return BulkAction::make('bulk_refund')
            ->label('Refund selected')
            ->icon('heroicon-o-receipt-refund')
            ->color('danger')
            ->visible(fn (): bool => auth('admin')->user()?->can('bookings.resolve_refund') ?? false)
            ->requiresConfirmation()
            ->modalHeading('Refund selected bookings')
            ->modalDescription('Each refundable booking is refunded individually; already-refunded, cancelled, or in-flight bookings are skipped. This cannot be undone.')
            ->schema([
                Textarea::make('reason')
                    ->label('Refund reason')
                    ->required()
                    ->minLength(10)
                    ->rows(2)
                    ->helperText('Applied to every refunded booking and logged.'),
            ])
            ->action(function (\Illuminate\Support\Collection $records, array $data): void {
                $service = app(BookingRefundService::class);
                $actor = auth('admin')->user();

                $refunded = 0;
                $skipped = 0;
                $failed = 0;

                foreach ($records as $record) {
                    try {
                        $service->refund($record, $data['reason'], $actor);
                        $refunded++;
                    } catch (BookingNotRefundableException) {
                        $skipped++;
                    } catch (\Throwable) {
                        $failed++;
                    }
                }

                $body = "Refunded {$refunded}, skipped {$skipped}".($failed > 0 ? ", failed {$failed}" : '').'.';

                Notification::make()
                    ->title($failed > 0 ? 'Bulk refund finished with errors' : 'Bulk refund complete')
                    ->body($body)
                    ->status($failed > 0 ? 'warning' : 'success')
                    ->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
