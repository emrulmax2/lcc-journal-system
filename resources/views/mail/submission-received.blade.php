@component('mail::message')
# Your manuscript is with the editorial office

Thank you for submitting **{{ $title }}** to *{{ $journalTitle }}*.

@if($reference)
Your reference number is **{{ $reference }}** — quote it in any correspondence about this
submission.
@endif

You will hear from us as the manuscript moves through editorial assessment and, if it goes
forward, peer review. There is nothing you need to do in the meantime.

Thanks,<br>
The editorial office, {{ $journalTitle }}
@endcomponent
