<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A real newsletter list.
 *
 * The footer form currently takes an email address, runs a 900ms fake spinner, discards
 * the address, and tells the person "Thanks — check your inbox to confirm your
 * subscription." Nothing is sent. Nothing is stored. That is not an unfinished feature,
 * it is a false statement made to someone who just handed over personal data.
 *
 * DOUBLE OPT-IN is the design, not a nicety: under UK GDPR/PECR, an unconfirmed address
 * typed into a public box is not consent, and the message the UI already shows ("check
 * your inbox to confirm") is describing a confirmation step that must therefore exist.
 * Until `confirmed_at` is set, nothing may be sent to this address.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('confirmation_token', 64)->unique();

            $table->timestamp('confirmed_at')->nullable();      // NULL = not consented yet
            $table->timestamp('unsubscribed_at')->nullable();

            // For a subject-access or complaint request, "when and from where did they
            // sign up?" must be answerable. Kept minimal and never displayed.
            $table->ipAddress('signup_ip')->nullable();
            $table->timestamps();

            $table->index('confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_subscribers');
    }
};
