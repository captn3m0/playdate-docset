<?php

namespace App\Docsets;

use Godbout\DashDocsetBuilder\Docsets\BaseDocset;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Wa72\HtmlPageDom\HtmlPageCrawler;

class Playdate extends BaseDocset
{
    public const CODE = 'playdate';
    public const NAME = 'Playdate';
    public const URL = 'sdk.play.date';
    // dashIndexFilePath: a trimmed-down copy of play.date/dev that links to the
    // three references. Shipped under the sdk.play.date domain (see grab()) so
    // it shares the URL prefix and its ../../ asset links keep resolving.
    public const INDEX = 'dev/index.html';
    public const PLAYGROUND = '';
    public const ICON_16 = 'favicon-16x16.png';
    public const ICON_32 = 'favicon-32x32.png';
    public const EXTERNAL_DOMAINS = [
        'help.play.date',
        'static-cdn.play.date',
    ];

    /**
     * Asciidoctor "item" CSS class -> Dash entry type.
     */
    protected const ITEM_TYPES = [
        'function' => 'Function',
        'method' => 'Method',
        'callback' => 'Callback',
        'property' => 'Property',
        'variable' => 'Variable',
    ];

    /**
     * The pre-downloaded doc domains shipped inside the docset.
     */
    protected const DOC_DOMAINS = [
        'sdk.play.date',
        'help.play.date',
        'static-cdn.play.date',
    ];

    /**
     * Absolute play.date URLs that should point at a bundled doc instead. The
     * value is the target's path relative to the Documents root; the actual
     * (per-file) relative href is computed at format time. Both the "pretty"
     * URLs and the versioned ones are covered, and the bare sdk.play.date root
     * (which redirects to the Lua reference). Fragments are preserved.
     */
    protected const INTERNAL_LINKS = [
        'https://sdk.play.date/' => 'sdk.play.date/3.0.6/Inside%20Playdate.html',
        'https://sdk.play.date/inside-playdate' => 'sdk.play.date/3.0.6/Inside%20Playdate.html',
        'https://sdk.play.date/3.0.6/Inside%20Playdate.html' => 'sdk.play.date/3.0.6/Inside%20Playdate.html',
        'https://sdk.play.date/inside-playdate-with-c' => 'sdk.play.date/3.0.6/Inside%20Playdate%20with%20C.html',
        'https://sdk.play.date/3.0.6/Inside%20Playdate%20with%20C.html' => 'sdk.play.date/3.0.6/Inside%20Playdate%20with%20C.html',
        'https://sdk.play.date/designing-for-playdate' => 'help.play.date/developer/designing-for-playdate/index.html',
        'https://help.play.date/developer/designing-for-playdate' => 'help.play.date/developer/designing-for-playdate/index.html',
        'https://help.play.date/developer/designing-for-playdate/' => 'help.play.date/developer/designing-for-playdate/index.html',
    ];


    /**
     * The docs are already downloaded (via wget) into the project's ./html
     * directory, so instead of fetching them again we just copy the relevant
     * domains into the builder's download directory. Analytics-only hosts
     * (plausible.io, umami.panic.com) are intentionally left out. Paths are
     * anchored to the working directory, which is also where the builder's
     * `storage` disk lives, so run the build from the project root.
     */
    public function grab(): bool
    {
        $source = getcwd() . '/html';
        $destination = getcwd() . '/storage/' . $this->downloadedDirectory();

        foreach (self::DOC_DOMAINS as $domain) {
            if (File::isDirectory("{$source}/{$domain}")) {
                File::copyDirectory("{$source}/{$domain}", "{$destination}/{$domain}");
            }
        }

        // The landing page comes from play.date/dev; drop it inside the
        // sdk.play.date domain so it becomes <URL>/dev/index.html and its
        // depth-2 (../../) asset links still point at static-cdn.play.date.
        if (File::isDirectory("{$source}/play.date/dev")) {
            File::copyDirectory("{$source}/play.date/dev", "{$destination}/" . static::URL . '/dev');
        }

        return File::exists(
            "{$destination}/" . static::URL . '/' . rawurldecode(static::INDEX)
        );
    }

    public function entries(string $file): Collection
    {
        if (Str::contains($file, static::URL . '/dev/')) {
            return collect([[
                'name' => 'Playdate Development Documentation',
                'type' => 'Guide',
                'path' => $this->relativePath($file),
            ]]);
        }

        $crawler = HtmlPageCrawler::create(Storage::get($file));

        if (Str::contains($file, 'designing-for-playdate')) {
            return $this->guideEntries($crawler, $file);
        }

        return $this->apiEntries($crawler, $file);
    }

    /**
     * Index the Asciidoctor "Inside Playdate" (Lua) and "Inside Playdate with C"
     * references: every API item (function/method/callback/property/variable)
     * plus every class, module and section heading.
     */
    protected function apiEntries(HtmlPageCrawler $crawler, string $file): Collection
    {
        $entries = collect();
        $base = $this->relativePath($file);

        $crawler->filter('.item')->each(function (HtmlPageCrawler $node) use ($entries, $base) {
            $type = $this->itemType($node);
            if ($type === null) {
                return;
            }

            $id = (string) $node->attr('id');
            $name = $this->itemName($node);
            if ($id === '' || $name === '') {
                return;
            }

            $entries->push([
                'name' => $name,
                'type' => $type,
                'path' => "{$base}#{$id}",
            ]);
        });

        $crawler->filter('.sect1, .sect2, .sect3, .sect4, .sect5')->each(function (HtmlPageCrawler $node) use ($entries, $base) {
            $heading = $node->filter('h1, h2, h3, h4, h5, h6');
            if ($heading->count() === 0) {
                return;
            }
            $heading = $heading->first();

            $id = (string) $heading->attr('id');
            $name = $this->cleanHeading($heading->text());
            if ($id === '' || $name === '') {
                return;
            }

            $entries->push([
                'name' => $name,
                'type' => $this->sectionType($node),
                'path' => "{$base}#{$id}",
            ]);
        });

        return $entries;
    }

    /**
     * Index the Hugo-generated "Designing for Playdate" guide by its headings.
     */
    protected function guideEntries(HtmlPageCrawler $crawler, string $file): Collection
    {
        $entries = collect();
        $base = $this->relativePath($file);

        $entries->push([
            'name' => 'Designing for Playdate',
            'type' => 'Guide',
            'path' => $base,
        ]);

        $crawler->filter('h2[id], h3[id]')->each(function (HtmlPageCrawler $node) use ($entries, $base) {
            $id = (string) $node->attr('id');
            $name = $this->cleanHeading($node->text());
            if ($id === '' || $name === '') {
                return;
            }

            $entries->push([
                'name' => $name,
                'type' => strtolower($node->nodeName()) === 'h2' ? 'Guide' : 'Section',
                'path' => "{$base}#{$id}",
            ]);
        });

        return $entries;
    }

    public function format(string $file): string
    {
        $crawler = HtmlPageCrawler::create(Storage::get($file));

        $this->removeChrome($crawler);
        $this->removeJavaScript($crawler);
        $this->fixLazyImages($crawler);
        $this->internalizeLinks($crawler, $file);

        if (Str::contains($file, static::URL . '/dev/')) {
            $this->formatLandingPage($crawler);
        } elseif (Str::contains($file, 'designing-for-playdate')) {
            $this->insertGuideAnchors($crawler);
        } else {
            $this->insertApiAnchors($crawler);
        }

        // Drop IE conditional comments (e.g. the html5shiv <script>) that the
        // DOM crawler can't see because they live inside HTML comments.
        return preg_replace(
            '/<!--\[if[^\]]*\]>.*?<!\[endif\]-->/is',
            '',
            $crawler->saveHTML()
        );
    }

    /**
     * Strip the play.date site navigation, footers and version switcher.
     */
    protected function removeChrome(HtmlPageCrawler $crawler): void
    {
        $selectors = [
            '#navbar',
            '#footer',
            '#docSwitcher',
            'nav.breadcrumb-nav',
            '#sitemap',
            'footer',
        ];

        foreach ($selectors as $selector) {
            $this->removeIfPresent($crawler, $selector);
        }
    }

    protected function removeIfPresent(HtmlPageCrawler $crawler, string $selector): void
    {
        $node = $crawler->filter($selector);
        if ($node->count()) {
            $node->remove();
        }
    }

    /**
     * Turn the play.date/dev page into a lean docset landing page: keep the
     * "Playdate Development Documentation" card (repointed at the local docs)
     * and the developer resource cards, drop the marketing chrome the site
     * wraps them in.
     */
    protected function formatLandingPage(HtmlPageCrawler $crawler): void
    {
        $this->insertTopAnchor($crawler);

        // Secondary tab bar (SDK / Pulp / Links / Dev Help), the "For the
        // coders…" heading, the SDK download + license card, the Pulp and
        // "Playdate Mirror" marketing sections, and the email signup.
        foreach (['nav.internal-nav', 'section.gluedToTop', '#cardSDK', 'section.pulp', 'section.more', '#devNews'] as $selector) {
            $this->removeIfPresent($crawler, $selector);
        }

        // The "For everybody else…" and "Cool info for all" dividers are bare
        // (class-less) <section> headings; remove those, keep the SDK card grid.
        $crawler->filter('main > section')->each(function (HtmlPageCrawler $section) {
            if ((string) $section->attr('class') === '') {
                $section->remove();
            }
        });

        // Drop the now-empty card grid the SDK download card lived in.
        $crawler->filter('ul.cards')->each(function (HtmlPageCrawler $list) {
            if ($list->filter('li')->count() === 0) {
                $list->remove();
            }
        });
    }

    /**
     * A no-JS docset: drop every script (including analytics) and noscript.
     */
    protected function removeJavaScript(HtmlPageCrawler $crawler): void
    {
        foreach (['script', 'noscript'] as $selector) {
            $node = $crawler->filter($selector);
            if ($node->count()) {
                $node->remove();
            }
        }
    }

    /**
     * The "Designing for Playdate" guide lazy-loads images with lazysizes: the
     * real file sits in `src` but a 1x1 base64 gif in `srcset` shadows it and
     * the `.lazyload` class hides it until JS runs. Undo both so images render.
     */
    protected function fixLazyImages(HtmlPageCrawler $crawler): void
    {
        $images = $crawler->filter('img[srcset]');
        if ($images->count()) {
            $images->each(function (HtmlPageCrawler $img) {
                $img->removeAttr('srcset');
                $img->removeClass('lazyload');
            });
        }
    }

    protected function insertApiAnchors(HtmlPageCrawler $crawler): void
    {
        $this->insertTopAnchor($crawler);

        $crawler->filter('.item')->each(function (HtmlPageCrawler $node) {
            $type = $this->itemType($node);
            if ($type === null) {
                return;
            }
            $name = $this->itemName($node);
            if ($name === '') {
                return;
            }
            $node->before($this->dashAnchor($type, $name));
        });

        $crawler->filter('.sect1, .sect2, .sect3, .sect4, .sect5')->each(function (HtmlPageCrawler $node) {
            $heading = $node->filter('h1, h2, h3, h4, h5, h6');
            if ($heading->count() === 0) {
                return;
            }
            $heading = $heading->first();
            $name = $this->cleanHeading($heading->text());
            if ($name === '') {
                return;
            }
            $heading->before($this->dashAnchor($this->sectionType($node), $name));
        });
    }

    protected function insertGuideAnchors(HtmlPageCrawler $crawler): void
    {
        $this->insertTopAnchor($crawler);

        $crawler->filter('h2[id], h3[id]')->each(function (HtmlPageCrawler $node) {
            $name = $this->cleanHeading($node->text());
            if ($name === '') {
                return;
            }
            $type = strtolower($node->nodeName()) === 'h2' ? 'Guide' : 'Section';
            $node->before($this->dashAnchor($type, $name));
        });
    }

    protected function insertTopAnchor(HtmlPageCrawler $crawler): void
    {
        $body = $crawler->filter('body');
        if ($body->count()) {
            $body->prepend('<a name="//apple_ref/cpp/Section/Top" class="dashAnchor"></a>');
        }
    }

    protected function dashAnchor(string $type, string $name): string
    {
        return '<a name="//apple_ref/cpp/' . $type . '/' . rawurlencode($name) . '" class="dashAnchor"></a>';
    }

    /**
     * Resolve an "item" div's Dash type from its CSS classes, skipping the
     * cross-reference ("xref") duplicates so entries point at the canonical
     * definition.
     */
    protected function itemType(HtmlPageCrawler $node): ?string
    {
        $classes = preg_split('/\s+/', (string) $node->attr('class'));

        if (in_array('xref', $classes, true)) {
            return null;
        }

        foreach (self::ITEM_TYPES as $class => $type) {
            if (in_array($class, $classes, true)) {
                return $type;
            }
        }

        return null;
    }

    /**
     * The signature title is the item's first `.title` child (later `.title`
     * nodes belong to nested Tip/Note admonition blocks).
     */
    protected function itemName(HtmlPageCrawler $node): string
    {
        $title = $node->filter('.title');
        if ($title->count() === 0) {
            return '';
        }

        return trim($title->first()->text());
    }

    protected function sectionType(HtmlPageCrawler $node): string
    {
        $classes = preg_split('/\s+/', (string) $node->attr('class'));

        if (in_array('class', $classes, true)) {
            return 'Class';
        }
        if (in_array('module', $classes, true)) {
            return 'Module';
        }
        if (in_array('sect1', $classes, true)) {
            return 'Guide';
        }

        return 'Section';
    }

    /**
     * Drop the Asciidoctor section numbering ("7.20. Graphics" -> "Graphics").
     */
    protected function cleanHeading(string $text): string
    {
        return trim(preg_replace('/^\d+(\.\d+)*\.?\s+/', '', trim($text)));
    }

    protected function relativePath(string $file): string
    {
        $relative = Str::after($file, $this->innerDirectory() . '/');

        return str_replace(' ', '%20', $relative);
    }

    /**
     * Rewrite absolute cross-references between the bundled docs (Lua <-> C and
     * both -> Designing) into local relative links, keeping any #fragment.
     * Unknown play.date links (forum, downloads, other help pages) are left
     * alone so they still open in a browser.
     */
    protected function internalizeLinks(HtmlPageCrawler $crawler, string $file): void
    {
        $links = $crawler->filter('a[href]');
        if ($links->count() === 0) {
            return;
        }

        $fromDirectory = dirname($this->relativePath($file));

        $links->each(function (HtmlPageCrawler $anchor) use ($fromDirectory) {
            [$url, $fragment] = array_pad(explode('#', (string) $anchor->attr('href'), 2), 2, null);

            if (! isset(self::INTERNAL_LINKS[$url])) {
                return;
            }

            $href = $this->relativeUrl($fromDirectory, self::INTERNAL_LINKS[$url]);
            if ($fragment !== null && $fragment !== '') {
                $href .= '#' . $fragment;
            }

            $anchor->getNode(0)->setAttribute('href', $href);
        });
    }

    /**
     * Build a relative path from a directory to a target file, both expressed
     * relative to the Documents root.
     */
    protected function relativeUrl(string $fromDirectory, string $target): string
    {
        $from = ($fromDirectory === '' || $fromDirectory === '.') ? [] : explode('/', $fromDirectory);
        $to = explode('/', $target);
        $file = array_pop($to);

        while ($from && $to && $from[0] === $to[0]) {
            array_shift($from);
            array_shift($to);
        }

        $to[] = $file;

        return str_repeat('../', count($from)) . implode('/', $to);
    }
}
