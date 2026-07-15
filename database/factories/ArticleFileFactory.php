<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ArticleFileType;
use App\Models\Article;
use App\Models\ArticleFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ArticleFile> */
class ArticleFileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'article_id' => Article::factory(),
            'type' => ArticleFileType::Pdf,
            'path' => 'articles/'.$this->faker->uuid().'.pdf',
            'label' => 'Full text (PDF)',
            'original_name' => 'manuscript.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => $this->faker->numberBetween(200_000, 4_000_000),
            'downloads_count' => 0,
        ];
    }
}
