<?php

declare(strict_types=1);

final class MTB_Affiliate_Audit_Service {
    private const INLINE_MARKER_PATTERN = '/\bamazon:([A-Z0-9]{10})\b/i';
    private const AMAZON_LINK_PATTERN = '/https?:\/\/(?:www\.)?amazon\.[^\s"\']+?\/dp\/([A-Z0-9]{10})(?:[?\/"\']|$)[^\s"\']*/i';
    private const AFFILIATE_CARD_BLOCK_PATTERN = '/<!-- wp:meintechblog\/affiliate-cards(?:\s+({.*?}))?\s*\/-->/su';

    public function default_state(): array {
        return [
            'status' => 'offen',
            'counts' => [
                'affiliate_finds' => 0,
                'card_blocks' => 0,
            ],
            'asins' => [],
            'tracking' => '',
            'short_log' => '',
            'timestamps' => [
                'checked_at' => '',
                'straightened_at' => '',
            ],
        ];
    }

    public function build_short_log(array $state): string {
        $finds = (int) ($state['counts']['affiliate_finds'] ?? 0);
        $cards = (int) ($state['counts']['card_blocks'] ?? 0);
        $tracking = trim((string) ($state['tracking'] ?? ''));

        if ($finds === 0) {
            return 'Keine Affiliate-Funde';
        }

        $parts = [
            sprintf('%d Affiliate-Funde', $finds),
            sprintf('%d Cards', $cards),
        ];

        if ($tracking !== '') {
            $parts[] = 'Tracking-ID ' . $tracking;
        }

        return implode(', ', $parts);
    }

    public function scan_post_content(string $content): array {
        $state = $this->default_state();
        $markerAsins = [];
        $linkAsins = [];
        $tags = [];

        if (preg_match_all(self::INLINE_MARKER_PATTERN, $content, $markerMatches)) {
            foreach ($markerMatches[1] as $asin) {
                $markerAsins[] = strtoupper((string) $asin);
            }
        }

        if (preg_match_all(self::AMAZON_LINK_PATTERN, $content, $linkMatches)) {
            foreach ($linkMatches[1] as $asin) {
                $linkAsins[] = strtoupper((string) $asin);
            }

            foreach ($linkMatches[0] as $url) {
                $tag = $this->extract_partner_tag((string) $url);
                if ($tag !== '') {
                    $tags[] = $tag;
                }
            }
        }

        $state['counts']['affiliate_finds'] = count($markerAsins) + count($linkAsins);
        $state['counts']['card_blocks'] = preg_match_all(self::AFFILIATE_CARD_BLOCK_PATTERN, $content) ?: 0;
        $state['asins'] = array_values(array_unique(array_merge($markerAsins, $linkAsins)));
        $state['tracking'] = $this->classify_tracking($tags, (int) $state['counts']['affiliate_finds']);

        return $state;
    }

    public function meta_key(): string {
        return '_mtb_affiliate_audit';
    }

    public function list_recent_rows(int $limit = 25): array {
        if (! function_exists('get_posts')) {
            return [];
        }

        $posts = get_posts([
            'post_type' => 'post',
            'post_status' => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => $limit,
            'orderby' => 'date',
            'order' => 'DESC',
        ]);

        if (! is_array($posts)) {
            return [];
        }

        $rows = [];
        foreach ($posts as $post) {
            if (! is_object($post)) {
                continue;
            }

            $postId = (int) ($post->ID ?? 0);
            if ($postId <= 0) {
                continue;
            }

            $state = $this->read_post_state($postId, (string) ($post->post_content ?? ''));
            $rows[] = [
                'id' => $postId,
                'title' => (string) ($post->post_title ?? ''),
                'date' => (string) ($post->post_date ?? ''),
                'status' => (string) ($state['status'] ?? 'offen'),
                'affiliate_finds' => (int) ($state['counts']['affiliate_finds'] ?? 0),
                'tracking' => (string) ($state['tracking'] ?? ''),
                'card_blocks' => (int) ($state['counts']['card_blocks'] ?? 0),
                'asins' => array_values(array_map('strval', $state['asins'] ?? [])),
                'checked_at' => (string) ($state['timestamps']['checked_at'] ?? ''),
                'short_log' => (string) ($state['short_log'] ?? ''),
                'edit_link' => function_exists('get_edit_post_link') ? (string) get_edit_post_link($postId) : '',
            ];
        }

        return $rows;
    }

    public function read_post_state(int $postId, string $fallbackContent = ''): array {
        $state = [];
        if (function_exists('get_post_meta')) {
            $stored = get_post_meta($postId, $this->meta_key(), true);
            if (is_array($stored)) {
                $state = $stored;
            }
        }

        if ($state === [] && $fallbackContent !== '') {
            $state = $this->scan_post_content($fallbackContent);
        }

        $state = array_replace_recursive($this->default_state(), is_array($state) ? $state : []);
        if (($state['short_log'] ?? '') === '') {
            $state['short_log'] = $this->build_short_log($state);
        }

        return $state;
    }

    private function extract_partner_tag(string $url): string {
        $query = parse_url($this->normalize_stored_url($url), PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return '';
        }

        parse_str($query, $params);
        return trim((string) ($params['tag'] ?? ''));
    }

    private function normalize_stored_url(string $url): string {
        return str_replace(
            ['\\u0026', '\\/', '&amp;'],
            ['&', '/', '&'],
            $url
        );
    }

    private function classify_tracking(array $tags, int $affiliateFinds): string {
        if ($affiliateFinds <= 0) {
            return 'keine_funde';
        }

        $tags = array_values(array_unique(array_filter(array_map('strval', $tags))));
        if ($tags === []) {
            return 'unklar';
        }

        if (count($tags) > 1) {
            return 'abweichend';
        }

        return preg_match('/^meintechblog-\d{6}-21$/', $tags[0]) === 1 ? 'ok' : 'abweichend';
    }
}
