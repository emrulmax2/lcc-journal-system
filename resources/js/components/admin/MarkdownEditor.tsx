import { useCallback, useEffect, useRef, useState } from 'react'
import {
  AlertTriangle,
  Bold,
  Eye,
  Heading2,
  Heading3,
  Info,
  Italic,
  Link2,
  List,
  Table,
  type LucideIcon,
} from 'lucide-react'
import { Spinner } from '@/components/admin/Shell'
import { contentHref } from '@/lib/content'

/**
 * The markdown editor: a textarea, a toolbar that INSERTS markdown, and a live preview.
 *
 * THE PREVIEW IS RENDERED BY THE SERVER. There is no markdown parser in this bundle, and
 * adding one would be a mistake with a security edge, not just a duplication:
 *
 *   - MarkdownRenderer runs with `html_input: 'escape'` and `allow_unsafe_links: false`. A
 *     client-side library, configured by whoever reached for it, almost certainly does not.
 *     The editor would then type `<script>`, watch the preview "work", and publish a page
 *     that renders it as literal text — or, far worse, the reverse.
 *   - Two renderers drift the moment one is upgraded. The preview would become a promise the
 *     live page does not keep, which is worse than no preview at all.
 *
 * So the textarea POSTs to /admin/content/preview and renders what comes back. What you see
 * is what will be served, because it is the same function that will serve it.
 *
 * The HTML is injected with dangerouslySetInnerHTML, and that is safe HERE and only here:
 * it is HTML our own server just produced from markdown with raw HTML escaped. It is not
 * user-supplied HTML, because no such thing can exist in this system.
 */

type Tool = {
  label: string
  icon: LucideIcon
  /** Wraps the selection, or inserts at the caret when there is none. */
  apply: (selection: string) => { text: string; caret?: number }
}

const TOOLS: Tool[] = [
  {
    label: 'Bold',
    icon: Bold,
    apply: (s) => ({ text: `**${s || 'bold text'}**`, caret: s ? undefined : 2 }),
  },
  {
    label: 'Italic',
    icon: Italic,
    apply: (s) => ({ text: `*${s || 'italic text'}*`, caret: s ? undefined : 1 }),
  },
  {
    label: 'Heading 2',
    icon: Heading2,
    apply: (s) => ({ text: `## ${s || 'Heading'}` }),
  },
  {
    label: 'Heading 3',
    icon: Heading3,
    apply: (s) => ({ text: `### ${s || 'Heading'}` }),
  },
  {
    label: 'Link',
    icon: Link2,
    apply: (s) => ({ text: `[${s || 'link text'}](https://)` }),
  },
  {
    label: 'Bulleted list',
    icon: List,
    apply: (s) => ({
      text: (s || 'First item')
        .split('\n')
        .map((line) => `- ${line}`)
        .join('\n'),
    }),
  },
  {
    label: 'Table',
    icon: Table,
    apply: () => ({
      // The APC and waiver policies are tables. This is why TableExtension is enabled.
      text: '| Column | Column |\n| --- | --- |\n| Cell | Cell |',
    }),
  },
]

export function MarkdownEditor({
  id,
  value,
  onChange,
  label,
  hint,
  error,
  rows = 18,
}: {
  id: string
  value: string
  onChange: (value: string) => void
  label: string
  hint?: string
  error?: string
  rows?: number
}) {
  const textarea = useRef<HTMLTextAreaElement>(null)
  const [html, setHtml] = useState('')
  const [rendering, setRendering] = useState(false)
  const [failed, setFailed] = useState(false)

  const insert = useCallback(
    (tool: Tool) => {
      const el = textarea.current
      if (!el) return

      const start = el.selectionStart
      const end = el.selectionEnd
      const selection = value.slice(start, end)
      const { text, caret } = tool.apply(selection)

      const next = value.slice(0, start) + text + value.slice(end)
      onChange(next)

      // Put the caret where a human would expect it: inside the markers when we inserted a
      // placeholder, after the inserted text when we wrapped a selection.
      requestAnimationFrame(() => {
        el.focus()
        const position = caret === undefined ? start + text.length : start + caret
        const length = caret === undefined ? 0 : text.length - caret * 2
        el.setSelectionRange(position, position + length)
      })
    },
    [onChange, value],
  )

  /**
   * The preview round-trip. Debounced, and every in-flight request is aborted when the next
   * keystroke supersedes it — otherwise a slow response can land after a fast one and paint
   * a preview of text the editor has already changed.
   */
  useEffect(() => {
    const controller = new AbortController()

    const timer = window.setTimeout(() => {
      setRendering(true)

      fetch(contentHref.preview, {
        method: 'POST',
        signal: controller.signal,
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
          // Laravel's CSRF middleware reads this header and decrypts the cookie it came from.
          'X-XSRF-TOKEN': readXsrfToken(),
        },
        body: JSON.stringify({ markdown: value }),
      })
        .then((response) => {
          if (!response.ok) throw new Error(String(response.status))
          return response.json() as Promise<{ html: string }>
        })
        .then((data) => {
          setHtml(data.html)
          setFailed(false)
        })
        .catch((e: unknown) => {
          if (e instanceof DOMException && e.name === 'AbortError') return
          setFailed(true)
        })
        .finally(() => setRendering(false))
    }, 400)

    return () => {
      window.clearTimeout(timer)
      controller.abort()
    }
  }, [value])

  return (
    <div>
      <div className="flex flex-wrap items-end justify-between gap-3">
        <label htmlFor={id} className="block text-sm font-medium text-ink-800">
          {label}
        </label>

        <p className="flex items-center gap-1.5 text-xs text-ink-600">
          {rendering ? (
            <Spinner className="h-3.5 w-3.5" />
          ) : (
            <Eye className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
          )}
          {rendering ? 'Rendering…' : 'Preview is rendered by the server'}
        </p>
      </div>

      <div className="mt-1.5 grid gap-4 lg:grid-cols-2">
        {/* -------------------------------- Source -------------------------------- */}
        <div className="overflow-hidden rounded-lg border border-ink-300 bg-white focus-within:border-brand-600">
          <div
            role="toolbar"
            aria-label="Markdown"
            aria-controls={id}
            className="flex flex-wrap gap-1 border-b border-ink-200 bg-ink-50 p-1.5"
          >
            {TOOLS.map((tool) => {
              const Icon = tool.icon

              return (
                <button
                  key={tool.label}
                  type="button"
                  onClick={() => insert(tool)}
                  title={tool.label}
                  aria-label={tool.label}
                  className="inline-flex h-9 w-9 cursor-pointer items-center justify-center rounded-md
                             text-ink-700 transition-colors duration-200 hover:bg-ink-200 hover:text-ink-900"
                >
                  <Icon className="h-4 w-4" aria-hidden="true" />
                </button>
              )
            })}
          </div>

          <textarea
            id={id}
            ref={textarea}
            rows={rows}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            aria-describedby={error ? `${id}-error` : hint ? `${id}-hint` : undefined}
            className="w-full resize-y border-0 bg-white px-3.5 py-2.5 font-mono text-sm leading-relaxed
                       text-ink-900 placeholder:text-ink-500 focus:outline-none"
            placeholder="## A heading&#10;&#10;Some **markdown**."
          />
        </div>

        {/* -------------------------------- Preview ------------------------------- */}
        <div className="rounded-lg border border-ink-200 bg-ink-50/60 p-5">
          {failed ? (
            <p role="alert" className="flex items-start gap-2 text-sm text-danger-800">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
              <span>
                The preview could not be rendered. Your text is safe — this is the preview
                request failing, not the save. Try again in a moment.
              </span>
            </p>
          ) : html ? (
            // Server-rendered from markdown, with raw HTML escaped. See the class docblock.
            <div className="prose-cms max-w-prose" dangerouslySetInnerHTML={{ __html: html }} />
          ) : (
            <p className="text-sm text-ink-600">Nothing to preview yet.</p>
          )}
        </div>
      </div>

      {error ? (
        <p id={`${id}-error`} className="mt-1.5 flex items-start gap-1.5 text-sm text-danger-700">
          <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0" aria-hidden="true" />
          {error}
        </p>
      ) : (
        <p id={`${id}-hint`} className="mt-1.5 flex items-start gap-1.5 text-sm text-ink-600">
          <Info className="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-500" aria-hidden="true" />
          <span>
            {hint ??
              'Markdown. Raw HTML is escaped and rendered as text — that is deliberate, and it is what stops a pasted <script> becoming one.'}
          </span>
        </p>
      )}
    </div>
  )
}

/**
 * Laravel sets XSRF-TOKEN on every response. It is URL-encoded, and sending it un-decoded is
 * a 419 that looks like a broken preview.
 */
function readXsrfToken(): string {
  if (typeof document === 'undefined') return ''

  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/)

  return match ? decodeURIComponent(match[1]) : ''
}
