@include('oai.partials.envelope')
    <request verb="ListSets">{{ url('/oai') }}</request>
    <ListSets>
@foreach ($journals as $journal)
        <set>
            <setSpec>journal:{{ $journal->slug }}</setSpec>
            <setName>{{ $journal->title }}</setName>
        </set>
@endforeach
    </ListSets>
</OAI-PMH>
