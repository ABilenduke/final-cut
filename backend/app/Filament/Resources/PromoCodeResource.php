<?php

namespace App\Filament\Resources;

use App\Exceptions\PromoCodeInUseException;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\PromoCodeResource\Pages;
use App\Models\PromoCode;
use App\Services\PromoCodeService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Every write (create/update/deactivate/delete) routes through
 * `PromoCodeService` with `auth('admin')->user()` as the actor so the
 * service emits `activity_log` rows. The delete action is hidden for any
 * promo with `uses_count > 0`, and the service also enforces this rule —
 * the UI guard is belt-and-braces.
 */
class PromoCodeResource extends BaseResource
{
    use FormatsCurrency;
    use TimestampColumns;

    protected static ?string $model = PromoCode::class;

    protected static ?string $permissionPrefix = 'promos';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-ticket';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 50;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Code')
                ->schema([
                    TextInput::make('code')
                        ->required()
                        ->maxLength(32)
                        ->alphaDash()
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set) => $set('code', strtoupper((string) $state)))
                        ->dehydrateStateUsing(fn ($state) => strtoupper((string) $state))
                        ->unique(ignoreRecord: true)
                        ->helperText('Uppercase letters, numbers, and dashes only. Customers enter this at checkout.'),
                ]),

            Section::make('Discount')
                ->schema([
                    Select::make('discount_type')
                        ->options([
                            PromoCode::TYPE_PERCENTAGE => 'Percentage off',
                            PromoCode::TYPE_FIXED_CENTS => 'Fixed amount (cents)',
                        ])
                        ->required()
                        ->live(),
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->minValue(1)
                        ->suffix(fn ($get) => $get('discount_type') === PromoCode::TYPE_PERCENTAGE ? '%' : ' ¢')
                        ->helperText(fn ($get) => $get('discount_type') === PromoCode::TYPE_PERCENTAGE
                            ? '1–100 (percentage)'
                            : 'Cents, e.g., 500 = $5.00 off'),
                ])
                ->columns(2),

            Section::make('Limits')
                ->schema([
                    TextInput::make('usage_limit')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->helperText('Max total uses across all customers. Leave blank for unlimited.'),
                    TextInput::make('per_user_limit')
                        ->numeric()
                        ->nullable()
                        ->minValue(1)
                        ->helperText('Max uses per user. Schema-level only in v1 — not yet enforced at checkout.'),
                    DateTimePicker::make('expires_at')->nullable(),
                    Toggle::make('is_active')->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->copyable()->badge(),
                TextColumn::make('discount_type')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        PromoCode::TYPE_PERCENTAGE => 'Percent',
                        PromoCode::TYPE_FIXED_CENTS => 'Fixed',
                        default => $state,
                    }),
                TextColumn::make('amount')
                    ->formatStateUsing(fn ($state, $record) => $record->discount_type === PromoCode::TYPE_PERCENTAGE
                        ? "{$state}%"
                        : self::centsToDisplay((int) $state)),
                TextColumn::make('uses_count')
                    ->label('Used')
                    ->formatStateUsing(fn ($state, $record) => $record->usage_limit
                        ? "{$state} / {$record->usage_limit}"
                        : (string) $state),
                TextColumn::make('expires_at')->dateTime()->sortable(),
                IconColumn::make('is_active')->boolean(),
                ...self::standardTimestamps(),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-pause')
                    ->color('warning')
                    ->visible(fn ($record) => $record->is_active
                        && auth('admin')->user()?->can('promos.update'))
                    ->requiresConfirmation()
                    ->modalDescription('Deactivate this promo code? Customers will no longer be able to apply it at checkout, but usage history is preserved.')
                    ->action(fn ($record) => app(PromoCodeService::class)
                        ->deactivate($record, auth('admin')->user())),
                DeleteAction::make()
                    ->visible(fn ($record) => $record->uses_count === 0
                        && auth('admin')->user()?->can('promos.delete'))
                    ->using(function (PromoCode $record) {
                        try {
                            app(PromoCodeService::class)
                                ->delete($record, auth('admin')->user());
                        } catch (PromoCodeInUseException $e) {
                            Notification::make()
                                ->title('Cannot delete')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                            throw new Halt;
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPromoCodes::route('/'),
            'create' => Pages\CreatePromoCode::route('/create'),
            'edit' => Pages\EditPromoCode::route('/{record}/edit'),
        ];
    }
}
