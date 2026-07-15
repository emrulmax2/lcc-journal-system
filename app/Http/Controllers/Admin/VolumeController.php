<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\Volume;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Volumes exist only for issue-based journals. A continuous journal has none, and asking
 * for its volumes is a 404 rather than an empty list — see IssueController.
 *
 * A volume's NUMBER is load-bearing: it renders into every DOI suffix of every article in
 * every issue it contains ({journal}.v{volume}i{issue}.{seq}). Renumbering a volume that
 * already has published articles would therefore change what their DOIs *mean* while the
 * minted identifiers stay as they are. The suffix is stored, not recomputed, so nothing
 * breaks — but the archive would start lying, so a volume with a published issue is not
 * renumberable.
 */
final class VolumeController extends Controller
{
    public function store(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('manageIssues', $journal);

        abort_unless($journal->usesIssues(), 404);

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        if ($journal->volumes()->where('number', $data['number'])->exists()) {
            throw ValidationException::withMessages([
                'number' => "This journal already has a volume {$data['number']}.",
            ]);
        }

        $journal->volumes()->create($data);

        return back()->with('success', "Volume {$data['number']} created.");
    }

    public function update(Request $request, Volume $volume): RedirectResponse
    {
        $journal = $volume->journal;

        $this->authorize('manageIssues', $journal);

        $data = $request->validate([
            'number' => ['required', 'integer', 'min:1', 'max:999'],
            'year' => ['required', 'integer', 'min:1900', 'max:2100'],
        ]);

        $hasPublishedIssue = $volume->issues()->published()->exists();

        if ($hasPublishedIssue && $volume->number !== $data['number']) {
            throw ValidationException::withMessages([
                'number' => 'This volume contains a published issue. Its number is part of every '
                    .'DOI in it and of every citation already made to them.',
            ]);
        }

        $duplicate = $journal->volumes()
            ->where('number', $data['number'])
            ->whereKeyNot($volume->id)
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'number' => "This journal already has a volume {$data['number']}.",
            ]);
        }

        $volume->update($data);

        return back()->with('success', 'Volume updated.');
    }
}
