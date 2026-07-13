import { useState } from 'react'
import { Link } from 'react-router-dom'
import { AnimatePresence, motion } from 'framer-motion'
import {
  ArrowLeft,
  ArrowRight,
  Check,
  CheckCircle2,
  FileUp,
  Loader2,
  ShieldCheck,
  X,
} from 'lucide-react'
import { Reveal } from '@/components/Reveal'
import { JOURNALS } from '@/lib/data'
import { easeOut } from '@/lib/motion'

const STEPS = ['Journal', 'Manuscript', 'Authors', 'Declarations', 'Review'] as const
type StepIndex = 0 | 1 | 2 | 3 | 4

type Form = {
  journal: string
  title: string
  type: string
  abstract: string
  keywords: string
  file: string | null
  authors: { name: string; email: string; affiliation: string; corresponding: boolean }[]
  funding: string
  ethics: boolean
  conflicts: boolean
  dataAvailable: boolean
}

const EMPTY: Form = {
  journal: '',
  title: '',
  type: 'Original Research',
  abstract: '',
  keywords: '',
  file: null,
  authors: [{ name: '', email: '', affiliation: '', corresponding: true }],
  funding: '',
  ethics: false,
  conflicts: false,
  dataAvailable: false,
}

export default function Submit() {
  const [step, setStep] = useState<StepIndex>(0)
  const [form, setForm] = useState<Form>(EMPTY)
  const [errors, setErrors] = useState<Record<string, string>>({})
  const [submitting, setSubmitting] = useState(false)
  const [submitted, setSubmitted] = useState(false)

  const update = <K extends keyof Form>(key: K, value: Form[K]) => {
    setForm((f) => ({ ...f, [key]: value }))
    setErrors((e) => {
      const { [key]: _removed, ...rest } = e
      return rest
    })
  }

  /** Validate only the step being left, so users aren't shouted at about fields they haven't reached. */
  const validate = (s: StepIndex) => {
    const e: Record<string, string> = {}
    if (s === 0 && !form.journal) e.journal = 'Choose the journal you are submitting to.'
    if (s === 1) {
      if (!form.title.trim()) e.title = 'Enter the manuscript title.'
      if (form.abstract.trim().length < 40)
        e.abstract = 'The abstract must be at least 40 characters.'
      if (!form.file) e.file = 'Attach the manuscript file.'
    }
    if (s === 2) {
      if (!form.authors[0].name.trim()) e.authorName = 'The corresponding author needs a name.'
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.authors[0].email))
        e.authorEmail = 'Enter a valid email for the corresponding author.'
    }
    if (s === 3) {
      if (!form.ethics) e.ethics = 'Confirm the ethics statement to continue.'
      if (!form.conflicts) e.conflicts = 'Declare competing interests to continue.'
    }
    setErrors(e)
    return Object.keys(e).length === 0
  }

  const next = () => {
    if (!validate(step)) return
    setStep((s) => Math.min(s + 1, 4) as StepIndex)
  }
  const back = () => setStep((s) => Math.max(s - 1, 0) as StepIndex)

  const submit = () => {
    setSubmitting(true)
    // Stands in for POST /submissions.
    window.setTimeout(() => {
      setSubmitting(false)
      setSubmitted(true)
    }, 1400)
  }

  if (submitted) return <Success journal={form.journal} title={form.title} />

  return (
    <>
      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal>
            <p className="eyebrow">Submission</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Submit your research</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Format-free first submission. You can save and return at any point — nothing is sent
              to an editor until the final step.
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page grid gap-10 py-12 lg:grid-cols-[240px_1fr]">
        <Stepper step={step} />

        <div>
          {/* Progress is announced as well as drawn, so it isn't colour/position-only. */}
          <p className="text-sm font-medium text-ink-600" aria-live="polite">
            Step {step + 1} of {STEPS.length} — {STEPS[step]}
          </p>
          <div
            role="progressbar"
            aria-valuenow={step + 1}
            aria-valuemin={1}
            aria-valuemax={STEPS.length}
            aria-label="Submission progress"
            className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-ink-200"
          >
            <motion.div
              className="h-full rounded-full bg-brand-600"
              initial={false}
              animate={{ width: `${((step + 1) / STEPS.length) * 100}%` }}
              transition={{ duration: 0.4, ease: easeOut }}
            />
          </div>

          <div className="card mt-6 p-6 sm:p-8">
            <AnimatePresence mode="wait">
              <motion.div
                key={step}
                initial={{ opacity: 0, x: 16 }}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.25, ease: easeOut }}
              >
                {step === 0 && <StepJournal form={form} update={update} errors={errors} />}
                {step === 1 && <StepManuscript form={form} update={update} errors={errors} />}
                {step === 2 && <StepAuthors form={form} update={update} errors={errors} />}
                {step === 3 && <StepDeclarations form={form} update={update} errors={errors} />}
                {step === 4 && <StepReview form={form} />}
              </motion.div>
            </AnimatePresence>

            <div className="mt-8 flex items-center justify-between gap-3 border-t border-ink-200 pt-6">
              <button
                type="button"
                onClick={back}
                disabled={step === 0}
                className="btn-secondary"
              >
                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                Back
              </button>

              {step < 4 ? (
                <button type="button" onClick={next} className="btn-primary">
                  Continue
                  <ArrowRight className="h-4 w-4" aria-hidden="true" />
                </button>
              ) : (
                <button
                  type="button"
                  onClick={submit}
                  disabled={submitting}
                  className="btn-primary"
                >
                  {submitting ? (
                    <>
                      <Loader2 className="h-4 w-4 animate-spin" aria-hidden="true" />
                      Submitting…
                    </>
                  ) : (
                    <>
                      <Check className="h-4 w-4" aria-hidden="true" />
                      Submit to editor
                    </>
                  )}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  )
}

/* -------------------------------- Stepper -------------------------------- */

function Stepper({ step }: { step: number }) {
  return (
    <nav aria-label="Submission steps" className="lg:sticky lg:top-24 lg:self-start">
      <ol className="space-y-1">
        {STEPS.map((label, i) => {
          const done = i < step
          const current = i === step
          return (
            <li key={label}>
              <div
                aria-current={current ? 'step' : undefined}
                className={`flex items-center gap-3 rounded-full px-3 py-3 transition-colors duration-200 ${
                  current ? 'bg-brand-50' : ''
                }`}
              >
                <span
                  className={`inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold transition-colors duration-200 ${
                    done
                      ? 'bg-brand-700 text-white'
                      : current
                        ? 'border-2 border-brand-700 text-brand-800'
                        : 'border-2 border-ink-300 text-ink-500'
                  }`}
                >
                  {done ? <Check className="h-4 w-4" aria-hidden="true" /> : i + 1}
                </span>
                <span
                  className={`text-sm font-medium ${
                    current ? 'text-brand-900' : done ? 'text-ink-700' : 'text-ink-500'
                  }`}
                >
                  {label}
                  {done && <span className="sr-only"> (completed)</span>}
                </span>
              </div>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}

/* ------------------------------ Field helpers ----------------------------- */

function Field({
  id,
  label,
  error,
  hint,
  children,
}: {
  id: string
  label: string
  error?: string
  hint?: string
  children: React.ReactNode
}) {
  return (
    <div>
      <label htmlFor={id} className="block text-sm font-medium text-ink-800">
        {label}
      </label>
      {hint && <p className="mt-1 text-xs text-ink-500">{hint}</p>}
      <div className="mt-2">{children}</div>
      {/* Errors sit next to the field they belong to, with an icon as well as colour. */}
      {error && (
        <p className="mt-2 flex items-start gap-1.5 text-sm text-red-700">
          <X className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {error}
        </p>
      )}
    </div>
  )
}

/** Single-line fields are pills; multi-line ones keep a soft radius so they don't read as capsules. */
const inputClass = (error?: string) =>
  `h-12 w-full rounded-full border bg-white px-5 text-base text-ink-900 placeholder:text-ink-500
   transition-colors duration-200 ${
     error ? 'border-red-600' : 'border-ink-300 hover:border-ink-400 focus:border-brand-600'
   }`

type StepProps = {
  form: Form
  update: <K extends keyof Form>(k: K, v: Form[K]) => void
  errors: Record<string, string>
}

/* --------------------------------- Steps --------------------------------- */

function StepJournal({ form, update, errors }: StepProps) {
  return (
    <fieldset>
      <legend className="font-serif text-2xl text-ink-900">Choose a journal</legend>
      <p className="mt-2 text-sm text-ink-600">
        Not sure? Pick the closest fit — an editor will redirect the manuscript at no cost if it
        belongs elsewhere.
      </p>

      <div className="mt-6 grid gap-3 sm:grid-cols-2">
        {JOURNALS.map((j) => {
          const selected = form.journal === j.title
          return (
            <label
              key={j.slug}
              className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors duration-200 ${
                selected
                  ? 'border-brand-700 bg-brand-50'
                  : 'border-ink-200 hover:border-ink-400 hover:bg-ink-50'
              }`}
            >
              <input
                type="radio"
                name="journal"
                value={j.title}
                checked={selected}
                onChange={(e) => update('journal', e.target.value)}
                className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
              />
              <span>
                <span className="block text-sm font-semibold text-ink-900">{j.title}</span>
                <span className="mt-1 block text-xs leading-relaxed text-ink-600">
                  {j.description}
                </span>
                <span className="mt-2 block text-xs font-medium text-brand-800">
                  {j.acceptanceRate}% acceptance · {j.medianDaysToDecision} days to decision
                </span>
              </span>
            </label>
          )
        })}
      </div>

      {errors.journal && (
        <p className="mt-4 flex items-start gap-1.5 text-sm text-red-700">
          <X className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {errors.journal}
        </p>
      )}
    </fieldset>
  )
}

function StepManuscript({ form, update, errors }: StepProps) {
  return (
    <div className="space-y-6">
      <h2 className="font-serif text-2xl text-ink-900">The manuscript</h2>

      <Field id="title" label="Title" error={errors.title}>
        <input
          id="title"
          value={form.title}
          onChange={(e) => update('title', e.target.value)}
          aria-invalid={Boolean(errors.title)}
          placeholder="A concise, specific title"
          className={inputClass(errors.title)}
        />
      </Field>

      <Field id="type" label="Article type">
        <select
          id="type"
          value={form.type}
          onChange={(e) => update('type', e.target.value)}
          className={`${inputClass()} cursor-pointer`}
        >
          {['Original Research', 'Review', 'Methods', 'Perspective'].map((t) => (
            <option key={t}>{t}</option>
          ))}
        </select>
      </Field>

      <Field
        id="abstract"
        label="Abstract"
        error={errors.abstract}
        hint={`${form.abstract.length} characters — aim for 150 to 350 words`}
      >
        <textarea
          id="abstract"
          rows={6}
          value={form.abstract}
          onChange={(e) => update('abstract', e.target.value)}
          aria-invalid={Boolean(errors.abstract)}
          placeholder="Background, methods, results and what they mean."
          className={`w-full rounded-2xl border bg-white p-4 text-base leading-relaxed text-ink-900
                      placeholder:text-ink-500 transition-colors duration-200 ${
                        errors.abstract
                          ? 'border-red-600'
                          : 'border-ink-300 hover:border-ink-400 focus:border-brand-600'
                      }`}
        />
      </Field>

      <Field id="keywords" label="Keywords" hint="Comma separated, up to eight">
        <input
          id="keywords"
          value={form.keywords}
          onChange={(e) => update('keywords', e.target.value)}
          placeholder="coral bleaching, refugia, reef resilience"
          className={inputClass()}
        />
      </Field>

      <Field id="file" label="Manuscript file" error={errors.file}>
        {form.file ? (
          <div className="flex items-center justify-between gap-3 rounded-2xl border border-brand-300 bg-brand-50 p-4">
            <span className="flex min-w-0 items-center gap-3">
              <CheckCircle2 className="h-5 w-5 shrink-0 text-brand-700" aria-hidden="true" />
              <span className="truncate text-sm font-medium text-ink-900">{form.file}</span>
            </span>
            <button
              type="button"
              onClick={() => update('file', null)}
              className="btn-ghost shrink-0 px-3 text-sm"
            >
              Remove
            </button>
          </div>
        ) : (
          <label
            className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl
                        border-2 border-dashed p-8 text-center transition-colors duration-200 ${
                          errors.file
                            ? 'border-red-400 bg-red-50/50'
                            : 'border-ink-300 hover:border-brand-500 hover:bg-brand-50'
                        }`}
          >
            <FileUp className="h-7 w-7 text-ink-500" aria-hidden="true" />
            <span className="text-sm font-medium text-ink-900">
              Drop your manuscript here, or browse
            </span>
            <span className="text-xs text-ink-500">PDF, DOCX or LaTeX archive, up to 50 MB</span>
            <input
              id="file"
              type="file"
              className="sr-only"
              onChange={(e) => update('file', e.target.files?.[0]?.name ?? 'manuscript.pdf')}
            />
          </label>
        )}
      </Field>
    </div>
  )
}

function StepAuthors({ form, update, errors }: StepProps) {
  const setAuthor = (i: number, key: keyof Form['authors'][number], value: string | boolean) => {
    const next = form.authors.map((a, idx) => (idx === i ? { ...a, [key]: value } : a))
    update('authors', next)
  }

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-serif text-2xl text-ink-900">Authors</h2>
        <p className="mt-2 text-sm text-ink-600">
          The corresponding author receives all editorial correspondence and reviewer reports.
        </p>
      </div>

      {form.authors.map((author, i) => (
        <div key={i} className="rounded-xl border border-ink-200 p-5">
          <div className="flex items-center justify-between">
            <h3 className="text-sm font-semibold text-ink-900">
              {i === 0 ? 'Corresponding author' : `Co-author ${i}`}
            </h3>
            {i > 0 && (
              <button
                type="button"
                onClick={() =>
                  update(
                    'authors',
                    form.authors.filter((_, idx) => idx !== i),
                  )
                }
                className="btn-ghost px-3 text-sm text-red-700 hover:bg-red-50"
              >
                Remove
              </button>
            )}
          </div>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            <Field
              id={`name-${i}`}
              label="Full name"
              error={i === 0 ? errors.authorName : undefined}
            >
              <input
                id={`name-${i}`}
                value={author.name}
                onChange={(e) => setAuthor(i, 'name', e.target.value)}
                autoComplete="name"
                className={inputClass(i === 0 ? errors.authorName : undefined)}
              />
            </Field>
            <Field
              id={`email-${i}`}
              label="Email"
              error={i === 0 ? errors.authorEmail : undefined}
            >
              <input
                id={`email-${i}`}
                type="email"
                value={author.email}
                onChange={(e) => setAuthor(i, 'email', e.target.value)}
                autoComplete="email"
                placeholder="name@university.edu"
                className={inputClass(i === 0 ? errors.authorEmail : undefined)}
              />
            </Field>
          </div>

          <div className="mt-4">
            <Field id={`aff-${i}`} label="Affiliation">
              <input
                id={`aff-${i}`}
                value={author.affiliation}
                onChange={(e) => setAuthor(i, 'affiliation', e.target.value)}
                placeholder="Department, institution, country"
                className={inputClass()}
              />
            </Field>
          </div>
        </div>
      ))}

      <button
        type="button"
        onClick={() =>
          update('authors', [
            ...form.authors,
            { name: '', email: '', affiliation: '', corresponding: false },
          ])
        }
        className="btn-secondary"
      >
        Add a co-author
      </button>
    </div>
  )
}

function StepDeclarations({ form, update, errors }: StepProps) {
  const checks = [
    {
      key: 'ethics' as const,
      label: 'Ethics and consent',
      body: 'All human or animal work was approved by the relevant ethics committee, and informed consent was obtained where applicable.',
      error: errors.ethics,
    },
    {
      key: 'conflicts' as const,
      label: 'Competing interests',
      body: 'All financial and non-financial competing interests have been declared, or none exist.',
      error: errors.conflicts,
    },
    {
      key: 'dataAvailable' as const,
      label: 'Data availability (optional)',
      body: 'Datasets, code and protocols will be deposited and given their own DOI on acceptance.',
      error: undefined,
    },
  ]

  return (
    <div className="space-y-6">
      <div>
        <h2 className="font-serif text-2xl text-ink-900">Declarations</h2>
        <p className="mt-2 text-sm text-ink-600">
          These statements are published with the article.
        </p>
      </div>

      <Field id="funding" label="Funding statement" hint="Grant numbers and funders, if any">
        <textarea
          id="funding"
          rows={3}
          value={form.funding}
          onChange={(e) => update('funding', e.target.value)}
          placeholder="This work was supported by…"
          className="w-full rounded-2xl border border-ink-300 p-4 text-base leading-relaxed
                     placeholder:text-ink-500 transition-colors duration-200 hover:border-ink-400 focus:border-brand-600"
        />
      </Field>

      <ul className="space-y-3">
        {checks.map((c) => (
          <li key={c.key}>
            <label
              className={`flex cursor-pointer gap-3 rounded-xl border p-4 transition-colors duration-200 ${
                c.error
                  ? 'border-red-400 bg-red-50/50'
                  : form[c.key]
                    ? 'border-brand-300 bg-brand-50'
                    : 'border-ink-200 hover:border-ink-400 hover:bg-ink-50'
              }`}
            >
              <input
                type="checkbox"
                checked={form[c.key]}
                onChange={(e) => update(c.key, e.target.checked)}
                aria-invalid={Boolean(c.error)}
                className="mt-0.5 h-5 w-5 cursor-pointer accent-brand-700"
              />
              <span>
                <span className="block text-sm font-semibold text-ink-900">{c.label}</span>
                <span className="mt-1 block text-sm leading-relaxed text-ink-600">{c.body}</span>
                {c.error && (
                  <span className="mt-2 flex items-center gap-1.5 text-sm text-red-700">
                    <X className="h-4 w-4" aria-hidden="true" />
                    {c.error}
                  </span>
                )}
              </span>
            </label>
          </li>
        ))}
      </ul>
    </div>
  )
}

function StepReview({ form }: { form: Form }) {
  const rows = [
    { k: 'Journal', v: form.journal },
    { k: 'Article type', v: form.type },
    { k: 'Title', v: form.title },
    { k: 'Abstract', v: `${form.abstract.slice(0, 120)}${form.abstract.length > 120 ? '…' : ''}` },
    { k: 'Keywords', v: form.keywords || '—' },
    { k: 'File', v: form.file ?? '—' },
    {
      k: 'Corresponding author',
      v: `${form.authors[0].name} · ${form.authors[0].email}`,
    },
    { k: 'Co-authors', v: form.authors.length > 1 ? `${form.authors.length - 1}` : 'None' },
    { k: 'Funding', v: form.funding || 'None declared' },
  ]

  return (
    <div>
      <h2 className="font-serif text-2xl text-ink-900">Check and submit</h2>
      <p className="mt-2 text-sm text-ink-600">
        Nothing has been sent yet. Review the summary, then submit to the editorial office.
      </p>

      <dl className="mt-6 divide-y divide-ink-200 border-y border-ink-200">
        {rows.map((r) => (
          <div key={r.k} className="grid gap-1 py-4 sm:grid-cols-[180px_1fr] sm:gap-4">
            <dt className="text-sm font-medium text-ink-500">{r.k}</dt>
            <dd className="break-words text-sm text-ink-900">{r.v || '—'}</dd>
          </div>
        ))}
      </dl>

      <div className="mt-6 flex gap-3 rounded-xl bg-brand-50 p-5">
        <ShieldCheck className="h-5 w-5 shrink-0 text-brand-700" aria-hidden="true" />
        <p className="text-sm leading-relaxed text-ink-700">
          On submission your manuscript enters the editor check queue. You will be able to follow
          it — and read reviewer reports as they arrive — from the{' '}
          <Link to="/dashboard" className="link-underline font-semibold">
            editorial office
          </Link>
          .
        </p>
      </div>
    </div>
  )
}

/* -------------------------------- Success -------------------------------- */

function Success({ journal, title }: { journal: string; title: string }) {
  return (
    <section className="container-page flex min-h-[70vh] items-center justify-center py-20">
      <motion.div
        initial={{ opacity: 0, y: 16 }}
        animate={{ opacity: 1, y: 0 }}
        transition={{ duration: 0.4, ease: easeOut }}
        className="card max-w-xl p-10 text-center"
      >
        <motion.span
          initial={{ scale: 0.6, opacity: 0 }}
          animate={{ scale: 1, opacity: 1 }}
          transition={{ delay: 0.15, duration: 0.35, ease: easeOut }}
          className="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-700"
        >
          <CheckCircle2 className="h-8 w-8" aria-hidden="true" />
        </motion.span>

        <h1 className="mt-6 font-serif text-3xl">Submission received</h1>
        <p className="mt-3 text-ink-600">
          <span className="font-medium text-ink-900">{title || 'Your manuscript'}</span> is now in
          the editor check queue at {journal || 'your chosen journal'}.
        </p>
        <p className="mt-4 rounded-full bg-ink-50 px-4 py-3 font-mono text-sm text-ink-800">
          Manuscript ID: MRDN-2026-0451
        </p>
        <p className="mt-4 text-sm text-ink-600">
          Median time to first decision in this journal is 51 days. We will email the corresponding
          author at every stage.
        </p>

        <div className="mt-8 flex flex-wrap justify-center gap-3">
          <Link to="/dashboard" className="btn-primary">
            Track this manuscript
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
          <Link to="/" className="btn-secondary">
            Back to homepage
          </Link>
        </div>
      </motion.div>
    </section>
  )
}
