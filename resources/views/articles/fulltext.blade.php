{{--
    The crawlable HTML full text. Server-rendered PHP — no React, no Vite, no Node.

    citation_fulltext_html_url points here, and Google Scholar reads the full body straight
    out of this HTML. It must therefore be complete and correct with JavaScript disabled and
    the SSR process dead, exactly like the citation meta tags on the landing page.

    $bodyHtml comes from MarkdownRenderer, which escapes raw HTML on the way in — so {!! !!}
    here prints safe, editor-authored markup and never attacker script.
--}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $article->title }} — {{ $article->journal->title }}</title>
    <meta name="description" content="{{ \Illuminate\Support\Str::of($article->abstract ?? '')->squish()->limit(160) }}">

    {{-- Canonical points at the DOI landing page, not here: this is the full-text rendition,
         the landing page is the citable object. --}}
    <link rel="canonical" href="{{ $canonical }}">

    @foreach ($citationMeta ?? [] as [$name, $content])
        <meta name="{{ $name }}" content="{{ $content }}">
    @endforeach

    @unless ($indexable ?? true)
        <meta name="robots" content="noindex, nofollow">
    @endunless

    <style>
        :root { color-scheme: light dark; }
        body {
            font-family: Georgia, 'Times New Roman', serif;
            line-height: 1.65; color: #1a2230; background: #fff;
            max-width: 46rem; margin: 0 auto; padding: 2.5rem 1.25rem 6rem;
        }
        header { border-bottom: 1px solid #e2e8f0; padding-bottom: 1.5rem; margin-bottom: 2rem; }
        .eyebrow { font-family: system-ui, sans-serif; font-size: .8rem; letter-spacing: .06em;
            text-transform: uppercase; color: #0f766e; margin: 0 0 .5rem; }
        h1 { font-size: 1.9rem; line-height: 1.2; margin: 0 0 1rem; }
        .authors { font-family: system-ui, sans-serif; color: #475569; font-size: .95rem; margin: 0; }
        .meta { font-family: system-ui, sans-serif; color: #64748b; font-size: .85rem; margin-top: .75rem; }
        .meta a { color: #0f766e; }
        h2 { font-size: 1.3rem; margin: 2.5rem 0 .75rem; }
        .abstract { background: #f8fafc; border-left: 3px solid #0f766e; padding: 1rem 1.25rem; border-radius: 0 .5rem .5rem 0; }
        .fulltext :is(p, ul, ol, table) { margin: 1rem 0; }
        .fulltext img { max-width: 100%; height: auto; }
        .fulltext table { border-collapse: collapse; width: 100%; }
        .fulltext :is(th, td) { border: 1px solid #e2e8f0; padding: .4rem .6rem; text-align: left; }
        .refs { font-family: system-ui, sans-serif; font-size: .9rem; color: #334155; padding-left: 1.25rem; }
        .refs li { margin: .5rem 0; }
        .actions { font-family: system-ui, sans-serif; margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .actions a { color: #0f766e; text-decoration: none; font-size: .9rem; font-weight: 600; }
        @media (prefers-color-scheme: dark) {
            body { color: #e2e8f0; background: #0b1120; }
            .abstract { background: #111a2e; }
            header { border-color: #1e293b; }
            .fulltext :is(th, td) { border-color: #1e293b; }
        }
    </style>
</head>
<body>
    <article>
        <header>
            <p class="eyebrow">{{ $article->journal->title }}@if($article->section) · {{ $article->section->name }}@endif</p>
            <h1>{{ $article->title }}</h1>

            <p class="authors">
                @if ($article->hasCorporateAuthor())
                    {{ $article->corporate_author }}
                @else
                    {{ $article->authors->map(fn ($a) => $a->fullName())->implode(', ') }}
                @endif
            </p>

            <p class="meta">
                @if ($article->doi())
                    <a href="{{ $article->doiUrl() }}">https://doi.org/{{ $article->doi() }}</a>
                @endif
                @if ($article->pageRange()) · pp. {{ $article->pageRange() }}@endif
                @if ($article->published_at) · {{ $article->published_at->format('j F Y') }}@endif
            </p>

            <p class="actions">
                <a href="{{ $article->landingUrl() }}">Article landing page</a>
                @if ($article->hasPdf())<a href="{{ $article->pdfUrl() }}">Download PDF</a>@endif
            </p>
        </header>

        @if (filled($article->abstract))
            <section>
                <h2>Abstract</h2>
                <div class="abstract">{{ $article->abstract }}</div>
            </section>
        @endif

        <section class="fulltext">
            <h2>Full text</h2>
            {{-- Safe: MarkdownRenderer escapes raw HTML input, so this is editor markup only. --}}
            {!! $bodyHtml !!}
        </section>

        @if ($article->references->isNotEmpty())
            <section>
                <h2>References</h2>
                <ol class="refs">
                    @foreach ($article->references as $reference)
                        <li>
                            {{ $reference->raw_text }}
                            @if ($reference->doi)
                                <a href="https://doi.org/{{ $reference->doi }}">https://doi.org/{{ $reference->doi }}</a>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </section>
        @endif
    </article>
</body>
</html>
