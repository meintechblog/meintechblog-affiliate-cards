<?php

declare(strict_types=1);

final class MTB_Affiliate_Post_Processor {
    private const PARAGRAPH_PATTERN = '/<!-- wp:paragraph\b.*?-->\s*<p>(.*?)<\/p>\s*<!-- \/wp:paragraph -->\s*/su';
    private const TOKEN_PATTERN = '/^(?:amazon:)?([A-Z0-9]{10})$/';
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
        $existingAttrs = [];
        $placeholderInserted = false;

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
            static function (array $matches) use (&$asins, &$placeholderInserted): string {
                $innerText = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (! preg_match(self::TOKEN_PATTERN, $innerText, $tokenMatch)) {
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

        if ($uniqueAsins === []) {
            return [
                'asins' => [],
                'content' => $processedContent ?? $content,
            ];
        }

        $block = $this->serialize_block($uniqueAsins, $existingAttrs);
        $finalContent = str_replace(self::PLACEHOLDER, $block, $processedContent ?? $content);

        return [
            'asins' => $uniqueAsins,
            'content' => $finalContent,
        ];
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
