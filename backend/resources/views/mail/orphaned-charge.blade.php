@component('mail::message')
# Orphaned Stripe charge

A payment succeeded at Stripe but no booking or gift card was recorded for
it within 30 minutes. The customer was charged and received nothing — this
needs a manual refund or reconciliation.

- **PaymentIntent:** `{{ $paymentIntentId }}`
- **Amount:** {{ strtoupper($currency) }} {{ number_format($amount / 100, 2) }}

Look the payment up in the Stripe dashboard to identify the customer, then
either refund it or recreate the order.

@endcomponent
