import { useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import { AlertTriangle, ArrowLeft, Info, Save, ShieldCheck, Trash2 } from 'lucide-react'
import { PeopleShell } from '@/components/admin/PeopleShell'
import { Banner, Field, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import { peopleHref } from '@/lib/admin'
import type { Meta } from '@/lib/props'

type RoleOption = { name: string; description: string }
type JournalOption = { id: number; title: string; abbreviation: string | null }
type Assignment = { journalId: number; roles: string[] }

type Account = {
  id: number
  givenName: string | null
  familyName: string | null
  name: string
  email: string
  affiliation: string | null
  orcid: string | null
  isActive: boolean
  isSiteAdmin: boolean
  isSelf: boolean
  /** What they have left on the scholarly record. Decides delete vs deactivate. */
  contentCounts: { submissions: number; reviews: number }
}

type Props = {
  account: Account | null
  journals: JournalOption[]
  roles: RoleOption[]
  assignments: Assignment[]
  can: { grantSiteAdmin: boolean }
  meta: Meta
}

/**
 * One account: the person, and their roles on every journal at once.
 *
 * Given and family names are separate fields and both are required, because Crossref needs
 * them separately — an author deposited as one blob is an author nobody can search for.
 * `name` is derived from them on the server.
 *
 * The site-admin switch renders only for a site admin (can.grantSiteAdmin). That is not
 * cosmetic: is_site_admin is read by Gate::before, the one global bypass in the system, so
 * a publisher-admin who could set it would silently hold the highest privilege in the app.
 * The server discards the field regardless of what is posted — this just stops it taunting.
 */
export default function AccountEditor({ account, journals, roles, assignments, can, meta }: Props) {
  const editing = account !== null

  const form = useForm({
    given_name: account?.givenName ?? '',
    family_name: account?.familyName ?? '',
    email: account?.email ?? '',
    password: '',
    affiliation: account?.affiliation ?? '',
    orcid: account?.orcid ?? '',
    is_active: account?.isActive ?? true,
    is_site_admin: account?.isSiteAdmin ?? false,
    assignments: journals.map((journal) => ({
      journal_id: journal.id,
      roles: assignments.find((a) => a.journalId === journal.id)?.roles ?? [],
    })),
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    if (editing) {
      form.put(`/admin/users/${account.id}`, { preserveScroll: true })
    } else {
      form.post('/admin/users')
    }
  }

  const toggleRole = (journalId: number, role: string, on: boolean) => {
    form.setData(
      'assignments',
      form.data.assignments.map((a) =>
        a.journal_id !== journalId
          ? a
          : { ...a, roles: on ? [...a.roles, role] : a.roles.filter((r) => r !== role) },
      ),
    )
  }

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <PeopleShell
        title={editing ? account.name : 'New account'}
        description={
          editing
            ? 'The person, and what they may do on each journal.'
            : 'Create a login. Roles are granted per journal, below.'
        }
        canManageRoles={can.grantSiteAdmin}
        actions={
          <Link href={peopleHref.accounts} className="btn-ghost">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            All accounts
          </Link>
        }
      >
        {/* Guard errors (last site admin, self-deactivation) are attached to no field. */}
        {(form.errors as Record<string, string>).account && (
          <div className="mb-6">
            <Banner tone="danger" icon={AlertTriangle} title="That change was refused">
              {(form.errors as Record<string, string>).account}
            </Banner>
          </div>
        )}

        <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_340px]">
          <div className="space-y-8">
            <Panel title="The person" description="Given and family names are separate because Crossref deposits them separately.">
              <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Given name" htmlFor="given_name" error={form.errors.given_name}>
                  <input
                    id="given_name"
                    value={form.data.given_name}
                    onChange={(e) => form.setData('given_name', e.target.value)}
                    className={INPUT}
                    autoComplete="given-name"
                    required
                  />
                </Field>

                <Field label="Family name" htmlFor="family_name" error={form.errors.family_name}>
                  <input
                    id="family_name"
                    value={form.data.family_name}
                    onChange={(e) => form.setData('family_name', e.target.value)}
                    className={INPUT}
                    autoComplete="family-name"
                    required
                  />
                </Field>

                <Field
                  label="Email"
                  htmlFor="email"
                  error={form.errors.email}
                  hint="This is the login."
                  className="sm:col-span-2"
                >
                  <input
                    id="email"
                    type="email"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    className={INPUT}
                    autoComplete="off"
                    required
                  />
                </Field>

                <Field
                  label="Affiliation"
                  htmlFor="affiliation"
                  error={form.errors.affiliation}
                  className="sm:col-span-2"
                >
                  <input
                    id="affiliation"
                    value={form.data.affiliation}
                    onChange={(e) => form.setData('affiliation', e.target.value)}
                    className={INPUT}
                    placeholder="London Churchill College"
                  />
                </Field>

                <Field
                  label="ORCID"
                  htmlFor="orcid"
                  error={form.errors.orcid}
                  hint="Optional. Deposited to Crossref when they author an article."
                  className="sm:col-span-2"
                >
                  <input
                    id="orcid"
                    value={form.data.orcid}
                    onChange={(e) => form.setData('orcid', e.target.value)}
                    className={`${INPUT} font-mono`}
                    placeholder="0000-0002-1825-0097"
                  />
                </Field>
              </div>
            </Panel>

            <Panel
              title={editing ? 'Change password' : 'Password'}
              description={
                editing
                  ? 'Leave blank to keep the current one.'
                  : 'At least 12 characters. Tell them out of band — this is never shown again.'
              }
            >
              <Field label={editing ? 'New password' : 'Password'} htmlFor="password" error={form.errors.password}>
                <input
                  id="password"
                  type="password"
                  value={form.data.password}
                  onChange={(e) => form.setData('password', e.target.value)}
                  className={INPUT}
                  autoComplete="new-password"
                  required={!editing}
                />
              </Field>
            </Panel>

            <Panel
              title="Roles, per journal"
              description="Someone edits one journal and reviews for another. A role granted here applies to that journal and nowhere else."
            >
              {journals.length === 0 ? (
                <p className="text-sm text-ink-600">There are no journals yet.</p>
              ) : (
                <div className="space-y-6">
                  {journals.map((journal) => {
                    const assignment = form.data.assignments.find((a) => a.journal_id === journal.id)

                    return (
                      <fieldset key={journal.id} className="border-t border-ink-200 pt-5 first:border-0 first:pt-0">
                        <legend className="text-sm font-semibold text-ink-900">
                          {journal.abbreviation ?? journal.title}
                        </legend>

                        <div className="mt-3 flex flex-wrap gap-2">
                          {roles.map((role) => {
                            const on = assignment?.roles.includes(role.name) ?? false

                            return (
                              <label
                                key={role.name}
                                title={role.description}
                                className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5
                                            text-xs font-semibold transition-colors duration-200 ${
                                              on
                                                ? 'border-brand-700 bg-brand-700 text-white'
                                                : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
                                            }`}
                              >
                                <input
                                  type="checkbox"
                                  checked={on}
                                  onChange={(e) => toggleRole(journal.id, role.name, e.target.checked)}
                                  className="h-3.5 w-3.5 cursor-pointer accent-white"
                                />
                                {role.name}
                              </label>
                            )
                          })}
                        </div>
                      </fieldset>
                    )
                  })}
                </div>
              )}
            </Panel>
          </div>

          <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
            <div className="card p-6">
              <h2 className="font-serif text-lg text-ink-900">Access</h2>

              <div className="mt-4 space-y-4">
                <Toggle
                  id="is_active"
                  label="Active"
                  hint={
                    account?.isSelf
                      ? 'You cannot deactivate your own account.'
                      : 'A deactivated account cannot sign in, and is signed out immediately.'
                  }
                  checked={form.data.is_active}
                  disabled={account?.isSelf}
                  error={form.errors.is_active}
                  onChange={(v) => form.setData('is_active', v)}
                />

                {can.grantSiteAdmin && (
                  <Toggle
                    id="is_site_admin"
                    label="Site administrator"
                    hint="Bypasses every permission check, on every journal. Grant it to as few people as possible."
                    checked={form.data.is_site_admin}
                    error={form.errors.is_site_admin}
                    onChange={(v) => form.setData('is_site_admin', v)}
                  />
                )}
              </div>

              {form.data.is_site_admin && (
                <p className="mt-4 flex items-start gap-2 rounded-lg bg-gold-50 p-3 text-xs text-gold-700">
                  <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  <span>
                    A site administrator can publish, deposit DOIs and edit every journal — the
                    per-journal roles below are redundant for them.
                  </span>
                </p>
              )}

              <button type="submit" disabled={form.processing} className="btn-primary mt-6 w-full justify-center">
                {form.processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
                {editing ? 'Save changes' : 'Create account'}
              </button>

              {form.isDirty && !form.processing && (
                <p className="mt-2 text-center text-xs text-ink-500">Unsaved changes</p>
              )}
            </div>

            {editing && !account.isSelf && <DangerZone account={account} />}

            <div className="card p-6">
              <h2 className="font-serif text-lg text-ink-900">What the roles mean</h2>
              <dl className="mt-4 space-y-4 text-sm">
                {roles.map((role) => (
                  <div key={role.name}>
                    <dt className="font-mono text-xs font-semibold text-ink-900">{role.name}</dt>
                    <dd className="mt-0.5 text-ink-600">{role.description}</dd>
                  </div>
                ))}
              </dl>

              <p className="mt-5 flex items-start gap-2 rounded-lg bg-ink-50 p-3 text-xs text-ink-700">
                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                <span>
                  Publishing freezes a URL and spends money at Crossref, and there is no undo — so{' '}
                  <span className="font-mono">production</span> deliberately cannot do it, even
                  though it can edit every field of an article.
                </span>
              </p>
            </div>
          </aside>
        </form>
      </PeopleShell>
    </>
  )
}

function Toggle({
  id,
  label,
  hint,
  checked,
  disabled,
  error,
  onChange,
}: {
  id: string
  label: string
  hint: string
  checked: boolean
  disabled?: boolean
  error?: string
  onChange: (value: boolean) => void
}) {
  return (
    <div>
      <label
        htmlFor={id}
        className={`flex items-start gap-3 ${disabled ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
      >
        <input
          id={id}
          type="checkbox"
          checked={checked}
          disabled={disabled}
          onChange={(e) => onChange(e.target.checked)}
          className="mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-700"
        />
        <span className="min-w-0">
          <span className="block text-sm font-medium text-ink-800">{label}</span>
          <span className="mt-0.5 block text-xs text-ink-600">{hint}</span>
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

/**
 * Delete, or deactivate — the server decides which, and says so here first.
 *
 * Someone who has authored a submission or filed a review is part of the scholarly record.
 * Deleting them would orphan it, so they are deactivated instead and their history stays
 * attributable. Only an account with no trace at all is really deleted. The button says
 * which will happen BEFORE it is pressed, because "Delete" that silently deactivates is a
 * lie, and "Delete" that silently destroys a review history is a catastrophe.
 */
function DangerZone({ account }: { account: Account }) {
  const [confirming, setConfirming] = useState(false)
  const form = useForm({})

  const onRecord = account.contentCounts.submissions + account.contentCounts.reviews
  const willDelete = onRecord === 0

  const parts = [
    account.contentCounts.submissions > 0 &&
      `${account.contentCounts.submissions} ${account.contentCounts.submissions === 1 ? 'submission' : 'submissions'}`,
    account.contentCounts.reviews > 0 &&
      `${account.contentCounts.reviews} ${account.contentCounts.reviews === 1 ? 'review' : 'reviews'}`,
  ].filter(Boolean)

  return (
    <div className="card border-danger-700/30 p-6">
      <h2 className="flex items-center gap-2 font-serif text-lg text-ink-900">
        <ShieldCheck className="h-4 w-4 text-danger-700" aria-hidden="true" />
        {willDelete ? 'Delete account' : 'Deactivate account'}
      </h2>

      <p className="mt-2 text-sm text-ink-600">
        {willDelete ? (
          <>
            {account.name} has no submissions, reviews or decisions on record, so the account can
            be removed outright.
          </>
        ) : (
          <>
            {account.name} has {parts.join(' and ')} on record. The account will be{' '}
            <strong className="font-semibold text-ink-900">deactivated, not deleted</strong> — they
            lose access immediately and the scholarly record stays intact.
          </>
        )}
      </p>

      {confirming ? (
        <div className="mt-4 flex flex-wrap gap-2">
          <button
            type="button"
            onClick={() =>
              form.delete(`/admin/users/${account.id}`, { preserveScroll: true })
            }
            disabled={form.processing}
            className="btn-primary bg-danger-700 hover:bg-danger-700/90"
          >
            {form.processing ? <Spinner /> : <Trash2 className="h-4 w-4" aria-hidden="true" />}
            Yes, {willDelete ? 'delete' : 'deactivate'}
          </button>
          <button type="button" onClick={() => setConfirming(false)} className="btn-ghost">
            Cancel
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => setConfirming(true)}
          className="btn-ghost mt-4 text-danger-700 hover:bg-danger-50"
        >
          <Trash2 className="h-4 w-4" aria-hidden="true" />
          {willDelete ? 'Delete account' : 'Deactivate account'}
        </button>
      )}
    </div>
  )
}
