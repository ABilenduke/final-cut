# Content fundamentals — admin voice

The customer-facing voice is editorial. The admin voice is the **calm, literate house manager**: a hospitality veteran who runs the room without making a scene of it. Words are chosen, not stacked.

## Voice

Measured · precise · quietly confident · never cute. Imperative but polite — the panel is talking to a colleague, not a stranger.

| **Do:** | **Avoid:** |
| -- | -- |
| `Publish showtime` | `Schedule a New Showtime!` |
| `Void booking` | `Cancel This Booking` |
| `Acknowledge` | `Got it!` |
| `Refund issued by A. Bilenduke · 19:42` | `Refund was successfully issued by Andrew at 7:42 PM` |
| `Ask a supervisor before continuing.` | `Please contact your supervisor for approval` |

### Pronouns

- **You** when addressing the operator: *"Ask a supervisor before continuing."*
- **We** sparingly, only when the product itself is acting: *"We've held the booking for 7 minutes."*
- **Never reference the brand by name in-product** — the product's presence is felt, not announced. No "Final Cut requires…" or "The Final Cut admin panel will…".

### Tense

- **Imperative** for buttons, menu items, calls to action: *"Adjust points"*, *"Void booking"*, *"Publish"*.
- **Past tense** for completed audit events: *"Refund processed by A. Bilenduke · 19:42"*. Aligns with the activity log voice (`spatie/laravel-activitylog` already writes past-tense entries; match them).
- **Present** for system state: *"Held by another operator since 19:30."*
- **Future** is rare in admin copy. If you find yourself writing *"This will…"*, you're probably narrating; cut it.

## Casing

| Use | Where |
| --- | ----- |
| **Sentence case** | Page titles, section headings, button labels, menu items, table cells, validation messages, notification titles. |
| **ALL CAPS** | Table-column eyebrows / status pill labels (set in Newsreader Condensed with +0.10em tracking — already handled by `theme.css`). |
| **Title Case** | Proper nouns only — film titles ("The Brutalist"), auditorium names ("Atrium"), member tier names if you brand them ("Founders"). |

Casing examples drawn from this codebase:

- **Do:** `Booking lookup` (page title), `Adjust points` (button), `Confirmation code` (label).
- **Do:** `BOOKINGS` / `STATUS` / `TOTAL` (table column eyebrows).
- **Avoid:** `Booking Lookup`, `Adjust Points`, `Confirmation Code` — Title Case for any of these reads as marketing voice.

## Numbers and units

The customer site has its own conventions; the admin context is denser and stricter:

| Need | Convention | Example |
| ---- | ---------- | ------- |
| **Money** | Two decimals always, comma separator at ≥ 1,000. Stored as integer cents — see `docs/architecture/DATA_MODELS.md`. | `$18,420.00`, `$3.50`, never `$18420` or `$3.5` |
| **Time of day** | 24-hour format. Operators schedule in 24h. | `19:30`, never `7:30 PM` |
| **Date** | ISO `YYYY-MM-DD` for records. `Apr 26, 2026` only when human prose explicitly demands it. | `2026-04-26`, `2026-04-26 19:42` |
| **Counts ≥ 1,000** | Comma separator. | `1,820 points`, `12,400 bookings` |
| **Comparable digits** | Tabular monospace. Use `FontFamily::Mono` on table columns / stat values where operators scan vertically. | totals, balances, point deltas |
| **Percentages** | One decimal max. No trailing zero on whole percents. | `92%`, `87.5%`, never `92.0%` |

### Currency in code

Storage is integer cents; display is dollars. Never compute money in floats. The `Concerns\FormatsCurrency` trait already exists — reuse it:

```php
use App\Filament\Concerns\FormatsCurrency;
$display = self::centsToDisplay($booking->total_cents); // "$18,420.00"
```

## Emoji and Unicode

**No emoji, ever.** Meaning is carried by iconography (Heroicons line-style) and the brand color palette.

Unicode dingbats are permitted only as **typographic notation**:

- Bullet separator: `·` (interpunct, U+00B7) — used in compact rows like `Refund issued by A. Bilenduke · 19:42`.
- En-dash for ranges: `–` (U+2013) — `19:30 – 21:45`.
- Times for dimensions: `×` (U+00D7) — `12 × 18 grid`.
- Up/down deltas: `▲` `▼` (U+25B2 / U+25BC) — for stat-widget trend indicators.

**Avoid:** Don't use `↑`, `→`, `★`, `☆`, ✓, ✗ as decorative glyphs. Use Heroicons.

## Real examples (drawn from existing flows)

These are the paragon copy strings — match this register everywhere:

- `Eight showtimes sold out this weekend.`
- `Refund processed to original card · ending 4242.`
- `Auto-releases held seats after 7 minutes of inactivity.`
- `Amount exceeds $200. Ask a supervisor to sign off before continuing.`
- `Adjustment exceeds 1,000 points. Ask a manager to sign off.` (the existing `LOYALTY_LARGE_ADJUSTMENT_THRESHOLD` flow)
- `Gift card float running low. Lobby 2 has 4 cards left.`
- `Held by another operator since 19:30.`
- `No booking found. Double-check the code or email and try again.` (existing `BookingLookup`)

## Validation messages

Validation copy follows the same rules. Three patterns:

```php
// 1. Required field — name what's needed
'query.required' => 'Enter a confirmation code or email.',

// 2. Format — tell them what would work
'amount.numeric'   => 'Use digits only — for example, 1500 for $15.00.',

// 3. Threshold / business rule — name the condition
'points.max' => 'Adjustment exceeds 1,000 points. Ask a manager to sign off.',
```

**Avoid:**
- `'This field is required'` — generic, no context. Replace with the actual context.
- `'Invalid input!'` — the exclamation, the missing diagnosis.
- `'Please enter a valid value'` — circular. What value would be valid?

## Notification copy

See `references/notification-and-badge-mapping.md` § Title / body voice for the full pattern. Summary:

- Title: ≤ 6 words, sentence case, no exclamation. The success/warning/danger color carries the emotional register.
- Body: adds context (numbers, scope, next step). Past tense for completed actions, present-tense imperative for follow-up.

## Audit-log entries

`spatie/laravel-activitylog` writes one row per state change. Description format:

- Past tense subject-verb-object: *"Adjusted loyalty points"*, *"Voided gift card"*.
- Properties (the model attributes) carry the diff; the description carries the *intent*.
- Don't include actor name in the description — it's already in `causer_id`.

```php
activity('loyalty')
    ->performedOn($user)
    ->causedBy($admin)
    ->withProperties(['delta' => $delta, 'balance_after' => $balance])
    ->log('Adjusted loyalty points'); // ← this is the description
```

## Anti-patterns to grep for before merging

```bash
# Title Case button labels
grep -rnE "['\"](Adjust|Publish|Void|Refund|Create|Edit|Delete) [A-Z]" backend/app/Filament/

# 12-hour time formatting (basic-grep treats \| literally — must use -E)
grep -rnE 'AM|PM|h:i a' backend/app/Filament/

# Float money formatting (should always be cents)
grep -rn '\$money(' backend/app/Filament/ | grep -v 'divideBy: 100'

# Emoji in code (use a lint check too)
grep -rPn '[\x{1F300}-\x{1FAFF}]|[\x{2600}-\x{27BF}]' backend/app/Filament/

# Exclamation marks in user-facing strings (cover both quote styles)
grep -rnE "!['\"]" backend/app/Filament/
```

If a search returns matches, each one is a content-discipline review point — not necessarily wrong, but worth justifying.
