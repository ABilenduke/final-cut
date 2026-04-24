@component('mail::message')
Hi {{ $customerName }},

Unfortunately, your **{{ $booking->showtime->movie->title ?? 'showing' }}** showing on
{{ $booking->showtime?->start_time?->format('l F j, Y \a\t g:i a') ?? 'the scheduled date' }} has been cancelled.

Our team will contact you within 2 business days about a refund to your original payment method.

Your booking reference: **{{ $booking->confirmation_code }}**

@if ($booking->showtime?->auditorium?->location?->phone)
Questions? Reply to this email or call {{ $booking->showtime->auditorium->location->phone }}.
@else
Questions? Reply to this email.
@endif

Thanks for your understanding,
{{ config('app.name') }}
@endcomponent
