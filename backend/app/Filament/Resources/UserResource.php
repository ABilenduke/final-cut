<?php

namespace App\Filament\Resources;

use App\Enums\LoyaltyTier;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\User;
use App\Services\LoyaltyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use UnitEnum;

/**
 * Customer users. Read-heavy: the only mutable surface is loyalty tier + points,
 * which routes through `LoyaltyService` (see {@see handleRecordUpdate} on the
 * Edit page). `users.update` is intentionally NOT seeded — `canEdit` gates on
 * either loyalty permission instead.
 */
class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $permissionPrefix = 'users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 30;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /**
     * Override — `users.update` is intentionally not seeded (enforced by
     * `RoleSeederTest`). The edit surface only touches loyalty fields, so
     * either loyalty permission suffices.
     */
    public static function canEdit(Model $record): bool
    {
        $u = auth('admin')->user();

        return (bool) ($u?->can('loyalty.adjust_points') || $u?->can('loyalty.adjust_tier'));
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('bookings');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\BookingsRelationManager::class,
            RelationManagers\LoyaltyAdjustmentsRelationManager::class,
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->copyable()
                    ->sortable(),
                TextColumn::make('loyalty_tier')
                    ->label('Tier')
                    ->badge()
                    ->getStateUsing(fn (User $r): string => $r->loyalty_tier->value)
                    ->colors([
                        'gray' => LoyaltyTier::Member->value,
                        'warning' => LoyaltyTier::Premier->value,
                    ])
                    ->sortable(),
                TextColumn::make('loyalty_points')
                    ->label('Points')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('premier_expiry')
                    ->label('Premier expiry')
                    ->date()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->label('Joined')
                    ->date()
                    ->sortable(),
                TextColumn::make('bookings_count')
                    ->label('Bookings')
                    ->numeric()
                    ->sortable(),
                // `created_at` is already rendered as the visible "Joined" column
                // above; only surface `updated_at` from the standard timestamp set
                // to avoid a duplicate column + toggle entry.
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('loyalty_tier')
                    ->label('Tier')
                    ->options([
                        LoyaltyTier::Member->value => 'Member',
                        LoyaltyTier::Premier->value => 'Premier',
                    ]),
                Filter::make('joined_after')
                    ->schema([
                        DatePicker::make('joined_after')->label('Joined after'),
                    ])
                    ->query(fn (Builder $q, array $data) => $q->when(
                        $data['joined_after'] ?? null,
                        fn (Builder $qq, $d) => $qq->whereDate('created_at', '>=', $d),
                    )),
                Filter::make('has_upcoming_booking')
                    ->label('Has upcoming booking')
                    ->query(fn (Builder $q) => $q->whereHas(
                        'bookings.showtime',
                        fn (Builder $qq) => $qq->where('start_time', '>', now()),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * Schema used by both the Edit page form and the ViewUser infolist. On the
     * Edit page only loyalty fields are writable; on the View page everything
     * is read-only via Placeholder components in {@see viewSchema()}.
     */
    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Loyalty')
                ->description('Direct edits route through LoyaltyService and write an audit row. Large adjustments should use the "Adjust Points" action — it requires a reason and surfaces an elevated confirmation.')
                ->schema([
                    TextInput::make('loyalty_points')
                        ->label('Loyalty points')
                        ->numeric()
                        ->required()
                        ->disabled(fn (): bool => ! self::actorCanAdjustPoints())
                        ->dehydrated(fn (): bool => self::actorCanAdjustPoints()),
                    Select::make('loyalty_tier')
                        ->label('Tier')
                        ->options([
                            LoyaltyTier::Member->value => 'Member',
                            LoyaltyTier::Premier->value => 'Premier',
                        ])
                        ->required()
                        ->live()
                        ->disabled(fn (): bool => ! self::actorCanAdjustTier())
                        ->dehydrated(fn (): bool => self::actorCanAdjustTier()),
                    DatePicker::make('premier_expiry')
                        ->label('Premier expiry')
                        ->visible(fn (Get $get): bool => $get('loyalty_tier') === LoyaltyTier::Premier->value)
                        ->disabled(fn (): bool => ! self::actorCanAdjustTier())
                        ->dehydrated(fn (): bool => self::actorCanAdjustTier()),
                ])
                ->columns(2),
        ]);
    }

    public static function actorCanAdjustPoints(): bool
    {
        return (bool) (auth('admin')->user()?->can('loyalty.adjust_points'));
    }

    public static function actorCanAdjustTier(): bool
    {
        return (bool) (auth('admin')->user()?->can('loyalty.adjust_tier'));
    }

    /**
     * View-only schema rendered by ViewUser. Kept separate from `form()` so
     * the Edit page cannot accidentally expose non-loyalty fields for editing.
     */
    public static function viewSchema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profile')
                ->schema([
                    Placeholder::make('name')
                        ->label('Name')
                        ->content(fn (User $record): string => $record->name),
                    Placeholder::make('email')
                        ->label('Email')
                        ->content(fn (User $record): string => $record->email),
                    Placeholder::make('phone')
                        ->label('Phone')
                        ->content(fn (User $record): string => $record->phone ?? '—'),
                    Placeholder::make('date_of_birth')
                        ->label('Date of birth')
                        ->content(fn (User $record): string => $record->date_of_birth?->toDateString() ?? '—'),
                    Placeholder::make('created_at')
                        ->label('Joined')
                        ->content(fn (User $record): string => $record->created_at->toDateString()),
                ])
                ->columns(2),

            Section::make('Loyalty')
                ->schema([
                    Placeholder::make('loyalty_points')
                        ->label('Points')
                        ->content(fn (User $record): string => (string) $record->loyalty_points),
                    Placeholder::make('loyalty_tier')
                        ->label('Tier')
                        ->content(fn (User $record): string => ucfirst($record->loyalty_tier->value)),
                    Placeholder::make('premier_expiry')
                        ->label('Premier expiry')
                        ->content(fn (User $record): string => $record->premier_expiry?->toDateString() ?? '—'),
                ])
                ->columns(3),
        ]);
    }

    /* ====================================================================
     * Loyalty header actions (see Task 5). Factories, not calls — ViewUser
     * returns these from its `getHeaderActions()`.
     * ==================================================================== */

    /**
     * Admin point correction with required reason. Modal description elevates
     * the language when `abs(delta) >= config('loyalty.large_adjustment_threshold')`.
     */
    public static function adjustPointsAction(): Action
    {
        return Action::make('adjust_points')
            ->label('Adjust Points')
            ->icon('heroicon-o-sparkles')
            ->visible(fn () => auth('admin')->user()?->can('loyalty.adjust_points') ?? false)
            ->schema([
                TextInput::make('points_delta')
                    ->label('Points delta')
                    ->numeric()
                    ->required()
                    ->helperText('Positive to add, negative to deduct. Negative balances are permitted for fraud clawbacks.'),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(10)
                    ->helperText('Required. Logged permanently for audit.')
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->modalDescription(function (User $record, array $data = []): string {
                $delta = (int) ($data['points_delta'] ?? 0);
                $threshold = (int) config('loyalty.large_adjustment_threshold');

                if ($delta !== 0 && abs($delta) >= $threshold) {
                    return "This is a large adjustment (±{$threshold} points or more). Double-check before confirming — this action is permanent and logged.";
                }

                return "Adjusting {$delta} points for {$record->email}. Reason will be logged.";
            })
            ->action(function (User $record, array $data): void {
                app(LoyaltyService::class)->adjustPoints(
                    $record,
                    (int) $data['points_delta'],
                    $data['reason'],
                    auth('admin')->user(),
                );

                Notification::make()
                    ->title('Points adjusted and logged.')
                    ->success()
                    ->send();
            });
    }

    public static function upgradePremierAction(): Action
    {
        return Action::make('upgrade_premier')
            ->label('Upgrade to Premier')
            ->icon('heroicon-o-star')
            ->visible(fn (User $record): bool => (bool) (auth('admin')->user()?->can('loyalty.adjust_tier'))
                && $record->loyalty_tier === LoyaltyTier::Member)
            ->schema([
                DatePicker::make('expiry')
                    ->label('Premier expiry')
                    ->required()
                    ->default(fn () => now()->addYear()->toDateString())
                    ->helperText('Premier tier expires on this date unless renewed.'),
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(10)
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function (User $record, array $data): void {
                app(LoyaltyService::class)->grantPremier(
                    $record,
                    Carbon::parse($data['expiry']),
                    $data['reason'],
                    auth('admin')->user(),
                );

                Notification::make()
                    ->title('Premier granted.')
                    ->success()
                    ->send();
            });
    }

    public static function revokePremierAction(): Action
    {
        return Action::make('revoke_premier')
            ->label('Revoke Premier')
            ->icon('heroicon-o-shield-exclamation')
            ->color('danger')
            ->visible(fn (User $record): bool => (bool) (auth('admin')->user()?->can('loyalty.adjust_tier'))
                && $record->loyalty_tier === LoyaltyTier::Premier)
            ->schema([
                Textarea::make('reason')
                    ->label('Reason')
                    ->required()
                    ->minLength(10)
                    ->rows(3),
            ])
            ->requiresConfirmation()
            ->action(function (User $record, array $data): void {
                app(LoyaltyService::class)->revokePremier(
                    $record,
                    $data['reason'],
                    auth('admin')->user(),
                );

                Notification::make()
                    ->title('Premier revoked.')
                    ->success()
                    ->send();
            });
    }
}
