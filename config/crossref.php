<?php

declare(strict_types=1);

return [
    /*
     * DEFAULTS TO SANDBOX, deliberately.
     *
     * Flipping this to 'production' mints permanent, public identifiers and spends the
     * journal's Crossref deposit allowance. There is no undo, and a DOI registered
     * against the wrong metadata is corrected only by redeposit — never by deletion.
     * An environment that has not explicitly opted in must not be able to do that.
     */
    'endpoint' => env('CROSSREF_ENDPOINT', 'sandbox'),

    'endpoints' => [
        'sandbox' => 'https://test.crossref.org/servlet/deposit',
        'production' => 'https://doi.crossref.org/servlet/deposit',
    ],

    'status_endpoints' => [
        'sandbox' => 'https://test.crossref.org/servlet/submissionDownload',
        'production' => 'https://doi.crossref.org/servlet/submissionDownload',
    ],

    'depositor_name' => env('CROSSREF_DEPOSITOR_NAME', 'London Churchill College'),
    'depositor_email' => env('CROSSREF_DEPOSITOR_EMAIL', 'journal@lcc.ac.uk'),
    'registrant' => env('CROSSREF_REGISTRANT', 'London Churchill College'),

    /*
     * Fallback credentials. The real ones live per-journal on the journals table,
     * encrypted — a platform hosting several journals may hold several Crossref
     * memberships, and one journal's editor must not be able to deposit using another's
     * account.
     */
    'username' => env('CROSSREF_USERNAME'),
    'password' => env('CROSSREF_PASSWORD'),

    'timeout' => (int) env('CROSSREF_TIMEOUT', 60),
];
