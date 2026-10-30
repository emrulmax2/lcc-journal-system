<?php

declare(strict_types=1);

use App\Models\Journal;
use App\Models\JournalSection;
use App\Models\Submission;
use App\Rules\SensibleText;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * The submission form is public — guests can submit — and what it collects is shown to
 * editors and, once accepted, published. Junk must not get in: one giant unbroken "word",
 * keyboard-mash, implausible lengths, names made of symbols, or a flood of submissions.
 */
beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    Storage::fake('private');
    RateLimiter::clear('submit');

    $journal = Journal::factory()->create(['slug' => 'jcdms', 'abbreviation' => 'JCD&MS']);
    JournalSection::factory()->create(['journal_id' => $journal->id, 'name' => 'Research Article']);
});

function validSubmission(array $overrides = []): array
{
    return array_replace([
        'journal' => 'jcdms',
        'title' => 'Healthcare-seeking behaviour among people with disabilities in Bangladesh',
        'type' => 'Research Article',
        'abstract' => 'This study examines how people with disabilities in rural Bangladesh choose between public, '
            .'private and traditional care. Using interviews with sixty households and a survey of local clinics, '
            .'it finds that cost and distance outweigh trust, and sets out what that means for policy.',
        'keywords' => 'disability, health systems, Bangladesh',
        'file' => UploadedFile::fake()->create('manuscript.pdf', 50, 'application/pdf'),
        'authors' => [['name' => 'Siobhán O’Brien-Papé', 'email' => 'author@example.edu', 'affiliation' => 'London Churchill College', 'corresponding' => true]],
        'funding' => 'None.',
        'ethics' => true,
        'conflicts' => true,
    ], $overrides);
}

it('accepts a real manuscript — accents, hyphens, apostrophes, numbers and links included', function () {
    $this->post('/submit', validSubmission([
        'abstract' => validSubmission()['abstract'].' Data for 100000000 records are at https://doi.org/10.1080/0144929X.2021.1928290 today.',
    ]))->assertSessionHasNoErrors();

    expect(Submission::count())->toBe(1);
});

it('rejects the keyboard-mash abstract from the screenshot', function () {
    $this->post('/submit', validSubmission([
        'abstract' => 'testtestdsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfsfs',
    ]))->assertSessionHasErrors('abstract');

    expect(Submission::count())->toBe(0);
});

it('rejects repeated junk even when it is broken into short words', function () {
    $abstract = validSubmission()['abstract'].' asdasdasd fsfsfsfsfsfsfsfs';

    $this->post('/submit', validSubmission(['abstract' => $abstract]))->assertSessionHasErrors('abstract');
});

it('rejects a giant unbroken word, and a link that is absurdly long', function () {
    $this->post('/submit', validSubmission(['title' => 'A title with '.str_repeat('x', 30).str_repeat('y', 30).' inside']))
        ->assertSessionHasErrors('title');

    $this->post('/submit', validSubmission(['abstract' => validSubmission()['abstract'].' https://example.com/'.str_repeat('abcde/', 40)]))
        ->assertSessionHasErrors('abstract');
});

it('enforces sensible lengths for the title and abstract', function () {
    $this->post('/submit', validSubmission(['title' => 'test']))->assertSessionHasErrors('title');
    $this->post('/submit', validSubmission(['title' => 'Two words']))->assertSessionHasErrors('title');
    $this->post('/submit', validSubmission(['abstract' => 'Far too short to be an abstract of a research article.']))->assertSessionHasErrors('abstract');
    $this->post('/submit', validSubmission(['abstract' => str_repeat('word ', 501)]))->assertSessionHasErrors('abstract');
});

it('only accepts real names for authors', function () {
    foreach (['<script>alert(1)</script>', 'User123', 'aaaaaaaaaaaa', 'x'] as $name) {
        $this->post('/submit', validSubmission(['authors' => [['name' => $name, 'email' => 'a@example.edu', 'corresponding' => true]]]))
            ->assertSessionHasErrors('authors.0.name');
    }
});

it('limits keywords to eight short ones', function () {
    $this->post('/submit', validSubmission(['keywords' => implode(', ', array_map(fn ($i) => "topic {$i}", range(1, 9)))]))
        ->assertSessionHasErrors('keywords');

    $this->post('/submit', validSubmission(['keywords' => 'fine, '.str_repeat('long ', 15)]))
        ->assertSessionHasErrors('keywords');
});

it('rate-limits submissions from one visitor', function () {
    foreach (range(1, 6) as $i) {
        $this->post('/submit', validSubmission(['title' => "A real manuscript title number {$i}"]))->assertSessionHasNoErrors();
    }

    $this->post('/submit', validSubmission(['title' => 'The seventh manuscript in an hour']))->assertStatus(429);

    expect(Submission::count())->toBe(6);
});

it('keeps the browser and server rules in step', function () {
    // Submit.tsx reads these numbers from TEXT_LIMITS; this is the other half of the contract.
    expect(SensibleText::MAX_WORD)->toBe(50)
        ->and(SensibleText::MAX_LINK)->toBe(200)
        ->and(SensibleText::MAX_REPEATS)->toBe(8);

    $tsx = file_get_contents(resource_path('js/pages/Submit.tsx'));
    expect($tsx)->toContain('maxWord: 50,')->toContain('maxLink: 200,')->toContain('maxRepeats: 8,')
        ->toContain('abstractMinWords: 30,')->toContain('abstractMaxWords: 500,');
});
