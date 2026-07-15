<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * The editor's decision. Deliberately a SEPARATE enum from Recommendation even though the
 * four cases coincide today: a reviewer's recommendation is advice, an editor's decision
 * is an act with consequences (it closes the round, and on Accept it mints an Article).
 * Collapsing the two would make it possible to pass one where the other is meant.
 */
enum DecisionType: string
{
    case Accept = 'accept';
    case MinorRevision = 'minor_revision';
    case MajorRevision = 'major_revision';
    case Reject = 'reject';

    public function label(): string
    {
        return match ($this) {
            self::Accept => 'Accept',
            self::MinorRevision => 'Minor revision',
            self::MajorRevision => 'Major revision',
            self::Reject => 'Reject',
        };
    }

    /** The status a submission lands in once this decision is recorded. */
    public function resultingStatus(): SubmissionStatus
    {
        return match ($this) {
            self::Accept => SubmissionStatus::Accepted,
            self::Reject => SubmissionStatus::Rejected,
            self::MinorRevision, self::MajorRevision => SubmissionStatus::RevisionsRequested,
        };
    }

    /** Accept sends the manuscript to Production; anything else sends it back to the author. */
    public function resultingStage(): SubmissionStage
    {
        return $this === self::Accept
            ? SubmissionStage::Production
            : SubmissionStage::Decision;
    }
}
