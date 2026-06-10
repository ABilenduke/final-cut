<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\FaqItemResource\Pages;
use App\Models\FaqItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin resource for FAQ entries (admin-v2 Plan 13). Editorial content —
 * direct Eloquent writes; Publish logs activity manually.
 *
 * Permission prefix: content.faq
 */
class FaqItemResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = FaqItem::class;

    protected static ?string $permissionPrefix = 'content.faq';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'FAQ';

    protected static ?string $modelLabel = 'FAQ item';

    protected static ?string $recordTitleAttribute = 'question';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Entry')
                ->schema([
                    TextInput::make('category')
                        ->required()
                        ->maxLength(80)
                        ->datalist(fn () => FaqItem::query()->distinct()->pluck('category')->all())
                        ->helperText('Groups the public page. Reuse an existing category name unless you mean to start a new section.'),

                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0)
                        ->helperText('Orders items within and across categories — a category appears where its first item sorts.'),

                    TextInput::make('question')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),

                    Textarea::make('answer')
                        ->required()
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('category')->badge()->color('gray')->sortable(),
                TextColumn::make('question')->searchable()->limit(70),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (FaqItem $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => $state === 'live' ? 'success' : 'gray'),
                TextColumn::make('display_order')->label('Order')->sortable(),
                ...self::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(fn () => FaqItem::query()->distinct()->orderBy('category')->pluck('category', 'category')->all()),
            ])
            ->defaultSort('display_order')
            ->reorderable('display_order')
            ->recordActions([
                Action::make('publish')
                    ->label('Publish')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (FaqItem $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('content.faq.update') ?? false)
                    ->action(function (FaqItem $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()->success()->title('FAQ item published')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFaqItems::route('/'),
            'create' => Pages\CreateFaqItem::route('/create'),
            'edit' => Pages\EditFaqItem::route('/{record}/edit'),
        ];
    }
}
