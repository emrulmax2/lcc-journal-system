<?php

declare(strict_types=1);

namespace App\Services\Crossref;

use App\Models\Article;
use App\Models\ArticleAuthor;
use App\Models\Issue;
use App\Models\Journal;
use DOMDocument;
use DOMElement;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Builds a Crossref metadata deposit against schema 5.3.1.
 *
 * WHY 5.3.1 AND NOT THE CURRENT 5.5.0 — this is a deliberate choice, not staleness.
 *
 * Crossref's 5.5.0 schema is authored in XSD 1.1. PHP's libxml only implements XSD 1.0,
 * so `DOMDocument::schemaValidate()` cannot check a document against it at all — it
 * reports "Unsupported version '1.1'" and gives up (and, on this build, segfaults trying).
 * That would leave us generating deposit XML that nothing verifies until Crossref itself
 * rejects it — and Crossref's rejection messages do not tell you which element is at
 * fault. Element ORDER is significant in this schema; a silently misordered document is
 * exactly the failure this project cannot afford.
 *
 * 5.3.1 is XSD 1.0, is still fully accepted by Crossref for deposits, and carries
 * everything we need: ORCID, structured affiliations, organization (corporate) authors
 * and citation lists. So we deposit against a schema we can PROVE a document conforms to
 * before we send it. The real XSD graph (21 files) is cached in tests/fixtures/crossref/
 * and CrossrefDepositTest validates every generated document against it.
 *
 * If you upgrade to 5.5.0, you must first replace the validation step with something that
 * actually implements XSD 1.1 (Xerces, or Crossref's own sandbox as an integration test).
 * Do not upgrade and simply delete the assertion.
 *
 * Everything identifying an article comes from Article::doi() and Article::landingUrl() —
 * the SAME accessors the landing page's citation meta tags use. That is deliberate: what
 * we advertise to Google Scholar and what we deposit at Crossref are then incapable of
 * disagreeing about what an article is or where it lives.
 */
final class CrossrefXmlBuilder
{
    private const NS = 'http://www.crossref.org/schema/5.3.1';

    private const XSI = 'http://www.w3.org/2001/XMLSchema-instance';

    private const SCHEMA_LOCATION = 'http://www.crossref.org/schema/5.3.1 https://data.crossref.org/schemas/crossref5.3.1.xsd';

    private const VERSION = '5.3.1';

    /**
     * @param  Collection<int, Article>  $articles
     * @param  Issue|null  $issue  NULL for continuous-publication journals, which have no issue.
     */
    public function build(Journal $journal, Collection $articles, ?Issue $issue, string $batchId): string
    {
        if (blank($journal->doi_prefix)) {
            // Refuse rather than deposit a malformed DOI. A DOI registered against the
            // wrong prefix cannot be recalled.
            throw new RuntimeException(
                "Journal '{$journal->slug}' has no Crossref DOI prefix. Nothing can be deposited until "
                .'Crossref issues one.'
            );
        }

        // Sections flagged doi_eligible = false get no DOI (front matter, for example).
        // Depositing them would mint identifiers for things that should not be citable.
        $depositable = $articles->filter(
            fn (Article $a) => $a->section?->doi_eligible ?? true
        )->values();

        if ($depositable->isEmpty()) {
            throw new RuntimeException('No DOI-eligible articles in this deposit.');
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $root = $doc->createElementNS(self::NS, 'doi_batch');
        $root->setAttributeNS('http://www.w3.org/2000/xmlns/', 'xmlns:xsi', self::XSI);
        $root->setAttributeNS(self::XSI, 'xsi:schemaLocation', self::SCHEMA_LOCATION);
        $root->setAttribute('version', self::VERSION);
        $doc->appendChild($root);

        $root->appendChild($this->head($doc, $journal, $batchId));
        $root->appendChild($this->body($doc, $journal, $depositable, $issue));

        return (string) $doc->saveXML();
    }

    private function head(DOMDocument $doc, Journal $journal, string $batchId): DOMElement
    {
        $head = $doc->createElementNS(self::NS, 'head');

        $head->appendChild($this->el($doc, 'doi_batch_id', $batchId));

        // Crossref uses this to order competing deposits for the same DOI: the HIGHEST
        // timestamp wins, regardless of arrival order. A redeposit correcting metadata
        // must therefore carry a larger value than the one it supersedes, or Crossref
        // silently keeps the old record and reports success.
        $head->appendChild($this->el($doc, 'timestamp', (string) now()->format('YmdHisv')));

        $depositor = $doc->createElementNS(self::NS, 'depositor');
        $depositor->appendChild($this->el($doc, 'depositor_name', (string) config('crossref.depositor_name')));
        $depositor->appendChild($this->el($doc, 'email_address', (string) config('crossref.depositor_email')));
        $head->appendChild($depositor);

        $head->appendChild($this->el($doc, 'registrant', $journal->publisher ?: (string) config('crossref.registrant')));

        return $head;
    }

    /** @param  Collection<int, Article>  $articles */
    private function body(DOMDocument $doc, Journal $journal, Collection $articles, ?Issue $issue): DOMElement
    {
        $body = $doc->createElementNS(self::NS, 'body');
        $journalEl = $doc->createElementNS(self::NS, 'journal');

        $journalEl->appendChild($this->journalMetadata($doc, $journal));

        // Omitted entirely for continuous publication. An empty <journal_issue> is not the
        // same as no issue, and the XSD rejects one.
        if ($issue !== null) {
            $journalEl->appendChild($this->journalIssue($doc, $issue));
        }

        foreach ($articles as $article) {
            $journalEl->appendChild($this->journalArticle($doc, $journal, $article));
        }

        $body->appendChild($journalEl);

        return $body;
    }

    private function journalMetadata(DOMDocument $doc, Journal $journal): DOMElement
    {
        $meta = $doc->createElementNS(self::NS, 'journal_metadata');
        $meta->appendChild($this->el($doc, 'full_title', $journal->title));

        if (filled($journal->abbreviation)) {
            $meta->appendChild($this->el($doc, 'abbrev_title', $journal->abbreviation));
        }

        // Omitted while the British Library has not issued one. A placeholder ISSN
        // ("0000-0000") would be deposited as fact and propagate to every index.
        if (filled($journal->issn_online)) {
            $issn = $this->el($doc, 'issn', $journal->issn_online);
            $issn->setAttribute('media_type', 'electronic');
            $meta->appendChild($issn);
        }

        if (filled($journal->issn_print)) {
            $issn = $this->el($doc, 'issn', $journal->issn_print);
            $issn->setAttribute('media_type', 'print');
            $meta->appendChild($issn);
        }

        return $meta;
    }

    private function journalIssue(DOMDocument $doc, Issue $issue): DOMElement
    {
        $el = $doc->createElementNS(self::NS, 'journal_issue');

        $el->appendChild($this->publicationDate(
            $doc,
            $issue->publication_date?->year ?? $issue->volume->year,
            $issue->publication_date?->month,
            $issue->publication_date?->day,
        ));

        $volume = $doc->createElementNS(self::NS, 'journal_volume');
        $volume->appendChild($this->el($doc, 'volume', (string) $issue->volume->number));
        $el->appendChild($volume);

        $el->appendChild($this->el($doc, 'issue', (string) $issue->number));

        return $el;
    }

    private function journalArticle(DOMDocument $doc, Journal $journal, Article $article): DOMElement
    {
        $el = $doc->createElementNS(self::NS, 'journal_article');
        $el->setAttribute('publication_type', 'full_text');

        // ---- ORDER IS FIXED BY THE XSD. Do not rearrange. ----

        $titles = $doc->createElementNS(self::NS, 'titles');
        $titles->appendChild($this->el($doc, 'title', $article->title));
        $el->appendChild($titles);

        if ($contributors = $this->contributors($doc, $article)) {
            $el->appendChild($contributors);
        }

        $publishedAt = $article->published_at;
        $el->appendChild($this->publicationDate(
            $doc,
            $publishedAt?->year ?? $article->issue?->volume?->year ?? (int) now()->year,
            $publishedAt?->month,
            $publishedAt?->day,
        ));

        if ($article->first_page !== null) {
            $pages = $doc->createElementNS(self::NS, 'pages');
            $pages->appendChild($this->el($doc, 'first_page', (string) $article->first_page));
            if ($article->last_page !== null) {
                $pages->appendChild($this->el($doc, 'last_page', (string) $article->last_page));
            }
            $el->appendChild($pages);
        }

        // doi_data: the identifier and the page it must resolve to, forever.
        $doiData = $doc->createElementNS(self::NS, 'doi_data');
        $doiData->appendChild($this->el($doc, 'doi', (string) $article->doi()));
        $doiData->appendChild($this->el($doc, 'resource', $article->landingUrl()));
        $el->appendChild($doiData);

        // Deposited references are what make Cited-by work: other publishers can only
        // link to our DOIs if we participate in the same citation graph.
        if ($journal->crossref_deposit_references && $article->references->isNotEmpty()) {
            $el->appendChild($this->citationList($doc, $article));
        }

        return $el;
    }

    private function contributors(DOMDocument $doc, Article $article): ?DOMElement
    {
        $contributors = $doc->createElementNS(self::NS, 'contributors');

        // A corporate author is an <organization>, NOT a <person_name>. Emitting the
        // CLIR editorial's author as a person would deposit a surname of "College" and a
        // given name of "Members of the Centre for Learning Innovation and Research
        // (CLIR), London Churchill" — which is what every naive implementation does, and
        // it is then permanently attached to the DOI.
        if ($article->hasCorporateAuthor()) {
            $org = $this->el($doc, 'organization', (string) $article->corporate_author);
            $org->setAttribute('sequence', 'first');
            $org->setAttribute('contributor_role', 'author');
            $contributors->appendChild($org);

            return $contributors;
        }

        if ($article->authors->isEmpty()) {
            return null;   // an empty <contributors> is invalid against the XSD
        }

        foreach ($article->authors->sortBy('sequence')->values() as $index => $author) {
            $contributors->appendChild($this->personName($doc, $author, $index === 0));
        }

        return $contributors;
    }

    private function personName(DOMDocument $doc, ArticleAuthor $author, bool $isFirst): DOMElement
    {
        $person = $doc->createElementNS(self::NS, 'person_name');

        // Crossref's model of authorship: exactly one "first" contributor, everyone else
        // "additional". This carries the author ORDER, which is meaningful in scholarship
        // and must match the order shown on the landing page.
        $person->setAttribute('sequence', $isFirst ? 'first' : 'additional');
        $person->setAttribute('contributor_role', 'author');

        $person->appendChild($this->el($doc, 'given_name', $author->given_name));
        $person->appendChild($this->el($doc, 'surname', $author->family_name));

        if (filled($author->affiliation)) {
            $affiliations = $doc->createElementNS(self::NS, 'affiliations');
            $institution = $doc->createElementNS(self::NS, 'institution');
            $institution->appendChild($this->el($doc, 'institution_name', (string) $author->affiliation));
            $affiliations->appendChild($institution);
            $person->appendChild($affiliations);
        }

        // Only where a REAL ORCID exists. A fabricated or guessed ORCID attributes this
        // work to a different, real, identifiable researcher — permanently, and across
        // every index that consumes Crossref.
        if ($author->hasOrcid()) {
            $person->appendChild($this->el($doc, 'ORCID', (string) $author->orcidUrl()));
        }

        return $person;
    }

    private function citationList(DOMDocument $doc, Article $article): DOMElement
    {
        $list = $doc->createElementNS(self::NS, 'citation_list');

        foreach ($article->references as $reference) {
            $citation = $doc->createElementNS(self::NS, 'citation');
            $citation->setAttribute('key', "ref{$reference->ordinal}");

            if (filled($reference->doi)) {
                $citation->appendChild($this->el($doc, 'doi', (string) $reference->doi));
            }

            $citation->appendChild($this->el($doc, 'unstructured_citation', $reference->raw_text));

            $list->appendChild($citation);
        }

        return $list;
    }

    private function publicationDate(DOMDocument $doc, int $year, ?int $month, ?int $day): DOMElement
    {
        $date = $doc->createElementNS(self::NS, 'publication_date');
        $date->setAttribute('media_type', 'online');

        // XSD order: month, day, year — NOT the order a human would write them.
        if ($month !== null) {
            $date->appendChild($this->el($doc, 'month', str_pad((string) $month, 2, '0', STR_PAD_LEFT)));
        }

        if ($day !== null) {
            $date->appendChild($this->el($doc, 'day', str_pad((string) $day, 2, '0', STR_PAD_LEFT)));
        }

        $date->appendChild($this->el($doc, 'year', (string) $year));

        return $date;
    }

    /** createElement() does NOT escape its value; createTextNode() does. */
    private function el(DOMDocument $doc, string $name, string $value): DOMElement
    {
        $el = $doc->createElementNS(self::NS, $name);
        $el->appendChild($doc->createTextNode($value));

        return $el;
    }
}
