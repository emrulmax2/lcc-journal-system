<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class NewsletterSubscriber extends Model
{
    protected $guarded = ['id'];

    /** The token is a secret. It must never be serialised into a response. */
    protected $hidden = ['confirmation_token', 'signup_ip'];

    protected function casts(): array
    {
        return [
            'confirmed_at' => 'datetime',
            'unsubscribed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (NewsletterSubscriber $subscriber): void {
            $subscriber->confirmation_token ??= Str::random(64);
        });
    }

    /**
     * The ONLY people we may send to.
     *
     * An address typed into a public box is not consent under UK GDPR/PECR — it is a
     * claim by whoever typed it, and it may not be theirs. Nothing may be sent until
     * confirmed_at is set by someone clicking the link in their own inbox.
     */
    public function scopeMailable(Builder $query): Builder
    {
        return $query->whereNotNull('confirmed_at')->whereNull('unsubscribed_at');
    }

    public function isConfirmed(): bool
    {
        return $this->confirmed_at !== null && $this->unsubscribed_at === null;
    }
}
