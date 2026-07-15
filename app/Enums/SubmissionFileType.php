<?php

declare(strict_types=1);

namespace App\Enums;

enum SubmissionFileType: string
{
    case Manuscript = 'manuscript';
    case CoverLetter = 'cover_letter';
    case Figure = 'figure';
    case Supplementary = 'supplementary';
    case Revision = 'revision';

    public function label(): string
    {
        return match ($this) {
            self::Manuscript => 'Manuscript',
            self::CoverLetter => 'Cover letter',
            self::Figure => 'Figure',
            self::Supplementary => 'Supplementary material',
            self::Revision => 'Revision',
        };
    }

    /** The types that carry the paper itself, and so become the Article's PDF on acceptance. */
    public function isManuscriptText(): bool
    {
        return $this === self::Manuscript || $this === self::Revision;
    }
}
