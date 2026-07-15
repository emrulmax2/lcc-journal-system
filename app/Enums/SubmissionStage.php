<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The pipeline the Dashboard draws as five bars. The integers are the contract: the
 * component indexes PIPELINE_STAGES with this value, so 0-4 and these labels, in this
 * order, are not ours to change.
 */
enum SubmissionStage: int
{
    case Submitted = 0;
    case EditorCheck = 1;
    case PeerReview = 2;
    case Decision = 3;
    case Production = 4;

    public function label(): string
    {
        return match ($this) {
            self::Submitted => 'Submitted',
            self::EditorCheck => 'Editor check',
            self::PeerReview => 'Peer review',
            self::Decision => 'Decision',
            self::Production => 'Production',
        };
    }
}
