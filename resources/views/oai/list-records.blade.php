@include('oai.partials.envelope')
    <request verb="{{ $identifiersOnly ? 'ListIdentifiers' : 'ListRecords' }}" metadataPrefix="oai_dc">{{ url('/oai') }}</request>
    <{{ $identifiersOnly ? 'ListIdentifiers' : 'ListRecords' }}>
@foreach ($articles as $article)
@include('oai.partials.record', ['article' => $article, 'identifiersOnly' => $identifiersOnly])
@endforeach
@if ($resumptionToken !== null)
        <resumptionToken completeListSize="{{ $completeListSize }}" cursor="{{ $cursor }}">{{ $resumptionToken }}</resumptionToken>
@else
        {{-- An EMPTY resumptionToken is the spec's way of saying "that was the last page".
             Omitting the element entirely leaves the harvester unsure whether to keep asking. --}}
        <resumptionToken completeListSize="{{ $completeListSize }}" cursor="{{ $cursor }}"></resumptionToken>
@endif
    </{{ $identifiersOnly ? 'ListIdentifiers' : 'ListRecords' }}>
</OAI-PMH>
