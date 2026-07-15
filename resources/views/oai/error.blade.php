@include('oai.partials.envelope')
    <request>{{ url('/oai') }}</request>
    {{-- OAI errors are HTTP 200 with an <error> element. A 4xx makes harvesters treat the
         endpoint as broken instead of reading the reason. --}}
    <error code="{{ $code }}">{{ $message }}</error>
</OAI-PMH>
