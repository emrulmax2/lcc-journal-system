<?php

declare(strict_types=1);

use App\Console\Commands\CheckDoisCommand;
use App\Console\Commands\CheckSsrCommand;
use App\Console\Commands\ComputeJournalMetricsCommand;
use App\Console\Commands\SendReviewRemindersCommand;
use Illuminate\Support\Facades\Schedule;

/*
 * Both scheduled checks below guard against SILENT failures — things that break without
 * anyone noticing, because the site keeps looking perfectly fine to a human.
 */

// If the Node SSR process dies, Inertia falls back to client-side rendering: humans see a
// working site, every crawler sees an empty page, and Google Scholar quietly drops the
// journal. Nothing else in the stack will tell you this has happened.
Schedule::command(CheckSsrCommand::class)
    ->hourly()
    ->emailOutputOnFailure(config('crossref.depositor_email'));

// Link rot. A DOI minted two years ago that now 404s is invisible from inside the site —
// nothing here links to it, only other people's citations do.
Schedule::command(CheckDoisCommand::class)
    ->weeklyOn(1, '03:00')
    ->emailOutputOnFailure(config('crossref.depositor_email'));

// Acceptance rate, median days to decision, article and editor counts — recomputed from
// our own records, so that nobody can type a flattering number into a field that authors
// read as fact when choosing where to submit. impact_factor and cite_score are NOT
// touched: they are issued by JCR and Scopus, and computing our own would be a fabrication.
Schedule::command(ComputeJournalMetricsCommand::class)->dailyAt('02:00');

// Chase reviewers whose report is due soon or overdue. A late review is the biggest source of
// slow decisions, and only the editor could see it before this — the reviewer got no nudge.
// Once a day is enough; the command itself spaces reminders to the same reviewer.
Schedule::command(SendReviewRemindersCommand::class)->dailyAt('07:00');
