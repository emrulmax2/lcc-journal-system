<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Field;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Subject fields — "Development & Management", "Public Policy & Governance", …
 *
 * These ARE the filter chips on /journals, and the chip label is the field's own name. The
 * table has existed since the first migration and the public page has always read it; there
 * has simply never been a way to edit it outside a seeder, so the chips were effectively
 * hardcoded-by-accident. This is that missing screen.
 *
 * It lives under `content` (the `manage-site-content` gate) rather than under a journal,
 * for the reason that whole section exists: a field spans journals. "Economics & Finance"
 * is not JCD&MS's — JCD&MS is IN it. There is no per-journal answer to "may you rename
 * Economics & Finance", so it cannot be a JournalPolicy question.
 *
 * THE SLUG IS NOT FROZEN. Fields are not articles: no DOI resolves to one and no citation
 * points at one, so renaming is allowed. Article and journal URLs are the permanent ones —
 * see the Article observer.
 */
final class FieldController extends Controller
{
    public function index(): Response
    {
        $fields = Field::query()
            ->withCount('journals')
            ->orderBy('sequence')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/Content/Fields', [
            'fields' => $fields->map(fn (Field $field): array => [
                'id' => $field->id,
                'name' => $field->name,
                'slug' => $field->slug,
                'sequence' => $field->sequence,

                // Drives the delete guard, and the words on the button. A field with
                // journals in it cannot be removed without silently un-classifying them.
                'journalCount' => $field->journals_count,
            ])->values()->all(),

            'meta' => [
                'title' => 'Subject fields — content',
                'description' => 'The filter chips on the journals page.',
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        Field::create([
            'name' => $data['name'],
            'slug' => $this->slug($data),
            // New fields go to the end rather than to 0, where they would silently displace
            // the first chip.
            'sequence' => $data['sequence'] ?? ((int) Field::max('sequence') + 1),
        ]);

        return back()->with('success', "“{$data['name']}” added.");
    }

    public function update(Request $request, Field $field): RedirectResponse
    {
        $data = $this->validated($request, $field);

        $field->update([
            'name' => $data['name'],
            'slug' => $this->slug($data, $field),
            'sequence' => $data['sequence'] ?? $field->sequence,
        ]);

        return back()->with('success', "“{$field->name}” updated.");
    }

    /**
     * Refused while any journal is in this field.
     *
     * journals.field_id is a FK; deleting under it would either fail at the database or —
     * worse, depending on the constraint — null out the classification of every journal in
     * it, which is a silent data loss the editor did not ask for and cannot see. Reassign
     * first, on the journal's own settings screen.
     */
    public function destroy(Field $field): RedirectResponse
    {
        $count = $field->journals()->count();

        if ($count > 0) {
            throw ValidationException::withMessages([
                'field' => "“{$field->name}” still has {$count} ".Str::plural('journal', $count)
                    .' in it. Move them to another field first — deleting it would leave them unclassified.',
            ]);
        }

        $name = $field->name;
        $field->delete();

        return back()->with('success', "“{$name}” deleted.");
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Field $field = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],

            // Optional: derived from the name when absent. Unique because it is the URL
            // query value the chips filter on.
            'slug' => [
                'nullable', 'string', 'max:140', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('fields', 'slug')->ignore($field?->id),
            ],
            'sequence' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ], [
            'slug.regex' => 'A slug is lower-case words joined by hyphens, e.g. economics-finance.',
        ]);
    }

    /** @param  array<string, mixed>  $data */
    private function slug(array $data, ?Field $field = null): string
    {
        if (filled($data['slug'] ?? null)) {
            return $data['slug'];
        }

        // Str::slug drops the ampersand: "Economics & Finance" -> "economics-finance".
        $slug = Str::slug($data['name']);

        return $slug !== '' ? $slug : ($field?->slug ?? Str::slug($data['name'].'-field'));
    }
}
