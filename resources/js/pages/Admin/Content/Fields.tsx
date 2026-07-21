import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { AlertTriangle, Info, Plus, Save, Tags, Trash2, X } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { Banner, EmptyState, Field, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import type { ContentPage } from '@/lib/content'

type SubjectField = {
  id: number
  name: string
  slug: string
  sequence: number
  /** Journals classified in this field. Non-zero means it cannot be deleted. */
  journalCount: number
}

type Props = ContentPage<{ fields: SubjectField[] }>

/**
 * Subject fields — the filter chips on /journals.
 *
 * The name IS the chip label and the slug IS the filter value, so this screen is the
 * journals page's taxonomy. The table has been read by that page since the first migration;
 * there has just never been a way to edit it outside a seeder.
 *
 * Order is a number, not a drag handle. `sequence` decides the chip order and there are
 * six of them — a full drag-and-drop with its own reorder endpoint would be more code and
 * more failure modes than the problem has.
 */
export default function Fields({ fields, meta }: Props) {
  const [adding, setAdding] = useState(false)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Subject fields"
        description="The filter chips on the journals page. A field spans journals — it is not any one journal's."
        actions={
          <button type="button" onClick={() => setAdding((a) => !a)} className="btn-primary">
            <Plus className="h-4 w-4" aria-hidden="true" />
            New field
          </button>
        }
      >
        {adding && (
          <div className="mb-8">
            <AddField onDone={() => setAdding(false)} nextSequence={nextSequence(fields)} />
          </div>
        )}

        <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
          <div>
            {fields.length === 0 ? (
              <EmptyState
                icon={Tags}
                title="No subject fields"
                body="The journals page shows a chip per field. With none, every journal is unclassified and the filter row is empty."
              />
            ) : (
              <RevealGroup className="space-y-3" stagger={0.04}>
                {fields.map((field) => (
                  <RevealItem key={field.id}>
                    <FieldRow field={field} />
                  </RevealItem>
                ))}
              </RevealGroup>
            )}
          </div>

          <aside className="lg:sticky lg:top-24 lg:self-start">
            <div className="card p-6">
              <h2 className="font-serif text-lg text-ink-900">How these are used</h2>

              <p className="mt-3 text-sm text-ink-600">
                Each field is a chip on the journals page, in <strong>order</strong> order. The
                name is the label; the slug is what the URL filters on.
              </p>

              <p className="mt-4 flex items-start gap-2 rounded-lg bg-ink-50 p-3 text-xs text-ink-700">
                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                <span>
                  Renaming is safe — a field has no DOI and nothing cites it. Article and journal
                  URLs are the permanent ones, and those are frozen on publish.
                </span>
              </p>

              <p className="mt-3 flex items-start gap-2 rounded-lg bg-gold-50 p-3 text-xs text-gold-700">
                <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                <span>
                  A field with journals in it cannot be deleted. Move them to another field first,
                  on each journal's settings screen — deleting would leave them unclassified.
                </span>
              </p>
            </div>
          </aside>
        </div>
      </ContentShell>
    </>
  )
}

function nextSequence(fields: SubjectField[]): number {
  return fields.reduce((max, f) => Math.max(max, f.sequence), 0) + 1
}

function FieldRow({ field }: { field: SubjectField }) {
  const [editing, setEditing] = useState(false)
  const [confirming, setConfirming] = useState(false)

  const form = useForm({
    name: field.name,
    slug: field.slug,
    sequence: field.sequence,
  })

  const destroy = useForm({})

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.put(`/admin/content/fields/${field.id}`, {
      preserveScroll: true,
      onSuccess: () => setEditing(false),
    })
  }

  const locked = field.journalCount > 0
  const deleteError = (destroy.errors as Record<string, string>).field

  if (!editing) {
    return (
      <div className="card p-5">
        <div className="flex flex-wrap items-center justify-between gap-4">
          <div className="flex min-w-0 items-center gap-3">
            <span
              className="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-ink-50 font-mono text-xs text-ink-600"
              title="Order on the journals page"
            >
              {field.sequence}
            </span>

            <div className="min-w-0">
              <p className="truncate font-semibold text-ink-900">{field.name}</p>
              <p className="mt-0.5 truncate font-mono text-xs text-ink-500">{field.slug}</p>
            </div>
          </div>

          <div className="flex items-center gap-2">
            <span className="rounded-full bg-ink-50 px-2.5 py-1 text-xs text-ink-600">
              {field.journalCount} {field.journalCount === 1 ? 'journal' : 'journals'}
            </span>

            <button type="button" onClick={() => setEditing(true)} className="btn-ghost">
              Edit
            </button>

            {/* Absent, not disabled-and-taunting, when it cannot be used. */}
            {!locked &&
              (confirming ? (
                <span className="flex items-center gap-1">
                  <button
                    type="button"
                    onClick={() =>
                      destroy.delete(`/admin/content/fields/${field.id}`, { preserveScroll: true })
                    }
                    disabled={destroy.processing}
                    className="btn-ghost text-danger-700 hover:bg-danger-50"
                  >
                    {destroy.processing ? <Spinner /> : <Trash2 className="h-4 w-4" aria-hidden="true" />}
                    Confirm
                  </button>
                  <button type="button" onClick={() => setConfirming(false)} className="btn-ghost">
                    <X className="h-4 w-4" aria-hidden="true" />
                    <span className="sr-only">Cancel</span>
                  </button>
                </span>
              ) : (
                <button
                  type="button"
                  onClick={() => setConfirming(true)}
                  className="btn-ghost text-danger-700 hover:bg-danger-50"
                >
                  <Trash2 className="h-4 w-4" aria-hidden="true" />
                  <span className="sr-only">Delete {field.name}</span>
                </button>
              ))}
          </div>
        </div>

        {deleteError && (
          <div className="mt-4">
            <Banner tone="danger" icon={AlertTriangle} title="Not deleted">
              {deleteError}
            </Banner>
          </div>
        )}
      </div>
    )
  }

  return (
    <form onSubmit={submit} className="card p-5">
      <div className="grid gap-4 sm:grid-cols-[1fr_1fr_100px]">
        <Field label="Name" htmlFor={`name-${field.id}`} error={form.errors.name}>
          <input
            id={`name-${field.id}`}
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            className={INPUT}
            required
          />
        </Field>

        <Field label="Slug" htmlFor={`slug-${field.id}`} error={form.errors.slug}>
          <input
            id={`slug-${field.id}`}
            value={form.data.slug}
            onChange={(e) => form.setData('slug', e.target.value)}
            className={`${INPUT} font-mono`}
          />
        </Field>

        <Field label="Order" htmlFor={`sequence-${field.id}`} error={form.errors.sequence}>
          <input
            id={`sequence-${field.id}`}
            type="number"
            min={0}
            value={form.data.sequence}
            onChange={(e) => form.setData('sequence', Number(e.target.value))}
            className={INPUT}
          />
        </Field>
      </div>

      <div className="mt-4 flex items-center gap-2">
        <button type="submit" disabled={form.processing} className="btn-primary">
          {form.processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
          Save
        </button>
        <button
          type="button"
          onClick={() => {
            form.reset()
            form.clearErrors()
            setEditing(false)
          }}
          className="btn-ghost"
        >
          Cancel
        </button>
      </div>
    </form>
  )
}

function AddField({ onDone, nextSequence }: { onDone: () => void; nextSequence: number }) {
  const form = useForm({ name: '', slug: '', sequence: nextSequence })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.post('/admin/content/fields', {
      preserveScroll: true,
      onSuccess: () => {
        form.reset()
        onDone()
      },
    })
  }

  return (
    <Panel
      title="New subject field"
      description="It appears as a chip on the journals page as soon as it is saved."
    >
      <form onSubmit={submit} className="grid gap-4 sm:grid-cols-[1fr_1fr_100px_auto]">
        <Field label="Name" htmlFor="new-field-name" error={form.errors.name}>
          <input
            id="new-field-name"
            value={form.data.name}
            onChange={(e) => form.setData('name', e.target.value)}
            className={INPUT}
            placeholder="Economics & Finance"
            required
            autoFocus
          />
        </Field>

        <Field
          label="Slug"
          htmlFor="new-field-slug"
          error={form.errors.slug}
          hint="Optional — derived from the name."
        >
          <input
            id="new-field-slug"
            value={form.data.slug}
            onChange={(e) => form.setData('slug', e.target.value)}
            className={`${INPUT} font-mono`}
            placeholder="economics-finance"
          />
        </Field>

        <Field label="Order" htmlFor="new-field-sequence" error={form.errors.sequence}>
          <input
            id="new-field-sequence"
            type="number"
            min={0}
            value={form.data.sequence}
            onChange={(e) => form.setData('sequence', Number(e.target.value))}
            className={INPUT}
          />
        </Field>

        <div className="flex items-end">
          <button type="submit" disabled={form.processing} className="btn-primary">
            {form.processing ? <Spinner /> : <Plus className="h-4 w-4" aria-hidden="true" />}
            Add
          </button>
        </div>
      </form>
    </Panel>
  )
}
