<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\BlogPostResource\Pages;
use App\Models\BlogPost;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Admin resource for blog posts (admin-v2 Plan 12). Editorial content —
 * writes go directly through Eloquent (FeaturedSlide/TickerItem stance);
 * the Publish transition logs activity manually.
 *
 * Permission prefix: content.blog
 * Navigation group: Content
 */
class BlogPostResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = BlogPost::class;

    protected static ?string $permissionPrefix = 'content.blog';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-newspaper';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Blog posts';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Post')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(160)
                        ->live(onBlur: true)
                        // Only prefill the slug while creating — editing a
                        // published post's title must not silently move its URL.
                        ->afterStateUpdated(function (Set $set, ?string $state, ?BlogPost $record): void {
                            if ($record === null && $state !== null) {
                                $set('slug', Str::slug($state));
                            }
                        })
                        ->columnSpanFull(),

                    TextInput::make('slug')
                        ->required()
                        ->maxLength(160)
                        ->unique(ignoreRecord: true)
                        ->rules(['alpha_dash'])
                        ->helperText('URL path segment: /blog/{slug}. Changing it after publication breaks inbound links.'),

                    TextInput::make('author_name')
                        ->required()
                        ->maxLength(120)
                        ->default('Final Cut Staff'),

                    Textarea::make('excerpt')
                        ->required()
                        ->rows(3)
                        ->maxLength(400)
                        ->helperText('Shown on the listing card and used as the meta description.')
                        ->columnSpanFull(),

                    FileUpload::make('image_url')
                        ->label('Featured image (optional)')
                        ->image()
                        ->directory('blog-posts')
                        ->disk('public')
                        ->visibility('public')
                        ->imageEditor()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120)
                        ->helperText('Recommended: 1280×720 px, JPEG/PNG/WebP, max 5 MB.')
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make('Body')
                ->schema([
                    Textarea::make('body')
                        ->required()
                        ->rows(18)
                        ->helperText('Plain text. A blank line starts a new paragraph — no markdown.')
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(60),

                TextColumn::make('author_name')
                    ->label('Author'),

                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (BlogPost $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'success',
                        'scheduled' => 'info',
                        default => 'gray', // draft
                    }),

                TextColumn::make('published_at')
                    ->label('Published')
                    ->dateTime('M j, Y g:i A')
                    ->sortable()
                    ->placeholder('Draft'),

                ...self::standardTimestamps(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalDescription('The post becomes publicly visible at /blog immediately.')
                    ->visible(fn (BlogPost $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('content.blog.update') ?? false)
                    ->action(function (BlogPost $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()
                            ->success()
                            ->title('Post published')
                            ->body("\"{$record->title}\" is now live.")
                            ->send();
                    }),

                EditAction::make(),

                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogPosts::route('/'),
            'create' => Pages\CreateBlogPost::route('/create'),
            'edit' => Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }
}
