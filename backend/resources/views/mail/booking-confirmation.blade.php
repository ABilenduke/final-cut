@component('mail::message')
Hi {{ $customerName }},

You're in! Here are your tickets for
**{{ $booking->showtime->movie->title ?? 'your showing' }}**.

**Confirmation code: {{ $booking->confirmation_code }}**

- **When:** {{ $booking->showtime?->start_time?->format('l F j, Y \a\t g:i a') ?? 'See your booking' }}
- **Where:** {{ $booking->showtime?->auditorium?->location?->name ?? config('app.name') }} · {{ $booking->showtime?->auditorium?->name ?? '' }}

@if ($booking->seats->isNotEmpty())
**Seats**
@foreach ($booking->seats as $bookingSeat)
- {{ $bookingSeat->seat?->label ?? 'Seat' }} ({{ $bookingSeat->section }}) — ${{ number_format($bookingSeat->price / 100, 2) }}
@endforeach
@endif

@if ($booking->foodItems->isNotEmpty())
**Food & drink**
@foreach ($booking->foodItems as $foodItem)
- {{ $foodItem->quantity }} × {{ $foodItem->name }} — ${{ number_format($foodItem->total_price / 100, 2) }}
@endforeach
@endif

@if ($booking->discount > 0)
- **Subtotal:** ${{ number_format($booking->subtotal / 100, 2) }}
- **Discount:** −${{ number_format($booking->discount / 100, 2) }}
@endif
**Total paid: ${{ number_format($booking->total / 100, 2) }}**

Show your confirmation code at the door.

@if ($booking->showtime?->auditorium?->location?->phone)
Questions? Reply to this email or call {{ $booking->showtime->auditorium->location->phone }}.
@else
Questions? Reply to this email.
@endif

Enjoy the show,<br>
{{ config('app.name') }}
@endcomponent
