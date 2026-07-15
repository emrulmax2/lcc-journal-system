<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Journal;

/**
 * Crossref ECHOES THE SUBMITTING ACCOUNT BACK.
 *
 * `doi_deposits.response_body` is Crossref's raw reply, and its submission reports quote
 * the login they were sent — on some malformed-request errors, the password with it. The
 * raw reply is kept because it is the only real audit trail of what happened to a deposit,
 * but it is NOT safe to ship verbatim to a browser: an editor's DevTools, a proxy cache
 * and an error tracker would all then hold the journal's Crossref credentials.
 *
 * So the deposit log renders Crossref's actual words — which is the whole point of the
 * screen — with the credentials struck out of them first. See DoiDeposit::$hidden, whose
 * docblock demands exactly this.
 */
final class DepositRedactor
{
    public static function scrub(?string $text, Journal $journal): ?string
    {
        if (blank($text)) {
            return $text;
        }

        $secrets = array_filter([
            // Read through the model, so the `encrypted` cast has already decrypted it —
            // the ciphertext is not what would appear in Crossref's reply.
            $journal->crossref_password,
            $journal->crossref_username,
            config('crossref.password'),
            config('crossref.username'),
        ], fn ($secret): bool => filled($secret) && strlen((string) $secret) > 3);

        return str_replace(
            array_map(strval(...), $secrets),
            '[redacted]',
            (string) $text,
        );
    }
}
