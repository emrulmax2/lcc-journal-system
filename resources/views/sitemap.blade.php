<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($staticUrls as $url)
    <url>
        <loc>{{ $url }}</loc>
        <changefreq>weekly</changefreq>
    </url>
@endforeach
@foreach ($articles as $article)
    <url>
        <loc>{{ route('articles.show', $article->slug) }}</loc>
        <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
        {{-- A published article's URL is permanent. It will never change, so telling
             crawlers to keep re-checking it wastes their budget and ours. --}}
        <changefreq>yearly</changefreq>
        <priority>0.8</priority>
    </url>
@endforeach
</urlset>
