<?php

declare(strict_types=1);

final class MTB_Affiliate_Settings {
    private const OPTION_NAME = 'mtb_affiliate_cards_settings';
    private const SETTINGS_GROUP = 'mtb_affiliate_cards';

    public function defaults(): array {
        return [
            'cta_label' => 'Preis auf Amazon checken',
            'badge_mode' => 'auto',
            'auto_shorten_titles' => true,
            'marketplace' => 'www.amazon.de',
            'client_id' => '',
            'client_secret' => '',
        ];
    }

    public function option_name(): string {
        return self::OPTION_NAME;
    }

    public function settings_group(): string {
        return self::SETTINGS_GROUP;
    }

    public function get_all(): array {
        $stored = [];

        if (function_exists('get_option')) {
            $value = get_option($this->option_name(), []);
            if (is_array($value)) {
                $stored = $value;
            }
        }

        return $this->sanitize(array_merge($this->defaults(), $stored));
    }

    public function save(array $settings): array {
        $sanitized = $this->sanitize($settings);

        if (function_exists('update_option')) {
            update_option($this->option_name(), $sanitized);
        }

        return $sanitized;
    }

    public function register(): void {
        if (! function_exists('register_setting')) {
            return;
        }

        register_setting(
            $this->settings_group(),
            $this->option_name(),
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize'],
                'default' => $this->defaults(),
            ]
        );
    }

    public function sanitize(array $settings): array {
        $defaults = $this->defaults();
        $cta = trim((string) ($settings['cta_label'] ?? ''));
        $badgeMode = trim((string) ($settings['badge_mode'] ?? 'auto'));
        $marketplace = trim((string) ($settings['marketplace'] ?? $defaults['marketplace']));
        $clientId = trim((string) ($settings['client_id'] ?? ''));
        $clientSecret = trim((string) ($settings['client_secret'] ?? ''));

        if (! in_array($badgeMode, ['auto', 'video', 'setup'], true)) {
            $badgeMode = $defaults['badge_mode'];
        }

        return [
            'cta_label' => $cta !== '' ? $cta : $defaults['cta_label'],
            'badge_mode' => $badgeMode,
            'auto_shorten_titles' => filter_var($settings['auto_shorten_titles'] ?? $defaults['auto_shorten_titles'], FILTER_VALIDATE_BOOLEAN),
            'marketplace' => $marketplace !== '' ? $marketplace : $defaults['marketplace'],
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ];
    }
}
