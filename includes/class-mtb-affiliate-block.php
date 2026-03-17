<?php

declare(strict_types=1);

final class MTB_Affiliate_Block {
    private MTB_Affiliate_Renderer $renderer;
    private MTB_Affiliate_Badge_Resolver $badgeResolver;

    public function __construct() {
        $this->renderer = new MTB_Affiliate_Renderer();
        $this->badgeResolver = new MTB_Affiliate_Badge_Resolver();
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
        if ($block && isset($block->context['postId']) && function_exists('get_post_field')) {
            $postContent = (string) get_post_field('post_content', (int) $block->context['postId']);
        }

        $mode = $attributes['badgeMode'] ?? 'auto';
        $badgeLabel = $this->badgeResolver->resolve($postContent, $mode);
        $ctaLabel = $attributes['ctaLabel'] ?? 'Preis auf Amazon checken';

        ob_start();
        $renderer = $this->renderer;
        $itemsForTemplate = $items;
        $badgeLabelForTemplate = $badgeLabel;
        $ctaLabelForTemplate = $ctaLabel;
        include MTB_AFFILIATE_CARDS_DIR . 'templates/affiliate-cards.php';
        return (string) ob_get_clean();
    }
}
