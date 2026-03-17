<?php

declare(strict_types=1);

final class MTB_Affiliate_Plugin {
    private static ?MTB_Affiliate_Plugin $instance = null;

    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Block $block;

    private function __construct() {
        $this->settings = new MTB_Affiliate_Settings();
        $this->block = new MTB_Affiliate_Block();
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
        add_action('init', [$this, 'register_assets']);
        add_action('init', [$this->block, 'register']);
    }

    public function register_settings_page(): void {
        if (! function_exists('add_options_page')) {
            return;
        }

        add_options_page(
            'Affiliate Cards',
            'Affiliate Cards',
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

        $defaults = $this->settings->defaults();
        ?>
        <div class="wrap">
            <h1>Affiliate Cards</h1>
            <p>Grundkonfiguration für die nativen Amazon-Affiliate-Karten auf meintechblog.de.</p>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Standard-CTA</th>
                    <td><code><?php echo esc_html($defaults['cta_label']); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Badge-Modus</th>
                    <td><code><?php echo esc_html($defaults['badge_mode']); ?></code></td>
                </tr>
                <tr>
                    <th scope="row">Auto-Kürzung</th>
                    <td><code><?php echo $defaults['auto_shorten_titles'] ? 'aktiv' : 'inaktiv'; ?></code></td>
                </tr>
            </table>
            <p>Die echte Einstellungs-UI kommt im nächsten Implementierungsschritt.</p>
        </div>
        <?php
    }
}
