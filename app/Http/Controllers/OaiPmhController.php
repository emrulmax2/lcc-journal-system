<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Journal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * OAI-PMH 2.0 endpoint — how DOAJ, BASE, CORE and OpenAIRE actually pull our metadata.
 *
 * This is the other half of the discovery story. The citation_* meta tags get us into
 * Google Scholar; OAI-PMH gets us into the aggregators, and DOAJ requires it. Both are
 * consumed by machines that do not run JavaScript, so like every other public surface
 * here it is rendered server-side, in PHP.
 *
 * Only PUBLISHED articles are ever exposed. A draft leaking into a harvester cannot be
 * recalled — the aggregator has its own copy, and takedowns take months.
 *
 * Spec: http://www.openarchives.org/OAI/openarchivesprotocol.html
 */
class OaiPmhController extends Controller
{
    private const SUPPORTED_VERBS = [
        'Identify', 'ListMetadataFormats', 'ListSets',
        'ListIdentifiers', 'ListRecords', 'GetRecord',
    ];

    /** Harvesters are polite but numerous; a page of 100 keeps responses sane. */
    private const PAGE_SIZE = 100;

    public function __invoke(Request $request): Response
    {
        $verb = $request->string('verb')->toString();

        if ($verb === '' || ! in_array($verb, self::SUPPORTED_VERBS, true)) {
            return $this->error($request, 'badVerb', 'Illegal or missing OAI verb.');
        }

        return match ($verb) {
            'Identify' => $this->identify($request),
            'ListMetadataFormats' => $this->listMetadataFormats($request),
            'ListSets' => $this->listSets($request),
            'ListIdentifiers' => $this->listRecords($request, identifiersOnly: true),
            'ListRecords' => $this->listRecords($request, identifiersOnly: false),
            'GetRecord' => $this->getRecord($request),
        };
    }

    private function identify(Request $request): Response
    {
        return $this->xml('oai.identify', [
            'request' => $request,
            'earliest' => Article::published()->min('published_at')
                ? Carbon::parse(Article::published()->min('published_at'))->toIso8601ZuluString()
                : now()->toIso8601ZuluString(),
        ]);
    }

    private function listMetadataFormats(Request $request): Response
    {
        // oai_dc only. Advertising a format we cannot actually emit makes a harvester
        // request it and fail, and some then blacklist the endpoint.
        return $this->xml('oai.list-metadata-formats', ['request' => $request]);
    }

    private function listSets(Request $request): Response
    {
        return $this->xml('oai.list-sets', [
            'request' => $request,
            'journals' => Journal::where('is_active', true)->get(),
        ]);
    }

    private function listRecords(Request $request, bool $identifiersOnly): Response
    {
        if (($error = $this->requireOaiDc($request)) !== null) {
            return $error;
        }

        $query = Article::query()
            ->published()
            ->with(['journal', 'authors', 'section', 'issue.volume'])
            ->orderBy('published_at');

        // Sets are journals: set=journal:jcdms
        if ($set = $request->string('set')->toString()) {
            $slug = str_replace('journal:', '', $set);
            $query->whereHas('journal', fn ($j) => $j->where('slug', $slug));
        }

        // Selective harvesting. A harvester that has seen us before asks only for what
        // changed, and re-sending everything each time wastes both sides' bandwidth.
        if ($from = $request->string('from')->toString()) {
            $query->where('published_at', '>=', $from);
        }

        if ($until = $request->string('until')->toString()) {
            $query->where('published_at', '<=', $until);
        }

        $offset = max(0, (int) $request->string('resumptionToken')->toString());
        $total = (clone $query)->count();
        $articles = $query->skip($offset)->take(self::PAGE_SIZE)->get();

        if ($articles->isEmpty()) {
            return $this->error($request, 'noRecordsMatch', 'No published records match the request.');
        }

        $next = $offset + self::PAGE_SIZE;

        return $this->xml('oai.list-records', [
            'request' => $request,
            'articles' => $articles,
            'identifiersOnly' => $identifiersOnly,
            // An empty resumptionToken element is the spec's way of saying "that was the
            // last page" — omitting it entirely leaves the harvester unsure.
            'resumptionToken' => $next < $total ? (string) $next : null,
            'completeListSize' => $total,
            'cursor' => $offset,
        ]);
    }

    private function getRecord(Request $request): Response
    {
        if (($error = $this->requireOaiDc($request)) !== null) {
            return $error;
        }

        $identifier = $request->string('identifier')->toString();
        $slug = str_contains($identifier, ':') ? substr(strrchr($identifier, ':'), 1) : $identifier;

        $article = Article::query()
            ->published()
            ->with(['journal', 'authors', 'section', 'issue.volume'])
            ->where('slug', $slug)
            ->first();

        if ($article === null) {
            // idDoesNotExist, not a 404. A harvester asking for a withdrawn or unpublished
            // record must be told so in OAI's own vocabulary, or it retries forever.
            return $this->error($request, 'idDoesNotExist', "No published record for identifier '{$identifier}'.");
        }

        return $this->xml('oai.get-record', ['request' => $request, 'article' => $article]);
    }

    private function requireOaiDc(Request $request): ?Response
    {
        $prefix = $request->string('metadataPrefix')->toString();

        if ($prefix !== '' && $prefix !== 'oai_dc') {
            return $this->error(
                $request,
                'cannotDisseminateFormat',
                "Only 'oai_dc' is supported; '{$prefix}' is not."
            );
        }

        return null;
    }

    private function error(Request $request, string $code, string $message): Response
    {
        // OAI errors are still HTTP 200 with an <error> element. Returning a 4xx makes
        // harvesters treat the endpoint as broken rather than reading the reason.
        return $this->xml('oai.error', [
            'request' => $request,
            'code' => $code,
            'message' => $message,
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function xml(string $view, array $data): Response
    {
        return response(view($view, $data)->render(), 200, [
            'Content-Type' => 'text/xml; charset=utf-8',
        ]);
    }
}
