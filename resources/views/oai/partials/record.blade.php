{{--
    One OAI record, in oai_dc (Dublin Core).

    The identifier is derived from the article SLUG, which is frozen at publication and
    never regenerated. That is the whole reason it can be used as a permanent OAI
    identifier: a harvester that saw this record two years ago must be able to ask for the
    same id and get the same article.
--}}
    <record>
        <header>
            <identifier>oai:{{ request()->getHost() }}:{{ $article->slug }}</identifier>
            <datestamp>{{ $article->updated_at->toIso8601ZuluString() }}</datestamp>
            <setSpec>journal:{{ $article->journal->slug }}</setSpec>
        </header>
@if (! ($identifiersOnly ?? false))
        <metadata>
            <oai_dc:dc xmlns:oai_dc="http://www.openarchives.org/OAI/2.0/oai_dc/"
                       xmlns:dc="http://purl.org/dc/elements/1.1/"
                       xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
                       xsi:schemaLocation="http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd">
                <dc:title>{{ $article->title }}</dc:title>
{{-- A corporate author IS a creator. Looping over article_authors alone would emit an
     authorless record, and DOAJ rejects those. --}}
@if ($article->hasCorporateAuthor())
                <dc:creator>{{ $article->corporate_author }}</dc:creator>
@else
@foreach ($article->authors as $author)
                <dc:creator>{{ $author->family_name }}, {{ $author->given_name }}</dc:creator>
@endforeach
@endif
@foreach ($article->keywords ?? [] as $keyword)
                <dc:subject>{{ $keyword }}</dc:subject>
@endforeach
@if ($article->abstract)
                <dc:description>{{ $article->abstract }}</dc:description>
@endif
                <dc:publisher>{{ $article->journal->publisher }}</dc:publisher>
                <dc:date>{{ $article->published_at?->toDateString() }}</dc:date>
                <dc:type>{{ $article->section?->name ?? 'Text' }}</dc:type>
                <dc:format>application/pdf</dc:format>
{{-- The DOI is the preferred identifier, but it is NULL until Crossref issues a prefix.
     While it is, the landing page URL is the identifier — never an empty element, which
     would assert that the article has no identifier at all. --}}
@if ($article->doiUrl())
                <dc:identifier>{{ $article->doiUrl() }}</dc:identifier>
@endif
                <dc:identifier>{{ $article->landingUrl() }}</dc:identifier>
@php
    // Built in PHP rather than with inline @if/@endif chains: adjacent Blade directives
    // on one line do not parse, and a citation string is exactly the kind of thing that
    // wants to be assembled once and read once.
    $source = $article->journal->title;
    if ($article->issue) {
        $source .= ', '.$article->issue->volume->number.'('.$article->issue->number.')';
    }
    if ($article->pageRange()) {
        $source .= ', pp. '.$article->pageRange();
    }
@endphp
                <dc:source>{{ $source }}</dc:source>
                <dc:language>en</dc:language>
@if ($article->journal->license)
                <dc:rights>{{ $article->journal->license }}</dc:rights>
@endif
            </oai_dc:dc>
        </metadata>
@endif
    </record>
