<?php

declare(strict_types=1);

// Isolate activation from database migrations; verify that they still run each time.
class WP_List_Table {}
class ActivationTables {
    public static function create_table(): void {
        $GLOBALS['activation_tables'][] = static::class;
    }
}
class MTB_Affiliate_Tracking_Registry extends ActivationTables {}
class MTB_Affiliate_Product_Library extends ActivationTables {}
class MTB_Affiliate_Click_Tracker extends ActivationTables {}
class MTB_Affiliate_Availability_Checker extends ActivationTables {}

function get_option(string $name, $default = false) {
    return array_key_exists($name, $GLOBALS['activation_options']) ? $GLOBALS['activation_options'][$name] : $default;
}
function update_option(string $name, $value): bool {
    $GLOBALS['activation_writes']++;
    $GLOBALS['activation_options'][$name] = $value;
    return true;
}
function check_activation(bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-plugin.php';

$GLOBALS['activation_options'] = [];
$GLOBALS['activation_writes'] = 0;
$GLOBALS['activation_tables'] = [];
$settings = new MTB_Affiliate_Settings();
MTB_Affiliate_Plugin::activate();
$initial = get_option($settings->option_name());
check_activation($GLOBALS['activation_writes'] === 1, 'First activation must create the missing settings option.');
foreach ($settings->defaults() as $key => $value) {
    check_activation($key === 'telegram_webhook_secret' ? $initial[$key] !== '' : $initial[$key] === $value, 'First activation must initialize default: ' . $key);
}
MTB_Affiliate_Plugin::activate();
check_activation($GLOBALS['activation_writes'] === 1 && get_option($settings->option_name()) === $initial, 'Reactivation must preserve initial settings and the generated secret.');

$custom = array_merge($initial, [
    'client_id' => 'custom-client', 'client_secret' => 'custom-secret',
    'telegram_bot_token' => 'bot-token', 'telegram_chat_id' => 'chat-id',
    'telegram_webhook_secret' => 'existing-secret', 'auto_shorten_titles' => false,
    'cta_label' => 'Eigener Text', 'badge_mode' => 'setup', 'future_setting' => 'keep',
]);
foreach ([$custom, [], false, null] as $stored) {
    $GLOBALS['activation_options'][$settings->option_name()] = $stored;
    MTB_Affiliate_Plugin::activate();
    check_activation($GLOBALS['activation_writes'] === 1 && get_option($settings->option_name()) === $stored, 'Activation must never overwrite an existing option, including empty values.');
}
check_activation(count($GLOBALS['activation_tables']) === 24, 'All four schema initializers must still run on each activation.');
echo "ok\n";
