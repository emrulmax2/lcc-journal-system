<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\PublicationModel;
use App\Models\Article;
use App\Models\Field;
use App\Models\Journal;
use App\Models\JournalMetric;
use App\Models\JournalSection;
use App\Models\Media;
use App\Models\NewsItem;
use App\Models\ResearchTopic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * FICTIONAL DATA — the demo showcase, re-themed to BUSINESS, MANAGEMENT & DEVELOPMENT.
 *
 * The site is JCD&MS — the Journal of Contemporary Development & Management Studies. The
 * demo content used to be six invented life-sciences journals (coral reefs, mRNA vaccines,
 * orbital debris) illustrated with photographs of test tubes and nebulae, which made the
 * whole platform read as a laboratory-science publisher. It is not one. This seeder now
 * populates the multi-journal UI with business, management, development, economics, public
 * policy, education and health-systems content, on-theme for the real journal.
 *
 * Everything here is still invented — the journals, authors, DOIs, impact factors, news
 * and calls for papers. DO NOT RUN IT IN PRODUCTION. DatabaseSeeder gates it behind
 * `! app()->environment('production')`; that guard is all that stands between this file and
 * fabricated research being published under London Churchill College's name.
 *
 * Deliberate, not accidental:
 *   - publication_model = CONTINUOUS: no volumes, no issues, issue_id = NULL. Exercises the
 *     nullable-issue path that JCD&MS, being issue-based, never touches.
 *   - doi_prefix = '10.48219', demo journals ONLY. JCD&MS keeps NULL (Crossref has issued
 *     it no prefix). Never copy this onto a real journal.
 *   - ORCIDs are NULL for every demo author and must stay NULL. An invented ORCID collides
 *     with a real, identifiable person's record.
 *
 * IMAGERY is self-hosted, from the media library — the generated business photographs in
 * storage/app/public/media/demo. Each is wired as a real Media row (cover_media_id /
 * hero_media_id / media_id), which is the resolution order the whole site now follows:
 * a real asset we own, before any stock fallback. If the files are absent the records
 * simply carry no image and render the neutral placeholder — never a wrong photo.
 *
 * Re-runnable: updateOrCreate throughout, keyed on the natural slug.
 */
class DemoSeeder extends Seeder
{
    private const DEMO_DOI_PREFIX = '10.48219';

    private const DEMO_DOI_PREFIX_SLASH = self::DEMO_DOI_PREFIX.'/';

    /** Lowercased by DoiSuffixGenerator to build the {journal} token → "mrdn". */
    private const DEMO_ABBREVIATION = 'MRDN';

    /** Every demo journal offers the same four article types. */
    private const SECTIONS = ['Original Research', 'Review', 'Case Study', 'Perspective'];

    public function run(): void
    {
        $fields = $this->seedFields();
        $journals = $this->seedJournals($fields);
        $sections = $this->seedSections($journals);

        foreach ($this->articles() as $row) {
            $this->seedArticle($journals[$row['journal_slug']], $sections, $row);
        }

        $this->seedNews();
        $this->seedResearchTopics();
        $this->wireSiteImagery();
    }

    /**
     * Point the homepage's site-level imagery at the generated business photographs.
     *
     * Local-only, like the rest of this seeder. In production an editor sets the hero and
     * section images through the admin; here we just make the demo look right out of the
     * box, so the site reads as a business/management publisher rather than falling back to
     * a stock science photo.
     */
    private function wireSiteImagery(): void
    {
        if ($hero = $this->media('hero', 'A modern city skyline of glass towers at dusk, glowing windows and teal light reflections')) {
            // Via the model, so SiteContent's cache is flushed — a hero that only updated
            // on the next deploy is exactly the staleness the CMS exists to avoid.
            \App\Models\SiteSetting::where('key', 'hero_image')->first()?->update(['value' => (string) $hero]);
        }

        if ($impact = $this->media('publishing', 'Reading an open-access journal')) {
            \App\Models\HomeSection::where('key', 'impact')->update(['media_id' => $impact]);
        }

        if ($paths = $this->media('education', 'A university lecture theatre')) {
            \App\Models\HomeSection::where('key', 'author-paths')->update(['media_id' => $paths]);
        }

        // Give the REAL journal, JCD&MS, a cover for the local demo. It is development &
        // management, so the management photograph fits. In production an editor uploads
        // its own cover; this is a local convenience only.
        if ($cover = $this->media('management', 'JCD&MS — cover image')) {
            Journal::where('slug', 'jcdms')->update(['cover_media_id' => $cover]);
        }
    }

    // --- Media --------------------------------------------------------------

    /**
     * A Media row for one of the generated business photographs, or NULL if the file was
     * never downloaded. NULL is safe: the content simply renders no image rather than a
     * substitute one.
     */
    private function media(string $key, string $alt): ?int
    {
        $path = "media/demo/{$key}.jpg";

        if (! Storage::disk('public')->exists($path)) {
            return null;
        }

        return Media::updateOrCreate(
            ['path' => $path],
            [
                'disk' => 'public',
                'original_name' => "{$key}.jpg",
                'mime_type' => 'image/jpeg',
                'size_bytes' => Storage::disk('public')->size($path),
                'width' => 1264,
                'height' => 848,
                'alt' => $alt,
                // These are illustrative editorial photographs, not evidence from any
                // study. The credit says so, so nothing on a card can be mistaken for a
                // figure from the research.
                'credit' => 'Illustration',
            ],
        )->id;
    }

    // --- Fields -------------------------------------------------------------

    /** @return array<string, Field> keyed by field name */
    private function seedFields(): array
    {
        $fields = [];

        foreach ([
            'Business & Management',
            'Development Studies',
            'Economics & Finance',
            'Public Policy & Governance',
            'Education & Leadership',
            'Health Systems & Management',
        ] as $i => $name) {
            $fields[$name] = Field::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'sequence' => $i + 1],
            );
        }

        return $fields;
    }

    // --- Journals -----------------------------------------------------------

    /**
     * @param  array<string, Field>  $fields
     * @return array<string, Journal> keyed by slug
     */
    private function seedJournals(array $fields): array
    {
        $journals = [];

        foreach ($this->journalRows() as $row) {
            $journal = Journal::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'abbreviation' => self::DEMO_ABBREVIATION,
                    'field_id' => $fields[$row['field']]->id,
                    'description' => $row['description'],
                    'aims_and_scope' => $row['aims'],
                    'open_access' => true,
                    'publication_model' => PublicationModel::Continuous,
                    'doi_prefix' => self::DEMO_DOI_PREFIX,
                    'doi_suffix_pattern' => '{journal}.{year}.{seq}',
                    'doi_sequence_padding' => 5,
                    'issn_online' => null,
                    'principal_editor' => $row['editor'],
                    'contact_email' => 'journal@lcc.ac.uk',
                    'cover_media_id' => $this->media($row['image'], $row['title'].' — cover image'),
                    'is_active' => true,
                ],
            );

            JournalMetric::updateOrCreate(
                ['journal_id' => $journal->id],
                [
                    'impact_factor' => $row['impact_factor'],
                    'cite_score' => $row['cite_score'],
                    'external_updated_at' => now(),
                    'acceptance_rate' => $row['acceptance_rate'],
                    'median_days_to_decision' => $row['median_days_to_decision'],
                    'article_count' => $row['articles'],
                    'editor_count' => $row['editors'],
                    'computed_at' => now(),
                ],
            );

            $journals[$journal->slug] = $journal;
        }

        return $journals;
    }

    /**
     * @param  array<string, Journal>  $journals
     * @return array<string, array<string, JournalSection>> [journal slug][section name]
     */
    private function seedSections(array $journals): array
    {
        $sections = [];

        foreach ($journals as $slug => $journal) {
            foreach (self::SECTIONS as $i => $name) {
                $sections[$slug][$name] = JournalSection::updateOrCreate(
                    ['journal_id' => $journal->id, 'name' => $name],
                    ['sequence' => $i, 'is_active' => true, 'doi_eligible' => true],
                );
            }
        }

        return $sections;
    }

    // --- Articles -----------------------------------------------------------

    /** @param  array<string, array<string, JournalSection>>  $sections */
    private function seedArticle(Journal $journal, array $sections, array $row): void
    {
        $suffix = Str::after($row['doi'], self::DEMO_DOI_PREFIX_SLASH);
        $sequence = (int) Str::afterLast($suffix, '.');

        $article = Article::updateOrCreate(
            ['journal_id' => $journal->id, 'slug' => $row['slug']],
            [
                'issue_id' => null,   // continuous publication has no issues
                'journal_section_id' => $sections[$journal->slug][$row['type']]->id,
                'sequence' => $sequence,
                'doi_suffix' => $suffix,
                'title' => $row['title'],
                'abstract' => $row['abstract'],
                'keywords' => $row['keywords'],
                'first_page' => null,
                'last_page' => null,
                'status' => ArticleStatus::Published,
                'published_at' => $row['date'],
                'views_count' => $row['views'],
                'citations_count' => $row['citations'],
                // The article card / header illustration. Its Media credit is "Illustration",
                // so the detail page shows it as illustrative, never as a figure from the work.
                'hero_media_id' => $this->media($row['image'], $row['title']),
            ],
        );

        $article->authors()->delete();

        foreach ($row['authors'] as $i => $name) {
            [$given, $family] = $this->splitName($name);

            $article->authors()->create([
                'given_name' => $given,
                'family_name' => $family,
                'orcid' => null,   // never invented
                'is_corresponding' => $i === 0,
                'sequence' => $i + 1,
            ]);
        }
    }

    /**
     * @return array{0: string, 1: string} [given, family]
     */
    private function splitName(string $name): array
    {
        $name = trim($name);

        if (! str_contains($name, ' ')) {
            return ['', $name];
        }

        return [Str::beforeLast($name, ' '), Str::afterLast($name, ' ')];
    }

    // --- News and calls for papers ------------------------------------------

    private function seedNews(): void
    {
        foreach ($this->newsRows() as $row) {
            NewsItem::updateOrCreate(
                ['slug' => $row['slug']],
                [
                    'title' => $row['title'],
                    'category' => $row['category'],
                    'excerpt' => $row['excerpt'],
                    'body' => $row['body'],
                    'media_id' => $this->media($row['image'], $row['title']),
                    'published_at' => $row['date'],
                ],
            );
        }
    }

    private function seedResearchTopics(): void
    {
        foreach ($this->researchTopicRows() as $row) {
            ResearchTopic::updateOrCreate(
                ['slug' => Str::slug($row['title'])],
                [
                    'title' => $row['title'],
                    'description' => $row['description'],
                    'body' => $row['body'],
                    'submission_email' => 'journal@lcc.ac.uk',
                    'media_id' => $this->media($row['image'], $row['title']),
                    'deadline' => $row['deadline'],
                    'is_open' => true,
                ],
            );
        }
    }

    // --- The demo content ----------------------------------------------------

    /** @return array<int, array<string, mixed>> */
    private function journalRows(): array
    {
        return [
            [
                'slug' => 'business-management',
                'title' => 'Meridian Business & Management',
                'field' => 'Business & Management',
                'image' => 'management',
                'editor' => 'Professor Helena Whitfield',
                'description' => 'Strategy, organisation, leadership and operations in contemporary enterprise.',
                'aims' => "Meridian Business & Management publishes rigorous, applied research on how organisations are led, structured and run. Scope includes strategy, organisational behaviour, operations, marketing, entrepreneurship and the future of work.\n\nWe welcome quantitative, qualitative and mixed-methods studies, and case studies with clear managerial implications.",
                'impact_factor' => 3.4,
                'cite_score' => 5.1,
                'articles' => 1840,
                'editors' => 96,
                'acceptance_rate' => 27,
                'median_days_to_decision' => 48,
            ],
            [
                'slug' => 'development-studies',
                'title' => 'Meridian Development Studies',
                'field' => 'Development Studies',
                'image' => 'development',
                'editor' => 'Professor Samuel Rahman',
                'description' => 'Economic development, poverty reduction, sustainability and the Global South.',
                'aims' => "Meridian Development Studies is an interdisciplinary journal on the theory and practice of development. It covers poverty and inequality, rural and urban livelihoods, institutions, aid and finance, gender, and the environmental dimensions of development.\n\nWork grounded in fieldwork from low- and middle-income countries is particularly welcome.",
                'impact_factor' => 2.9,
                'cite_score' => 4.4,
                'articles' => 1220,
                'editors' => 74,
                'acceptance_rate' => 31,
                'median_days_to_decision' => 55,
            ],
            [
                'slug' => 'economics-finance',
                'title' => 'Meridian Economics & Finance',
                'field' => 'Economics & Finance',
                'image' => 'finance',
                'editor' => 'Professor Marco Delgado',
                'description' => 'Applied economics, financial markets, banking, investment and public finance.',
                'aims' => "Meridian Economics & Finance publishes applied and policy-relevant research across microeconomics, macroeconomics, financial economics and public finance. We value credible identification, replicable methods and clear relevance to markets or policy.",
                'impact_factor' => 3.8,
                'cite_score' => 6.0,
                'articles' => 2010,
                'editors' => 118,
                'acceptance_rate' => 22,
                'median_days_to_decision' => 41,
            ],
            [
                'slug' => 'public-policy',
                'title' => 'Meridian Public Policy & Governance',
                'field' => 'Public Policy & Governance',
                'image' => 'policy',
                'editor' => 'Professor Priya Adeyemi',
                'description' => 'Public administration, policy evaluation, institutional reform and governance.',
                'aims' => "Meridian Public Policy & Governance publishes research on how public decisions are made, implemented and evaluated. Scope includes policy analysis, public administration, regulation, local government, and the governance of public services.",
                'impact_factor' => 2.6,
                'cite_score' => 4.0,
                'articles' => 960,
                'editors' => 63,
                'acceptance_rate' => 30,
                'median_days_to_decision' => 52,
            ],
            [
                'slug' => 'education-leadership',
                'title' => 'Meridian Education & Leadership',
                'field' => 'Education & Leadership',
                'image' => 'education',
                'editor' => 'Professor Elena Sørensen',
                'description' => 'Educational leadership, pedagogy, professional learning and higher education.',
                'aims' => "Meridian Education & Leadership publishes research on leadership, management and pedagogy across schools, colleges and higher education. It welcomes work on professional learning, widening participation, and the leadership of teaching-intensive institutions.",
                'impact_factor' => 2.3,
                'cite_score' => 3.7,
                'articles' => 1130,
                'editors' => 71,
                'acceptance_rate' => 33,
                'median_days_to_decision' => 50,
            ],
            [
                'slug' => 'health-systems',
                'title' => 'Meridian Health Systems',
                'field' => 'Health Systems & Management',
                'image' => 'health',
                'editor' => 'Professor Ilias Mahmud',
                'description' => 'Health policy, healthcare management, service delivery and the health workforce.',
                'aims' => "Meridian Health Systems publishes research on the organisation, financing and management of health services — not clinical or laboratory science. Scope includes health policy, service delivery, workforce, quality improvement and health economics.",
                'impact_factor' => 3.1,
                'cite_score' => 4.9,
                'articles' => 1040,
                'editors' => 68,
                'acceptance_rate' => 29,
                'median_days_to_decision' => 46,
            ],
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function articles(): array
    {
        return [
            [
                'slug' => 'dynamic-capabilities-supply-chain-disruption',
                'journal_slug' => 'business-management',
                'type' => 'Original Research',
                'image' => 'management',
                'title' => 'Dynamic capabilities and firm resilience during supply-chain disruption',
                'authors' => ['Rachel Mensah', 'Liam Whitfield', 'Sofia Iyer'],
                'date' => '2026-06-28',
                'doi' => '10.48219/mrdn.2026.00412',
                'views' => 18240,
                'citations' => 34,
                'keywords' => ['dynamic capabilities', 'resilience', 'supply chain', 'strategy'],
                'abstract' => 'Drawing on a panel of 214 manufacturing firms through three consecutive disruption shocks, we show that firms with stronger sensing and reconfiguration capabilities recovered operating margins 38% faster than peers. The advantage was concentrated in firms that had decentralised operational decision rights before the shock, suggesting resilience is built in calm periods, not during the crisis.',
            ],
            [
                'slug' => 'microfinance-womens-enterprise-rural-bangladesh',
                'journal_slug' => 'development-studies',
                'type' => 'Original Research',
                'image' => 'development',
                'title' => "Microfinance, women's enterprise and household resilience in rural Bangladesh",
                'authors' => ['Ayesha Karim', 'Joana Fernandes', 'Tariq Owusu'],
                'date' => '2026-06-21',
                'doi' => '10.48219/mrdn.2026.00398',
                'views' => 22610,
                'citations' => 41,
                'keywords' => ['microfinance', 'women entrepreneurs', 'poverty', 'Bangladesh'],
                'abstract' => 'Using a mixed-methods design with 1,180 households, we find that access to group microfinance raised women-owned enterprise formation but that household resilience improved only where loans were paired with business training. Credit alone shifted who held debt without changing who could absorb a shock.',
            ],
            [
                'slug' => 'interest-rate-shocks-small-business-lending',
                'journal_slug' => 'economics-finance',
                'type' => 'Original Research',
                'image' => 'finance',
                'title' => 'Interest-rate shocks and small-business lending: evidence from a natural experiment',
                'authors' => ['Marco Delgado', 'Nadia Petrova'],
                'date' => '2026-06-14',
                'doi' => '10.48219/mrdn.2026.00381',
                'views' => 15980,
                'citations' => 28,
                'keywords' => ['monetary policy', 'SME finance', 'credit', 'banking'],
                'abstract' => 'Exploiting a staggered change in reserve requirements as a natural experiment, we estimate the pass-through of policy-rate shocks to small-business credit. A one-percentage-point rise cut new SME lending by 6.2% within two quarters, with the effect twice as large for firms without a prior banking relationship.',
            ],
            [
                'slug' => 'participatory-budgeting-trust-local-government',
                'journal_slug' => 'public-policy',
                'type' => 'Case Study',
                'image' => 'policy',
                'title' => 'Does participatory budgeting improve trust in local government? A field study',
                'authors' => ['Priya Adeyemi', 'Daniel Costa', 'Hana Novak'],
                'date' => '2026-06-09',
                'doi' => '10.48219/mrdn.2026.00370',
                'views' => 13120,
                'citations' => 22,
                'keywords' => ['participatory budgeting', 'public trust', 'local government', 'governance'],
                'abstract' => 'Across twelve municipalities that adopted participatory budgeting and eleven matched comparators, self-reported trust in the council rose by 9 points among residents who took part, but was unchanged among those who did not. Participation, not the policy itself, carried the effect — with implications for how such schemes are scaled.',
            ],
            [
                'slug' => 'distributed-leadership-teacher-retention-further-education',
                'journal_slug' => 'education-leadership',
                'type' => 'Original Research',
                'image' => 'education',
                'title' => 'Distributed leadership and teacher retention in further education colleges',
                'authors' => ['Elena Sørensen', 'Vikram Rao'],
                'date' => '2026-05-30',
                'doi' => '10.48219/mrdn.2026.00355',
                'views' => 16740,
                'citations' => 31,
                'keywords' => ['distributed leadership', 'teacher retention', 'further education', 'professional development'],
                'abstract' => 'Following 63 further-education colleges over four years, we find that distributing curriculum and hiring decisions to teaching teams was associated with a 4.8-point reduction in annual teacher turnover, mediated almost entirely by teachers\' sense of professional autonomy rather than by pay.',
            ],
            [
                'slug' => 'task-shifting-waiting-times-primary-care',
                'journal_slug' => 'health-systems',
                'type' => 'Original Research',
                'image' => 'health',
                'title' => 'Task-shifting and waiting times in primary care: a mixed-methods evaluation',
                'authors' => ['Ilias Mahmud', 'Kwame Boateng'],
                'date' => '2026-05-22',
                'doi' => '10.48219/mrdn.2026.00341',
                'views' => 19430,
                'citations' => 37,
                'keywords' => ['task-shifting', 'primary care', 'waiting times', 'health workforce'],
                'abstract' => 'Evaluating a task-shifting programme that moved routine chronic-disease reviews from doctors to trained nurses across 40 primary-care clinics, we find median waiting times fell by 31% with no measurable loss of care quality, provided nurses had structured referral protocols and protected supervision time.',
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function newsRows(): array
    {
        return [
            [
                'slug' => 'read-and-publish-agreement',
                'title' => 'New read-and-publish agreement widens open-access options for authors',
                'category' => 'Publishing',
                'date' => '2026-07-02',
                'image' => 'publishing',
                'excerpt' => 'Authors at partner institutions can now publish open access with the article processing charge covered centrally.',
                'body' => "A new read-and-publish agreement means that corresponding authors at partner institutions can publish open access across the Meridian portfolio with the article processing charge covered by their library, rather than paid from a grant or personally.\n\nThe agreement covers all research article types and takes effect for submissions received from the start of the next quarter.",
            ],
            [
                'slug' => 'time-to-decision-improves',
                'title' => 'Median time to first decision falls across the management portfolio',
                'category' => 'Editorial',
                'date' => '2026-06-25',
                'image' => 'management',
                'excerpt' => 'Editorial triage and a larger reviewer pool have shortened the time from submission to first decision.',
                'body' => "Investment in editorial triage and a broader reviewer pool has reduced the median time from submission to first decision across the business and management journals. The figures are computed from our own records and shown, per journal, on each journal's page — we do not publish an aggregate headline number, because averaging across very different journals would flatter the slower ones.",
            ],
            [
                'slug' => 'apc-waivers-global-south',
                'title' => 'Funders commit to APC waivers for Global South corresponding authors',
                'category' => 'Funding',
                'date' => '2026-06-18',
                'image' => 'development',
                'excerpt' => 'A consortium of funders will cover article processing charges for corresponding authors based in eligible countries.',
                'body' => "A consortium of development funders has committed to covering article processing charges for corresponding authors based in low- and lower-middle-income countries, removing a barrier that has kept some of the most relevant development research behind a fee.\n\nEligibility follows the standard country classification and is checked at acceptance.",
            ],
            [
                'slug' => 'research-integrity-checks',
                'title' => 'Strengthened research-integrity checks now run at submission',
                'category' => 'Policy',
                'date' => '2026-06-11',
                'image' => 'policy',
                'excerpt' => 'Every submission now passes automated similarity and data-consistency checks before it reaches an editor.',
                'body' => "Every new submission now passes automated similarity and basic data-consistency checks before it reaches a handling editor. The checks assist editors; they never make a decision. A flag prompts a human to look, and the author is told what was found.",
            ],
            [
                'slug' => 'early-career-editorial-board',
                'title' => 'Early-career researchers join the editorial boards',
                'category' => 'People',
                'date' => '2026-06-04',
                'image' => 'education',
                'excerpt' => 'A cohort of early-career researchers joins the boards, with structured mentoring from senior editors.',
                'body' => "A cohort of early-career researchers has joined the editorial boards across the portfolio, each paired with a senior editor for structured mentoring. The aim is both to broaden the range of expertise handling submissions and to build editorial capacity for the next decade.",
            ],
            [
                'slug' => 'data-availability-mandatory',
                'title' => 'Data-availability statements become mandatory for empirical articles',
                'category' => 'Policy',
                'date' => '2026-05-28',
                'image' => 'finance',
                'excerpt' => 'Empirical articles must now state where the data and analysis code supporting the findings can be found.',
                'body' => "From this month, every empirical article must carry a data-availability statement saying where the data and analysis code supporting its findings can be found, or explaining why they cannot be shared. The statement is part of the published record, not a checkbox.",
            ],
        ];
    }

    /** @return array<int, array<string, string>> */
    private function researchTopicRows(): array
    {
        return [
            [
                'title' => 'Sustainable business and the net-zero transition',
                'image' => 'management',
                'deadline' => '2026-09-30',
                'description' => 'How firms are reshaping strategy, operations and reporting for a net-zero economy.',
                'body' => "This collection invites research on how businesses are adapting strategy, operations, finance and reporting to the net-zero transition. We welcome studies of decarbonisation strategy, sustainable operations and supply chains, green finance, and the governance and disclosure of climate risk.\n\nEmpirical work with clear managerial or policy implications is particularly welcome.",
            ],
            [
                'title' => 'Financial inclusion and inclusive growth',
                'image' => 'finance',
                'deadline' => '2026-10-15',
                'description' => 'Access to finance, digital payments and their effect on livelihoods and growth.',
                'body' => "This call seeks research on financial inclusion — access to credit, savings, insurance and digital payments — and its relationship to livelihoods, enterprise and inclusive growth. We are interested in rigorous evaluations of interventions, and in the unintended consequences of expanding access.",
            ],
            [
                'title' => 'Leadership and workforce in public services',
                'image' => 'policy',
                'deadline' => '2026-11-01',
                'description' => 'Recruiting, retaining and leading the workforce that delivers public services.',
                'body' => "Public services — health, education, local government — depend on a workforce that is increasingly hard to recruit and retain. This collection invites research on leadership, workforce planning, retention and wellbeing across the public sector, and on what actually works to keep skilled people in public service.",
            ],
        ];
    }
}
