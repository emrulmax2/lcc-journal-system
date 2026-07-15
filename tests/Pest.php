<?php

// The imports live at the TOP, not down beside the helpers they serve. Pint's
// fully_qualified_strict_types fixer rewrites `Tests\TestCase::class` to `TestCase::class`
// and adds the import wherever the import block happens to be — and a `use` statement that
// sits BELOW the code referencing it does not alias that code, so pest()->extend() below
// silently fails to find the class.
use App\Actions\SubmitManuscriptAction;
use App\Models\Journal;
use App\Models\Submission;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
    /*
     * withoutVite(): tests must not require a built asset manifest.
     *
     * Note what this means for the citation-meta tests, and why it is the RIGHT
     * configuration rather than a convenience: with Vite stubbed there is no JS bundle
     * and no Inertia SSR process. So those tests assert the citation_* tags are present
     * in HTML rendered by PHP alone — which is exactly the guarantee we are claiming.
     * If someone later moves the meta tags into an Inertia <Head>, these tests go red,
     * which is the entire point.
     */
    ->beforeEach(fn () => $this->withoutVite())
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Grant a role ON A SPECIFIC JOURNAL. Roles are per-journal (Spatie teams) — "an editor"
 * with no journal named is a meaningless idea in this system.
 *
 * (JournalAuthorizationTest declares its own `grantOn`; this one lives here because three
 * separate feature files need it and a test file's function is only defined once that file
 * has been loaded.)
 */
function grantRoleOn(User $user, Journal $journal, string $role): User
{
    $registrar = app(PermissionRegistrar::class);
    $registrar->setPermissionsTeamId($journal->id);
    $user->assignRole($role);
    $registrar->setPermissionsTeamId(null);

    return $user->fresh();
}

/**
 * Submit a manuscript through the REAL action, exactly as the controller does — so that
 * every test downstream of a submission is testing a row the production path could
 * actually have produced.
 *
 * @param  array<string, mixed>  $overrides
 */
function submitManuscript(User $author, Journal $journal, array $overrides = []): Submission
{
    $data = array_merge([
        'title' => 'Coral refugia under repeated thermal stress',
        'type' => null,
        'abstract' => 'A study of thermal refugia across three reef systems, and what they imply for resilience.',
        'keywords' => 'coral, refugia, reef resilience',
        'authors' => [
            ['name' => 'Ada Byron King', 'email' => 'ada@example.edu', 'affiliation' => 'London Churchill College', 'corresponding' => true],
            ['name' => 'Alan Turing', 'email' => 'alan@example.edu', 'affiliation' => 'NPL', 'corresponding' => false],
        ],
        'funding' => 'Wellcome Trust 12345',
        'ethics' => true,
        'conflicts' => true,
        'dataAvailable' => true,
    ], $overrides);

    return app(SubmitManuscriptAction::class)->execute(
        $author,
        $journal,
        $data,
        UploadedFile::fake()->create('manuscript.pdf', 120, 'application/pdf'),
    );
}

/**
 * THE PAGE PROPS AS THEY LEAVE THE SERVER — not the rendered page.
 *
 * The anonymity tests assert against this. A rendered page can hide a leaked field in a
 * component that happens not to print it today; the props are what actually crossed the
 * wire, and what any future component, dev-tools panel or XHR could read.
 *
 * @return array<string, mixed>
 */
function pageProps(TestResponse $response): array
{
    return $response->viewData('page')['props'];
}

/** The whole payload as a string. Nothing hides from a substring search. */
function pagePropsJson(TestResponse $response): string
{
    return (string) json_encode(pageProps($response));
}
