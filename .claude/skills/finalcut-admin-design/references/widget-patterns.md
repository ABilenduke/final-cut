# Widgets — establishing the convention

`backend/app/Filament/Widgets/` is empty today. The dashboard renders Filament's stock `AccountWidget` and `FilamentInfoWidget`. The first real widget will set the pattern; this file makes sure that pattern is brand-correct.

## Three widget kinds operators actually need

The dashboard surface should answer three questions at a glance:

1. **"How are we doing right now?"** — single-stat cards: bookings today, revenue this week, sold-out showtimes this weekend.
2. **"What's the trend?"** — one or two compact charts: bookings over the last 14 days, gift-card-balance burn, point-redemption rate.
3. **"What needs my attention?"** — short tables: bookings flagged, refund follow-ups, low gift-card-float locations.

Don't put more than five widgets on the default dashboard. Anything beyond that belongs on a dedicated `/admin/reports` page (see `references/custom-page-patterns.md`).

## Stat widgets

Filament's `Filament\Widgets\StatsOverviewWidget` is the right base. It renders `Stat` items in a responsive grid.

```php
namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TodayStats extends StatsOverviewWidget
{
    protected ?string $heading = 'Today';
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $today = now()->startOfDay();
        return [
            Stat::make('Bookings', Booking::where('created_at', '>=', $today)->count())
                ->description('Confirmed and held')
                ->descriptionIcon('heroicon-o-ticket')
                ->color('primary'),
            Stat::make('Revenue', $this->formattedRevenueSince($today))
                ->description('Net of voids')
                ->descriptionIcon('heroicon-o-banknotes')
                ->color('success'),
            Stat::make('Sold-out showtimes', $this->soldOutCountSince($today))
                ->description('Tonight')
                ->descriptionIcon('heroicon-o-fire')
                ->color('warning'),
        ];
    }
}
```

### Brand rules for stat widgets

- **Heading:** `protected ?string $heading = 'Today';` — sentence case, no trailing punctuation.
- **Stat label:** sentence case (`'Bookings'`, not `'BOOKINGS'`).
- **Stat value:** raw value or pre-formatted string. **Money is integer cents** in storage; format to dollars at the widget boundary using `Concerns\FormatsCurrency::centsToDisplay()`. Counts ≥ 1,000 use comma separators (`number_format($n)`).
- **Description:** lowercase fragment, no trailing period.
- **Description icon:** outline Heroicon (`heroicon-o-*`).
- **Color:** semantic string (`primary`, `success`, `warning`, `danger`, `info`, `gray`). The bundled `theme.css` already maps these to brand tokens.
- **Sort:** integer. Lower = higher on the dashboard. Reserve `1–10` for "right now" stats, `11–20` for trend charts, `21+` for tables.

### Cents → dollars at widget boundary

Reuse the existing trait, don't reinvent it. The trait exposes two static methods: `centsToDisplay(?int)` for the storage→display direction and `displayToCents(?string)` for the inverse:

```php
use App\Filament\Concerns\FormatsCurrency;

class RevenueWidget extends StatsOverviewWidget
{
    use FormatsCurrency;

    protected function getStats(): array
    {
        $cents = Booking::sum('total_cents');
        return [Stat::make('Revenue', self::centsToDisplay($cents))];
    }
}
```

## Chart widgets

Filament's `Filament\Widgets\ChartWidget` is a Chart.js wrapper. Two chart types fit the brand:

| Chart kind | When | Why |
| ---------- | ---- | --- |
| `line` | Continuous metric over time (bookings/day, revenue/week) | Clean, unobtrusive. Reads as a sparkline at small sizes. |
| `bar` | Discrete counts across categories (bookings by location, refunds by reason) | Operators compare bars left-to-right faster than slices of pie. |

Avoid `pie` and `doughnut` — they encode less information per pixel and don't match the No-Line aesthetic.

### Color discipline for charts

Filament's stock chart palette is generic. Override it in the widget:

```php
protected function getOptions(): array
{
    return [
        'plugins' => [
            'legend' => ['display' => false], // operators don't need a legend on a single-series chart
        ],
        'scales' => [
            'x' => ['grid' => ['display' => false]],
            'y' => ['grid' => ['color' => 'rgba(255, 241, 220, 0.06)']], // matches --fc-border-subtle
        ],
    ];
}

protected function getData(): array
{
    return [
        'labels' => $this->labels(),
        'datasets' => [[
            'data' => $this->values(),
            'borderColor' => '#dac769', // --fc-secondary (signal gold)
            'backgroundColor' => 'rgba(218, 199, 105, 0.12)',
            'tension' => 0.3, // gentle curve, not aggressive
            'pointRadius' => 0, // No-Line: clean line, no scattered dots
            'pointHoverRadius' => 4,
        ]],
    ];
}
```

### Single-color series only

Multi-series charts on the admin dashboard are almost always a mistake — they need a legend, the legend needs colors that aren't gold, and the brand starts fragmenting. If you genuinely need to compare two metrics, render two charts side-by-side, both in gold, with the same y-axis range.

## Table widgets

For "needs attention" lists, prefer a compact `TableWidget` over a full Resource link-out.

```php
namespace App\Filament\Widgets;

use App\Models\Booking;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class FlaggedBookingsWidget extends TableWidget
{
    protected ?string $heading = 'Flagged for review';
    protected static ?int $sort = 21;
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            // Eager-load any relation referenced by a column. Filament does not
            // auto-eager-load — the dashboard becomes N+1 the moment a column
            // dotted-path-walks into a relation (e.g. `location.name`).
            ->query(
                Booking::query()
                    ->with(['location', 'user']) // add when columns reference these
                    ->whereNotNull('flagged_at')
                    ->latest('flagged_at')
                    ->limit(8)
            )
            ->columns([
                TextColumn::make('confirmation_code')->searchable(false),
                TextColumn::make('flagged_reason')->wrap()->color('gray'),
                TextColumn::make('flagged_at')->since(),
            ])
            ->recordUrl(fn (Booking $r) => route('filament.admin.resources.bookings.view', ['record' => $r]))
            ->paginated(false);
    }
}
```

### Brand rules for table widgets

- **Heading:** sentence case (`'Flagged for review'`, not `'Flagged Bookings'`).
- **Limit:** ≤ 8 rows. If a metric has more, link out to the full Resource.
- **No pagination** (`->paginated(false)`). Widgets are summaries, not browsers.
- **Click target:** `recordUrl` to the Resource's view page. Don't reinvent navigation.
- **Time:** `->since()` for "5 minutes ago" format on the dashboard. Tables-on-pages use absolute timestamps; widgets use relative.

## Layout — column spans

Filament's dashboard uses a 12-column grid by default. Widget `$columnSpan` values that read well:

- Single stat: `$columnSpan = 1` (1/3 width on a 3-stat row).
- Stat row (multiple stats): handled by `StatsOverviewWidget` automatically.
- Chart: `$columnSpan = ['md' => 2, 'xl' => 1]` — half-width on tablets, third on wide desktops.
- Table: `$columnSpan = 'full'` — full width.

Don't mix more than two column spans on a single row; it reads as random.

## Polling — be deliberate

Filament widgets accept `protected static ?string $pollingInterval = '60s';`. Use it sparingly:

- **Do:** "Bookings today" stat — polling every 60s confirms the panel is live.
- **Avoid:** Long-tail charts — refreshing a 30-day chart every 60 seconds wastes cache cycles for no perceptual gain.
- **Avoid:** Anything over `120s` polling on the same page — the user will refresh the browser before then.

## Permissions

Same `canView()` shape as Pages:

```php
public static function canView(): bool
{
    return auth('admin')->user()?->can('reports.view') ?? false;
}
```

Widgets inherit the panel's auth guard; missing this method shows the widget to everyone with `canAccessPanel()`.

## Anti-patterns

- **Avoid:** More than five widgets on the default dashboard — moves to `/admin/reports`.
- **Avoid:** Pie or doughnut charts.
- **Avoid:** Two-color stat (`->color('success')` + a colored description). Pick one accent per stat; the eye reads the value first, the description second.
- **Avoid:** Polling intervals shorter than 60s on dashboard widgets — breaks the "calm" half of "calm under dense data."
- **Avoid:** Reaching for `Color::hex('…')` inline. Use the semantic strings; the brand evolves through `AdminPanelProvider->colors()`.
