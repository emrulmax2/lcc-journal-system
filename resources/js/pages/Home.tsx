import { useState } from 'react'
import { Head, Link, router } from '@inertiajs/react'
import { motion, useReducedMotion, useScroll, useTransform } from 'framer-motion'
import { ArrowRight, Search, Sparkles } from 'lucide-react'
import Counter from '@/components/Counter'
import ImageWithFallback, { CardImage } from '@/components/ImageWithFallback'
import { ArticleCard, JournalCard, NewsCard, TopicCard } from '@/components/Cards'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { icon as resolveIcon } from '@/lib/icons'
import { easeOut } from '@/lib/motion'
import { mediaSetting, setting, toArticleCard, toJournalCard, toNewsCard, toTopicCard, useShared } from '@/lib/props'
import type {
  Article,
  HomeSection,
  HomeSections,
  HomeTopic,
  Journal,
  Meta,
  NewsItem,
  Stat,
} from '@/lib/props'

type Props = {
  /**
   * EVERY piece of editorial copy on this page: the eyebrow, heading and blurb of each
   * band, the four author-path cards, and the five pipeline steps. All of it used to be
   * literals in this file — including four invented medians ("About 20 minutes", "Median 4
   * days", "Median 38 days", "Median 9 days") presented as this platform's measured
   * performance. They were computed from nothing and contradicted the "Median 51 days" in
   * the navbar. Both are gone.
   *
   * A section an editor has hidden is ABSENT from this map, and the band renders nothing —
   * no crash, no empty strip with a heading and no content.
   */
  sections: HomeSections
  stats: Stat[]
  featuredJournals: Journal[]
  latestArticles: Article[]
  researchTopics: HomeTopic[]
  news: NewsItem[]
  meta: Meta
}

export default function Home({
  sections,
  stats,
  featuredJournals,
  latestArticles,
  researchTopics,
  news,
  meta,
}: Props) {
  return (
    <>
      <Head>
        <title>{meta.title}</title>
      </Head>

      <Hero />
      <Stats stats={stats} />
      <AuthorPaths section={sections['author-paths']} />
      <FeaturedJournals section={sections['featured-journals']} journals={featuredJournals} />
      <HowPublishingWorks section={sections['how-it-works']} />
      <ResearchTopics section={sections['research-topics']} topics={researchTopics} />
      <Impact section={sections['impact']} stats={stats} />
      <LatestResearch section={sections['latest-research']} articles={latestArticles} />
      <Newsroom section={sections['newsroom']} items={news} />
    </>
  )
}

/* ---------------------------- Section heading ---------------------------- */

function SectionHeading({ section, className = '' }: { section: HomeSection; className?: string }) {
  return (
    <div className={className}>
      {section.eyebrow && <p className="eyebrow">{section.eyebrow}</p>}
      {section.heading && (
        <h2 className="mt-3 font-serif text-3xl sm:text-4xl">{section.heading}</h2>
      )}
      {section.blurb && <p className="mt-4 max-w-prose text-ink-600">{section.blurb}</p>}
    </div>
  )
}

/* --------------------------------- Hero --------------------------------- */

function Hero() {
  const { site } = useShared()
  const [query, setQuery] = useState('')
  const reduceMotion = useReducedMotion()

  const badge = setting(site, 'hero_badge')
  const heading = setting(site, 'hero_heading')
  const blurb = setting(site, 'hero_blurb')
  const placeholder = setting(site, 'hero_search_placeholder') ?? 'Search articles'
  const heroImage = mediaSetting(site, 'hero_image')

  // useScroll with no target reads the window scroll. It is SSR-safe: on the server it
  // resolves to a MotionValue(0) and touches nothing, and it never reads `window` during
  // render on the client either.
  const { scrollY } = useScroll()

  // Gentle parallax on the hero photo — decorative, and on the PHOTO only. Nothing that
  // carries text is animated here: framer serialises an `initial` variant straight into
  // the server-rendered style attribute, so an entrance animation on the headline would
  // ship `<h1 style="opacity:0">` to every crawler that reads this page.
  const y = useTransform(scrollY, [0, 600], [0, reduceMotion ? 0 : 90])
  const scale = useTransform(scrollY, [0, 600], [1, reduceMotion ? 1 : 1.08])

  const search = (e: React.FormEvent) => {
    e.preventDefault()
    const q = query.trim()
    router.get('/articles', q ? { q } : {})
  }

  return (
    <section className="relative isolate overflow-hidden bg-ink-900">
      {heroImage && (
        <motion.div style={{ y, scale }} className="absolute inset-0 -z-10">
          {/* The hero image set in the admin. When none is set, the section is just the
              teal/navy scrim over a solid dark background — no stock photograph stands in,
              because a stand-in hero asserts a mood the publisher did not choose. */}
          <ImageWithFallback
            src={heroImage.url}
            alt={heroImage.alt ?? ''}
            width={1920}
            height={1200}
            priority
          />
        </motion.div>
      )}
      {/* Scrim — LAYERED, so the headline, badge, subcopy AND the floating navbar all
          clear 4.5:1 over any hero photo. The previous single diagonal left the badge and
          subcopy washed out over a bright daylight image (see the reported screenshot).
            1. left-anchored horizontal: darkest on the left, where the text column sits;
            2. a vertical wash: extra darkness at the very top for the navbar, and at the
               bottom for the search pill.
          Both are stacked, so they compound — guaranteed contrast regardless of the image. */}
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 bg-gradient-to-r from-ink-950/96 via-ink-950/82 to-brand-900/55"
      />
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 bg-gradient-to-b from-ink-950/70 via-transparent to-ink-950/45"
      />

      {/* Extra top padding clears the fixed navbar that floats over this photo. */}
      <div className="container-page pb-24 pt-32 sm:pb-32 sm:pt-40 lg:pb-40 lg:pt-48">
        <div className="max-w-3xl">
          {badge && (
            <p className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-brand-100 backdrop-blur">
              <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
              {badge}
            </p>
          )}

          {heading && (
            <h1 className="mt-6 font-serif text-4xl leading-[1.08] text-white sm:text-5xl lg:text-6xl">
              {heading}
            </h1>
          )}

          {blurb && (
            <p className="mt-5 max-w-prose text-lg leading-relaxed text-ink-200">{blurb}</p>
          )}

          {/* The search bar IS the hero CTA. One pill, with the submit button nested inside
              it rather than floating alongside — on mobile it drops below as its own pill,
              because a 44px inline button would eat the field at 375px.

              The "Popular:" pills that used to sit under it are GONE. They were 'coral
              bleaching', 'mRNA vaccines', 'orbital debris' and 'permafrost' — the subjects
              of the six fictional demo journals. Every one of them ran a search that now
              returns nothing. */}
          <form onSubmit={search} role="search" className="relative mt-9 max-w-2xl">
            <label htmlFor="hero-search" className="sr-only">
              Search articles, journals and research topics
            </label>

            <Search
              className="pointer-events-none absolute left-5 top-8 h-5 w-5 -translate-y-1/2 text-ink-500 sm:top-1/2"
              aria-hidden="true"
            />
            <input
              id="hero-search"
              type="search"
              name="q"
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder={placeholder}
              className="h-16 w-full rounded-full border border-white/30 bg-white/95 pl-14 pr-6 text-base
                         text-ink-900 shadow-lift placeholder:text-ink-500 backdrop-blur transition-colors
                         duration-200 focus:border-white focus:bg-white sm:pr-40"
            />
            <button
              type="submit"
              className="btn mt-3 h-12 w-full bg-brand-700 text-white hover:bg-brand-800
                         sm:absolute sm:right-2 sm:top-1/2 sm:mt-0 sm:h-12 sm:w-auto sm:-translate-y-1/2 sm:px-8"
            >
              Search
            </button>
          </form>

          {/* Author entry point, given a rule and a line of context so it reads as a
              deliberate row rather than a button left floating under the search bar. */}
          <div className="mt-10 flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-white/15 pt-8">
            <p className="text-sm text-ink-200">Ready to publish?</p>
            <Link href="/submit" className="btn-outline-light">
              Submit your research
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
            <Link
              href="/journals"
              className="cursor-pointer text-sm text-white underline decoration-white/40
                         underline-offset-4 transition-colors duration-200 hover:decoration-white"
            >
              or browse all journals
            </Link>
          </div>
        </div>
      </div>
    </section>
  )
}

/* -------------------------------- Stats --------------------------------- */

function Stats({ stats }: { stats: Stat[] }) {
  if (stats.length === 0) return null

  return (
    <section aria-label="Platform at a glance" className="border-b border-ink-200 bg-white">
      <RevealGroup className="container-page grid gap-8 py-14 sm:grid-cols-3" stagger={0.12}>
        {stats.map((s) => (
          <RevealItem key={s.label} className="text-center sm:text-left">
            <p className="font-serif text-4xl text-brand-800 sm:text-5xl">
              <Counter value={s.value} suffix={s.suffix} decimals={s.decimals} />
            </p>
            <p className="mt-2 text-sm text-ink-600">{s.label}</p>
          </RevealItem>
        ))}
      </RevealGroup>
    </section>
  )
}

/* ---------------------------- Author path cards --------------------------- */

function AuthorPaths({ section }: { section?: HomeSection }) {
  if (!section || section.items.length === 0) return null

  return (
    <section className="container-page py-20">
      <SectionHeading section={section} className="max-w-2xl" />

      <RevealGroup className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {section.items.map((item) => {
          const Icon = resolveIcon(item.icon)

          const body = (
            <>
              {/* An icon name the map does not know renders NOTHING — not a hole, not a
                  fallback glyph, and never a crash in the SSR process. */}
              {Icon && (
                <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition-colors duration-200 group-hover:bg-brand-700 group-hover:text-white">
                  <Icon className="h-6 w-6" aria-hidden="true" />
                </span>
              )}
              <h3 className={`font-serif text-xl text-ink-900 ${Icon ? 'mt-5' : ''}`}>
                {item.title}
              </h3>
              {item.body && (
                <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{item.body}</p>
              )}
              {item.meta && <p className="mt-3 text-xs font-semibold text-ink-500">{item.meta}</p>}
              {item.ctaLabel && item.url && (
                <span className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800">
                  {item.ctaLabel}
                  <ArrowRight
                    className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
                    aria-hidden="true"
                  />
                </span>
              )}
            </>
          )

          return (
            <RevealItem key={item.title} className="h-full">
              <motion.div
                whileHover={{ y: -4 }}
                transition={{ duration: 0.2, ease: easeOut }}
                className="h-full"
              >
                {item.url ? (
                  <Link
                    href={item.url}
                    className="card group flex h-full cursor-pointer flex-col p-6 hover:border-brand-300 hover:shadow-lift"
                  >
                    {body}
                  </Link>
                ) : (
                  <div className="card group flex h-full flex-col p-6">{body}</div>
                )}
              </motion.div>
            </RevealItem>
          )
        })}
      </RevealGroup>
    </section>
  )
}

/* ---------------------------- Featured journals --------------------------- */

function FeaturedJournals({ section, journals }: { section?: HomeSection; journals: Journal[] }) {
  if (!section || journals.length === 0) return null

  return (
    <section className="bg-ink-50 py-20">
      <div className="container-page">
        <Reveal className="flex flex-wrap items-end justify-between gap-4">
          <SectionHeading section={section} className="max-w-2xl" />
          <Link href="/journals" className="btn-secondary">
            All journals
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>

        <RevealGroup className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {journals.map((j) => (
            <JournalCard key={j.slug} journal={toJournalCard(j)} />
          ))}
        </RevealGroup>
      </div>
    </section>
  )
}

/* -------------------------- How publishing works -------------------------- */

function HowPublishingWorks({ section }: { section?: HomeSection }) {
  if (!section || section.items.length === 0) return null

  return (
    <section className="container-page py-20">
      <SectionHeading section={section} className="max-w-2xl" />

      {/* Was a framer `initial="hidden"` stagger, which server-rendered the whole list at
          opacity:0. RevealGroup does the same job as a progressive enhancement: visible in
          the SSR HTML, revealed on scroll only once JS is running. */}
      <RevealGroup
        as="ol"
        className="relative mt-12 grid gap-8 md:grid-cols-2 lg:grid-cols-5"
        stagger={0.12}
      >
        {/* The connecting rail draws itself as the list scrolls in. Decorative and
            aria-hidden, so an entrance animation here costs a crawler nothing. */}
        <motion.span
          aria-hidden="true"
          initial={{ scaleX: 0 }}
          whileInView={{ scaleX: 1 }}
          viewport={{ once: true, amount: 0.25 }}
          transition={{ duration: 0.9, ease: easeOut, delay: 0.2 }}
          className="absolute left-0 right-0 top-6 hidden h-px origin-left bg-gradient-to-r from-brand-300 via-brand-400 to-brand-100 lg:block"
        />

        {section.items.map((step, i) => {
          const Icon = resolveIcon(step.icon)

          return (
            <RevealItem as="li" key={step.title} className="relative">
              <span className="relative z-dropdown inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-brand-200 bg-white text-brand-700">
                {Icon ? (
                  <Icon className="h-5 w-5" aria-hidden="true" />
                ) : (
                  <span className="font-serif text-lg" aria-hidden="true">
                    {i + 1}
                  </span>
                )}
              </span>
              <p className="mt-4 text-xs font-semibold uppercase tracking-wider text-ink-500">
                Step {i + 1}
              </p>
              <h3 className="mt-1 font-serif text-xl text-ink-900">{step.title}</h3>
              {step.body && (
                <p className="mt-2 text-sm leading-relaxed text-ink-600">{step.body}</p>
              )}
              {/* `meta` is NULL for every step now. It renders only when an editor has put a
                  real, owned figure there — not because one was written into this file. */}
              {step.meta && (
                <p className="mt-3 inline-block rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-800">
                  {step.meta}
                </p>
              )}
            </RevealItem>
          )
        })}
      </RevealGroup>

      <Reveal className="mt-12" delay={0.1}>
        <Link href="/submit" className="btn-primary">
          Start your submission
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </Link>
      </Reveal>
    </section>
  )
}

/* ----------------------------- Research topics ---------------------------- */

function ResearchTopics({ section, topics }: { section?: HomeSection; topics: HomeTopic[] }) {
  if (!section || topics.length === 0) return null

  return (
    <section className="container-page py-20">
      <Reveal className="flex flex-wrap items-end justify-between gap-4">
        <SectionHeading section={section} className="max-w-2xl" />
        <Link href="/topics" className="btn-secondary">
          All Research Topics
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </Link>
      </Reveal>

      <RevealGroup className="mt-10 grid gap-6 md:grid-cols-3">
        {topics.map((t) => (
          <TopicCard key={t.slug} topic={toTopicCard(t)} />
        ))}
      </RevealGroup>
    </section>
  )
}

/* --------------------------------- Impact -------------------------------- */

function Impact({ section, stats }: { section?: HomeSection; stats: Stat[] }) {
  if (!section) return null

  return (
    <section className="bg-ink-900 py-20 text-white">
      <div className="container-page grid items-center gap-12 lg:grid-cols-2">
        <Reveal>
          {section.eyebrow && <p className="eyebrow text-brand-400">{section.eyebrow}</p>}
          {section.heading && (
            <h2 className="mt-3 font-serif text-3xl text-white sm:text-4xl">{section.heading}</h2>
          )}
          {/* Every number in this block used to be invented — "6 journals with an Impact
              Factor", "1M reviews published openly". They are Frontiers' figures, not ours.
              The counters below render the SAME real aggregates as the stats band, passed
              from HomeController, so the page cannot claim more than we have. */}
          {section.blurb && (
            <p className="mt-5 max-w-prose leading-relaxed text-ink-300">{section.blurb}</p>
          )}

          {stats.length > 0 && (
            <dl className="mt-8 grid grid-cols-3 gap-6">
              {stats.map((s) => (
                <div key={s.label}>
                  <dd className="font-serif text-3xl text-brand-300">
                    <Counter value={s.value} suffix={s.suffix} decimals={s.decimals} />
                  </dd>
                  <dt className="mt-1 text-xs leading-snug text-ink-300">{s.label}</dt>
                </div>
              ))}
            </dl>
          )}

          <Link href="/articles" className="btn mt-8 bg-white text-ink-900 hover:bg-ink-200">
            Read the research
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>

        <Reveal delay={0.1}>
          {section.image ? (
            <figure className="overflow-hidden rounded-xl">
              <div className="aspect-[4/3]">
                <CardImage
                  image={section.image}
                  photo={null}
                  alt=""
                  width={800}
                  height={600}
                />
              </div>
              {(section.image.caption || section.image.credit) && (
                <figcaption className="mt-3 text-sm text-ink-300">
                  {section.image.caption}
                  {section.image.caption && section.image.credit ? ' ' : ''}
                  {section.image.credit && <span className="text-ink-400">{section.image.credit}</span>}
                </figcaption>
              )}
            </figure>
          ) : null}
        </Reveal>
      </div>
    </section>
  )
}

/* ----------------------------- Latest research ---------------------------- */

function LatestResearch({ section, articles }: { section?: HomeSection; articles: Article[] }) {
  if (!section || articles.length === 0) return null

  return (
    <section className="container-page py-20">
      {/* The heading used to be "Published this month". The controller sends the latest four
          articles WHENEVER they were published — so on a quiet month that headline was
          simply false. It is now whatever the CMS says, seeded as "Recently published". */}
      <Reveal className="flex flex-wrap items-end justify-between gap-4">
        <SectionHeading section={section} className="max-w-2xl" />
        <Link href="/articles" className="btn-secondary">
          All articles
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </Link>
      </Reveal>

      <RevealGroup className="mt-10 grid gap-5 lg:grid-cols-2">
        {articles.map((a) => (
          <ArticleCard key={a.slug} article={toArticleCard(a)} />
        ))}
      </RevealGroup>
    </section>
  )
}

/* -------------------------------- Newsroom -------------------------------- */

function Newsroom({ section, items }: { section?: HomeSection; items: NewsItem[] }) {
  if (!section || items.length === 0) return null

  return (
    <section className="bg-ink-50 py-20">
      <div className="container-page">
        <Reveal className="flex flex-wrap items-end justify-between gap-4">
          <SectionHeading section={section} className="max-w-2xl" />
          <Link href="/news" className="btn-secondary">
            All news
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>

        <RevealGroup className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {items.map((n) => (
            <NewsCard key={n.slug} item={toNewsCard(n)} />
          ))}
        </RevealGroup>
      </div>
    </section>
  )
}
