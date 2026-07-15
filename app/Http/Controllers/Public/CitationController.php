<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Services\Citations\CitationFormatter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a citation as a downloadable file, so that "Cite" can hand a .bib or .ris
 * straight to Zotero / Mendeley / EndNote rather than making the reader copy text out of
 * a modal and hand-fix it.
 */
class CitationController extends Controller
{
    public function __invoke(Request $request, Article $article, string $format): Response
    {
        abort_unless($article->isPublished(), 404);

        $article->load(['journal', 'authors', 'issue.volume']);

        $body = match ($format) {
            'harvard' => app(CitationFormatter::class)->harvard($article),
            'bibtex' => app(CitationFormatter::class)->bibtex($article),
            'ris' => app(CitationFormatter::class)->ris($article),
        };

        [$mime, $extension] = match ($format) {
            // The MIME types reference managers actually sniff for. text/plain would make
            // the browser render the file instead of handing it to Zotero.
            'bibtex' => ['application/x-bibtex', 'bib'],
            'ris' => ['application/x-research-info-systems', 'ris'],
            'harvard' => ['text/plain; charset=utf-8', 'txt'],
        };

        $filename = ($article->doi_suffix ?? $article->slug).'.'.$extension;

        return response($body, 200, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
