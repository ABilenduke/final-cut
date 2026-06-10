@component('mail::message')
# A gift from {{ $giftCard->sender_name }}

{{ $giftCard->recipient_name }}, you've been sent a Final Cut gift card.

@if ($giftCard->message)
> {{ $giftCard->message }}
@endif

- **Value:** ${{ number_format($giftCard->initial_balance / 100, 2) }}
- **Code:** `{{ $giftCard->code }}`

Redeem it on any film, any seat, any provision from the bar — enter the code
at checkout or at the box office. It never expires.

See you in the dark,<br>
Final Cut
@endcomponent
