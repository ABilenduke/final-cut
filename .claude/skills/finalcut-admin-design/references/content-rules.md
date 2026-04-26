# Content Rules Reference

Voice, tone, casing, copy, and formatting conventions for the Final Cut
admin panel. Written for operations staff, managers, and finance leads.

---

## Voice

Calm, literate, hospitality-veteran. The admin panel speaks the way a
head usher would: knowledgeable, efficient, never condescending. Copy
assumes the reader understands the domain (booking confirmation codes,
loyalty tiers, showtime terminology). No over-explaining.

The voice is consistent whether the copy is a button label, an empty-state
message, a warning modal, or an audit log entry.

---

## Tone

Imperative but polite. Actions are named as verbs that describe exactly
what will happen:

Good: "Publish showtime" / "Void booking" / "Adjust points" / "Cancel refund"
Bad: "Submit" / "Confirm action" / "OK" / "Yes"

Warnings are matter-of-fact. No exclamation marks. No "Are you sure?"

Good: "Void this booking. The seat will be released and the customer
notified by email. This cannot be undone."

Bad: "Warning! Are you sure you want to void this booking?!"

Error messages state the situation and the remedy, nothing else:

Good: "No bookings match CVF-A3X9K2. Check the code and try again."
Bad: "Error: Record not found (404)"

---

## Casing

| Context | Rule |
|---------|------|
| Page titles | Sentence case |
| Section headings | Sentence case |
| Button labels | Sentence case |
| Navigation items | Sentence case |
| Form field labels | Sentence case |
| Placeholder text | Sentence case |
| Table headers | ALL CAPS + letter-spacing 0.06em |
| Status chip text | ALL CAPS |
| Eyebrow labels above stat cards | ALL CAPS |
| Error messages | Sentence case |
| Audit log entries | Sentence case |
| Tooltip text | Sentence case |

Never title-case a sentence outside of a proper noun. Never ALL CAPS a
full sentence or paragraph.

---

## Number formatting

### Time

24-hour always. No AM/PM anywhere.

```
Good: 19:30
Bad:  7:30 PM

Good: 09:05
Bad:  9:05 AM

With date: Sat 25 Apr, 19:30
```

Include the date when a value might span midnight or the current day is
ambiguous.

### Currency

Two decimal places always. Thousands separator at or above 1,000. No
rounding display.

```
Good: $18,420.00
Good: $0.00
Good: $4.50
Bad:  $18420
Bad:  $0
Bad:  $4.5
```

### Counts and quantities

Thousands separator at or above 1,000.

```
Good: 1,204 seats
Good: 34 customers
Bad:  1204 seats
```

### Tabular figures

Apply `font-variant-numeric: tabular-nums` (via the `admin-tabular` class)
to any table column where values will be read and compared: totals, seat
counts, loyalty points, timestamps. Proportional figures are acceptable for
inline copy and headings.

### Uncertain or unavailable values

Use an em dash (--) instead of zero or "N/A" when a value is genuinely
unknown or not applicable to the current record. "Balance: --" is honest.
"Balance: 0" implies a confirmed zero, which may be wrong and misleading.

```
Good: "Premier expiry: --"     (member-tier user has no expiry)
Bad:  "Premier expiry: N/A"
Bad:  "Premier expiry: 0"
```

---

## Date and time formats

| Context | Format |
|---------|--------|
| Full date | Sat 25 Apr 2026 |
| Short date (current year implied) | 25 Apr |
| Time | 19:30 |
| Date and time combined | Sat 25 Apr, 19:30 |
| Date range | 25 Apr -- 02 May |
| Audit log timestamp | 2026-04-25 19:42:07 |
| Relative (recent, informal) | 3 minutes ago -- only in audit log tails, never in tables |

Do not display timezone abbreviations unless the value is in a timezone
different from the local admin timezone.

---

## Audit log conventions

Audit log entries use past tense, third person. Actor precedes the action.

### Format

"[Actor] [verb] [object] [detail]."

The actor is a person name (abbreviated first initial + surname) or
"System" for automated actions.

### Examples

"A. Okafor voided booking CVF-A3X9K2."
"System released held seats for showtime #4481."
"M. Patel adjusted loyalty points: +200 pts (manual correction)."
"J. Kim published showtime: Dune Part Two, Screen 3, Sat 25 Apr 19:30."
"System processed refund: $18.50 to card ending 4242."
"A. Okafor cancelled showtime #4481 (equipment fault)."

Do not write audit log entries in present tense ("cancels", "voids").
Do not omit the actor even for system actions.
Do not include PII beyond what is needed for the audit record.

---

## Worked copy examples

These illustrate the conventions above in context.

### Stat card

```
SHOWTIMES THIS WEEK
8
3 sold out
```

### Empty state (table)

```
No bookings found
Adjust your filters or search for a different confirmation code.
```

### Success notification (toast)

```
Showtime published
Dune Part Two, Screen 3, Sat 25 Apr 19:30
```

### Warning modal

```
Void this booking?

CVF-A3X9K2 -- 2 seats, Screen 2, Fri 24 Apr 21:15

The seats will be released and the customer notified by email.
This cannot be undone.

[Cancel]  [Void booking]
```

### Elevated-amount confirmation

```
Amount exceeds $200.00

Adjusting loyalty points by +1,500 pts is a large correction.
Ask a supervisor to sign off before continuing.

[Cancel]  [Proceed with adjustment]
```

### Info banner (low stock)

```
Gift card float running low
Lobby 2 has 4 cards remaining. Restock before the evening session.
```

### Cancellation followup email status

```
34 customers notified
Refund-pending bookings: 12
Confirmation emails queued at 19:42
```

### Error (lookup miss)

```
No results for "CVF-A3X9Z9"
Check the code and try again. Codes begin with CVF-.
```
