<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Rejects text that cannot be real prose: one enormous unbroken "word", a short pattern
 * repeated over and over ("fsfsfsfsfs…", "aaaaaaaa"), or too few / too many words.
 *
 * Why: the submission form is public (guests can submit), and its fields are shown to
 * editors and — once accepted — published. A 2,000-character string with no spaces breaks
 * every layout it lands in, and keyboard-mash is not a manuscript. Length limits alone do
 * not catch either.
 *
 * The SAME checks run in the wizard (Submit.tsx → textProblem()), so an honest author is
 * told while typing; this class is what a hand-crafted request cannot get past.
 *
 *   new SensibleText(field: 'abstract', minWords: 30, maxWords: 500)
 */
final class SensibleText implements ValidationRule
{
    /** Longest ordinary word. "Internationalisation" is 20; chemical names and hyphenated compounds reach the 40s. */
    public const MAX_WORD = 50;

    /** A link or DOI may be longer than a word, but not unlimited. */
    public const MAX_LINK = 200;

    /** The same 1–4 characters repeated this many times in a row is mashing, not writing. */
    public const MAX_REPEATS = 8;

    public function __construct(
        private readonly string $field = 'This field',
        private readonly int $minWords = 0,
        private readonly int $maxWords = 0,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || trim($value) === '') {
            return;   // emptiness is 'required''s business, not ours
        }

        if ($problem = self::problem($value, $this->field, $this->minWords, $this->maxWords)) {
            $fail($problem);
        }
    }

    /** The first thing wrong with $text, as a sentence for the author — or null. */
    public static function problem(string $text, string $field, int $minWords = 0, int $maxWords = 0): ?string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($words as $word) {
            $isLink = (bool) preg_match('~^(https?://|doi:|10\.\d{4,9}/)~i', $word);
            $limit = $isLink ? self::MAX_LINK : self::MAX_WORD;

            if (mb_strlen($word) > $limit) {
                return $isLink
                    ? "{$field} contains a link longer than ".self::MAX_LINK.' characters.'
                    : "{$field} contains a word of ".mb_strlen($word).' characters with no spaces. Words can be at most '.self::MAX_WORD.' characters.';
            }
        }

        // 1–4 characters repeated MAX_REPEATS times in a row: "fsfsfsfsfsfsfsfs", "aaaaaaaa",
        // "!!!!!!!!". Digits and spaces are left out — "100000000" is a number, and a pasted
        // indent is just spacing.
        if (preg_match('/([^\d\s]{1,4}?)\1{'.(self::MAX_REPEATS - 1).',}/u', $text)) {
            return "{$field} contains the same characters repeated over and over. Please enter real text.";
        }

        $count = count($words);

        if ($minWords > 0 && $count < $minWords) {
            return "{$field} must be at least {$minWords} words (it is {$count}).";
        }

        if ($maxWords > 0 && $count > $maxWords) {
            return "{$field} can be at most {$maxWords} words (it is {$count}).";
        }

        return null;
    }
}
