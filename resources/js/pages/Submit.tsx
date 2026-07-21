import { useEffect, useState, type ChangeEvent, type ReactNode } from 'react'
import { Head, Link, router, usePage } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import {
  AlertTriangle,
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
import { formatNumber } from '@/lib/format'
import { easeOut } from '@/lib/motion'
import { useShared, type Meta } from '@/lib/props'

/* -------------------------------------------------------------------------- *
 * The submission-wizard page-prop contract.
 * -------------------------------------------------------------------------- */

/** The journal picker only needs enough to choose with — and the SLUG to submit with. */
export type SubmitJournal = {
  slug: string
  title: string
  description: string | null
  acceptanceRate: number | null
  medianDaysToDecision: number | null
}

export type Author = {
  name: string
  email: string
  affiliation: string
  corresponding: boolean
}

/**
 * A saved draft, as it comes back from the server.
 *
 * There is NO `file` here, and there cannot be: a File object does not survive a JSON
 * round-trip. The draft remembers the file's NAME so the wizard can tell the author what
 * they attached last time, and the wizard makes them re-attach it before submitting.
 * Printing a filename we cannot actually upload would be a lie the author only discovers
 * when the manuscript arrives empty.
 */
export type SubmissionDraft = {
  /** Journal SLUG. */
  journal: string
  title: string
  type: string
  abstract: string
  keywords: string
  fileName: string | null
  authors: Author[]
  funding: string
  ethics: boolean
  conflicts: boolean
  dataAvailable: boolean
  /** Step the author had reached, 0-4. */
  step?: number
}

/**
 * Flashed by the server after a successful POST /submit.
 *
 * The success screen used to print `MRDN-2026-0451` and "51 days" as literals. A manuscript
 * ID that exists in no database is worse than no ID at all — the author quotes it in an
 * email and the editorial office has no idea what they are talking about. Anything the
 * server does not send is simply not shown.
 */
export type SubmissionReceipt = {
  reference: string | null
  journal: string | null
  title: string | null
  medianDaysToDecision: number | null
}

type Props = {
  journals: SubmitJournal[]
  types: string[]
  draft: SubmissionDraft | null
  meta: Meta
}

type SharedProps = {
  flash: {
    success?: string | null
    error?: string | null
    submission?: SubmissionReceipt | null
  }
}

/* --------------------------------- Form ---------------------------------- */

const STEPS = ['Journal', 'Manuscript', 'Authors', 'Declarations', 'Review'] as const
type StepIndex = 0 | 1 | 2 | 3 | 4
const LAST_STEP: StepIndex = 4

/** The UI has always claimed 50 MB. Until now nothing enforced it. */
const MAX_FILE_BYTES = 50 * 1024 * 1024
const ACCEPTED_FILES = '.pdf,.docx,.tex,.zip'

const EMAIL_RE = /^[^\s@]+@[^\s@]+\.[^\s@]+$/

type Errors = Record<string, string>

type Form = {
  /** The journal SLUG — not its title. The title is a display string; the backend needs a key. */
  journal: string
  title: string
  type: string
  abstract: string
  keywords: string
  /** The actual File. The old form stored `e.target.files[0].name`, so no upload was possible. */
  file: File | null
  fileName: string | null
  authors: Author[]
  funding: string
  ethics: boolean
  conflicts: boolean
  dataAvailable: boolean
}

const emptyAuthor = (corresponding = false): Author => ({
  name: '',
  email: '',
  affiliation: '',
  corresponding,
})

function formFromDraft(draft: SubmissionDraft | null, types: string[]): Form {
  const defaultType = types[0] ?? 'Original Research'

  if (!draft) {
    return {
      journal: '',
      title: '',
      type: defaultType,
      abstract: '',
      keywords: '',
      file: null,
      fileName: null,
      authors: [emptyAuthor(true)],
      funding: '',
      ethics: false,
      conflicts: false,
      dataAvailable: false,
    }
  }

  return {
    journal: draft.journal ?? '',
    title: draft.title ?? '',
    type: draft.type || defaultType,
    abstract: draft.abstract ?? '',
    keywords: draft.keywords ?? '',
    file: null,
    fileName: draft.fileName ?? null,
    // A draft with an empty authors array must still render one row to type into.
    authors: draft.authors?.length ? draft.authors : [emptyAuthor(true)],
    funding: draft.funding ?? '',
    ethics: Boolean(draft.ethics),
    conflicts: Boolean(draft.conflicts),
    dataAvailable: Boolean(draft.dataAvailable),
  }
}

function clampStep(step: number | undefined): StepIndex {
  const n = Math.min(Math.max(Math.trunc(step ?? 0), 0), LAST_STEP)
  return n as StepIndex
}

/**
 * Per-step validation. The shape is deliberate: an author is only told about the fields on
 * the step they are LEAVING, never about ones they have not reached yet.
 *
 * What changed is the coverage. Only `authors[0]` was ever checked, so a co-author row with
 * a blank name and no email sailed through the wizard and was rejected by the server —
 * or, worse, accepted, leaving an author on the paper with no way to be contacted.
 * Every author is validated now.
 */
function errorsForStep(step: StepIndex, form: Form): Errors {
  const e: Errors = {}

  if (step === 0 && !form.journal) {
    e.journal = 'Choose the journal you are submitting to.'
  }

  if (step === 1) {
    if (!form.title.trim()) e.title = 'Enter the manuscript title.'
    if (form.abstract.trim().length < 40) {
      e.abstract = 'The abstract must be at least 40 characters.'
    }
    if (!form.file) {
      e.file = form.fileName
        ? `Re-attach “${form.fileName}”. A saved draft keeps the file's name, not the file itself.`
        : 'Attach the manuscript file.'
    }
  }

  if (step === 2) {
    form.authors.forEach((author, i) => {
      const who = i === 0 ? 'the corresponding author' : `co-author ${i}`
      if (!author.name.trim()) {
        e[`authors.${i}.name`] = `Enter a name for ${who}.`
      }
      if (!EMAIL_RE.test(author.email.trim())) {
        e[`authors.${i}.email`] = `Enter a valid email for ${who}.`
      }
    })
  }

  if (step === 3) {
    if (!form.ethics) e.ethics = 'Confirm the ethics statement to continue.'
    if (!form.conflicts) e.conflicts = 'Declare competing interests to continue.'
  }

  return e
}

/** Which step owns a given error key — including the keys Laravel will send back. */
function stepForField(key: string): StepIndex {
  if (key === 'journal') return 0
  if (key.startsWith('authors')) return 2
  if (['title', 'type', 'abstract', 'keywords', 'file'].includes(key)) return 1
  return 3
}

const AUTHOR_ERROR_RE = /^authors\.\d+\.(name|email)$/
const FIELD_KEYS = ['journal', 'title', 'abstract', 'file', 'ethics', 'conflicts']

/** True when the error has a field on screen to attach itself to. */
function isBound(key: string): boolean {
  return FIELD_KEYS.includes(key) || AUTHOR_ERROR_RE.test(key)
}

export default function Submit({ journals, types, draft, meta }: Props) {
  const { flash } = usePage<SharedProps>().props
  const { auth } = useShared()

  // Where "Cancel" returns to. A signed-in author's draft is saved server-side, so they can
  // resume from their dashboard; a guest has no dashboard, so they go home.
  const cancelHref = auth.user ? '/dashboard' : '/'

  // Hydrated from the draft on the FIRST render, not in an effect: the server already sent
  // it, so there is no reason for the author to watch an empty form fill itself in.
  const [step, setStep] = useState<StepIndex>(() => clampStep(draft?.step))
  const [form, setForm] = useState<Form>(() => formFromDraft(draft, types))
  const [errors, setErrors] = useState<Errors>({})
  const [submitting, setSubmitting] = useState(false)
  const [confirmed, setConfirmed] = useState(false)

  // See Layout.tsx: framer serialises `initial` into the style attribute, so an ungated
  // initial={{ opacity: 0 }} ships the whole wizard body to the server as transparent.
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])

  const clearError = (key: string) =>
    setErrors((e) => {
      if (!(key in e)) return e
      const { [key]: _removed, ...rest } = e
      return rest
    })

  const update = <K extends keyof Form>(key: K, value: Form[K]) => {
    setForm((f) => ({ ...f, [key]: value }))
    clearError(key)
  }

  /** The draft is saved on every step transition, so four steps of typing cannot evaporate. */
  const persistDraft = (atStep: StepIndex, next: Form = form) => {
    router.post('/submit/draft', draftPayload(next, atStep), {
      preserveState: true,
      preserveScroll: true,
      only: [],
    })
  }

  const goTo = (target: StepIndex) => {
    setStep(target)
    persistDraft(target)
  }

  const next = () => {
    const found = errorsForStep(step, form)
    setErrors(found)
    if (Object.keys(found).length > 0) return
    goTo(Math.min(step + 1, LAST_STEP) as StepIndex)
  }

  const back = () => goTo(Math.max(step - 1, 0) as StepIndex)

  const jumpToFirstError = (found: Errors) => {
    const steps = Object.keys(found).map(stepForField)
    if (steps.length > 0) setStep(Math.min(...steps) as StepIndex)
  }

  const onFile = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0]
    if (!file) return

    if (file.size > MAX_FILE_BYTES) {
      // Rejecting it here, with the real size, beats a 413 from the server after a
      // ten-minute upload on a conference wifi.
      setErrors((e) => ({
        ...e,
        file: `That file is ${formatNumber(file.size / 1_048_576, 1)} MB. The limit is 50 MB — compress it, or attach the LaTeX source instead.`,
      }))
      // Let the same file be chosen again once it has been shrunk.
      event.target.value = ''
      return
    }

    setForm((f) => ({ ...f, file, fileName: file.name }))
    clearError('file')
  }

  const removeFile = () => {
    setForm((f) => ({ ...f, file: null, fileName: null }))
    clearError('file')
  }

  const submit = () => {
    // Every step is checked here, not just the last one: a resumed draft can drop the
    // author straight onto step 5 without steps 1-4 ever having been validated.
    const found: Errors = {
      ...errorsForStep(0, form),
      ...errorsForStep(1, form),
      ...errorsForStep(2, form),
      ...errorsForStep(3, form),
    }

    if (Object.keys(found).length > 0) {
      setErrors(found)
      jumpToFirstError(found)
      return
    }

    setSubmitting(true)

    router.post('/submit', submitPayload(form), {
      // The File is a Blob, so the payload has to go as multipart. Without this it is
      // JSON-encoded and the manuscript silently never leaves the browser.
      forceFormData: true,
      // Keeps the wizard's state alive across the round trip — both so a 422 does not wipe
      // four steps of typing, and so the success flash lands on a component that still
      // knows what was submitted.
      preserveState: true,
      preserveScroll: true,
      onError: (serverErrors) => {
        setErrors(serverErrors)
        jumpToFirstError(serverErrors)
      },
      onSuccess: () => setConfirmed(true),
      onFinish: () => setSubmitting(false),
    })
  }

  const receipt = flash?.submission ?? null
  const journalTitle = journals.find((j) => j.slug === form.journal)?.title ?? null

  if (confirmed || receipt) {
    return (
      <Success
        meta={meta}
        receipt={receipt}
        fallbackTitle={form.title}
        fallbackJournal={journalTitle}
      />
    )
  }

  // Anything the server rejected that has no field on screen — an unexpected key, a rule we
  // do not model client-side — is shown here rather than swallowed.
  const unbound = Object.entries(errors)
    .filter(([key]) => !isBound(key))
    .map(([, message]) => message)

  if (flash?.error) unbound.unshift(flash.error)

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <header className="border-b border-ink-200 bg-ink-50">
        <div className="container-page py-12">
          <Reveal>
            <p className="eyebrow">Submission</p>
            <h1 className="mt-3 font-serif text-4xl sm:text-5xl">Submit your research</h1>
            <p className="mt-4 max-w-prose text-ink-600">
              Format-free first submission. Your progress is saved at every step — nothing is sent
              to an editor until the final one.
            </p>
          </Reveal>
        </div>
      </header>

      <div className="container-page grid gap-10 py-12 lg:grid-cols-[240px_1fr]">
        <Stepper step={step} />

        <div>
          {/* Progress is announced as well as drawn, so it isn't colour/position-only. */}
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm font-medium text-ink-600" aria-live="polite">
              Step {step + 1} of {STEPS.length} — {STEPS[step]}
            </p>
            {/* An escape hatch from any step. A signed-in author's progress is already saved,
                so this discards nothing they cannot resume; the copy above says as much. */}
            <Link
              href={cancelHref}
              className="inline-flex items-center gap-1.5 text-sm font-medium text-ink-500 transition-colors duration-200 hover:text-ink-800"
            >
              <X className="h-4 w-4" aria-hidden="true" />
              Cancel
            </Link>
          </div>
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

          {unbound.length > 0 && (
            <div
              role="alert"
              className="mt-6 flex items-start gap-3 rounded-xl border border-danger-600/40 bg-danger-50 p-4"
            >
              <AlertTriangle
                className="mt-0.5 h-5 w-5 shrink-0 text-danger-700"
                aria-hidden="true"
              />
              <div className="text-sm text-ink-800">
                {unbound.map((message) => (
                  <p key={message}>{message}</p>
                ))}
              </div>
            </div>
          )}

          <div className="card mt-6 p-6 sm:p-8">
            <AnimatePresence mode="wait" initial={false}>
              <motion.div
                key={step}
                initial={mounted ? { opacity: 0, x: 16 } : false}
                animate={{ opacity: 1, x: 0 }}
                exit={{ opacity: 0, x: -16 }}
                transition={{ duration: 0.25, ease: easeOut }}
              >
                {step === 0 && (
                  <StepJournal
                    form={form}
                    update={update}
                    errors={errors}
                    journals={journals}
                  />
                )}
                {step === 1 && (
                  <StepManuscript
                    form={form}
                    update={update}
                    errors={errors}
                    types={types}
                    onFile={onFile}
                    onRemoveFile={removeFile}
                  />
                )}
                {step === 2 && (
                  <StepAuthors
                    form={form}
                    update={update}
                    errors={errors}
                    clearError={clearError}
                  />
                )}
                {step === 3 && <StepDeclarations form={form} update={update} errors={errors} />}
                {step === 4 && <StepReview form={form} journalTitle={journalTitle} />}
              </motion.div>
            </AnimatePresence>

            <div className="mt-8 flex items-center justify-between gap-3 border-t border-ink-200 pt-6">
              {/* No Back on the first step — there is nowhere to go back TO, and a disabled
                  button that still looks clickable reads as broken. A spacer keeps Continue
                  right-aligned. */}
              {step > 0 ? (
                <button type="button" onClick={back} className="btn-secondary">
                  <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                  Back
                </button>
              ) : (
                <span aria-hidden="true" />
              )}

              {step < LAST_STEP ? (
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

/* -------------------------------- Payloads -------------------------------- */

/** JSON-safe. The File is deliberately absent — a draft save is not an upload. */
function draftPayload(form: Form, step: StepIndex) {
  return {
    journal: form.journal,
    title: form.title,
    type: form.type,
    abstract: form.abstract,
    keywords: form.keywords,
    fileName: form.fileName,
    authors: form.authors,
    funding: form.funding,
    ethics: form.ethics,
    conflicts: form.conflicts,
    dataAvailable: form.dataAvailable,
    step,
  }
}

/** Multipart. `file` is a real File, so this must be sent with forceFormData. */
function submitPayload(form: Form) {
  return {
    journal: form.journal,
    title: form.title,
    type: form.type,
    abstract: form.abstract,
    keywords: form.keywords,
    file: form.file,
    authors: form.authors,
    funding: form.funding,
    ethics: form.ethics,
    conflicts: form.conflicts,
    dataAvailable: form.dataAvailable,
  }
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
  children: ReactNode
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
        <p className="mt-2 flex items-start gap-1.5 text-sm text-danger-700">
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
     error ? 'border-danger-600' : 'border-ink-300 hover:border-ink-400 focus:border-brand-600'
   }`

type StepProps = {
  form: Form
  update: <K extends keyof Form>(k: K, v: Form[K]) => void
  errors: Errors
}

/* --------------------------------- Steps --------------------------------- */

function StepJournal({ form, update, errors, journals }: StepProps & { journals: SubmitJournal[] }) {
  return (
    <fieldset>
      <legend className="font-serif text-2xl text-ink-900">Choose a journal</legend>
      <p className="mt-2 text-sm text-ink-600">
        Not sure? Pick the closest fit — an editor will redirect the manuscript at no cost if it
        belongs elsewhere.
      </p>

      {journals.length === 0 ? (
        <p className="mt-6 rounded-xl border border-ink-200 bg-ink-50 p-5 text-sm text-ink-700">
          No journals are open for submission at the moment. Please check back shortly.
        </p>
      ) : (
        <div className="mt-6 grid gap-3 sm:grid-cols-2">
          {journals.map((j) => {
            // The radio's value is the SLUG. It used to be the journal's TITLE — a display
            // string, which the backend would then have had to reverse-engineer into an id.
            const selected = form.journal === j.slug

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
                  value={j.slug}
                  checked={selected}
                  onChange={(e) => update('journal', e.target.value)}
                  className="mt-1 h-4 w-4 cursor-pointer accent-brand-700"
                />
                <span>
                  <span className="block text-sm font-semibold text-ink-900">{j.title}</span>
                  {j.description && (
                    <span className="mt-1 block text-xs leading-relaxed text-ink-600">
                      {j.description}
                    </span>
                  )}
                  {/* A launch journal has no acceptance rate and no median yet. Rendering 0%
                      would be a wrong statistic on the page an author chooses with. */}
                  {(j.acceptanceRate !== null || j.medianDaysToDecision !== null) && (
                    <span className="mt-2 block text-xs font-medium text-brand-800">
                      {j.acceptanceRate !== null && `${formatNumber(j.acceptanceRate)}% acceptance`}
                      {j.acceptanceRate !== null && j.medianDaysToDecision !== null && ' · '}
                      {j.medianDaysToDecision !== null &&
                        `${formatNumber(j.medianDaysToDecision)} days to decision`}
                    </span>
                  )}
                </span>
              </label>
            )
          })}
        </div>
      )}

      {errors.journal && (
        <p className="mt-4 flex items-start gap-1.5 text-sm text-danger-700">
          <X className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          {errors.journal}
        </p>
      )}
    </fieldset>
  )
}

function StepManuscript({
  form,
  update,
  errors,
  types,
  onFile,
  onRemoveFile,
}: StepProps & {
  types: string[]
  onFile: (e: ChangeEvent<HTMLInputElement>) => void
  onRemoveFile: () => void
}) {
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
          {types.map((t) => (
            <option key={t} value={t}>
              {t}
            </option>
          ))}
        </select>
      </Field>

      <Field
        id="abstract"
        label="Abstract"
        error={errors.abstract}
        hint={`${formatNumber(form.abstract.length)} characters — aim for 150 to 350 words`}
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
                          ? 'border-danger-600'
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
              <span className="min-w-0">
                <span className="block truncate text-sm font-medium text-ink-900">
                  {form.fileName}
                </span>
                <span className="block text-xs text-ink-600">
                  {formatNumber(form.file.size / 1_048_576, 1)} MB
                </span>
              </span>
            </span>
            <button
              type="button"
              onClick={onRemoveFile}
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
                            ? 'border-danger-600 bg-danger-50'
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
              accept={ACCEPTED_FILES}
              className="sr-only"
              aria-invalid={Boolean(errors.file)}
              onChange={onFile}
            />
          </label>
        )}
      </Field>
    </div>
  )
}

function StepAuthors({
  form,
  update,
  errors,
  clearError,
}: StepProps & { clearError: (key: string) => void }) {
  const setAuthor = (i: number, key: keyof Author, value: string | boolean) => {
    update(
      'authors',
      form.authors.map((a, idx) => (idx === i ? { ...a, [key]: value } : a)),
    )
    // Clear only this author's error for this field — not everyone else's.
    clearError(`authors.${i}.${key}`)
  }

  const removeAuthor = (i: number) => {
    update(
      'authors',
      form.authors.filter((_, idx) => idx !== i),
    )
    clearError(`authors.${i}.name`)
    clearError(`authors.${i}.email`)
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
                onClick={() => removeAuthor(i)}
                className="btn-ghost px-3 text-sm text-danger-700 hover:bg-danger-50"
              >
                Remove
                <span className="sr-only"> co-author {i}</span>
              </button>
            )}
          </div>

          <div className="mt-4 grid gap-4 sm:grid-cols-2">
            {/* Every author is validated, not just the first. */}
            <Field id={`name-${i}`} label="Full name" error={errors[`authors.${i}.name`]}>
              <input
                id={`name-${i}`}
                value={author.name}
                onChange={(e) => setAuthor(i, 'name', e.target.value)}
                aria-invalid={Boolean(errors[`authors.${i}.name`])}
                autoComplete="name"
                className={inputClass(errors[`authors.${i}.name`])}
              />
            </Field>
            <Field id={`email-${i}`} label="Email" error={errors[`authors.${i}.email`]}>
              <input
                id={`email-${i}`}
                type="email"
                value={author.email}
                onChange={(e) => setAuthor(i, 'email', e.target.value)}
                aria-invalid={Boolean(errors[`authors.${i}.email`])}
                autoComplete="email"
                placeholder="name@university.edu"
                className={inputClass(errors[`authors.${i}.email`])}
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
        onClick={() => update('authors', [...form.authors, emptyAuthor()])}
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
                  ? 'border-danger-600 bg-danger-50'
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
                  <span className="mt-2 flex items-center gap-1.5 text-sm text-danger-700">
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

function StepReview({ form, journalTitle }: { form: Form; journalTitle: string | null }) {
  const corresponding = form.authors[0]

  const rows = [
    // The form holds the slug; the author gets to read the title they chose.
    { k: 'Journal', v: journalTitle ?? form.journal },
    { k: 'Article type', v: form.type },
    { k: 'Title', v: form.title },
    { k: 'Abstract', v: `${form.abstract.slice(0, 120)}${form.abstract.length > 120 ? '…' : ''}` },
    { k: 'Keywords', v: form.keywords || '—' },
    { k: 'File', v: form.fileName ?? '—' },
    {
      k: 'Corresponding author',
      v: corresponding ? `${corresponding.name} · ${corresponding.email}` : '—',
    },
    {
      k: 'Co-authors',
      v: form.authors.length > 1 ? formatNumber(form.authors.length - 1) : 'None',
    },
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
          <Link href="/dashboard" className="link-underline font-semibold">
            editorial office
          </Link>
          .
        </p>
      </div>
    </div>
  )
}

/* -------------------------------- Success -------------------------------- */

function Success({
  meta,
  receipt,
  fallbackTitle,
  fallbackJournal,
}: {
  meta: Meta
  receipt: SubmissionReceipt | null
  fallbackTitle: string
  fallbackJournal: string | null
}) {
  // This screen can be server-rendered (the controller redirects back with the receipt
  // flashed), so the entrance animation is gated exactly like the rest of the page.
  const [mounted, setMounted] = useState(false)
  useEffect(() => setMounted(true), [])

  const title = receipt?.title || fallbackTitle
  const journal = receipt?.journal || fallbackJournal
  const reference = receipt?.reference ?? null
  const median = receipt?.medianDaysToDecision ?? null

  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <section className="container-page flex min-h-[70vh] items-center justify-center py-20">
        <motion.div
          initial={mounted ? { opacity: 0, y: 16 } : false}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.4, ease: easeOut }}
          className="card max-w-xl p-10 text-center"
        >
          <motion.span
            initial={mounted ? { scale: 0.6, opacity: 0 } : false}
            animate={{ scale: 1, opacity: 1 }}
            transition={{ delay: 0.15, duration: 0.35, ease: easeOut }}
            className="mx-auto inline-flex h-16 w-16 items-center justify-center rounded-full bg-brand-50 text-brand-700"
          >
            <CheckCircle2 className="h-8 w-8" aria-hidden="true" />
          </motion.span>

          <h1 className="mt-6 font-serif text-3xl">Submission received</h1>
          <p className="mt-3 text-ink-600">
            <span className="font-medium text-ink-900">{title || 'Your manuscript'}</span> is now in
            the editor check queue{journal ? ` at ${journal}` : ''}.
          </p>

          {/* The reference is printed ONLY if the server issued one. An invented manuscript
              ID is worse than none: the author quotes it, and it matches nothing. */}
          {reference ? (
            <p className="mt-4 rounded-full bg-ink-50 px-4 py-3 font-mono text-sm text-ink-800">
              Manuscript ID: {reference}
            </p>
          ) : (
            <p className="mt-4 text-sm text-ink-600">
              Your manuscript reference will be in the confirmation email.
            </p>
          )}

          <p className="mt-4 text-sm text-ink-600">
            {median !== null && (
              <>
                Median time to first decision in this journal is {formatNumber(median)} days.{' '}
              </>
            )}
            We will email the corresponding author at every stage.
          </p>

          <div className="mt-8 flex flex-wrap justify-center gap-3">
            <Link href="/dashboard" className="btn-primary">
              Track this manuscript
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
            <Link href="/" className="btn-secondary">
              Back to homepage
            </Link>
          </div>
        </motion.div>
      </section>
    </>
  )
}
