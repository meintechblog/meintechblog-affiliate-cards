<?php

declare(strict_types=1);

$GLOBALS['mtb_actions'] = [];
$GLOBALS['mtb_deleted_options'] = [];

function add_action(string $hook, $callback): void {
    $GLOBALS['mtb_actions'][$hook][] = $callback;
}

function delete_option(string $option): bool {
    $GLOBALS['mtb_deleted_options'][] = $option;
    return true;
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-renderer.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-block.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-plugin.php';

function assert_true_lifecycle(bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

MTB_Affiliate_Plugin::instance()->boot();

assert_true_lifecycle(isset($GLOBALS['mtb_actions']['admin_menu']), 'Plugin should register the settings page hook.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['init']), 'Plugin should register init hooks.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['admin_init']), 'Plugin should register admin_init for settings.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['save_post']), 'Plugin should register save_post for autoscan processing.');

define('WP_UNINSTALL_PLUGIN', true);
require dirname(__DIR__) . '/uninstall.php';

assert_true_lifecycle(
    in_array('mtb_affiliate_cards_settings', $GLOBALS['mtb_deleted_options'], true),
    'Uninstall should delete the plugin settings option.'
);

assert_true_lifecycle(
    is_file(dirname(__DIR__) . '/scripts/build-zip.sh'),
    'Plugin repo should include a build script for an installable ZIP.'
);

assert_true_lifecycle(
    str_contains((string) file_get_contents(dirname(__DIR__) . '/scripts/build-zip.sh'), 'meintechblog-affiliate-cards.zip'),
    'Build script should target a predictable plugin ZIP name.'
);

echo "ok\n";
