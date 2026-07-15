<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Mail\NewsletterConfirmation;
use App\Models\NewsletterSubscriber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * A newsletter that actually subscribes you.
 *
 * The old footer form ran a 900ms fake spinner, discarded the address, and told the person
 * "Thanks — check your inbox to confirm your subscription." Nothing was stored and nothing
 * was sent. That is not an unfinished feature; it is a false statement made to someone who
 * has just handed over personal data.
 *
 * DOUBLE OPT-IN, because an address typed into a public box is not consent under UK
 * GDPR/PECR — anyone can type anyone's address. Nothing may be sent to it until the owner
 * clicks the link in their own inbox.
 */
class NewsletterController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email:rfc,dns', 'max:255'],
        ]);

        $email = strtolower(trim($validated['email']));

        $subscriber = NewsletterSubscriber::firstOrNew(['email' => $email]);

        // Already confirmed? Say the same thing we say to a new signup.
        //
        // NOT because it is tidier, but because a distinct "you are already subscribed"
        // message turns this public, unauthenticated form into an oracle that reveals
        // whether any given email address is on the list.
        if ($subscriber->exists && $subscriber->isConfirmed()) {
            return back()->with('success', 'Thanks — check your inbox to confirm your subscription.');
        }

        if (! $subscriber->exists) {
            $subscriber->confirmation_token = Str::random(64);
            $subscriber->signup_ip = $request->ip();
        }

        $subscriber->unsubscribed_at = null;   // re-subscribing after unsubscribing
        $subscriber->save();

        Mail::to($subscriber->email)->send(new NewsletterConfirmation($subscriber));

        // The message the UI already showed. Now it is true.
        return back()->with('success', 'Thanks — check your inbox to confirm your subscription.');
    }

    public function confirm(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)->first();

        if ($subscriber === null) {
            return redirect()->route('home')->with('error', 'That confirmation link is not valid.');
        }

        $subscriber->update(['confirmed_at' => $subscriber->confirmed_at ?? now(), 'unsubscribed_at' => null]);

        return redirect()->route('home')->with('success', 'You are subscribed. Thank you.');
    }

    public function unsubscribe(string $token): RedirectResponse
    {
        $subscriber = NewsletterSubscriber::where('confirmation_token', $token)->first();

        // "Unsubscribe in one click" is a promise the footer makes, and PECR requires it
        // to be easy. No login, no confirmation step, no "are you sure".
        $subscriber?->update(['unsubscribed_at' => now()]);

        return redirect()->route('home')->with('success', 'You have been unsubscribed.');
    }
}
