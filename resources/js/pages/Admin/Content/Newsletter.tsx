import { Head } from '@inertiajs/react'
import { CheckCircle2, Clock, Download, Info, Mail, XCircle } from 'lucide-react'
import { ContentShell } from '@/components/admin/ContentShell'
import { Banner, EmptyState, Panel } from '@/components/admin/Shell'
import { formatDate } from '@/lib/format'
import { contentHref, type ContentPage } from '@/lib/content'

/**
 * The newsletter list. READ-ONLY.
 *
 * THE EXPORT IS CONFIRMED SUBSCRIBERS ONLY, and the scope is applied on the server
 * (NewsletterSubscriber::mailable()) rather than left to whoever opens the CSV.
 *
 * An address typed into a public box is not consent under UK GDPR/PECR. It is a claim by
 * whoever typed it, and it may not be theirs — which is exactly why the footer form promises a
 * confirmation email, and why nothing may be sent until the person clicks the link in their own
 * inbox. An export that included unconfirmed addresses would be the moment that promise became
 * an unlawful send.
 *
 * There is no "add subscriber" and no "confirm on their behalf" on this screen, because both
 * would be somebody other than the subscriber consenting for them.
 *
 * The confirmation token is nowhere in these props. It is a capability — whoever holds it can
 * confirm or unsubscribe that address without proving they own the inbox.
 */

type Status = 'confirmed' | 'unconfirmed' | 'unsubscribed'

type Subscriber = {
  id: number
  email: string
  status: Status
  confirmedAt: string | null
  unsubscribedAt: string | null
  signedUpAt: string | null
}

type Props = ContentPage<{
  subscribers: Subscriber[]
  counts: { confirmed: number; unconfirmed: number; unsubscribed: number; total: number }
}>

export default function ContentNewsletter({ subscribers, counts, meta }: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <ContentShell
        title="Newsletter"
        description="Who signed up, who confirmed, and who may lawfully be sent to."
        actions={
          counts.confirmed > 0 && (
            // A plain <a>: this is a file download, not an Inertia visit.
            <a href={contentHref.newsletterExport} className="btn-primary">
              <Download className="h-4 w-4" aria-hidden="true" />
              Export confirmed ({counts.confirmed})
            </a>
          )
        }
      >
        <div className="space-y-8">
          <div className="grid gap-4 sm:grid-cols-3">
            <Tile
              icon={CheckCircle2}
              tone="success"
              label="Confirmed"
              value={counts.confirmed}
              note="The only people anything may be sent to."
            />
            <Tile
              icon={Clock}
              tone="gold"
              label="Unconfirmed"
              value={counts.unconfirmed}
              note="Typed an address; never clicked the link. Not consent."
            />
            <Tile
              icon={XCircle}
              tone="ink"
              label="Unsubscribed"
              value={counts.unsubscribed}
              note="Kept, so a re-signup cannot silently overwrite the refusal."
            />
          </div>

          <Banner tone="info" icon={Info} title="The export contains confirmed subscribers only">
            That is enforced on the server, not by whoever opens the CSV. An unconfirmed address
            is a claim by whoever typed it — it may not be theirs, and sending to it is a PECR
            breach, not an over-cautious rule.
          </Banner>

          {subscribers.length === 0 ? (
            <EmptyState
              icon={Mail}
              title="Nobody has signed up yet"
              body="The footer form now stores the address and sends a confirmation email. It previously ran a fake 900ms spinner, discarded the address, and told the person to check their inbox."
            />
          ) : (
            <Panel
              title={`${counts.total} ${counts.total === 1 ? 'subscriber' : 'subscribers'}`}
              description="Newest first."
            >
              <div className="overflow-x-auto">
                <table className="w-full min-w-[36rem] text-left text-sm">
                  <thead>
                    <tr className="border-b border-ink-200 text-xs uppercase tracking-wide text-ink-600">
                      <th scope="col" className="py-2.5 pr-4 font-semibold">
                        Email
                      </th>
                      <th scope="col" className="py-2.5 pr-4 font-semibold">
                        Status
                      </th>
                      <th scope="col" className="py-2.5 pr-4 font-semibold">
                        Signed up
                      </th>
                      <th scope="col" className="py-2.5 font-semibold">
                        Confirmed
                      </th>
                    </tr>
                  </thead>

                  {/* No <Reveal> here: it would have to wrap <tr>s in a <div>, which is invalid
                      inside a table and which React would hydrate differently from the server. */}
                  <tbody className="divide-y divide-ink-200">
                    {subscribers.map((subscriber) => (
                      <tr key={subscriber.id}>
                        <td className="py-3 pr-4 font-medium text-ink-900">{subscriber.email}</td>
                        <td className="py-3 pr-4">
                          <StatusBadge status={subscriber.status} />
                        </td>
                        <td className="py-3 pr-4 text-ink-600">
                          {formatDate(subscriber.signedUpAt)}
                        </td>
                        <td className="py-3 text-ink-600">
                          {subscriber.status === 'unsubscribed'
                            ? `Unsubscribed ${formatDate(subscriber.unsubscribedAt)}`
                            : formatDate(subscriber.confirmedAt)}
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>
            </Panel>
          )}
        </div>
      </ContentShell>
    </>
  )
}

function Tile({
  icon: Icon,
  tone,
  label,
  value,
  note,
}: {
  icon: typeof CheckCircle2
  tone: 'success' | 'gold' | 'ink'
  label: string
  value: number
  note: string
}) {
  const tones = {
    success: 'text-success-800',
    gold: 'text-gold-700',
    ink: 'text-ink-700',
  }

  return (
    <div className="card p-5">
      <p className={`flex items-center gap-2 text-sm font-semibold ${tones[tone]}`}>
        <Icon className="h-4 w-4" aria-hidden="true" />
        {label}
      </p>
      <p className="mt-2 font-serif text-3xl text-ink-900">{value}</p>
      <p className="mt-1 text-xs text-ink-600">{note}</p>
    </div>
  )
}

/** Icon AND word. "Confirmed" and "unconfirmed" differ by one prefix and a hue otherwise. */
function StatusBadge({ status }: { status: Status }) {
  if (status === 'confirmed') {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-success-50 px-2.5 py-1 text-[11px] font-semibold text-success-800">
        <CheckCircle2 className="h-3.5 w-3.5" aria-hidden="true" />
        Confirmed
      </span>
    )
  }

  if (status === 'unsubscribed') {
    return (
      <span className="inline-flex items-center gap-1.5 rounded-full bg-ink-100 px-2.5 py-1 text-[11px] font-semibold text-ink-700">
        <XCircle className="h-3.5 w-3.5" aria-hidden="true" />
        Unsubscribed
      </span>
    )
  }

  return (
    <span className="inline-flex items-center gap-1.5 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
      <Clock className="h-3.5 w-3.5" aria-hidden="true" />
      Unconfirmed
    </span>
  )
}
