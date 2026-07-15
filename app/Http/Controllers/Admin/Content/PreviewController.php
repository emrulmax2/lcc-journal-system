<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Services\Content\MarkdownRenderer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The markdown preview. THE ONLY RENDERER IS THE SERVER'S.
 *
 * The obvious implementation is a client-side markdown library, and it is wrong: there would
 * then be TWO renderers with two sets of rules, and the preview would be a promise the live
 * page does not keep. They drift the day one of them is upgraded — and the drift that matters
 * is the security one, because MarkdownRenderer escapes raw HTML (`html_input: 'escape'`) and
 * a client-side library configured by whoever added it very likely does not. An editor would
 * paste a `<script>`, see it "work" in the preview, and publish a page that renders it as
 * literal text — or, far worse, the reverse.
 *
 * So the preview is a round-trip through the same MarkdownRenderer that renders the live
 * page. What you see is what will be served, because it is the same function.
 */
final class PreviewController extends Controller
{
    public function __invoke(Request $request, MarkdownRenderer $renderer): JsonResponse
    {
        $data = $request->validate([
            'markdown' => ['nullable', 'string', 'max:200000'],
        ]);

        return response()->json([
            'html' => $renderer->toHtml($data['markdown'] ?? null),
        ]);
    }
}
