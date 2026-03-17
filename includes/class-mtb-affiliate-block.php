<?php

declare(strict_types=1);

final class MTB_Affiliate_Block {
    private MTB_Affiliate_Renderer $renderer;
    private MTB_Affiliate_Badge_Resolver $badgeResolver;
    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Amazon_Client $amazonClient;
    private MTB_Affiliate_Title_Shortener $shortener;

    public function __construct(
        ?MTB_Affiliate_Settings $settings = null,
        ?MTB_Affiliate_Amazon_Client $amazonClient = null,
        ?MTB_Affiliate_Title_Shortener $shortener = null
    ) {
        $this->renderer = new MTB_Affiliate_Renderer();
        $this->badgeResolver = new MTB_Affiliate_Badge_Resolver();
        $this->settings = $settings ?? new MTB_Affiliate_Settings();
        $this->amazonClient = $amazonClient ?? new MTB_Affiliate_Amazon_Client();
        $this->shortener = $shortener ?? new MTB_Affiliate_Title_Shortener();
    }

    public function register(): void {
        if (! function_exists('register_block_type')) {
            return;
        }

        wp_register_script(
            'mtb-affiliate-cards-editor',
            MTB_AFFILIATE_CARDS_URL . 'blocks/affiliate-cards/index.js',
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-element', 'wp-i18n'],
            MTB_AFFILIATE_CARDS_VERSION,
            true
        );

        wp_register_style(
            'mtb-affiliate-cards-editor',
            MTB_AFFILIATE_CARDS_URL . 'blocks/affiliate-cards/editor.css',
            [],
            MTB_AFFILIATE_CARDS_VERSION
        );

        register_block_type(MTB_AFFILIATE_CARDS_DIR . 'blocks/affiliate-cards', [
            'render_callback' => [$this, 'render'],
        ]);
    }

    public function render(array $attributes = [], string $content = '', ?WP_Block $block = null): string {
        $items = $attributes['items'] ?? [];
        $postContent = '';
        $postId = 0;
        if ($block && isset($block->context['postId']) && function_exists('get_post_field')) {
            $postId = (int) $block->context['postId'];
            $postContent = (string) get_post_field('post_content', $postId);
        }

        $settings = $this->settings->get_all();
        $mode = $attributes['badgeMode'] ?? $settings['badge_mode'];
        $badgeLabel = $this->badgeResolver->resolve($postContent, $mode);
        $ctaLabel = $attributes['ctaLabel'] ?? $settings['cta_label'];
        $resolvedItems = $this->resolve_items($items, $postId, $settings, ! empty($attributes['autoShortenTitles']));

        ob_start();
        $renderer = $this->renderer;
        $itemsForTemplate = $resolvedItems;
        $badgeLabelForTemplate = $badgeLabel;
        $ctaLabelForTemplate = $ctaLabel;
        include MTB_AFFILIATE_CARDS_DIR . 'templates/affiliate-cards.php';
        return (string) ob_get_clean();
    }

    private function resolve_items(array $items, int $postId, array $settings, bool $autoShortenTitles): array {
        if ($items === []) {
            return [];
        }

        $mappedByAsin = [];
        $asins = [];
        foreach ($items as $item) {
            $asin = trim((string) ($item['asin'] ?? ''));
            if ($asin === '') {
                continue;
            }
            $asins[] = $asin;
        }

        if ($asins !== [] && $settings['client_id'] !== '' && $settings['client_secret'] !== '') {
            try {
                $postDate = $postId > 0 && function_exists('get_post_field')
                    ? (string) get_post_field('post_date', $postId)
                    : '';

                $fetchedItems = $this->amazonClient->get_items($asins, [
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'marketplace' => $settings['marketplace'],
                    'partner_tag' => $this->amazonClient->derive_partner_tag($postDate),
                ]);

                foreach ($fetchedItems as $fetchedItem) {
                    $mappedByAsin[(string) $fetchedItem['asin']] = $fetchedItem;
                }
            } catch (Throwable $exception) {
                $mappedByAsin = [];
            }
        }

        $resolved = [];
        foreach ($items as $item) {
            $asin = trim((string) ($item['asin'] ?? ''));
            if ($asin === '') {
                continue;
            }

            $fetched = $mappedByAsin[$asin] ?? [];
            $title = (string) ($fetched['title'] ?? $item['title'] ?? $asin);
            if (! empty($item['titleOverride'])) {
                $title = trim((string) $item['titleOverride']);
            } elseif ($autoShortenTitles) {
                $title = $this->shortener->shorten($asin, $title);
            }

            $resolved[] = [
                'asin' => $asin,
                'title' => $title,
                'image_url' => (string) ($fetched['image_url'] ?? $item['image_url'] ?? ''),
                'detail_url' => (string) ($fetched['detail_url'] ?? $item['detail_url'] ?? ('https://www.amazon.de/dp/' . $asin)),
                'benefit' => (string) ($item['benefit'] ?? $fetched['benefit'] ?? ''),
                'price_text' => $fetched['price_text'] ?? null,
            ];
        }

        return $resolved;
    }
}
