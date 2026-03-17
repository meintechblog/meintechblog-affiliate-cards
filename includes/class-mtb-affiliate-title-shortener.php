<?php

declare(strict_types=1);

final class MTB_Affiliate_Title_Shortener {
    private const TITLE_OVERRIDES = [
        'B0CK3L9WD3' => 'Raspberry Pi 5 4GB',
        'B08JC5DH9Q' => 'GIGASTONE MLC 8GB Industrial MicroSDXC Karte',
        'B0DF2KFDC8' => 'USB-C Tester Messgerät',
        'B0CLTV6YB2' => 'Miuzei Metallgehäuse für Raspberry Pi 5',
        'B0D7955R6N' => 'Waveshare Industrial Gigabit PoE Splitter',
    ];

    public function shorten(string $asin, string $apiTitle, string $sourceLabel = '', ?string $override = null): string {
        if ($override !== null && trim($override) !== '') {
            return trim($override);
        }

        if (isset(self::TITLE_OVERRIDES[$asin])) {
            return self::TITLE_OVERRIDES[$asin];
        }

        $candidate = $sourceLabel !== '' ? $sourceLabel : $apiTitle;
        $candidate = html_entity_decode($candidate, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $candidate = preg_replace('/\[[^\]]+\]/u', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\([^)]*\)/u', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\b(with|mit|adapter|onboard|protection|quiet case)\b.*$/iu', '', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+/u', ' ', $candidate) ?? $candidate;
        $candidate = trim($candidate, " ,:-");

        if (mb_strlen($candidate) <= 55) {
            return $candidate;
        }

        $short = mb_substr($candidate, 0, 55);
        $lastSpace = mb_strrpos($short, ' ');
        if ($lastSpace !== false) {
            $short = mb_substr($short, 0, $lastSpace);
        }

        return trim($short, " ,:-");
    }
}
