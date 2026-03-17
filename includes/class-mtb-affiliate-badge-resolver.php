<?php

declare(strict_types=1);

final class MTB_Affiliate_Badge_Resolver {
    public function resolve(string $postContent, string $mode = 'auto'): string {
        if ($mode === 'video') {
            return 'Im Video verwendet';
        }

        if ($mode === 'setup') {
            return 'Passend zu diesem Setup';
        }

        $haystack = strtolower($postContent);
        $markers = [
            '"providernameslug":"youtube"',
            'youtube.com/watch',
            'youtube-nocookie.com',
            'wp-block-embed-youtube',
        ];

        foreach ($markers as $marker) {
            if (str_contains($haystack, $marker)) {
                return 'Im Video verwendet';
            }
        }

        return 'Passend zu diesem Setup';
    }
}
