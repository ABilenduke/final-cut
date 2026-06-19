<?php

namespace App\Filament\Resources;

use App\Enums\GiftCardDeliveryMethod;
use App\Enums\GiftCardEdition;
use App\Enums\GiftCardStatus;
use App\Exceptions\GiftCardNotAdjustableException;
use App\Exceptions\GiftCardNotVoidableException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\GiftCardResource\Pages;
use App\Filament\Resources\GiftCardResource\RelationManagers\BalanceHistoryRelationManager;
use App\Models\GiftCard;
use App\Services\GiftCardService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-focused resource: no create/edit/delete pages. Its write actions are
 * `adjust_balance` and `void`, each routed through `GiftCardService` with the
 * admin actor; `void` dispatches a queued finance notification on success.
 */
class GiftCardResource extends BaseResource
{
    use FormatsCurrency;
    use TimestampColumns;

    protected static ?string $model = GiftCard::class;

    protected static ?string $permissionPrefix = 'gift_cards';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    // Distinct within Operations (was 30, colliding with User + Auditorium).
    protected static ?int $navigationSort = 32;

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

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextEntry::make('code')->copyable(),
                    TextEntry::make('recipient_name'),
                    TextEntry::make('recipient_email'),
                    TextEntry::make('sender_name'),
                    TextEntry::make('message'),
                ])
                ->columns(2),

            Section::make('Balance & Status')
                ->schema([
                    TextEntry::make('initial_balance')
                        ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state)),
                    TextEntry::make('current_balance')
                        ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state)),
                    TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => self::statusColor($state)),
                    TextEntry::make('voided_at')->dateTime()->placeholder('—'),
                    TextEntry::make('voided_reason')->placeholder('—'),
                ])
                ->columns(2),

            Section::make('Design & Delivery')
                ->schema([
                    TextEntry::make('edition')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::enumLabel($state, GiftCardEdition::class))
                        ->color(fn ($state) => self::editionColor($state)),
                    TextEntry::make('delivery_method')
                        ->label('Delivery method')
                        ->badge()
                        ->formatStateUsing(fn ($state) => self::enumLabel($state, GiftCardDeliveryMethod::class))
                        ->color(fn ($state) => self::deliveryMethodColor($state)),
                    TextEntry::make('scheduled_send_at')
                        ->label('Scheduled send')
                        ->dateTime()
                        ->placeholder('Sent immediately'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->copyable()->badge(),
                TextColumn::make('recipient_name'),
                TextColumn::make('recipient_email')->searchable(),
                TextColumn::make('sender_name'),
                TextColumn::make('initial_balance')
                    ->label('Initial')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state)),
                TextColumn::make('current_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state))
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => self::statusColor($state)),
                TextColumn::make('edition')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::enumLabel($state, GiftCardEdition::class))
                    ->color(fn ($state) => self::editionColor($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('delivery_method')
                    ->label('Delivery')
                    ->badge()
                    ->formatStateUsing(fn ($state) => self::enumLabel($state, GiftCardDeliveryMethod::class))
                    ->color(fn ($state) => self::deliveryMethodColor($state))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('scheduled_send_at')
                    ->label('Scheduled')
                    ->dateTime()
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('purchased_at')->date()->sortable(),
                ...self::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'active' => 'Active',
                        'depleted' => 'Depleted',
                        'voided' => 'Voided',
                        'expired' => 'Expired',
                    ]),
                SelectFilter::make('edition')
                    ->options([
                        'reactor' => 'Reactor',
                        'gold' => 'Charter Gold',
                        'void' => 'Pure Void',
                    ]),
                SelectFilter::make('delivery_method')
                    ->label('Delivery')
                    ->options([
                        'email' => 'By email',
                        'print' => 'Printed & posted',
                    ]),
                Filter::make('with_balance')
                    ->label('Has remaining balance')
                    ->query(fn (Builder $q) => $q->where('current_balance', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('adjust_balance')
                    ->label('Adjust balance')
                    ->color('warning')
                    ->icon('heroicon-o-scale')
                    ->visible(fn ($record) => (auth('admin')->user()?->can('gift_cards.adjust') ?? false)
                        && in_array($record->status, [GiftCardStatus::Active, GiftCardStatus::Depleted], true))
                    ->schema([
                        TextInput::make('amount_cents')
                            ->label('Adjustment (cents)')
                            ->numeric()
                            ->integer()
                            ->required()
                            ->rule('not_in:0')
                            ->helperText('Signed cents: 500 credits $5.00, -500 deducts $5.00. The balance can never go below zero.'),
                        Textarea::make('reason')
                            ->required()
                            ->minLength(10)
                            ->helperText('Required. Written to the balance ledger and the activity log.'),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription(fn ($record) => 'Current balance: '
                        .self::centsToDisplay($record->current_balance)
                        .'. The adjustment is recorded as a ledger entry attributed to you.')
                    ->action(function ($record, array $data) {
                        try {
                            app(GiftCardService::class)
                                ->adjust($record, (int) $data['amount_cents'], $data['reason'], auth('admin')->user());
                            Notification::make()
                                ->title('Balance adjusted to '.self::centsToDisplay($record->refresh()->current_balance))
                                ->success()
                                ->send();
                        } catch (GiftCardNotAdjustableException $e) {
                            Notification::make()
                                ->title('Gift card cannot be adjusted')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                Action::make('void')
                    ->label('Void')
                    ->color('danger')
                    ->icon('heroicon-o-x-circle')
                    ->visible(fn ($record) => auth('admin')->user()?->can('gift_cards.void')
                        && $record->status === GiftCardStatus::Active)
                    ->schema([
                        Textarea::make('reason')
                            ->required()
                            ->minLength(20)
                            ->helperText('Required. Finance team is notified by email.'),
                    ])
                    ->requiresConfirmation()
                    ->modalDescription(fn ($record) => 'This will void the gift card with remaining balance '
                        .self::centsToDisplay($record->current_balance)
                        .'. A finance notification will be queued so the refund can be processed against the original purchaser.')
                    ->action(function ($record, array $data) {
                        try {
                            app(GiftCardService::class)
                                ->void($record, $data['reason'], auth('admin')->user());
                            Notification::make()
                                ->title('Gift card voided. Finance notification queued.')
                                ->success()
                                ->send();
                        } catch (GiftCardNotVoidableException $e) {
                            Notification::make()
                                ->title('Gift card cannot be voided')
                                ->body(match ($e->reason) {
                                    GiftCardNotVoidableException::REASON_ALREADY_VOIDED => 'This card has already been voided.',
                                    GiftCardNotVoidableException::REASON_DEPLETED => 'This card has a zero balance.',
                                    GiftCardNotVoidableException::REASON_EXPIRED => 'This card is expired.',
                                    default => $e->getMessage(),
                                })
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            BalanceHistoryRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGiftCards::route('/'),
            'view' => Pages\ViewGiftCard::route('/{record}'),
        ];
    }

    private static function statusColor(GiftCardStatus|string|null $state): string
    {
        $value = $state instanceof GiftCardStatus ? $state->value : $state;

        return match ($value) {
            'active' => 'success',
            'depleted' => 'gray',
            'voided' => 'danger',
            'expired' => 'warning',
            default => 'gray',
        };
    }

    /**
     * Render a labelled enum for table/infolist columns. The
     * `\App\Enums\HasLabel` constraint is what gives PHPStan confidence the
     * resolved enum exposes a `label()` method; the generic erases the
     * union of concrete types.
     *
     * @template T of \BackedEnum&\App\Enums\HasLabel
     *
     * @param  class-string<T>  $enumClass
     */
    private static function enumLabel(BackedEnum|string|null $state, string $enumClass): string
    {
        if ($state === null) {
            return '—';
        }

        if ($state instanceof $enumClass) {
            return $state->label();
        }

        /** @var T|null $enum */
        $enum = $enumClass::tryFrom((string) $state);

        return $enum?->label() ?? (string) $state;
    }

    private static function editionColor(GiftCardEdition|string|null $state): string
    {
        $value = $state instanceof GiftCardEdition ? $state->value : $state;

        return match ($value) {
            'reactor' => 'danger',
            'gold' => 'warning',
            default => 'gray',
        };
    }

    private static function deliveryMethodColor(GiftCardDeliveryMethod|string|null $state): string
    {
        $value = $state instanceof GiftCardDeliveryMethod ? $state->value : $state;

        return match ($value) {
            'email' => 'info',
            default => 'gray',
        };
    }
}
