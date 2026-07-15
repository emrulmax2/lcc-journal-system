<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\HomeSection;
use App\Models\HomeSectionItem;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\SiteSetting;
use Illuminate\Database\Seeder;

/**
 * The site's default content.
 *
 * This runs in PRODUCTION — it is the real chrome, not a demo fixture. Every string here
 * replaces something that was previously hardcoded in a React component, and several of
 * them replace something that was hardcoded AND false.
 *
 * What is deliberately absent, and must stay absent until it is true:
 *
 *   - Any impact factor. The navbar rendered six of them (7.3, 6.1, 5.8…) from a fixture
 *     file. Impact Factors are issued by Clarivate; we have not been issued one.
 *   - "Search 2.9M articles". We have single digits.
 *   - "Median 51 days to first decision", and the four invented medians in the how-it-works
 *     steps. Real medians are computed per journal and shown on the Journals page, where
 *     they are attributable.
 *   - "Reviews open by default" / "reviewers' reports published in full alongside this
 *     article". Review is SINGLE-BLIND — the reviewer's identity is withheld from the
 *     author by design, and no report is published anywhere.
 *   - "A fictional publisher built as a UI prototype", which was in the live footer.
 */
class CmsSeeder extends Seeder
{
    public function run(): void
    {
        $this->settings();
        $pages = $this->pages();
        $this->menus($pages);
        $this->homeSections();
    }

    private function settings(): void
    {
        $settings = [
            // --- General -------------------------------------------------------
            ['site_name', 'general', 'text', 'Site name', 'JCDMS', 'Appears in the navbar, footer and every page title.'],
            ['site_tagline', 'general', 'text', 'Tagline', 'Contemporary Development & Management Studies', null],
            ['logo', 'general', 'media', 'Logo', null, 'Leave empty to use the built-in wordmark.'],

            // --- Hero ----------------------------------------------------------
            ['hero_badge', 'hero', 'text', 'Hero badge', 'Open access · Peer reviewed · Permanently citable', 'Small pill above the headline. Every claim here must be true on launch day.'],
            ['hero_heading', 'hero', 'text', 'Hero heading', 'Where research informs decisions', null],
            ['hero_blurb', 'hero', 'textarea', 'Hero blurb', 'Open-access publishing and an end-to-end journal management system — submission, peer review, decision and production, in one place.', null],
            ['hero_search_placeholder', 'hero', 'text', 'Search placeholder', 'Search articles, journals, topics', null],
            ['hero_image', 'hero', 'media', 'Hero image', null, 'Replaces the stock photo. Needs alt text.'],

            // --- Footer --------------------------------------------------------
            ['footer_blurb', 'footer', 'textarea', 'Footer blurb', 'Open-access publishing and end-to-end journal management. Every article is free to read on the day it is published, and carries a permanent DOI.', 'Do NOT claim open peer review here — review is single-blind.'],
            ['footer_copyright', 'footer', 'text', 'Copyright holder', 'London Churchill College', 'The year is added automatically — do not type one, or it will be wrong next January.'],
            ['newsletter_heading', 'footer', 'text', 'Newsletter heading', 'Research that reaches further', null],
            ['newsletter_blurb', 'footer', 'textarea', 'Newsletter blurb', 'A monthly digest of new research, editorial calls and Research Topic deadlines. Confirm by email; unsubscribe in one click.', null],
            ['newsletter_enabled', 'footer', 'boolean', 'Newsletter enabled', '1', 'Turn off to hide the signup form entirely.'],

            // --- Contact -------------------------------------------------------
            ['contact_email', 'contact', 'email', 'Contact email', 'journal@lcc.ac.uk', null],
            ['contact_address', 'contact', 'textarea', 'Postal address', "London Churchill College\nLondon, United Kingdom", null],

            // --- Social --------------------------------------------------------
            // Empty by default. An icon linking to "#" is worse than no icon: it looks
            // like a live account and goes nowhere. The footer hides any that are blank.
            ['social_x', 'social', 'url', 'X (Twitter) URL', null, 'Leave empty to hide the icon.'],
            ['social_linkedin', 'social', 'url', 'LinkedIn URL', null, 'Leave empty to hide the icon.'],
            ['social_youtube', 'social', 'url', 'YouTube URL', null, 'Leave empty to hide the icon.'],
        ];

        foreach ($settings as $i => [$key, $group, $type, $label, $value, $help]) {
            SiteSetting::updateOrCreate(
                ['key' => $key],
                [
                    'group' => $group,
                    'type' => $type,
                    'label' => $label,
                    'help' => $help,
                    'sequence' => $i,
                    // Do not overwrite an editor's change on a re-seed.
                    'value' => SiteSetting::where('key', $key)->value('value') ?? $value,
                ],
            );
        }
    }

    /** @return array<string, Page> */
    private function pages(): array
    {
        // Every one of these was a link in the navbar or footer that went to "#" or to the
        // homepage. is_system = the navigation structurally depends on it and it cannot be
        // deleted, only unpublished.
        $pages = [
            ['author-guidelines', 'Author guidelines', 'How to prepare and submit a manuscript.', $this->authorGuidelines()],
            ['publication-ethics', 'Publication ethics', 'Our policy on ethics, misconduct and corrections.', $this->publicationEthics()],
            ['article-processing-charges', 'Article processing charges', 'What it costs to publish with us, and who pays.', $this->apcPolicy()],
            ['peer-review-policy', 'Peer review policy', 'How manuscripts are reviewed, and by whom.', $this->peerReviewPolicy()],
            ['editorial-policies', 'Editorial policies', 'Authorship, data availability, competing interests.', "## Authorship\n\nAll listed authors must have made a substantial contribution to the work and must approve the submitted version.\n\n## Data availability\n\nAuthors are asked to state where the data supporting their findings can be found.\n\n## Competing interests\n\nAll authors must declare any financial or non-financial competing interests at submission.\n\n*This page is a placeholder. Replace it with London Churchill College's approved editorial policy.*"],
            ['privacy-policy', 'Privacy policy', 'How we handle your personal data.', "*This page is a placeholder and is not yet a lawful privacy notice.*\n\nBefore launch it must state, at minimum: what personal data is collected (names, email addresses, affiliations, ORCID, manuscript files, newsletter subscriptions, IP-derived view counts), the lawful basis for each, how long it is kept, who it is shared with (Crossref receives author names, affiliations and ORCIDs on publication), and how to exercise your rights.\n\nContact: journal@lcc.ac.uk"],
            ['terms-and-conditions', 'Terms and conditions', 'Terms of use for this site.', "*This page is a placeholder. Replace it with London Churchill College's approved terms.*"],
            ['accessibility-statement', 'Accessibility statement', 'Our commitment to an accessible site.', $this->accessibilityStatement()],
            ['contact', 'Contact', 'How to reach the editorial office.', "The editorial office can be reached at **journal@lcc.ac.uk**.\n\nFor questions about a manuscript already under review, please quote your manuscript reference."],
        ];

        $created = [];

        foreach ($pages as [$slug, $title, $summary, $body]) {
            $created[$slug] = Page::updateOrCreate(
                ['slug' => $slug],
                [
                    'title' => $title,
                    'summary' => $summary,
                    'status' => 'published',
                    'published_at' => now(),
                    'is_system' => true,
                    // Do not clobber an editor's rewrite on a re-seed.
                    'body' => Page::where('slug', $slug)->value('body') ?? $body,
                ],
            );
        }

        return $created;
    }

    /** @param  array<string, Page>  $pages */
    private function menus(array $pages): void
    {
        $menus = [
            ['main', 'Main navigation', 'navbar', 0],
            ['authors-mega', 'For authors (mega-menu)', 'navbar-mega', 1],
            ['footer-guidelines', 'Guidelines', 'footer', 0],
            ['footer-explore', 'Explore', 'footer', 1],
            ['footer-about', 'About', 'footer', 2],
            ['legal', 'Legal', 'legal', 3],
        ];

        foreach ($menus as [$key, $name, $location, $sequence]) {
            Menu::updateOrCreate(['key' => $key], ['name' => $name, 'location' => $location, 'sequence' => $sequence]);
        }

        // Every item points at a REAL destination: a named route, or a page that exists.
        // MenuItem::saving() refuses anything else — no "#" is possible.
        $items = [
            ['main', 'Journals', null, 'journals.index', null],
            ['main', 'Articles', null, 'articles.index', null],
            ['main', 'Research Topics', null, 'topics.index', null],
            ['main', 'News', null, 'news.index', null],

            ['authors-mega', 'Author guidelines', 'Formatting, ethics and data policy', null, 'author-guidelines'],
            ['authors-mega', 'Article processing charges', 'What it costs, and who pays', null, 'article-processing-charges'],
            ['authors-mega', 'Peer review policy', 'How your manuscript is assessed', null, 'peer-review-policy'],
            ['authors-mega', 'Submit your research', 'A guided submission in five steps', 'submit', null],

            ['footer-guidelines', 'Author guidelines', null, null, 'author-guidelines'],
            ['footer-guidelines', 'Publication ethics', null, null, 'publication-ethics'],
            ['footer-guidelines', 'Peer review policy', null, null, 'peer-review-policy'],
            ['footer-guidelines', 'Article processing charges', null, null, 'article-processing-charges'],
            ['footer-guidelines', 'Editorial policies', null, null, 'editorial-policies'],

            ['footer-explore', 'All journals', null, 'journals.index', null],
            ['footer-explore', 'All articles', null, 'articles.index', null],
            ['footer-explore', 'Research Topics', null, 'topics.index', null],
            ['footer-explore', 'News', null, 'news.index', null],

            ['footer-about', 'Contact', null, null, 'contact'],
            ['footer-about', 'Submit your research', null, 'submit', null],

            ['legal', 'Privacy policy', null, null, 'privacy-policy'],
            ['legal', 'Terms and conditions', null, null, 'terms-and-conditions'],
            ['legal', 'Accessibility statement', null, null, 'accessibility-statement'],
        ];

        $sequences = [];

        foreach ($items as [$menuKey, $label, $description, $routeName, $pageSlug]) {
            $menu = Menu::where('key', $menuKey)->firstOrFail();
            $sequences[$menuKey] = ($sequences[$menuKey] ?? -1) + 1;

            MenuItem::updateOrCreate(
                ['menu_id' => $menu->id, 'label' => $label],
                [
                    'description' => $description,
                    'route_name' => $routeName,
                    'page_id' => $pageSlug !== null ? $pages[$pageSlug]->id : null,
                    'external_url' => null,
                    'sequence' => $sequences[$menuKey],
                    'is_active' => true,
                ],
            );
        }
    }

    private function homeSections(): void
    {
        $sections = [
            ['author-paths', 'Author paths', 'For researchers', 'Everything from idea to indexed', 'Four ways in, depending on where you are.', 0],
            ['featured-journals', 'Featured journals', 'Journals', 'One editorial standard', 'Every journal is fully open access and editor-led. Every article gets a permanent DOI and a landing page that will keep resolving.', 1],
            ['how-it-works', 'How publishing works', 'How it works', 'From submission to indexed, in the open', 'The same pipeline the editorial office runs on — visible to authors at every stage.', 2],
            ['research-topics', 'Research Topics', 'Research Topics', 'Open calls for papers', 'Curated collections led by topic editors.', 3],
            ['impact', 'Impact band', 'Impact', 'Research is only useful once it is read', 'Every article is free to read on the day it is published — no paywall, no embargo, no exceptions. Each one carries a permanent DOI and a landing page we undertake to keep resolving.', 4],
            ['latest-research', 'Latest research', 'Latest research', 'Recently published', null, 5],
            ['newsroom', 'Newsroom', 'Newsroom', 'Science, in the open', null, 6],
        ];

        foreach ($sections as [$key, $name, $eyebrow, $heading, $blurb, $sequence]) {
            HomeSection::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'eyebrow' => $eyebrow,
                    'heading' => $heading,
                    'blurb' => $blurb,
                    'sequence' => $sequence,
                    'is_visible' => true,
                ],
            );
        }

        // The four author-path cards. Previously a hardcoded PATHS array; one of them
        // advertised an "Open data hub" that does not exist anywhere in the application.
        $this->items('author-paths', [
            ['FileSearch', 'Find a journal', 'Match your manuscript to an open-access journal, and see its scope, acceptance rate and time to decision before you submit.', null, 'Browse journals', 'journals.index'],
            ['Compass', 'Find a Research Topic', 'Open calls for papers, led by topic editors, with published deadlines.', null, 'Explore topics', 'topics.index'],
            ['Send', 'Submit your research', 'A guided submission in five steps. Your progress is saved as you go.', null, 'Start a submission', 'submit'],
            ['BookOpen', 'Read the archive', 'Every article is free to read on the day it is published. Search titles, abstracts, keywords and authors.', null, 'Search articles', 'articles.index'],
        ]);

        // The pipeline. NOTE THE ABSENT TIMINGS — the prototype rendered "About 20 minutes",
        // "Median 4 days", "Median 38 days", "Median 9 days" as fact. None was computed from
        // anything, and they contradicted the "Median 51 days" in the navbar. `meta` is
        // editable, so a real, owned figure can be put back once one is actually measured.
        $this->items('how-it-works', [
            ['Send', 'Submit', 'Format-free first submission. Upload the manuscript as you have it.', null, null, null],
            ['ClipboardCheck', 'Editor check', 'A handling editor checks scope, ethics and integrity before review begins.', null, null, null],
            ['Users', 'Peer review', 'Independent reviewers assess the work. Review is single-blind: their identity is withheld from you.', null, null, null],
            ['Gavel', 'Decision', 'The editor decides, and tells you why, with the reviewers\' comments in full.', null, null, null],
            ['Rocket', 'Production', 'Typesetting, a permanent DOI, and a landing page indexed by Google Scholar and Crossref.', null, null, null],
        ]);
    }

    /** @param  list<array{0:?string,1:string,2:?string,3:?string,4:?string,5:?string}>  $items */
    private function items(string $sectionKey, array $items): void
    {
        $section = HomeSection::where('key', $sectionKey)->firstOrFail();

        foreach ($items as $i => [$icon, $title, $body, $meta, $ctaLabel, $routeName]) {
            HomeSectionItem::updateOrCreate(
                ['home_section_id' => $section->id, 'title' => $title],
                [
                    'icon' => $icon,
                    'body' => $body,
                    'meta' => $meta,
                    'cta_label' => $ctaLabel,
                    'route_name' => $routeName,
                    'sequence' => $i,
                ],
            );
        }
    }

    // --- Default page bodies -------------------------------------------------

    private function authorGuidelines(): string
    {
        return <<<'MD'
        ## Before you submit

        Read the aims and scope of the journal you are submitting to. If the manuscript is a poor fit, an editor will tell you early rather than send it to review.

        ## Format-free first submission

        We do not require a specific template for a first submission. Send the manuscript as you have it — a single PDF, DOCX or LaTeX archive up to 50 MB. If the paper is accepted, we will ask for source files at that point.

        ## What you will need

        - The title, abstract and up to eight keywords
        - Every author's full name, email and affiliation
        - An ORCID for each author where they have one. **We will never guess or auto-fill an ORCID** — a wrong ORCID attributes the work to a different, real researcher.
        - A statement on ethics, competing interests and data availability

        ## Authorship

        All listed authors must have made a substantial contribution and must approve the submitted version. The corresponding author is responsible for confirming this.

        ## After submission

        You will receive a manuscript reference by email. You can follow the manuscript's progress at any time from the editorial office.

        *This page is editable in the admin. Replace this text with London Churchill College's approved guidance.*
        MD;
    }

    private function publicationEthics(): string
    {
        return <<<'MD'
        ## Our position

        We follow the principles of the Committee on Publication Ethics (COPE).

        ## Misconduct

        Allegations of plagiarism, data fabrication, image manipulation or undisclosed competing interests are investigated. Where an allegation is upheld, the article is corrected or retracted.

        ## Corrections and retractions

        **A published article is never deleted.** Its landing page and its DOI must keep resolving, because other people have already cited it in work they cannot now change. A correction is published alongside it; a retraction replaces the article with a retraction notice at the same permanent address.

        ## Competing interests

        All authors must declare financial and non-financial competing interests at submission. Reviewers must decline where they have one.

        *This page is editable in the admin. Replace this text with London Churchill College's approved policy.*
        MD;
    }

    private function apcPolicy(): string
    {
        return <<<'MD'
        ## What it costs

        **No article processing charge is currently levied.**

        This journal is published by London Churchill College and is free to read and free to publish in.

        If that changes, the charge, the waiver policy and who is eligible will be stated on this page **before** it applies to any manuscript already under consideration.

        ## What you get

        - Open access on the day of publication — no paywall, no embargo
        - A permanent DOI and a landing page that will keep resolving
        - Deposit to Crossref, and metadata harvestable by DOAJ, BASE and CORE

        *This page is editable in the admin. It must be reviewed and approved before launch — this is the page an author reads before deciding whether to submit.*
        MD;
    }

    private function peerReviewPolicy(): string
    {
        return <<<'MD'
        ## Single-blind review

        Manuscripts are reviewed **single-blind**: the reviewers know who the authors are, and **the reviewers' identities are withheld from the authors**.

        Reviewer reports are shared with the author in full, with the reviewer's name removed. Reviewers may also send confidential comments to the editor, which the author does not see.

        We do **not** publish reviewer reports alongside the article, and we do not name reviewers.

        ## How a manuscript is handled

        1. A handling editor checks scope, ethics and integrity.
        2. Independent reviewers are invited.
        3. Reports are returned to the editor.
        4. The editor decides, and gives the author the reasons and the reports.

        ## Reviewers

        Reviewers must decline where they have a competing interest. Reviewer identities are not disclosed to authors at any stage, including after publication.

        *This page is editable in the admin.*
        MD;
    }

    private function accessibilityStatement(): string
    {
        return <<<'MD'
        ## Our commitment

        This site aims to meet **WCAG 2.1 level AA**.

        What that means in practice here:

        - All text meets a 4.5:1 contrast ratio, including placeholder text and footer legal text
        - Every interactive element is reachable by keyboard and shows a visible focus ring
        - Charts are accompanied by an equivalent data table
        - Motion is disabled for anyone whose operating system asks for reduced motion
        - Images carry alt text; decorative images are explicitly marked as such

        ## Known gaps

        *List them here. An accessibility statement that claims full compliance and lists no gaps is not credible, and under the public-sector accessibility regulations it is not sufficient either.*

        ## Telling us about a problem

        If you find something on this site you cannot use, email **journal@lcc.ac.uk** and tell us what you were trying to do. We will respond.

        *This page is editable in the admin and must be reviewed before launch.*
        MD;
    }
}
