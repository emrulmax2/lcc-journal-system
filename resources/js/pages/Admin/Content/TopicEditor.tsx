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

type EditableTopic = {
  id: number
  title: string
  slug: string
  description: string | null
  body: string | null
  mediaId: number | null
  deadline: string | null
  journalId: number | null
  submissionEmail: string | null
  isOpen: boolean
  editorIds: number[]
  url: string
}

type JournalOption = { id: number; title: string; abbreviation: string | null }

type Props = ContentPage<{
  topic: EditableTopic | null
  media: MediaItem[]
  journals: JournalOption[]
  users: UserOption[]
}>

/**
 * A call for papers.
 *
 * `journal_id` IS NULLABLE AND MUST STAY SO. A cross-journal Research Topic is a real thing —
 * a collection that spans two journals is not an error state, and forcing one journal onto it
 * to satisfy a form would be a lie about who is running it.
 *
 * The slug is the URL. Same rule as News: changing it after publication is allowed, and the
 * banner says what it costs.
 */
export default function TopicEditor({ topic, media, journals, users, meta }: Props) {
  const isNew = topic === null
  const [confirmingDelete, setConfirmingDelete] = useState(false)
  const [slugTouched, setSlugTouched] = useState(!isNew)

  const form = useForm({
    title: topic?.title ?? '',
    slug: topic?.slug ?? '',
    description: topic?.description ?? '',
    body: topic?.body ?? '',
    media_id: topic?.mediaId ?? null,
    deadline: topic?.deadline ?? '',
    journal_id: topic?.journalId ?? null,
    submission_email: topic?.submissionEmail ?? '',
    is_open: topic?.isOpen ?? true,
    editors: topic?.editorIds ?? [],
  })

  const { data, setData, errors, processing } = form

  const slugChanged = topic !== null && data.slug !== topic.slug

  const toggleEditor = (id: number) =>
    setData(
      'editors',
      data.editors.includes(id)
        ? data.editors.filter((editorId) => editorId !== id)
        : [...data.editors, id],
    )

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    if (isNew) {
      form.post('/admin/content/topics', { preserveScroll: true })
    } else {
      form.put(`/admin/content/topics/${topic.id}`, { preserveScroll: true })
    }
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title={isNew ? 'New Research Topic' : topic.title}
        description={isNew ? 'An open call for papers.' : `Editing ${topic.url}`}
        actions={
          <>
            {!isNew && (
              <a href={topic.url} target="_blank" rel="noreferrer" className="btn-ghost">
                <ExternalLink className="h-4 w-4" aria-hidden="true" />
                View
              </a>
            )}

            <button type="submit" form="topic-form" disabled={processing} className="btn-primary">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : isNew ? 'Create topic' : 'Save topic'}
            </button>
          </>
        }
      >
        <div className="mb-6">
          <Link href={contentHref.topics} className="btn-ghost px-3 py-1.5 text-xs">
            <ArrowLeft className="h-3.5 w-3.5" aria-hidden="true" />
            All Research Topics
          </Link>
        </div>

        <form id="topic-form" onSubmit={submit} className="space-y-8">
          <Panel title="The call">
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
                hint="The topic lives at /topics/slug."
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

              {slugChanged && (
                <div className="sm:col-span-2">
                  <Banner
                    tone="gold"
                    icon={AlertTriangle}
                    title={`This topic is published at ${topic.url}. Changing the slug moves it.`}
                  >
                    Every existing link to {topic.url} will 404 — including any in a call-for-papers
                    email that has already gone out. Nothing redirects the old URL.
                  </Banner>
                </div>
              )}

              <Field
                label="Short description"
                htmlFor="description"
                error={errors.description}
                hint="The card on the homepage and the /topics list show this."
                className="sm:col-span-2"
              >
                <textarea
                  id="description"
                  rows={2}
                  value={data.description}
                  onChange={(e) => setData('description', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <div className="sm:col-span-2">
                <MarkdownEditor
                  id="body"
                  value={data.body}
                  onChange={(value) => setData('body', value)}
                  label="Scope"
                  hint="What this collection is calling for. An author decides whether their manuscript belongs here by reading this."
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
                  error={errors.media_id}
                />
              </div>
            </div>
          </Panel>

          <Panel
            title="Submissions"
            description="Who runs it, which journal it belongs to (if any), and when it closes."
          >
            <div className="grid gap-6 sm:grid-cols-2">
              <Field
                label="Journal"
                htmlFor="journal_id"
                error={errors.journal_id}
                hint="Optional. A cross-journal call belongs to no single journal — leave it empty."
              >
                <select
                  id="journal_id"
                  value={data.journal_id ?? ''}
                  onChange={(e) =>
                    setData('journal_id', e.target.value ? Number(e.target.value) : null)
                  }
                  className={SELECT}
                >
                  <option value="">No single journal</option>
                  {journals.map((journal) => (
                    <option key={journal.id} value={journal.id}>
                      {journal.abbreviation ?? journal.title}
                    </option>
                  ))}
                </select>
              </Field>

              <Field
                label="Deadline"
                htmlFor="deadline"
                error={errors.deadline}
                hint="A published deadline is a promise. Leave it empty rather than invent one."
              >
                <input
                  id="deadline"
                  type="date"
                  value={data.deadline}
                  onChange={(e) => setData('deadline', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field
                label="Submission email"
                htmlFor="submission_email"
                error={errors.submission_email}
                hint="Where an author sends a question about scope."
              >
                <input
                  id="submission_email"
                  type="email"
                  value={data.submission_email}
                  onChange={(e) => setData('submission_email', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <label className="flex cursor-pointer items-start gap-2 pt-7 text-sm text-ink-800">
                <input
                  type="checkbox"
                  checked={data.is_open}
                  onChange={(e) => setData('is_open', e.target.checked)}
                  className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
                />
                <span>
                  Open for submissions
                  <span className="mt-0.5 block text-ink-600">
                    Closed topics stay readable — the collection does not disappear when the call
                    ends.
                  </span>
                </span>
              </label>
            </div>

            {/* -------------------------------- Editors -------------------------------- */}
            <fieldset className="mt-6 border-t border-ink-200 pt-6">
              <legend className="text-sm font-medium text-ink-800">Topic editors</legend>

              <p className="mt-1 text-sm text-ink-600">
                The people leading this call. Their names appear on the public page.
              </p>

              {users.length === 0 ? (
                <p className="mt-3 text-sm text-ink-600">No users to choose from.</p>
              ) : (
                <ul className="mt-3 grid max-h-64 gap-2 overflow-y-auto rounded-lg border border-ink-200 p-3 sm:grid-cols-2">
                  {users.map((user) => (
                    <li key={user.id}>
                      <label className="flex cursor-pointer items-start gap-2 rounded-md p-1.5 text-sm text-ink-800 transition-colors duration-200 hover:bg-ink-50">
                        <input
                          type="checkbox"
                          checked={data.editors.includes(user.id)}
                          onChange={() => toggleEditor(user.id)}
                          className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
                        />
                        <span className="min-w-0">
                          <span className="block truncate font-medium">{user.name}</span>
                          <span className="block truncate text-xs text-ink-600">{user.email}</span>
                        </span>
                      </label>
                    </li>
                  ))}
                </ul>
              )}
            </fieldset>
          </Panel>

          {!isNew && (
            <Panel title="Delete this Research Topic">
              {confirmingDelete ? (
                <div className="rounded-lg border border-danger-600/40 bg-danger-50 p-4">
                  <p className="flex items-start gap-2 text-sm text-ink-900">
                    <AlertTriangle
                      className="mt-0.5 h-4 w-4 shrink-0 text-danger-700"
                      aria-hidden="true"
                    />
                    <span>
                      Delete “{topic.title}” permanently? {topic.url} will 404. If the call has
                      simply closed, untick “Open for submissions” instead — the collection stays
                      readable.
                    </span>
                  </p>

                  <div className="mt-4 flex gap-2">
                    <button
                      type="button"
                      onClick={() => router.delete(`/admin/content/topics/${topic.id}`)}
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
                  Delete topic
                </button>
              )}
            </Panel>
          )}
        </form>
      </ContentShell>
    </>
  )
}
