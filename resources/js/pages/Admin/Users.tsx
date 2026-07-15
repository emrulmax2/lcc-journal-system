import { useState } from 'react'
import { Head, router, useForm } from '@inertiajs/react'
import { Info, ShieldCheck, UserMinus, UserPlus, Users as UsersIcon } from 'lucide-react'
import { AdminShell, EmptyState, Field, Panel, SELECT, Spinner } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import type { AdminPage } from '@/lib/admin'

type Member = {
  id: number
  name: string
  email: string
  isActive: boolean
  isSiteAdmin: boolean
  /** Scoped to THIS journal. The same person may hold a different set on the next one. */
  roles: string[]
}

type Role = { name: string; description: string }

type Props = AdminPage<{
  members: Member[]
  candidates: { id: number; name: string; email: string }[]
  roles: Role[]
}>

/**
 * Per-journal roles.
 *
 * Someone edits Journal A and reviews for Journal B. A global role cannot express that, so
 * every assignment on this page is scoped to THIS journal (Spatie teams, team = journal) —
 * the server sets the team context before syncing, or the role would attach globally and one
 * careless save would make a reviewer here an editor everywhere.
 */
export default function AdminUsers({ members, candidates, roles, journal, can, journals, meta }: Props) {
  const [adding, setAdding] = useState(false)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={journal.abbreviation ?? 'Journal'}
        title="People"
        description="Who does what on this journal — and only on this journal."
        actions={
          candidates.length > 0 && (
            <button type="button" onClick={() => setAdding((a) => !a)} className="btn-primary">
              <UserPlus className="h-4 w-4" aria-hidden="true" />
              Add someone
            </button>
          )
        }
      >
        {adding && (
          <div className="mb-8">
            <AddMember
              journalId={journal.id}
              candidates={candidates}
              roles={roles}
              onDone={() => setAdding(false)}
            />
          </div>
        )}

        <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
          <div>
            {members.length === 0 ? (
              <EmptyState
                icon={UsersIcon}
                title="Nobody has a role on this journal"
                body="Roles are per-journal. Someone with an editor role on another journal has no standing here until they are given one."
              />
            ) : (
              <RevealGroup className="space-y-4" stagger={0.06}>
                {members.map((member) => (
                  <RevealItem key={member.id}>
                    <MemberRow journalId={journal.id} member={member} roles={roles} />
                  </RevealItem>
                ))}
              </RevealGroup>
            )}
          </div>

          <aside className="lg:sticky lg:top-24 lg:self-start">
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
        </div>
      </AdminShell>
    </>
  )
}

function MemberRow({
  journalId,
  member,
  roles,
}: {
  journalId: number
  member: Member
  roles: Role[]
}) {
  const [saving, setSaving] = useState(false)

  const toggle = (role: string, on: boolean) => {
    const next = on ? [...member.roles, role] : member.roles.filter((r) => r !== role)

    setSaving(true)

    router.put(
      `/admin/journals/${journalId}/users/${member.id}`,
      { roles: next },
      { preserveScroll: true, onFinish: () => setSaving(false) },
    )
  }

  const remove = () => {
    setSaving(true)

    // An empty role list removes them FROM THIS JOURNAL. It touches no other journal's
    // assignment, because the team context scopes the sync.
    router.put(
      `/admin/journals/${journalId}/users/${member.id}`,
      { roles: [] },
      { preserveScroll: true, onFinish: () => setSaving(false) },
    )
  }

  return (
    <div className="card p-5">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="flex items-center gap-2 font-semibold text-ink-900">
            {member.name}
            {member.isSiteAdmin && (
              <span className="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2 py-0.5 text-[11px] font-semibold text-brand-800">
                <ShieldCheck className="h-3.5 w-3.5" aria-hidden="true" />
                Site admin
              </span>
            )}
            {!member.isActive && (
              <span className="rounded-full bg-ink-100 px-2 py-0.5 text-[11px] font-semibold text-ink-700">
                Inactive
              </span>
            )}
          </p>
          <p className="mt-0.5 text-sm text-ink-600">{member.email}</p>
        </div>

        <div className="flex items-center gap-2">
          {saving && <Spinner className="h-4 w-4 text-ink-500" />}
          <button
            type="button"
            onClick={remove}
            disabled={saving || member.roles.length === 0}
            className="btn-ghost text-danger-700 hover:bg-danger-50"
          >
            <UserMinus className="h-4 w-4" aria-hidden="true" />
            Remove from journal
          </button>
        </div>
      </div>

      <fieldset className="mt-4">
        <legend className="sr-only">Roles on this journal for {member.name}</legend>
        <div className="flex flex-wrap gap-2">
          {roles.map((role) => {
            const on = member.roles.includes(role.name)

            return (
              <label
                key={role.name}
                title={role.description}
                className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs
                            font-semibold transition-colors duration-200 ${
                              on
                                ? 'border-brand-700 bg-brand-700 text-white'
                                : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
                            }`}
              >
                <input
                  type="checkbox"
                  checked={on}
                  disabled={saving}
                  onChange={(e) => toggle(role.name, e.target.checked)}
                  className="h-3.5 w-3.5 cursor-pointer accent-white"
                />
                {role.name}
              </label>
            )
          })}
        </div>
      </fieldset>
    </div>
  )
}

function AddMember({
  journalId,
  candidates,
  roles,
  onDone,
}: {
  journalId: number
  candidates: { id: number; name: string; email: string }[]
  roles: Role[]
  onDone: () => void
}) {
  const form = useForm({
    user: '' as string,
    roles: [] as string[],
  })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    if (!form.data.user) return

    router.put(
      `/admin/journals/${journalId}/users/${form.data.user}`,
      { roles: form.data.roles },
      { preserveScroll: true, onSuccess: onDone },
    )
  }

  return (
    <Panel title="Add someone to this journal" description="The role applies here and nowhere else.">
      <form onSubmit={submit} className="grid gap-5 sm:grid-cols-[1fr_auto]">
        <Field label="Person" htmlFor="new-member" error={form.errors.user}>
          <select
            id="new-member"
            value={form.data.user}
            onChange={(e) => form.setData('user', e.target.value)}
            className={SELECT}
            required
          >
            <option value="">— choose —</option>
            {candidates.map((candidate) => (
              <option key={candidate.id} value={candidate.id}>
                {candidate.name} ({candidate.email})
              </option>
            ))}
          </select>
        </Field>

        <div className="flex items-end">
          <button type="submit" disabled={form.processing} className="btn-primary">
            {form.processing ? <Spinner /> : <UserPlus className="h-4 w-4" aria-hidden="true" />}
            Add
          </button>
        </div>

        <fieldset className="sm:col-span-2">
          <legend className="text-sm font-medium text-ink-800">Roles</legend>
          <div className="mt-2 flex flex-wrap gap-2">
            {roles.map((role) => {
              const on = form.data.roles.includes(role.name)

              return (
                <label
                  key={role.name}
                  title={role.description}
                  className={`inline-flex cursor-pointer items-center gap-2 rounded-full border px-3 py-1.5 text-xs
                              font-semibold transition-colors duration-200 ${
                                on
                                  ? 'border-brand-700 bg-brand-700 text-white'
                                  : 'border-ink-300 bg-white text-ink-700 hover:border-ink-900 hover:bg-ink-50'
                              }`}
                >
                  <input
                    type="checkbox"
                    checked={on}
                    onChange={(e) =>
                      form.setData(
                        'roles',
                        e.target.checked
                          ? [...form.data.roles, role.name]
                          : form.data.roles.filter((r) => r !== role.name),
                      )
                    }
                    className="h-3.5 w-3.5 cursor-pointer accent-white"
                  />
                  {role.name}
                </label>
              )
            })}
          </div>
        </fieldset>
      </form>
    </Panel>
  )
}
