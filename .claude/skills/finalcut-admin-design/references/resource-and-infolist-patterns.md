# Resources, infolists, and tables — patterns

The admin panel is read-heavy. Most Resources here use `viewSchema()` more than `formSchema()` — operators audit and verify more than they edit. This file covers the three surfaces in order of frequency: tables, view schemas (infolists), then form schemas.

## Filament v5 schema-first API

All Resources in this codebase use the schema-first imports. Patches that don't match this shape are wrong.

```php
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

public static function form(Schema $schema): Schema { /* … */ }
public static function viewSchema(Schema $schema): Schema { /* … */ }
public static function table(Table $table): Table { /* … */ }
```

If you see `Filament\Forms\Form` or `function form(Form $form)`, that's v3. Convert before merging.

## Tables — the most-touched surface

Operators live in tables. Get these right and the panel feels right.

### Column ordering

Lead with the **identifier** the operator searched for (confirmation code, gift card code, user email). Then the qualifier (status, location, tier). Then the metric they care about (total, balance, points). Then the timestamp. The eye scans left-to-right; put the answer to "did I find the right row?" first.

```php
$table->columns([
    TextColumn::make('confirmation_code')->searchable()->copyable(),
    TextColumn::make('display_status')->badge()->color(fn (string $state) => match ($state) { /* … */ }),
    TextColumn::make('location.name')->toggleable(),
    TextColumn::make('total_cents')->money('USD', divideBy: 100)->alignRight(),
    TextColumn::make('created_at')->dateTime('Y-m-d H:i')->sortable(),
]);
```

### Format conventions

- **Money:** `->money('USD', divideBy: 100)` — values are stored as integers in cents (project rule, see `docs/architecture/DATA_MODELS.md`). The `divideBy: 100` parameter keeps the storage convention intact while displaying dollars.
- **Time:** `->dateTime('Y-m-d H:i')` — 24-hour format, ISO-style date. Operators schedule in 24h.
- **Numbers:** `->numeric(thousandsSeparator: ',')` for any count ≥ 1,000 (loyalty points, seat counts in busy showtimes).
- **Tabular columns:** `->fontFamily(\Filament\Support\Enums\FontFamily::Mono)` on any column that operators **compare** down a column — totals, balances, point deltas. Filament's stock proportional font misaligns digits.

### Eager-loading

Filament does not auto-eager-load. Any column whose `make()` walks a relation (`'location.name'`, `'user.email'`) issues an extra query per row. For tables with relation columns, override `getEloquentQuery()` on the Resource (or `->modifyQueryUsing()` on the table) with `->with([...])`:

```php
public static function getEloquentQuery(): Builder
{
    return parent::getEloquentQuery()->with(['location', 'user']);
}
```

Same goes for `viewSchema` callbacks that walk relations: `$r->bookingSeats->map(...)` is N+1 unless `bookingSeats` was eager-loaded for the View page.

### Filters

Use `SelectFilter` for enum-backed columns (booking status, loyalty tier, location). Avoid free-text search filters on indexed columns — they're slower than `->searchable()` on the column itself, which uses the right indexes.

```php
SelectFilter::make('status')
    ->options(BookingStatus::class) // PHP 8.1 enum-as-options
    ->multiple();
```

### Row actions

Single primary action: open the View page. Use the row click target, not a separate "View" button — wastes column space. Filament does this by default when `ViewAction` is registered:

```php
$table->recordUrl(fn (Booking $r): string => static::getUrl('view', ['record' => $r]));
```

Reserve action buttons for things that *aren't* "look at this row": flag, void, refund. Cap at three; spillover goes into a `…` menu.

## View schemas (infolists) — the read-heavy surface

`viewSchema()` is what renders on the View page (`/admin/bookings/{id}`). It's a `Schema` of `Section` and `Placeholder` components — *not* a form. Operators read, copy, and follow links.

### The right component for each role

| Need | Component | Notes |
| ---- | --------- | ----- |
| Display a static value | `Placeholder::make('label')->content(...)` | Default. The `content` callback receives the model. |
| Display a value as a badge | `Placeholder::make(...)->content(fn ($r) => view('filament.partials.status-badge', […]))` | Reach for a Blade partial — keeps tokens out of PHP. |
| Display a list of related records | Nested `Schema` of `TextEntry`s, **not** `HtmlString` of `<ul>` | See `references/token-mapping.md` § Pitfall 3. |
| Display formatted markup | `Placeholder` with a Blade partial | Don't reach for `HtmlString` until you've ruled out a partial. |

### Placeholder content

The simplest form is a static method or closure that returns a string:

```php
Placeholder::make('subtotal')
    ->content(fn (Booking $r) => $r->formattedSubtotal()); // Concerns\FormatsCurrency
```

Avoid `HtmlString` unless the content is genuinely structural. When you do reach for it, **don't ship raw Tailwind utility classes inside the string**. Either:

1. **Wrap in a Blade partial** so the markup is reviewed alongside the component:
   ```php
   ->content(fn (Booking $r) => view('filament.partials.seat-list', ['seats' => $r->bookingSeats]))
   ```
2. **Use brand-owned classes** that resolve in `theme.css`:
   ```php
   ->content(fn (Booking $r) => new HtmlString(
       '<ul class="fc-list">' . $r->bookingSeats->map(fn ($s) => "<li>{$s->seat_label}</li>")->implode('') . '</ul>'
   ))
   ```
   Then add `.fc-list { /* … */ }` to `theme.css`. The brand owns the class; the brand owns the spacing.

### Section composition

Group related placeholders into `Section`s. Each Section gets a heading, optional description, and a body of components. Sections sit on `surface_container` (the bundled `theme.css` enforces this). Don't nest Sections — use the `->columns(2)` modifier on a single Section to lay out side-by-side fields.

```php
Section::make('Booking')
    ->description('Final Cut booking record · read-only audit view')
    ->columns(2)
    ->schema([
        Placeholder::make('confirmation_code')->content(fn ($r) => $r->confirmation_code),
        Placeholder::make('display_status')->content(...),
        Placeholder::make('total')->content(...),
        Placeholder::make('booked_at')->content(fn ($r) => $r->created_at->format('Y-m-d H:i')),
    ]);
```

## Form schemas — the lighter surface

Most Resources are read-only at the field level (e.g., `BookingResource` has no form). Where forms exist, they tend to be narrow: loyalty point adjustments on `UserResource`, content fields on `CalendarEventResource`.

### Field selection

- **Strings:** `TextInput::make()` — single line. `Textarea::make()` for multi-line.
- **Enums:** `Select::make()->options(SomeEnum::class)` — never `Radio` (more visual weight than enums need).
- **Booleans:** `Toggle::make()` for human-meaningful state (e.g., "Visible to customers"). `Checkbox::make()` only for "I've read this" confirmation.
- **Money:** `TextInput::make('amount_cents')->numeric()->prefix('$')->dehydrateStateUsing(fn ($state) => (int) round($state * 100))` — UX shows dollars; storage stays cents. Use the `Concerns\FormatsCurrency` trait if it grows.
- **File upload:** `FileUpload::make()->disk('public')` — already used in `CalendarEventResource`. The customer-facing API derives URLs via `Storage::disk('public')->url(...)`.

### Validation copy

Validation errors render in `--fc-primary` (salmon) by default — appropriate. The message itself follows the content rules: sentence case, imperative if it tells the user what to do.

- **Do:** `Enter a confirmation code or email.`
- **Do:** `Adjustment exceeds 1,000 points. Ask a manager to sign off.`
- **Avoid:** `This field is required` (generic Filament default; replace with context)
- **Avoid:** `Invalid input!` (the exclamation, the missing context)

### Confirmation modals

For destructive or high-stakes actions, use `Action::make()->requiresConfirmation()` with a body that names the consequence. The bundled `theme.css` themes the confirmation modal to match.

```php
Action::make('void')
    ->requiresConfirmation()
    ->modalHeading('Void this booking?')
    ->modalDescription('Seats will be released and the customer will be notified. Cannot be undone.')
    ->modalSubmitActionLabel('Void booking')
    ->color('danger')
    ->action(/* … */);
```

The `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` flow on `UserResource` is the canonical example of an "elevated confirmation" — a modal that surfaces only when the delta exceeds the env-configured threshold. Mirror its shape (modal description names the threshold, submit label is the imperative verb, body uses sentence case).

## Anti-patterns — quick checks

Before merging any Resource change, grep for these:

```bash
# v3-style imports — should be empty
grep -rn 'use Filament\\Forms\\Form;' backend/app/Filament/

# Inline Tailwind in PHP — should be reviewed case-by-case
grep -rn 'class="' backend/app/Filament/

# Solid Heroicons — should be empty (line-style only)
grep -rn "'heroicon-s-" backend/app/Filament/

# Money-as-float — should be empty (always integer cents)
grep -rn '->money(' backend/app/Filament/ | grep -v 'divideBy: 100'
```
