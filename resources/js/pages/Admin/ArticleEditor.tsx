import { useState } from 'react'
import { Head, Link, useForm } from '@inertiajs/react'
import {
  AlertTriangle,
  ArrowDown,
  ArrowUp,
  Building2,
  FileUp,
  GripVertical,
  Info,
  Link2,
  Lock,
  Plus,
  Save,
  Trash2,
  Users,
} from 'lucide-react'
import {
  AdminShell,
  Banner,
  Field,
  INPUT,
  Panel,
  SELECT,
  Spinner,
} from '@/components/admin/Shell'
import { PublishButton } from '@/components/admin/PublishButton'
import { ArticleBadge } from '@/components/admin/Status'
import { formatBytes, type AdminPage, type ArticleStatus } from '@/lib/admin'

type Author = {
  given_name: string
  family_name: string
  affiliation: string | null
  email: string | null
  orcid: string | null
  is_corresponding: boolean
}

type Reference = { raw_text: string; doi: string | null }

type Article = {
  id: number
  title: string
  slug: string
  abstract: string | null
  body: string | null
  keywords: string[]
  journal_section_id: number | null
  issue_id: number | null
  sequence: number | null
  first_page: number | null
  last_page: number | null
  corporate_author: string | null
  status: ArticleStatus
  statusLabel: string
  isPublished: boolean
  isFrozen: boolean
  publishedAt: string | null
  doiSuffix: string | null
  doi: string | null
  landingUrl: string
  authors: Author[]
  references: Reference[]
  pdf: { name: string; size: number | null; url: string } | null
}

type Props = AdminPage<{
  article: Article | null
  issues: { id: number; label: string; isPublished: boolean }[]
  sections: { id: number; name: string; doiEligible: boolean }[]
}>

const EMPTY_AUTHOR: Author = {
  given_name: '',
  family_name: '',
  affiliation: '',
  email: '',
  orcid: '',
  is_corresponding: false,
}

/** "0000-0002-1825-0097". Nothing here completes, matches or infers one. */
const ORCID_PATTERN = '\\d{4}-\\d{4}-\\d{4}-\\d{3}[0-9X]'

export default function ArticleEditor({
  article,
  issues,
  sections,
  journal,
  can,
  journals,
  meta,
}: Props) {
  const frozen = article?.isFrozen ?? false

  const [corporate, setCorporate] = useState(Boolean(article?.corporate_author))

  const form = useForm({
    title: article?.title ?? '',
    slug: article?.slug ?? '',
    abstract: article?.abstract ?? '',
    body: article?.body ?? '',
    keywords: (article?.keywords ?? []).join(', '),
    journal_section_id: article?.journal_section_id ?? '',
    issue_id: article?.issue_id ?? '',
    sequence: article?.sequence ?? '',
    first_page: article?.first_page ?? '',
    last_page: article?.last_page ?? '',
    corporate_author: article?.corporate_author ?? '',
    authors: (article?.authors ?? []) as Author[],
    references: (article?.references ?? []) as Reference[],
    pdf: null as File | null,
  })

  const { data, setData, errors, processing } = form

  const submit = (e: React.FormEvent) => {
    e.preventDefault()

    // transform() mutates the form and returns void, so it cannot be chained onto post().
    form.transform((payload) => ({
      ...payload,

      // Comma-separated in the box, an array on the wire — `keywords` is a JSON column.
      keywords: payload.keywords
        .split(',')
        .map((k: string) => k.trim())
        .filter(Boolean),

      // An article has NAMED AUTHORS OR A CORPORATE AUTHOR, never both. The toggle clears the
      // other side here so the two can never be posted together — PublishArticleAction refuses
      // that combination, and so does the endpoint.
      corporate_author: corporate ? payload.corporate_author : '',
      authors: corporate ? [] : payload.authors,

      // Empty selects arrive as '' from the DOM. NULL is what "no issue" and "no section"
      // actually are, and '' would fail an exists rule that a null passes.
      journal_section_id: payload.journal_section_id === '' ? null : payload.journal_section_id,
      issue_id: payload.issue_id === '' ? null : payload.issue_id,
      sequence: payload.sequence === '' ? null : payload.sequence,
      first_page: payload.first_page === '' ? null : payload.first_page,
      last_page: payload.last_page === '' ? null : payload.last_page,
    }))

    form.post(
      article ? `/admin/articles/${article.id}` : `/admin/journals/${journal.id}/articles`,
      { preserveScroll: true, forceFormData: true },
    )
  }

  // --- Author repeater ---

  const setAuthors = (next: Author[]) => setData('authors', next)

  const moveAuthor = (index: number, delta: number) => {
    const target = index + delta
    if (target < 0 || target >= data.authors.length) return
    const next = [...data.authors]
    ;[next[index], next[target]] = [next[target], next[index]]
    setAuthors(next)
  }

  const dropAuthor = (from: number, to: number) => {
    if (from === to || Number.isNaN(from)) return
    const next = [...data.authors]
    const [moved] = next.splice(from, 1)
    next.splice(to, 0, moved)
    setAuthors(next)
  }

  const derivedDoi =
    journal.doiPrefix && article?.doiSuffix ? `${journal.doiPrefix}/${article.doiSuffix}` : null

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <AdminShell
        chrome={{ journal, can, journals }}
        eyebrow={article ? 'Edit article' : 'New article'}
        title={article ? article.title || 'Untitled' : 'New article'}
        description={
          article
            ? undefined
            : 'It is created as a DRAFT. Nothing is public and no DOI exists until it is published.'
        }
        actions={
          <>
            {article && <ArticleBadge status={article.status} label={article.statusLabel} />}

            {article && can.publish && !article.isFrozen && (
              <PublishButton
                url={`/admin/articles/${article.id}/publish`}
                label="Publish"
                heading="Publish this article?"
                summary="It goes live immediately, and its identity becomes permanent."
                permanentUrl={article.landingUrl}
                doi={derivedDoi}
                canMintDois={journal.canMintDois}
              />
            )}
          </>
        }
      >
        {frozen && (
          <div className="mb-8">
            <Banner tone="info" icon={Lock} title="Published — the identity of this article is frozen">
              The slug, the position in the issue and the DOI suffix are permanent. Editing the
              title will NOT regenerate the slug: a changed URL is a dead DOI, and there is no
              undo. Everything else on this page can still be corrected, and a redeposit updates
              the Crossref record.
            </Banner>
          </div>
        )}

        <form onSubmit={submit} className="grid gap-8 lg:grid-cols-[1fr_320px]">
          <div className="space-y-8">
            {/* ------------------------------- Metadata ------------------------------- */}
            <Panel title="Metadata" description="What the citation, the indexes and Crossref all read.">
              <div className="space-y-5">
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

                {/* FROZEN AT PUBLICATION. Rendered read-only, with the reason. */}
                <Field
                  label="Slug — the permanent URL"
                  htmlFor="slug"
                  error={errors.slug}
                  hint={
                    frozen
                      ? 'Frozen at publication — this is the permanent URL and the DOI resolves to it.'
                      : 'Lowercase letters, numbers and hyphens. Frozen the moment the article is published.'
                  }
                >
                  <input
                    id="slug"
                    type="text"
                    value={data.slug}
                    onChange={(e) => setData('slug', e.target.value)}
                    className={INPUT}
                    readOnly={frozen}
                    disabled={frozen}
                    required={!frozen}
                    pattern="[a-z0-9]+(?:-[a-z0-9]+)*"
                  />
                </Field>

                <Field
                  label="Abstract"
                  htmlFor="abstract"
                  error={errors.abstract}
                  hint="Required to publish — indexes and Crossref both need one."
                >
                  <textarea
                    id="abstract"
                    rows={6}
                    value={data.abstract ?? ''}
                    onChange={(e) => setData('abstract', e.target.value)}
                    className={INPUT}
                  />
                </Field>

                <Field
                  label="Keywords"
                  htmlFor="keywords"
                  error={errors.keywords}
                  hint="Comma-separated. Up to twelve."
                >
                  <input
                    id="keywords"
                    type="text"
                    value={data.keywords}
                    onChange={(e) => setData('keywords', e.target.value)}
                    className={INPUT}
                    placeholder="educational leadership, transition, mentoring"
                  />
                </Field>

                <div className="grid gap-5 sm:grid-cols-2">
                  <Field label="Section" htmlFor="section" error={errors.journal_section_id}>
                    <select
                      id="section"
                      value={data.journal_section_id ?? ''}
                      onChange={(e) => setData('journal_section_id', e.target.value)}
                      className={SELECT}
                    >
                      <option value="">— none —</option>
                      {sections.map((section) => (
                        <option key={section.id} value={section.id}>
                          {section.name}
                          {section.doiEligible ? '' : ' (no DOI — front matter)'}
                        </option>
                      ))}
                    </select>
                  </Field>

                  {/* Issues exist only for an issue-based journal. For a continuous one the
                      whole control is absent, not disabled. */}
                  {journal.publicationModel === 'issue_based' && (
                    <Field label="Issue" htmlFor="issue" error={errors.issue_id}>
                      <select
                        id="issue"
                        value={data.issue_id ?? ''}
                        onChange={(e) => setData('issue_id', e.target.value)}
                        className={SELECT}
                      >
                        <option value="">— not placed —</option>
                        {issues.map((issue) => (
                          <option key={issue.id} value={issue.id}>
                            {issue.label}
                            {issue.isPublished ? ' (published)' : ''}
                          </option>
                        ))}
                      </select>
                    </Field>
                  )}
                </div>

                <div className="grid gap-5 sm:grid-cols-3">
                  <Field
                    label="Position in issue"
                    htmlFor="sequence"
                    error={errors.sequence}
                    hint={
                      frozen
                        ? 'Frozen at publication — the DOI suffix is derived from it.'
                        : 'The DOI suffix is derived from this.'
                    }
                  >
                    <input
                      id="sequence"
                      type="number"
                      min={1}
                      value={data.sequence ?? ''}
                      onChange={(e) => setData('sequence', e.target.value)}
                      className={INPUT}
                      readOnly={frozen}
                      disabled={frozen}
                    />
                  </Field>

                  <Field label="First page" htmlFor="first_page" error={errors.first_page}>
                    <input
                      id="first_page"
                      type="number"
                      min={1}
                      value={data.first_page ?? ''}
                      onChange={(e) => setData('first_page', e.target.value)}
                      className={INPUT}
                    />
                  </Field>

                  <Field label="Last page" htmlFor="last_page" error={errors.last_page}>
                    <input
                      id="last_page"
                      type="number"
                      min={1}
                      value={data.last_page ?? ''}
                      onChange={(e) => setData('last_page', e.target.value)}
                      className={INPUT}
                    />
                  </Field>
                </div>
              </div>
            </Panel>

            {/* -------------------------------- Authors ------------------------------- */}
            <Panel
              title="Authors"
              description="Order is meaningful — it is the contribution order, and Crossref deposits it."
              actions={
                !corporate && (
                  <button
                    type="button"
                    onClick={() => setAuthors([...data.authors, { ...EMPTY_AUTHOR }])}
                    className="btn-secondary"
                  >
                    <Plus className="h-4 w-4" aria-hidden="true" />
                    Add author
                  </button>
                )
              }
            >
              {/* An article has named people OR a corporate author. Never both — Crossref
                  accepts one or the other, and depositing both is a contradiction. */}
              <label className="flex cursor-pointer items-start gap-3 rounded-lg border border-ink-200 bg-ink-50 p-4">
                <input
                  type="checkbox"
                  checked={corporate}
                  onChange={(e) => {
                    setCorporate(e.target.checked)
                    if (e.target.checked) setAuthors([])
                    else setData('corporate_author', '')
                  }}
                  className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
                />
                <span className="text-sm">
                  <span className="flex items-center gap-2 font-semibold text-ink-900">
                    <Building2 className="h-4 w-4 text-ink-600" aria-hidden="true" />
                    This article has a corporate author
                  </span>
                  <span className="mt-1 block text-ink-600">
                    An editorial by a research centre rather than named people. Crossref receives
                    an <span className="font-mono text-xs">&lt;organization&gt;</span>, not a
                    person. The author list is then empty — that is the correct shape, not a
                    missing one.
                  </span>
                </span>
              </label>

              {corporate ? (
                <div className="mt-5">
                  <Field
                    label="Corporate author"
                    htmlFor="corporate_author"
                    error={errors.corporate_author}
                  >
                    <input
                      id="corporate_author"
                      type="text"
                      value={data.corporate_author ?? ''}
                      onChange={(e) => setData('corporate_author', e.target.value)}
                      className={INPUT}
                      placeholder="Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College"
                    />
                  </Field>
                </div>
              ) : data.authors.length === 0 ? (
                <p className="mt-5 flex items-start gap-2 text-sm text-ink-600">
                  <Users className="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
                  No authors yet. An article needs at least one named author, or a corporate
                  author, before it can be published.
                </p>
              ) : (
                <ol className="mt-5 space-y-4">
                  {data.authors.map((author, index) => (
                    <li
                      key={index}
                      draggable
                      onDragStart={(e) => e.dataTransfer.setData('text/plain', String(index))}
                      onDragOver={(e) => e.preventDefault()}
                      onDrop={(e) => {
                        e.preventDefault()
                        dropAuthor(Number(e.dataTransfer.getData('text/plain')), index)
                      }}
                      className="rounded-lg border border-ink-200 p-4"
                    >
                      <div className="flex items-start gap-3">
                        <span className="mt-1 cursor-grab text-ink-400 active:cursor-grabbing" aria-hidden="true">
                          <GripVertical className="h-5 w-5" />
                        </span>

                        <span className="mt-0.5 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-100 font-mono text-xs font-semibold text-ink-700">
                          {index + 1}
                        </span>

                        <div className="min-w-0 flex-1 space-y-4">
                          <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                              label="Given name"
                              htmlFor={`author-${index}-given`}
                              error={errors[`authors.${index}.given_name` as keyof typeof errors]}
                            >
                              <input
                                id={`author-${index}-given`}
                                type="text"
                                value={author.given_name}
                                onChange={(e) => {
                                  const next = [...data.authors]
                                  next[index] = { ...author, given_name: e.target.value }
                                  setAuthors(next)
                                }}
                                className={INPUT}
                              />
                            </Field>

                            <Field
                              label="Family name"
                              htmlFor={`author-${index}-family`}
                              error={errors[`authors.${index}.family_name` as keyof typeof errors]}
                            >
                              <input
                                id={`author-${index}-family`}
                                type="text"
                                value={author.family_name}
                                onChange={(e) => {
                                  const next = [...data.authors]
                                  next[index] = { ...author, family_name: e.target.value }
                                  setAuthors(next)
                                }}
                                className={INPUT}
                              />
                            </Field>
                          </div>

                          <Field
                            label="Affiliation"
                            htmlFor={`author-${index}-affiliation`}
                            error={errors[`authors.${index}.affiliation` as keyof typeof errors]}
                          >
                            <input
                              id={`author-${index}-affiliation`}
                              type="text"
                              value={author.affiliation ?? ''}
                              onChange={(e) => {
                                const next = [...data.authors]
                                next[index] = { ...author, affiliation: e.target.value }
                                setAuthors(next)
                              }}
                              className={INPUT}
                            />
                          </Field>

                          <div className="grid gap-4 sm:grid-cols-2">
                            <Field
                              label="ORCID"
                              htmlFor={`author-${index}-orcid`}
                              error={errors[`authors.${index}.orcid` as keyof typeof errors]}
                              hint="Leave empty unless you have the real one. A wrong ORCID attributes this work to a real, identifiable other person — in every index that reads the deposit."
                            >
                              <input
                                id={`author-${index}-orcid`}
                                type="text"
                                inputMode="numeric"
                                value={author.orcid ?? ''}
                                onChange={(e) => {
                                  const next = [...data.authors]
                                  next[index] = { ...author, orcid: e.target.value }
                                  setAuthors(next)
                                }}
                                className={`${INPUT} font-mono`}
                                placeholder="0000-0002-1825-0097"
                                pattern={ORCID_PATTERN}
                                // No autocomplete, no lookup, no suggestion list. Deliberate.
                                autoComplete="off"
                              />
                            </Field>

                            <Field
                              label="Email"
                              htmlFor={`author-${index}-email`}
                              error={errors[`authors.${index}.email` as keyof typeof errors]}
                            >
                              <input
                                id={`author-${index}-email`}
                                type="email"
                                value={author.email ?? ''}
                                onChange={(e) => {
                                  const next = [...data.authors]
                                  next[index] = { ...author, email: e.target.value }
                                  setAuthors(next)
                                }}
                                className={INPUT}
                              />
                            </Field>
                          </div>

                          <label className="flex cursor-pointer items-center gap-2 text-sm text-ink-700">
                            <input
                              type="checkbox"
                              checked={author.is_corresponding}
                              onChange={(e) => {
                                const next = [...data.authors]
                                next[index] = { ...author, is_corresponding: e.target.checked }
                                setAuthors(next)
                              }}
                              className="h-4 w-4 cursor-pointer accent-brand-700"
                            />
                            Corresponding author
                          </label>
                        </div>

                        <div className="flex shrink-0 flex-col gap-1">
                          <button
                            type="button"
                            onClick={() => moveAuthor(index, -1)}
                            disabled={index === 0}
                            aria-label={`Move author ${index + 1} up`}
                            className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-ink-300
                                       text-ink-700 transition-colors duration-200 hover:border-ink-900 hover:bg-ink-50
                                       disabled:cursor-not-allowed disabled:opacity-40"
                          >
                            <ArrowUp className="h-4 w-4" aria-hidden="true" />
                          </button>
                          <button
                            type="button"
                            onClick={() => moveAuthor(index, 1)}
                            disabled={index === data.authors.length - 1}
                            aria-label={`Move author ${index + 1} down`}
                            className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-ink-300
                                       text-ink-700 transition-colors duration-200 hover:border-ink-900 hover:bg-ink-50
                                       disabled:cursor-not-allowed disabled:opacity-40"
                          >
                            <ArrowDown className="h-4 w-4" aria-hidden="true" />
                          </button>
                          <button
                            type="button"
                            onClick={() => setAuthors(data.authors.filter((_, i) => i !== index))}
                            aria-label={`Remove author ${index + 1}`}
                            className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg border border-ink-300
                                       text-danger-700 transition-colors duration-200 hover:border-danger-600 hover:bg-danger-50"
                          >
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                          </button>
                        </div>
                      </div>
                    </li>
                  ))}
                </ol>
              )}
            </Panel>

            {/* ------------------------------ References ------------------------------ */}
            <Panel
              title="References"
              description="Deposited to Crossref as a citation list. It is what makes Cited-by work — other publishers' links to our DOIs only exist if we participate in the same graph."
              actions={
                <button
                  type="button"
                  onClick={() => setData('references', [...data.references, { raw_text: '', doi: '' }])}
                  className="btn-secondary"
                >
                  <Plus className="h-4 w-4" aria-hidden="true" />
                  Add reference
                </button>
              }
            >
              {data.references.length === 0 ? (
                <p className="text-sm text-ink-600">No references.</p>
              ) : (
                <ol className="space-y-3">
                  {data.references.map((reference, index) => (
                    <li key={index} className="rounded-lg border border-ink-200 p-4">
                      <div className="flex items-start gap-3">
                        <span className="mt-2 inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-ink-100 font-mono text-xs font-semibold text-ink-700">
                          {index + 1}
                        </span>

                        <div className="min-w-0 flex-1 space-y-3">
                          <Field
                            label="Reference"
                            htmlFor={`reference-${index}`}
                            error={errors[`references.${index}.raw_text` as keyof typeof errors]}
                          >
                            <textarea
                              id={`reference-${index}`}
                              rows={2}
                              value={reference.raw_text}
                              onChange={(e) => {
                                const next = [...data.references]
                                next[index] = { ...reference, raw_text: e.target.value }
                                setData('references', next)
                              }}
                              className={INPUT}
                            />
                          </Field>

                          <Field
                            label="DOI of the cited work"
                            htmlFor={`reference-${index}-doi`}
                            error={errors[`references.${index}.doi` as keyof typeof errors]}
                            hint="Optional. Only where it is known — never guessed."
                          >
                            <input
                              id={`reference-${index}-doi`}
                              type="text"
                              value={reference.doi ?? ''}
                              onChange={(e) => {
                                const next = [...data.references]
                                next[index] = { ...reference, doi: e.target.value }
                                setData('references', next)
                              }}
                              className={`${INPUT} font-mono`}
                              placeholder="10.1234/abcd.5678"
                            />
                          </Field>
                        </div>

                        <button
                          type="button"
                          onClick={() =>
                            setData(
                              'references',
                              data.references.filter((_, i) => i !== index),
                            )
                          }
                          aria-label={`Remove reference ${index + 1}`}
                          className="inline-flex h-9 w-9 shrink-0 cursor-pointer items-center justify-center rounded-lg border
                                     border-ink-300 text-danger-700 transition-colors duration-200
                                     hover:border-danger-600 hover:bg-danger-50"
                        >
                          <Trash2 className="h-4 w-4" aria-hidden="true" />
                        </button>
                      </div>
                    </li>
                  ))}
                </ol>
              )}
            </Panel>
          </div>

          {/* --------------------------------- Aside --------------------------------- */}
          <aside className="space-y-6 lg:sticky lg:top-24 lg:self-start">
            <div className="card p-6">
              <h2 className="font-serif text-lg text-ink-900">Full text (PDF)</h2>
              <p className="mt-1 text-sm text-ink-600">
                Required to publish. <span className="font-mono text-xs">citation_pdf_url</span> is
                advertised to Google Scholar, and an advertised PDF that 404s downgrades the whole
                journal.
              </p>

              {article?.pdf && (
                <p className="mt-4 flex items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 p-3 text-sm">
                  <FileUp className="h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
                  <a
                    href={article.pdf.url}
                    className="link-underline min-w-0 flex-1 truncate"
                    target="_blank"
                    rel="noreferrer"
                  >
                    {article.pdf.name}
                  </a>
                  <span className="shrink-0 text-xs text-ink-500">
                    {formatBytes(article.pdf.size)}
                  </span>
                </p>
              )}

              <Field
                label={article?.pdf ? 'Replace the PDF' : 'Upload a PDF'}
                htmlFor="pdf"
                error={errors.pdf}
                className="mt-4"
              >
                <input
                  id="pdf"
                  type="file"
                  accept="application/pdf"
                  onChange={(e) => setData('pdf', e.target.files?.[0] ?? null)}
                  className="mt-1.5 w-full cursor-pointer rounded-lg border border-ink-300 bg-white p-2.5 text-sm
                             text-ink-900 file:mr-3 file:cursor-pointer file:rounded-full file:border-0
                             file:bg-ink-900 file:px-4 file:py-2 file:text-xs file:font-semibold file:text-white
                             hover:border-ink-400"
                />
              </Field>
            </div>

            <div className="card p-6">
              <h2 className="font-serif text-lg text-ink-900">Identity</h2>

              <dl className="mt-4 space-y-4 text-sm">
                <div>
                  <dt className="flex items-center gap-1.5 text-xs uppercase tracking-wider text-ink-500">
                    <Link2 className="h-3.5 w-3.5" aria-hidden="true" />
                    Landing page
                  </dt>
                  <dd className="mt-1 break-all font-mono text-xs text-ink-800">
                    {article?.landingUrl ?? 'Created when the article is saved.'}
                  </dd>
                </div>

                <div>
                  <dt className="text-xs uppercase tracking-wider text-ink-500">DOI</dt>
                  <dd className="mt-1 break-all text-ink-800">
                    {article?.doi ? (
                      <span className="font-mono text-xs">{article.doi}</span>
                    ) : derivedDoi ? (
                      <>
                        <span className="font-mono text-xs">{derivedDoi}</span>
                        <span className="mt-1 block text-xs text-ink-500">
                          Not minted yet — this is what it will be.
                        </span>
                      </>
                    ) : (
                      <span className="flex items-start gap-1.5 text-xs text-ink-600">
                        <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                        {journal.canMintDois
                          ? 'Derived on publication, from the issue and the position.'
                          : 'Crossref has not issued a prefix; DOIs cannot be registered yet.'}
                      </span>
                    )}
                  </dd>
                </div>

                {frozen && (
                  <div className="flex items-start gap-2 rounded-lg bg-ink-50 p-3 text-xs text-ink-700">
                    <Lock className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                    <span>Slug, position and DOI suffix are frozen at publication.</span>
                  </div>
                )}
              </dl>
            </div>

            <div className="card p-6">
              <button type="submit" disabled={processing} className="btn-primary w-full">
                {processing ? <Spinner /> : <Save className="h-4 w-4" aria-hidden="true" />}
                {processing ? 'Saving…' : article ? 'Save article' : 'Create article'}
              </button>

              <p className="mt-3 flex items-start gap-1.5 text-xs text-ink-600">
                <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
                Metadata, authors and references save together, in one transaction.
              </p>

              {form.hasErrors && (
                <p className="mt-3 flex items-start gap-1.5 text-xs text-danger-700">
                  <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
                  Nothing was saved. Correct the fields marked above.
                </p>
              )}

              {article && (
                <Link
                  href="/admin"
                  className="btn-ghost mt-2 w-full"
                >
                  Back to journals
                </Link>
              )}
            </div>
          </aside>
        </form>
      </AdminShell>
    </>
  )
}
