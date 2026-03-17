<?php

declare(strict_types=1);

final class MTB_Affiliate_Post_Processor {
    private const PARAGRAPH_PATTERN = '/<!-- wp:paragraph\b.*?-->\s*<p>(.*?)<\/p>\s*<!-- \/wp:paragraph -->\s*/su';
    private const TOKEN_PATTERN = '/^(?:amazon:)?([A-Z0-9]{10})$/';
    private const INLINE_MARKER_PATTERN = '/\bamazon:([A-Z0-9]{10})\b/i';
    private const INLINE_LINK_PATTERN = '/href=(["\'])https?:\/\/(?:www\.)?amazon\.[^"\']+?\/dp\/([A-Z0-9]{10})(?:[?\/"\']|$)/i';
    private const BLOCK_PATTERN = '/<!-- wp:meintechblog\/affiliate-cards(?:\s+({.*?}))?\s*\/-->\s*/su';
    private const PLACEHOLDER = '__MTB_AFFILIATE_BLOCK__';

    private MTB_Affiliate_Token_Scanner $scanner;
    private array $defaults;
    /** @var null|callable */
    private $itemResolver;

    public function __construct(?MTB_Affiliate_Token_Scanner $scanner = null, array $defaults = [], ?callable $itemResolver = null) {
        $this->scanner = $scanner ?? new MTB_Affiliate_Token_Scanner();
        $this->defaults = array_merge([
            'badgeMode' => 'auto',
            'ctaLabel' => 'Preis auf Amazon checken',
            'autoShortenTitles' => true,
        ], $defaults);
        $this->itemResolver = $itemResolver;
    }

    public function process(string $content): array {
        $asins = [];
        $inlineAsins = [];
        $inlinePlaceholders = [];
        $existingAttrs = [];
        $placeholderInserted = false;
        $inlinePlaceholderIndex = 0;

        $contentWithoutBlock = preg_replace_callback(
            self::BLOCK_PATTERN,
            function (array $matches) use (&$placeholderInserted, &$existingAttrs): string {
                if ($placeholderInserted) {
                    return '';
                }

                $existingAttrs = $this->decode_attrs($matches[1] ?? '');
                $placeholderInserted = true;
                return self::PLACEHOLDER . "\n\n";
            },
            $content
        );

        $processedContent = preg_replace_callback(
            self::PARAGRAPH_PATTERN,
            static function (array $matches) use (&$asins, &$inlineAsins, &$inlinePlaceholders, &$placeholderInserted, &$inlinePlaceholderIndex): string {
                $innerText = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (! preg_match(self::TOKEN_PATTERN, $innerText, $tokenMatch)) {
                    $foundInlineAsins = self::extract_inline_asins($matches[1]);
                    if ($foundInlineAsins !== []) {
                        $asins = array_merge($asins, $foundInlineAsins);
                        $inlineAsins = array_merge($inlineAsins, $foundInlineAsins);
                        $placeholders = [];
                        foreach ($foundInlineAsins as $asin) {
                            $placeholder = self::build_inline_placeholder(++$inlinePlaceholderIndex, $asin, $matches[0]);
                            $inlinePlaceholders[$placeholder] = $asin;
                            $placeholders[] = $placeholder;
                        }
                        return $matches[0] . "\n\n" . implode("\n\n", $placeholders) . "\n\n";
                    }
                    return $matches[0];
                }

                $asins[] = $tokenMatch[1];

                if (! $placeholderInserted) {
                    $placeholderInserted = true;
                    return self::PLACEHOLDER . "\n\n";
                }

                return '';
            },
            $contentWithoutBlock ?? $content
        );

        $uniqueAsins = array_values(array_unique($asins));
        $uniqueInlineAsins = array_values(array_unique($inlineAsins));

        if ($uniqueAsins === []) {
            return [
                'asins' => [],
                'content' => $processedContent ?? $content,
            ];
        }

        if ($uniqueInlineAsins !== []) {
            $inlineMap = $this->resolve_inline_items($uniqueInlineAsins, $existingAttrs);
            if ($inlineMap !== []) {
                $processedContent = $this->replace_inline_markers(
                    $processedContent ?? $content,
                    $inlineMap
                );
            }

            if ($inlinePlaceholders !== []) {
                foreach ($inlinePlaceholders as $placeholder => $asin) {
                    $item = $inlineMap[$asin] ?? ['asin' => $asin];
                    $processedContent = str_replace(
                        $placeholder,
                        $this->serialize_single_block($item),
                        $processedContent ?? $content
                    );
                }
            }
        }

        $block = $this->serialize_block($uniqueAsins, $existingAttrs);
        $finalContent = str_replace(self::PLACEHOLDER, $block, $processedContent ?? $content);
        $finalContent = $this->collapse_adjacent_affiliate_blocks($finalContent);

        return [
            'asins' => $uniqueAsins,
            'content' => $finalContent,
        ];
    }

    private static function extract_inline_asins(string $html): array {
        $decoded = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $found = [];

        $fragments = preg_split('/(<[^>]+>)/u', $decoded, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (is_array($fragments)) {
            $tagStack = [];
            foreach ($fragments as $fragment) {
                if ($fragment === '') {
                    continue;
                }

                if ($fragment[0] === '<') {
                    if (preg_match('/^<\s*\/\s*([a-z0-9:-]+)/i', $fragment, $closingTag)) {
                        $tagName = strtolower((string) $closingTag[1]);
                        $position = array_search($tagName, $tagStack, true);
                        if ($position !== false) {
                            array_splice($tagStack, $position, 1);
                        }
                    } elseif (
                        preg_match('/^<\s*([a-z0-9:-]+)/i', $fragment, $openingTag)
                        && ! preg_match('/\/\s*>$/', $fragment)
                    ) {
                        $tagStack[] = strtolower((string) $openingTag[1]);
                    }
                    continue;
                }

                if (array_intersect($tagStack, ['a', 'code']) !== []) {
                    continue;
                }

                if (preg_match_all(self::INLINE_MARKER_PATTERN, $fragment, $matches)) {
                    foreach ($matches[1] as $asin) {
                        $found[] = strtoupper((string) $asin);
                    }
                }
            }
        }

        if (preg_match_all(self::INLINE_LINK_PATTERN, $decoded, $matches)) {
            foreach ($matches[2] as $asin) {
                $found[] = strtoupper($asin);
            }
        }

        if ($found === []) {
            return [];
        }

        return array_values(array_unique($found));
    }

    private function serialize_block(array $asins, array $existingAttrs = []): string {
        $existingItems = $this->sanitize_items($existingAttrs['items'] ?? []);
        $itemsByAsin = [];
        $orderedItems = [];

        foreach ($existingItems as $item) {
            $asin = (string) $item['asin'];
            if (isset($itemsByAsin[$asin])) {
                continue;
            }

            $itemsByAsin[$asin] = $item;
            $orderedItems[] = $item;
        }

        $newAsins = array_values(array_filter(
            $asins,
            static fn(string $asin): bool => ! isset($itemsByAsin[$asin])
        ));

        $resolvedItems = array_map(
            static fn(string $asin): array => ['asin' => $asin],
            $newAsins
        );

        if ($newAsins !== [] && is_callable($this->itemResolver)) {
            $resolverResult = ($this->itemResolver)($newAsins);
            if (is_array($resolverResult) && $resolverResult !== []) {
                $resolvedItems = $resolverResult;
            }
        }

        foreach ($this->sanitize_items($resolvedItems) as $item) {
            $asin = (string) $item['asin'];
            if (isset($itemsByAsin[$asin])) {
                continue;
            }

            $itemsByAsin[$asin] = $item;
            $orderedItems[] = $item;
        }

        $attrs = [
            'items' => $orderedItems,
            'badgeMode' => $existingAttrs['badgeMode'] ?? $this->defaults['badgeMode'],
            'ctaLabel' => $existingAttrs['ctaLabel'] ?? $this->defaults['ctaLabel'],
            'autoShortenTitles' => array_key_exists('autoShortenTitles', $existingAttrs)
                ? (bool) $existingAttrs['autoShortenTitles']
                : (bool) $this->defaults['autoShortenTitles'],
        ];

        return '<!-- wp:meintechblog/affiliate-cards ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
    }

    private function serialize_single_block(array $item): string {
        $attrs = [
            'items' => $this->sanitize_items([$item]),
            'badgeMode' => $this->defaults['badgeMode'],
            'ctaLabel' => $this->defaults['ctaLabel'],
            'autoShortenTitles' => (bool) $this->defaults['autoShortenTitles'],
        ];

        return '<!-- wp:meintechblog/affiliate-cards ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
    }

    private function collapse_adjacent_affiliate_blocks(string $content): string {
        return preg_replace_callback(
            '/(?:(?:<!-- wp:meintechblog\/affiliate-cards(?:\s+{.*?})?\s*\/-->\s*){2,})/su',
            function (array $matches): string {
                if (! preg_match_all(self::BLOCK_PATTERN, $matches[0], $blockMatches, PREG_SET_ORDER)) {
                    return $matches[0];
                }

                $kept = [];
                $seen = [];

                foreach ($blockMatches as $index => $blockMatch) {
                    $blockMarkup = $blockMatch[0];
                    $attrs = $this->decode_attrs($blockMatch[1] ?? '');
                    $items = $this->sanitize_items($attrs['items'] ?? []);
                    $asinKey = count($items) === 1 ? (string) ($items[0]['asin'] ?? '') : '';
                    $dedupeKey = $asinKey !== '' ? 'asin:' . $asinKey : 'block:' . $index;

                    if (isset($seen[$dedupeKey])) {
                        continue;
                    }

                    $seen[$dedupeKey] = true;
                    $kept[] = $blockMarkup;
                }

                return implode('', $kept);
            },
            $content
        ) ?? $content;
    }

    private static function build_inline_placeholder(int $index, string $asin, string $paragraphMarkup): string {
        return sprintf(
            '<!-- MTB_AFFILIATE_INLINE_BLOCK:%d:%s:%s -->',
            $index,
            strtoupper($asin),
            substr(md5($paragraphMarkup), 0, 12)
        );
    }

    private function resolve_inline_items(array $asins, array $existingAttrs): array {
        $map = [];

        $existingItems = $this->sanitize_items($existingAttrs['items'] ?? []);
        foreach ($existingItems as $item) {
            $asin = (string) $item['asin'];
            if ($asin === '') {
                continue;
            }
            $map[$asin] = $item;
        }

        if ($asins !== [] && is_callable($this->itemResolver)) {
            $resolverResult = ($this->itemResolver)($asins);
            if (is_array($resolverResult)) {
                foreach ($this->sanitize_items($resolverResult) as $item) {
                    $asin = (string) $item['asin'];
                    if ($asin !== '') {
                        $map[$asin] = $item;
                    }
                }
            }
        }

        return $map;
    }

    private function replace_inline_markers(string $content, array $resolvedItems): string {
        return preg_replace_callback(
            self::PARAGRAPH_PATTERN,
            static function (array $matches) use ($resolvedItems): string {
                $innerHtml = $matches[1];
                $fragments = preg_split('/(<[^>]+>)/u', $innerHtml, -1, PREG_SPLIT_DELIM_CAPTURE);
                if (! is_array($fragments)) {
                    return $matches[0];
                }

                $tagStack = [];
                foreach ($fragments as $index => $fragment) {
                    if ($fragment === '') {
                        continue;
                    }

                    if ($fragment[0] === '<') {
                        if (preg_match('/^<\s*\/\s*([a-z0-9:-]+)/i', $fragment, $closingTag)) {
                            $tagName = strtolower((string) $closingTag[1]);
                            $position = array_search($tagName, $tagStack, true);
                            if ($position !== false) {
                                array_splice($tagStack, $position, 1);
                            }
                        } elseif (
                            preg_match('/^<\s*([a-z0-9:-]+)/i', $fragment, $openingTag)
                            && ! preg_match('/\/\s*>$/', $fragment)
                        ) {
                            $tagStack[] = strtolower((string) $openingTag[1]);
                        }
                        continue;
                    }

                    if (array_intersect($tagStack, ['a', 'code']) !== []) {
                        continue;
                    }

                    $replaced = preg_replace_callback(
                        self::INLINE_MARKER_PATTERN,
                        static function (array $token) use ($resolvedItems): string {
                            $asin = strtoupper((string) $token[1]);
                            if (! isset($resolvedItems[$asin])) {
                                return $token[0];
                            }
                            $item = $resolvedItems[$asin];
                            $title = trim((string) ($item['title'] ?? ''));
                            $detailUrl = trim((string) ($item['detail_url'] ?? ''));
                            if ($title === '' || $detailUrl === '') {
                                return $token[0];
                            }
                            $linkText = htmlspecialchars($title . ' (Affiliate-Link)', ENT_QUOTES, 'UTF-8');
                            $href = htmlspecialchars($detailUrl, ENT_QUOTES, 'UTF-8');
                            return '<a href="' . $href . '" target="_blank" rel="nofollow noopener sponsored">' . $linkText . '</a>';
                        },
                        $fragment
                    );

                    if ($replaced !== null) {
                        $fragments[$index] = $replaced;
                    }
                }

                return str_replace($innerHtml, implode('', $fragments), $matches[0]);
            },
            $content
        ) ?? $content;
    }

    private function decode_attrs(string $json): array {
        if ($json === '') {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            return is_array($decoded) ? $decoded : [];
        } catch (Throwable $exception) {
            return [];
        }
    }

    private function sanitize_items($items): array {
        if (! is_array($items)) {
            return [];
        }

        $sanitized = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $asin = trim((string) ($item['asin'] ?? ''));
            if ($asin === '') {
                continue;
            }

            $item['asin'] = $asin;
            $sanitized[] = $item;
        }

        return $sanitized;
    }
}
