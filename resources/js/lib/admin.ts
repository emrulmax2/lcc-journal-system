import type { Meta } from '@/lib/props'

/**
 * The Inertia page-prop contract for the EDITORIAL ADMIN.
 *
 * Derived from App\Support\AdminChrome and the App\Http\Controllers\Admin\* controllers.
 *
 * Two things are NOT in here, and their absence is the design:
 *
 *  1. `crossrefPassword`. It is write-only. It is not in any prop, any resource or any
 *     response, and there is no shape of this type that could hold it. The settings page
 *     receives `crossref_password_set: boolean` and nothing else — see SettingsController.
 *
 *  2. A `doiPrefix` that is always a string. It is `string | null`, because until Crossref
 *     issues one it IS null, and the compiler should force every screen to say so rather
 *     than print an empty box or, worse, invent "10.xxxx".
 */

export type PublicationModel = 'continuous' | 'issue_based'

export type AdminJournal = {
  id: number
  slug: string
  title: string
  abbreviation: string | null
  publicationModel: PublicationModel
  /** NULL until Crossref issues one. Not an error — a state. */
  doiPrefix: string | null
  canMintDois: boolean
}

/**
 * What the policies say this person may do ON THIS JOURNAL. Used to decide what to RENDER.
 * Never what to ALLOW — every mutation is authorised again on the server. `production` is
 * the case to keep in mind: manageArticles true, publish false.
 */
export type AdminAbilities = {
  manageArticles: boolean
  manageIssues: boolean
  manageSettings: boolean
  manageUsers: boolean
  depositDois: boolean
  publish: boolean

  /**
   * THE EDITORIAL COCKPIT. Decide what renders on the submission screens — the Submissions
   * tab, the reviewer-assignment form, the decision form. `production` has none of these;
   * a section-editor has all three but NOT `publish`. Never what to ALLOW — every mutation
   * is authorised again on the server.
   */
  viewAllSubmissions: boolean
  assignReviewers: boolean
  recordDecision: boolean

  /**
   * THE ONE ABILITY HERE THAT IS NOT ABOUT A JOURNAL.
   *
   * Site content — the footer, the privacy policy, the navigation, the homepage — belongs to
   * the SITE. Roles are per-journal (Spatie teams), so "may you edit the privacy policy?" has
   * no per-journal answer, and it is a Gate with no model (`manage-site-content`) rather than
   * a JournalPolicy method. It rides in `can` because it decides whether the Content tab
   * renders, and that tab is on every admin screen.
   */
  manageSiteContent: boolean

  /**
   * ALSO NOT ABOUT A JOURNAL — and deliberately not the same field as `manageUsers`.
   *
   * manageUsers    : may you set roles ON THIS JOURNAL. Per-journal, a policy question.
   * manageAccounts : may you create a person at all. A person is not of a journal.
   *
   * Two tabs, because two jobs: "who edits JCD&MS" and "who has an account".
   */
  manageAccounts: boolean

  /** Site admin only — a role definition is team-agnostic, so editing it hits every journal. */
  manageRoles: boolean
}

export type AdminChrome = {
  journal: AdminJournal
  can: AdminAbilities
  journals: { id: number; title: string; abbreviation: string | null }[]
}

export type AdminPage<T> = T & AdminChrome & { meta: Meta }

/** Shared props (HandleInertiaRequests). `publishErrors` is the COMPLETE pre-flight list. */
export type AdminShared = {
  flash: {
    success: string | null
    error: string | null
    /** EVERY failure from the publish gate, not the first. Rendered as one list. */
    publishErrors: string[] | null
  }
  now: string
}

export type ArticleStatus = 'draft' | 'published' | 'withdrawn'
export type IssueStatus = 'draft' | 'published'
export type DepositStatus = 'queued' | 'depositing' | 'submitted' | 'registered' | 'failed'
export type DepositItemStatus = 'pending' | 'registered' | 'failed'

export function issuesHref(journalId: number): string {
  return `/admin/journals/${journalId}/issues`
}

/** The editorial queue — journal-scoped, like issues and deposits. */
export function submissionsHref(journalId: number): string {
  return `/admin/journals/${journalId}/submissions`
}

/** One manuscript's detail. Bound by submission id — globally unique, no journal needed. */
export function submissionHref(submissionId: number): string {
  return `/admin/submissions/${submissionId}`
}

export function depositsHref(journalId: number): string {
  return `/admin/journals/${journalId}/deposits`
}

export function settingsHref(journalId: number): string {
  return `/admin/journals/${journalId}/settings`
}

/** Roles ON ONE JOURNAL. Not the same screen as `peopleHref` — see AdminAbilities. */
export function usersHref(journalId: number): string {
  return `/admin/journals/${journalId}/users`
}

/**
 * SITE-WIDE accounts and role definitions. No journal in the URL, because a person is not
 * of a journal and a role definition is not a journal's either.
 */
export const peopleHref = {
  accounts: '/admin/users',
  newAccount: '/admin/users/create',
  editAccount: (id: number) => `/admin/users/${id}/edit`,
  roles: '/admin/roles',
} as const

export function newArticleHref(journalId: number): string {
  return `/admin/journals/${journalId}/articles/create`
}

export function editArticleHref(articleId: number): string {
  return `/admin/articles/${articleId}/edit`
}

export function issueHref(issueId: number): string {
  return `/admin/issues/${issueId}`
}

/** Bytes -> "1.2 MB". Pinned to en-GB so SSR and the browser agree. */
export function formatBytes(bytes: number | null): string {
  if (bytes === null || Number.isNaN(bytes)) return '—'
  if (bytes < 1024) return `${bytes} B`
  const units = ['kB', 'MB', 'GB']
  let value = bytes / 1024
  let unit = 0
  while (value >= 1024 && unit < units.length - 1) {
    value /= 1024
    unit += 1
  }
  return `${value.toLocaleString('en-GB', { maximumFractionDigits: 1 })} ${units[unit]}`
}
