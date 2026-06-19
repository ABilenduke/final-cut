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
 * Editorial form for the header + footer primary navigation (admin-v6 G1).
 * One keyed blob through SiteSettingsService; /api/site-content/navigation
 * serves it and the frontend falls back to its built-in nav whenever a list
 * is null/empty — so the layout shell can never render an empty nav. Each
 * href must be a site-relative path (`/movies`) or an absolute http(s) URL;
 * the scheme guard rejects `javascript:`/`data:` at the form layer (the
 * frontend re-checks as defence-in-depth). The defaults mirror
 * SiteHeader.vue / SiteFooter.vue.
 *
 * @property Schema $form
 */
class NavigationContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Navigation';

    protected static ?string $title = 'Navigation';

    protected string $view = 'filament.pages.navigation-content';

    /** Site-relative path or absolute http(s) URL — never javascript:/data:. */
    public const HREF_PATTERN = '/^(\/|https?:\/\/)/';

    /** @var list<array{label: string, href: string}> */
    public const HEADER_DEFAULTS = [
        ['label' => 'Movies', 'href' => '/movies'],
        ['label' => "What's On", 'href' => '/whats-on'],
        ['label' => 'Food & Drink', 'href' => '/food-drink'],
        ['label' => 'Events', 'href' => '/events'],
        ['label' => 'Gift Cards', 'href' => '/gift-cards'],
    ];

    /** @var list<array{label: string, href: string}> */
    public const FOOTER_DEFAULTS = [
        ['label' => 'Our Cinemas', 'href' => '/locations'],
        ['label' => 'Contact', 'href' => '/contact'],
        ['label' => 'FAQ', 'href' => '/faq'],
        ['label' => 'Accessibility', 'href' => '/accessibility'],
        ['label' => 'Careers', 'href' => '/careers'],
        ['label' => 'Private Screenings', 'href' => '/private-screenings'],
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

        $saved = $settings->get(SiteSettingsService::KEY_NAVIGATION);
        $saved = is_array($saved) ? $saved : [];

        $this->form->fill([
            'header' => self::normalizeForForm($saved['header'] ?? null, self::HEADER_DEFAULTS),
            'footer' => self::normalizeForForm($saved['footer'] ?? null, self::FOOTER_DEFAULTS),
        ]);
    }

    /**
     * @param  mixed  $saved
     * @param  list<array{label: string, href: string}>  $defaults
     * @return list<array{label: string, href: string}>
     */
    private static function normalizeForForm($saved, array $defaults): array
    {
        if (! is_array($saved) || $saved === []) {
            return $defaults;
        }

        $items = [];
        foreach ($saved as $item) {
            if (is_array($item) && isset($item['label'], $item['href'])) {
                $items[] = ['label' => (string) $item['label'], 'href' => (string) $item['href']];
            }
        }

        return $items === [] ? $defaults : $items;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Header navigation')
                    ->description('The primary nav in the site header (desktop + mobile menu).')
                    ->schema([self::navRepeater('header')]),

                Section::make('Footer navigation')
                    ->description('The secondary nav in the site footer.')
                    ->schema([self::navRepeater('footer')]),
            ]);
    }

    private static function navRepeater(string $name): Repeater
    {
        return Repeater::make($name)
            ->label('Links')
            ->schema([
                TextInput::make('label')->label('Label')->required()->maxLength(40),
                TextInput::make('href')
                    ->label('Link')
                    ->required()
                    ->maxLength(200)
                    ->rules(['regex:'.self::HREF_PATTERN])
                    ->validationMessages([
                        'regex' => 'Use a site path like /movies or a full https:// URL.',
                    ])
                    ->helperText('Site path (/movies) or an https:// URL.'),
            ])
            ->reorderable()
            ->columns(2)
            ->addActionLabel('Add link');
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array{header?: array<int, array{label?: string, href?: string}>, footer?: array<int, array{label?: string, href?: string}>} $state */
        $state = $this->form->getState();

        $clean = [
            'header' => self::cleanItems($state['header'] ?? []),
            'footer' => self::cleanItems($state['footer'] ?? []),
        ];

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_NAVIGATION, $clean, $actor);

        Notification::make()
            ->title('Navigation saved')
            ->body('The header and footer pick the change up within five minutes.')
            ->success()
            ->send();
    }

    /**
     * @param  array<int, array{label?: string, href?: string}>  $items
     * @return list<array{label: string, href: string}>
     */
    private static function cleanItems(array $items): array
    {
        $clean = [];
        foreach ($items as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            $href = trim((string) ($item['href'] ?? ''));
            if ($label !== '' && preg_match(self::HREF_PATTERN, $href) === 1) {
                $clean[] = ['label' => $label, 'href' => $href];
            }
        }

        return $clean;
    }
}
