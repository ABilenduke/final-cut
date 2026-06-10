<?php

namespace App\Filament\Resources;

use App\Exceptions\OutboxRetryException;
use App\Filament\Resources\DispatchOutboxResource\Pages;
use App\Models\DispatchOutbox;
use App\Services\OutboxRetryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only ops surface over dispatch_outbox (admin-v2 Plan 08) — the row
 * view behind the dashboard's OpsHealth counts. Parked rows (failed_at set)
 * carry a Retry action routed through OutboxRetryService. Admin-role only:
 * payloads expose customer emails/ids and retrying is an ops-level call.
 */
class DispatchOutboxResource extends BaseResource
{
    protected static ?string $model = DispatchOutbox::class;

    protected static ?string $permissionPrefix = 'outbox';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-inbox-stack';

    protected static string|UnitEnum|null $navigationGroup = 'System';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Outbox';

    protected static ?string $modelLabel = 'outbox row';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PARKED = 'parked';

    public const STATUS_PROCESSED = 'processed';

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

    public static function getNavigationBadge(): ?string
    {
        if (! (auth('admin')->user()?->can('outbox.view') ?? false)) {
            return null;
        }

        $parked = DispatchOutbox::whereNotNull('failed_at')->count();

        return $parked > 0 ? (string) $parked : null;
    }

    /** Derive the operator-facing status from the worker columns. */
    public static function deriveStatus(DispatchOutbox $row): string
    {
        if ($row->failed_at !== null) {
            return self::STATUS_PARKED;
        }

        if ($row->processed_at !== null) {
            return self::STATUS_PROCESSED;
        }

        return self::STATUS_PENDING;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('event_type')->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (DispatchOutbox $r): string => self::deriveStatus($r))
                    ->color(fn (string $state): string => match ($state) {
                        self::STATUS_PARKED => 'danger',
                        self::STATUS_PROCESSED => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('attempts')->sortable(),
                TextColumn::make('available_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('processed_at')->since()->placeholder('—'),
                TextColumn::make('failed_at')->since()->placeholder('—'),
                TextColumn::make('last_error')->limit(60)->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        self::STATUS_PENDING => 'Pending',
                        self::STATUS_PARKED => 'Parked',
                        self::STATUS_PROCESSED => 'Processed',
                    ])
                    ->query(fn (Builder $q, array $data) => match ($data['value'] ?? null) {
                        self::STATUS_PENDING => $q->whereNull('processed_at')->whereNull('failed_at'),
                        self::STATUS_PARKED => $q->whereNotNull('failed_at'),
                        self::STATUS_PROCESSED => $q->whereNotNull('processed_at'),
                        default => $q,
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('danger')
                    ->visible(fn (DispatchOutbox $record): bool => (auth('admin')->user()?->can('outbox.retry') ?? false)
                        && $record->failed_at !== null)
                    ->requiresConfirmation()
                    ->modalDescription(fn (DispatchOutbox $record): string => sprintf(
                        'Re-queues this %s row with a fresh retry budget. Last error: %s',
                        $record->event_type,
                        $record->last_error ?? '—',
                    ))
                    ->action(function (DispatchOutbox $record): void {
                        try {
                            app(OutboxRetryService::class)->retry($record, auth('admin')->user());
                        } catch (OutboxRetryException $e) {
                            Notification::make()
                                ->danger()
                                ->title('Cannot retry')
                                ->body($e->getMessage())
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Row re-queued')
                            ->body('The next outbox:dispatch tick (every minute) will re-attempt delivery.')
                            ->send();
                    }),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Row')
                ->schema([
                    Placeholder::make('event_type')
                        ->content(fn (DispatchOutbox $record): string => $record->event_type),
                    Placeholder::make('status')
                        ->content(fn (DispatchOutbox $record): string => self::deriveStatus($record)),
                    Placeholder::make('attempts')
                        ->content(fn (DispatchOutbox $record): string => (string) $record->attempts),
                    Placeholder::make('last_error')
                        ->content(fn (DispatchOutbox $record): string => $record->last_error ?? '—'),
                ])
                ->columns(2),

            Section::make('Payload')
                ->schema([
                    Placeholder::make('payload')
                        ->label('')
                        ->content(fn (DispatchOutbox $record): string => json_encode(
                            $record->payload,
                            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
                        ) ?: '—'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDispatchOutbox::route('/'),
            'view' => Pages\ViewDispatchOutbox::route('/{record}'),
        ];
    }
}
