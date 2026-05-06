<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\FeaturedSlideResource\Pages;
use App\Models\FeaturedSlide;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin resource for the home hero carousel slides.
 *
 * Slides are editorial content with no downstream invariants that require
 * a service layer — writes go directly through Eloquent. Activity log
 * entries are written manually on the Publish custom action because that
 * transition carries semantic weight. Standard CRUD activity is not traced
 * at the model level (no LogsActivity on FeaturedSlide) — consistent with
 * CalendarEvent and MenuItem.
 *
 * Permission prefix: marketing.featured_slides
 * Navigation group: Marketing
 */
class FeaturedSlideResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = FeaturedSlide::class;

    protected static ?string $permissionPrefix = 'marketing.featured_slides';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-photo';

    protected static string|UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Featured Slides';

    protected static ?string $recordTitleAttribute = 'headline';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Content')
                ->schema([
                    TextInput::make('headline')
                        ->required()
                        ->maxLength(80)
                        ->helperText('Max 80 characters. This is the hero title text.')
                        ->columnSpanFull(),

                    TextInput::make('sub_headline')
                        ->maxLength(160)
                        ->helperText('Optional. Max 160 characters.')
                        ->columnSpanFull(),

                    FileUpload::make('image_url')
                        ->label('Hero image')
                        ->image()
                        ->directory('featured-slides')
                        ->disk('public')
                        ->visibility('public')
                        ->imageEditor()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120) // 5 MB
                        ->helperText('Recommended: 1920×800 px, JPEG/PNG/WebP, max 5 MB.')
                        ->columnSpanFull(),
                ]),

            Section::make('Call to Action')
                ->schema([
                    TextInput::make('cta_label')
                        ->required()
                        ->maxLength(24)
                        ->helperText('Button label. Max 24 characters (e.g. "See the lineup").'),

                    TextInput::make('cta_href')
                        ->required()
                        ->helperText('URL or internal route path (e.g. `/movies/the-brutalist`). Full URLs are accepted too.')
                        ->rules(['required', 'string', fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                            if (! (
                                filter_var($value, FILTER_VALIDATE_URL) ||
                                (is_string($value) && str_starts_with($value, '/'))
                            )) {
                                $fail('The CTA link must be a full URL or a path starting with /.');
                            }
                        }]),
                ]),

            Section::make('Scheduling')
                ->schema([
                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Lower numbers appear first. Use the drag handle in the list to reorder visually.'),

                    DateTimePicker::make('starts_at')
                        ->nullable()
                        ->helperText('When the slide becomes visible. Leave blank for immediate.'),

                    DateTimePicker::make('ends_at')
                        ->nullable()
                        ->helperText('When the slide stops being visible. Leave blank for indefinite.')
                        ->after('starts_at'),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label('Image')
                    ->disk('public')
                    ->width(50)
                    ->height(30),

                TextColumn::make('headline')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (FeaturedSlide $record): string => $record->displayStatus())
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

                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('—'),

                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('—'),

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
                    ->modalHeading('Publish this slide?')
                    ->modalDescription('This slide will become publicly visible immediately (subject to any scheduling window).')
                    ->visible(fn (FeaturedSlide $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('marketing.featured_slides.update') ?? false)
                    ->action(function (FeaturedSlide $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()
                            ->success()
                            ->title('Slide published')
                            ->body("\"{$record->headline}\" is now live.")
                            ->send();
                    }),

                EditAction::make(),

                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFeaturedSlides::route('/'),
            'create' => Pages\CreateFeaturedSlide::route('/create'),
            'edit' => Pages\EditFeaturedSlide::route('/{record}/edit'),
        ];
    }
}
