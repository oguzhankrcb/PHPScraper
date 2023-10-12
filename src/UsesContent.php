<?php

namespace Spekulatius\PHPScraper;

use DonatelloZa\RakePlus\RakePlus;
use League\Uri\Uri;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Image as DomCrawlerImage;
use Symfony\Component\DomCrawler\Link as DomCrawlerLink;

trait UsesContent
{
    /**
     * Access conveniences: Methods to make the data more accessible.
     *
     * I like to have direct access to stuff without too many chained calls.
     * So I've added a number of things which might be of interest.
     *
     * Any suggestions what is missing? Send a PR :)
     *
     * @see https://phpscraper.de/contributing
     */
    public function title(): ?string
    {
        return $this->filterFirstText('//title');
    }

    public function charset(): ?string
    {
        return $this->filterFirstExtractAttribute('//meta[@charset]', ['charset']);
    }

    public function contentType(): ?string
    {
        return $this->filterFirstExtractAttribute('//meta[@http-equiv="Content-type"]', ['content']);
    }

    public function canonical(): ?string
    {
        return $this->filterFirstExtractAttribute('//link[@rel="canonical"]', ['href']);
    }

    public function viewportString(): ?string
    {
        return $this->filterFirstContent('//meta[@name="viewport"]');
    }

    public function viewport(): array
    {
        return is_null($this->viewportString()) ? [] : (array) \preg_split('/,\s*/', $this->viewportString());
    }

    public function csrfToken(): ?string
    {
        return $this->filterFirstExtractAttribute('//meta[@name="csrf-token"]', ['content']);
    }

    public function baseHref(): ?string
    {
        return $this->filterFirstExtractAttribute('//base', ['href']);
    }

    /**
     * Get the header collected as an array
     *
     * @return array<string, array|string|null>
     */
    public function headers(): array
    {
        return [
            'charset' => $this->charset(),
            'contentType' => $this->contentType(),
            'viewport' => $this->viewport(),
            'canonical' => $this->canonical(),
            'csrfToken' => $this->csrfToken(),
        ];
    }

    public function author(): ?string
    {
        return $this->filterFirstContent('//meta[@name="author"]');
    }

    public function image(): ?string
    {
        return $this->makeUrlAbsolute($this->filterFirstContent('//meta[@name="image"]'));
    }

    public function keywordString(): ?string
    {
        return $this->filterFirstContent('//meta[@name="keywords"]');
    }

    public function keywords(): array
    {
        return is_null($this->keywordString()) ? [] : (array) \preg_split('/,\s*/', $this->keywordString());
    }

    public function description(): ?string
    {
        return $this->filterFirstContent('//meta[@name="description"]');
    }

    /**
     * Get the meta collected as an array
     */
    public function metaTags(): array
    {
        return [
            'author' => $this->author(),
            'image' => $this->image(),
            'keywords' => $this->keywords(),
            'description' => $this->description(),
        ];
    }

    /**
     * Gets all Twitter-Card attributes (`twitter:`) as an array
     *
     * @return array<string, string>
     */
    public function twitterCard(): array
    {
        $data = $this
            ->filter('//meta[contains(@name, "twitter:")]')
            ->extract(['name', 'content']);

        // Prepare the data
        $result = [];
        foreach ($data as $set) {
            $result[(string) $set[0]] = (string) $set[1];
        }

        return $result;
    }

    /**
     * Gets any OpenGraph attributes (`og:`) as an array
     *
     * @return array<string, string>
     */
    public function openGraph(): array
    {
        $data = $this
            ->filter('//meta[contains(@property, "og:")]')
            ->extract(['property', 'content']);

        // Prepare the data
        $result = [];
        foreach ($data as $set) {
            $result[(string) $set[0]] = (string) $set[1];
        }

        return $result;
    }

    public function h1(): array
    {
        return $this->filterExtractAttributes('//h1', ['_text']);
    }

    public function h2(): array
    {
        return $this->filterExtractAttributes('//h2', ['_text']);
    }

    public function h3(): array
    {
        return $this->filterExtractAttributes('//h3', ['_text']);
    }

    public function h4(): array
    {
        return $this->filterExtractAttributes('//h4', ['_text']);
    }

    public function h5(): array
    {
        return $this->filterExtractAttributes('//h5', ['_text']);
    }

    public function h6(): array
    {
        return $this->filterExtractAttributes('//h6', ['_text']);
    }

    /**
     * Get all heading tags
     *
     * @return array<array>
     */
    public function headings(): array
    {
        return [
            $this->h1(),
            $this->h2(),
            $this->h3(),
            $this->h4(),
            $this->h5(),
            $this->h6(),
        ];
    }

    public function lists(): array
    {
        $lists = [];

        /** @var \DOMElement $list */
        foreach ($this->currentPage->filter('ol, ul') as $list) {
            $lists[] = [
                'type' => $list->tagName,
                'children' => $list->childNodes,
                'children_plain' => array_values(array_filter(array_map('trim', explode("\n", $list->textContent)))),
            ];
        }

        return $lists;
    }

    /**
     * @return array<string>
     **/
    public function orderedLists(): array
    {
        return array_values(array_filter($this->lists(), function ($list) {
            return $list['type'] === 'ol';
        }));
    }

    /**
     * @return array<string>
     **/
    public function unorderedLists(): array
    {
        return array_values(array_filter($this->lists(), function ($list) {
            return $list['type'] === 'ul';
        }));
    }

    /**
     * @return array<string>
     **/
    public function paragraphs(): array
    {
        return array_map(
            'trim',
            $this->filterExtractAttributes('//p', ['_text'])
        );
    }

    /**
     * Get the paragraphs of the page excluding empty paragraphs.
     */
    public function cleanParagraphs(): array
    {
        return array_values(array_filter(
            $this->paragraphs(),
            function ($paragraph) {
                return $paragraph !== '';
            }
        ));
    }

    /**
     * Parses the content outline of the web-page
     *
     * @return array<string>
     */
    public function outline(): array
    {
        $result = $this->filterExtractAttributes('//h1|//h2|//h3|//h4|//h5|//h6', ['_name', '_text']);

        foreach ($result as $index => $array) {
            $result[$index] = array_combine(['tag', 'content'], (array) $array);
        }

        return $result;
    }

    /**
     * Parses the content outline of the web-page
     *
     * @return array<array>
     */
    public function outlineWithParagraphs(): array
    {
        $result = $this->filterExtractAttributes('//h1|//h2|//h3|//h4|//h5|//h6|//p', ['_name', '_text']);

        foreach ($result as $index => $array) {
            $result[$index] = array_combine(['tag', 'content'], (array) $array);
            $result[$index]['content'] = trim($result[$index]['content']);
        }

        return $result;
    }

    /**
     * Parses the content outline of the web-page
     */
    public function cleanOutlineWithParagraphs(bool $onlyContent = false): array
    {
        $elementsNameAndText = $this->filterExtractAttributes('//h1|//h2|//h3|//h4|//h5|//h6|//p', ['_name', '_text']);
        $result = [];

        /** @var array<string> $nameAndText */
        foreach ($elementsNameAndText as $index => $nameAndText) {
            // Element has no text.
            if (empty(trim($nameAndText[1]))) {
                continue;
            }

            if ($onlyContent) {
                $result[$index] = trim($nameAndText[1]);
            } else {
                $result[$index] = [
                    'tag' => $nameAndText[0],
                    'content' => trim($nameAndText[1]),
                ];
            }
        }

        return $result;
    }

    /**
     * Parses the content outline of the web-page as string
     */
    public function cleanOutlineWithParagraphsAsString(bool $onlyContent = false): string
    {
        $elementsNameAndText = $this->filterExtractAttributes('//h1|//h2|//h3|//h4|//h5|//h6|//p', ['_name', '_text']);
        $result = [];

        /** @var array<string> $nameAndText */
        foreach ($elementsNameAndText as $index => $nameAndText) {
            // Element has no text.
            if (empty(trim($nameAndText[1]))) {
                continue;
            }

            if ($onlyContent) {
                $result[$index] = trim($nameAndText[1]);
            } else {
                $result[$index] = [
                    'tag' => $nameAndText[0],
                    'content' => trim($nameAndText[1]),
                ];
            }
        }

        return implode(' ', $result);
    }

    /**
     * Internal method to prepare the content for keyword analysis
     *  done in the called methods for the rake analysis
     *
     * Uses:
     *
     *  - Title
     *  - Headings
     *  - Paragraphs/Content
     *  - Link anchors and Titles
     *  - Alt Texts of Images
     *  - Meta Title, Description and Keywords
     *
     * @see https://github.com/Donatello-za/rake-php-plus
     * @see https://phpscraper.de/examples/extract-keywords.html
     * @see https://github.com/spekulatius/phpscraper-keyword-scraping-example
     *
     * @return array<string>
     */
    protected function prepContent(): array
    {
        // Collect content strings
        $content = array_merge(
            // Website title
            [$this->title()],

            // Paragraphs
            $this->paragraphs(),

            // Various meta tags
            [
                $this->author(),
                $this->description(),
                implode(' ', $this->keywords()),
            ]
        );

        // Add headings
        foreach ($this->headings() as $headings) {
            $content += array_values($headings);
        }

        // Add image alt texts in
        foreach ($this->linksWithDetails() as $link) {
            $content[] = $link['text'];
            $content[] = $link['title'];
        }
        foreach ($this->imagesWithDetails() as $image) {
            $content[] = $image['alt'];
        }

        return $content;
    }

    /**
     * Gets a set of keywords based on the rake approach.
     *
     * Uses:
     *
     *  - Title
     *  - Headings
     *  - Paragraphs/Content
     *  - Link anchors and Titles
     *  - Alt Texts of Images
     *  - Meta Title, Description and Keywords
     *
     * @see https://github.com/Donatello-za/rake-php-plus
     * @see https://phpscraper.de/examples/extract-keywords.html
     * @see https://github.com/spekulatius/phpscraper-keyword-scraping-example
     *
     * @param  string  $locale (default: 'en_US')
     */
    public function contentKeywords($locale = 'en_US'): array
    {
        // Extract the keyword phrases and return a sorted array
        return RakePlus::create(implode(' ', $this->prepContent()), $locale)
            ->sort('asc')
            ->get();
    }

    /**
     * Gets a set of keywords with scores based on the rake approach
     *
     * Uses:
     *
     *  - Title
     *  - Headings
     *  - Paragraphs/Content
     *  - Link anchors and Titles
     *  - Alt Texts of Images
     *  - Meta Title, Description and Keywords
     *
     * @see https://github.com/Donatello-za/rake-php-plus
     * @see https://phpscraper.de/examples/extract-keywords.html
     * @see https://github.com/spekulatius/phpscraper-keyword-scraping-example
     *
     * @param  string  $locale (default: 'en_US')
     */
    public function contentKeywordsWithScores($locale = 'en_US'): array
    {
        // Extract the keyword phrases and return a sorted array
        return RakePlus::create(implode(' ', $this->prepContent()), $locale)
            ->sortByScore('desc')
            ->scores();
    }

    /**
     * Get all links on the page as absolute URLs
     *
     * @see https://github.com/spekulatius/link-scraping-test-beautifulsoup-vs-phpscraper
     */
    public function links(): array
    {
        $links = $this->filter('//a')->links();

        // Generate a list of all image entries
        $result = [];
        foreach ($links as $link) {
            $result[] = $link->getUri();
        }

        return $result;
    }

    /**
     * Get all internal links (same root or sub-domain) on the page as absolute URLs
     */
    public function internalLinks(): array
    {
        // Get the current host - to compare against for internal links
        $currentRootDomain = $this->currentHost();

        // Filter the array
        return array_values(array_filter(
            $this->links(),
            function ($link) use (&$currentRootDomain) {
                $linkRootDomain = Uri::createFromString($link)->getHost();

                return $currentRootDomain === $linkRootDomain;
            }
        ));
    }

    /**
     * Get all external links on the page as absolute URLs
     */
    public function externalLinks(): array
    {
        // Diff the array
        return array_values(array_diff(
            $this->links(),
            $this->internalLinks()
        ));
    }

    /**
     * Get all links on the page with commonly interesting details
     */
    public function linksWithDetails(): array
    {
        /** @var array<\DOMElement> $links */
        $links = $this->filter('//a');

        // Generate a list of all image entries
        $result = [];

        foreach ($links as $link) {
            // Check if the anchor is only an image. If so, wrap it into DomCrawler\Image to get the Uri.
            $image = [];

            /** @var \DOMElement $childNode */
            foreach ($link->childNodes as $childNode) {
                if ($childNode->nodeName === 'img') {
                    $image[] = (new DomCrawlerImage($childNode, $this->currentBaseHost()))->getUri();
                }
            }

            // Collect commonly interesting attributes and URL
            $rel = $link->getAttribute('rel');

            // Generate the proper uri using the Symfony's link class
            $uri = (new DomCrawlerLink($link, $this->currentBaseHost()))->getUri();

            // Prepare the result set.
            $entry = [
                'url' => $uri,
                'protocol' => \strpos($uri, ':') !== false ? explode(':', $uri)[0] : null,
                'text' => trim($link->nodeValue ?? ''),
                'title' => $link->getAttribute('title') === '' ? null : $link->getAttribute('title'),
                'target' => $link->getAttribute('target') === '' ? null : $link->getAttribute('target'),
                'rel' => ($rel === '') ? null : strtolower($rel),
                'image' => $image,
                'isNofollow' => ($rel === '') ? false : (\strpos($rel, 'nofollow') !== false),
                'isUGC' => ($rel === '') ? false : (\strpos($rel, 'ugc') !== false),
                'isSponsored' => ($rel === '') ? false : (\strpos($rel, 'sponsored') !== false),
                'isMe' => ($rel === '') ? false : (\strpos($rel, 'me') !== false),
                'isNoopener' => ($rel === '') ? false : (\strpos($rel, 'noopener') !== false),
                'isNoreferrer' => ($rel === '') ? false : (\strpos($rel, 'noreferrer') !== false),
            ];

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * Get all images on the page with absolute URLs
     */
    public function images(): array
    {
        // Generate a list of all image entries
        $result = [];

        $images = $this->filter('//img')->images();

        /** @var \Symfony\Component\DomCrawler\Image $image */
        foreach ($images as $image) {
            if (
                str_starts_with($image->getNode()->getAttribute('data-src'), 'https://') &&
                str_starts_with($image->getNode()->getAttribute('src'), 'data:image/svg+xml')
            ) {
                $imageURL = $image->getNode()->getAttribute('data-src');
            } else {
                $imageURL = $image->getUri();
            }

            $result[] = $imageURL;
        }

        return $result;
    }

    /**
     * Get all images on the page with commonly interesting details
     */
    public function imagesWithDetails(): array
    {
        // Generate a list of all image entries
        $result = [];

        /** @var array<\DOMElement> $images */
        $images = $this->filter('//img');

        foreach ($images as $image) {
            // Collect the URL and commonly interesting attributes
            if (
                str_starts_with($image->getAttribute('data-src'), 'https://') &&
                str_starts_with($image->getAttribute('src'), 'data:image/svg+xml')
            ) {
                $imageURL = $image->getAttribute('data-src');
            } else {
                $imageURL = (new DomCrawlerImage($image, $this->currentBaseHost()))->getUri();
            }

            $result[] = [
                // Re-generate the proper uri using the Symfony's image class
                'url' => $imageURL,
                'alt' => $image->getAttribute('alt'),
                'width' => $image->getAttribute('width') === '' ? null : $image->getAttribute('width'),
                'height' => $image->getAttribute('height') === '' ? null : $image->getAttribute('height'),
            ];
        }

        return $result;
    }

    /**
     * Get all blockquotes on the page
     */
    public function blockQuotes(): array
    {
        // Generate a list of all blockquote entries
        $result = [];

        /** @var array<\DOMElement> $blockQuotes */
        $blockQuotes = $this->filter('//blockquote');

        foreach ($blockQuotes as $blockQuote) {
            $result[] = $blockQuote->textContent;
        }

        return $result;
    }

    /**
     * Get all video players on the page
     */
    public function videoPlayers(): array
    {
        $result = [];

        $withoutIframe = $this->filter('//video');
        $youtubeIframes = $this->filter('//iframe[contains(@src, "youtube")]');
        $vimeoIframes = $this->filter('//iframe[contains(@src, "vimeo")]');
        $dailyMotion = $this->filter('//iframe[contains(@src, "dailymotion")]');

        /** @var array<Crawler> $allVideoPlayers */
        $allVideoFilters = [$withoutIframe, $youtubeIframes, $vimeoIframes, $dailyMotion];

        foreach ($allVideoFilters as $videoFilter) {
            foreach ($videoFilter as $videoPlayer)
            {
                $result[] = $videoPlayer->getAttribute('src');
            }
        }

        return $result;
    }

    /**
     * Get question marks count on the page
     */
    public function questionMarksCount(): int
    {
        $content = implode(' ', $this->cleanOutlineWithParagraphs(onlyContent: true));
        $segments = explode('?', $content);

        if (! str_ends_with(trim($content), '?')) {
            array_pop($segments);
        }

        return count($segments);
    }

    /**
     * Get most common triplets on the page
     */
    public function mostCommonTriplets(): array
    {
        $content = implode(' ', $this->cleanOutlineWithParagraphs(onlyContent: true));
        $triplets = [];
        $excludedStrings = ['Read More'];

        preg_match_all('/[^.!?]*[.!?]/', $content, $matches);
        $sentences = $matches[0];

        foreach ($sentences as $sentence) {
            foreach ($excludedStrings as $exclude) {
                $sentence = str_replace($exclude, '', $sentence);
            }

            $cleanedSentence = preg_replace("/[\r\n]+/", " ", $sentence);
            $words = preg_split('/\s+/', trim($cleanedSentence));

            for ($i = 0; $i < count($words) - 2; $i++) {
                $triplet = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
                $triplets[$triplet] = ($triplets[$triplet] ?? 0) + 1;
            }
        }

        arsort($triplets);
        $maxCount = reset($triplets);

        $results = [];
        foreach ($triplets as $triplet => $count) {
            if ($count == $maxCount) {
                $results[] = ["triplet" => $triplet, "count" => $count];
            }
        }

        return $results;
    }

    /**
     * Get most common duplets on the page
     */
    public function mostCommonDuplets(): array
    {
        $content = implode(' ', $this->cleanOutlineWithParagraphs(onlyContent: true));
        $duplets = [];
        $excludedStrings = ['Read More'];

        preg_match_all('/[^.!?]*[.!?]/', $content, $matches);
        $sentences = $matches[0];

        foreach ($sentences as $sentence) {
            foreach ($excludedStrings as $exclude) {
                $sentence = str_replace($exclude, '', $sentence);
            }

            $cleanedSentence = preg_replace("/[\r\n]+/", " ", $sentence);
            $words = preg_split('/\s+/', trim($cleanedSentence));

            for ($i = 0; $i < count($words) - 1; $i++) {
                $duplet = $words[$i] . ' ' . $words[$i + 1];
                $duplets[$duplet] = ($duplets[$duplet] ?? 0) + 1;
            }
        }

        arsort($duplets);
        $maxCount = reset($duplets);

        $results = [];
        foreach ($duplets as $duplet => $count) {
            if ($count == $maxCount) {
                $results[] = ["duplet" => $duplet, "count" => $count];
            }
        }

        return $results;
    }
}
