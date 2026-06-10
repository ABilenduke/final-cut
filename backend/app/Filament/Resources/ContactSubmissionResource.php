<?php

namespace App\Filament\Resources;

use App\Exceptions\ContactSubmissionException;
use App\Filament\Resources\ContactSubmissionResource\Pages;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Services\ContactSubmissionService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only inbox for contact-form submissions (admin-v2 Plan 10 — the form
 * previously only logged). Mark-handled is the single mutation, attributed
 * via handled_by + activity.
 */
class ContactSubmissionResource extends BaseResource
{
    protected static ?string $model = ContactSubmission::class;

    protected static ?string $permissionPrefix = 'contact';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-ellipsis';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 45;

    protected static ?string $navigationLabel = 'Contact messages';

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
        if (! (auth('admin')->user()?->can('contact.view') ?? false)) {
            return null;
        }

        $unhandled = ContactSubmission::whereNull('handled_at')->count();

        return $unhandled > 0 ? (string) $unhandled : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('subject')->searchable()->limit(50),
                TextColumn::make('handled')
                    ->badge()
                    ->getStateUsing(fn (ContactSubmission $r): string => $r->handled_at !== null ? 'handled' : 'new')
                    ->color(fn (string $state): string => $state === 'handled' ? 'success' : 'warning'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                TernaryFilter::make('handled')
                    ->placeholder('All')
                    ->trueLabel('Handled')
                    ->falseLabel('New')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('handled_at'),
                        false: fn (Builder $q) => $q->whereNull('handled_at'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('mark_handled')
                    ->label('Mark handled')
                    ->icon('heroicon-o-check')
                    ->color('success')
                    ->visible(fn (ContactSubmission $record): bool => (auth('admin')->user()?->can('contact.resolve') ?? false)
                        && $record->handled_at === null)
                    ->requiresConfirmation()
                    ->action(function (ContactSubmission $record): void {
                        try {
                            app(ContactSubmissionService::class)->markHandled($record, auth('admin')->user());
                        } catch (ContactSubmissionException $e) {
                            Notification::make()->danger()->title('Cannot mark handled')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Marked handled')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Submission')
                ->schema([
                    Placeholder::make('name')
                        ->content(fn (ContactSubmission $record): string => $record->name),
                    Placeholder::make('email')
                        ->content(fn (ContactSubmission $record): string => $record->email),
                    Placeholder::make('subject')
                        ->content(fn (ContactSubmission $record): string => $record->subject),
                    Placeholder::make('handled')
                        ->content(function (ContactSubmission $record): string {
                            if ($record->handled_at === null) {
                                return 'New';
                            }

                            // handled_by is nullOnDelete — the handler User may be gone.
                            $handler = $record->handler;

                            return 'Handled '.$record->handled_at->diffForHumans()
                                .' by '.($handler instanceof User ? $handler->name : '—');
                        }),
                ])
                ->columns(2),

            Section::make('Message')
                ->schema([
                    Placeholder::make('message')
                        ->label('')
                        ->content(fn (ContactSubmission $record): string => $record->message),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListContactSubmissions::route('/'),
            'view' => Pages\ViewContactSubmission::route('/{record}'),
        ];
    }
}
