<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubmissionFileType;
use Database\Factories\SubmissionFileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmissionFile extends Model
{
    /** @use HasFactory<SubmissionFileFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => SubmissionFileType::class,
            'version' => 'integer',
            'size_bytes' => 'integer',
        ];
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
