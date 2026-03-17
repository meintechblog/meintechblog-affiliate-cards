<?php

declare(strict_types=1);

$GLOBALS['mtb_actions'] = [];
$GLOBALS['mtb_deleted_options'] = [];
$GLOBALS['mtb_rest_routes'] = [];

function add_action(string $hook, $callback): void {
    $GLOBALS['mtb_actions'][$hook][] = $callback;
}

function register_rest_route(string $namespace, string $route, array $args): bool {
    $GLOBALS['mtb_rest_routes'][] = [
        'namespace' => $namespace,
        'route' => $route,
        'args' => $args,
    ];
    return true;
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

function assert_same_lifecycle($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

MTB_Affiliate_Plugin::instance()->boot();

assert_true_lifecycle(isset($GLOBALS['mtb_actions']['admin_menu']), 'Plugin should register the settings page hook.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['init']), 'Plugin should register init hooks.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['admin_init']), 'Plugin should register admin_init for settings.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['rest_api_init']), 'Plugin should register REST API hydration wiring.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['save_post']), 'Plugin should register save_post for inline affiliate enrichment on save.');

foreach ($GLOBALS['mtb_actions']['rest_api_init'] as $restCallback) {
    if (is_callable($restCallback)) {
        $restCallback();
    }
}

$routeFound = false;
foreach ($GLOBALS['mtb_rest_routes'] as $route) {
    if (($route['namespace'] ?? '') === 'mtb-affiliate-cards/v1' && ($route['route'] ?? '') === '/item') {
        $routeFound = true;
        break;
    }
}

assert_true_lifecycle($routeFound, 'REST hydration endpoint /mtb-affiliate-cards/v1/item should be registered.');

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

$retryCalls = [];
$retryTransport = static function (string $method, string $url, array $headers, ?array $body) use (&$retryCalls): array {
    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-xyz']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        $retryCalls[] = $body['partner_tag'] ?? $body['partnerTag'] ?? null;
        $partnerTag = (string) ($body['partner_tag'] ?? $body['partnerTag'] ?? '');

        if ($partnerTag === 'meintechblog-251107-21') {
            return [400, ['message' => 'Invalid or unmapped partner tag']];
        }

        if ($partnerTag === 'meintechblog-251007-21') {
            return [200, ['itemsResult' => ['items' => [[
                'asin' => 'B0RETRY001',
                'detailPageURL' => 'https://www.amazon.de/dp/B0RETRY001?tag=meintechblog-251007-21',
                'images' => [],
                'itemInfo' => [
                    'title' => ['displayValue' => 'Retry Product'],
                ],
            ]]]]];
        }
    }

    return [500, ['message' => 'unexpected']];
};

$retryClient = new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $retryTransport);
$pluginReflection = new ReflectionClass('MTB_Affiliate_Plugin');
$pluginForRetry = $pluginReflection->newInstanceWithoutConstructor();
$amazonClientProperty = $pluginReflection->getProperty('amazonClient');
$amazonClientProperty->setValue($pluginForRetry, $retryClient);

$resolveMethod = $pluginReflection->getMethod('resolve_items_for_save');

$resolved = $resolveMethod->invoke(
    $pluginForRetry,
    ['B0RETRY001'],
    [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'marketplace' => 'www.amazon.de',
    ],
    (object) [
        'post_date' => '2025-11-07T14:08:39',
        'post_content' => '<p><a href="https://www.amazon.de/dp/B0NO_TAG">ohne tag zuerst</a> und danach <a href="https://www.amazon.de/dp/B0RETRY001?tag=meintechblog-251007-21">mit tag</a></p>',
    ]
);

assert_same_lifecycle(
    ['meintechblog-251107-21', 'meintechblog-251007-21'],
    $retryCalls,
    'Save resolver should try the derived date tag first and then retry with an existing functional tag from post content.'
);

assert_same_lifecycle(
    'https://www.amazon.de/dp/B0RETRY001?tag=meintechblog-251007-21',
    $resolved[0]['detail_url'] ?? null,
    'Save resolver should return items from the successful fallback tag request.'
);

echo "ok\n";
