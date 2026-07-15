<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The vocabulary is FIXED BY THE FRONTEND. label() returns the exact string Dashboard.tsx
 * keys its STATUS_STYLE map on — rename one of these and the pill silently falls back to
 * a neutral grey, which is how a rejected manuscript comes to look like a routine one.
 */
enum SubmissionStatus: string
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case RevisionsRequested = 'revisions_requested';
    case Accepted = 'accepted';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Submitted => 'Submitted',
            self::UnderReview => 'Under Review',
            self::RevisionsRequested => 'Revisions Requested',
            self::Accepted => 'Accepted',
            self::Rejected => 'Rejected',
        };
    }

    /** In flight: with an editor or with reviewers, and not yet decided either way. */
    public function isActive(): bool
    {
        return match ($this) {
            self::Submitted, self::UnderReview, self::RevisionsRequested => true,
            default => false,
        };
    }

    /** A draft is invisible to editors. Nothing goes to an editor until the author sends it. */
    public function isVisibleToEditors(): bool
    {
        return $this !== self::Draft;
    }

    public function isDecided(): bool
    {
        return $this === self::Accepted || $this === self::Rejected;
    }
}
