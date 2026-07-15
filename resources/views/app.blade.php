@php
    /**
     * The Inertia root view.
     *
     * READ THIS BEFORE MOVING ANYTHING INTO REACT.
     *
     * The citation meta block below is rendered by PHP, deliberately. Inertia SSR runs
     * in a separate Node process; when that process dies, Inertia does NOT error — it
     * silently falls back to client-side rendering. Humans still see a working site.
     * Crawlers see an empty <div id="app">. Nobody notices, and every DOI we have
     * registered quietly stops resolving to anything a machine can read.
     *
     * Emitting these tags from Blade means they are in the HTML whether Node is up,
     * down, or was never started. It is the one part of the DOI programme that cannot
     * be broken by an ops failure.
     *
     * `php artisan journal:check-ssr` asserts the body content separately.
     */
@endphp
<!DOCTYPE html>
<html lang="en" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title inertia>{{ $page['props']['meta']['title'] ?? config('app.name') }}</title>
    <meta name="description" content="{{ $page['props']['meta']['description'] ?? '' }}">

    <link rel="canonical" href="{{ $canonical ?? url()->current() }}">

    {{-- ------------------------------------------------------------------ --}}
    {{-- Highwire Press + Dublin Core. Built by App\Support\CitationMeta from --}}
    {{-- the same accessors the Crossref deposit reads, so the two cannot     --}}
    {{-- disagree about what this article is.                                 --}}
    {{-- ------------------------------------------------------------------ --}}
    @foreach ($citationMeta ?? [] as [$name, $content])
        <meta name="{{ $name }}" content="{{ $content }}">
    @endforeach

    @unless ($indexable ?? true)
        {{-- Drafts and previews must never enter an index. --}}
        <meta name="robots" content="noindex, nofollow">
    @endunless

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Newsreader:ital,opsz,wght@0,6..72,400;0,6..72,500;0,6..72,600;0,6..72,700;1,6..72,400;1,6..72,500&family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    @routes
    @viteReactRefresh
    @vite(['resources/js/app.tsx'])
    @inertiaHead
</head>
<body class="bg-white font-sans text-ink-900 antialiased">
    @inertia
</body>
</html>
