<?php

declare(strict_types=1);

final class MTB_Affiliate_Token_Prepass {
    private MTB_Affiliate_Product_Library $library;

    /** Shorthand keywords (lowercase) -- NOT 10-char ASIN patterns */
    private const SHORTHAND_KEYWORDS = ['last', 'heute', 'today', 'gestern', 'yesterday'];

    /** Pattern matches paragraphs (same format as Post Processor) */
    private const PARAGRAPH_PATTERN = '/(?:<!-- wp:paragraph\b.*?-->\s*)?<p>(.*?)<\/p>(?:\s*<!-- \/wp:paragraph -->)?\s*/su';

    /** Matches standalone amazon:keyword where keyword is a shorthand (NOT a 10-char ASIN) */
    private const SHORTHAND_TOKEN_PATTERN = '/^amazon:(last|heute|today|gestern|yesterday)$/i';

    /** Matches inline amazon:keyword within surrounding text */
    private const INLINE_SHORTHAND_PATTERN = '/\bamazon:(last|heute|today|gestern|yesterday)\b/i';

    public function __construct(MTB_Affiliate_Product_Library $library) {
        $this->library = $library;
    }

    /**
     * Resolve shorthand tokens in content to concrete amazon:ASIN paragraphs.
     *
     * Non-shorthand tokens (e.g. amazon:B0D7955R6N) pass through unchanged.
     * Content with no shorthand tokens passes through unchanged.
     *
     * @param string $content Gutenberg post content.
     * @return string Content with shorthand tokens replaced by amazon:ASIN paragraphs.
     */
    public function resolve(string $content): string {
        // Fast path: no shorthand keyword present at all.
        $lowerContent = strtolower($content);
        $hasShorthand = false;
        foreach (self::SHORTHAND_KEYWORDS as $keyword) {
            if (strpos($lowerContent, 'amazon:' . $keyword) !== false) {
                $hasShorthand = true;
                break;
            }
        }

        if (! $hasShorthand) {
            return $content;
        }

        $appended = '';

        $result = preg_replace_callback(
            self::PARAGRAPH_PATTERN,
            function (array $matches) use (&$appended): string {
                $fullMatch = $matches[0];
                $innerText = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));

                // Check for standalone shorthand token.
                if (preg_match(self::SHORTHAND_TOKEN_PATTERN, $innerText, $tokenMatch)) {
                    $keyword = strtolower($tokenMatch[1]);
                    $asins = $this->resolve_keyword($keyword);

                    if ($asins === []) {
                        return '';
                    }

                    return implode('', array_map([$this, 'wrap_paragraph'], $asins));
                }

                // Check for inline shorthand token within paragraph text.
                if (preg_match(self::INLINE_SHORTHAND_PATTERN, $matches[1], $inlineMatch)) {
                    $keyword = strtolower($inlineMatch[1]);
                    $asins = $this->resolve_keyword($keyword);

                    if ($asins === []) {
                        // Remove the inline token text, keep the rest of the paragraph.
                        $replaced = preg_replace(self::INLINE_SHORTHAND_PATTERN, '', $matches[1]) ?? $matches[1];
                        return $this->rebuild_paragraph($fullMatch, $replaced);
                    }

                    // Substitute first ASIN inline, append remaining as separate paragraphs.
                    $firstAsin = array_shift($asins);
                    $replaced = preg_replace(
                        self::INLINE_SHORTHAND_PATTERN,
                        'amazon:' . $firstAsin,
                        $matches[1],
                        1
                    ) ?? $matches[1];

                    $extra = implode('', array_map([$this, 'wrap_paragraph'], $asins));
                    $appended .= $extra;

                    return $this->rebuild_paragraph($fullMatch, $replaced) . $extra;
                }

                return $fullMatch;
            },
            $content
        );

        return $result ?? $content;
    }

    /**
     * Resolve a shorthand keyword to an array of ASINs.
     *
     * @param string $keyword Lowercase shorthand keyword.
     * @return string[] Array of ASIN strings (may be empty).
     */
    private function resolve_keyword(string $keyword): array {
        $products = match ($keyword) {
            'last'                   => array_filter([$this->library->get_last(1)]),
            'heute', 'today'         => $this->library->get_products_today(),
            'gestern', 'yesterday'   => $this->library->get_products_yesterday(),
            default                  => [],
        };

        return array_values(array_map(
            static fn(array $p): string => (string) $p['asin'],
            array_values($products)
        ));
    }

    /**
     * Wrap an ASIN in a Gutenberg wp:paragraph block.
     */
    private function wrap_paragraph(string $asin): string {
        return '<!-- wp:paragraph --><p>amazon:' . $asin . '</p><!-- /wp:paragraph -->';
    }

    /**
     * Rebuild the outer paragraph wrapper from the original full match, replacing inner HTML.
     */
    private function rebuild_paragraph(string $fullMatch, string $newInner): string {
        // Preserve the original opening/closing wp:paragraph comments if present.
        $hasOpenComment  = (bool) preg_match('/^<!-- wp:paragraph\b.*?-->/su', $fullMatch);
        $hasCloseComment = (bool) preg_match('/<!-- \/wp:paragraph -->/su', $fullMatch);

        if ($hasOpenComment && $hasCloseComment) {
            return '<!-- wp:paragraph --><p>' . $newInner . '</p><!-- /wp:paragraph -->';
        }

        return '<p>' . $newInner . '</p>';
    }
}
