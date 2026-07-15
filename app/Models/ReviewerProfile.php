<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ReviewerProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReviewerProfile extends Model
{
    /** @use HasFactory<ReviewerProfileFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expertise' => 'array',
            'available' => 'boolean',
            'max_concurrent_reviews' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
