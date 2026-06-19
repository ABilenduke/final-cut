<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SiteSettingsService;
use BackedEnum;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * Editorial form for the private-screenings page header (admin-v6 G3): the
 * title and the lead paragraph. The packages below it are admin-managed via
 * ScreeningPackageResource; this is just the page intro. One keyed blob
 * through SiteSettingsService; the public /api/site-content/private-screenings
 * endpoint serves it and the frontend falls back to its built-in copy until
 * the first save. The defaults below mirror
 * frontend/app/pages/private-screenings.vue.
 *
 * @property Schema $form
 */
class PrivateScreeningsContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-film';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Private screenings content';

    protected static ?string $title = 'Private screenings content';

    protected string $view = 'filament.pages.private-screenings-content';

    /** @var array<string, string> */
    public const SCREENINGS_DEFAULTS = [
        'title' => 'Private Screenings & Events',
        'intro' => 'From birthdays to boardrooms, Final Cut is the perfect venue for your next event. Choose a package below or tell us what you have in mind.',
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

        $saved = $settings->get(SiteSettingsService::KEY_PRIVATE_SCREENINGS);

        $this->form->fill(array_merge(self::SCREENINGS_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Page header')
                    ->description('The title and lead paragraph above the private-screening packages.')
                    ->schema([
                        TextInput::make('title')->label('Title')->required()->maxLength(120),
                        Textarea::make('intro')->label('Lead paragraph')->required()->rows(3)->maxLength(600),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, string> $state */
        $state = $this->form->getState();

        $clean = [
            'title' => trim($state['title'] ?? ''),
            'intro' => trim($state['intro'] ?? ''),
        ];

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_PRIVATE_SCREENINGS, $clean, $actor);

        Notification::make()
            ->title('Private screenings content saved')
            ->body('The page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
