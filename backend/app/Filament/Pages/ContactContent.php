<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SiteSettingsService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Editorial form for the contact-page "getting here" prose (admin-v6 G6):
 * By Car, By Transit, and the Accessibility note. One keyed blob through
 * SiteSettingsService; the public /api/site-content/contact-info endpoint
 * serves it and the frontend falls back to its built-in copy until the first
 * save. The defaults below mirror frontend/app/pages/contact.vue so the first
 * edit starts from what visitors currently see.
 *
 * Brand-level by design: the contact page is brand-led and points to
 * /locations/:slug for per-venue detail, so this is one blob — not per-Location
 * columns (a divergence from the gap audit's suggestion, recorded in the
 * admin-v6 journal).
 *
 * @property Schema $form
 */
class ContactContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Contact content';

    protected static ?string $title = 'Contact content';

    protected string $view = 'filament.pages.contact-content';

    /** @var array<string, string> */
    public const CONTACT_INFO_DEFAULTS = [
        'byCar' => 'Parking garage located directly beneath the theater. Enter via Cinema Lane. First 3 hours validated with ticket purchase.',
        'byTransit' => 'Nearest subway: Cinema Station (A/C/E lines), one block east. Bus routes 14, 23, and 42 stop at Cinema Boulevard.',
        'accessibility' => 'Step-free access at all entrances. Accessible parking spaces available on Level 1 of the garage, nearest to the elevator. Wheelchair-accessible seating available in every auditorium.',
    ];

    /** @var array<string, mixed> */
    public array $data = [];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('content.site_settings.update') ?? false;
    }

    public function mount(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        $saved = $settings->get(SiteSettingsService::KEY_CONTACT_INFO);

        $this->form->fill(array_merge(self::CONTACT_INFO_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Getting here')
                    ->description('The "Getting Here" and "Accessibility" prose on the public contact page.')
                    ->schema([
                        Textarea::make('byCar')->label('By car')->required()->rows(3)->maxLength(600),
                        Textarea::make('byTransit')->label('By transit')->required()->rows(3)->maxLength(600),
                        Textarea::make('accessibility')->label('Accessibility note')->required()->rows(3)->maxLength(600),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, string> $state */
        $state = $this->form->getState();

        $clean = [
            'byCar' => trim($state['byCar'] ?? ''),
            'byTransit' => trim($state['byTransit'] ?? ''),
            'accessibility' => trim($state['accessibility'] ?? ''),
        ];

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_CONTACT_INFO, $clean, $actor);

        Notification::make()
            ->title('Contact content saved')
            ->body('The contact page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
