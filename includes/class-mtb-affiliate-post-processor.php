<?php

declare(strict_types=1);

final class MTB_Affiliate_Post_Processor {
    private const PARAGRAPH_PATTERN = '/(?:<!-- wp:paragraph\b.*?-->\s*)?<p>(.*?)<\/p>(?:\s*<!-- \/wp:paragraph -->)?\s*/su';
    private const MORE_PATTERN = '/(?:<!-- wp:more\b.*?-->.*?<!-- \/wp:more -->|<!--more-->)/su';
    private const TOKEN_PATTERN = '/^(?:amazon:)?([A-Z0-9]{10})$/';
    private const INLINE_MARKER_PATTERN = '/\bamazon:([A-Z0-9]{10})\b/i';
    private const INLINE_LINK_PATTERN = '/href=(["\'])https?:\/\/(?:www\.)?amazon\.[^"\']+?\/dp\/([A-Z0-9]{10})(?:[?\/"\']|$)/i';
    private const INLINE_LINK_CAPTURE_PATTERN = '/<a\b[^>]*href=(["\'])(https?:\/\/(?:www\.)?amazon\.[^"\']+?\/dp\/([A-Z0-9]{10})[^"\']*)\1[^>]*>(.*?)<\/a>/isu';
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
        $standaloneAsins = [];
        $inlineAsins = [];
        $inlinePlaceholders = [];
        $inlineCardAsins = [];
        $inlineFallbackItems = [];
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
            static function (array $matches) use (&$asins, &$standaloneAsins, &$inlineAsins, &$inlinePlaceholders, &$inlineCardAsins, &$inlineFallbackItems, &$placeholderInserted, &$inlinePlaceholderIndex): string {
                $innerText = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (! preg_match(self::TOKEN_PATTERN, $innerText, $tokenMatch)) {
                    $foundInlineAsins = self::extract_inline_asins($matches[1]);
                    if ($foundInlineAsins !== []) {
                        $asins = array_merge($asins, $foundInlineAsins);
                        $inlineAsins = array_merge($inlineAsins, $foundInlineAsins);
                        foreach (self::extract_inline_fallback_items($matches[1]) as $asin => $fallbackItem) {
                            if (! isset($inlineFallbackItems[$asin])) {
                                $inlineFallbackItems[$asin] = $fallbackItem;
                            }
                        }
                        $placeholders = [];
                        foreach ($foundInlineAsins as $asin) {
                            if (isset($inlineCardAsins[$asin])) {
                                continue;
                            }
                            $inlineCardAsins[$asin] = true;
                            $placeholder = self::build_inline_placeholder(++$inlinePlaceholderIndex, $asin, $matches[0]);
                            $inlinePlaceholders[$placeholder] = $asin;
                            $placeholders[] = $placeholder;
                        }
                        if ($placeholders === []) {
                            return $matches[0];
                        }
                        return $matches[0] . "\n\n" . implode("\n\n", $placeholders) . "\n\n";
                    }
                    return $matches[0];
                }

                $asins[] = $tokenMatch[1];
                $standaloneAsins[] = $tokenMatch[1];

                if (! $placeholderInserted) {
                    $placeholderInserted = true;
                    return self::PLACEHOLDER . "\n\n";
                }

                return '';
            },
            $contentWithoutBlock ?? $content
        );

        $uniqueAsins = array_values(array_unique($asins));
        $uniqueStandaloneAsins = array_values(array_unique($standaloneAsins));
        $uniqueInlineAsins = array_values(array_unique($inlineAsins));

        if ($uniqueAsins === []) {
            return [
                'asins' => [],
                'content' => $processedContent ?? $content,
            ];
        }

        if ($uniqueInlineAsins !== []) {
            $inlineMap = $this->resolve_inline_items($uniqueInlineAsins, $existingAttrs, $inlineFallbackItems);
            if ($inlineMap !== []) {
                $processedContent = $this->replace_inline_markers(
                    $processedContent ?? $content,
                    $inlineMap
                );
            }

            $processedContent = $this->relocate_intro_inline_placeholders(
                $processedContent ?? $content,
                array_keys($inlinePlaceholders)
            );

            if ($inlinePlaceholders !== []) {
                foreach ($inlinePlaceholders as $placeholder => $asin) {
                    $item = $inlineMap[$asin] ?? null;
                    $processedContent = str_replace(
                        $placeholder,
                        is_array($item) ? $this->serialize_single_block($item) : '',
                        $processedContent ?? $content
                    );
                }
            }
        }

        $block = $uniqueStandaloneAsins !== []
            ? $this->serialize_block($uniqueStandaloneAsins, $existingAttrs)
            : '';
        $finalContent = str_replace(self::PLACEHOLDER, $block, $processedContent ?? $content);
        $finalContent = $this->collapse_adjacent_affiliate_blocks($finalContent);

        return [
            'asins' => $uniqueAsins,
            'content' => $finalContent,
        ];
    }

    private function relocate_intro_inline_placeholders(string $content, array $placeholders): string {
        if ($content === '' || $placeholders === []) {
            return $content;
        }

        if (! preg_match(self::MORE_PATTERN, $content, $moreMatch, PREG_OFFSET_CAPTURE)) {
            return $content;
        }

        $moreMarkup = (string) ($moreMatch[0][0] ?? '');
        $moreOffset = (int) ($moreMatch[0][1] ?? -1);
        if ($moreMarkup === '' || $moreOffset < 0) {
            return $content;
        }

        $beforeMore = substr($content, 0, $moreOffset);
        $afterMore = substr($content, $moreOffset + strlen($moreMarkup));
        if ($beforeMore === false || $afterMore === false) {
            return $content;
        }

        $moved = [];
        foreach ($placeholders as $placeholder) {
            if (! is_string($placeholder) || $placeholder === '' || strpos($beforeMore, $placeholder) === false) {
                continue;
            }

            $moved[] = $placeholder;
            $beforeMore = str_replace($placeholder, '', $beforeMore);
        }

        if ($moved === []) {
            return $content;
        }

        $beforeMore = preg_replace("/\n{3,}/", "\n\n", $beforeMore) ?? $beforeMore;
        $insertion = implode("\n\n", $moved);

        return rtrim($beforeMore) . "\n\n" . $moreMarkup . "\n\n" . $insertion . ltrim($afterMore);
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
                    if (preg_match('/^<\s*a\b/i', $fragment) && preg_match(self::INLINE_LINK_PATTERN, $fragment, $linkMatch)) {
                        $found[] = strtoupper((string) $linkMatch[2]);
                    }

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

        if ($found === []) {
            return [];
        }

        return array_values(array_unique($found));
    }

    private static function extract_inline_fallback_items(string $html): array {
        if (! preg_match_all(self::INLINE_LINK_CAPTURE_PATTERN, $html, $matches, PREG_SET_ORDER)) {
            return [];
        }

        $items = [];
        foreach ($matches as $match) {
            $asin = strtoupper(trim((string) ($match[3] ?? '')));
            $detailUrl = html_entity_decode((string) ($match[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $title = trim(strip_tags(html_entity_decode((string) ($match[4] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
            $title = preg_replace('/\s*\(Affiliate-Link\)\s*$/iu', '', $title) ?? $title;
            if ($asin === '' || $detailUrl === '' || $title === '' || isset($items[$asin])) {
                continue;
            }

            $items[$asin] = [
                'asin' => $asin,
                'title' => $title,
                'detail_url' => $detailUrl,
                'image_url' => '',
                'images' => [],
                'benefit' => '',
            ];
        }

        return $items;
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
        if (! preg_match_all(self::BLOCK_PATTERN, $content, $blockMatches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return $content;
        }

        $result = '';
        $cursor = 0;
        $pendingGroup = [];

        foreach ($blockMatches as $index => $blockMatch) {
            $blockMarkup = (string) ($blockMatch[0][0] ?? '');
            $blockOffset = (int) ($blockMatch[0][1] ?? -1);
            if ($blockMarkup === '' || $blockOffset < 0) {
                continue;
            }

            $between = substr($content, $cursor, $blockOffset - $cursor);
            if ($between === false) {
                $between = '';
            }

            if ($pendingGroup === []) {
                $result .= $between;
                $pendingGroup[] = $this->build_collapsible_block_entry($blockMatch, $blockMarkup, $index);
            } elseif (trim($between) === '') {
                $pendingGroup[] = $this->build_collapsible_block_entry($blockMatch, $blockMarkup, $index);
            } else {
                $result .= $this->dedupe_collapsible_block_group($pendingGroup);
                $result .= $between;
                $pendingGroup = [$this->build_collapsible_block_entry($blockMatch, $blockMarkup, $index)];
            }

            $cursor = $blockOffset + strlen($blockMarkup);
        }

        if ($pendingGroup !== []) {
            $result .= $this->dedupe_collapsible_block_group($pendingGroup);
        }

        $tail = substr($content, $cursor);
        if ($tail !== false) {
            $result .= $tail;
        }

        return $result;
    }

    private function build_collapsible_block_entry(array $blockMatch, string $blockMarkup, int $index): array {
        $attrs = $this->decode_attrs($blockMatch[1][0] ?? $blockMatch[1] ?? '');
        $items = $this->sanitize_items($attrs['items'] ?? []);
        $asinKey = count($items) === 1 ? (string) ($items[0]['asin'] ?? '') : '';

        return [
            'markup' => $blockMarkup,
            'key' => $asinKey !== '' ? 'asin:' . $asinKey : 'block:' . $index,
        ];
    }

    private function dedupe_collapsible_block_group(array $group): string {
        $kept = [];
        $seen = [];

        foreach ($group as $entry) {
            $dedupeKey = (string) ($entry['key'] ?? '');
            if ($dedupeKey === '') {
                $dedupeKey = 'block:' . count($kept);
            }

            if (isset($seen[$dedupeKey])) {
                continue;
            }

            $seen[$dedupeKey] = true;
            $kept[] = (string) ($entry['markup'] ?? '');
        }

        return implode('', $kept);
    }

    private static function build_inline_placeholder(int $index, string $asin, string $paragraphMarkup): string {
        return sprintf(
            '<!-- MTB_AFFILIATE_INLINE_BLOCK:%d:%s:%s -->',
            $index,
            strtoupper($asin),
            substr(md5($paragraphMarkup), 0, 12)
        );
    }

    private function resolve_inline_items(array $asins, array $existingAttrs, array $fallbackItems = []): array {
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
                        $map[$asin] = $this->merge_inline_item_with_fallback(
                            $item,
                            $fallbackItems[$asin] ?? null
                        );
                    }
                }
            }
        }

        foreach ($fallbackItems as $asin => $fallbackItem) {
            if (! array_key_exists($asin, $map)) {
                $map[$asin] = $fallbackItem;
                continue;
            }

            if ($map[$asin] === null) {
                unset($map[$asin]);
            }
        }

        return $map;
    }

    private function merge_inline_item_with_fallback(array $resolvedItem, ?array $fallbackItem): ?array {
        if (! is_array($fallbackItem) || $fallbackItem === []) {
            return $resolvedItem;
        }

        $resolvedTitle = trim((string) ($resolvedItem['title'] ?? ''));
        $fallbackTitle = trim((string) ($fallbackItem['title'] ?? ''));
        if ($resolvedTitle === '' || $fallbackTitle === '') {
            return $resolvedItem;
        }

        if ($this->titles_hard_mismatch($fallbackTitle, $resolvedTitle)) {
            return null;
        }

        $merged = $resolvedItem;
        $merged['title'] = $fallbackTitle;

        return $merged;
    }

    private function titles_hard_mismatch(string $fallbackTitle, string $resolvedTitle): bool {
        $fallbackTokens = $this->title_tokens($fallbackTitle);
        $resolvedTokens = $this->title_tokens($resolvedTitle);

        if ($fallbackTokens === [] || $resolvedTokens === []) {
            return false;
        }

        foreach ($fallbackTokens as $fallbackToken) {
            foreach ($resolvedTokens as $resolvedToken) {
                if ($fallbackToken === $resolvedToken) {
                    return false;
                }

                $shorterLength = min(strlen($fallbackToken), strlen($resolvedToken));
                if (
                    $shorterLength >= 6
                    && (str_contains($fallbackToken, $resolvedToken) || str_contains($resolvedToken, $fallbackToken))
                ) {
                    return false;
                }
            }
        }

        return true;
    }

    private function title_tokens(string $title): array {
        $genericTokens = [
            'affiliate',
            'affiliatelink',
            'amazon',
            'artikel',
            'card',
            'info',
            'link',
            'links',
            'mehr',
            'preis',
            'produkt',
        ];
        $normalized = html_entity_decode($title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $normalized = preg_replace('/\s*\(Affiliate-Link\)\s*$/iu', '', $normalized) ?? $normalized;
        $normalized = mb_strtolower($normalized, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $normalized) ?? $normalized;
        $parts = preg_split('/\s+/u', trim($normalized)) ?: [];

        $tokens = [];
        foreach ($parts as $part) {
            if ($part === '') {
                continue;
            }

            $length = mb_strlen($part, 'UTF-8');
            if ($length < 4 && ! preg_match('/\d/u', $part)) {
                continue;
            }

            if (preg_match('/^\d+$/u', $part)) {
                continue;
            }

            if (in_array($part, $genericTokens, true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return array_values(array_unique($tokens));
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
