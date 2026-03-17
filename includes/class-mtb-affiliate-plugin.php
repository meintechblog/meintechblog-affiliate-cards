<?php

declare(strict_types=1);

final class MTB_Affiliate_Plugin {
    private static ?MTB_Affiliate_Plugin $instance = null;

    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Block $block;
    private MTB_Affiliate_Amazon_Client $amazonClient;

    private function __construct() {
        $this->settings = new MTB_Affiliate_Settings();
        $this->amazonClient = new MTB_Affiliate_Amazon_Client();
        $this->block = new MTB_Affiliate_Block($this->settings, $this->amazonClient);
    }

    public static function instance(): MTB_Affiliate_Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    public function boot(): void {
        if (! function_exists('add_action')) {
            return;
        }

        add_action('admin_menu', [$this, 'register_settings_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this->block, 'register']);
    }

    public static function activate(): void {
        $settings = new MTB_Affiliate_Settings();
        $settings->save($settings->defaults());
    }

    public function register_settings(): void {
        $this->settings->register();
    }

    public function register_settings_page(): void {
        if (! function_exists('add_options_page')) {
            return;
        }

        add_options_page(
            'Affiliate Card',
            'Affiliate Card',
            'manage_options',
            'mtb-affiliate-cards',
            [$this, 'render_settings_page']
        );
    }

    public function register_assets(): void {
        if (! function_exists('wp_register_style')) {
            return;
        }

        wp_register_style(
            'mtb-affiliate-cards-frontend',
            MTB_AFFILIATE_CARDS_URL . 'assets/frontend.css',
            [],
            MTB_AFFILIATE_CARDS_VERSION
        );
    }

    public function render_settings_page(): void {
        if (! current_user_can('manage_options')) {
            return;
        }

        $settings = $this->settings->get_all();
        ?>
        <div class="wrap">
            <h1>Affiliate Card</h1>
            <p>Grundkonfiguration fuer die nativen Amazon-Affiliate-Cards auf meintechblog.de.</p>
            <form method="post" action="options.php">
                <?php if (function_exists('settings_fields')) : ?>
                    <?php settings_fields($this->settings->settings_group()); ?>
                <?php endif; ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="mtb-cta-label">CTA-Text</label></th>
                        <td><input id="mtb-cta-label" type="text" class="regular-text" name="<?php echo esc_attr($this->settings->option_name()); ?>[cta_label]" value="<?php echo esc_attr($settings['cta_label']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mtb-badge-mode">Badge-Modus</label></th>
                        <td>
                            <select id="mtb-badge-mode" name="<?php echo esc_attr($this->settings->option_name()); ?>[badge_mode]">
                                <option value="auto" <?php selected($settings['badge_mode'], 'auto'); ?>>Automatisch</option>
                                <option value="video" <?php selected($settings['badge_mode'], 'video'); ?>>Immer Im Video verwendet</option>
                                <option value="setup" <?php selected($settings['badge_mode'], 'setup'); ?>>Immer Passend zu diesem Setup</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mtb-marketplace">Marketplace</label></th>
                        <td><input id="mtb-marketplace" type="text" class="regular-text" name="<?php echo esc_attr($this->settings->option_name()); ?>[marketplace]" value="<?php echo esc_attr($settings['marketplace']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mtb-client-id">Amazon Client ID</label></th>
                        <td><input id="mtb-client-id" type="text" class="regular-text code" name="<?php echo esc_attr($this->settings->option_name()); ?>[client_id]" value="<?php echo esc_attr($settings['client_id']); ?>"></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="mtb-client-secret">Amazon Client Secret</label></th>
                        <td><input id="mtb-client-secret" type="password" class="regular-text code" name="<?php echo esc_attr($this->settings->option_name()); ?>[client_secret]" value="<?php echo esc_attr($settings['client_secret']); ?>" autocomplete="new-password"></td>
                    </tr>
                    <tr>
                        <th scope="row">Kurze Titel bevorzugen</th>
                        <td><label><input type="checkbox" name="<?php echo esc_attr($this->settings->option_name()); ?>[auto_shorten_titles]" value="1" <?php checked($settings['auto_shorten_titles']); ?>> Automatische Kürzung im aktuellen Live-Stil aktivieren</label></td>
                    </tr>
                </table>
                <?php if (function_exists('submit_button')) : ?>
                    <?php submit_button('Einstellungen speichern'); ?>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }

    public function handle_save_post(int $postId, $post = null, bool $update = false): void {
        if (! function_exists('wp_update_post') || ! function_exists('remove_action') || ! function_exists('add_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (function_exists('wp_is_post_revision') && wp_is_post_revision($postId)) {
            return;
        }

        if (function_exists('wp_is_post_autosave') && wp_is_post_autosave($postId)) {
            return;
        }

        if (! is_object($post) && function_exists('get_post')) {
            $post = get_post($postId);
        }

        if (! is_object($post) || ! isset($post->post_content)) {
            return;
        }

        $settings = $this->settings->get_all();
        $processor = new MTB_Affiliate_Post_Processor(
            null,
            [
                'badgeMode' => $settings['badge_mode'],
                'ctaLabel' => $settings['cta_label'],
                'autoShortenTitles' => $settings['auto_shorten_titles'],
            ],
            function (array $asins) use ($settings, $post): array {
                return $this->resolve_items_for_save($asins, $settings, $post);
            }
        );

        $processed = $processor->process((string) $post->post_content);
        if ($processed['asins'] === [] || $processed['content'] === (string) $post->post_content) {
            return;
        }

        remove_action('save_post', [$this, 'handle_save_post'], 20);

        wp_update_post([
            'ID' => $postId,
            'post_content' => $processed['content'],
        ]);

        add_action('save_post', [$this, 'handle_save_post'], 20, 3);
    }

    private function resolve_items_for_save(array $asins, array $settings, object $post): array {
        $fallbackItems = array_map(
            static fn(string $asin): array => ['asin' => $asin],
            $asins
        );

        if ($asins === [] || $settings['client_id'] === '' || $settings['client_secret'] === '') {
            return $fallbackItems;
        }

        try {
            $partnerTag = $this->amazonClient->derive_partner_tag((string) ($post->post_date ?? ''));
            $resolvedItems = $this->amazonClient->get_items($asins, [
                'client_id' => $settings['client_id'],
                'client_secret' => $settings['client_secret'],
                'marketplace' => $settings['marketplace'],
                'partner_tag' => $partnerTag,
            ]);

            $byAsin = [];
            foreach ($resolvedItems as $item) {
                $asin = trim((string) ($item['asin'] ?? ''));
                if ($asin !== '') {
                    $byAsin[$asin] = $item;
                }
            }

            return array_map(
                static function (string $asin) use ($byAsin): array {
                    return $byAsin[$asin] ?? ['asin' => $asin];
                },
                $asins
            );
        } catch (Throwable $exception) {
            return $fallbackItems;
        }
    }
}
