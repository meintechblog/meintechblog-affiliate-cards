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

$secret = $saved['telegram_webhook_secret'];
assert_same_settings(32, strlen($secret), 'An empty webhook secret should be generated.');

// Emulate the Settings API callbacks with each form's submitted fields.
$telegram = $settings->sanitize([
    'telegram_bot_token' => ' bot-token ',
    'telegram_chat_id' => ' chat-id ',
    'telegram_webhook_secret' => $secret,
]);
update_option($settings->option_name(), $telegram);
assert_same_settings('next-client', $telegram['client_id'], 'Telegram must keep Amazon credentials.');
assert_same_settings('next-secret', $telegram['client_secret'], 'Telegram must keep the Amazon secret.');
assert_same_settings($saved['cta_label'], $telegram['cta_label'], 'Telegram must keep card settings.');
assert_same_settings($secret, $telegram['telegram_webhook_secret'], 'Telegram must not rotate an existing secret.');

$amazon = $settings->sanitize([
    'cta_label' => 'Neuer CTA',
    'badge_mode' => 'setup',
    'marketplace' => 'www.amazon.de',
    'client_id' => 'changed-client',
    'client_secret' => 'changed-secret',
    'auto_shorten_titles' => '0', // hidden field when unchecked
]);
update_option($settings->option_name(), $amazon);
assert_same_settings('bot-token', $amazon['telegram_bot_token'], 'Amazon must keep the bot token.');
assert_same_settings('chat-id', $amazon['telegram_chat_id'], 'Amazon must keep the chat ID.');
assert_same_settings($secret, $amazon['telegram_webhook_secret'], 'Amazon must not regenerate the webhook secret.');
assert_same_settings(false, $settings->get_all()['auto_shorten_titles'], 'Unchecked must remain false after reading.');

$settings->save(['telegram_chat_id' => '']);
$afterTelegram = $settings->get_all();
assert_same_settings('', $afterTelegram['telegram_chat_id'], 'An explicitly submitted empty field may be cleared.');
assert_same_settings(false, $afterTelegram['auto_shorten_titles'], 'A later Telegram save must keep the checkbox off.');
assert_same_settings('changed-client', $afterTelegram['client_id'], 'A later Telegram save must keep changed Amazon credentials.');
assert_same_settings($secret, $afterTelegram['telegram_webhook_secret'], 'Partial saves must keep the secret stable.');
assert_same_settings($afterTelegram, $settings->sanitize($afterTelegram), 'Repeated sanitization must be stable.');

echo "ok\n";
