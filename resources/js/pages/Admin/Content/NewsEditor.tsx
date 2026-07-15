import { useState } from 'react'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { AlertTriangle, ArrowLeft, ExternalLink, Save, Trash2 } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { MarkdownEditor } from '@/components/admin/MarkdownEditor'
import { MediaPicker } from '@/components/admin/MediaPicker'
import { Banner, Field, INPUT, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import {
  contentHref,
  slugify,
  type ContentPage,
  type MediaItem,
  type UserOption,
} from '@/lib/content'

type EditableNews = {
  id: number
  title: string
  slug: string
  category: string
  excerpt: string | null
  body: string | null
  mediaId: number | null
  authorId: number | null
  publishedAt: string | null
  isPublished: boolean
  url: string
}

type Props = ContentPage<{
  item: EditableNews | null
  media: MediaItem[]
  authors: UserOption[]
  categories: string[]
}>

/**
 * A news story.
 *
 * THE SLUG IS THE URL, AND ONCE THE STORY IS LIVE SOMEBODY MAY HAVE LINKED TO IT.
 *
 * This is not a DOI. There is no undertaking to keep a news URL resolving forever, and fixing
 * a typo in one on the morning it goes out is worth doing. So changing it is ALLOWED — but the
 * cost is stated plainly, before the save: every existing link to the old URL 404s, including
 * the one in the newsletter that has already gone out, and nothing redirects it.
 *
 * That is the difference between this screen and the article editor, where the slug is FROZEN
 * on publication by an observer, because a DOI resolves through it and a dead DOI cannot be
 * withdrawn.
 */
export default function NewsEditor({ item, media, authors, categories, meta }: Props) {
  const isNew = item === null
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [slugTouched, setSlugTouched] = useState(!isNew)

  const form = useForm({
    title: item?.title ?? '',
    slug: item?.slug ?? '',
    category: item?.category ?? '',
    excerpt: item?.excerpt ?? '',
    body: item?.body ?? '',
    media_id: item?.mediaId ?? null,
    author_id: item?.authorId ?? null,
    published_at: item?.publishedAt ?? '',
  })

  const { data, setData, errors, processing } = form

  const slugChanged = item !== null && data.slug !== item.slug

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    if (isNew) {
      form.post('/admin/content/news', { preserveScroll: true })
    } else {
      form.put(`/admin/content/news/${item.id}`, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title={isNew ? 'New story' : item.title}
        description={isNew ? 'Markdown, rendered by the server.' : `Editing ${item.url}`}
        actions={
          <>
            {!isNew && item.isPublished && (
              <a href={item.url} target="_blank" rel="noreferrer" className="btn-ghost">
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
                View
              </a>
            )}

            <button type="submit" form="news-form" disabled={processing} className="btn-primary">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : isNew ? 'Create story' : 'Save story'}
            </button>
          </>
        }
      >
        <div className="mb-6">
          <Link href={contentHref.news} className="btn-ghost px-3 py-1.5 text-xs">
            <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
            All news
          </Link>
        </div>

        <form id="news-form" onSubmit={submit} className="space-y-8">
          <Panel title="The story">
            <div className="grid gap-6 sm:grid-cols-2">
              <Field label="Title" htmlFor="title" error={errors.title}>
                <input
                  id="title"
                  type="text"
                  value={data.title}
                  onChange={(e) =>
                    setData((current) => ({
                      ...current,
                      title: e.target.value,
                      slug: slugTouched ? current.slug : slugify(e.target.value),
                    }))
                  }
                  className={INPUT}
                  required
                />
              </Field>

              <Field
                label="Slug (the URL)"
                htmlFor="slug"
                error={errors.slug}
                hint="The story lives at /news/slug."
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

              {slugChanged && item.isPublished && (
                <div className="sm:col-span-2">
                  <Banner
                    tone="gold"
                    icon={AlertTriangle}
                    title={`This story is live at ${item.url}. Changing the slug moves it.`}
                  >
                    Every existing link to {item.url} will 404 — including any already sent to the
                    newsletter list, and anything another site has linked. Nothing redirects the
                    old URL. This is allowed (a news slug is not a DOI), but it is not free.
                  </Banner>
                </div>
              )}

              <Field
                label="Category"
                htmlFor="category"
                error={errors.category}
                hint="e.g. Announcement, Call for papers, Research highlight."
              >
                <input
                  id="category"
                  type="text"
                  list="news-categories"
                  value={data.category}
                  onChange={(e) => setData('category', e.target.value)}
                  className={INPUT}
                  required
                />
                <datalist id="news-categories">
                  {categories.map((category) => (
                    <option key={category} value={category} />
                  ))}
                </datalist>
              </Field>

              <Field
                label="Author"
                htmlFor="author_id"
                error={errors.author_id}
                hint="Optional. Shown on the story page."
              >
                <select
                  id="author_id"
                  value={data.author_id ?? ''}
                  onChange={(e) =>
                    setData('author_id', e.target.value ? Number(e.target.value) : null)
                  }
                  className={SELECT}
                >
                  <option value="">No author</option>
                  {authors.map((author) => (
                    <option key={author.id} value={author.id}>
                      {author.name} ({author.email})
                    </option>
                  ))}
                </select>
              </Field>

              <Field
                label="Excerpt"
                htmlFor="excerpt"
                error={errors.excerpt}
                hint="The card on the homepage and the /news list show this."
                className="sm:col-span-2"
              >
                <textarea
                  id="excerpt"
                  rows={2}
                  value={data.excerpt}
                  onChange={(e) => setData('excerpt', e.target.value)}
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
                  id="media_id"
                  value={data.media_id}
                  onChange={(id) => setData('media_id', id)}
                  media={media}
                  label="Image"
                  hint="A real photo we own — not a stock image of someone else's laboratory."
                  error={errors.media_id}
                />
              </div>
            </div>
          </Panel>

          <Panel
            title="Publication"
            description="Empty is a draft. A future date is scheduled — the story is not on the site until then."
          >
            <div className="grid gap-6 sm:grid-cols-2">
              <Field label="Publication date" htmlFor="published_at" error={errors.published_at}>
                <input
                  id="published_at"
                  type="datetime-local"
                  value={data.published_at}
                  onChange={(e) => setData('published_at', e.target.value)}
                  className={INPUT}
                />
              </Field>
            </div>
          </Panel>

          {!isNew && (
            <Panel title="Delete this story">
              {confirmingDelete ? (
                <div className="rounded-lg border border-danger-600/40 bg-danger-50 p-4">
                  <p className="flex items-start gap-2 text-sm text-ink-900">
                    <AlertTriangle
                      className="mt-0.5 h-4 w-4 shrink-0 text-danger-700"
                      aria-hidden="true"
                    />
                    <span>
                      Delete “{item.title}” permanently? {item.url} will 404 for anyone who has
                      linked to it.
                    </span>
                  </p>

                  <div className="mt-4 flex gap-2">
                    <button
                      type="button"
                      onClick={() => router.delete(`/admin/content/news/${item.id}`)}
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
                  Delete story
                </button>
              )}
            </Panel>
          )}
        </form>
      </ContentShell>
    </>
  )
}
