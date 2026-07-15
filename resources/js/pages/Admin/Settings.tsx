import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import {
  AlertTriangle,
  Eye,
  EyeOff,
  Info,
  KeyRound,
  Plus,
  Save,
  Trash2,
} from 'lucide-react'
import {
  AdminShell,
  Banner,
  Field,
  INPUT,
  Panel,
  Spinner,
} from '@/components/admin/Shell'
import type { AdminPage } from '@/lib/admin'

type Settings = {
  title: string
  abbreviation: string | null
  description: string | null
  aims_and_scope: string | null
  publisher: string
  principal_editor: string | null
  contact_email: string | null
  /** NULL until the British Library issues one. Never "0000-0000". */
  issn_online: string | null
  issn_print: string | null
  /** NULL until Crossref issues one. Never "10.xxxx". */
  doi_prefix: string | null
  doi_suffix_pattern: string
  doi_sequence_padding: number
  license: string | null
  license_holder: string | null
  open_access: boolean
  crossref_username: string | null
  crossref_deposit_references: boolean

  /**
   * NOT the password. WHETHER one is set.
   *
   * The password is write-only: encrypted at rest, hidden on the model, absent from every
   * resource, and absent from this prop. There is no shape of this page that can read it —
   * only replace it.
   */
  crossref_password_set: boolean
}

type Section = {
  id: number | null
  name: string
  sequence: number
  is_active: boolean
  doi_eligible: boolean
  articles: number
}

type Props = AdminPage<{ settings: Settings; sections: Section[] }>

export default function AdminSettings({ settings, sections, journal, can, journals, meta }: Props) {
  const [replacingPassword, setReplacingPassword] = useState(!settings.crossref_password_set)
  const [showPassword, setShowPassword] = useState(false)

  const form = useForm({
    ...settings,
    // The field starts EMPTY, always, and an empty one means "leave it as it is" — not
    // "clear it". A browser autofill, a half-finished edit and a stray keystroke all look
    // like an empty password box, and none of them should wipe a working credential.
    crossref_password: '',
    sections: sections.map((s) => ({
      id: s.id,
      name: s.name,
      is_active: s.is_active,
      doi_eligible: s.doi_eligible,
      articles: s.articles,
    })),
  })

  const { data, setData, errors, processing } = form

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.put(`/admin/journals/${journal.id}/settings`, { preserveScroll: true })
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={journal.abbreviation ?? 'Journal'}
        title="Settings"
        description="Masthead, identifiers, licence, sections and Crossref credentials."
        actions={
          <button type="submit" form="settings-form" disabled={processing} className="btn-primary">
            {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
            {processing ? 'Saving…' : 'Save settings'}
          </button>
        }
      >
        <form id="settings-form" onSubmit={submit} className="space-y-8">
          {/* -------------------------------- Masthead ------------------------------- */}
          <Panel title="Masthead" description="What the journal is called, and who publishes it.">
            <div className="grid gap-5 sm:grid-cols-2">
              <Field label="Title" htmlFor="title" error={errors.title}>
                <input
                  id="title"
                  type="text"
                  value={data.title}
                  onChange={(e) => setData('title', e.target.value)}
                  className={INPUT}
                  required
                />
              </Field>

              <Field
                label="Abbreviation"
                htmlFor="abbreviation"
                error={errors.abbreviation}
                hint="citation_journal_abbrev — and the {journal} token in the DOI suffix."
              >
                <input
                  id="abbreviation"
                  type="text"
                  value={data.abbreviation ?? ''}
                  onChange={(e) => setData('abbreviation', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field label="Publisher" htmlFor="publisher" error={errors.publisher}>
                <input
                  id="publisher"
                  type="text"
                  value={data.publisher}
                  onChange={(e) => setData('publisher', e.target.value)}
                  className={INPUT}
                  required
                />
              </Field>

              <Field
                label="Principal editor"
                htmlFor="principal_editor"
                error={errors.principal_editor}
              >
                <input
                  id="principal_editor"
                  type="text"
                  value={data.principal_editor ?? ''}
                  onChange={(e) => setData('principal_editor', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field label="Contact email" htmlFor="contact_email" error={errors.contact_email}>
                <input
                  id="contact_email"
                  type="email"
                  value={data.contact_email ?? ''}
                  onChange={(e) => setData('contact_email', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field label="Licence" htmlFor="license" error={errors.license} hint="e.g. CC BY 4.0. Empty means all rights reserved.">
                <input
                  id="license"
                  type="text"
                  value={data.license ?? ''}
                  onChange={(e) => setData('license', e.target.value)}
                  className={INPUT}
                  placeholder="CC BY 4.0"
                />
              </Field>

              <Field label="Licence holder" htmlFor="license_holder" error={errors.license_holder}>
                <input
                  id="license_holder"
                  type="text"
                  value={data.license_holder ?? ''}
                  onChange={(e) => setData('license_holder', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field label="Description" htmlFor="description" error={errors.description} className="sm:col-span-2">
                <textarea
                  id="description"
                  rows={3}
                  value={data.description ?? ''}
                  onChange={(e) => setData('description', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <Field label="Aims and scope" htmlFor="aims_and_scope" error={errors.aims_and_scope} className="sm:col-span-2">
                <textarea
                  id="aims_and_scope"
                  rows={5}
                  value={data.aims_and_scope ?? ''}
                  onChange={(e) => setData('aims_and_scope', e.target.value)}
                  className={INPUT}
                />
              </Field>

              <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
                <input
                  type="checkbox"
                  checked={data.open_access}
                  onChange={(e) => setData('open_access', e.target.checked)}
                  className="h-4 w-4 cursor-pointer accent-brand-700"
                />
                Open access
              </label>
            </div>
          </Panel>

          {/* -------------------------------- Identity ------------------------------- */}
          <Panel
            title="Identifiers"
            description="ISSN and DOI prefix are issued by the British Library and Crossref. Until they are, they are EMPTY — and empty is the correct value, not a placeholder."
          >
            {!journal.canMintDois && (
              <div className="mb-6">
                <Banner
                  tone="gold"
                  icon={AlertTriangle}
                  title="Crossref has not issued a prefix; DOIs cannot be registered yet"
                >
                  Publishing still works and URLs are still permanent. Typing a prefix that is not
                  yours would mint identifiers that resolve nowhere and cannot be withdrawn.
                </Banner>
              </div>
            )}

            <div className="grid gap-5 sm:grid-cols-2">
              <Field
                label="ISSN (online)"
                htmlFor="issn_online"
                error={errors.issn_online}
                hint="Leave empty until it is issued. It is omitted from the deposit entirely rather than sent as a placeholder."
              >
                <input
                  id="issn_online"
                  type="text"
                  value={data.issn_online ?? ''}
                  onChange={(e) => setData('issn_online', e.target.value)}
                  className={`${INPUT} font-mono`}
                  placeholder="2755-0001"
                />
              </Field>

              <Field label="ISSN (print)" htmlFor="issn_print" error={errors.issn_print}>
                <input
                  id="issn_print"
                  type="text"
                  value={data.issn_print ?? ''}
                  onChange={(e) => setData('issn_print', e.target.value)}
                  className={`${INPUT} font-mono`}
                  placeholder="2755-001X"
                />
              </Field>

              <Field
                label="DOI prefix"
                htmlFor="doi_prefix"
                error={errors.doi_prefix}
                hint="Issued by Crossref, e.g. 10.12345. Changing this row moves every DOI this journal owns."
              >
                <input
                  id="doi_prefix"
                  type="text"
                  value={data.doi_prefix ?? ''}
                  onChange={(e) => setData('doi_prefix', e.target.value)}
                  className={`${INPUT} font-mono`}
                  placeholder="10.12345"
                />
              </Field>

              <Field
                label="Suffix pattern"
                htmlFor="doi_suffix_pattern"
                error={errors.doi_suffix_pattern}
                hint="Tokens: {journal} {volume} {issue} {year} {seq}. Issue-based journals use {journal}.v{volume}i{issue}.{seq}."
              >
                <input
                  id="doi_suffix_pattern"
                  type="text"
                  value={data.doi_suffix_pattern}
                  onChange={(e) => setData('doi_suffix_pattern', e.target.value)}
                  className={`${INPUT} font-mono`}
                  required
                />
              </Field>

              <Field
                label="Sequence padding"
                htmlFor="doi_sequence_padding"
                error={errors.doi_sequence_padding}
                hint="3 renders article 5 as 005."
              >
                <input
                  id="doi_sequence_padding"
                  type="number"
                  min={1}
                  max={9}
                  value={data.doi_sequence_padding}
                  onChange={(e) => setData('doi_sequence_padding', Number(e.target.value))}
                  className={INPUT}
                  required
                />
              </Field>
            </div>
          </Panel>

          {/* -------------------------------- Sections ------------------------------- */}
          <Panel
            title="Sections"
            description="The journal's article types. A section that is not DOI-eligible is skipped by the Crossref deposit — front matter gets no DOI, editorials do."
            actions={
              <button
                type="button"
                onClick={() =>
                  setData('sections', [
                    ...data.sections,
                    { id: null, name: '', is_active: true, doi_eligible: true, articles: 0 },
                  ])
                }
                className="btn-secondary"
              >
                <Plus className="h-4 w-4" aria-hidden="true" />
                Add section
              </button>
            }
          >
            {data.sections.length === 0 ? (
              <p className="text-sm text-ink-600">No sections.</p>
            ) : (
              <ul className="space-y-3">
                {data.sections.map((section, index) => (
                  <li key={section.id ?? `new-${index}`} className="rounded-lg border border-ink-200 p-4">
                    <div className="flex flex-wrap items-start gap-4">
                      <Field
                        label="Name"
                        htmlFor={`section-${index}`}
                        error={errors[`sections.${index}.name` as keyof typeof errors]}
                        className="min-w-[200px] flex-1"
                      >
                        <input
                          id={`section-${index}`}
                          type="text"
                          value={section.name}
                          onChange={(e) => {
                            const next = [...data.sections]
                            next[index] = { ...section, name: e.target.value }
                            setData('sections', next)
                          }}
                          className={INPUT}
                        />
                      </Field>

                      <div className="flex flex-wrap items-center gap-5 pt-7">
                        <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
                          <input
                            type="checkbox"
                            checked={section.is_active}
                            onChange={(e) => {
                              const next = [...data.sections]
                              next[index] = { ...section, is_active: e.target.checked }
                              setData('sections', next)
                            }}
                            className="h-4 w-4 cursor-pointer accent-brand-700"
                          />
                          Active
                        </label>

                        <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-800">
                          <input
                            type="checkbox"
                            checked={section.doi_eligible}
                            onChange={(e) => {
                              const next = [...data.sections]
                              next[index] = { ...section, doi_eligible: e.target.checked }
                              setData('sections', next)
                            }}
                            className="h-4 w-4 cursor-pointer accent-brand-700"
                          />
                          DOI-eligible
                        </label>

                        {section.articles > 0 ? (
                          <span className="inline-flex items-center gap-1.5 text-xs text-ink-600">
                            <Info className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
                            {section.articles} {section.articles === 1 ? 'article' : 'articles'} —
                            cannot be removed
                          </span>
                        ) : (
                          <button
                            type="button"
                            onClick={() =>
                              setData(
                                'sections',
                                data.sections.filter((_, i) => i !== index),
                              )
                            }
                            aria-label={`Remove section ${section.name || index + 1}`}
                            className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border
                                       border-ink-300 text-danger-700 transition-colors duration-200
                                       hover:border-danger-600 hover:bg-danger-50"
                          >
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                          </button>
                        )}
                      </div>
                    </div>
                  </li>
                ))}
              </ul>
            )}
          </Panel>

          {/* -------------------------------- Crossref ------------------------------- */}
          <Panel
            title="Crossref credentials"
            description="Used to deposit DOIs. The password is write-only — it is not in this page's props, and nothing can read it back out."
          >
            <div className="grid gap-5 sm:grid-cols-2">
              <Field
                label="Crossref username"
                htmlFor="crossref_username"
                error={errors.crossref_username}
              >
                <input
                  id="crossref_username"
                  type="text"
                  value={data.crossref_username ?? ''}
                  onChange={(e) => setData('crossref_username', e.target.value)}
                  className={INPUT}
                  autoComplete="off"
                />
              </Field>

              <div>
                <span className="block text-sm font-medium text-ink-800">Crossref password</span>

                {/* WRITE-ONLY. The indicator is a boolean from the server: whether one is set.
                    The value itself has never left the database, and cannot. */}
                {settings.crossref_password_set && !replacingPassword ? (
                  <div className="mt-1.5 flex flex-wrap items-center gap-3">
                    <span className="inline-flex items-center gap-2 rounded-lg border border-ink-300 bg-ink-50 px-3.5 py-2.5 text-sm text-ink-700">
                      <KeyRound className="h-4 w-4 text-ink-500" aria-hidden="true" />
                      <span className="font-mono">••••••••</span>
                      <span className="text-xs font-semibold uppercase tracking-wide text-success-800">
                        set
                      </span>
                    </span>
                    <button
                      type="button"
                      onClick={() => setReplacingPassword(true)}
                      className="btn-ghost"
                    >
                      Replace
                    </button>
                  </div>
                ) : (
                  <>
                    <div className="relative">
                      <input
                        id="crossref_password"
                        type={showPassword ? 'text' : 'password'}
                        value={data.crossref_password}
                        onChange={(e) => setData('crossref_password', e.target.value)}
                        className={`${INPUT} pr-12`}
                        autoComplete="new-password"
                        placeholder={
                          settings.crossref_password_set
                            ? 'Type a new password to replace it'
                            : 'Not set'
                        }
                      />
                      <button
                        type="button"
                        onClick={() => setShowPassword((s) => !s)}
                        aria-label={showPassword ? 'Hide password' : 'Show password'}
                        className="absolute right-2 top-1/2 mt-0.5 inline-flex h-9 w-9 -translate-y-1/2 cursor-pointer
                                   items-center justify-center rounded-lg text-ink-500 transition-colors duration-200
                                   hover:bg-ink-100 hover:text-ink-900"
                      >
                        {showPassword ? (
                          <EyeOff className="h-4 w-4" aria-hidden="true" />
                        ) : (
                          <Eye className="h-4 w-4" aria-hidden="true" />
                        )}
                      </button>
                    </div>

                    <p className="mt-1.5 flex items-start gap-1.5 text-sm text-ink-600">
                      <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                      <span>
                        {settings.crossref_password_set
                          ? 'Leave empty to keep the existing password. It is encrypted at rest and is never sent to this page.'
                          : 'Encrypted at rest, and never returned in any response.'}
                      </span>
                    </p>

                    {settings.crossref_password_set && (
                      <button
                        type="button"
                        onClick={() => {
                          setReplacingPassword(false)
                          setData('crossref_password', '')
                        }}
                        className="btn-ghost mt-2"
                      >
                        Keep the existing password
                      </button>
                    )}
                  </>
                )}
              </div>

              <label className="flex cursor-pointer items-start gap-2 text-sm text-ink-800 sm:col-span-2">
                <input
                  type="checkbox"
                  checked={data.crossref_deposit_references}
                  onChange={(e) => setData('crossref_deposit_references', e.target.checked)}
                  className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
                />
                <span>
                  Deposit reference lists
                  <span className="mt-0.5 block text-ink-600">
                    This is what powers Cited-by: other publishers' links to our DOIs exist only if
                    we participate in the same citation graph.
                  </span>
                </span>
              </label>
            </div>
          </Panel>

          <div className="flex justify-end">
            <button type="submit" disabled={processing} className="btn-primary">
              {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              {processing ? 'Saving…' : 'Save settings'}
            </button>
          </div>
        </form>
      </AdminShell>
    </>
  )
}
