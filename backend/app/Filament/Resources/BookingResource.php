<?php

namespace App\Filament\Resources;

use App\Enums\BookingStatus;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\BookingResource\Pages;
use App\Models\Auditorium;
use App\Models\Booking;
use App\Models\BookingFoodItem;
use App\Models\BookingSeat;
use App\Models\Location;
use App\Models\Showtime;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;
use UnitEnum;

/**
 * Read-only resource for bookings. v1 intentionally has no cancel or refund
 * surfaces — those are deferred per Plan 07 spec § 4.4. Customer columns
 * synthesize display because authed bookings store `user_id` (UUID) while
 * guest bookings store `guest_email` instead of `customer_email`/`customer_name`.
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
}
