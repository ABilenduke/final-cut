<?php

namespace App\Filament\Resources;

use App\Enums\GiftCardStatus;
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
 * Read-focused resource: no create/edit/delete pages. The only write action
 * is `void`, which routes through `GiftCardService::void` with the admin
 * actor and dispatches a queued finance notification on success.
 */
class GiftCardResource extends BaseResource
{
    use FormatsCurrency;
    use TimestampColumns;

    protected static ?string $model = GiftCard::class;

    protected static ?string $permissionPrefix = 'gift_cards';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

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
                Filter::make('with_balance')
                    ->label('Has remaining balance')
                    ->query(fn (Builder $q) => $q->where('current_balance', '>', 0)),
            ])
            ->recordActions([
                ViewAction::make(),
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
}
