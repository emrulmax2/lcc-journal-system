<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\HomeSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HomeSection extends Model
{
    /** @use HasFactory<HomeSectionFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sequence' => 'integer',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sequence');
    }

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }
}
