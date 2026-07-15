<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositItemStatus;
use App\Models\Article;
use App\Models\DoiDeposit;
use App\Models\DoiDepositItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DoiDepositItem>
 */
class DoiDepositItemFactory extends Factory
{
    protected $model = DoiDepositItem::class;

    /**
     * `doi` is the full string as SENT — an audit record, not a live reference. It must
     * survive the article being edited afterwards, so it is stored rather than derived.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'doi_deposit_id' => DoiDeposit::factory(),
            'article_id' => Article::factory(),
            'doi' => '10.12345/'.fake()->unique()->slug(2),
            'status' => DepositItemStatus::Pending,
        ];
    }

    /** Crossref can accept a batch and still reject the records inside it. */
    public function failed(string $message = 'Record not processed because submitted DOI was not found'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DepositItemStatus::Failed,
            'message' => $message,
        ]);
    }

    public function registered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DepositItemStatus::Registered,
        ]);
    }
}
