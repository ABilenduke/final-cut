# Custom pages — Page class + Blade view

Custom Pages live under `backend/app/Filament/Pages/` with their Blade views at `backend/resources/views/filament/pages/`. Four exist today — they're the precedents.

| Page | Class | Blade view | Purpose |
| ---- | ----- | ---------- | ------- |
| `BookingLookup` | `App\Filament\Pages\BookingLookup` | `filament.pages.booking-lookup` | Confirmation-code / email lookup (read-only redirect) |
| `SchedulePlanner` | `App\Filament\Pages\SchedulePlanner` | `filament.pages.schedule-planner` | Showtime scheduling helper |
| `ActivityLog` | `App\Filament\Pages\ActivityLog` | `filament.pages.activity-log` | Audit-trail browser |
| `CancellationFollowupQueue` | `App\Filament\Pages\CancellationFollowupQueue` | `filament.pages.cancellation-followup-queue` | Queue of bookings awaiting refund follow-up |

## Page class shape

The minimum useful Page:

```php
namespace App\Filament\Pages;

use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use UnitEnum;

class MyPage extends Page
{
    protected string $view = 'filament.pages.my-page';
    protected static ?string $slug = 'my-page';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-funnel';
    protected static string|UnitEnum|null $navigationGroup = 'Operations';
    protected static ?int $navigationSort = 1;

    public ?string $someInput = null;

    public function getTitle(): string
    {
        return 'My page';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->can('some.permission') ?? false;
    }

    public function someAction(): void
    {
        $this->validate(['someInput' => ['required', 'string', 'min:3']]);
        // ... business logic via App\Services\... (never inline)
        Notification::make()->title('Done')->success()->send();
    }
}
```

### Properties to set (most to least common)

- `$view` — required.
- `$slug` — sentence-case-collapsed kebab. The URL path under the panel.
- `$navigationIcon` — line-style Heroicon (`heroicon-o-*`).
- `$navigationGroup` — group label. Existing groups: `Operations`, `Content`. Add a new one only when you have ≥ 2 pages for it.
- `$navigationSort` — integer, lower = higher in the sidebar.
- `getTitle()` — sentence case, no trailing punctuation. Don't include the brand ("Final Cut Booking Lookup" — no, just "Booking lookup").
- `canAccess()` — gate every page on a Spatie permission. The default `auth('admin')->user()?->can('foo.bar') ?? false` shape is in use across this codebase and is the right pattern.

### Action methods

Live on the Page class as public methods, called from the Blade view via Livewire (`wire:click="someAction"`). Keep the Page class *thin* — push business logic into `App\Services\...`. Reasons:

1. The Page class is hard to test in isolation; a Service with constructor-injected dependencies is straightforward.
2. Multiple pages may need the same operation (BookingLookup and CancellationFollowupQueue both touch booking lookups).
3. Validation lives close to the input; service methods can throw domain exceptions that the page translates into Notifications.

Pattern, modeled on the real `BookingLookup::search()` (which keeps its query inline because it's the only consumer):

```php
public function lookup(): void
{
    $this->validate(['query' => ['required', 'string', 'min:3']]);
    $needle = trim($this->query ?? '');

    $booking = Booking::query()
        ->where(function (Builder $q) use ($needle) {
            // …confirmation-code, guest-email, user-email lookup…
        })
        ->latest('created_at')
        ->first();

    if (! $booking) {
        Notification::make()
            ->title('No booking found')
            ->body('Double-check the code or email and try again.')
            ->warning()
            ->send();
        return;
    }
    $this->redirect(BookingResource::getUrl('view', ['record' => $booking]));
}
```

When a query like this is reused by a second consumer (a Widget, a console command), extract it to a `Service` at that point — not before. Existing services in `backend/app/Services/` (e.g., `LoyaltyService`, `GiftCardService`, `SeatAvailabilityService`) are good models for shape.

## Blade view shape

The view file lives at `backend/resources/views/filament/pages/{slug-as-kebab}.blade.php`. It receives the Page instance as `$this`.

### The container component

Always start with `<x-filament-panels::page>`. It gives you the standard page chrome — title, breadcrumbs, padding, surface tier — that matches every other admin page. Don't reinvent the page shell.

```blade
<x-filament-panels::page>
    <x-filament::section>
        {{-- form-like content --}}
        <form wire:submit="lookup" class="fc-stack">
            <x-filament::input.wrapper>
                <x-filament::input
                    type="text"
                    wire:model="query"
                    placeholder="CVF-A3X9K2 or guest@example.com"
                />
            </x-filament::input.wrapper>
            <x-filament::button type="submit">Look up booking</x-filament::button>
        </form>
    </x-filament::section>
</x-filament-panels::page>
```

### Reaching for a Filament component first

For any UI primitive — buttons, inputs, sections, cards, dropdowns — check Filament's blade components before writing your own markup:

| Element | Filament component |
| ------- | ------------------ |
| Section | `<x-filament::section>` |
| Input | `<x-filament::input.wrapper>` + `<x-filament::input>` |
| Button | `<x-filament::button>` |
| Link | `<x-filament::link>` |
| Badge | `<x-filament::badge>` |
| Icon | `<x-filament::icon icon="heroicon-o-..." />` |
| Empty state | `<x-filament::empty-state>` |

The bundled `theme.css` already brand-correctly themes all of these. Custom markup forces you to re-discover the brand by hand.

### Brand-owned classes for layout-only utilities

When you need a layout helper that Filament doesn't ship (a vertical stack, a grid, a centered narrow column), define a brand-owned class in `theme.css` (`.fc-stack`, `.fc-grid-2`, `.fc-narrow`). Don't reach for Tailwind utilities baked into class names — they bypass the design system per `references/token-mapping.md` § Pitfall 3.

```blade
{{-- **Do:** brand-owned --}}
<div class="fc-stack">…</div>

{{-- **Avoid:** inline Tailwind --}}
<div class="flex flex-col gap-4">…</div>
```

If you find yourself repeatedly using a particular utility, add it to `theme.css` as a brand-named class.

### Empty / loading / error states

Three shapes worth standardizing:

```blade
{{-- Empty: nothing to show, not an error --}}
<x-filament::empty-state
    icon="heroicon-o-magnifying-glass"
    heading="No bookings flagged for follow-up"
    description="Cancellations awaiting refund will appear here."
/>

{{-- Loading: action in flight --}}
<div wire:loading wire:target="lookup" class="fc-loading">
    Searching…
</div>

{{-- Error: problem the user can act on --}}
@error('query')
    <p class="fc-error">{{ $message }}</p>
@enderror
```

Add `.fc-loading` and `.fc-error` classes to `theme.css` if not already present (`fc-error` should map to `--fc-primary` text on no fill).

## Navigation grouping

Filament's sidebar groups pages by `$navigationGroup`. The current grouping in this codebase:

- **Operations** — daily ops surfaces: BookingLookup, SchedulePlanner, CancellationFollowupQueue, ActivityLog.
- **Content** — content management: CalendarEventResource, MovieResource (when added).
- **(default group)** — Resources without an explicit group (most of the existing 10).

Don't introduce a new group for a single page. If a fifth Operations page lands, keep it there. If "Reports" eventually becomes 3+ pages, add it then.

## Page-level access

`canAccess()` returns `false` for unauthorized users — Filament hides the sidebar entry **and** returns 403 on direct URL access. This is the right pattern; it matches the layered security model documented in `CLAUDE.md` § Admin Panel.

The Spatie permission name should follow the existing convention: lowercase, dot-separated, scoped to a domain. Examples already in use: `bookings.view`, `loyalty.adjust_points`, `events.create`.

## Anti-patterns

- **Avoid:** Page class fat with business logic. Push to a Service.
- **Avoid:** Blade view that doesn't start with `<x-filament-panels::page>`. You'll lose the breadcrumbs and the brand.
- **Avoid:** `Notification::make()->success()->title('Successfully did the thing!')`. Title Case + exclamation + redundant "Successfully" — see `content-fundamentals.md`.
- **Avoid:** A new `$navigationGroup` for a single page. Group label noise.
