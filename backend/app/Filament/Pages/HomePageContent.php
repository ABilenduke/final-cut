<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Services\SiteSettingsService;
use BackedEnum;
use Filament\Forms\Components\Repeater;
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
 * Editorial form for the home page membership pitch (admin-v2 Plan 15).
 * Writes one keyed blob through SiteSettingsService; the public
 * /api/site-content/home endpoint serves it and the frontend falls back to
 * its built-in copy until the first save. The form prefills those same
 * built-in defaults so the first edit starts from what visitors see today.
 *
 * @property Schema $form
 */
class HomePageContent extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home-modern';

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Home page';

    protected static ?string $title = 'Home page content';

    protected string $view = 'filament.pages.home-page-content';

    /**
     * Mirror of the frontend's built-in membership copy
     * (frontend/app/data/homepage.ts `membershipContent`). Keys are the
     * frontend's camelCase wire contract — the blob passes through the API
     * untransformed.
     *
     * @var array<string, mixed>
     */
    public const MEMBERSHIP_DEFAULTS = [
        'eyebrow' => 'Membership',
        'title' => 'Join the',
        'titleEmphasis' => 'Reel Society.',
        'copy' => 'A monthly membership for people who want to be in a room with a beam of light and a story. Unlimited screenings, early booking, and a seat at our director conversations.',
        'priceLabel' => 'Become a Member · $24/mo',
        'ctaLabel' => 'View all tiers',
        'cardTier' => 'Charter Member',
        'cardNumber' => 'No. 0047',
        'cardValidThrough' => 'Valid through 12 · 2027',
        'cardSociety' => 'Reel Society',
        'cardTitle' => 'Final',
        'cardTitleEmphasis' => 'Cut.',
        'perks' => [
            ['title' => 'Unlimited screenings', 'detail' => 'Every film, every screen, every night.'],
            ['title' => 'Early booking', 'detail' => 'Seats unlocked 48h before general release.'],
            ['title' => 'Reserved row', 'detail' => 'Center, Row F — held for members at all screenings.'],
            ['title' => 'Director evenings', 'detail' => 'Post-screening conversations, four per year.'],
        ],
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

        $saved = $settings->get(SiteSettingsService::KEY_HOME_MEMBERSHIP);

        $this->form->fill(array_merge(self::MEMBERSHIP_DEFAULTS, is_array($saved) ? $saved : []));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Membership pitch')
                    ->description('The copy block on the home page membership section.')
                    ->schema([
                        TextInput::make('eyebrow')->required()->maxLength(60),
                        TextInput::make('title')->label('Heading')->required()->maxLength(120),
                        TextInput::make('titleEmphasis')->label('Heading emphasis')
                            ->helperText('Rendered italicised after the heading.')
                            ->required()->maxLength(120),
                        Textarea::make('copy')->label('Pitch copy')->required()->rows(4)->maxLength(600),
                        TextInput::make('priceLabel')->label('CTA button label')->required()->maxLength(80),
                        TextInput::make('ctaLabel')->label('Secondary link label')->required()->maxLength(80),
                    ]),

                Section::make('Perks')
                    ->schema([
                        Repeater::make('perks')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')->required()->maxLength(80),
                                TextInput::make('detail')->required()->maxLength(160),
                            ])
                            ->reorderable()
                            ->minItems(1)
                            ->maxItems(6)
                            ->required(),
                    ]),

                Section::make('Membership card art')
                    ->description('Text printed on the decorative card visual.')
                    ->collapsed()
                    ->schema([
                        TextInput::make('cardTier')->label('Tier line')->required()->maxLength(60),
                        TextInput::make('cardNumber')->label('Number line')->required()->maxLength(60),
                        TextInput::make('cardTitle')->label('Card title')->required()->maxLength(60),
                        TextInput::make('cardTitleEmphasis')->label('Card title emphasis')->required()->maxLength(60),
                        TextInput::make('cardValidThrough')->label('Valid-through line')->required()->maxLength(60),
                        TextInput::make('cardSociety')->label('Society line')->required()->maxLength(60),
                    ]),
            ]);
    }

    public function save(SiteSettingsService $settings): void
    {
        abort_unless(static::canAccess(), 403);

        /** @var array<string, mixed> $state */
        $state = $this->form->getState();

        /** @var User $actor */
        $actor = auth('admin')->user();

        $settings->set(SiteSettingsService::KEY_HOME_MEMBERSHIP, $state, $actor);

        Notification::make()
            ->title('Home page content saved')
            ->body('The public page picks the change up within five minutes.')
            ->success()
            ->send();
    }
}
