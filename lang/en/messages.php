<?php

declare(strict_types=1);

/*
 * Interface strings, English (the source language).
 *
 * The WHOLE array is shared to React per request (HandleInertiaRequests), and read on the
 * frontend with t('group.key'). Keep keys stable — they are the contract the components rely
 * on. A missing key falls back to the key itself, never to a blank.
 *
 * This is a starter set covering the public reading surface and the article page. Add keys as
 * screens are localised; every locale file mirrors these keys.
 */

return [
    'nav' => [
        'articles' => 'Articles',
        'journals' => 'Journals',
        'about' => 'About',
        'submit' => 'Submit',
        'dashboard' => 'Dashboard',
    ],

    'article' => [
        'download_pdf' => 'Download PDF',
        'read_full_text' => 'Read full text',
        'cite' => 'Cite',
        'share' => 'Share',
        'abstract' => 'Abstract',
        'references' => 'References',
        'keywords' => 'Keywords',
    ],

    'common' => [
        'language' => 'Language',
        'search' => 'Search',
        'read_more' => 'Read more',
        'loading' => 'Loading…',
    ],
];
