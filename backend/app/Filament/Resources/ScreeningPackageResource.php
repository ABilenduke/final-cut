<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\ScreeningPackageResource\Pages;
use App\Models\ScreeningPackage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/**
 * Admin resource for private-screening packages (admin-v2 Plan 14).
 *
 * Permission prefix: content.packages
 */
class ScreeningPackageResource extends BaseResource
{
    use FormatsCurrency;
    use TimestampColumns;

    protected static ?string $model = ScreeningPackage::class;

    protected static ?string $permissionPrefix = 'content.packages';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Screening packages';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Package')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(120),

                    TextInput::make('starting_price')
                        ->label('Starting price (cents)')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('¢')
                        ->required()
                        ->helperText('Store as cents: $350.00 → 35000. The page renders "From $350".'),

                    TextInput::make('display_order')
                        ->numeric()
                        ->default(0),

                    Textarea::make('description')
                        ->required()
                        ->rows(3)
                        ->columnSpanFull(),

                    Repeater::make('features')
                        ->simple(
                            TextInput::make('feature')->required()->maxLength(160),
                        )
                        ->minItems(1)
                        ->reorderable()
                        ->helperText('The bulleted "what\'s included" list on the package card.')
                        ->columnSpanFull(),
                ])
                ->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('starting_price')
                    ->label('From')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay($state)),
                TextColumn::make('status')
                    ->badge()
                    ->getStateUsing(fn (ScreeningPackage $record): string => $record->displayStatus())
                    ->color(fn (string $state): string => $state === 'live' ? 'success' : 'gray'),
                TextColumn::make('display_order')->label('Order')->sortable(),
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
                    ->visible(fn (ScreeningPackage $record): bool => $record->published_at === null)
                    ->authorize(fn (): bool => auth('admin')->user()?->can('content.packages.update') ?? false)
                    ->action(function (ScreeningPackage $record): void {
                        $record->update(['published_at' => now()]);

                        activity('admin')
                            ->causedBy(auth('admin')->user())
                            ->performedOn($record)
                            ->log('published');

                        Notification::make()->success()->title('Package published')->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListScreeningPackages::route('/'),
            'create' => Pages\CreateScreeningPackage::route('/create'),
            'edit' => Pages\EditScreeningPackage::route('/{record}/edit'),
        ];
    }
}
