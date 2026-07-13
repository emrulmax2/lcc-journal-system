import { PHOTO, type PhotoKey } from './images'

/* ------------------------------------------------------------------ *
 * Demo content. Everything here is fictional — journals, authors,     *
 * manuscripts and metrics are invented for the prototype.             *
 * ------------------------------------------------------------------ */

export type Journal = {
  slug: string
  title: string
  field: Field
  impactFactor: number
  citeScore: number
  articles: number
  editors: number
  photo: PhotoKey
  description: string
  openAccess: true
  acceptanceRate: number
  medianDaysToDecision: number
}

export const FIELDS = [
  'Health & Medicine',
  'Neuroscience',
  'Climate & Earth',
  'Engineering & AI',
  'Marine Biology',
  'Physics & Space',
] as const
export type Field = (typeof FIELDS)[number]

export const JOURNALS: Journal[] = [
  {
    slug: 'immunology',
    title: 'Meridian Immunology',
    field: 'Health & Medicine',
    impactFactor: 7.3,
    citeScore: 9.1,
    articles: 12480,
    editors: 1420,
    photo: 'testTubes',
    description:
      'Mechanisms of immune regulation, vaccine science and translational immunotherapy.',
    openAccess: true,
    acceptanceRate: 31,
    medianDaysToDecision: 54,
  },
  {
    slug: 'neuroscience',
    title: 'Meridian Neuroscience',
    field: 'Neuroscience',
    impactFactor: 6.1,
    citeScore: 8.2,
    articles: 9840,
    editors: 1105,
    photo: 'neuro',
    description:
      'From synaptic biophysics to cognition, ageing and computational models of the brain.',
    openAccess: true,
    acceptanceRate: 28,
    medianDaysToDecision: 61,
  },
  {
    slug: 'climate',
    title: 'Meridian Climate Systems',
    field: 'Climate & Earth',
    impactFactor: 5.8,
    citeScore: 7.4,
    articles: 6210,
    editors: 780,
    photo: 'climate',
    description:
      'Earth-system modelling, carbon cycles, adaptation policy and planetary boundaries.',
    openAccess: true,
    acceptanceRate: 34,
    medianDaysToDecision: 47,
  },
  {
    slug: 'ai-engineering',
    title: 'Meridian AI & Engineering',
    field: 'Engineering & AI',
    impactFactor: 6.9,
    citeScore: 10.3,
    articles: 8130,
    editors: 960,
    photo: 'circuit',
    description:
      'Machine learning systems, robotics, materials engineering and applied computation.',
    openAccess: true,
    acceptanceRate: 25,
    medianDaysToDecision: 39,
  },
  {
    slug: 'marine-science',
    title: 'Meridian Marine Science',
    field: 'Marine Biology',
    impactFactor: 4.6,
    citeScore: 6.5,
    articles: 4390,
    editors: 610,
    photo: 'coral',
    description:
      'Ocean ecosystems, reef resilience, fisheries and deep-sea biodiversity.',
    openAccess: true,
    acceptanceRate: 38,
    medianDaysToDecision: 52,
  },
  {
    slug: 'astrophysics',
    title: 'Meridian Physics & Space',
    field: 'Physics & Space',
    impactFactor: 5.2,
    citeScore: 7.9,
    articles: 5570,
    editors: 705,
    photo: 'nebula',
    description:
      'Observational astronomy, orbital debris, gravitation and high-energy physics.',
    openAccess: true,
    acceptanceRate: 30,
    medianDaysToDecision: 44,
  },
]

export type Article = {
  slug: string
  title: string
  authors: string[]
  journal: string
  journalSlug: string
  type: 'Original Research' | 'Review' | 'Methods' | 'Perspective'
  date: string
  doi: string
  views: number
  citations: number
  photo: PhotoKey
  abstract: string
  keywords: string[]
}

export const ARTICLES: Article[] = [
  {
    slug: 'reef-thermal-refugia',
    title: 'Thermal refugia buffer coral reef collapse under repeated bleaching events',
    authors: ['A. Okonkwo', 'L. Marchetti', 'S. Iyer'],
    journal: 'Meridian Marine Science',
    journalSlug: 'marine-science',
    type: 'Original Research',
    date: '2026-06-28',
    doi: '10.48219/mrdn.2026.00412',
    views: 41230,
    citations: 87,
    photo: 'coral',
    abstract:
      'Repeated marine heatwaves are reshaping reef assemblages faster than recovery windows allow. Across 214 monitored sites we identify hydrodynamic thermal refugia that reduce bleaching severity by 38% and act as recolonisation sources for adjacent degraded reef.',
    keywords: ['coral bleaching', 'marine heatwave', 'refugia', 'reef resilience'],
  },
  {
    slug: 'microglia-cognitive-decline',
    title: 'Microglial pruning signatures predict cognitive decline a decade before onset',
    authors: ['J. Almeida', 'R. Novak'],
    journal: 'Meridian Neuroscience',
    journalSlug: 'neuroscience',
    type: 'Original Research',
    date: '2026-06-21',
    doi: '10.48219/mrdn.2026.00398',
    views: 63410,
    citations: 142,
    photo: 'neuro',
    abstract:
      'Longitudinal imaging of 1,842 participants links early microglial synaptic pruning patterns to measurable cognitive decline nine to eleven years later, offering a candidate window for pre-symptomatic intervention.',
    keywords: ['microglia', 'ageing', 'cognition', 'biomarkers'],
  },
  {
    slug: 'orbital-debris-cascade',
    title: 'Modelling cascade risk in low-Earth orbit after mega-constellation deployment',
    authors: ['H. Nakamura', 'P. Weiss', 'D. Osei'],
    journal: 'Meridian Physics & Space',
    journalSlug: 'astrophysics',
    type: 'Methods',
    date: '2026-06-14',
    doi: '10.48219/mrdn.2026.00381',
    views: 28900,
    citations: 54,
    photo: 'satellite',
    abstract:
      'We present an open Monte-Carlo framework for Kessler-cascade probability under current launch cadence, and show that debris-removal of just 12 high-risk objects per year halves collision probability by 2040.',
    keywords: ['orbital debris', 'Kessler syndrome', 'simulation'],
  },
  {
    slug: 'enzymatic-plastic-degradation',
    title: 'Engineered enzymes depolymerise mixed post-consumer plastics at ambient temperature',
    authors: ['M. Haddad', 'C. Lindqvist'],
    journal: 'Meridian AI & Engineering',
    journalSlug: 'ai-engineering',
    type: 'Original Research',
    date: '2026-06-09',
    doi: '10.48219/mrdn.2026.00370',
    views: 51780,
    citations: 96,
    photo: 'greenTech',
    abstract:
      'A directed-evolution pipeline guided by a structure-prediction model yields PETase variants retaining 91% activity at 25°C, making enzymatic recycling of unsorted waste streams energetically viable.',
    keywords: ['biocatalysis', 'recycling', 'protein engineering'],
  },
  {
    slug: 'permafrost-carbon-flux',
    title: 'Permafrost carbon flux is underestimated by current Earth-system models',
    authors: ['E. Sorensen', 'T. Bakker', 'V. Rao'],
    journal: 'Meridian Climate Systems',
    journalSlug: 'climate',
    type: 'Review',
    date: '2026-05-30',
    doi: '10.48219/mrdn.2026.00355',
    views: 37650,
    citations: 118,
    photo: 'climate',
    abstract:
      'Synthesising 63 field campaigns, we find abrupt thaw processes contribute 1.6× more carbon than gradual-thaw parameterisations assume, with implications for remaining carbon budgets.',
    keywords: ['permafrost', 'carbon cycle', 'Earth-system models'],
  },
  {
    slug: 'mrna-adjuvant-platform',
    title: 'A self-adjuvanting mRNA platform broadens protection against drifted variants',
    authors: ['N. Petrova', 'K. Adeyemi'],
    journal: 'Meridian Immunology',
    journalSlug: 'immunology',
    type: 'Original Research',
    date: '2026-05-22',
    doi: '10.48219/mrdn.2026.00341',
    views: 72340,
    citations: 203,
    photo: 'pipette',
    abstract:
      'Co-encoding a TLR-agonist peptide within the mRNA construct elicits durable cross-reactive T-cell responses in preclinical models, without the reactogenicity associated with exogenous adjuvants.',
    keywords: ['mRNA', 'vaccines', 'adjuvant', 'immunology'],
  },
]

export type NewsItem = {
  slug: string
  title: string
  category: string
  date: string
  photo: PhotoKey
  excerpt: string
}

export const NEWS: NewsItem[] = [
  {
    slug: 'open-peer-review-milestone',
    title: 'One million peer reviews now published openly alongside their articles',
    category: 'Publishing',
    date: '2026-07-02',
    photo: 'library',
    excerpt:
      'Every report, every revision, named or anonymous by reviewer choice — our open review archive passes a milestone.',
  },
  {
    slug: 'ai-triage-editors',
    title: 'How AI triage cut median time-to-first-decision by 19 days',
    category: 'Editorial',
    date: '2026-06-25',
    photo: 'dataScreen',
    excerpt:
      'Scope-matching and reviewer discovery are now assisted, never automated. Editors keep the final call.',
  },
  {
    slug: 'reef-restoration-funding',
    title: 'Reef restoration research receives record open-access funding',
    category: 'Research',
    date: '2026-06-18',
    photo: 'coral',
    excerpt:
      'A consortium of funders commits to covering article processing charges for coastal-resilience work.',
  },
  {
    slug: 'fair-data-service',
    title: 'FAIR data deposits become one click from the submission form',
    category: 'Product',
    date: '2026-06-11',
    photo: 'microscope',
    excerpt:
      'Datasets, code and protocols get their own DOI, versioned and citable, at the moment you submit.',
  },
  {
    slug: 'young-minds-launch',
    title: 'Young Minds: research reviewed by the scientists of 2040',
    category: 'Outreach',
    date: '2026-06-04',
    photo: 'writing',
    excerpt:
      'School-age reviewers work with authors to rewrite findings for readers aged 8 to 15.',
  },
  {
    slug: 'orbital-policy-lab',
    title: 'Policy Lab convenes on orbital debris governance',
    category: 'Policy',
    date: '2026-05-28',
    photo: 'satellite',
    excerpt:
      'Researchers and regulators meet to translate cascade-risk modelling into launch licensing rules.',
  },
]

/* ---------------------- Journal management system ---------------------- */

export type SubmissionStatus =
  | 'Draft'
  | 'Submitted'
  | 'Under Review'
  | 'Revisions Requested'
  | 'Accepted'
  | 'Rejected'

export type Submission = {
  id: string
  title: string
  journal: string
  status: SubmissionStatus
  submitted: string
  updated: string
  stage: number // 0-4 across the pipeline
  reviewers: Reviewer[]
  correspondingAuthor: string
  type: Article['type']
}

export type Reviewer = {
  name: string
  affiliation: string
  avatar: string
  status: 'Invited' | 'Accepted' | 'Report submitted' | 'Declined'
  recommendation?: 'Accept' | 'Minor revision' | 'Major revision' | 'Reject'
  due: string
}

export const PIPELINE_STAGES = [
  'Submitted',
  'Editor check',
  'Peer review',
  'Decision',
  'Production',
] as const

export const SUBMISSIONS: Submission[] = [
  {
    id: 'MRDN-2026-0417',
    title: 'Thermal refugia buffer coral reef collapse under repeated bleaching events',
    journal: 'Meridian Marine Science',
    status: 'Under Review',
    submitted: '2026-05-14',
    updated: '2026-07-08',
    stage: 2,
    correspondingAuthor: 'A. Okonkwo',
    type: 'Original Research',
    reviewers: [
      {
        name: 'Dr Sofia Ramírez',
        affiliation: 'Institute of Marine Ecology',
        avatar: 'https://randomuser.me/api/portraits/women/44.jpg',
        status: 'Report submitted',
        recommendation: 'Minor revision',
        due: '2026-07-01',
      },
      {
        name: 'Prof. Daniel Osei',
        affiliation: 'Coastal Systems Lab',
        avatar: 'https://randomuser.me/api/portraits/men/32.jpg',
        status: 'Accepted',
        due: '2026-07-19',
      },
      {
        name: 'Dr Mei Tanaka',
        affiliation: 'Reef Futures Centre',
        avatar: 'https://randomuser.me/api/portraits/women/68.jpg',
        status: 'Invited',
        due: '2026-07-22',
      },
    ],
  },
  {
    id: 'MRDN-2026-0392',
    title: 'Graph neural surrogates for coupled ocean–atmosphere simulation',
    journal: 'Meridian AI & Engineering',
    status: 'Revisions Requested',
    submitted: '2026-04-02',
    updated: '2026-07-05',
    stage: 3,
    correspondingAuthor: 'A. Okonkwo',
    type: 'Methods',
    reviewers: [
      {
        name: 'Dr Henrik Lindqvist',
        affiliation: 'Numerical Methods Group',
        avatar: 'https://randomuser.me/api/portraits/men/76.jpg',
        status: 'Report submitted',
        recommendation: 'Major revision',
        due: '2026-06-20',
      },
      {
        name: 'Dr Priya Raman',
        affiliation: 'Climate Compute Institute',
        avatar: 'https://randomuser.me/api/portraits/women/21.jpg',
        status: 'Report submitted',
        recommendation: 'Minor revision',
        due: '2026-06-24',
      },
    ],
  },
  {
    id: 'MRDN-2026-0356',
    title: 'Sediment cores reveal a 4,000-year record of monsoon variability',
    journal: 'Meridian Climate Systems',
    status: 'Accepted',
    submitted: '2026-02-11',
    updated: '2026-06-30',
    stage: 4,
    correspondingAuthor: 'A. Okonkwo',
    type: 'Original Research',
    reviewers: [
      {
        name: 'Prof. Elena Sorensen',
        affiliation: 'Palaeoclimate Unit',
        avatar: 'https://randomuser.me/api/portraits/women/12.jpg',
        status: 'Report submitted',
        recommendation: 'Accept',
        due: '2026-05-30',
      },
      {
        name: 'Dr Tomas Bakker',
        affiliation: 'Sediment Dynamics Lab',
        avatar: 'https://randomuser.me/api/portraits/men/18.jpg',
        status: 'Report submitted',
        recommendation: 'Accept',
        due: '2026-06-02',
      },
    ],
  },
  {
    id: 'MRDN-2026-0448',
    title: 'Deep-sea sponge microbiomes as a source of novel antibiotics',
    journal: 'Meridian Marine Science',
    status: 'Draft',
    submitted: '—',
    updated: '2026-07-11',
    stage: 0,
    correspondingAuthor: 'A. Okonkwo',
    type: 'Original Research',
    reviewers: [],
  },
]

export const STATS = [
  { label: 'Researchers on the platform', value: 3.9, suffix: 'M', decimals: 1 },
  { label: 'Citations to date', value: 16, suffix: 'M', decimals: 0 },
  { label: 'Article views & downloads', value: 5.3, suffix: 'B', decimals: 1 },
]

/** Decision-time trend for the editorial dashboard chart (days to first decision). */
export const DECISION_TIME = [
  { month: 'Jan', days: 78 },
  { month: 'Feb', days: 74 },
  { month: 'Mar', days: 69 },
  { month: 'Apr', days: 63 },
  { month: 'May', days: 58 },
  { month: 'Jun', days: 54 },
  { month: 'Jul', days: 51 },
]

export const RESEARCH_TOPICS = [
  {
    title: 'Planetary health and zoonotic spillover',
    articles: 214,
    editors: 18,
    photo: 'microscope' as PhotoKey,
    deadline: '2026-09-30',
  },
  {
    title: 'Foundation models for scientific discovery',
    articles: 187,
    editors: 22,
    photo: 'dataScreen' as PhotoKey,
    deadline: '2026-10-15',
  },
  {
    title: 'Carbon removal: evidence and governance',
    articles: 96,
    editors: 14,
    photo: 'greenTech' as PhotoKey,
    deadline: '2026-11-01',
  },
]

export const PHOTO_KEYS = PHOTO
