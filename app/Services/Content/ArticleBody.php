<?php

declare(strict_types=1);

namespace App\Services\Content;

use Illuminate\Support\Str;

/**
 * An article's full text as HTML, plus its "On this page" table of contents.
 *
 * The body is Markdown (articles.body). It is rendered by MarkdownRenderer — CommonMark with
 * raw HTML ESCAPED on the way in, so nothing an author or the import wrote can inject markup —
 * and then lightly post-processed. Every change here only ADDS structure to what CommonMark
 * itself emitted; none of it can widen what is allowed:
 *
 *   - section headings (h2–h4) get stable ids, so the contents can link to them and a
 *     reader can share a link to a section;
 *   - tables are wrapped so a wide one scrolls inside its box instead of the page;
 *   - a paragraph that is nothing but italics directly after a figure is its caption;
 *   - images load lazily.
 *
 * Rendered on the SERVER, so the full text is in the raw HTML Google Scholar fetches.
 */
final class ArticleBody
{
    /** Ids the page itself uses for its own sections; a body heading must not take them. */
    private const RESERVED_IDS = ['abstract', 'keywords', 'authors', 'references', 'contents', 'metrics'];

    public function __construct(private readonly MarkdownRenderer $markdown) {}

    /**
     * @return array{html: string, toc: list<array{id: string, text: string, level: int}>}
     */
    public function render(?string $markdown): array
    {
        $html = $this->markdown->toHtml($markdown);

        if ($html === '') {
            return ['html' => '', 'toc' => []];
        }

        $toc = [];
        $used = array_fill_keys(self::RESERVED_IDS, true);

        $html = (string) preg_replace_callback('~<h([2-4])>(.*?)</h\1>~s', function (array $m) use (&$toc, &$used): string {
            $level = (int) $m[1];
            $text = trim(html_entity_decode(strip_tags($m[2]), ENT_QUOTES | ENT_HTML5));
            $id = $this->uniqueId($text, $used);

            if ($level <= 3 && $text !== '') {
                $toc[] = ['id' => $id, 'text' => $text, 'level' => $level];
            }

            return "<h{$level} id=\"{$id}\">{$m[2]}</h{$level}>";
        }, $html);

        $html = str_replace(['<table>', '</table>'], ['<div class="table-wrap"><table>', '</table></div>'], $html);

        // "*Fig. 3: Impact of …*" on its own line, as the converter writes captions.
        $html = (string) preg_replace('~<p><em>((?:(?!</em>).)+)</em></p>~s', '<p class="caption"><em>$1</em></p>', $html);

        $html = str_replace('<img ', '<img loading="lazy" decoding="async" ', $html);

        return ['html' => $html, 'toc' => $toc];
    }

    /** @param  array<string, true>  $used */
    private function uniqueId(string $text, array &$used): string
    {
        $base = Str::slug($text) ?: 'section';
        // An id must not start with a digit for every CSS selector to reach it.
        if (ctype_digit($base[0])) {
            $base = 's-'.$base;
        }

        $id = $base;
        for ($n = 2; isset($used[$id]); $n++) {
            $id = "{$base}-{$n}";
        }
        $used[$id] = true;

        return $id;
    }
}
