<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SiteSettingsService;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Editorial form for site-wide contact details (admin-v3 Plan 02): the
 * footer line and the support email addresses scattered across the public
 * pages. One keyed blob through SiteSettingsService; the public
 * /api/site-content/contacts endpoint serves it and the frontend falls back
 * to its built-in values until the first save. The defaults below mirror
 * frontend/app/data/siteContacts.ts so the first edit starts from what
 * visitors currently see.
 *
 * @property Schema $form
 */
class SiteContacts extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-at-symbol';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Site contacts';

    protected static ?string $title = 'Site contacts';

    protected string $view = 'filament.pages.site-contacts';

    /** @var array<string, string> */
    public const CONTACT_DEFAULTS = [
        'footerVenueName' => 'Final Cut Theatre',
        'footerAddress' => '123 Cinema Boulevard',
        'footerPhone' => '(555) 123-4567',
        'generalEmail' => 'hello@finalcut.test',
        'privacyEmail' => 'privacy@finalcut.test',
        'careersEmail' => 'careers@finalcut.test',
        'accessibilityEmail' => 'accessibility@finalcut.test',
        'accessibilityPhone' => '212-555-0199',
        'conciergeEmail' => 'concierge@finalcut.test',
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

        $saved = $settings->get(SiteSettingsService::KEY_SITE_CONTACTS);

        $this->form->fill(array_merge(self::CONTACT_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Footer')
                    ->description('Rendered on every page as "Name · Address · Phone".')
                    ->schema([
                        TextInput::make('footerVenueName')->label('Venue name')->required()->maxLength(80),
                        TextInput::make('footerAddress')->label('Address line')->required()->maxLength(120),
                        TextInput::make('footerPhone')->label('Phone')->required()->maxLength(40),
                    ]),

                Section::make('Support inboxes')
                    ->description('Each address appears on its matching public page.')
                    ->schema([
                        TextInput::make('generalEmail')->label('General (terms page)')->email()->required()->maxLength(120),
                        TextInput::make('privacyEmail')->label('Privacy page')->email()->required()->maxLength(120),
                        TextInput::make('careersEmail')->label('Careers page')->email()->required()->maxLength(120),
                        TextInput::make('conciergeEmail')->label('Gift card concierge')->email()->required()->maxLength(120),
                    ])
                    ->columns(2),

                Section::make('Accessibility services')
                    ->schema([
                        TextInput::make('accessibilityEmail')->label('Email')->email()->required()->maxLength(120),
                        TextInput::make('accessibilityPhone')->label('Phone')->required()->maxLength(40),
                    ])
                    ->columns(2),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_SITE_CONTACTS, $state, $actor);

        Notification::make()
            ->title('Site contacts saved')
            ->body('Public pages pick the change up within five minutes.')
            ->success()
            ->send();
    }
}
