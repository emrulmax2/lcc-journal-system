<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\DepositStatus;
use App\Models\DoiDeposit;
use App\Models\Journal;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DoiDeposit>
 */
class DoiDepositFactory extends Factory
{
    protected $model = DoiDeposit::class;

    /**
     * SANDBOX by default. A factory whose default is `production` is a factory that makes
     * "did we really deposit against the live endpoint?" an easy thing to get wrong in a
     * test fixture, and the whole point of the Registrations screen is that the endpoint is
     * never ambiguous.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_id' => Journal::factory(),
            'issue_id' => null,
            'batch_id' => (string) Str::uuid(),
            'status' => DepositStatus::Queued,
            'endpoint' => 'sandbox',
            'attempts' => 0,
        ];
    }

    /** A deposit Crossref rejected, with Crossref's own words on it. Retryable — always. */
    public function failed(string $message = 'Error: DOI prefix 10.12345 is not owned by this account'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DepositStatus::Failed,
            'error_message' => $message,
            'attempts' => 1,
            'submitted_at' => now(),
        ]);
    }

    public function production(): static
    {
        return $this->state(fn (array $attributes): array => [
            'endpoint' => 'production',
        ]);
    }
}
