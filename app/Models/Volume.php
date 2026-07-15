<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VolumeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Volume extends Model
{
    /** @use HasFactory<VolumeFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'year' => 'integer',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function issues(): HasMany
    {
        return $this->hasMany(Issue::class)->orderBy('number');
    }
}
