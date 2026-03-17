<?php

declare(strict_types=1);

final class MTB_Affiliate_Token_Scanner {
    private const PARAGRAPH_PATTERN = '/<!-- wp:paragraph\b.*?-->\s*<p>(.*?)<\/p>\s*<!-- \/wp:paragraph -->\s*/su';
    private const TOKEN_PATTERN = '/^(?:amazon:)?([A-Z0-9]{10})$/';

    public function scan(string $content): array {
        $asins = [];

        $updatedContent = preg_replace_callback(
            self::PARAGRAPH_PATTERN,
            static function (array $matches) use (&$asins): string {
                $innerText = trim(strip_tags(html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
                if (! preg_match(self::TOKEN_PATTERN, $innerText, $tokenMatch)) {
                    return $matches[0];
                }

                $asins[] = $tokenMatch[1];
                return '';
            },
            $content
        );

        return [
            'asins' => array_values(array_unique($asins)),
            'content' => $updatedContent ?? $content,
        ];
    }
}
