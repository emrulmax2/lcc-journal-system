@component('mail::message')
# Confirm your subscription

Someone — we hope you — asked to receive the {{ config('app.name') }} newsletter at this address.

**We will not send you anything until you confirm.** If it wasn't you, ignore this email and nothing further will happen.

@component('mail::button', ['url' => $confirmUrl])
Confirm subscription
@endcomponent

You can unsubscribe at any time, in one click: [unsubscribe]({{ $unsubscribeUrl }}).

Thanks,<br>
{{ config('app.name') }}
@endcomponent
