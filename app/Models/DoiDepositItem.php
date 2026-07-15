<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositItemStatus;
use Database\Factories\DoiDepositItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DoiDepositItem extends Model
{
    /** @use HasFactory<DoiDepositItemFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => DepositItemStatus::class,
        ];
    }

    public function deposit(): BelongsTo
    {
        return $this->belongsTo(DoiDeposit::class, 'doi_deposit_id');
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(Article::class);
    }
}
