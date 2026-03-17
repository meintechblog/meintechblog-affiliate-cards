<?php

declare(strict_types=1);

final class MTB_Affiliate_Post_Processor {
    private const PARAGRAPH_PATTERN = '/<!-- wp:paragraph\b.*?-->\s*<p>(.*?)<\/p>\s*<!-- \/wp:paragraph -->\s*/su';
    private const TOKEN_PATTERN = '/^(?:amazon:)?([A-Z0-9]{10})$/';
    private const BLOCK_PATTERN = '/<!-- wp:meintechblog\/affiliate-cards\b.*?\/-->\s*/su';
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
        $placeholderInserted = false;

        $contentWithoutBlock = preg_replace_callback(
            self::BLOCK_PATTERN,
            static function (array $matches) use (&$placeholderInserted): string {
                if ($placeholderInserted) {
                    return '';
                }

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

        $block = $this->serialize_block($uniqueAsins);
        $finalContent = str_replace(self::PLACEHOLDER, $block, $processedContent ?? $content);

        return [
            'asins' => $uniqueAsins,
            'content' => $finalContent,
        ];
    }

    private function serialize_block(array $asins): string {
        $items = array_map(
            static fn(string $asin): array => ['asin' => $asin],
            $asins
        );

        if (is_callable($this->itemResolver)) {
            $resolvedItems = ($this->itemResolver)($asins);
            if (is_array($resolvedItems) && $resolvedItems !== []) {
                $items = $resolvedItems;
            }
        }

        $attrs = [
            'items' => $items,
            'badgeMode' => $this->defaults['badgeMode'],
            'ctaLabel' => $this->defaults['ctaLabel'],
            'autoShortenTitles' => (bool) $this->defaults['autoShortenTitles'],
        ];

        return '<!-- wp:meintechblog/affiliate-cards ' . json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ' /-->';
    }
}
