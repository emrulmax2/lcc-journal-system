import { Head, useForm } from '@inertiajs/react'
import { KeyRound, Save } from 'lucide-react'
import { Field, Flash, INPUT, Panel, Spinner } from '@/components/admin/Shell'
import { Reveal } from '@/components/Reveal'
import type { Meta } from '@/lib/props'

type Account = {
  givenName: string | null
  familyName: string | null
  /** The display fallback, for accounts predating the given/family split. */
  name: string
  email: string
  affiliation: string | null
  orcid: string | null
}

type Props = {
  account: Account
  meta: Meta
}

/**
 * MY ACCOUNT — yourself. The counterpart to Admin/AccountEditor, which is someone else
 * editing you and carries the fields this one deliberately cannot: is_active, is_site_admin
 * and roles on every journal. Nothing here changes what you may DO, only who you are.
 *
 * TWO FORMS, NOT ONE, and that is the point. Inertia hands validation errors back per
 * request, so a single form posting both would mean a mistyped current password re-renders
 * the page with the corrected affiliation still unsaved and the user unsure which half
 * landed. Separate requests fail separately.
 *
 * No AdminShell here: that shell needs an AdminChrome with a journal, and a reviewer with no
 * editorial role anywhere has none — this page has to work for them too.
 */
export default function Account({ account, meta }: Props) {
  const profile = useForm({
    given_name: account.givenName ?? '',
    family_name: account.familyName ?? '',
    email: account.email,
    affiliation: account.affiliation ?? '',
    orcid: account.orcid ?? '',
  })

  const password = useForm({
    current_password: '',
    password: '',
    password_confirmation: '',
  })

  const saveProfile = (e: React.FormEvent) => {
    e.preventDefault()
    profile.put('/account', { preserveScroll: true })
  }

  const savePassword = (e: React.FormEvent) => {
    e.preventDefault()
    password.put('/account/password', {
      preserveScroll: true,
      // The three boxes are cleared whichever way it goes. On success they are spent; on
      // failure, leaving a rejected password sitting in the DOM helps nobody re-type it.
      onFinish: () => password.reset(),
    })
  }

  const displayName =
    [account.givenName, account.familyName].filter(Boolean).join(' ') || account.name

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal>
            <p className="eyebrow">My account</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">{displayName}</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Your name, contact details and password. What you may do on each journal is set
              by an editor, not here.
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page py-12">
        <Flash />

        <div className="grid max-w-3xl gap-8">
          <form onSubmit={saveProfile}>
            <Panel
              title="Your details"
              description="Given and family names are separate because Crossref deposits them separately."
              actions={
                <button type="submit" disabled={profile.processing} className="btn-primary">
                  {profile.processing ? (
                    <Spinner />
                  ) : (
                    <Save className="h-4 w-4" aria-hidden="true" />
                  )}
                  Save details
                </button>
              }
            >
              <div className="grid gap-5 sm:grid-cols-2">
                <Field label="Given name" htmlFor="given_name" error={profile.errors.given_name}>
                  <input
                    id="given_name"
                    value={profile.data.given_name}
                    onChange={(e) => profile.setData('given_name', e.target.value)}
                    className={INPUT}
                    autoComplete="given-name"
                    required
                  />
                </Field>

                <Field label="Family name" htmlFor="family_name" error={profile.errors.family_name}>
                  <input
                    id="family_name"
                    value={profile.data.family_name}
                    onChange={(e) => profile.setData('family_name', e.target.value)}
                    className={INPUT}
                    autoComplete="family-name"
                    required
                  />
                </Field>

                <Field
                  label="Email"
                  htmlFor="email"
                  error={profile.errors.email}
                  hint="This is your login. Changing it changes how you sign in."
                  className="sm:col-span-2"
                >
                  <input
                    id="email"
                    type="email"
                    value={profile.data.email}
                    onChange={(e) => profile.setData('email', e.target.value)}
                    className={INPUT}
                    autoComplete="email"
                    required
                  />
                </Field>

                <Field
                  label="Affiliation"
                  htmlFor="affiliation"
                  error={profile.errors.affiliation}
                  className="sm:col-span-2"
                >
                  <input
                    id="affiliation"
                    value={profile.data.affiliation}
                    onChange={(e) => profile.setData('affiliation', e.target.value)}
                    className={INPUT}
                    placeholder="London Churchill College"
                  />
                </Field>

                <Field
                  label="ORCID"
                  htmlFor="orcid"
                  error={profile.errors.orcid}
                  hint="Optional. Deposited to Crossref when you author an article."
                  className="sm:col-span-2"
                >
                  <input
                    id="orcid"
                    value={profile.data.orcid}
                    onChange={(e) => profile.setData('orcid', e.target.value)}
                    className={`${INPUT} font-mono`}
                    placeholder="0000-0002-1825-0097"
                  />
                </Field>
              </div>
            </Panel>
          </form>

          <form onSubmit={savePassword}>
            <Panel
              title="Change password"
              description="At least 12 characters. You need your current one to set a new one."
              actions={
                <button type="submit" disabled={password.processing} className="btn-secondary">
                  {password.processing ? (
                    <Spinner />
                  ) : (
                    <KeyRound className="h-4 w-4" aria-hidden="true" />
                  )}
                  Change password
                </button>
              }
            >
              {/*
                A hidden username field, and it is not dead markup: without it password
                managers cannot tell which account these boxes belong to, and offer to save
                the new password against the wrong entry — or against no entry at all.
              */}
              <input
                type="text"
                name="username"
                value={account.email}
                autoComplete="username"
                readOnly
                hidden
              />

              <div className="grid gap-5 sm:grid-cols-2">
                <Field
                  label="Current password"
                  htmlFor="current_password"
                  error={password.errors.current_password}
                  className="sm:col-span-2"
                >
                  <input
                    id="current_password"
                    type="password"
                    value={password.data.current_password}
                    onChange={(e) => password.setData('current_password', e.target.value)}
                    className={INPUT}
                    autoComplete="current-password"
                    required
                  />
                </Field>

                <Field label="New password" htmlFor="password" error={password.errors.password}>
                  <input
                    id="password"
                    type="password"
                    value={password.data.password}
                    onChange={(e) => password.setData('password', e.target.value)}
                    className={INPUT}
                    autoComplete="new-password"
                    minLength={12}
                    required
                  />
                </Field>

                <Field
                  label="Repeat new password"
                  htmlFor="password_confirmation"
                  error={password.errors.password_confirmation}
                >
                  <input
                    id="password_confirmation"
                    type="password"
                    value={password.data.password_confirmation}
                    onChange={(e) => password.setData('password_confirmation', e.target.value)}
                    className={INPUT}
                    autoComplete="new-password"
                    minLength={12}
                    required
                  />
                </Field>
              </div>
            </Panel>
          </form>
        </div>
      </div>
    </>
  )
}
