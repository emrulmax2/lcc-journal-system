<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ArticleMetricDailyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleMetricDaily extends Model
{
    /** @use HasFactory<ArticleMetricDailyFactory> */
    use HasFactory;

    /**
     * Set explicitly. Eloquent's pluraliser would resolve this class to
     * `article_metric_dailies`, which is not the table the migration created.
     */
    protected $table = 'article_metric_daily';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'views' => 'integer',
            'downloads' => 'integer',
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
