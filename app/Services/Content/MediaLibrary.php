<?php

declare(strict_types=1);

namespace App\Services\Content;

use App\Models\Article;
use App\Models\HomeSection;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\Media;
use App\Models\NewsItem;
use App\Models\Page;
use App\Models\ResearchTopic;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * The media library: the one way an image gets onto this site.
 *
 * THERE IS NO SVG. This is the decision most likely to be "helpfully" reverted, so: an SVG
 * is an XML document that may contain <script>, and a browser executes it when the file is
 * served as image/svg+xml FROM OUR OWN ORIGIN. Any editor with an upload button would then
 * have a stored-XSS vector on a trusted academic domain — the same hole MarkdownRenderer
 * exists to close, reopened through the back door. Logos go through a designer and land in
 * the build, not through this form.
 *
 * ALT TEXT IS REQUIRED, and its absence is not the same as its emptiness:
 *
 *   alt = 'Three researchers…'  a description
 *   alt = ''                    a DELIBERATE statement that the image is decorative, and a
 *                               screen reader should skip it entirely
 *   alt = NULL                  nobody has said
 *
 * NULL must not reach a public page. London Churchill College is a public-sector body and
 * is legally required to keep this site accessible (WCAG 2.1 AA); an image with no alt is a
 * hole in a screen reader's view of the page, and "we'll add it later" is how that hole
 * becomes permanent. So the form demands one, and "decorative" is a checkbox that writes
 * the empty string — an editor saying so on the record.
 */
final class MediaLibrary
{
    /** 8 MB. A hero image is not a manuscript. */
    public const MAX_KILOBYTES = 8192;

    /** @var list<string> */
    public const ALLOWED_MIMES = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    /** @var list<string> */
    public const ALLOWED_EXTENSIONS = ['jpeg', 'jpg', 'png', 'webp', 'avif'];

    /**
     * The upload rules. `image` alone is NOT enough — Laravel's `image` rule has an
     * `allow_svg` mode and its default set is wider than ours — so the extension list is
     * explicit, and store() checks the real mime type again afterwards.
     *
     * @return array<string, mixed>
     */
    public static function uploadRules(bool $decorative): array
    {
        return [
            'file' => [
                'required',
                'file',
                'image',
                'mimes:'.implode(',', self::ALLOWED_EXTENSIONS),
                'max:'.self::MAX_KILOBYTES,
            ],
            ...self::metadataRules($decorative),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function metadataRules(bool $decorative): array
    {
        return [
            'decorative' => ['boolean'],

            // NOT `nullable`. An image arrives with a description, or with an explicit
            // statement that it does not need one. There is no third option.
            'alt' => [Rule::requiredIf(! $decorative), 'nullable', 'string', 'max:500'],
            'caption' => ['nullable', 'string', 'max:500'],
            'credit' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'alt.required' => 'Alt text is required. If the image is purely decorative, tick '
                .'“this image is decorative” — that records an empty alt deliberately, which is '
                .'a different statement from leaving it blank.',
            'file.mimes' => 'Only JPEG, PNG, WebP and AVIF images can be uploaded. SVG is refused: '
                .'it is an executable document, and serving one from our own domain would let it '
                .'run script in a reader’s browser.',
            'file.image' => 'That file is not an image.',
            'file.max' => 'The image must be 8 MB or smaller.',
        ];
    }

    /**
     * Stores the file and writes the Media row.
     *
     * `$alt` of '' is legitimate and means decorative. NULL is not accepted here — the
     * caller has already validated that a human said something.
     */
    public function store(
        UploadedFile $file,
        string $alt,
        ?string $caption = null,
        ?string $credit = null,
        ?User $uploader = null,
    ): Media {
        $mime = (string) $file->getMimeType();

        // The extension said one thing; this asks the file itself. A .png that is really an
        // SVG is the whole reason this check is not just the validator's.
        if (! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => "That file is a {$mime}. Only JPEG, PNG, WebP and AVIF are accepted.",
            ]);
        }

        // BEFORE storeAs(), which moves the temp file out from under us.
        $dimensions = @getimagesize($file->getRealPath());

        $directory = 'media/'.now()->format('Y').'/'.now()->format('m');

        $name = Str::random(24).'.'.strtolower($file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'jpg');

        $path = $file->storeAs($directory, $name, ['disk' => 'public']);

        return Media::create([
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'size_bytes' => $file->getSize(),
            'width' => is_array($dimensions) ? $dimensions[0] : null,
            'height' => is_array($dimensions) ? $dimensions[1] : null,
            'alt' => $alt,
            'caption' => $caption,
            'credit' => $credit,
            'uploaded_by' => $uploader?->id,
        ]);
    }

    /**
     * EVERY place this image is used.
     *
     * The FKs are all nullOnDelete. That means the database would let the delete through
     * and SILENTLY blank the reference — the journal cover, the article hero, the homepage
     * band would simply lose their image, with no error anywhere and nobody told. The
     * schema's choice is right (a dangling id is worse), but it means the refusal has to
     * live here.
     *
     * @return list<string>
     */
    public function usages(Media $media): array
    {
        $usages = [];

        foreach (Journal::where('cover_media_id', $media->id)->get() as $journal) {
            $usages[] = "Journal cover — {$journal->title}";
        }

        foreach (Article::where('hero_media_id', $media->id)->get() as $article) {
            $usages[] = 'Article hero — '.str($article->title)->limit(60);
        }

        foreach (Issue::where('cover_media_id', $media->id)->with('volume')->get() as $issue) {
            $usages[] = "Issue cover — Vol {$issue->volume?->number}, No {$issue->number}";
        }

        foreach (NewsItem::where('media_id', $media->id)->get() as $news) {
            $usages[] = 'News — '.str($news->title)->limit(60);
        }

        foreach (ResearchTopic::where('media_id', $media->id)->get() as $topic) {
            $usages[] = 'Research Topic — '.str($topic->title)->limit(60);
        }

        foreach (Page::where('hero_media_id', $media->id)->get() as $page) {
            $usages[] = "Page hero — {$page->title}";
        }

        foreach (HomeSection::where('media_id', $media->id)->get() as $section) {
            $usages[] = "Homepage section — {$section->name}";
        }

        foreach (SiteSetting::where('type', 'media')->where('value', (string) $media->id)->get() as $setting) {
            $usages[] = "Site setting — {$setting->label}";
        }

        return $usages;
    }

    /**
     * The same question as usages(), asked once for the WHOLE library.
     *
     * The media grid needs to tell an editor which images are in use BEFORE they reach for the
     * delete button — a refusal after the click is a worse experience than an absent button.
     * Doing that with usages() per row would be eight queries per image; this is eight queries
     * for the entire library.
     *
     * @return array<int, list<string>> media id => what uses it
     */
    public function usageMap(): array
    {
        $map = [];

        $add = function (?int $mediaId, string $label) use (&$map): void {
            if ($mediaId !== null) {
                $map[$mediaId][] = $label;
            }
        };

        foreach (Journal::whereNotNull('cover_media_id')->get() as $journal) {
            $add($journal->cover_media_id, "Journal cover — {$journal->title}");
        }

        foreach (Article::whereNotNull('hero_media_id')->get() as $article) {
            $add($article->hero_media_id, 'Article hero — '.str($article->title)->limit(60));
        }

        foreach (Issue::whereNotNull('cover_media_id')->with('volume')->get() as $issue) {
            $add($issue->cover_media_id, "Issue cover — Vol {$issue->volume?->number}, No {$issue->number}");
        }

        foreach (NewsItem::whereNotNull('media_id')->get() as $news) {
            $add($news->media_id, 'News — '.str($news->title)->limit(60));
        }

        foreach (ResearchTopic::whereNotNull('media_id')->get() as $topic) {
            $add($topic->media_id, 'Research Topic — '.str($topic->title)->limit(60));
        }

        foreach (Page::whereNotNull('hero_media_id')->get() as $page) {
            $add($page->hero_media_id, "Page hero — {$page->title}");
        }

        foreach (HomeSection::whereNotNull('media_id')->get() as $section) {
            $add($section->media_id, "Homepage section — {$section->name}");
        }

        foreach (SiteSetting::where('type', 'media')->whereNotNull('value')->get() as $setting) {
            $add(is_numeric($setting->value) ? (int) $setting->value : null, "Site setting — {$setting->label}");
        }

        return $map;
    }

    /**
     * Deletes the row AND the file — but only if nothing points at it.
     *
     * @throws ValidationException naming every use, so the editor can go and unpick them
     */
    public function delete(Media $media): void
    {
        $usages = $this->usages($media);

        if ($usages !== []) {
            throw ValidationException::withMessages([
                'media' => array_merge(
                    ['This image is in use and was not deleted. Remove it from the following first, '
                        .'or the image would silently disappear from them with no error:'],
                    $usages,
                ),
            ]);
        }

        Storage::disk($media->disk)->delete($media->path);

        $media->delete();
    }

    /**
     * The library, in the shape every picker and grid renders.
     *
     * @return list<array<string, mixed>>
     */
    public function options(int $limit = 200): array
    {
        return Media::query()
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (Media $media): array => [
                'id' => $media->id,
                'url' => $media->url,
                'name' => $media->original_name,
                'alt' => $media->alt,
                'caption' => $media->caption,
                'credit' => $media->credit,
                'width' => $media->width,
                'height' => $media->height,
                'sizeBytes' => $media->size_bytes,
                'mimeType' => $media->mime_type,

                // The library shows this loudly. An image with no alt is one that must not
                // be put on a public page until somebody says something about it.
                'needsAltText' => $media->needsAltText(),
                'isDecorative' => $media->alt === '',
                'uploadedAt' => $media->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
