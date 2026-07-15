import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { AnimatePresence, motion } from 'framer-motion'
import { AlertTriangle, Globe, Link2, ShieldAlert } from 'lucide-react'
import { Spinner } from '@/components/admin/Shell'
import { easeOut } from '@/lib/motion'

/**
 * THE PUBLISH GATE — the highest-risk button in the system.
 *
 * Publishing freezes a URL, freezes an identifier and, downstream, spends money at Crossref
 * minting a DOI that can never be withdrawn. There is no undo. So there is a confirmation,
 * it is unmistakable, and it says what will actually become permanent — not "Are you sure?",
 * which is a question nobody reads.
 *
 * The dialog is CLIENT-ONLY (`open` is false on the server), so no framer `initial` variant
 * is ever serialised into the server HTML.
 *
 * The refusal — every pre-flight failure at once — is rendered by <Flash/> at the top of the
 * page, from the `publishErrors` shared prop. Not here, and not one at a time.
 */
export function PublishButton({
  url,
  label,
  heading,
  summary,
  permanentUrl,
  doi,
  canMintDois,
  className = 'btn-primary',
}: {
  url: string
  label: string
  heading: string
  summary: string
  /** The URL that becomes permanent. Shown verbatim — it is the promise being made. */
  permanentUrl?: string | null
  /** The DOI that will be minted, or null while Crossref has issued no prefix. */
  doi?: string | null
  canMintDois: boolean
  className?: string
}) {
  const [open, setOpen] = useState(false)
  const [publishing, setPublishing] = useState(false)

  useEffect(() => {
    if (!open) return

    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false)
    }

    window.addEventListener('keydown', onKey)
    return () => window.removeEventListener('keydown', onKey)
  }, [open])

  const publish = () => {
    setPublishing(true)

    router.post(
      url,
      {},
      {
        preserveScroll: true,
        onFinish: () => {
          setPublishing(false)
          setOpen(false)
        },
      },
    )
  }

  return (
    <>
      <button type="button" onClick={() => setOpen(true)} className={className}>
        <Globe className="h-4 w-4" aria-hidden="true" />
        {label}
      </button>

      <AnimatePresence>
        {open && (
          <div className="fixed inset-0 z-modal flex items-center justify-center p-4">
            <motion.div
              initial={{ opacity: 0 }}
              animate={{ opacity: 1 }}
              exit={{ opacity: 0 }}
              transition={{ duration: 0.2 }}
              className="absolute inset-0 z-overlay bg-ink-950/60"
              onClick={() => !publishing && setOpen(false)}
              aria-hidden="true"
            />

            <motion.div
              role="dialog"
              aria-modal="true"
              aria-labelledby="publish-heading"
              initial={{ opacity: 0, y: 8 }}
              animate={{ opacity: 1, y: 0 }}
              exit={{ opacity: 0, y: 8 }}
              transition={{ duration: 0.2, ease: easeOut }}
              className="relative z-modal w-full max-w-lg rounded-xl bg-white p-6 shadow-lift"
            >
              <span className="inline-flex h-11 w-11 items-center justify-center rounded-full bg-gold-50 text-gold-700">
                <ShieldAlert className="h-6 w-6" aria-hidden="true" />
              </span>

              <h2 id="publish-heading" className="mt-4 font-serif text-2xl text-ink-900">
                {heading}
              </h2>

              <p className="mt-2 text-sm text-ink-700">{summary}</p>

              <ul className="mt-5 space-y-3 rounded-lg border border-ink-200 bg-ink-50 p-4 text-sm">
                {permanentUrl && (
                  <li className="flex items-start gap-2.5">
                    <Link2 className="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
                    <span className="min-w-0 text-ink-800">
                      This URL becomes permanent and can never change:
                      <span className="mt-0.5 block break-all font-mono text-xs text-ink-900">
                        {permanentUrl}
                      </span>
                    </span>
                  </li>
                )}

                <li className="flex items-start gap-2.5">
                  <Globe className="mt-0.5 h-4 w-4 shrink-0 text-ink-500" aria-hidden="true" />
                  <span className="min-w-0 text-ink-800">
                    {canMintDois ? (
                      <>
                        A DOI is registered with Crossref and{' '}
                        <strong className="font-semibold">cannot be withdrawn</strong>:
                        {doi && (
                          <span className="mt-0.5 block break-all font-mono text-xs text-ink-900">
                            {doi}
                          </span>
                        )}
                      </>
                    ) : (
                      // The deliberate NULL, said in words. Publishing is still correct and
                      // still permanent — only the DOI waits.
                      <>
                        Crossref has not issued this journal a prefix, so{' '}
                        <strong className="font-semibold">no DOI can be registered yet</strong>. The
                        pages go live now; the DOI is deposited once a prefix exists.
                      </>
                    )}
                  </span>
                </li>

                <li className="flex items-start gap-2.5">
                  <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0 text-gold-700" aria-hidden="true" />
                  <span className="text-ink-800">
                    <strong className="font-semibold">There is no undo.</strong> A published
                    article can be withdrawn — its page keeps resolving with a notice — but it can
                    never be deleted, and its URL and DOI never change.
                  </span>
                </li>
              </ul>

              <div className="mt-6 flex flex-wrap justify-end gap-3">
                <button
                  type="button"
                  onClick={() => setOpen(false)}
                  disabled={publishing}
                  className="btn-secondary"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  onClick={publish}
                  disabled={publishing}
                  className="btn-primary"
                >
                  {publishing ? (
                    <>
                      <Spinner />
                      Publishing…
                    </>
                  ) : (
                    <>
                      <Globe className="h-4 w-4" aria-hidden="true" />
                      {label}
                    </>
                  )}
                </button>
              </div>
            </motion.div>
          </div>
        )}
      </AnimatePresence>
    </>
  )
}
