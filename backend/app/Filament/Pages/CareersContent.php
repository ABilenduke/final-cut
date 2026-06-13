<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SiteSettingsService;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Editorial form for the careers-page "why work here" benefits (admin-v6 G5).
 * One keyed blob through SiteSettingsService; the public
 * /api/site-content/careers endpoint serves it and the frontend falls back to
 * its built-in list until the first save. The defaults below mirror
 * frontend/app/pages/careers.vue so the first edit starts from what visitors
 * currently see. Job openings themselves stay on JobOpeningResource.
 *
 * @property Schema $form
 */
class CareersContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-briefcase';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Careers content';

    protected static ?string $title = 'Careers content';

    // Distinct within Content (JobOpeningResource is 40; this sits just after).
    protected static ?int $navigationSort = 41;

    protected string $view = 'filament.pages.careers-content';

    /** @var list<string> */
    public const BENEFIT_DEFAULTS = [
        'Free movie tickets for you and a guest',
        'Discounted food and beverages',
        'Flexible scheduling',
        'Loyalty program Premier membership',
        'Career development and training',
        'A team that genuinely loves film',
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('content.careers.update') ?? false;
    }

    public function mount(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $saved = $settings->get(SiteSettingsService::KEY_CAREERS_BENEFITS);
        $benefits = is_array($saved) && is_array($saved['benefits'] ?? null)
            ? array_values($saved['benefits'])
            : self::BENEFIT_DEFAULTS;

        $this->form->fill(['benefits' => $benefits]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Why work here')
                    ->description('The benefits list on the public careers page. Reorder, add, or remove rows.')
                    ->schema([
                        Repeater::make('benefits')
                            ->label('Benefits')
                            ->simple(
                                TextInput::make('benefit')
                                    ->maxLength(120),
                            )
                            // Blank rows are dropped on save rather than blocked,
                            // so an admin can clear a row mid-edit without a wall
                            // of validation errors.
                            ->reorderable()
                            ->addActionLabel('Add benefit'),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array{benefits?: array<int, string>} $state */
        $state = $this->form->getState();

        $benefits = array_values(array_filter(
            array_map('trim', $state['benefits'] ?? []),
            static fn (string $b): bool => $b !== '',
        ));

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_CAREERS_BENEFITS, ['benefits' => $benefits], $actor);

        Notification::make()
            ->title('Careers content saved')
            ->body('The careers page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
