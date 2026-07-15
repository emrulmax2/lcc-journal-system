<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Content\SiteContent;
use Illuminate\Database\Eloquent\Model;

class SiteSetting extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }

    protected static function booted(): void
    {
        // Flushed by the model, not by whoever remembers. A navbar that only updates on a
        // deploy is a CMS editors stop trusting.
        static::saved(fn () => SiteContent::flush());
        static::deleted(fn () => SiteContent::flush());
    }

    public static function value(string $key, mixed $default = null): mixed
    {
        return static::where('key', $key)->value('value') ?? $default;
    }
}
