<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Exceptions\BookingFlagException;
use App\Exceptions\BookingNotRefundableException;
use App\Exceptions\BookingNotResendableException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Showtime;
use App\Services\BookingFlagService;
use App\Services\BookingNotificationService;
use App\Services\BookingRefundService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
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
                ])
                ->columns(2),
        ]);
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
}
