{{-- Atom 1.0 feed. Server-rendered XML for feed readers and aggregators — no JavaScript. --}}
<?php echo '<?xml version="1.0" encoding="UTF-8"?>'.PHP_EOL; ?>
<feed xmlns="http://www.w3.org/2005/Atom">
    <title>{{ $feedTitle }}</title>
    <id>{{ $feedId }}</id>
    <link rel="self" type="application/atom+xml" href="{{ $selfUrl }}"/>
    <link rel="alternate" type="text/html" href="{{ $feedId }}"/>
    <updated>{{ $updated->toAtomString() }}</updated>
    <generator>{{ config('app.name') }}</generator>

    @foreach ($articles as $article)
        <entry>
            <title>{{ $article->title }}</title>
            {{-- A DOI is the durable id when it exists; the landing URL otherwise. --}}
            <id>{{ $article->doiUrl() ?? $article->landingUrl() }}</id>
            <link rel="alternate" type="text/html" href="{{ $article->landingUrl() }}"/>
            @if ($article->hasPdf())
                <link rel="enclosure" type="application/pdf" href="{{ $article->pdfUrl() }}"/>
            @endif
            <updated>{{ ($article->updated_at ?? $article->published_at)?->toAtomString() }}</updated>
            <published>{{ $article->published_at?->toAtomString() }}</published>

            @if ($article->hasCorporateAuthor())
                <author><name>{{ $article->corporate_author }}</name></author>
            @else
                @foreach ($article->authors as $author)
                    <author><name>{{ $author->fullName() }}</name></author>
                @endforeach
            @endif

            @if (filled($article->abstract))
                <summary>{{ \Illuminate\Support\Str::of($article->abstract)->squish()->limit(500) }}</summary>
            @endif

            <source><title>{{ $article->journal->title }}</title></source>
        </entry>
    @endforeach
</feed>
