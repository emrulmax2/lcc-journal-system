import { Link } from 'react-router-dom'
import { motion } from 'framer-motion'
import { ArrowUpRight, BookOpen, Quote, Users } from 'lucide-react'
import ImageWithFallback from './ImageWithFallback'
import { PHOTO, unsplash } from '@/lib/images'
import type { Article, Journal, NewsItem } from '@/lib/data'
import { fadeUp, hoverLift } from '@/lib/motion'

const dateFmt = new Intl.DateTimeFormat('en-GB', {
  day: 'numeric',
  month: 'short',
  year: 'numeric',
})

export function formatDate(iso: string) {
  if (iso === '—') return iso
  return dateFmt.format(new Date(iso))
}

export function OpenAccessBadge() {
  return (
    <span className="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2.5 py-1 text-[11px] font-semibold text-gold-700">
      <svg viewBox="0 0 24 24" className="h-3 w-3" aria-hidden="true" fill="currentColor">
        <path d="M12 2a5 5 0 0 0-5 5v3H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8a2 2 0 0 0-2-2H9V7a3 3 0 0 1 6 0h2a5 5 0 0 0-5-5Z" />
      </svg>
      Open access
    </span>
  )
}

export function JournalCard({ journal }: { journal: Journal }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        to={`/articles?journal=${journal.slug}`}
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <div className="aspect-[16/9] w-full overflow-hidden">
          <ImageWithFallback
            src={unsplash(PHOTO[journal.photo], 800, 450)}
            seed={journal.slug}
            alt={`${journal.title} — ${journal.field}`}
            width={800}
            height={450}
            className="transition-transform duration-500 group-hover:scale-[1.04]"
          />
        </div>

        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-center justify-between gap-2">
            <span className="text-xs font-medium text-brand-700">{journal.field}</span>
            <OpenAccessBadge />
          </div>

          <h3 className="mt-2 font-serif text-xl leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {journal.title}
          </h3>
          <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{journal.description}</p>

          <dl className="mt-4 grid grid-cols-3 gap-2 border-t border-ink-200 pt-4">
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Impact</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {journal.impactFactor.toFixed(1)}
              </dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Articles</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {journal.articles.toLocaleString()}
              </dd>
            </div>
            <div>
              <dt className="text-[11px] uppercase tracking-wide text-ink-500">Editors</dt>
              <dd className="text-sm font-semibold text-ink-900">
                {journal.editors.toLocaleString()}
              </dd>
            </div>
          </dl>
        </div>
      </Link>
    </motion.article>
  )
}

export function ArticleCard({ article }: { article: Article }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        to={`/articles/${article.slug}`}
        className="card group flex h-full cursor-pointer gap-4 overflow-hidden p-4 hover:shadow-lift sm:p-5"
      >
        <div className="hidden h-28 w-28 shrink-0 overflow-hidden rounded-lg sm:block">
          <ImageWithFallback
            src={unsplash(PHOTO[article.photo], 320, 320)}
            seed={article.slug}
            alt=""
            width={320}
            height={320}
            className="transition-transform duration-500 group-hover:scale-[1.06]"
          />
        </div>

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-2 text-xs">
            <span className="rounded-full bg-brand-50 px-2.5 py-1 font-semibold text-brand-800">
              {article.type}
            </span>
            <span className="text-ink-500">{formatDate(article.date)}</span>
          </div>

          <h3 className="mt-2 font-serif text-lg leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {article.title}
          </h3>

          <p className="mt-1.5 truncate text-sm text-ink-600">
            {article.authors.join(', ')} · {article.journal}
          </p>

          <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-ink-600">
            <span className="inline-flex items-center gap-1.5">
              <Users className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
              {article.views.toLocaleString()} views
            </span>
            <span className="inline-flex items-center gap-1.5">
              <Quote className="h-3.5 w-3.5 text-ink-500" aria-hidden="true" />
              {article.citations} citations
            </span>
          </div>
        </div>
      </Link>
    </motion.article>
  )
}

export function NewsCard({ item }: { item: NewsItem }) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <a
        href="#"
        className="card group flex h-full cursor-pointer flex-col overflow-hidden hover:shadow-lift"
      >
        <div className="aspect-[16/10] w-full overflow-hidden">
          <ImageWithFallback
            src={unsplash(PHOTO[item.photo], 700, 440)}
            seed={item.slug}
            alt=""
            width={700}
            height={440}
            className="transition-transform duration-500 group-hover:scale-[1.04]"
          />
        </div>
        <div className="flex flex-1 flex-col p-5">
          <div className="flex items-center gap-2 text-xs">
            <span className="font-semibold uppercase tracking-wide text-brand-700">
              {item.category}
            </span>
            <span className="text-ink-500">{formatDate(item.date)}</span>
          </div>
          <h3 className="mt-2 font-serif text-lg leading-snug text-ink-900 transition-colors duration-200 group-hover:text-brand-800">
            {item.title}
          </h3>
          <p className="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{item.excerpt}</p>
          <span className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-brand-800">
            Read the story
            <ArrowUpRight
              className="h-4 w-4 transition-transform duration-200 group-hover:translate-x-0.5 group-hover:-translate-y-0.5"
              aria-hidden="true"
            />
          </span>
        </div>
      </a>
    </motion.article>
  )
}

export function TopicCard({
  title,
  articles,
  editors,
  photo,
  deadline,
}: {
  title: string
  articles: number
  editors: number
  photo: keyof typeof PHOTO
  deadline: string
}) {
  return (
    <motion.article variants={fadeUp} {...hoverLift} className="h-full">
      <Link
        to="/journals"
        className="card group relative flex h-full min-h-[260px] cursor-pointer flex-col justify-end overflow-hidden p-6 text-white hover:shadow-lift"
      >
        <span className="absolute inset-0">
          <ImageWithFallback
            src={unsplash(PHOTO[photo], 700, 500)}
            seed={title}
            alt=""
            width={700}
            height={500}
            className="transition-transform duration-700 group-hover:scale-[1.06]"
          />
          {/* Scrim keeps text at 4.5:1 over any photo. */}
          <span
            aria-hidden="true"
            className="absolute inset-0 bg-gradient-to-t from-ink-950/90 via-ink-900/60 to-ink-900/20"
          />
        </span>

        <span className="relative">
          <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-2.5 py-1 text-[11px] font-semibold backdrop-blur">
            <BookOpen className="h-3 w-3" aria-hidden="true" />
            Research Topic
          </span>
          <h3 className="mt-3 font-serif text-xl leading-snug text-white">{title}</h3>
          <p className="mt-2 text-sm text-ink-200">
            {articles} articles · {editors} topic editors
          </p>
          <p className="mt-1 text-xs text-ink-300">
            Abstract deadline {formatDate(deadline)}
          </p>
        </span>
      </Link>
    </motion.article>
  )
}
