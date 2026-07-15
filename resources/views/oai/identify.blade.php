@include('oai.partials.envelope')
    <request verb="Identify">{{ url('/oai') }}</request>
    <Identify>
        <repositoryName>{{ config('app.name') }}</repositoryName>
        <baseURL>{{ url('/oai') }}</baseURL>
        <protocolVersion>2.0</protocolVersion>
        <adminEmail>{{ config('crossref.depositor_email') }}</adminEmail>
        <earliestDatestamp>{{ $earliest }}</earliestDatestamp>
        {{-- We never delete a published record. It can only be WITHDRAWN, and the landing
             page keeps resolving with a notice — because deleting it would kill the DOI. --}}
        <deletedRecord>no</deletedRecord>
        <granularity>YYYY-MM-DDThh:mm:ssZ</granularity>
    </Identify>
</OAI-PMH>
