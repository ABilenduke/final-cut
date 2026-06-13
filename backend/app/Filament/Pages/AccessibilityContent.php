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
 * Editorial form for the accessibility-page prose (admin-v6 G4): the intro and
 * the six section paragraphs. The section headings, the calendar links, and
 * the contact block stay structural (the contact email/phone are already
 * served from SiteContacts) — only the descriptive copy is editable here. One
 * keyed blob through SiteSettingsService; /api/site-content/accessibility
 * serves it and the frontend falls back to its built-in copy until the first
 * save. The defaults below mirror frontend/app/pages/accessibility.vue.
 *
 * @property Schema $form
 */
class AccessibilityContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Accessibility content';

    protected static ?string $title = 'Accessibility content';

    protected string $view = 'filament.pages.accessibility-content';

    /** @var array<string, string> */
    public const STATEMENT_DEFAULTS = [
        'intro' => 'Final Cut is committed to providing an inclusive experience for every guest. Our facilities, services, and programming are designed so that everyone can enjoy the magic of cinema.',
        'assistedListening' => 'Assistive listening devices are available at no charge from the guest services desk in each lobby. We offer both headset and neck-loop receivers compatible with hearing aids set to T-coil mode. A valid ID is required as a deposit and returned when the device is brought back.',
        'wheelchairSeating' => 'Every auditorium has designated wheelchair-accessible seating locations with companion seats. These seats are integrated into the main seating area — not separated or placed at the back. Accessible seats can be selected during the normal ticket purchase flow and are clearly marked in the seat map.',
        'openCaption' => 'We schedule open caption screenings for most films throughout the week. Captions are displayed directly on the screen so no special equipment is needed. Check our calendar for upcoming open caption showtimes.',
        'audioDescription' => 'Audio description narrates visual elements of the film — actions, facial expressions, scene changes — through a personal headset. Audio description devices are available at guest services for any screening where an audio description track is available.',
        'sensoryFriendly' => 'Our sensory-friendly screenings offer a modified environment: house lights are kept slightly up, sound levels are reduced, and there are no previews or pre-show advertisements. Guests are welcome to move around and make noise. These screenings are open to everyone and are especially popular with families.',
        'serviceAnimals' => 'Service animals are welcome in all areas of the theater. We ask that service animals remain on the floor beside their handler during screenings. Fresh water is available upon request from any staff member.',
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

        $saved = $settings->get(SiteSettingsService::KEY_ACCESSIBILITY_STATEMENT);

        $this->form->fill(array_merge(self::STATEMENT_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Statement')
                    ->description('The intro and the descriptive paragraph under each accessibility section. Headings and the "view showtimes" links are fixed.')
                    ->schema([
                        Textarea::make('intro')->label('Intro')->required()->rows(3)->maxLength(800),
                        Textarea::make('assistedListening')->label('Assisted listening devices')->required()->rows(3)->maxLength(800),
                        Textarea::make('wheelchairSeating')->label('Wheelchair seating')->required()->rows(3)->maxLength(800),
                        Textarea::make('openCaption')->label('Open caption showtimes')->required()->rows(3)->maxLength(800),
                        Textarea::make('audioDescription')->label('Audio description')->required()->rows(3)->maxLength(800),
                        Textarea::make('sensoryFriendly')->label('Sensory-friendly screenings')->required()->rows(3)->maxLength(800),
                        Textarea::make('serviceAnimals')->label('Service animals')->required()->rows(3)->maxLength(800),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, string> $state */
        $state = $this->form->getState();

        $clean = [];
        foreach (array_keys(self::STATEMENT_DEFAULTS) as $field) {
            $clean[$field] = trim($state[$field] ?? '');
        }

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_ACCESSIBILITY_STATEMENT, $clean, $actor);

        Notification::make()
            ->title('Accessibility content saved')
            ->body('The accessibility page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
