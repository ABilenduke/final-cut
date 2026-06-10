<?php

namespace App\Filament\Resources;

use App\Enums\MenuCategory;
use App\Filament\Concerns\FormatsCurrency;
use App\Filament\Concerns\TimestampColumns;
use App\Filament\Resources\MenuItemResource\Pages;
use App\Models\MenuItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Menu items have no downstream invariants today, so writes use Filament's
 * default Eloquent persistence with no dedicated service. If a future
 * invariant emerges (combos, cross-location price rules), promote writes to
 * a `MenuService` following the `?User $actor` pattern used elsewhere.
 */
class MenuItemResource extends BaseResource
{
    use FormatsCurrency;
    use TimestampColumns;

    protected static ?string $model = MenuItem::class;

    protected static ?string $permissionPrefix = 'menu';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cake';

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 40;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identity')
                ->schema([
                    TextInput::make('name')->required()->maxLength(255),
                    Select::make('category')
                        ->options(collect(MenuCategory::cases())->mapWithKeys(
                            fn (MenuCategory $c) => [$c->value => ucfirst($c->value)],
                        )->all())
                        ->required(),
                ])
                ->columns(2),

            Section::make('Content')
                ->schema([
                    Textarea::make('description')->rows(3),
                    FileUpload::make('image_url')
                        ->image()
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize(5120) // 5 MB; raster-only — SVG excluded (XML → stored XSS off the public disk)
                        ->directory('menu-items')
                        ->disk('public')
                        ->visibility('public')
                        ->imageEditor(),
                    TextInput::make('price')
                        ->numeric()
                        ->required()
                        ->suffix(' ¢')
                        ->helperText('Base price in cents, e.g., $5.99 = 599. Individual locations may override.'),
                ]),

            Section::make('Dietary / Allergens')
                ->schema([
                    CheckboxList::make('allergens')
                        ->options([
                            'nuts' => 'Nuts',
                            'dairy' => 'Dairy',
                            'gluten' => 'Gluten',
                            'soy' => 'Soy',
                            'eggs' => 'Eggs',
                            'shellfish' => 'Shellfish',
                        ])
                        ->columns(3)
                        ->helperText('Stored as JSON array on menu_items.allergens.'),
                    CheckboxList::make('dietary')
                        ->options([
                            'vegan' => 'Vegan',
                            'vegetarian' => 'Vegetarian',
                            'gluten_free' => 'Gluten-Free',
                        ])
                        ->columns(3)
                        ->helperText('Stored as JSON array on menu_items.dietary.'),
                ]),

            Section::make('Availability (global)')
                ->schema([
                    // Toggle is backed by the nullable `unavailable_at` timestamp —
                    // off = stamp with now(), on = null. Customer browse endpoints
                    // also filter by per-location pivot unavailable_at below.
                    Toggle::make('unavailable_at')
                        ->label('Available globally')
                        ->default(null)
                        ->helperText('Off = hide this item across all customer menus regardless of per-location state.')
                        ->dehydrateStateUsing(fn ($state) => $state ? null : now())
                        ->formatStateUsing(fn ($state) => is_null($state)),
                ]),

            Section::make('Locations')
                ->description('Attach this item to the locations that should offer it. Per-location price overrides and pivot-level availability are managed via a dedicated flow — item here is either offered or not at each location.')
                ->schema([
                    CheckboxList::make('locations')
                        ->relationship('locations', 'name')
                        ->columns(2)
                        ->required()
                        ->minItems(1)
                        ->helperText('Customers will see this item on the public menu, but it will be dimmed at checkout if their booking is at a location where it isn\'t stocked. At least one location must be selected.'),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')->square(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('locations.name')
                    ->label('Locations')
                    ->badge()
                    ->separator(','),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (MenuCategory $state): string => ucfirst($state->value)),
                TextColumn::make('price')
                    ->formatStateUsing(fn ($state) => self::centsToDisplay((int) $state))
                    ->sortable(),
                IconColumn::make('available')
                    ->boolean()
                    ->label('Active'),
                TextColumn::make('allergens')
                    ->badge()
                    ->separator(','),
                ...self::standardTimestamps(),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options(collect(MenuCategory::cases())->mapWithKeys(
                        fn (MenuCategory $c) => [$c->value => ucfirst($c->value)],
                    )->all()),
                SelectFilter::make('location')
                    ->relationship('locations', 'name'),
                TernaryFilter::make('available')
                    ->label('Globally available')
                    ->placeholder('Any')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('menu_items.unavailable_at'),
                        false: fn (Builder $q) => $q->whereNotNull('menu_items.unavailable_at'),
                        blank: fn (Builder $q) => $q,
                    ),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('feature_on_home')
                    ->label('Feature on home')
                    ->icon('heroicon-o-home-modern')
                    ->visible(fn ($record) => $record->featured_on_home_at === null
                        && (auth('admin')->user()?->can('menu.update') ?? false))
                    ->action(function ($record) {
                        // Several items may be flagged at once — the home food
                        // teaser prefers flagged items and tops up to three.
                        $record->update(['featured_on_home_at' => now()]);

                        Notification::make()->title('Featured on home')->success()->send();
                    }),
                Action::make('unfeature_from_home')
                    ->label('Remove from home')
                    ->icon('heroicon-o-home-modern')
                    ->visible(fn ($record) => $record->featured_on_home_at !== null
                        && (auth('admin')->user()?->can('menu.update') ?? false))
                    ->action(function ($record) {
                        $record->update(['featured_on_home_at' => null]);

                        Notification::make()->title('Removed from home')->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMenuItems::route('/'),
            'create' => Pages\CreateMenuItem::route('/create'),
            'edit' => Pages\EditMenuItem::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // Eager-load `locations` so the `locations.name` table column
        // doesn't trigger lazy loads per row in the admin list.
        return parent::getEloquentQuery()->with('locations');
    }
}
