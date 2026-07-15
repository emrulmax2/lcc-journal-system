import { Head, useForm } from '@inertiajs/react'
import { AlertTriangle, Save } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { MarkdownEditor } from '@/components/admin/MarkdownEditor'
import { MediaPicker } from '@/components/admin/MediaPicker'
import { Field, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import type {
  ContentPage,
  MediaItem,
  SettingGroup,
  SettingRow,
} from '@/lib/content'

/**
 * The site's chrome, as editable rows.
 *
 * THE FORM IS DRIVEN BY THE DATA. Each row carries its own `type`, `label` and `help`, and
 * this component renders a control for whichever type it is. Adding an editable string is a
 * seeder line — not a migration, a controller change and a component change. That is the whole
 * reason site_settings is a key/value table, and hardcoding a field list here would throw it
 * away on the first sprint.
 *
 * THE HELP TEXT IS NOT DECORATION. Two of these rows carry warnings that were written after
 * the fact they describe:
 *
 *   footer_copyright — "The year is added automatically — do not type one, or it will be
 *                       wrong next January."
 *   footer_blurb     — "Do NOT claim open peer review here — review is single-blind."
 *
 * The second one is a claim the live footer used to make, and it was false. So `help` is
 * rendered for every row, prominently, and never collapsed behind a tooltip.
 */

type Props = ContentPage<{
  groups: SettingGroup[]
  values: Record<string, string | null>
  media: MediaItem[]
}>

type FormValue = string | number | boolean | null

export default function ContentSettings({ groups, values, media, meta }: Props) {
  const rows = groups.flatMap((group) => group.settings)

  const form = useForm<{ values: Record<string, FormValue> }>({
    values: Object.fromEntries(
      rows.map((row) => [row.key, initial(row, values[row.key] ?? null)]),
    ),
  })

  const { data, setData, errors, processing } = form

  const set = (key: string, value: FormValue) =>
    setData('values', { ...data.values, [key]: value })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.put('/admin/content/settings', { preserveScroll: true })
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Site settings"
        description="The brand, the hero, the footer and the contact details — everything that appears on every page."
        actions={
          <button type="submit" form="site-settings" disabled={processing} className="btn-primary">
            {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
            {processing ? 'Saving…' : 'Save settings'}
          </button>
        }
      >
        <form id="site-settings" onSubmit={submit} className="space-y-8">
          {groups.map((group) => (
            <Panel key={group.key} title={group.label} description={group.description ?? undefined}>
              <div className="grid gap-6 sm:grid-cols-2">
                {group.settings.map((row) => (
                  <Control
                    key={row.key}
                    row={row}
                    value={data.values[row.key] ?? null}
                    onChange={(value) => set(row.key, value)}
                    media={media}
                    error={errors[`values.${row.key}` as keyof typeof errors] as string | undefined}
                  />
                ))}
              </div>
            </Panel>
          ))}

          <div className="flex justify-end">
            <button type="submit" disabled={processing} className="btn-primary">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : 'Save settings'}
            </button>
          </div>
        </form>
      </ContentShell>
    </>
  )
}

/* --------------------------------- Controls -------------------------------- */

function Control({
  row,
  value,
  onChange,
  media,
  error,
}: {
  row: SettingRow
  value: FormValue
  onChange: (value: FormValue) => void
  media: MediaItem[]
  error?: string
}) {
  const id = `setting-${row.key}`
  const wide = row.type === 'textarea' || row.type === 'markdown' || row.type === 'media'

  if (row.type === 'boolean') {
    return (
      <div className="sm:col-span-2">
        <label className="flex cursor-pointer items-start gap-3 text-sm text-ink-800">
          <input
            id={id}
            type="checkbox"
            checked={value === true}
            onChange={(e) => onChange(e.target.checked)}
            className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
          />
          <span>
            <span className="font-medium text-ink-800">{row.label}</span>
            {row.help && <span className="mt-0.5 block text-ink-600">{row.help}</span>}
          </span>
        </label>

        {error && (
          <p className="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
            <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
            {error}
          </p>
        )}
      </div>
    )
  }

  if (row.type === 'media') {
    return (
      <div className="sm:col-span-2">
        <MediaPicker
          id={id}
          value={typeof value === 'number' ? value : null}
          onChange={(mediaId) => onChange(mediaId)}
          media={media}
          label={row.label}
          hint={row.help ?? undefined}
          error={error}
        />
      </div>
    )
  }

  if (row.type === 'markdown') {
    return (
      <div className="sm:col-span-2">
        <MarkdownEditor
          id={id}
          value={typeof value === 'string' ? value : ''}
          onChange={onChange}
          label={row.label}
          hint={row.help ?? undefined}
          error={error}
          rows={10}
        />
      </div>
    )
  }

  return (
    <Field
      label={row.label}
      htmlFor={id}
      hint={row.help ?? undefined}
      error={error}
      className={wide ? 'sm:col-span-2' : undefined}
    >
      {row.type === 'textarea' ? (
        <textarea
          id={id}
          rows={3}
          value={typeof value === 'string' ? value : ''}
          onChange={(e) => onChange(e.target.value)}
          className={INPUT}
        />
      ) : (
        <input
          id={id}
          // `url` and `email` are validated on the SERVER by the row's type. The input type is
          // a keyboard hint and a first pass, never the control.
          type={row.type === 'email' ? 'email' : row.type === 'url' ? 'url' : 'text'}
          value={typeof value === 'string' ? value : ''}
          onChange={(e) => onChange(e.target.value)}
          className={INPUT}
          placeholder={row.type === 'url' ? 'https://' : undefined}
        />
      )}
    </Field>
  )
}

/** The DB stores every value as text. The control needs the right primitive. */
function initial(row: SettingRow, value: string | null): FormValue {
  if (row.type === 'boolean') return value === '1'
  if (row.type === 'media') return value === null || value === '' ? null : Number(value)

  return value ?? ''
}
