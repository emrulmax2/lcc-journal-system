<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscriber;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The newsletter list. READ-ONLY, and deliberately so.
 *
 * THE CONFIRMATION TOKEN IS NEVER SENT ANYWHERE. It is in the model's $hidden, and this
 * controller also builds every row by hand rather than serialising the model — because
 * $hidden protects a `toArray()`, and one `->toJson()` on a raw query result, one debug dump,
 * one "just add the id" change six months from now, and it is in a page prop. The token is a
 * capability: whoever holds it can confirm or unsubscribe that address without ever proving
 * they own the inbox. A leaked list of tokens is a leaked list of consents.
 *
 * THE EXPORT IS CONFIRMED SUBSCRIBERS ONLY — NewsletterSubscriber::mailable().
 *
 * An address typed into a public box is not consent under UK GDPR/PECR. It is a claim by
 * whoever typed it, and it may not be theirs. Exporting the unconfirmed ones into a mail tool
 * is precisely how that claim becomes an unlawful send, and the CSV is the moment it would
 * happen — so the scope is applied here, in the export, not left to whoever opens the file.
 *
 * There is no "add subscriber" and no "confirm on their behalf". Both would be a person other
 * than the subscriber consenting for them.
 */
final class NewsletterController extends Controller
{
    public function index(): Response
    {
        $subscribers = NewsletterSubscriber::query()
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Admin/Content/Newsletter', [
            'subscribers' => $subscribers->map(fn (NewsletterSubscriber $s): array => [
                'id' => $s->id,
                'email' => $s->email,
                'status' => $this->status($s),
                'confirmedAt' => $s->confirmed_at?->toIso8601String(),
                'unsubscribedAt' => $s->unsubscribed_at?->toIso8601String(),
                'signedUpAt' => $s->created_at?->toIso8601String(),
                // No confirmation_token. No signup_ip either — it exists to answer a
                // subject-access request, not to be browsed.
            ])->values()->all(),

            'counts' => [
                'confirmed' => NewsletterSubscriber::query()->mailable()->count(),
                'unconfirmed' => NewsletterSubscriber::whereNull('confirmed_at')
                    ->whereNull('unsubscribed_at')
                    ->count(),
                'unsubscribed' => NewsletterSubscriber::whereNotNull('unsubscribed_at')->count(),
                'total' => $subscribers->count(),
            ],

            'meta' => [
                'title' => 'Newsletter — content',
                'description' => 'Subscribers, and who may lawfully be sent to.',
            ],
        ]);
    }

    /** CONFIRMED ONLY. The scope is the point of the endpoint. */
    public function export(): StreamedResponse
    {
        $filename = 'newsletter-confirmed-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function (): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['email', 'confirmed_at']);

            NewsletterSubscriber::query()
                ->mailable()   // confirmed_at NOT NULL, unsubscribed_at NULL. Nothing else.
                ->orderBy('email')
                ->chunk(500, function ($subscribers) use ($handle): void {
                    foreach ($subscribers as $subscriber) {
                        fputcsv($handle, [
                            $subscriber->email,
                            $subscriber->confirmed_at?->toIso8601String(),
                        ]);
                    }
                });

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function status(NewsletterSubscriber $subscriber): string
    {
        return match (true) {
            $subscriber->unsubscribed_at !== null => 'unsubscribed',
            $subscriber->confirmed_at !== null => 'confirmed',
            default => 'unconfirmed',
        };
    }
}
