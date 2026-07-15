import { Head, useForm } from '@inertiajs/react'
import { AlertTriangle, Eye, EyeOff, Plus, Save, Trash2 } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { MediaPicker } from '@/components/admin/MediaPicker'
import { Field, INPUT, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import { icon as resolveIcon } from '@/lib/icons'
import type {
  ContentPage,
  HomeSectionItemRow,
  HomeSectionRow,
  MediaItem,
  RouteOption,
} from '@/lib/content'

/**
 * The homepage's editorial bands, and the cards inside them.
 *
 * THE `meta` FIELD ON A HOW-IT-WORKS STEP IS WHY THIS SCREEN EXISTS.
 *
 * The prototype rendered four medians in those five steps — "About 20 minutes", "Median 4
 * days", "Median 38 days", "Median 9 days" — as this platform's measured performance. Not one
 * of them was computed from anything. They were typed. They also contradicted the "Median 51
 * days" in the navbar, which was also typed. The seeder ships those steps with `meta` EMPTY,
 * and this form carries the warning, because the next person to fill the field in is the
 * person who needs to read it.
 *
 * A real median does exist — journal_metrics.median_days_to_decision — and it is rendered on
 * the Journals page, where it is attributable to a specific journal. This field is not that,
 * and it never will be: it is a free string, and whoever types one owns it.
 */

type Props = ContentPage<{
  sections: HomeSectionRow[]
  icons: string[]
  routes: RouteOption[]
  media: MediaItem[]
}>

export default function ContentHomeSections({ sections, icons, routes, media, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Homepage"
        description="Each band on the homepage: its heading, its blurb, whether it appears at all, and the cards inside it."
      >
        <div className="space-y-8">
          {sections.map((section) => (
            <SectionForm
              key={section.id}
              section={section}
              icons={icons}
              routes={routes}
              media={media}
            />
          ))}
        </div>
      </ContentShell>
    </>
  )
}

/* -------------------------------- Section form ------------------------------ */

type ItemDraft = {
  id: number | null
  icon: string | null
  title: string
  body: string
  meta: string
  cta_label: string
  route_name: string | null
  external_url: string
}

function SectionForm({
  section,
  icons,
  routes,
  media,
}: {
  section: HomeSectionRow
  icons: string[]
  routes: RouteOption[]
  media: MediaItem[]
}) {
  const form = useForm({
    eyebrow: section.eyebrow ?? '',
    heading: section.heading ?? '',
    blurb: section.blurb ?? '',
    media_id: section.mediaId,
    is_visible: section.isVisible,
    sequence: section.sequence,
    items: section.items.map(toDraft),
  })

  const { data, setData, errors, processing } = form

  const setItem = (index: number, patch: Partial<ItemDraft>) => {
    const next = [...data.items]
    next[index] = { ...next[index], ...patch }
    setData('items', next)
  }

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.put(`/admin/content/home/${section.id}`, { preserveScroll: true })
  }

  const error = (key: string) => (errors as Record<string, string | undefined>)[key]

  return (
    <form onSubmit={submit} aria-label={section.name}>
      <Panel
        title={section.name}
        description={`Section key: ${section.key}`}
        actions={
          <>
            <span
              className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-semibold ${
                data.is_visible ? 'bg-success-50 text-success-800' : 'bg-ink-100 text-ink-700'
              }`}
            >
              {data.is_visible ? (
                <Eye className="h-3.5 w-3.5" aria-hidden="true" />
              ) : (
                <EyeOff className="h-3.5 w-3.5" aria-hidden="true" />
              )}
              {data.is_visible ? 'On the homepage' : 'Hidden'}
            </span>

            <button type="submit" disabled={processing} className="btn-primary px-4 py-2 text-xs">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : 'Save section'}
            </button>
          </>
        }
      >
        <div className="grid gap-5 sm:grid-cols-2">
          <Field label="Eyebrow" htmlFor={`eyebrow-${section.id}`} error={errors.eyebrow}>
            <input
              id={`eyebrow-${section.id}`}
              type="text"
              value={data.eyebrow}
              onChange={(e) => setData('eyebrow', e.target.value)}
              className={INPUT}
            />
          </Field>

          <Field label="Heading" htmlFor={`heading-${section.id}`} error={errors.heading}>
            <input
              id={`heading-${section.id}`}
              type="text"
              value={data.heading}
              onChange={(e) => setData('heading', e.target.value)}
              className={INPUT}
            />
          </Field>

          <Field
            label="Blurb"
            htmlFor={`blurb-${section.id}`}
            error={errors.blurb}
            className="sm:col-span-2"
          >
            <textarea
              id={`blurb-${section.id}`}
              rows={2}
              value={data.blurb}
              onChange={(e) => setData('blurb', e.target.value)}
              className={INPUT}
            />
          </Field>

          <div className="sm:col-span-2">
            <MediaPicker
              id={`media-${section.id}`}
              value={data.media_id}
              onChange={(id) => setData('media_id', id)}
              media={media}
              label="Section image"
              hint="Optional. Not every band has one."
              error={errors.media_id}
            />
          </div>

          <Field
            label="Order on the page"
            htmlFor={`sequence-${section.id}`}
            error={errors.sequence}
            hint="Lower numbers come first."
          >
            <input
              id={`sequence-${section.id}`}
              type="number"
              min={0}
              max={999}
              value={data.sequence}
              onChange={(e) => setData('sequence', Number(e.target.value))}
              className={INPUT}
            />
          </Field>

          <label className="flex cursor-pointer items-center gap-2 pt-7 text-sm text-ink-800">
            <input
              type="checkbox"
              checked={data.is_visible}
              onChange={(e) => setData('is_visible', e.target.checked)}
              className="h-4 w-4 cursor-pointer accent-brand-700"
            />
            Show this section on the homepage
          </label>
        </div>

        {/* ---------------------------------- Cards --------------------------------- */}
        <div className="mt-8 border-t border-ink-200 pt-6">
          <div className="flex flex-wrap items-center justify-between gap-3">
            <h3 className="font-serif text-lg text-ink-900">
              Cards {data.items.length > 0 && `(${data.items.length})`}
            </h3>

            <button
              type="button"
              onClick={() =>
                setData('items', [
                  ...data.items,
                  {
                    id: null,
                    icon: null,
                    title: '',
                    body: '',
                    meta: '',
                    cta_label: '',
                    route_name: null,
                    external_url: '',
                  },
                ])
              }
              className="btn-secondary px-4 py-2 text-xs"
            >
              <Plus className="h-4 w-4" aria-hidden="true" />
              Add card
            </button>
          </div>

          {data.items.length === 0 ? (
            <p className="mt-4 text-sm text-ink-600">This section has no cards.</p>
          ) : (
            <ul className="mt-4 space-y-4">
              {data.items.map((item, index) => {
                const Icon = resolveIcon(item.icon)

                return (
                  <li key={item.id ?? `new-${index}`} className="rounded-lg border border-ink-200 p-4">
                    <div className="grid gap-5 sm:grid-cols-2">
                      <Field
                        label="Icon"
                        htmlFor={`icon-${section.id}-${index}`}
                        error={error(`items.${index}.icon`)}
                        hint="An allow-list. An unknown name renders no icon at all."
                      >
                        <div className="flex items-center gap-3">
                          <select
                            id={`icon-${section.id}-${index}`}
                            value={item.icon ?? ''}
                            onChange={(e) => setItem(index, { icon: e.target.value || null })}
                            className={SELECT}
                          >
                            <option value="">No icon</option>
                            {icons.map((name) => (
                              <option key={name} value={name}>
                                {name}
                              </option>
                            ))}
                          </select>

                          <span className="mt-1.5 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-ink-100 text-ink-700">
                            {Icon ? <Icon className="h-5 w-5" aria-hidden="true" /> : null}
                          </span>
                        </div>
                      </Field>

                      <Field
                        label="Title"
                        htmlFor={`title-${section.id}-${index}`}
                        error={error(`items.${index}.title`)}
                      >
                        <input
                          id={`title-${section.id}-${index}`}
                          type="text"
                          value={item.title}
                          onChange={(e) => setItem(index, { title: e.target.value })}
                          className={INPUT}
                          required
                        />
                      </Field>

                      <Field
                        label="Body"
                        htmlFor={`body-${section.id}-${index}`}
                        error={error(`items.${index}.body`)}
                        className="sm:col-span-2"
                      >
                        <textarea
                          id={`body-${section.id}-${index}`}
                          rows={2}
                          value={item.body}
                          onChange={(e) => setItem(index, { body: e.target.value })}
                          className={INPUT}
                        />
                      </Field>

                      {/* THE FIELD. Read the hint. */}
                      <Field
                        label="Figure / timing"
                        htmlFor={`meta-${section.id}-${index}`}
                        error={error(`items.${index}.meta`)}
                        hint="Only put a figure here that you can point at a source for. This field used to render four invented medians as though they were measured."
                        className="sm:col-span-2"
                      >
                        <input
                          id={`meta-${section.id}-${index}`}
                          type="text"
                          value={item.meta}
                          onChange={(e) => setItem(index, { meta: e.target.value })}
                          className={INPUT}
                          placeholder="Empty — and empty is the honest value until one is measured"
                        />
                      </Field>

                      <Field
                        label="Call-to-action label"
                        htmlFor={`cta-${section.id}-${index}`}
                        error={error(`items.${index}.cta_label`)}
                      >
                        <input
                          id={`cta-${section.id}-${index}`}
                          type="text"
                          value={item.cta_label}
                          onChange={(e) => setItem(index, { cta_label: e.target.value })}
                          className={INPUT}
                        />
                      </Field>

                      <Field
                        label="Links to"
                        htmlFor={`route-${section.id}-${index}`}
                        error={error(`items.${index}.route_name`)}
                        hint="A section of the site, or leave it empty for a card that is not a link."
                      >
                        <select
                          id={`route-${section.id}-${index}`}
                          value={item.route_name ?? ''}
                          onChange={(e) =>
                            setItem(index, {
                              route_name: e.target.value || null,
                              // A route AND an external URL means one of them is silently
                              // ignored by HomeSectionItem::url(). Clear the other.
                              external_url: e.target.value ? '' : item.external_url,
                            })
                          }
                          className={SELECT}
                        >
                          <option value="">Not a link</option>
                          {routes.map((route) => (
                            <option key={route.name} value={route.name}>
                              {route.path} ({route.name})
                            </option>
                          ))}
                        </select>
                      </Field>

                      <Field
                        label="…or an external URL"
                        htmlFor={`external-${section.id}-${index}`}
                        error={error(`items.${index}.external_url`)}
                        className="sm:col-span-2"
                      >
                        <input
                          id={`external-${section.id}-${index}`}
                          type="url"
                          value={item.external_url}
                          onChange={(e) =>
                            setItem(index, {
                              external_url: e.target.value,
                              route_name: e.target.value ? null : item.route_name,
                            })
                          }
                          className={INPUT}
                          placeholder="https://"
                        />
                      </Field>
                    </div>

                    <div className="mt-4 flex justify-end">
                      <button
                        type="button"
                        onClick={() =>
                          setData(
                            'items',
                            data.items.filter((_, i) => i !== index),
                          )
                        }
                        aria-label={`Remove card ${item.title || index + 1}`}
                        className="inline-flex items-center gap-1.5 rounded-lg border border-ink-300 px-3 py-1.5
                                   text-xs font-semibold text-danger-700 transition-colors duration-200
                                   hover:border-danger-600 hover:bg-danger-50"
                      >
                        <Trash2 className="h-3.5 w-3.5" aria-hidden="true" />
                        Remove card
                      </button>
                    </div>
                  </li>
                )
              })}
            </ul>
          )}

          {section.key === 'how-it-works' && (
            <p className="mt-5 flex items-start gap-2 rounded-lg bg-gold-50 p-3 text-xs text-gold-700">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
              <span>
                These are the steps that used to advertise four medians nobody measured. A figure
                here is a promise to an author about how this platform performs — put one in only
                if you can say where it came from.
              </span>
            </p>
          )}
        </div>
      </Panel>
    </form>
  )
}

function toDraft(item: HomeSectionItemRow): ItemDraft {
  return {
    id: item.id,
    icon: item.icon,
    title: item.title,
    body: item.body ?? '',
    meta: item.meta ?? '',
    cta_label: item.ctaLabel ?? '',
    route_name: item.routeName,
    external_url: item.externalUrl ?? '',
  }
}
