<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap-plugin.php';

$GLOBALS['mtb_actions'] = [];
$GLOBALS['mtb_deleted_options'] = [];
$GLOBALS['mtb_rest_routes'] = [];
$GLOBALS['mtb_options'] = [
    // Keep unrelated schema upgrades out of this standalone lifecycle test.
    'mtb_affiliate_clicks_db_version' => '1.0',
    'mtb_affiliate_availability_db_version' => '1.0',
];
$GLOBALS['mtb_update_posts'] = [];
$_GET['tab'] = 'settings';

require_once __DIR__ . '/support-post-saving.php';

function add_filter(string $hook, $callback): void {}

function add_menu_page(...$args): void {}

function add_submenu_page(string $parent, string $title, string $label, string $capability, string $slug, $callback): void {
    $GLOBALS['mtb_submenus'][$slug] = $callback;
}

function current_user_can(string $capability): bool {
    return $capability === 'manage_options';
}

function esc_attr(string $value): string {
    return $value;
}

function selected($actual, $expected): string {
    return $actual === $expected ? 'selected' : '';
}

function checked($actual): string {
    return $actual ? 'checked' : '';
}

function settings_fields(string $group): void {
    echo '<input type="hidden" name="settings-group" value="' . $group . '">';
}

function submit_button(string $text): void {
    echo '<button>' . $text . '</button>';
}

function register_rest_route(string $namespace, string $route, array $args): bool {
    $GLOBALS['mtb_rest_routes'][] = [
        'namespace' => $namespace,
        'route' => $route,
        'args' => $args,
    ];
    return true;
}

function get_option(string $option, $default = false) {
    return $GLOBALS['mtb_options'][$option] ?? $default;
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
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-audit-service.php';
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
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['admin_post_mtb_affiliate_audit']), 'Plugin should register the audit admin-post handler.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['rest_api_init']), 'Plugin should register REST API hydration wiring.');
assert_true_lifecycle(isset($GLOBALS['mtb_actions']['save_post']), 'Plugin should register save_post for inline affiliate enrichment on save.');

$instanceReflection = new ReflectionClass('MTB_Affiliate_Plugin');
$instance = MTB_Affiliate_Plugin::instance();
$auditServiceProperty = $instanceReflection->getProperty('auditService');
assert_true_lifecycle(
    $auditServiceProperty->getValue($instance) instanceof MTB_Affiliate_Audit_Service,
    'Plugin should initialize the audit service for the admin audit tab.'
);

ob_start();
$instance->render_settings_page();
$settingsPageHtml = (string) ob_get_clean();

assert_true_lifecycle(
    str_contains($settingsPageHtml, '<h1>Konfiguration</h1>'),
    'Settings page should render the configuration heading.'
);
assert_true_lifecycle(
    str_contains($settingsPageHtml, 'type="hidden" name="mtb_affiliate_cards_settings[auto_shorten_titles]" value="0"'),
    'The Amazon form must explicitly submit false for an unchecked checkbox.'
);
$instance->register_settings_page();
assert_true_lifecycle(
    ($GLOBALS['mtb_submenus']['mtb-affiliate-cards'] ?? null) === [$instance, 'render_settings_page'],
    'Settings menu should link to the configuration page.'
);
assert_true_lifecycle(
    ($GLOBALS['mtb_submenus']['mtb-affiliate-cards-audit'] ?? null) === [$instance, 'render_audit_page'],
    'Audit menu should link to the audit page.'
);

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
    is_file(dirname(__DIR__) . '/assets/admin.css'),
    'Plugin repo should include admin styling for the Affiliate Audit tab.'
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

$partialCalls = [];
$partialTransport = static function (string $method, string $url, array $headers, ?array $body) use (&$partialCalls): array {
    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-partial']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        $partialCalls[] = $body['partner_tag'] ?? $body['partnerTag'] ?? null;
        return [200, ['itemsResult' => ['items' => [[
            'asin' => 'B0VALID001',
            'detailPageURL' => 'https://www.amazon.de/dp/B0VALID001?tag=meintechblog-260317-21',
            'images' => [],
            'itemInfo' => [
                'title' => ['displayValue' => 'Valid Product'],
            ],
        ]]]]];
    }

    return [500, ['message' => 'unexpected']];
};

$partialClient = new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $partialTransport);
$pluginForPartial = $pluginReflection->newInstanceWithoutConstructor();
$amazonClientProperty->setValue($pluginForPartial, $partialClient);

$partiallyResolved = $resolveMethod->invoke(
    $pluginForPartial,
    ['B0VALID001', 'INVALID123'],
    [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'marketplace' => 'www.amazon.de',
    ],
    (object) [
        'post_date' => '2026-03-17T10:38:49',
        'post_content' => '<p>Inline marker mix.</p>',
    ]
);

$GLOBALS['mtb_options']['mtb_affiliate_cards_settings'] = [
    'client_id' => 'client-id',
    'client_secret' => 'client-secret',
    'marketplace' => 'www.amazon.de',
    'cta_label' => 'Preis auf Amazon checken',
    'badge_mode' => 'auto',
    'auto_shorten_titles' => true,
];
$GLOBALS['mtb_update_posts'] = [];

$GLOBALS['mtb_posts'][20042] = (object) [
    'ID' => 20042,
    'post_date' => '2024-06-04 16:42:54',
    'post_content' => <<<HTML
<!-- wp:paragraph -->
<p><a href="https://www.amazon.de/dp/B0CP3ZLG7Y?tag=meintechblog-240604-21">ESP32 D1 Mini (Affiliate-Link)</a></p>
<!-- /wp:paragraph -->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0CP3ZLG7Y","title":"ESP32 D1 Mini","detail_url":"https://www.amazon.de/dp/B0CP3ZLG7Y?tag=meintechblog-240604-21","image_url":"","images":[],"benefit":""}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->
HTML,
];
$instance->handle_save_post(20042, get_post(20042), true);

assert_same_lifecycle(
    [],
    $GLOBALS['mtb_update_posts'],
    'save_post should leave existing plain Amazon links and affiliate blocks untouched when no explicit amazon: marker is present.'
);

assert_same_lifecycle(
    ['meintechblog-260317-21'],
    $partialCalls,
    'Save resolver should still try the derived tag for mixed valid and invalid inline markers.'
);

assert_same_lifecycle(
    1,
    count($partiallyResolved),
    'Save resolver should only return resolved items and never emit asin-only placeholders for unresolved markers.'
);

assert_same_lifecycle(
    'B0VALID001',
    $partiallyResolved[0]['asin'] ?? null,
    'Save resolver should keep the resolved item in output order.'
);

echo "ok\n";
