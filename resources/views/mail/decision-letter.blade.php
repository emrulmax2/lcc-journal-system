@component('mail::message')
# Decision: {{ $decisionLabel }}

Regarding **{{ $title }}**@if($reference) ({{ $reference }})@endif, submitted to
*{{ $journalTitle }}*.

@component('mail::panel')
{{ $letter }}
@endcomponent

Thanks,<br>
The editorial office, {{ $journalTitle }}
@endcomponent
