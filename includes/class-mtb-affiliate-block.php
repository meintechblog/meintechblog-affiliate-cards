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
            ['wp-blocks', 'wp-block-editor', 'wp-components', 'wp-data', 'wp-dom-ready', 'wp-element', 'wp-i18n'],
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
        $items = $this->apply_persisted_card_attributes($attributes, $items);
        $postContent = '';
        $postId = 0;
        if ($block && isset($block->context['postId']) && function_exists('get_post_field')) {
            $postId = (int) $block->context['postId'];
            $postContent = (string) get_post_field('post_content', $postId);
        } elseif (function_exists('get_the_ID') && function_exists('get_post_field')) {
            $postId = (int) get_the_ID();
            if ($postId > 0) {
                $postContent = (string) get_post_field('post_content', $postId);
            }
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

    private function apply_persisted_card_attributes(array $attributes, array $items): array {
        if ($items === [] || ! isset($items[0]) || ! is_array($items[0])) {
            return $items;
        }

        $item = $items[0];
        $asin = strtoupper(trim((string) ($item['asin'] ?? '')));
        $hydratedAsin = strtoupper(trim((string) ($attributes['hydratedAsin'] ?? '')));
        $currentTitle = trim((string) ($item['title'] ?? ''));
        $isPlaceholderTitle = $currentTitle === '' || strtoupper($currentTitle) === $asin;

        if ($hydratedAsin !== '' && $hydratedAsin !== $asin) {
            $item['image_url'] = '';
            $item['detail_url'] = '';
            if ($currentTitle === '' || $isPlaceholderTitle) {
                $item['title'] = $asin;
            }
            $items[0] = $item;
            return $items;
        }

        $amazonTitle = trim((string) ($attributes['amazonTitle'] ?? ''));
        if ($amazonTitle !== '' && $isPlaceholderTitle) {
            $item['title'] = $amazonTitle;
        }

        $detailUrl = trim((string) ($attributes['detailUrl'] ?? ''));
        if ($detailUrl !== '' && trim((string) ($item['detail_url'] ?? '')) === '') {
            $item['detail_url'] = $detailUrl;
        }

        $images = array_values(
            array_filter(
                is_array($attributes['images'] ?? null) ? $attributes['images'] : [],
                static fn($value): bool => is_string($value) && trim($value) !== ''
            )
        );

        if ($images !== []) {
            $selectedImageIndex = (int) ($attributes['selectedImageIndex'] ?? 0);
            if ($selectedImageIndex < 0) {
                $selectedImageIndex = 0;
            }
            if ($selectedImageIndex >= count($images)) {
                $selectedImageIndex = count($images) - 1;
            }
            $item['image_url'] = (string) $images[$selectedImageIndex];
            $item['images'] = $images;
        }

        $items[0] = $item;
        return $items;
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
            $persistedTitle = trim((string) ($item['title'] ?? ''));
            if ($persistedTitle !== '' && strtoupper($persistedTitle) === strtoupper($asin)) {
                $persistedTitle = '';
            }
            $fetchedTitle = trim((string) ($fetched['title'] ?? ''));
            $title = $persistedTitle !== '' ? $persistedTitle : ($fetchedTitle !== '' ? $fetchedTitle : $asin);
            if (! empty($item['titleOverride'])) {
                $title = trim((string) $item['titleOverride']);
            } elseif ($autoShortenTitles) {
                $title = $this->shortener->shorten($asin, $title);
            }

            $persistedImageUrl = trim((string) ($item['image_url'] ?? ''));
            $persistedDetailUrl = trim((string) ($item['detail_url'] ?? ''));
            $persistedBenefit = trim((string) ($item['benefit'] ?? ''));
            $fetchedImageUrl = trim((string) ($fetched['image_url'] ?? ''));
            $fetchedDetailUrl = trim((string) ($fetched['detail_url'] ?? ''));
            $fetchedBenefit = trim((string) ($fetched['benefit'] ?? ''));

            $resolved[] = [
                'asin' => $asin,
                'title' => $title,
                'image_url' => $persistedImageUrl !== '' ? $persistedImageUrl : $fetchedImageUrl,
                'detail_url' => $persistedDetailUrl !== '' ? $persistedDetailUrl : ($fetchedDetailUrl !== '' ? $fetchedDetailUrl : ('https://www.amazon.de/dp/' . $asin)),
                'benefit' => $persistedBenefit !== '' ? $persistedBenefit : $fetchedBenefit,
                'price_text' => $fetched['price_text'] ?? null,
            ];
        }

        return $resolved;
    }
}
