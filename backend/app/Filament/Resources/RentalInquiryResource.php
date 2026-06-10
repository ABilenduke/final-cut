<?php

namespace App\Filament\Resources;

use App\Enums\InquiryStatus;
use App\Exceptions\InquiryTransitionException;
use App\Filament\Resources\RentalInquiryResource\Pages;
use App\Models\RentalInquiry;
use App\Services\RentalInquiryService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/**
 * Read-only inbox for private-screening rental inquiries (admin-v2 Plan 10).
 * The only mutation is the status transition, driven by
 * RentalInquiryService's transition map so the UI never offers an illegal move.
 */
class RentalInquiryResource extends BaseResource
{
    protected static ?string $model = RentalInquiry::class;

    protected static ?string $permissionPrefix = 'rentals';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope-open';

    protected static string|UnitEnum|null $navigationGroup = 'Operations';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Rental inquiries';

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
        if (! (auth('admin')->user()?->can('rentals.view') ?? false)) {
            return null;
        }

        $pending = RentalInquiry::where('status', InquiryStatus::Pending)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('email')->searchable()->copyable(),
                TextColumn::make('event_type')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => str_replace('_', ' ', $state->value)),
                TextColumn::make('preferred_date')->date()->sortable(),
                TextColumn::make('guest_count')->label('Guests'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (InquiryStatus $state): string => match ($state) {
                        InquiryStatus::Pending => 'warning',
                        InquiryStatus::Contacted => 'info',
                        InquiryStatus::Confirmed => 'success',
                        InquiryStatus::Declined => 'gray',
                    }),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(InquiryStatus::cases())
                        ->mapWithKeys(fn (InquiryStatus $s) => [$s->value => ucfirst($s->value)])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('set_status')
                    ->label('Update status')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->visible(fn (RentalInquiry $record): bool => (auth('admin')->user()?->can('rentals.update_status') ?? false)
                        && RentalInquiryService::allowedTransitions($record->status) !== [])
                    ->schema([
                        Select::make('status')
                            ->label('New status')
                            ->options(fn (RentalInquiry $record) => collect(RentalInquiryService::allowedTransitions($record->status))
                                ->mapWithKeys(fn (InquiryStatus $s) => [$s->value => ucfirst($s->value)])
                                ->all())
                            ->required(),
                    ])
                    ->action(function (RentalInquiry $record, array $data): void {
                        try {
                            app(RentalInquiryService::class)->transition(
                                $record,
                                InquiryStatus::from($data['status']),
                                auth('admin')->user(),
                            );
                        } catch (InquiryTransitionException $e) {
                            Notification::make()->danger()->title('Cannot update status')->body($e->getMessage())->send();

                            return;
                        }

                        Notification::make()->success()->title('Status updated')->send();
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Inquiry')
                ->schema([
                    Placeholder::make('name')
                        ->content(fn (RentalInquiry $record): string => $record->name),
                    Placeholder::make('email')
                        ->content(fn (RentalInquiry $record): string => $record->email),
                    Placeholder::make('phone')
                        ->content(fn (RentalInquiry $record): string => $record->phone ?? '—'),
                    Placeholder::make('event_type')
                        ->content(fn (RentalInquiry $record): string => str_replace('_', ' ', $record->event_type->value)),
                    Placeholder::make('preferred_date')
                        ->content(fn (RentalInquiry $record): string => $record->preferred_date->toFormattedDateString()),
                    Placeholder::make('guest_count')
                        ->content(fn (RentalInquiry $record): string => (string) $record->guest_count),
                ])
                ->columns(2),

            Section::make('Message')
                ->schema([
                    Placeholder::make('message')
                        ->label('')
                        ->content(fn (RentalInquiry $record): string => $record->message ?? '—'),
                ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRentalInquiries::route('/'),
            'view' => Pages\ViewRentalInquiry::route('/{record}'),
        ];
    }
}
