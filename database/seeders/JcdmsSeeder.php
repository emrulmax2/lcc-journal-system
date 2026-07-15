<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ArticleStatus;
use App\Enums\IssueStatus;
use App\Enums\PublicationModel;
use App\Models\Article;
use App\Models\Field;
use App\Models\Issue;
use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\User;
use App\Models\Volume;
use App\Services\Doi\DoiSuffixGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

/**
 * REAL DATA. This is genuine, unpublished JCD&MS content — Volume 10, Issue 2, Spring
 * 2026, pages 8-106. Titles, abstracts, author names, affiliations, ORCIDs and page
 * ranges are copied exactly from the editorial prototype.
 *
 * Rules for this file:
 *   - Never paraphrase a title or abstract. They are the record.
 *   - Never run faker over these records.
 *   - NEVER fabricate an ORCID. Six authors have one; the rest are NULL and must stay
 *     NULL. A wrong ORCID in a Crossref deposit attributes someone else's work to a
 *     real, identifiable person, and it propagates to every downstream index.
 *
 * Deliberate NULLs (these are NOT missing data):
 *   - doi_prefix   — Crossref has not issued one. The prototype showed "10.xxxx";
 *                    we store NULL so that depositing is impossible rather than wrong.
 *   - issn_online  — the British Library has not assigned one. Prototype: "0000-0000".
 */
class JcdmsSeeder extends Seeder
{
    public function run(): void
    {
        $field = Field::firstOrCreate(
            ['slug' => 'development-management'],
            ['name' => 'Development & Management', 'sequence' => 1],
        );

        $journal = Journal::updateOrCreate(
            ['slug' => 'jcdms'],
            [
                'title' => 'Journal of Contemporary Development & Management Studies',
                'abbreviation' => 'JCD&MS',
                'field_id' => $field->id,
                'description' => 'Peer-reviewed research on contemporary development, management, '
                    .'education and health systems, published by London Churchill College.',
                'publisher' => 'London Churchill College',
                'principal_editor' => 'Professor Tommie Anderson-Jaquest, PhD',
                'contact_email' => 'journal@lcc.ac.uk',
                'publication_model' => PublicationModel::IssueBased,
                'open_access' => true,

                // Not yet issued. See the class docblock — these NULLs are the design.
                'issn_online' => null,
                'issn_print' => null,
                'doi_prefix' => null,

                // Produces jcdms.v10i2.001 once a prefix exists.
                'doi_suffix_pattern' => '{journal}.v{volume}i{issue}.{seq}',
                'doi_sequence_padding' => 3,

                'license' => null,                       // prototype: "none"
                'license_holder' => 'The Authors',
                'crossref_deposit_references' => true,
                'is_active' => true,
            ],
        );

        $sections = [];
        foreach (['Editorial', 'Research Article', 'Research Report', 'Research Protocol'] as $i => $name) {
            $sections[$name] = JournalSection::updateOrCreate(
                ['journal_id' => $journal->id, 'name' => $name],
                ['sequence' => $i, 'is_active' => true, 'doi_eligible' => true],
            );
        }

        $volume = Volume::updateOrCreate(
            ['journal_id' => $journal->id, 'number' => 10],
            ['year' => 2026],
        );

        // Issue 1 is published but has no article records in the source system — its
        // content predates this platform. It exists so that Issue 2's numbering and the
        // volume's archive are correct. Do not "fix" the empty article list.
        Issue::updateOrCreate(
            ['volume_id' => $volume->id, 'number' => 1],
            [
                'season' => 'Autumn 2025',
                'publication_date' => '2025-11-15',
                'status' => IssueStatus::Published,
            ],
        );

        $issue2 = Issue::updateOrCreate(
            ['volume_id' => $volume->id, 'number' => 2],
            [
                'season' => 'Spring 2026',
                'publication_date' => null,
                'status' => IssueStatus::Draft,
            ],
        );

        $this->seedUsers($journal);

        foreach ($this->articles() as $row) {
            $this->seedArticle($journal, $issue2, $sections[$row['section']], $row);
        }
    }

    private function seedArticle(Journal $journal, Issue $issue, JournalSection $section, array $row): void
    {
        $article = Article::updateOrCreate(
            ['journal_id' => $journal->id, 'slug' => $row['slug']],
            [
                'issue_id' => $issue->id,
                'journal_section_id' => $section->id,
                'sequence' => $row['sequence'],
                'title' => $row['title'],
                'abstract' => $row['abstract'],
                'keywords' => $row['keywords'],
                'corporate_author' => $row['corporate_author'] ?? null,
                'first_page' => $row['first_page'],
                'last_page' => $row['last_page'],
                'status' => ArticleStatus::Draft,
                'published_at' => null,
            ],
        );

        // The suffix is generated now, from the one generator, so the editorial team can
        // see the DOI an article WILL get before committing to it. It is not frozen
        // until publish. Note doi() still returns NULL, because the prefix is NULL —
        // a suffix alone is not an identifier.
        $article->doi_suffix = App::make(DoiSuffixGenerator::class)->generate(
            $article->setRelation('journal', $journal)->setRelation('issue', $issue->setRelation('volume', $issue->volume))
        );
        $article->save();

        $article->authors()->delete();

        foreach ($row['authors'] as $i => $author) {
            $article->authors()->create([
                'given_name' => $author['given'],
                'family_name' => $author['family'],
                'affiliation' => $author['affiliation'] ?? 'London Churchill College, UK',
                'orcid' => $author['orcid'] ?? null,   // NULL where none exists. Never invent one.
                'is_corresponding' => $author['corresponding'] ?? false,
                'sequence' => $i + 1,                  // author order is meaningful
            ]);
        }
    }

    /** The editorial team, with their real per-journal roles. */
    private function seedUsers(Journal $journal): void
    {
        $registrar = App::make(PermissionRegistrar::class);

        $people = [
            ['Tommie', 'Anderson-Jaquest', 't.anderson-jaquest@lcc.ac.uk', ['journal-editor'], true],
            ['Nick', 'Papé', 'Nick.Pape@lcc.ac.uk', ['section-editor', 'author'], true],
            ['Ilias', 'Mahmud', 'ilias.mahmud@lcc.ac.uk', ['section-editor', 'author'], true],
            ['Teddy', 'Wyrley-Birch', 't.wyrley-birch@lcc.ac.uk', ['production', 'author'], true],
            ['Abdur', 'Rahim', 'a.rahim@lcc.ac.uk', ['author'], true],
            ['Mohammad Shahadat', 'Hossain', 'm.hossain@lcc.ac.uk', ['author'], false],
        ];

        foreach ($people as [$given, $family, $email, $roles, $active]) {
            $user = User::updateOrCreate(
                ['email' => $email],
                [
                    'name' => trim("{$given} {$family}"),
                    'given_name' => $given,
                    'family_name' => $family,
                    'affiliation' => 'London Churchill College, UK',
                    'is_active' => $active,
                    'password' => Hash::make('password'),   // dev only; SSO replaces this
                ],
            );

            // Roles are granted IN THE CONTEXT OF THIS JOURNAL. Without setting the team
            // id first, Spatie would attach the role globally and the cross-journal
            // isolation tests would (correctly) fail.
            $registrar->setPermissionsTeamId($journal->id);
            $user->syncRoles($roles);
        }

        $registrar->setPermissionsTeamId(null);
    }

    /** @return array<int, array<string, mixed>> */
    private function articles(): array
    {
        return [
            [
                'sequence' => 1,
                'section' => 'Editorial',
                'slug' => 'organising-research-teaching-intensive-higher-education-clir',
                'first_page' => 8,
                'last_page' => 12,
                'title' => 'Organising Research in Teaching Intensive Higher Education: The Establishment of the Centre for Learning Innovation and Research (CLIR)',
                // A corporate author with ZERO personal authors. This is the edge case that
                // breaks naive citation code and naive Crossref XML.
                'corporate_author' => 'Members of the Centre for Learning Innovation and Research (CLIR), London Churchill College',
                'authors' => [],
                'keywords' => ['Research organisation', 'Teaching intensive higher education', 'Research governance', 'Independent higher education', 'Academic practice', 'Widening participation'],
                'abstract' => 'This editorial examines the organisation of research within teaching intensive higher education through the establishment of the Centre for Learning Innovation and Research at London Churchill College. The creation of a research centre introduces organisational consolidation through central recording of research outputs, formal ethical governance and collaborative publication planning — converting dispersed scholarly activity into recognised institutional research practice while preserving the primary educational mission of a teaching focused provider.',
            ],
            [
                'sequence' => 2,
                'section' => 'Research Article',
                'slug' => 'historians-modern-british-empire-post-colonial-theory',
                'first_page' => 13,
                'last_page' => 24,
                'title' => 'How Have Historians of the Modern British Empire Responded to the Insights Offered by Post-Colonial Theory?',
                'authors' => [
                    ['given' => 'Teddy', 'family' => 'Wyrley-Birch', 'orcid' => null, 'corresponding' => true],
                ],
                'keywords' => ['Post-colonial theory', 'Imperialism', 'Orientalism', 'Subalternity', 'Colonialism', 'Decolonisation'],
                'abstract' => 'This article examines how the historiography of the modern British Empire has responded to the ideas and interventions of post-colonial theory. Focusing on the influential work of Edward Said, Gayatri Chakravorty Spivak, and Frantz Fanon, it demonstrates how post-colonial perspectives encouraged historians to reassess the structures, legacies, and lived experiences of empires.',
            ],
            [
                'sequence' => 3,
                'section' => 'Research Report',
                'slug' => 'pluralistic-health-systems-disability-bangladesh',
                'first_page' => 25,
                'last_page' => 41,
                'title' => 'Navigating Pluralistic Health Systems: Healthcare-Seeking Behaviour Among People with Disabilities in Bangladesh',
                'authors' => [
                    ['given' => 'Ilias', 'family' => 'Mahmud', 'orcid' => '0000-0003-2870-7715', 'corresponding' => true],
                ],
                'keywords' => ['Healthcare-seeking behaviour', 'Disability', 'Medical pluralism', 'Rehabilitation', 'Bangladesh'],
                'abstract' => 'This study examines how people with disabilities in Bangladesh navigate formal and informal healthcare sectors before reaching specialised rehabilitation services, using a sequential exploratory mixed-methods design with a survey of 100 participants. Care pathways were dynamic and pluralistic: 82% used both formal and informal sectors. Disability-inclusive health systems require earlier referral, decentralised rehabilitation and culturally competent communication with patients and families.',
            ],
            [
                'sequence' => 4,
                'section' => 'Research Article',
                'slug' => 'cyber-security-awareness-training-deepfake-students',
                'first_page' => 42,
                'last_page' => 58,
                'title' => 'Investigating the Impact of Cyber Security Awareness Training in the Field of Deepfake on Students',
                'authors' => [
                    ['given' => 'Mohammad Shahadat', 'family' => 'Hossain', 'orcid' => null, 'corresponding' => true],
                ],
                'keywords' => ['Deepfake', 'Cyber security', 'Training'],
                'abstract' => "This research investigates how training about cybersecurity affects students' capacity to identify deepfake content. Using a pre-post assessment approach with 21 Level 7 graduate students evaluated with paired sample t-tests, the results demonstrated significant improvement across several domains, establishing that purposeful cybersecurity education can provide the protective abilities individuals need to face emerging security threats.",
            ],
            [
                'sequence' => 5,
                'section' => 'Research Protocol',
                'slug' => 'transition-teaching-educational-leadership-motivations-barriers',
                'first_page' => 59,
                'last_page' => 79,
                'title' => 'Research Protocol: Navigating the Transition from Teaching to Educational Leadership in Higher Education – Motivations and Barriers',
                // Five authors, order meaningful. Russell Kabir is the one external author;
                // getting his affiliation wrong would misattribute the work to LCC.
                'authors' => [
                    ['given' => 'Nick', 'family' => 'Papé', 'orcid' => '0000-0003-1395-3751', 'corresponding' => true],
                    ['given' => 'Rahaman', 'family' => 'Hasan', 'orcid' => '0000-0003-1690-2458'],
                    ['given' => 'Gerry', 'family' => 'Takamura', 'orcid' => '0000-0002-3107-1874'],
                    ['given' => 'Russell', 'family' => 'Kabir', 'orcid' => '0000-0002-4510-0385', 'affiliation' => 'Anglia Ruskin University, UK'],
                    ['given' => 'Ilias', 'family' => 'Mahmud', 'orcid' => '0000-0003-2870-7715'],
                ],
                'keywords' => ['Educational leadership', 'Higher education', 'Career transition', 'Professional identity', 'Gender and leadership', 'Barriers to progression'],
                'abstract' => 'This research protocol outlines a mixed-methods study exploring the transition from teaching roles to management positions within higher education in England, using a sequential explanatory design. Particular attention is paid to the experiences of women and individuals from under-represented backgrounds, whose leadership pathways remain uneven and often undocumented.',
            ],
            [
                'sequence' => 6,
                'section' => 'Research Article',
                'slug' => 'situated-agency-collective-meaning-vygotskian-praxis',
                'first_page' => 80,
                'last_page' => 91,
                'title' => 'Situated Agency and Collective Meaning: Rethinking Development through Vygotskian Praxis',
                'authors' => [
                    ['given' => 'Nick', 'family' => 'Papé', 'orcid' => '0000-0003-1395-3751', 'corresponding' => true],
                ],
                'keywords' => ['Collaborative praxis', 'Vygotskian theory', 'Cultural-historical psychology', 'Mature learners', 'Digital pedagogy', 'Institutional power'],
                'abstract' => 'This article reconsiders Vygotskian psychology through the lens of collaborative praxis, arguing that learning is not only a cognitive process but also a social, ethical and political act. It positions education as a site of collective meaning-making and calls for renewed attentiveness to memory, care and conflict as generative forces in educational practice.',
            ],
            [
                'sequence' => 7,
                'section' => 'Research Article',
                'slug' => 'behind-the-grade-silent-curriculum-identity-emotion',
                'first_page' => 92,
                'last_page' => 106,
                'title' => 'Behind the Grade. What Students Learn That Is Never Recorded: The Silent Curriculum — Identity, Emotion and the Reasons Students Stay',
                'authors' => [
                    ['given' => 'Nick', 'family' => 'Papé', 'orcid' => '0000-0003-1395-3751', 'corresponding' => true],
                    ['given' => 'Abdur', 'family' => 'Rahim', 'orcid' => '0009-0003-5500-1369'],
                ],
                'keywords' => ['Mature students', 'Affective learning', 'Student voice', 'Learner identity', 'Educational persistence', 'Invisible learning'],
                'abstract' => 'This article explores forms of student learning in Higher Education that are often unseen, unmeasured and unrecorded. Focusing on mature students, it argues that personal growth, emotional persistence and identity shifts play a central role in educational experience yet remain largely invisible in curriculum design and institutional review.',
            ],
        ];
    }
}
