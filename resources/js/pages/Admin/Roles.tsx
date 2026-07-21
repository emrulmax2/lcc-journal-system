import { useState } from 'react'
import { Head, useForm } from '@inertiajs/react'
import { AlertTriangle, Info, Save, Users } from 'lucide-react'
import { PeopleShell } from '@/components/admin/PeopleShell'
import { Banner, Spinner } from '@/components/admin/Shell'
import { RevealGroup, RevealItem } from '@/components/Reveal'
import type { Meta } from '@/lib/props'

type Role = {
  name: string
  description: string
  permissions: string[]
  /** How many people hold this role, across all journals. */
  holders: number
}

type Catalogue = {
  group: string
  permissions: { name: string; description: string }[]
}[]

type Props = {
  roles: Role[]
  catalogue: Catalogue
  meta: Meta
}

/**
 * What each role MEANS.
 *
 * ONE ROLE PER CARD, saved on its own — not a giant matrix with one Save. A role's
 * permissions apply on EVERY journal it is granted on, so a save here is not a small edit;
 * making six of them at once, from one button, is how you change something you did not mean
 * to and cannot see.
 *
 * There is deliberately no "new role" button. The six names are not data — JournalPolicy,
 * AdminChrome and UserController reference them by name, so a seventh created here would
 * carry permissions that no code ever asks for. It would look like it worked and do
 * nothing. Adding a role is a code change.
 */
export default function Roles({ roles, catalogue, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <PeopleShell
        title="Roles & permissions"
        description="What each role may do — on every journal it is granted on."
        canManageRoles
      >
        <div className="mb-8">
          <Banner tone="gold" icon={AlertTriangle} title="These apply everywhere at once">
            A role is defined once and granted per journal. Changing what{' '}
            <span className="font-mono">journal-editor</span> may do changes it for every journal,
            including ones created later. To change one person on one journal, edit their account
            instead.
          </Banner>
        </div>

        <RevealGroup className="space-y-6" stagger={0.05}>
          {roles.map((role) => (
            <RevealItem key={role.name}>
              <RoleCard role={role} catalogue={catalogue} />
            </RevealItem>
          ))}
        </RevealGroup>
      </PeopleShell>
    </>
  )
}

function RoleCard({ role, catalogue }: { role: Role; catalogue: Catalogue }) {
  const [open, setOpen] = useState(false)
  const form = useForm({ permissions: role.permissions })

  const submit = (e: React.FormEvent) => {
    e.preventDefault()
    form.put(`/admin/roles/${role.name}`, { preserveScroll: true })
  }

  const toggle = (permission: string, on: boolean) => {
    form.setData(
      'permissions',
      on
        ? [...form.data.permissions, permission]
        : form.data.permissions.filter((p) => p !== permission),
    )
  }

  return (
    <form onSubmit={submit} className="card p-6">
      <div className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <h2 className="font-mono text-sm font-semibold text-ink-900">{role.name}</h2>
          <p className="mt-1 max-w-prose text-sm text-ink-600">{role.description}</p>
        </div>

        <p className="inline-flex shrink-0 items-center gap-1.5 rounded-full bg-ink-50 px-3 py-1 text-xs text-ink-700">
          <Users className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
          {role.holders} {role.holders === 1 ? 'person holds this' : 'people hold this'}
        </p>
      </div>

      {form.errors.permissions && (
        <div className="mt-4">
          <Banner tone="danger" icon={AlertTriangle} title="That change was refused">
            {form.errors.permissions}
          </Banner>
        </div>
      )}

      <button
        type="button"
        onClick={() => setOpen((o) => !o)}
        aria-expanded={open}
        className="btn-ghost mt-4"
      >
        {open ? 'Hide permissions' : `Show permissions (${form.data.permissions.length})`}
      </button>

      {!open && (
        <ul className="mt-3 flex flex-wrap gap-1.5">
          {form.data.permissions.map((permission) => (
            <li
              key={permission}
              className="rounded-full border border-ink-200 bg-ink-50 px-2.5 py-0.5 font-mono text-[11px] text-ink-700"
            >
              {permission}
            </li>
          ))}
        </ul>
      )}

      {open && (
        <>
          <div className="mt-5 space-y-6">
            {catalogue.map((group) => (
              <fieldset key={group.group}>
                <legend className="text-sm font-semibold text-ink-900">{group.group}</legend>

                <div className="mt-3 space-y-2.5">
                  {group.permissions.map((permission) => {
                    const on = form.data.permissions.includes(permission.name)

                    return (
                      <label
                        key={permission.name}
                        htmlFor={`${role.name}-${permission.name}`}
                        className="flex cursor-pointer items-start gap-3 rounded-lg p-2 transition-colors duration-200 hover:bg-ink-50"
                      >
                        <input
                          id={`${role.name}-${permission.name}`}
                          type="checkbox"
                          checked={on}
                          onChange={(e) => toggle(permission.name, e.target.checked)}
                          className="mt-0.5 h-4 w-4 shrink-0 rounded accent-brand-700"
                        />
                        <span className="min-w-0">
                          <span className="block font-mono text-xs font-semibold text-ink-900">
                            {permission.name}
                          </span>
                          <span className="mt-0.5 block text-xs text-ink-600">
                            {permission.description}
                          </span>
                        </span>
                      </label>
                    )
                  })}
                </div>
              </fieldset>
            ))}
          </div>

          {role.name === 'production' && form.data.permissions.includes('journal.publish') && (
            <p className="mt-5 flex items-start gap-2 rounded-lg bg-gold-50 p-3 text-xs text-gold-700">
              <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
              <span>
                <span className="font-mono">production</span> is designed without{' '}
                <span className="font-mono">journal.publish</span>: it prepares everything and
                stops short of the one action that freezes a URL and spends money at Crossref.
                Granting it here removes that separation.
              </span>
            </p>
          )}

          <div className="mt-6 flex items-center gap-3 border-t border-ink-200 pt-5">
            <button type="submit" disabled={form.processing || !form.isDirty} className="btn-primary">
              {form.processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
              Save {role.name}
            </button>

            {form.isDirty && !form.processing && (
              <p className="text-xs text-ink-500">
                Unsaved — this will change {role.holders}{' '}
                {role.holders === 1 ? "person's" : "people's"} access.
              </p>
            )}
          </div>
        </>
      )}
    </form>
  )
}
