<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Database\Factories\DoiDepositFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DoiDeposit extends Model
{
    /** @use HasFactory<DoiDepositFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    /**
     * Deliberately empty, and it must stay a conscious decision rather than an oversight.
     *
     * `response_body` is Crossref's raw reply. Crossref echoes the submitting account
     * back inside its submission reports, so this column can contain the journal's
     * Crossref username — and, on some malformed-request errors, the credentials as
     * they were sent. It is kept because the raw reply is the only real audit trail of
     * what happened to a deposit, but it MUST NOT be rendered to anyone below admin,
     * and must never be included in an API resource, log line or exception payload for
     * a non-admin. Redact before display.
     *
     * @var array<int, string>
     */
    protected $hidden = [];

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'attempts' => 'integer',
            'submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    /** NULL for continuous journals, which deposit per-article rather than per-issue. */
    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DoiDepositItem::class);
    }
}
