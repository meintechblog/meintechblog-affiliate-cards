<?php

declare(strict_types=1);

$GLOBALS['mtb_test_options'] = [
    'mtb_affiliate_cards_settings' => [
        'cta_label' => '  Mein CTA  ',
        'badge_mode' => 'video',
        'auto_shorten_titles' => false,
        'marketplace' => 'www.amazon.de',
        'client_id' => '  client-id  ',
        'client_secret' => '  secret  ',
    ],
];

function get_option(string $option, $default = false) {
    return $GLOBALS['mtb_test_options'][$option] ?? $default;
}

function update_option(string $option, $value): bool {
    $GLOBALS['mtb_test_options'][$option] = $value;
    return true;
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';

function assert_same_settings($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$settings = new MTB_Affiliate_Settings();

assert_same_settings(
    'mtb_affiliate_cards_settings',
    $settings->option_name(),
    'Settings should expose a stable option name.'
);

$all = $settings->get_all();

assert_same_settings('Mein CTA', $all['cta_label'], 'CTA label should be trimmed when read.');
assert_same_settings('video', $all['badge_mode'], 'Stored badge mode should be preserved when valid.');
assert_same_settings(false, $all['auto_shorten_titles'], 'Boolean settings should be preserved.');
assert_same_settings('client-id', $all['client_id'], 'Client id should be trimmed.');
assert_same_settings('secret', $all['client_secret'], 'Client secret should be trimmed.');

$settings->save([
    'cta_label' => '',
    'badge_mode' => 'kaputt',
    'auto_shorten_titles' => '1',
    'marketplace' => '  www.amazon.de  ',
    'client_id' => ' next-client ',
    'client_secret' => ' next-secret ',
]);

$saved = $GLOBALS['mtb_test_options']['mtb_affiliate_cards_settings'];

assert_same_settings('Preis auf Amazon checken', $saved['cta_label'], 'Empty CTA should fall back to default.');
assert_same_settings('auto', $saved['badge_mode'], 'Invalid badge mode should fall back to auto.');
assert_same_settings(true, $saved['auto_shorten_titles'], 'Truthy auto shorten values should be normalized to bool.');
assert_same_settings('www.amazon.de', $saved['marketplace'], 'Marketplace should be trimmed.');
assert_same_settings('next-client', $saved['client_id'], 'Client id should be sanitized on save.');
assert_same_settings('next-secret', $saved['client_secret'], 'Client secret should be sanitized on save.');

echo "ok\n";
