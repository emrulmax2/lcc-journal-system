<?php

declare(strict_types=1);

namespace App\Services\Crossref;

use App\Models\Journal;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Posts a deposit to Crossref, and polls for its real outcome.
 *
 * TWO THINGS THAT SURPRISE PEOPLE, both encoded here:
 *
 * 1. A 200 ON THE POST IS NOT REGISTRATION. Crossref processes deposits asynchronously.
 *    The POST returns success as soon as the XML is *accepted for processing*. Treating
 *    that as confirmation is how a journal ends up believing it has DOIs that Crossref
 *    in fact rejected. Only the submission report says whether a DOI is live.
 *
 * 2. DEPOSITS ARE IDEMPOTENT ON THE DOI. Redepositing the same DOI UPDATES the record;
 *    it does not create a duplicate and it does not error. Retry is therefore ALWAYS
 *    safe. Do not add an "already deposited?" guard — it would block the legitimate and
 *    necessary case of correcting metadata on a published article. (The one thing that
 *    must increase on a redeposit is the <timestamp> in the head, because Crossref
 *    resolves competing deposits by taking the highest. CrossrefXmlBuilder stamps it.)
 */
// Deliberately not `final`: this is the boundary to an external service we do not
// control, and the outage behaviour ("Crossref is down — do the pages stay live?") is
// something we must be able to simulate by substituting it. A final class here would
// force the outage test to fake the HTTP client instead, which tests Laravel rather
// than us.
class CrossrefDepositor
{
    public function deposit(Journal $journal, string $xml, string $batchId): Response
    {
        [$username, $password] = $this->credentials($journal);

        $response = Http::timeout((int) config('crossref.timeout'))
            ->asMultipart()
            ->attach('fname', $xml, "{$batchId}.xml")
            ->post($this->endpoint(), [
                ['name' => 'operation', 'contents' => 'doMDUpload'],
                ['name' => 'login_id', 'contents' => $username],
                ['name' => 'login_passwd', 'contents' => $password],
            ]);

        return $response;
    }

    /**
     * Fetch the submission report — the only thing that actually says whether each DOI
     * registered. Returns Crossref's raw XML report.
     */
    public function fetchSubmissionReport(Journal $journal, string $batchId): string
    {
        [$username, $password] = $this->credentials($journal);

        $response = Http::timeout((int) config('crossref.timeout'))
            ->asMultipart()
            ->post($this->statusEndpoint(), [
                ['name' => 'usr', 'contents' => $username],
                ['name' => 'pwd', 'contents' => $password],
                ['name' => 'doi_batch_id', 'contents' => $batchId],
                ['name' => 'type', 'contents' => 'result'],
            ]);

        return $response->body();
    }

    /**
     * @return array{0: string, 1: string}
     *
     * @throws RuntimeException when no credentials exist — better than posting an empty
     *                          password and reading Crossref's auth failure as a network
     *                          problem.
     */
    private function credentials(Journal $journal): array
    {
        $username = $journal->crossref_username ?: config('crossref.username');
        $password = $journal->crossref_password ?: config('crossref.password');

        if (blank($username) || blank($password)) {
            throw new RuntimeException(
                "No Crossref credentials for journal '{$journal->slug}'. Set them in the journal's "
                .'settings, or as CROSSREF_USERNAME / CROSSREF_PASSWORD.'
            );
        }

        return [(string) $username, (string) $password];
    }

    public function endpoint(): string
    {
        return $this->resolve('crossref.endpoints');
    }

    private function statusEndpoint(): string
    {
        return $this->resolve('crossref.status_endpoints');
    }

    public function isProduction(): bool
    {
        return config('crossref.endpoint') === 'production';
    }

    private function resolve(string $key): string
    {
        $endpoints = (array) config($key);
        $selected = (string) config('crossref.endpoint');

        // An unrecognised value falls back to SANDBOX, never to production. A typo in an
        // env file must not be able to spend money.
        return $endpoints[$selected] ?? $endpoints['sandbox'];
    }
}
