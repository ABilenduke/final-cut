<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\JobOpeningResource\Pages;
use App\Models\JobOpening;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin resource for careers-page job openings (admin-v2 Plan 13).
 *
 * Permission prefix: content.careers
 */
class JobOpeningResource extends BaseResource
{
    use TimestampColumns;

    protected static ?string $model = JobOpening::class;

    protected static ?string $permissionPrefix = 'content.careers';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Job openings';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Opening')
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('department')
                        ->required()
                        ->maxLength(80),

                    Select::make('employment_type')
                        ->options([
                            'Full-time' => 'Full-time',
                            'Part-time' => 'Part-time',
                            'Seasonal' => 'Seasonal',
                        ])
                        ->required()
                        ->default('Full-time'),

                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0),

                    Textarea::make('description')
                        ->required()
                        ->rows(4)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('department')->badge()->color('gray'),
                TextColumn::make('employment_type')->label('Type'),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (JobOpening $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => $state === 'live' ? 'success' : 'gray'),
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
                    ->visible(fn (JobOpening $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('content.careers.update') ?? false)
                    ->action(function (JobOpening $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()->success()->title('Opening published')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJobOpenings::route('/'),
            'create' => Pages\CreateJobOpening::route('/create'),
            'edit' => Pages\EditJobOpening::route('/{record}/edit'),
        ];
    }
}
