<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    protected $table = 'media';   // Laravel would pluralise this to "medias"

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    /** @var list<string> */
    protected $appends = ['url'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * NULL alt is not the same as empty alt.
     *
     * '' means "decorative, deliberately" — a screen reader should skip it.
     * NULL means nobody has said, and shipping that to a public page on a site an academic
     * institution is legally required to keep accessible is a real failure, not a to-do.
     */
    public function needsAltText(): bool
    {
        return $this->alt === null && str_starts_with($this->mime_type, 'image/');
    }
}
