@component('mail::message')
Hi {{ $customerName }},

Your booking **{{ $booking->confirmation_code }}** for
**{{ $booking->showtime->movie->title ?? 'your showing' }}** on
{{ $booking->showtime?->start_time?->format('l F j, Y \a\t g:i a') ?? 'the scheduled date' }}
has been refunded.

@if ($cardRefund > 0)
- **Refunded to your card:** ${{ number_format($cardRefund / 100, 2) }} (allow 5–10 business days to appear on your statement)
@endif
@if ($giftRestored > 0)
- **Restored to your gift card:** ${{ number_format($giftRestored / 100, 2) }}
@endif

@if ($booking->showtime?->auditorium?->location?->phone)
Questions? Reply to this email or call {{ $booking->showtime->auditorium->location->phone }}.
@else
Questions? Reply to this email.
@endif

Thanks,<br>
{{ config('app.name') }}
@endcomponent
