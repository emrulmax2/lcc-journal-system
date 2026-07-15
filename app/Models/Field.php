<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\FieldFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    /** @use HasFactory<FieldFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
        ];
    }

    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }
}
