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
 * Editorial form for the gift-cards page masthead (admin-v6 G8): the eyebrow
 * kicker and the lede paragraph. The stylized <h1> title stays structural (it
 * carries brand typography), and the composer/balance widgets below are their
 * own flows — this is just the masthead copy. One keyed blob through
 * SiteSettingsService; the public /api/site-content/gift-cards endpoint serves
 * it and the frontend falls back to its built-in copy until the first save.
 * The defaults below mirror frontend/app/pages/gift-cards.vue.
 *
 * @property Schema $form
 */
class GiftCardsContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Gift cards content';

    protected static ?string $title = 'Gift cards content';

    protected string $view = 'filament.pages.gift-cards-content';

    /** @var array<string, string> */
    public const GIFT_CARDS_DEFAULTS = [
        'eyebrow' => 'Vol. XXIII · Reel Society Gift Programme',
        'lede' => 'A cinema gift card is a quiet, deliberate thing: redeemable on any film, any seat, any provision from the bar. Delivered by email or printed on heavy stock and posted in a black sleeve.',
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

        $saved = $settings->get(SiteSettingsService::KEY_GIFT_CARDS_EDITORIAL);

        $this->form->fill(array_merge(self::GIFT_CARDS_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Masthead')
                    ->description('The eyebrow kicker and lead paragraph at the top of the gift-cards page. The headline itself stays fixed.')
                    ->schema([
                        TextInput::make('eyebrow')->label('Eyebrow')->required()->maxLength(80),
                        Textarea::make('lede')->label('Lead paragraph')->required()->rows(3)->maxLength(600),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, string> $state */
        $state = $this->form->getState();

        $clean = [
            'eyebrow' => trim($state['eyebrow'] ?? ''),
            'lede' => trim($state['lede'] ?? ''),
        ];

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_GIFT_CARDS_EDITORIAL, $clean, $actor);

        Notification::make()
            ->title('Gift cards content saved')
            ->body('The page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
