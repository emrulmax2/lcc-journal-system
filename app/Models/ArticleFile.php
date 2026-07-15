<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleFileType;
use Database\Factories\ArticleFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleFile extends Model
{
    /** @use HasFactory<ArticleFileFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => ArticleFileType::class,
            'size_bytes' => 'integer',
            'downloads_count' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
