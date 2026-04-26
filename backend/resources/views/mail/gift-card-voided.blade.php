@component('mail::message')
# Gift Card Voided

A gift card has been voided by an admin:

- **Code:** {{ $giftCard->code }}
- **Recipient:** {{ $giftCard->recipient_name }} ({{ $giftCard->recipient_email }})
- **Sender:** {{ $giftCard->sender_name }}
- **Balance voided:** ${{ number_format($balanceVoided / 100, 2) }}
- **Voided by:** {{ $by?->email ?? 'system' }}
- **Reason:** {{ $reason }}

Please contact the original purchaser to arrange a refund to their original payment method.

@endcomponent
