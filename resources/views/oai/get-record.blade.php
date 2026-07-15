@include('oai.partials.envelope')
    <request verb="GetRecord" metadataPrefix="oai_dc">{{ url('/oai') }}</request>
    <GetRecord>
@include('oai.partials.record', ['article' => $article, 'identifiersOnly' => false])
    </GetRecord>
</OAI-PMH>
