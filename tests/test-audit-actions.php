<?php

declare(strict_types=1);

$GLOBALS['mtb_options'] = [
    'mtb_affiliate_cards_settings' => [
        'cta_label' => 'Preis auf Amazon checken',
        'badge_mode' => 'auto',
        'auto_shorten_titles' => true,
        'marketplace' => 'www.amazon.de',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ],
];
$GLOBALS['mtb_post_meta'] = [];
$GLOBALS['mtb_updated_post'] = null;
$GLOBALS['mtb_redirect_to'] = null;
$GLOBALS['mtb_checked_nonce'] = null;
$GLOBALS['mtb_posts'] = [
    10 => (object) [
        'ID' => 10,
        'post_date' => '2026-03-18 10:00:00',
        'post_content' => '<!-- wp:paragraph --><p>Ich nutze amazon:B0VALID001 im Setup.</p><!-- /wp:paragraph -->',
        'post_status' => 'draft',
        'post_title' => 'Audit Test',
    ],
    11 => (object) [
        'ID' => 11,
        'post_date' => '2026-03-18 10:00:00',
        'post_content' => '<!-- wp:paragraph --><p>Ungültig amazon:INVALID123 im Setup.</p><!-- /wp:paragraph -->',
        'post_status' => 'draft',
        'post_title' => 'Audit Invalid Test',
    ],
    12 => (object) [
        'ID' => 12,
        'post_date' => '2025-11-07 14:08:39',
        'post_content' => '<!-- wp:paragraph --><p>Infos zu den Hintergründen: <a href="https://www.amazon.de/dp/B0VALID001?tag=meintechblog-251007-21">Valid Product (Affiliate-Link)</a>.</p><!-- /wp:paragraph -->' . "\n\n" .
            '<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0VALID001","title":"Valid Product","detail_url":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-251007-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"}],"detailUrl":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-251007-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"} /-->',
        'post_status' => 'publish',
        'post_title' => 'Audit Existing Link Mismatch',
    ],
    13 => (object) [
        'ID' => 13,
        'post_date' => '2025-07-18 14:32:04',
        'post_content' => '<!-- wp:paragraph --><p>Produkt: <a href="https://www.amazon.de/dp/B0VALID001?tag=meintechblog-250719-21">Valid Product (Affiliate-Link)</a>.</p><!-- /wp:paragraph -->' . "\n\n" .
            '<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0VALID001","title":"Valid Product","detail_url":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-250719-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"}],"detailUrl":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-250719-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"} /-->',
        'post_status' => 'publish',
        'post_title' => 'Audit Derived Tag Invalid',
    ],
    14 => (object) [
        'ID' => 14,
        'post_date' => '2017-10-31 15:00:27',
        'post_content' => '<!-- wp:paragraph --><p>Alt-Link: <a href="https://www.amazon.de/dp/B0VALID001?tag=meintechblog-171031-21">Legacy Product (Affiliate-Link)</a>.</p><!-- /wp:paragraph -->',
        'post_status' => 'publish',
        'post_title' => 'Audit Legacy Candidate',
    ],
];

function get_option(string $name, $default = false) {
    return $GLOBALS['mtb_options'][$name] ?? $default;
}

function update_option(string $name, $value): bool {
    $GLOBALS['mtb_options'][$name] = $value;
    return true;
}

function get_post(int $postId) {
    return $GLOBALS['mtb_posts'][$postId] ?? null;
}

function get_post_meta(int $postId, string $key, bool $single = false) {
    $value = $GLOBALS['mtb_post_meta'][$postId][$key] ?? ($single ? '' : []);
    return $single ? $value : [$value];
}

function update_post_meta(int $postId, string $key, $value): bool {
    $GLOBALS['mtb_post_meta'][$postId][$key] = $value;
    return true;
}

function wp_update_post(array $postarr): int {
    $GLOBALS['mtb_updated_post'] = $postarr;
    $postId = (int) ($postarr['ID'] ?? 0);
    if ($postId && isset($GLOBALS['mtb_posts'][$postId])) {
        $GLOBALS['mtb_posts'][$postId]->post_content = (string) ($postarr['post_content'] ?? $GLOBALS['mtb_posts'][$postId]->post_content);
    }
    return $postId;
}

function remove_action(string $hook, $callback, int $priority = 10): void {}
function add_action(string $hook, $callback, int $priority = 10, int $accepted_args = 1): void {}
function wp_is_post_revision(int $postId): bool { return false; }
function wp_is_post_autosave(int $postId): bool { return false; }
function current_user_can(string $capability): bool { return $capability === 'manage_options'; }
function check_admin_referer(string $action, string $name = '_wpnonce'): void { $GLOBALS['mtb_checked_nonce'] = [$action, $name]; }
function admin_url(string $path = ''): string { return '/wp-admin/' . ltrim($path, '/'); }
function add_query_arg(array $args, string $url): string {
    $separator = str_contains($url, '?') ? '&' : '?';
    return $url . $separator . http_build_query($args);
}
function wp_safe_redirect(string $url): bool { $GLOBALS['mtb_redirect_to'] = $url; return true; }

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-renderer.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-audit-service.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-block.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-rest-controller.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-plugin.php';

function assert_same_actions($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains_actions(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$transport = static function (string $method, string $url, array $headers, ?array $body): array {
    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-123']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        $itemIds = $body['itemIds'] ?? [];
        $partnerTag = (string) ($body['partnerTag'] ?? '');
        $items = [];
        foreach ($itemIds as $asin) {
            if ($asin !== 'B0VALID001') {
                continue;
            }
            if (! in_array($partnerTag, ['meintechblog-260318-21', 'meintechblog-251107-21', 'meintechblog-250719-21'], true)) {
                return [400, ['reason' => 'InvalidPartnerTag']];
            }
            $items[] = [
                'asin' => $asin,
                'detailPageURL' => 'https://www.amazon.de/dp/' . $asin . '?tag=' . $partnerTag,
                'images' => [],
                'itemInfo' => [
                    'title' => ['displayValue' => 'Valid Product'],
                ],
            ];
        }
        return [200, ['itemsResult' => ['items' => $items]]];
    }

    return [500, ['message' => 'unexpected']];
};

$pluginReflection = new ReflectionClass('MTB_Affiliate_Plugin');
$plugin = $pluginReflection->newInstanceWithoutConstructor();

$settingsProperty = $pluginReflection->getProperty('settings');
$settingsProperty->setValue($plugin, new MTB_Affiliate_Settings());
$auditServiceProperty = $pluginReflection->getProperty('auditService');
$auditServiceProperty->setValue($plugin, new MTB_Affiliate_Audit_Service());
$amazonClientProperty = $pluginReflection->getProperty('amazonClient');
$amazonClientProperty->setValue($plugin, new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $transport));
$blockProperty = $pluginReflection->getProperty('block');
$blockProperty->setValue($plugin, new MTB_Affiliate_Block(new MTB_Affiliate_Settings(), $amazonClientProperty->getValue($plugin)));
$restControllerProperty = $pluginReflection->getProperty('restController');
$restControllerProperty->setValue($plugin, new MTB_Affiliate_Rest_Controller(new MTB_Affiliate_Settings(), $amazonClientProperty->getValue($plugin)));

$auditMethod = $pluginReflection->getMethod('run_affiliate_audit');

$checked = $auditMethod->invoke($plugin, 10, false);
assert_same_actions('geprueft', $checked['status'] ?? null, 'Check action should mark valid content as geprueft.');
assert_same_actions(null, $GLOBALS['mtb_updated_post'], 'Check action must not update post content.');
assert_same_actions('geprueft', $GLOBALS['mtb_post_meta'][10]['_mtb_affiliate_audit']['status'] ?? null, 'Check action should persist audit meta.');

$legacyChecked = $auditMethod->invoke($plugin, 14, false);
assert_same_actions('legacy', $legacyChecked['status'] ?? null, 'Check action should mark old unresolved affiliate-only content as legacy when no safe card can be created.');

$GLOBALS['mtb_updated_post'] = null;
$straightened = $auditMethod->invoke($plugin, 10, true);
assert_same_actions('geradegezogen', $straightened['status'] ?? null, 'Straighten action should mark resolvable content as geradegezogen.');
assert_same_actions(10, $GLOBALS['mtb_updated_post']['ID'] ?? null, 'Straighten action should update the post when content changes.');
assert_contains_actions('Valid Product (Affiliate-Link)', $GLOBALS['mtb_posts'][10]->post_content, 'Straighten action should rewrite inline affiliate markers.');
assert_contains_actions('wp:meintechblog/affiliate-cards', $GLOBALS['mtb_posts'][10]->post_content, 'Straighten action should add an affiliate card block.');

$GLOBALS['mtb_updated_post'] = null;
$invalid = $auditMethod->invoke($plugin, 11, true);
assert_same_actions('manuell_pruefen', $invalid['status'] ?? null, 'Straighten action should flag unresolved content for manual review.');
assert_same_actions(null, $GLOBALS['mtb_updated_post'], 'Straighten action should not update content when no safe change is possible.');

$GLOBALS['mtb_updated_post'] = null;
$existingMismatch = $auditMethod->invoke($plugin, 12, true);
assert_same_actions('geradegezogen', $existingMismatch['status'] ?? null, 'Straighten action should normalize existing affiliate links and cards to the post-date tracking tag.');
assert_same_actions(12, $GLOBALS['mtb_updated_post']['ID'] ?? null, 'Straighten action should persist normalized tracking links for existing affiliate content.');
assert_contains_actions('https://www.amazon.de/dp/B0VALID001?tag=meintechblog-251107-21', $GLOBALS['mtb_posts'][12]->post_content, 'Straighten action should update inline Amazon links to the derived post-date tag.');
assert_contains_actions('detail_url":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-251107-21', $GLOBALS['mtb_posts'][12]->post_content, 'Straighten action should update stored affiliate-card URLs to the derived post-date tag.');

$GLOBALS['mtb_updated_post'] = null;
$existingValidFallback = $auditMethod->invoke($plugin, 13, true);
assert_same_actions('geradegezogen', $existingValidFallback['status'] ?? null, 'Straighten action should keep working content geradegezogen when the derived datestamp tag is invalid.');
assert_same_actions(13, $GLOBALS['mtb_updated_post']['ID'] ?? null, 'Straighten action should still persist content when it keeps the validated existing tag.');
assert_contains_actions('https://www.amazon.de/dp/B0VALID001?tag=meintechblog-250719-21', $GLOBALS['mtb_posts'][13]->post_content, 'Straighten action should preserve the validated existing inline Amazon tag when the post-date tag is invalid.');
assert_contains_actions('detail_url":"https://www.amazon.de/dp/B0VALID001?tag=meintechblog-250719-21', $GLOBALS['mtb_posts'][13]->post_content, 'Straighten action should preserve the validated existing affiliate-card URL when the post-date tag is invalid.');
assert_same_actions(
    false,
    str_contains($GLOBALS['mtb_posts'][13]->post_content, 'meintechblog-250718-21'),
    'Straighten action must not rewrite content to an invalid derived partner tag.'
);

$_POST = [
    'mtb_audit_task' => 'straighten',
    'post_id' => '10',
    'mtb_affiliate_audit_nonce' => 'nonce-value',
];
$GLOBALS['mtb_redirect_to'] = null;
$GLOBALS['mtb_checked_nonce'] = null;
$plugin->handle_audit_admin_post();

assert_same_actions(
    ['mtb_affiliate_audit_10_straighten', 'mtb_affiliate_audit_nonce'],
    $GLOBALS['mtb_checked_nonce'],
    'Admin handler should validate the per-row audit nonce.'
);
assert_same_actions(
    '/wp-admin/options-general.php?page=mtb-affiliate-cards&tab=audit&mtb-audit-post=10&mtb-audit-result=straighten&mtb-audit-status=geradegezogen',
    $GLOBALS['mtb_redirect_to'],
    'Admin handler should redirect back to the audit tab with the result payload.'
);

echo "ok\n";
