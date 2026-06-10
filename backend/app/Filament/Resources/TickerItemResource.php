<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\TickerItemResource\Pages;
use App\Models\TickerItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin resource for Neural Ticker items (admin-v2 Plan 11). Editorial
 * content with no downstream invariants — writes go directly through
 * Eloquent (same stance as FeaturedSlide); the Publish transition logs
 * activity manually because it carries semantic weight.
 *
 * Permission prefix: marketing.ticker
 * Navigation group: Marketing
 */
class TickerItemResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = TickerItem::class;

    protected static ?string $permissionPrefix = 'marketing.ticker';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-arrow-down';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Neural Ticker';

    protected static ?string $modelLabel = 'ticker item';

    protected static ?string $recordTitleAttribute = 'text';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Content')
                ->schema([
                    TextInput::make('label')
                        ->required()
                        ->maxLength(32)
                        ->helperText('The eyebrow shown before the text, e.g. "Now Showing".'),

                    TextInput::make('text')
                        ->required()
                        ->maxLength(160)
                        ->helperText('The scrolling line itself. Keep it telegraphic — this is bridge-console telemetry, not a paragraph.'),

                    TextInput::make('href')
                        ->label('Link (optional)')
                        ->helperText('Internal path (e.g. /events/kubrick) or full http(s) URL.')
                        ->rules(['nullable', 'string', fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            if ($value === null || $value === '') {
                                return;
                            }
                            $scheme = is_string($value) ? strtolower((string) parse_url($value, PHP_URL_SCHEME)) : '';
                            $isRelative = is_string($value) && str_starts_with($value, '/');
                            $isHttp = in_array($scheme, ['http', 'https'], true);

                            if (! ($isRelative || $isHttp)) {
                                $fail('The link must be an http(s) URL or a path starting with /.');
                            }
                        }]),
                ])
                ->columns(1),

            Section::make('Scheduling')
                ->schema([
                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first. Drag in the list to reorder visually.'),

                    DateTimePicker::make('starts_at')
                        ->nullable()
                        ->helperText('When the item becomes visible. Leave blank for immediate.'),

                    DateTimePicker::make('ends_at')
                        ->nullable()
                        ->helperText('When the item stops being visible. Leave blank for indefinite.')
                        ->after('starts_at'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('text')
                    ->searchable()
                    ->limit(60),

                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (TickerItem $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        'expired' => 'danger',
                        default => 'gray', // draft
                    }),

                TextColumn::make('display_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Draft'),

                ...self::standardTimestamps(),
            ])
            ->defaultSort('display_order')
            ->reorderable('display_order')
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('This item joins the public ticker immediately (subject to any scheduling window).')
                    ->visible(fn (TickerItem $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('marketing.ticker.update') ?? false)
                    ->action(function (TickerItem $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()
                            ->success()
                            ->title('Ticker item published')
                            ->send();
                    }),

                EditAction::make(),

                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickerItems::route('/'),
            'create' => Pages\CreateTickerItem::route('/create'),
            'edit' => Pages\EditTickerItem::route('/{record}/edit'),
        ];
    }
}
