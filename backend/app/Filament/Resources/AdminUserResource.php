<?php

namespace App\Filament\Resources;

use App\Exceptions\AdminUserException;
use App\Filament\Resources\AdminUserResource\Pages;
use App\Models\User;
use App\Services\AdminUserService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Role;
use UnitEnum;

/**
 * Staff management (admin-v2 Plan 09) — the UI for the long-seeded
 * `admin_users.*` permissions. Rows are READ-ONLY: identity (name, email,
 * password) belongs to the shared customer account; every mutation here is
 * a narrowly-scoped action through AdminUserService (provision, role
 * assignment, disable/enable), each hidden on the actor's own row.
 */
class AdminUserResource extends BaseResource
{
    protected static ?string $model = User::class;

    protected static ?string $permissionPrefix = 'admin_users';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 80;

    protected static ?string $navigationLabel = 'Admin users';

    protected static ?string $modelLabel = 'admin user';

    protected static ?string $slug = 'admin-users';

    public static function canCreate(): bool
    {
        return false; // provisioning happens via the header action
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false; // admins are disabled, never deleted — audit trail stays intact
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('adminProfile')
            ->with(['adminProfile', 'roles']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('role')
                    ->badge()
                    ->getStateUsing(fn (User $r): string => $r->getRoleNames()->first() ?? '—')
                    ->color('info'),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (User $r): string => $r->adminProfile?->disabled_at !== null ? 'disabled' : 'active')
                    ->color(fn (string $state): string => $state === 'disabled' ? 'danger' : 'success'),
                TextColumn::make('adminProfile.last_login_at')
                    ->label('Last login')
                    ->since()
                    ->placeholder('never'),
            ])
            ->filters([
                TernaryFilter::make('disabled')
                    ->placeholder('All')
                    ->trueLabel('Disabled only')
                    ->falseLabel('Active only')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('adminProfile', fn (Builder $p) => $p->whereNotNull('disabled_at')),
                        false: fn (Builder $q) => $q->whereHas('adminProfile', fn (Builder $p) => $p->whereNull('disabled_at')),
                    ),
            ])
            ->recordActions([
                self::assignRoleAction(),
                self::disableAction(),
                self::enableAction(),
            ])
            ->defaultSort('name');
    }

    public static function provisionAction(): Action
    {
        return Action::make('provision')
            ->label('Add admin')
            ->icon('heroicon-o-user-plus')
            ->visible(fn (): bool => auth('admin')->user()?->can('admin_users.create') ?? false)
            ->schema([
                TextInput::make('name')
                    ->helperText('Optional when the email belongs to an existing customer (promotion).'),
                TextInput::make('email')
                    ->email()
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->minLength(12)
                    ->helperText('Required for a brand-new account. Leave blank when promoting — the customer keeps their password.'),
                Select::make('role')
                    ->options(fn () => Role::where('guard_name', 'admin')->orderBy('name')->pluck('name', 'name'))
                    ->required()
                    ->default('ops'),
            ])
            ->action(function (array $data): void {
                try {
                    $user = app(AdminUserService::class)->provision(
                        $data['name'] ?: null,
                        $data['email'],
                        $data['password'] ?: null,
                        $data['role'],
                        auth('admin')->user(),
                    );
                } catch (AdminUserException $e) {
                    Notification::make()->danger()->title('Cannot add admin')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title("{$user->email} is now {$data['role']}")->send();
            });
    }

    private static function assignRoleAction(): Action
    {
        return Action::make('assign_role')
            ->label('Change role')
            ->icon('heroicon-o-identification')
            ->visible(fn (User $record): bool => (auth('admin')->user()?->can('admin_users.update') ?? false)
                && $record->id !== auth('admin')->id())
            ->schema([
                Select::make('role')
                    ->options(fn () => Role::where('guard_name', 'admin')->orderBy('name')->pluck('name', 'name'))
                    ->required(),
            ])
            ->requiresConfirmation()
            ->action(function (User $record, array $data): void {
                try {
                    app(AdminUserService::class)->assignRole($record, $data['role'], auth('admin')->user());
                } catch (AdminUserException $e) {
                    Notification::make()->danger()->title('Cannot change role')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Role updated')->send();
            });
    }

    private static function disableAction(): Action
    {
        return Action::make('disable')
            ->label('Disable')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->visible(fn (User $record): bool => (auth('admin')->user()?->can('admin_users.update') ?? false)
                && $record->id !== auth('admin')->id()
                && $record->adminProfile?->disabled_at === null)
            ->requiresConfirmation()
            ->modalDescription('Their session dies on the next request and they can no longer sign in. Reversible with Enable.')
            ->action(function (User $record): void {
                try {
                    app(AdminUserService::class)->disable($record, auth('admin')->user());
                } catch (AdminUserException $e) {
                    Notification::make()->danger()->title('Cannot disable')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Admin disabled')->send();
            });
    }

    private static function enableAction(): Action
    {
        return Action::make('enable')
            ->label('Enable')
            ->icon('heroicon-o-check-circle')
            ->color('success')
            ->visible(fn (User $record): bool => (auth('admin')->user()?->can('admin_users.update') ?? false)
                && $record->adminProfile?->disabled_at !== null)
            ->requiresConfirmation()
            ->action(function (User $record): void {
                try {
                    app(AdminUserService::class)->enable($record, auth('admin')->user());
                } catch (AdminUserException $e) {
                    Notification::make()->danger()->title('Cannot enable')->body($e->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title('Admin re-enabled')->send();
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdminUsers::route('/'),
        ];
    }
}
