import { useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import {
  AlertTriangle,
  Check,
  Info,
  Lock,
  Pencil,
  Trash2,
  Upload,
  X,
} from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { Banner, EmptyState, Field, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import { formatBytes } from '@/lib/admin'
import { formatDate } from '@/lib/format'
import type { ContentPage, MediaItem } from '@/lib/content'

/**
 * The media library.
 *
 * ALT TEXT IS REQUIRED ON UPLOAD, AND THE FORM WILL NOT SUBMIT WITHOUT IT.
 *
 * Not a nag, not a lint warning that ships anyway. An image with no alt text is a hole in a
 * screen reader's view of the page, and London Churchill College is a public-sector body that
 * is legally required to keep this site accessible. "We'll add it later" is how the hole
 * becomes permanent.
 *
 * "This image is decorative" writes an EMPTY STRING, which is a DIFFERENT STATEMENT from no
 * alt at all: '' tells a screen reader to skip the image, deliberately, on the record. NULL
 * says nobody has been asked. The library shows which of the three each image is.
 *
 * AN IMAGE THAT IS IN USE HAS NO DELETE BUTTON. Every FK pointing at media is nullOnDelete, so
 * the database would accept the delete and SILENTLY blank the reference — a journal cover would
 * simply vanish, with no error and nobody told. The server refuses it; this screen does not
 * offer it, and says what is using it instead.
 */

type LibraryItem = MediaItem & { usages: string[] }

type Props = ContentPage<{
  media: LibraryItem[]
  limits: { maxKilobytes: number; extensions: string[] }
}>

export default function ContentMedia({ media, limits, meta }: Props) {
  const [editing, setEditing] = useState<number | null>(null)

  const undescribed = media.filter((item) => item.needsAltText)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Media"
        description="Images we own and host. Every one of them carries the text a blind reader gets instead of the picture."
      >
        {undescribed.length > 0 && (
          <div className="mb-8">
            <Banner
              tone="danger"
              icon={AlertTriangle}
              title={`${undescribed.length} ${
                undescribed.length === 1 ? 'image has' : 'images have'
              } no alt text`}
            >
              Nobody has said what these images show. On a public page that is an accessibility
              failure, not a to-do — describe them, or mark them decorative if they genuinely
              carry no information.
            </Banner>
          </div>
        )}

        <div className="space-y-8">
          <UploadPanel limits={limits} />

          {media.length === 0 ? (
            <EmptyState
              icon={Upload}
              title="The library is empty"
              body="Every photo on this site is still a remote stock image — including one captioned as being from a study site it has nothing to do with. Upload something we own."
            />
          ) : (
            <Panel
              title={`${media.length} ${media.length === 1 ? 'image' : 'images'}`}
              description="Click an image to edit its alt text, caption and credit."
            >
              <RevealGroup
                as="ul"
                className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3"
                stagger={0.04}
              >
                {media.map((item) => (
                  <RevealItem
                    as="li"
                    key={item.id}
                    className="overflow-hidden rounded-xl border border-ink-200"
                  >
                    <img
                      src={item.url}
                      alt={item.alt ?? ''}
                      className="h-44 w-full bg-ink-100 object-cover"
                    />

                    <div className="p-4">
                      {editing === item.id ? (
                        <MetadataForm item={item} onDone={() => setEditing(null)} />
                      ) : (
                        <>
                          <p className="truncate text-sm font-medium text-ink-900" title={item.name}>
                            {item.name}
                          </p>

                          <p className="mt-1 text-xs text-ink-500">
                            {item.width && item.height ? `${item.width}×${item.height} · ` : ''}
                            {formatBytes(item.sizeBytes)}
                            {item.uploadedAt ? ` · ${formatDate(item.uploadedAt)}` : ''}
                          </p>

                          <div className="mt-3">
                            <AltLine item={item} />
                          </div>

                          {item.credit && (
                            <p className="mt-2 text-xs text-ink-600">Credit: {item.credit}</p>
                          )}

                          {item.usages.length > 0 && (
                            <div className="mt-3 rounded-md bg-ink-50 p-2.5">
                              <p className="flex items-center gap-1.5 text-xs font-semibold text-ink-800">
                                <Lock className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
                                In use — cannot be deleted
                              </p>
                              <ul className="mt-1.5 space-y-0.5 text-xs text-ink-600">
                                {item.usages.map((usage) => (
                                  <li key={usage}>{usage}</li>
                                ))}
                              </ul>
                            </div>
                          )}

                          <div className="mt-4 flex items-center gap-2">
                            <button
                              type="button"
                              onClick={() => setEditing(item.id)}
                              className="btn-secondary px-3 py-1.5 text-xs"
                            >
                              <Pencil className="h-3.5 w-3.5" aria-hidden="true" />
                              Edit details
                            </button>

                            {/* ABSENT for an image in use. Never a disabled button. */}
                            {item.usages.length === 0 && <DeleteButton item={item} />}
                          </div>
                        </>
                      )}
                    </div>
                  </RevealItem>
                ))}
              </RevealGroup>
            </Panel>
          )}
        </div>
      </ContentShell>
    </>
  )
}

/* -------------------------------- Upload form ------------------------------- */

function UploadPanel({ limits }: { limits: { maxKilobytes: number; extensions: string[] } }) {
  const form = useForm<{
    file: File | null
    alt: string
    caption: string
    credit: string
    decorative: boolean
  }>({
    file: null,
    alt: '',
    caption: '',
    credit: '',
    decorative: false,
  })

  const { data, setData, errors, processing, progress } = form

  // The submit is BLOCKED without alt text. The server refuses it too — this is so the editor
  // finds out before the upload, not after it.
  const missingAlt = !data.decorative && data.alt.trim() === ''

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    form.post('/admin/content/media', {
      preserveScroll: true,
      forceFormData: true,
      onSuccess: () => form.reset(),
    })
  }

  return (
    <Panel
      title="Upload an image"
      description={`JPEG, PNG, WebP or AVIF, up to ${Math.round(limits.maxKilobytes / 1024)} MB. SVG is refused — it is an executable document, and serving one from our own domain would let it run script in a reader's browser.`}
    >
      <form onSubmit={submit} className="grid gap-6 sm:grid-cols-2">
        <Field
          label="Image file"
          htmlFor="file"
          error={errors.file}
          hint={limits.extensions.map((extension) => `.${extension}`).join(' · ')}
          className="sm:col-span-2"
        >
          <input
            id="file"
            type="file"
            accept={limits.extensions.map((extension) => `.${extension}`).join(',')}
            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
            className={`${INPUT} cursor-pointer file:mr-3 file:cursor-pointer file:rounded-full file:border-0
                        file:bg-ink-100 file:px-4 file:py-1.5 file:text-sm file:font-semibold file:text-ink-800`}
            required
          />
        </Field>

        <Field
          label="Alt text"
          htmlFor="alt"
          error={errors.alt}
          hint="What the image shows, for someone who cannot see it. Not a caption — a replacement."
          className="sm:col-span-2"
        >
          <input
            id="alt"
            type="text"
            value={data.alt}
            onChange={(e) => setData('alt', e.target.value)}
            className={INPUT}
            disabled={data.decorative}
            required={!data.decorative}
            placeholder={
              data.decorative
                ? 'Decorative — a screen reader will skip this image'
                : 'Three researchers examining a soil core on a riverbank'
            }
          />
        </Field>

        <label className="flex cursor-pointer items-start gap-2 text-sm text-ink-800 sm:col-span-2">
          <input
            type="checkbox"
            checked={data.decorative}
            onChange={(e) => setData('decorative', e.target.checked)}
            className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
          />
          <span>
            This image is decorative
            <span className="mt-0.5 block text-ink-600">
              It carries no information a reader would miss. This records an EMPTY alt
              deliberately — which is a different statement from leaving it blank, and screen
              readers will skip the image entirely.
            </span>
          </span>
        </label>

        <Field label="Caption" htmlFor="caption" error={errors.caption} hint="Optional. Shown under the image.">
          <input
            id="caption"
            type="text"
            value={data.caption}
            onChange={(e) => setData('caption', e.target.value)}
            className={INPUT}
          />
        </Field>

        <Field
          label="Credit"
          htmlFor="credit"
          error={errors.credit}
          hint="Photographer or licence. If we cannot attribute it, we should not be publishing it."
        >
          <input
            id="credit"
            type="text"
            value={data.credit}
            onChange={(e) => setData('credit', e.target.value)}
            className={INPUT}
          />
        </Field>

        <div className="flex items-center gap-4 sm:col-span-2">
          <button
            type="submit"
            disabled={processing || missingAlt || data.file === null}
            className="btn-primary"
          >
            {processing ? <Spinner /> : <Upload className="h-4 w-4" aria-hidden="true" />}
            {processing ? 'Uploading…' : 'Upload image'}
          </button>

          {missingAlt && data.file !== null && (
            <p className="flex items-start gap-1.5 text-sm text-danger-700">
              <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              Alt text is required — or tick “this image is decorative”.
            </p>
          )}

          {progress && (
            <p className="text-sm text-ink-600" role="status">
              {progress.percentage}%
            </p>
          )}
        </div>
      </form>
    </Panel>
  )
}

/* ------------------------------- Metadata form ------------------------------ */

function MetadataForm({ item, onDone }: { item: LibraryItem; onDone: () => void }) {
  const form = useForm({
    alt: item.alt ?? '',
    caption: item.caption ?? '',
    credit: item.credit ?? '',
    decorative: item.isDecorative,
  })

  const { data, setData, errors, processing } = form

  const missingAlt = !data.decorative && data.alt.trim() === ''

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.patch(`/admin/content/media/${item.id}`, { preserveScroll: true, onSuccess: onDone })
  }

  return (
    <form onSubmit={submit} aria-label={`Edit ${item.name}`} className="space-y-4">
      <Field label="Alt text" htmlFor={`alt-${item.id}`} error={errors.alt}>
        <input
          id={`alt-${item.id}`}
          type="text"
          value={data.alt}
          onChange={(e) => setData('alt', e.target.value)}
          className={INPUT}
          disabled={data.decorative}
          required={!data.decorative}
        />
      </Field>

      <label className="flex cursor-pointer items-start gap-2 text-xs text-ink-800">
        <input
          type="checkbox"
          checked={data.decorative}
          onChange={(e) => setData('decorative', e.target.checked)}
          className="mt-0.5 h-4 w-4 cursor-pointer accent-brand-700"
        />
        This image is decorative — a screen reader will skip it
      </label>

      <Field label="Caption" htmlFor={`caption-${item.id}`} error={errors.caption}>
        <input
          id={`caption-${item.id}`}
          type="text"
          value={data.caption}
          onChange={(e) => setData('caption', e.target.value)}
          className={INPUT}
        />
      </Field>

      <Field label="Credit" htmlFor={`credit-${item.id}`} error={errors.credit}>
        <input
          id={`credit-${item.id}`}
          type="text"
          value={data.credit}
          onChange={(e) => setData('credit', e.target.value)}
          className={INPUT}
        />
      </Field>

      <div className="flex gap-2">
        <button
          type="submit"
          disabled={processing || missingAlt}
          className="btn-primary px-4 py-2 text-xs"
        >
          {processing ? <Spinner /> : <Check className="h-4 w-4" aria-hidden="true" />}
          Save
        </button>
        <button type="button" onClick={onDone} className="btn-ghost px-4 py-2 text-xs">
          <X className="h-4 w-4" aria-hidden="true" />
          Cancel
        </button>
      </div>
    </form>
  )
}

/* --------------------------------- Delete ---------------------------------- */

function DeleteButton({ item }: { item: LibraryItem }) {
  const [confirming, setConfirming] = useState(false)

  if (!confirming) {
    return (
      <button
        type="button"
        onClick={() => setConfirming(true)}
        aria-label={`Delete ${item.name}`}
        className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border
                   border-ink-300 text-danger-700 transition-colors duration-200
                   hover:border-danger-600 hover:bg-danger-50"
      >
        <Trash2 className="h-4 w-4" aria-hidden="true" />
      </button>
    )
  }

  return (
    <>
      <button
        type="button"
        onClick={() =>
          router.delete(`/admin/content/media/${item.id}`, { preserveScroll: true })
        }
        className="btn inline-flex bg-danger-700 px-3 py-1.5 text-xs text-white hover:bg-danger-800"
      >
        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
        Delete
      </button>
      <button
        type="button"
        onClick={() => setConfirming(false)}
        className="btn-ghost px-3 py-1.5 text-xs"
      >
        Cancel
      </button>
    </>
  )
}

/* --------------------------------- Alt line --------------------------------- */

/** Three states, and they are not the same. Icon AND word, never a colour on its own. */
function AltLine({ item }: { item: MediaItem }) {
  if (item.needsAltText) {
    return (
      <p className="flex items-start gap-1.5 text-xs font-semibold text-danger-800">
        <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
        No alt text — nobody has said what this shows
      </p>
    )
  }

  if (item.isDecorative) {
    return (
      <p className="flex items-start gap-1.5 text-xs text-ink-700">
        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
        Decorative — screen readers skip it, deliberately
      </p>
    )
  }

  return (
    <p className="flex items-start gap-1.5 text-xs text-ink-700">
      <Check className="mt-0.5 h-3.5 w-3.5 shrink-0 text-success-700" aria-hidden="true" />
      <span>{item.alt}</span>
    </p>
  )
}
