<?php

namespace App\Filament\Pages;

use App\Models\User;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

class ActivityLog extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected string $view = 'filament.pages.activity-log';

    protected static ?int $navigationSort = 99;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $slug = 'activity';

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('activity.view') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Activity::query()->with('causer')->latest())
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('causer.email')->label('Admin'),
                TextColumn::make('description')->label('Action'),
                TextColumn::make('subject_type')
                    ->label('Resource')
                    ->formatStateUsing(fn ($state) => $state ? class_basename($state) : '—'),
                TextColumn::make('subject_id')->label('ID'),
            ])
            ->filters([
                SelectFilter::make('causer_id')
                    ->label('Admin')
                    // Only Users with an AdminProfile appear in the picker, so
                    // the resulting filter is admin-only without an extra scope.
                    ->options(fn () => User::query()
                        ->whereHas('adminProfile')
                        ->pluck('email', 'id'))
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['value'] ?? null, fn (Builder $q, $adminId) => $q
                            ->where('causer_id', $adminId)
                            ->where('causer_type', User::class))),
                SelectFilter::make('subject_type')
                    ->label('Resource')
                    ->options(fn () => Activity::query()
                        ->whereNotNull('subject_type')
                        ->distinct()
                        ->pluck('subject_type', 'subject_type')
                        ->mapWithKeys(fn ($v, $k) => [$k => class_basename($v)])
                        ->all()),
                Filter::make('created_at')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '>=', Carbon::parse($date)->startOfDay()))
                        ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->where('created_at', '<=', Carbon::parse($date)->endOfDay()))),
            ]);
    }
}
