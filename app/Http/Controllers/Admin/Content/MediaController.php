<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Content;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Services\Content\MediaLibrary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The media library.
 *
 * This screen exists to end the Unsplash dependency: every photo on the site is currently a
 * remote stock image, and the article page captions one of them "Figure 1. Representative
 * imagery from the study site." It is not. It is a stranger's laboratory, and on a published
 * paper that is a fabrication, not a placeholder.
 *
 * TWO RULES ARE ENFORCED HERE AND NOWHERE ELSE.
 *
 * 1. ALT TEXT IS REQUIRED ON UPLOAD. Not "encouraged", not "a to-do". An image with no alt is
 *    a hole in a screen reader's view of the page, and London Churchill College is legally
 *    required to keep this site accessible. "Decorative" is a checkbox, and it writes an EMPTY
 *    STRING — a deliberate statement that a screen reader should skip the image, which is a
 *    different thing from NULL ("nobody has said"). See Media::needsAltText().
 *
 * 2. MEDIA IN USE CANNOT BE DELETED. Every FK pointing at media is nullOnDelete, so the
 *    database would happily accept the delete and SILENTLY blank the reference — the journal
 *    cover, the article hero, the homepage band would just lose their image, with no error and
 *    nobody told. MediaLibrary::usages() is what makes that impossible, and the refusal NAMES
 *    every use so the editor can go and unpick them.
 */
final class MediaController extends Controller
{
    public function index(MediaLibrary $library): Response
    {
        $usages = $library->usageMap();

        return Inertia::render('Admin/Content/Media', [
            // WHAT USES EACH IMAGE, sent with the grid rather than discovered on refusal. The
            // delete control is then simply absent for one that is in use — the same rule as a
            // system page. A button that exists in order to refuse you teaches editors that the
            // refusal is a bug.
            'media' => array_map(
                fn (array $item): array => [...$item, 'usages' => $usages[$item['id']] ?? []],
                $library->options(500),
            ),

            'limits' => [
                'maxKilobytes' => MediaLibrary::MAX_KILOBYTES,
                'extensions' => MediaLibrary::ALLOWED_EXTENSIONS,
            ],

            'meta' => [
                'title' => 'Media — content',
                'description' => 'Images we own, with the alt text a screen reader will read.',
            ],
        ]);
    }

    public function store(Request $request, MediaLibrary $library): RedirectResponse
    {
        $decorative = $request->boolean('decorative');

        $data = $request->validate(
            MediaLibrary::uploadRules($decorative),
            MediaLibrary::messages(),
        );

        $media = $library->store(
            $request->file('file'),
            // '' is the decorative statement. It is NOT the same as never having been asked.
            $decorative ? '' : (string) $data['alt'],
            $data['caption'] ?? null,
            $data['credit'] ?? null,
            $request->user(),
        );

        return back()->with('success', "“{$media->original_name}” uploaded.");
    }

    public function update(Request $request, Media $media): RedirectResponse
    {
        $decorative = $request->boolean('decorative');

        $data = $request->validate(
            MediaLibrary::metadataRules($decorative),
            MediaLibrary::messages(),
        );

        // The alt text is what a blind reader gets INSTEAD of the image, so it cannot be
        // cleared back to NULL here either — the same rule as on upload.
        $media->update([
            'alt' => $decorative ? '' : (string) $data['alt'],
            'caption' => $data['caption'] ?? null,
            'credit' => $data['credit'] ?? null,
        ]);

        return back()->with('success', 'Image details saved.');
    }

    public function destroy(Media $media, MediaLibrary $library): RedirectResponse
    {
        // Throws a ValidationException naming every use. The FKs are nullOnDelete, so without
        // this the delete would succeed and quietly blank somebody's cover image.
        $library->delete($media);

        return back()->with('success', 'Image deleted.');
    }
}
