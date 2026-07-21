<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AccountController;
use App\Http\Controllers\Admin\ArticleController as AdminArticleController;
use App\Http\Controllers\Admin\Content\FieldController;
use App\Http\Controllers\Admin\Content\HomeSectionController;
use App\Http\Controllers\Admin\Content\MediaController;
use App\Http\Controllers\Admin\Content\MenuController;
use App\Http\Controllers\Admin\Content\NewsController as ContentNewsController;
use App\Http\Controllers\Admin\Content\NewsletterController as ContentNewsletterController;
use App\Http\Controllers\Admin\Content\PageController as ContentPageController;
use App\Http\Controllers\Admin\Content\PreviewController;
use App\Http\Controllers\Admin\Content\SiteSettingController;
use App\Http\Controllers\Admin\Content\TopicController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\IssueController;
use App\Http\Controllers\Admin\PublishController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SettingsController;
use App\Http\Controllers\Admin\SubmissionController as AdminSubmissionController;
use App\Http\Controllers\Admin\SubmissionDiscussionController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VolumeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DecisionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\ArticleController;
use App\Http\Controllers\Public\CitationController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\JournalController;
use App\Http\Controllers\Public\JournalShowController;
use App\Http\Controllers\Public\NewsController;
use App\Http\Controllers\Public\NewsletterController;
use App\Http\Controllers\Public\PageController;
use App\Http\Controllers\Public\ResearchTopicController;
use App\Http\Controllers\Public\SitemapController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SubmissionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public reading surface
|--------------------------------------------------------------------------
| No auth. Cacheable. MUST be crawlable — these are DOI landing pages, and
| Google Scholar, Crossref, DOAJ and OAI-PMH harvesters do not execute
| JavaScript. Everything here is server-rendered.
|
| citation_pdf_url and citation_abstract_html_url must match these routes
| EXACTLY. A mismatch between the advertised PDF URL and the real one is the
| most common reason Scholar silently refuses to index a journal — and it
| looks perfectly fine to a human reviewer.
*/

Route::get('/', HomeController::class)->name('home');

Route::get('/journals', [JournalController::class, 'index'])->name('journals.index');

// The journal landing page. It did not exist — every journal link went to a filtered
// article list, so aims_and_scope, ISSN, principal_editor and contact_email were columns
// read by nothing. DOAJ will not accept a journal with no public aims-and-scope page,
// so this is a prerequisite for the application, not a nicety.
// {journal:slug}, explicitly. The admin binds the same model by id (see Journal.php) —
// declaring the key here keeps the two from fighting over one model-wide setting.
Route::get('/journals/{journal:slug}', JournalShowController::class)->name('journals.show');

Route::get('/articles', [ArticleController::class, 'index'])->name('articles.index');

// Declared BEFORE the {article} route. Laravel matches in declaration order, and
// ".pdf" would otherwise be read as part of the slug and served as HTML.
Route::get('/articles/{article}.pdf', [ArticleController::class, 'pdf'])->name('articles.pdf');

// The crawlable HTML full text. Declared BEFORE {article} for the same reason as .pdf —
// ".html" would otherwise be read as part of the slug. Server-rendered by Blade so Scholar
// can read the full body with no JavaScript. citation_fulltext_html_url points here.
Route::get('/articles/{article}.html', [ArticleController::class, 'html'])->name('articles.html');

Route::get('/articles/{article}/cite/{format}', CitationController::class)
    ->whereIn('format', ['harvard', 'bibtex', 'ris'])
    ->name('articles.cite');

Route::get('/articles/{article}', [ArticleController::class, 'show'])->name('articles.show');

// News. `news_items.slug` and `.body` have existed since the first migration, and six
// news cards rendered on the homepage with a "Read the story" affordance — every one of
// them href="#". The body column was written for and read by nothing.
Route::get('/news', [NewsController::class, 'index'])->name('news.index');
Route::get('/news/{news}', [NewsController::class, 'show'])->name('news.show');

// Research Topics — open calls for papers. These cards all linked to /journals.
Route::get('/topics', [ResearchTopicController::class, 'index'])->name('topics.index');
Route::get('/topics/{topic}', [ResearchTopicController::class, 'show'])->name('topics.show');

// The newsletter that previously discarded your address and told you to check your inbox.
Route::post('/newsletter', [NewsletterController::class, 'store'])
    ->middleware('throttle:5,1')   // a public unauthenticated form that sends mail
    ->name('newsletter.store');
Route::get('/newsletter/confirm/{token}', [NewsletterController::class, 'confirm'])->name('newsletter.confirm');
Route::get('/newsletter/unsubscribe/{token}', [NewsletterController::class, 'unsubscribe'])->name('newsletter.unsubscribe');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Language switch. Stores the choice in the session (and on the account, if signed in) and
// returns to the current page. GET so the switcher can be a plain link.
Route::get('/locale/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
| Laravel's built-in session guard. Deliberately not Breeze or Jetstream —
| the app needs a login form, not a scaffold with its own layout, profile
| pages and design opinions to be undone.
|
| The route is named `login` because that is the name the `auth` middleware
| redirects to when a guest reaches for the editorial office.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.store');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/*
|--------------------------------------------------------------------------
| Submit a manuscript — PUBLIC, no account required
|--------------------------------------------------------------------------
| An open-access journal wants submissions, and making authors create an
| account first is a barrier for no gain. The corresponding author is
| identified by the email entered in the wizard, not by a login. A draft is
| resumed server-side only for signed-in users; a guest's progress lives in
| the wizard's own state for the session.
*/

Route::get('/submit', [SubmissionController::class, 'create'])->name('submit');
Route::post('/submit', [SubmissionController::class, 'store'])
    ->middleware('throttle:20,60')   // a public form that stores files — rate-limit it
    ->name('submit.store');
Route::post('/submit/draft', [SubmissionController::class, 'storeDraft'])->name('submit.draft');

/*
|--------------------------------------------------------------------------
| Editorial office — submission & peer review
|--------------------------------------------------------------------------
| ALL behind auth. Nothing here is crawlable, cacheable or public: these are
| unpublished manuscripts, confidential reviewer reports and the identities
| of reviewers who were promised anonymity.
|
| Authorisation is per-journal and lives in the policies (JournalPolicy,
| SubmissionPolicy, ReviewAssignmentPolicy), never in a middleware — being
| signed in tells you nothing about whether this manuscript is any of your
| business.
*/

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // The reviewer's own invitation. ReviewAssignmentPolicy refuses every other reviewer's.
    Route::post('/reviews/{assignment}/accept', [ReviewController::class, 'accept'])->name('reviews.accept');
    Route::post('/reviews/{assignment}/decline', [ReviewController::class, 'decline'])->name('reviews.decline');
    Route::post('/reviews/{assignment}/report', [ReviewController::class, 'report'])->name('reviews.report');

    Route::post('/submissions/{submission}/reviewers', [ReviewController::class, 'invite'])->name('submissions.invite');
    Route::post('/reviews/{assignment}/withdraw', [ReviewController::class, 'withdraw'])->name('reviews.withdraw');
    Route::post('/submissions/{submission}/decision', [DecisionController::class, 'store'])->name('submissions.decision');

    // The author's revised manuscript, in response to a revise-and-resubmit decision. Closes
    // the revision loop. Policy-gated to the owner while status is RevisionsRequested.
    Route::post('/submissions/{submission}/revision', [SubmissionController::class, 'revision'])
        ->middleware('throttle:20,60')
        ->name('submissions.revision');

    // Internal editorial discussion threads on a manuscript. Not crawlable, not public, not
    // author-facing — the controller gates every write on viewAllSubmissions. Alongside the
    // other editorial-office mutations, not under /admin, which is where the read screens live.
    Route::post('/submissions/{submission}/discussions', [SubmissionDiscussionController::class, 'store'])->name('discussions.store');
    Route::post('/discussions/{discussion}/messages', [SubmissionDiscussionController::class, 'reply'])->name('discussions.reply');
});

/*
|--------------------------------------------------------------------------
| Editorial admin — issues, articles, publication, DOIs
|--------------------------------------------------------------------------
| Behind `auth`, and then behind the EXISTING policies — JournalPolicy,
| IssuePolicy and ArticlePolicy — asked per route, per object. There is no
| "is an admin" middleware, because there is no such person: roles are
| per-journal (Spatie teams), so the only meaningful question is "may this
| person do this ON THIS JOURNAL", and a middleware cannot ask it.
|
| Two routes here can spend money and make a URL permanent — articles.publish
| and issues.publish — and both call the existing publish actions rather than
| reimplementing the pre-flight. `production` reaches neither: it may prepare
| everything and make nothing permanent.
|
| {article:id} rather than {article}: Article::getRouteKeyName() is `slug`,
| and a slug is unique only WITHIN a journal (see the articles migration). The
| public routes can bind on it because they only ever serve published articles;
| the admin edits drafts across every journal, where two of them may legally
| share one.
*/

Route::middleware('auth')->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', AdminDashboardController::class)->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Editorial cockpit — the submission queue and one manuscript's detail
    |--------------------------------------------------------------------------
    | READ-ONLY screens. Inviting a reviewer and recording a decision do NOT
    | live here — they POST to the existing audited endpoints (submissions.invite,
    | submissions.decision, defined above under the editorial-office group). This
    | controller renders what those act on.
    |
    | The queue is journal-scoped (/journals/{journal}/...), like issues and
    | deposits. The detail and download bind a submission directly — a submission
    | id is globally unique, so there is no journal to disambiguate, and the
    | policy is asked against submission->journal either way.
    */
    Route::get('/journals/{journal}/submissions', [AdminSubmissionController::class, 'index'])->name('submissions.index');
    Route::get('/submissions/{submission}', [AdminSubmissionController::class, 'show'])->name('submissions.show');
    Route::get('/submissions/{submission}/files/{file}', [AdminSubmissionController::class, 'download'])->name('submissions.files.download');

    // --- Volumes & issues (issue-based journals only; a 404 for continuous ones) ---
    Route::get('/journals/{journal}/issues', [IssueController::class, 'index'])->name('issues.index');
    Route::post('/journals/{journal}/issues', [IssueController::class, 'store'])->name('issues.store');
    Route::post('/journals/{journal}/volumes', [VolumeController::class, 'store'])->name('volumes.store');
    Route::put('/volumes/{volume}', [VolumeController::class, 'update'])->name('volumes.update');

    Route::get('/issues/{issue}', [IssueController::class, 'show'])->name('issues.show');
    Route::put('/issues/{issue}', [IssueController::class, 'update'])->name('issues.update');

    // Reordering changes which article a sequence-derived DOI suffix refers to. IssuePolicy
    // refuses it outright on a published issue.
    Route::post('/issues/{issue}/reorder', [IssueController::class, 'reorder'])->name('issues.reorder');

    // --- Articles ---
    Route::get('/journals/{journal}/articles/create', [AdminArticleController::class, 'create'])->name('articles.create');

    // POST, not PUT, for the update: the form carries the PDF, and a multipart body cannot
    // be method-spoofed through Inertia's file upload without a hidden _method dance that
    // buys nothing here.
    Route::post('/journals/{journal}/articles', [AdminArticleController::class, 'store'])->name('articles.store');
    Route::get('/articles/{article:id}/edit', [AdminArticleController::class, 'edit'])->name('articles.edit');
    Route::post('/articles/{article:id}', [AdminArticleController::class, 'update'])->name('articles.update');

    // --- The publish gate. No undo exists beyond this line. ---
    Route::post('/articles/{article:id}/publish', [PublishController::class, 'article'])->name('articles.publish');
    Route::post('/issues/{issue}/publish', [PublishController::class, 'issue'])->name('issues.publish');

    // --- Crossref deposit log ---
    Route::get('/journals/{journal}/deposits', [DepositController::class, 'index'])->name('deposits.index');
    Route::post('/deposits/{deposit}/retry', [DepositController::class, 'retry'])->name('deposits.retry');
    Route::get('/deposits/{deposit}/xml', [DepositController::class, 'xml'])->name('deposits.xml');

    // --- Settings & people ---
    Route::get('/journals/{journal}/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/journals/{journal}/settings', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/journals/{journal}/users', [UserController::class, 'index'])->name('users.index');
    Route::put('/journals/{journal}/users/{user}', [UserController::class, 'update'])->name('users.update');

    /*
    |--------------------------------------------------------------------------
    | Accounts and roles — SITE-WIDE, and that is the whole distinction
    |--------------------------------------------------------------------------
    | admin.users.*    (above) — "who does what ON THIS JOURNAL". Team-scoped.
    | admin.accounts.* (here)  — the PERSON: name, email, password, active,
    |                            site admin, and their roles across every journal.
    |
    | A user is not "of" a journal. They exist, and then they hold roles on
    | journals — so there is no journal to scope this to, and `can:manage-users`
    | is a gate with no model, exactly like `manage-site-content` below.
    |
    | Unlike the journal routes above, these DO carry middleware: the guard is a
    | site-wide gate, not a per-object policy, so there is no model for a
    | controller-level authorize() to ask about. Each action re-authorizes
    | anyway — the middleware is the fence, not the lock.
    |
    | admin.roles.* is site-admin only (`manage-roles`): a role definition is
    | team-agnostic, so editing it changes every journal at once.
    */
    Route::middleware('can:manage-users')->group(function () {
        Route::get('/users', [AccountController::class, 'index'])->name('accounts.index');
        Route::get('/users/create', [AccountController::class, 'create'])->name('accounts.create');
        Route::post('/users', [AccountController::class, 'store'])->name('accounts.store');

        // {account} binds User by id. Named `account` rather than `user` so it cannot be
        // confused with the {user} of the per-journal routes above, which means something
        // different: a member of one journal, not an account.
        Route::get('/users/{account}/edit', [AccountController::class, 'edit'])->name('accounts.edit');
        Route::put('/users/{account}', [AccountController::class, 'update'])->name('accounts.update');
        Route::delete('/users/{account}', [AccountController::class, 'destroy'])->name('accounts.destroy');
    });

    Route::middleware('can:manage-roles')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });

    /*
    |--------------------------------------------------------------------------
    | Site content — the CMS
    |--------------------------------------------------------------------------
    | Settings, pages, navigation, the homepage, news, Research Topics, the
    | media library and the newsletter list.
    |
    | ONE GATE GUARDS ALL OF IT: `manage-site-content`.
    |
    | Not a policy, and not `can:journal.settings.manage` on some journal —
    | because this content is SITE-WIDE and roles are PER-JOURNAL. "May you
    | edit the privacy policy?" has no per-journal answer; the privacy policy
    | is not JCD&MS's. The gate (AppServiceProvider) allows a site admin, or
    | anyone holding publisher-admin on any journal.
    |
    | Every route below is behind it — a journal-editor reaching /admin/content
    | gets a 403, not a screen that fails when they press save.
    */
    Route::prefix('content')
        ->name('content.')
        ->middleware('can:manage-site-content')
        ->group(function () {
            // THE ONE MARKDOWN RENDERER. The editor's live preview round-trips through the
            // same MarkdownRenderer the public page uses, so the two cannot drift.
            Route::post('/preview', PreviewController::class)->name('preview');

            Route::get('/settings', [SiteSettingController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [SiteSettingController::class, 'update'])->name('settings.update');

            // {page:id}, not the model's route key (slug) — an editor renaming a slug must
            // not have the admin URL change under them mid-edit.
            Route::get('/pages', [ContentPageController::class, 'index'])->name('pages.index');
            Route::get('/pages/create', [ContentPageController::class, 'create'])->name('pages.create');
            Route::post('/pages', [ContentPageController::class, 'store'])->name('pages.store');
            Route::get('/pages/{page:id}/edit', [ContentPageController::class, 'edit'])->name('pages.edit');
            Route::put('/pages/{page:id}', [ContentPageController::class, 'update'])->name('pages.update');
            Route::delete('/pages/{page:id}', [ContentPageController::class, 'destroy'])->name('pages.destroy');

            Route::get('/menus', [MenuController::class, 'index'])->name('menus.index');
            Route::post('/menus/{menu:id}/items', [MenuController::class, 'storeItem'])->name('menus.items.store');
            Route::post('/menus/{menu:id}/reorder', [MenuController::class, 'reorder'])->name('menus.reorder');
            Route::put('/menu-items/{item}', [MenuController::class, 'updateItem'])->name('menus.items.update');
            Route::delete('/menu-items/{item}', [MenuController::class, 'destroyItem'])->name('menus.items.destroy');

            Route::get('/home', [HomeSectionController::class, 'index'])->name('home.index');
            Route::put('/home/{section:id}', [HomeSectionController::class, 'update'])->name('home.update');

            Route::get('/news', [ContentNewsController::class, 'index'])->name('news.index');
            Route::get('/news/create', [ContentNewsController::class, 'create'])->name('news.create');
            Route::post('/news', [ContentNewsController::class, 'store'])->name('news.store');
            Route::get('/news/{news:id}/edit', [ContentNewsController::class, 'edit'])->name('news.edit');
            Route::put('/news/{news:id}', [ContentNewsController::class, 'update'])->name('news.update');
            Route::delete('/news/{news:id}', [ContentNewsController::class, 'destroy'])->name('news.destroy');

            Route::get('/topics', [TopicController::class, 'index'])->name('topics.index');
            Route::get('/topics/create', [TopicController::class, 'create'])->name('topics.create');
            Route::post('/topics', [TopicController::class, 'store'])->name('topics.store');
            Route::get('/topics/{topic:id}/edit', [TopicController::class, 'edit'])->name('topics.edit');
            Route::put('/topics/{topic:id}', [TopicController::class, 'update'])->name('topics.update');
            Route::delete('/topics/{topic:id}', [TopicController::class, 'destroy'])->name('topics.destroy');

            /*
             * Subject fields — the filter chips on /journals.
             *
             * Site content, not a journal's: "Economics & Finance" is not JCD&MS's, JCD&MS
             * is IN it. So it sits behind manage-site-content with the rest of the CMS,
             * and there is no per-journal variant of this screen.
             */
            Route::get('/fields', [FieldController::class, 'index'])->name('fields.index');
            Route::post('/fields', [FieldController::class, 'store'])->name('fields.store');
            Route::put('/fields/{field}', [FieldController::class, 'update'])->name('fields.update');
            Route::delete('/fields/{field}', [FieldController::class, 'destroy'])->name('fields.destroy');

            Route::get('/media', [MediaController::class, 'index'])->name('media.index');
            Route::post('/media', [MediaController::class, 'store'])->name('media.store');
            Route::patch('/media/{media}', [MediaController::class, 'update'])->name('media.update');
            Route::delete('/media/{media}', [MediaController::class, 'destroy'])->name('media.destroy');

            // GET only. There is no "add a subscriber" and no "confirm on their behalf":
            // both would be somebody other than the subscriber consenting for them.
            Route::get('/newsletter', [ContentNewsletterController::class, 'index'])->name('newsletter.index');
            Route::get('/newsletter/export', [ContentNewsletterController::class, 'export'])->name('newsletter.export');
        });
});

/*
|--------------------------------------------------------------------------
| Discovery — OAI-PMH and the other harvester-facing surfaces
|--------------------------------------------------------------------------
| Required HERE, above the catch-all, and not from a `then:` callback in
| bootstrap/app.php — because `then` runs AFTER this file, which would put
| /oai BELOW the /{page} catch-all in registration order. Laravel matches in
| that order, so /oai was being looked up as a CMS page and 404ing: every
| harvester endpoint dead, and nothing but OaiPmhTest to notice.
|
| The rule below is not a style preference. It is this.
*/
require __DIR__.'/discovery.php';

/*
|--------------------------------------------------------------------------
| CMS pages — MUST BE LAST
|--------------------------------------------------------------------------
| A top-level catch-all: /author-guidelines, /privacy-policy, /contact …
|
| Declared at the very bottom on purpose. Laravel matches in declaration
| order, so if this sat any higher it would swallow /journals, /articles,
| /admin and everything else — every route in the file above would 404 with
| "page not found", and the cause would be entirely non-obvious.
|
| Any new route MUST be added ABOVE this line.
*/
Route::get('/{page}', [PageController::class, 'show'])->name('pages.show');
