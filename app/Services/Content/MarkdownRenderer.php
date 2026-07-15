<?php

declare(strict_types=1);

namespace App\Services\Content;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Autolink\AutolinkExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

/**
 * Renders editor-authored Markdown to HTML.
 *
 * RAW HTML IS DISALLOWED, AND THAT IS THE ENTIRE POINT.
 *
 * The obvious CMS choice is a WYSIWYG that stores HTML. It is also how you hand every
 * person with an editor login a stored-XSS vector on a public page — one pasted
 * `<script>`, or an `onerror=` on an image, and the site is serving attacker JavaScript
 * to every reader from a trusted academic domain. Sanitising HTML after the fact is a
 * bug-compatibility race with browsers that people lose regularly.
 *
 * Markdown with `html_input: 'escape'` sidesteps the whole category: there is no HTML in,
 * so there is no HTML to sanitise. An editor typing `<script>` gets the literal text
 * `<script>` on the page, which is the correct and obvious behaviour.
 *
 * `allow_unsafe_links: false` additionally strips `javascript:` and `data:` URIs, which
 * Markdown link syntax would otherwise happily carry.
 */
final class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            // No HTML in. Nothing to sanitise on the way out.
            'html_input' => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 20,   // a hand-crafted deeply-nested doc can DoS the parser
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new AutolinkExtension);   // bare URLs become links
        $environment->addExtension(new TableExtension);      // policy pages need tables (APCs, waivers)

        $this->converter = new MarkdownConverter($environment);
    }

    public function toHtml(?string $markdown): string
    {
        if (blank($markdown)) {
            return '';
        }

        return $this->converter->convert($markdown)->getContent();
    }

    /** Plain text, for meta descriptions and search excerpts. */
    public function toText(?string $markdown, int $limit = 160): string
    {
        $text = strip_tags($this->toHtml($markdown));
        $text = trim((string) preg_replace('/\s+/u', ' ', html_entity_decode($text, ENT_QUOTES)));

        return str($text)->limit($limit)->toString();
    }
}
