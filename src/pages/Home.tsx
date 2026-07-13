import { useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { motion, useReducedMotion, useScroll, useTransform } from 'framer-motion'
import {
  ArrowRight,
  ClipboardCheck,
  Database,
  FileSearch,
  MessageSquareQuote,
  Search,
  Send,
  Sparkles,
  UserCheck,
} from 'lucide-react'
import Counter from '@/components/Counter'
import ImageWithFallback from '@/components/ImageWithFallback'
import { ArticleCard, JournalCard, NewsCard, TopicCard } from '@/components/Cards'
import { Reveal, RevealGroup, RevealItem } from '@/components/Reveal'
import { ARTICLES, JOURNALS, NEWS, RESEARCH_TOPICS, STATS } from '@/lib/data'
import { PHOTO, unsplash } from '@/lib/images'
import { easeOut, fadeUp, staggerParent, viewportOnce } from '@/lib/motion'

export default function Home() {
  return (
    <>
      <Hero />
      <Stats />
      <AuthorPaths />
      <FeaturedJournals />
      <HowPublishingWorks />
      <ResearchTopics />
      <Impact />
      <LatestResearch />
      <News />
    </>
  )
}

/* --------------------------------- Hero --------------------------------- */

function Hero() {
  const [query, setQuery] = useState('')
  const navigate = useNavigate()
  const reduceMotion = useReducedMotion()
  const { scrollY } = useScroll()

  // Gentle parallax on the hero photo. Disabled outright for reduced-motion users.
  const y = useTransform(scrollY, [0, 600], [0, reduceMotion ? 0 : 90])
  const scale = useTransform(scrollY, [0, 600], [1, reduceMotion ? 1 : 1.08])

  const search = (e: React.FormEvent) => {
    e.preventDefault()
    navigate(`/articles?q=${encodeURIComponent(query.trim())}`)
  }

  return (
    <section className="relative isolate overflow-hidden bg-ink-900">
      <motion.div style={{ y, scale }} className="absolute inset-0 -z-10">
        <ImageWithFallback
          src={unsplash(PHOTO.aurora, 1920, 1200)}
          seed="hero-aurora"
          alt="A researcher on an ice field beneath the aurora borealis"
          width={1920}
          height={1200}
          priority
        />
      </motion.div>
      {/* Scrim: guarantees 4.5:1 for the headline and search field over the photo. */}
      <div
        aria-hidden="true"
        className="absolute inset-0 -z-10 bg-gradient-to-br from-ink-950/92 via-ink-900/80 to-brand-900/70"
      />

      {/* Extra top padding clears the fixed navbar that floats over this photo. */}
      <div className="container-page pb-24 pt-32 sm:pb-32 sm:pt-40 lg:pb-40 lg:pt-48">
        <motion.div
          variants={staggerParent(0.1)}
          initial="hidden"
          animate="visible"
          className="max-w-3xl"
        >
          <motion.p
            variants={fadeUp}
            className="inline-flex items-center gap-2 rounded-full border border-white/20 bg-white/10 px-3 py-1.5 text-xs font-semibold text-brand-100 backdrop-blur"
          >
            <Sparkles className="h-3.5 w-3.5" aria-hidden="true" />
            All 6 journals now indexed with an Impact Factor
          </motion.p>

          <motion.h1
            variants={fadeUp}
            className="mt-6 font-serif text-4xl leading-[1.08] text-white sm:text-5xl lg:text-6xl"
          >
            Where scientists empower society
          </motion.h1>

          <motion.p
            variants={fadeUp}
            className="mt-5 max-w-prose text-lg leading-relaxed text-ink-200"
          >
            Open-access publishing and an end-to-end journal management system — submission, peer
            review, decision and production, in one place.
          </motion.p>

          {/* The search bar IS the hero CTA. One pill, with the submit button nested inside
              it rather than floating alongside — on mobile it drops below as its own pill,
              because a 44px inline button would eat the field at 375px. */}
          <motion.form
            variants={fadeUp}
            onSubmit={search}
            role="search"
            className="relative mt-9 max-w-2xl"
          >
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
              value={query}
              onChange={(e) => setQuery(e.target.value)}
              placeholder="Search articles, journals, topics"
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
          </motion.form>

          {/* Popular searches as pills — reduces friction to the first search. */}
          <motion.div variants={fadeUp} className="mt-6 flex flex-wrap items-center gap-2">
            <span className="mr-1 text-sm text-ink-200">Popular:</span>
            {['coral bleaching', 'mRNA vaccines', 'orbital debris', 'permafrost'].map((t) => (
              <Link
                key={t}
                to={`/articles?q=${encodeURIComponent(t)}`}
                className="inline-flex min-h-[44px] cursor-pointer items-center rounded-full border
                           border-white/30 bg-white/10 px-4 text-sm text-white backdrop-blur
                           transition-colors duration-200 hover:border-white hover:bg-white/25"
              >
                {t}
              </Link>
            ))}
          </motion.div>

          {/* Author entry point, given a rule and a line of context so it reads as a
              deliberate row rather than a button left floating under the search bar. */}
          <motion.div
            variants={fadeUp}
            className="mt-10 flex flex-wrap items-center gap-x-5 gap-y-3 border-t border-white/15 pt-8"
          >
            <p className="text-sm text-ink-200">Ready to publish?</p>
            <Link to="/submit" className="btn-outline-light">
              Submit your research
              <ArrowRight className="h-4 w-4" aria-hidden="true" />
            </Link>
            <Link
              to="/journals"
              className="cursor-pointer text-sm text-white underline decoration-white/40
                         underline-offset-4 transition-colors duration-200 hover:decoration-white"
            >
              or browse all journals
            </Link>
          </motion.div>
        </motion.div>
      </div>
    </section>
  )
}

/* -------------------------------- Stats --------------------------------- */

function Stats() {
  return (
    <section aria-label="Platform at a glance" className="border-b border-ink-200 bg-white">
      <RevealGroup className="container-page grid gap-8 py-14 sm:grid-cols-3" stagger={0.12}>
        {STATS.map((s) => (
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

const PATHS = [
  {
    icon: FileSearch,
    title: 'Find a journal',
    body: 'Match your manuscript to one of 6 open-access journals across 1,700+ disciplines.',
    to: '/journals',
    cta: 'Browse journals',
  },
  {
    icon: MessageSquareQuote,
    title: 'Find a topic',
    body: 'Join a Research Topic — a curated collection led by editors in your field.',
    to: '/journals',
    cta: 'Explore topics',
  },
  {
    icon: Send,
    title: 'Submit your research',
    body: 'A guided submission in five steps, with ethics and data checks built in.',
    to: '/submit',
    cta: 'Start submission',
  },
  {
    icon: Database,
    title: 'Manage your data',
    body: 'Deposit datasets, code and protocols. Each gets a citable DOI of its own.',
    to: '/dashboard',
    cta: 'Open data hub',
  },
]

function AuthorPaths() {
  return (
    <section className="container-page py-20">
      <Reveal className="max-w-2xl">
        <p className="eyebrow">For researchers</p>
        <h2 className="mt-3 font-serif text-3xl sm:text-4xl">Everything from idea to indexed</h2>
        <p className="mt-4 max-w-prose text-ink-600">
          Four ways in. Whether you are scoping a journal or tracking a manuscript already in
          review, start here.
        </p>
      </Reveal>

      <RevealGroup className="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        {PATHS.map(({ icon: Icon, ...p }) => (
          <RevealItem key={p.title} className="h-full">
            <motion.div whileHover={{ y: -4 }} transition={{ duration: 0.2, ease: easeOut }} className="h-full">
              <Link
                to={p.to}
                className="card group flex h-full cursor-pointer flex-col p-6 hover:border-brand-300 hover:shadow-lift"
              >
                <span className="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand-700 transition-colors duration-200 group-hover:bg-brand-700 group-hover:text-white">
                  <Icon className="h-6 w-6" aria-hidden="true" />
                </span>
                <h3 className="mt-5 font-serif text-xl text-ink-900">{p.title}</h3>
                <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{p.body}</p>
                <span className="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-brand-800">
                  {p.cta}
                  <ArrowRight
                    className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-1"
                    aria-hidden="true"
                  />
                </span>
              </Link>
            </motion.div>
          </RevealItem>
        ))}
      </RevealGroup>
    </section>
  )
}

/* ---------------------------- Featured journals --------------------------- */

function FeaturedJournals() {
  return (
    <section className="bg-ink-50 py-20">
      <div className="container-page">
        <Reveal className="flex flex-wrap items-end justify-between gap-4">
          <div className="max-w-2xl">
            <p className="eyebrow">Journals</p>
            <h2 className="mt-3 font-serif text-3xl sm:text-4xl">
              Six journals. One editorial standard.
            </h2>
            <p className="mt-4 max-w-prose text-ink-600">
              Every journal is fully open access, editor-led and indexed. Reviews are published
              alongside the article.
            </p>
          </div>
          <Link to="/journals" className="btn-secondary">
            All journals
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>

        <RevealGroup className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {JOURNALS.slice(0, 3).map((j) => (
            <JournalCard key={j.slug} journal={j} />
          ))}
        </RevealGroup>
      </div>
    </section>
  )
}

/* -------------------------- How publishing works -------------------------- */

const STEPS = [
  {
    icon: Send,
    title: 'Submit',
    body: 'Upload the manuscript, declare funding, ethics and data availability. Format-free first submission.',
    meta: 'About 20 minutes',
  },
  {
    icon: ClipboardCheck,
    title: 'Editor check',
    body: 'A specialist editor confirms scope, integrity and reporting standards before review begins.',
    meta: 'Median 4 days',
  },
  {
    icon: UserCheck,
    title: 'Collaborative peer review',
    body: 'Reviewers and authors work in a shared forum until the report is agreed. Names published on acceptance.',
    meta: 'Median 38 days',
  },
  {
    icon: Sparkles,
    title: 'Decision and production',
    body: 'The handling editor decides. Accepted work is typeset, given a DOI and indexed within a fortnight.',
    meta: 'Median 9 days',
  },
]

function HowPublishingWorks() {
  return (
    <section className="container-page py-20">
      <Reveal className="max-w-2xl">
        <p className="eyebrow">How it works</p>
        <h2 className="mt-3 font-serif text-3xl sm:text-4xl">
          From submission to indexed in a median of 51 days
        </h2>
        <p className="mt-4 max-w-prose text-ink-600">
          The same pipeline the editorial office runs on — visible to authors at every stage.
        </p>
      </Reveal>

      <motion.ol
        variants={staggerParent(0.12)}
        initial="hidden"
        whileInView="visible"
        viewport={viewportOnce}
        className="relative mt-12 grid gap-8 lg:grid-cols-4"
      >
        {/* The connecting rail draws itself as the list scrolls in. */}
        <motion.span
          aria-hidden="true"
          initial={{ scaleX: 0 }}
          whileInView={{ scaleX: 1 }}
          viewport={viewportOnce}
          transition={{ duration: 0.9, ease: easeOut, delay: 0.2 }}
          className="absolute left-0 right-0 top-6 hidden h-px origin-left bg-gradient-to-r from-brand-300 via-brand-400 to-brand-100 lg:block"
        />

        {STEPS.map(({ icon: Icon, ...s }, i) => (
          <motion.li key={s.title} variants={fadeUp} className="relative">
            <span className="relative z-dropdown inline-flex h-12 w-12 items-center justify-center rounded-full border-2 border-brand-200 bg-white text-brand-700">
              <Icon className="h-5 w-5" aria-hidden="true" />
            </span>
            <p className="mt-4 text-xs font-semibold uppercase tracking-wider text-ink-500">
              Step {i + 1}
            </p>
            <h3 className="mt-1 font-serif text-xl text-ink-900">{s.title}</h3>
            <p className="mt-2 text-sm leading-relaxed text-ink-600">{s.body}</p>
            <p className="mt-3 inline-block rounded-md bg-brand-50 px-2 py-1 text-xs font-semibold text-brand-800">
              {s.meta}
            </p>
          </motion.li>
        ))}
      </motion.ol>

      <Reveal className="mt-12" delay={0.1}>
        <Link to="/submit" className="btn-primary">
          Start your submission
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </Link>
      </Reveal>
    </section>
  )
}

/* ----------------------------- Research topics ---------------------------- */

function ResearchTopics() {
  return (
    <section className="container-page py-20">
      <Reveal className="max-w-2xl">
        <p className="eyebrow">Research Topics</p>
        <h2 className="mt-3 font-serif text-3xl sm:text-4xl">Open calls for papers</h2>
        <p className="mt-4 max-w-prose text-ink-600">
          Curated collections led by topic editors. Contribute an article before the abstract
          deadline.
        </p>
      </Reveal>

      <RevealGroup className="mt-10 grid gap-6 md:grid-cols-3">
        {RESEARCH_TOPICS.map((t) => (
          <TopicCard key={t.title} {...t} />
        ))}
      </RevealGroup>
    </section>
  )
}

/* --------------------------------- Impact -------------------------------- */

function Impact() {
  return (
    <section className="bg-ink-900 py-20 text-white">
      <div className="container-page grid items-center gap-12 lg:grid-cols-2">
        <Reveal>
          <p className="eyebrow text-brand-400">Impact</p>
          <h2 className="mt-3 font-serif text-3xl text-white sm:text-4xl">
            Research is only useful once it is read
          </h2>
          <p className="mt-5 max-w-prose leading-relaxed text-ink-300">
            All six journals we publish have received a Journal Impact Factor, and all six carry a
            CiteScore. Every article is free to read on the day it is published — no paywall, no
            embargo, no exceptions.
          </p>

          <dl className="mt-8 grid grid-cols-3 gap-6">
            {[
              { k: 'Journals with an IF', v: 6, s: '' },
              { k: 'Median days to decision', v: 51, s: '' },
              { k: 'Reviews published openly', v: 1, s: 'M' },
            ].map((m) => (
              <div key={m.k}>
                <dd className="font-serif text-3xl text-brand-300">
                  <Counter value={m.v} suffix={m.s} />
                </dd>
                <dt className="mt-1 text-xs leading-snug text-ink-400">{m.k}</dt>
              </div>
            ))}
          </dl>

          <Link to="/articles" className="btn mt-8 bg-white text-ink-900 hover:bg-ink-200">
            Read the research
            <ArrowRight className="h-4 w-4" aria-hidden="true" />
          </Link>
        </Reveal>

        <Reveal delay={0.1}>
          <div className="grid grid-cols-2 gap-4">
            <div className="aspect-[3/4] overflow-hidden rounded-xl">
              <ImageWithFallback
                src={unsplash(PHOTO.microscope, 500, 660)}
                seed="impact-1"
                alt="A researcher examining a sample under a microscope"
                width={500}
                height={660}
              />
            </div>
            <div className="mt-10 aspect-[3/4] overflow-hidden rounded-xl">
              <ImageWithFallback
                src={unsplash(PHOTO.earthNetwork, 500, 660)}
                seed="impact-2"
                alt="A globe rendered as a network of connected points of light"
                width={500}
                height={660}
              />
            </div>
          </div>
        </Reveal>
      </div>
    </section>
  )
}

/* ----------------------------- Latest research ---------------------------- */

function LatestResearch() {
  return (
    <section className="container-page py-20">
      <Reveal className="flex flex-wrap items-end justify-between gap-4">
        <div className="max-w-2xl">
          <p className="eyebrow">Latest research</p>
          <h2 className="mt-3 font-serif text-3xl sm:text-4xl">Published this month</h2>
        </div>
        <Link to="/articles" className="btn-secondary">
          All articles
          <ArrowRight className="h-4 w-4" aria-hidden="true" />
        </Link>
      </Reveal>

      <RevealGroup className="mt-10 grid gap-5 lg:grid-cols-2">
        {ARTICLES.slice(0, 4).map((a) => (
          <ArticleCard key={a.slug} article={a} />
        ))}
      </RevealGroup>
    </section>
  )
}

/* ---------------------------------- News --------------------------------- */

function News() {
  return (
    <section className="bg-ink-50 py-20">
      <div className="container-page">
        <Reveal className="max-w-2xl">
          <p className="eyebrow">Newsroom</p>
          <h2 className="mt-3 font-serif text-3xl sm:text-4xl">Science, in the open</h2>
        </Reveal>

        <RevealGroup className="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
          {NEWS.map((n) => (
            <NewsCard key={n.slug} item={n} />
          ))}
        </RevealGroup>
      </div>
    </section>
  )
}
