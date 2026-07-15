import { useState } from 'react'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { AlertTriangle, ArrowLeft, ExternalLink, Info, Lock, Save, Trash2 } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { MarkdownEditor } from '@/components/admin/MarkdownEditor'
import { MediaPicker } from '@/components/admin/MediaPicker'
import { Banner, Field, INPUT, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import {
  contentHref,
  slugify,
  type ContentPage,
  type MediaItem,
  type PageStatus,
} from '@/lib/content'

type EditablePage = {
  id: number
  title: string
  slug: string
  summary: string | null
  body: string | null
  heroMediaId: number | null
  status: PageStatus
  publishedAt: string | null
  metaTitle: string | null
  metaDescription: string | null
  isSystem: boolean
  isPublished: boolean
  url: string
}

type Props = ContentPage<{
  page: EditablePage | null
  media: MediaItem[]
  reservedSlugs: string[]
}>

/**
 * Create / edit a content page.
 *
 * THREE THINGS THIS SCREEN REFUSES TO LET SOMEONE DO BY ACCIDENT:
 *
 * 1. Publish a page that is not published. `status = published` alone does NOT put a page on
 *    the site — Page::isPublished() also wants a published_at, in the past. A page marked
 *    published with an empty date is invisible, and nothing anywhere would say why. So the
 *    date is filled in the moment you choose Published, and the banner says what the rule is.
 *
 * 2. Change the slug of a live page without knowing what it costs. The slug IS the URL. It is
 *    not a DOI — there is no promise to keep it forever, and fixing a typo on launch day is
 *    worth doing — so the change is ALLOWED. But every existing link to the old URL 404s, and
 *    the editor is told that before they save, not after somebody reports a dead link.
 *
 * 3. Delete a system page. The control does not exist for one. Page::booted() throws and the
 *    controller 403s, but the UI must not offer an action the model refuses.
 */
export default function PageEditor({ page, media, reservedSlugs, meta }: Props) {
  const isNew = page === null
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [slugTouched, setSlugTouched] = useState(!isNew)

  const form = useForm({
    title: page?.title ?? '',
    slug: page?.slug ?? '',
    summary: page?.summary ?? '',
    body: page?.body ?? '',
    hero_media_id: page?.heroMediaId ?? null,
    status: (page?.status ?? 'draft') as PageStatus,
    published_at: page?.publishedAt ?? '',
    meta_title: page?.metaTitle ?? '',
    meta_description: page?.metaDescription ?? '',
  })

  const { data, setData, errors, processing } = form

  const slugChanged = page !== null && data.slug !== page.slug
  const slugIsReserved = reservedSlugs.includes(data.slug)

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    if (isNew) {
      form.post('/admin/content/pages', { preserveScroll: true })
    } else {
      form.put(`/admin/content/pages/${page.id}`, { preserveScroll: true })
    }
  }

  const destroy = () => {
    if (page === null) return
    router.delete(`/admin/content/pages/${page.id}`)
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title={isNew ? 'New page' : page.title}
        description={
          isNew
            ? 'Markdown, rendered by the server. Raw HTML is escaped — that is deliberate.'
            : `Editing ${page.url}`
        }
        actions={
          <>
            {!isNew && page.isPublished && (
              <a href={page.url} target="_blank" rel="noreferrer" className="btn-ghost">
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
                View
              </a>
            )}

            <button type="submit" form="page-form" disabled={processing} className="btn-primary">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : isNew ? 'Create page' : 'Save page'}
            </button>
          </>
        }
      >
        <div className="mb-6">
          <Link href={contentHref.pages} className="btn-ghost px-3 py-1.5 text-xs">
            <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
            All pages
          </Link>
        </div>

        <form id="page-form" onSubmit={submit} className="space-y-8">
          <Panel title="The page" description="Title, URL and the body an author actually reads.">
            <div className="grid gap-6 sm:grid-cols-2">
              <Field label="Title" htmlFor="title" error={errors.title}>
                <input
                  id="title"
                  type="text"
                  value={data.title}
                  onChange={(e) => {
                    setData((current) => ({
                      ...current,
                      title: e.target.value,
                      // Suggests a slug until someone types one. Never overwrites their choice,
                      // and never touches an existing page's URL.
                      slug: slugTouched ? current.slug : slugify(e.target.value),
                    }))
                  }}
                  className={INPUT}
                  required
                />
              </Field>

              <Field
                label="Slug (the URL)"
                htmlFor="slug"
                error={errors.slug}
                hint="Lowercase letters, numbers and hyphens. The page lives at /slug."
              >
                <input
                  id="slug"
                  type="text"
                  value={data.slug}
                  onChange={(e) => {
                    setSlugTouched(true)
                    setData('slug', e.target.value)
                  }}
                  className={`${INPUT} font-mono`}
                  required
                />
              </Field>

              {slugIsReserved && (
                <div className="sm:col-span-2">
                  <Banner
                    tone="danger"
                    icon={AlertTriangle}
                    title={`/${data.slug} is already a route on this site`}
                  >
                    A page lives at /{'{slug}'}, and that route is matched LAST. Another route
                    (/journals, /articles, /admin…) would match first and this page would be
                    unreachable — with no error anywhere. Choose a different slug.
                  </Banner>
                </div>
              )}

              {slugChanged && page.isPublished && (
                <div className="sm:col-span-2">
                  <Banner
                    tone="gold"
                    icon={AlertTriangle}
                    title={`This page is live at ${page.url}. Changing the slug moves it.`}
                  >
                    Every existing link to {page.url} will 404 — including the ones in the footer
                    of an email that has already gone out, and any a reader bookmarked. This is
                    allowed (a page slug is not a DOI, and a typo is worth fixing), but nothing
                    redirects the old URL to the new one.
                  </Banner>
                </div>
              )}

              <Field
                label="Summary"
                htmlFor="summary"
                error={errors.summary}
                hint="One or two sentences. Used as the meta description when there is no explicit one."
                className="sm:col-span-2"
              >
                <textarea
                  id="summary"
                  rows={2}
                  value={data.summary}
                  onChange={(e) => setData('summary', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <div className="sm:col-span-2">
                <MarkdownEditor
                  id="body"
                  value={data.body}
                  onChange={(value) => setData('body', value)}
                  label="Body"
                  error={errors.body}
                />
              </div>

              <div className="sm:col-span-2">
                <MediaPicker
                  id="hero_media_id"
                  value={data.hero_media_id}
                  onChange={(id) => setData('hero_media_id', id)}
                  media={media}
                  label="Hero image"
                  hint="Optional. It needs alt text — that is what a blind reader gets instead of it."
                  error={errors.hero_media_id}
                />
              </div>
            </div>
          </Panel>

          {/* ------------------------------- Publication ------------------------------ */}
          <Panel
            title="Publication"
            description="A page is live when the status is Published AND the date is set and in the past. Both."
          >
            <div className="grid gap-6 sm:grid-cols-2">
              <Field label="Status" htmlFor="status" error={errors.status}>
                <select
                  id="status"
                  value={data.status}
                  onChange={(e) => {
                    const status = e.target.value as PageStatus

                    setData((current) => ({
                      ...current,
                      status,
                      // Choosing Published with no date would silently produce a page that is
                      // not on the site. Fill it in; the editor can still change it.
                      published_at:
                        status === 'published' && !current.published_at
                          ? localNow()
                          : current.published_at,
                    }))
                  }}
                  className={SELECT}
                >
                  <option value="draft">Draft — not on the site</option>
                  <option value="published">Published</option>
                </select>
              </Field>

              <Field
                label="Publication date"
                htmlFor="published_at"
                error={errors.published_at}
                hint="A future date is a scheduled page — it is not live until then."
              >
                <input
                  id="published_at"
                  type="datetime-local"
                  value={data.published_at}
                  onChange={(e) => setData('published_at', e.target.value)}
                  className={INPUT}
                />
              </Field>

              {data.status === 'published' && !data.published_at && (
                <div className="sm:col-span-2">
                  <Banner
                    tone="gold"
                    icon={AlertTriangle}
                    title="Published with no date is NOT live"
                  >
                    The public page controller asks for a status of published AND a date in the
                    past. With no date, this page is invisible to readers and nothing would tell
                    you why.
                  </Banner>
                </div>
              )}
            </div>
          </Panel>

          {/* ---------------------------------- SEO ---------------------------------- */}
          <Panel
            title="Search engines"
            description="Optional. Left empty, the title and summary are used."
          >
            <div className="grid gap-6 sm:grid-cols-2">
              <Field label="Meta title" htmlFor="meta_title" error={errors.meta_title}>
                <input
                  id="meta_title"
                  type="text"
                  value={data.meta_title}
                  onChange={(e) => setData('meta_title', e.target.value)}
                  className={INPUT}
                  placeholder={data.title}
                />
              </Field>

              <Field
                label="Meta description"
                htmlFor="meta_description"
                error={errors.meta_description}
              >
                <input
                  id="meta_description"
                  type="text"
                  value={data.meta_description}
                  onChange={(e) => setData('meta_description', e.target.value)}
                  className={INPUT}
                  placeholder={data.summary || undefined}
                />
              </Field>
            </div>
          </Panel>

          {/* -------------------------------- Deletion ------------------------------- */}
          {!isNew && (
            <Panel
              title="Delete this page"
              description="A page that is linked from the navigation cannot be deleted — unpublish it instead."
            >
              {page.isSystem ? (
                /* NO DELETE CONTROL AT ALL. Not a disabled one. */
                <p className="flex items-start gap-2 rounded-lg bg-ink-50 p-4 text-sm text-ink-700">
                  <Lock className="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
                  <span>
                    <strong className="font-semibold text-ink-900">This is a system page.</strong>{' '}
                    The footer and the mega-menu link to it structurally. Deleting it would not
                    remove those links — it would turn them into 404s that nothing on the site
                    would ever report. Set the status to Draft to take it off the site.
                  </span>
                </p>
              ) : confirmingDelete ? (
                <div className="rounded-lg border border-danger-600/40 bg-danger-50 p-4">
                  <p className="flex items-start gap-2 text-sm text-ink-900">
                    <AlertTriangle
                      className="mt-0.5 h-4 w-4 shrink-0 text-danger-700"
                      aria-hidden="true"
                    />
                    <span>
                      Delete “{page.title}” permanently? Any menu item pointing at it is removed
                      with it, and {page.url} will 404.
                    </span>
                  </p>

                  <div className="mt-4 flex gap-2">
                    <button
                      type="button"
                      onClick={destroy}
                      className="btn inline-flex bg-danger-700 text-white hover:bg-danger-800"
                    >
                      <Trash2 className="h-4 w-4" aria-hidden="true" />
                      Yes, delete it
                    </button>
                    <button
                      type="button"
                      onClick={() => setConfirmingDelete(false)}
                      className="btn-ghost"
                    >
                      Keep it
                    </button>
                  </div>
                </div>
              ) : (
                <button
                  type="button"
                  onClick={() => setConfirmingDelete(true)}
                  className="btn-secondary text-danger-700 hover:border-danger-600 hover:bg-danger-50"
                >
                  <Trash2 className="h-4 w-4" aria-hidden="true" />
                  Delete page
                </button>
              )}
            </Panel>
          )}

          <p className="flex items-start gap-1.5 text-sm text-ink-600">
            <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
            <span>
              The body is markdown and is rendered on the server. A pasted {'<script>'} is
              escaped and appears as literal text — there is no HTML input, so there is no HTML
              to sanitise.
            </span>
          </p>
        </form>
      </ContentShell>
    </>
  )
}

/** "Y-m-dTH:i" in the browser's own zone — what datetime-local expects. */
function localNow(): string {
  const now = new Date()
  const offset = now.getTimezoneOffset() * 60_000

  return new Date(now.getTime() - offset).toISOString().slice(0, 16)
}
