<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Support\AdminChrome;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Journal settings: masthead, ISSN, DOI prefix and pattern, licence, sections, Crossref.
 *
 * THE CROSSREF PASSWORD IS WRITE-ONLY. It is not in the props, not in a resource, not in a
 * hidden field, and there is no endpoint anywhere that returns it. The form shows whether
 * one is SET and offers to replace it; it cannot show it, and neither can a browser
 * extension, a proxy cache, an error tracker or a screenshot.
 *
 * Three independent layers already exist — `encrypted` cast, `$hidden` on the model,
 * JournalResource omitting it — and this controller is the fourth, because a credential
 * leaked into a JSON payload cannot be un-leaked. AdminTest asserts it never appears in any
 * admin response payload.
 *
 * THE ISSN AND DOI PREFIX ARE NULLABLE AND MUST STAY SO. They are NULL until the British
 * Library and Crossref issue them. A "0000-0000" placeholder would be deposited as fact.
 */
final class SettingsController extends Controller
{
    public function edit(Request $request, Journal $journal): Response
    {
        $this->authorize('manageSettings', $journal);

        $journal->load('sections');

        return Inertia::render('Admin/Settings', array_merge(
            AdminChrome::for($request->user(), $journal),
            [
                'settings' => [
                    'title' => $journal->title,
                    'abbreviation' => $journal->abbreviation,
                    'description' => $journal->description,
                    'aims_and_scope' => $journal->aims_and_scope,
                    'publisher' => $journal->publisher,
                    'principal_editor' => $journal->principal_editor,
                    'contact_email' => $journal->contact_email,

                    // NULL until issued. The UI says so in words rather than showing an
                    // empty box that looks like something went missing.
                    'issn_online' => $journal->issn_online,
                    'issn_print' => $journal->issn_print,
                    'doi_prefix' => $journal->doi_prefix,

                    'doi_suffix_pattern' => $journal->doi_suffix_pattern,
                    'doi_sequence_padding' => $journal->doi_sequence_padding,

                    'license' => $journal->license,
                    'license_holder' => $journal->license_holder,
                    'open_access' => (bool) $journal->open_access,

                    'crossref_username' => $journal->crossref_username,
                    'crossref_deposit_references' => (bool) $journal->crossref_deposit_references,

                    /*
                     * NOT the password. Whether one exists.
                     *
                     * This boolean is the entire contract with the frontend: it renders
                     * "•••• set" or "not set", and a field to REPLACE it. There is no shape
                     * of this page, and no dev-tools panel, that can read the value.
                     */
                    'crossref_password_set' => filled($journal->crossref_password),
                ],

                'sections' => $journal->sections->map(fn (JournalSection $section): array => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'sequence' => $section->sequence,
                    'is_active' => (bool) $section->is_active,

                    // Front matter gets no DOI; editorials do. CrossrefXmlBuilder skips a
                    // section with this off, so it silently decides what gets an identifier.
                    'doi_eligible' => (bool) $section->doi_eligible,
                    'articles' => $section->articles()->count(),
                ])->values()->all(),

                'meta' => [
                    'title' => 'Settings — '.$journal->title,
                    'description' => 'Masthead, ISSN, DOI prefix, licence, sections and Crossref credentials.',
                ],
            ],
        ));
    }

    public function update(Request $request, Journal $journal): RedirectResponse
    {
        $this->authorize('manageSettings', $journal);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'abbreviation' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'aims_and_scope' => ['nullable', 'string'],
            'publisher' => ['required', 'string', 'max:255'],
            'principal_editor' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],

            // An ISSN is eight digits with a hyphen; the check digit may be an X. NULL is
            // the correct value until the British Library issues one — never '0000-0000'.
            'issn_online' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}[\dX]$/'],
            'issn_print' => ['nullable', 'string', 'regex:/^\d{4}-\d{3}[\dX]$/'],

            // "10.12345". NULL until Crossref issues it, and canMintDois() is false until
            // then — which is what stops the app depositing garbage identifiers.
            'doi_prefix' => ['nullable', 'string', 'regex:/^10\.\d{4,9}$/'],

            'doi_suffix_pattern' => ['required', 'string', 'max:255'],
            'doi_sequence_padding' => ['required', 'integer', 'min:1', 'max:9'],

            'license' => ['nullable', 'string', 'max:255'],
            'license_holder' => ['nullable', 'string', 'max:255'],
            'open_access' => ['boolean'],

            'crossref_username' => ['nullable', 'string', 'max:255'],

            // WRITE-ONLY. Absent or empty means "leave it as it is" — it does NOT mean
            // "clear it", because an empty text field is what a browser autofill, a
            // half-finished edit and a keyboard accident all look like.
            'crossref_password' => ['nullable', 'string', 'max:255'],

            'crossref_deposit_references' => ['boolean'],

            'sections' => ['array', 'max:50'],
            'sections.*.id' => [
                'nullable',
                Rule::exists('journal_sections', 'id')->where('journal_id', $journal->id),
            ],
            'sections.*.name' => ['required', 'string', 'max:255'],
            'sections.*.is_active' => ['boolean'],
            'sections.*.doi_eligible' => ['boolean'],
        ], [
            'issn_online.regex' => 'An ISSN looks like 2755-0001. Leave it empty until the British Library issues one.',
            'issn_print.regex' => 'An ISSN looks like 2755-0001. Leave it empty until the British Library issues one.',
            'doi_prefix.regex' => 'A Crossref prefix looks like 10.12345. Leave it empty until Crossref issues yours — '
                .'a made-up prefix mints DOIs that resolve nowhere and cannot be withdrawn.',
        ]);

        $this->guardSuffixPattern($journal, $data['doi_suffix_pattern']);

        DB::transaction(function () use ($journal, $data): void {
            $journal->fill([
                'title' => $data['title'],
                'abbreviation' => $data['abbreviation'] ?? null,
                'description' => $data['description'] ?? null,
                'aims_and_scope' => $data['aims_and_scope'] ?? null,
                'publisher' => $data['publisher'],
                'principal_editor' => $data['principal_editor'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'issn_online' => $data['issn_online'] ?? null,
                'issn_print' => $data['issn_print'] ?? null,
                'doi_prefix' => $data['doi_prefix'] ?? null,
                'doi_suffix_pattern' => $data['doi_suffix_pattern'],
                'doi_sequence_padding' => $data['doi_sequence_padding'],
                'license' => $data['license'] ?? null,
                'license_holder' => $data['license_holder'] ?? null,
                'open_access' => (bool) ($data['open_access'] ?? true),
                'crossref_username' => $data['crossref_username'] ?? null,
                'crossref_deposit_references' => (bool) ($data['crossref_deposit_references'] ?? true),
            ]);

            // Only ever WRITTEN, and only when a new one was actually typed. The cast
            // encrypts it on the way in; nothing reads it back out to a response.
            if (filled($data['crossref_password'] ?? null)) {
                $journal->crossref_password = $data['crossref_password'];
            }

            $journal->save();

            $this->syncSections($journal, $data['sections'] ?? []);
        });

        return back()->with('success', 'Settings saved.');
    }

    /**
     * A pattern that cannot resolve is a pattern that mints malformed permanent identifiers.
     *
     * DoiSuffixGenerator refuses an unresolved token at generation time — but that is at the
     * moment of publication, with an editor watching a 500. Catching it here means a
     * continuous journal cannot be given `{volume}`/`{issue}` tokens it has no values for.
     */
    private function guardSuffixPattern(Journal $journal, string $pattern): void
    {
        if (! str_contains($pattern, '{seq}')) {
            throw ValidationException::withMessages([
                'doi_suffix_pattern' => 'The pattern must contain {seq}, or every article in an issue '
                    .'would derive the same DOI.',
            ]);
        }

        if (! $journal->usesIssues() && (str_contains($pattern, '{volume}') || str_contains($pattern, '{issue}'))) {
            throw ValidationException::withMessages([
                'doi_suffix_pattern' => 'This journal publishes continuously. It has no volumes or issues, '
                    .'so {volume} and {issue} can never resolve — the suffix would be minted with an empty component.',
            ]);
        }

        $unknown = [];
        preg_match_all('/\{([a-z]+)\}/', $pattern, $matches);

        foreach ($matches[1] ?? [] as $token) {
            if (! in_array($token, ['journal', 'volume', 'issue', 'year', 'seq'], true)) {
                $unknown[] = $token;
            }
        }

        if ($unknown !== []) {
            throw ValidationException::withMessages([
                'doi_suffix_pattern' => 'Unknown token{'.implode('}, {', $unknown).'}. '
                    .'The generator knows {journal}, {volume}, {issue}, {year} and {seq}.',
            ]);
        }
    }

    /** @param  array<int, array<string, mixed>>  $sections */
    private function syncSections(Journal $journal, array $sections): void
    {
        $kept = [];

        foreach (array_values($sections) as $i => $section) {
            $attributes = [
                'name' => $section['name'],
                'sequence' => $i + 1,
                'is_active' => (bool) ($section['is_active'] ?? true),
                'doi_eligible' => (bool) ($section['doi_eligible'] ?? true),
            ];

            $model = filled($section['id'] ?? null)
                ? $journal->sections()->whereKey($section['id'])->first()
                : null;

            if ($model instanceof JournalSection) {
                $model->update($attributes);
            } else {
                $model = $journal->sections()->create($attributes);
            }

            $kept[] = $model->id;
        }

        // A section still carrying articles is NOT deleted — the FK is nullOnDelete, so
        // removing it would quietly strip the article type off every article filed under it,
        // and citation_journal_title's companion "type" would vanish from the archive.
        $removable = $journal->sections()
            ->whereKeyNot($kept ?: [0])
            ->withCount('articles')
            ->get()
            ->filter(fn (JournalSection $section): bool => $section->articles_count === 0);

        foreach ($removable as $section) {
            $section->delete();
        }
    }
}
