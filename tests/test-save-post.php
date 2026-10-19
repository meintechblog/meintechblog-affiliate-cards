<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap-plugin.php';
require_once __DIR__ . '/support-post-saving.php';
foreach (['settings', 'title-shortener', 'token-scanner', 'post-processor', 'audit-service', 'amazon-client', 'plugin'] as $dependency) {
    require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-' . $dependency . '.php';
}

function get_option(string $option, $default = false) { return $GLOBALS['mtb_options'][$option] ?? $default; }
function update_post_meta(int $postId, string $key, $value): bool { return true; }
function parse_blocks(string $content): array {
    $GLOBALS['mtb_last_parsed_content'] = $content;
    preg_match_all('~<!-- wp:meintechblog/affiliate-cards (\{.*?\}) (?:/-->|-->)~s', $content, $matches);
    return array_map(static fn(string $json): array => [
        'blockName' => 'meintechblog/affiliate-cards', 'attrs' => json_decode($json, true),
    ], $matches[1]);
}

class Save_Test_Library extends MTB_Affiliate_Product_Library {
    public array $synced = [];
    public function get_last(int $n = 1): ?array { return null; }
    public function update_by_asin(string $asin, array $fields): bool {
        $this->synced[$asin] = $fields;
        return true;
    }
}

function same_save($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        throw new RuntimeException($message . '\nExpected: ' . var_export($expected, true) . '\nActual: ' . var_export($actual, true));
    }
}

$reflection = new ReflectionClass(MTB_Affiliate_Plugin::class);
$plugin = $reflection->newInstanceWithoutConstructor();
$library = new Save_Test_Library();
foreach ([
    'settings' => new MTB_Affiliate_Settings(),
    'amazonClient' => new MTB_Affiliate_Amazon_Client(),
    'productLibrary' => $library,
    'auditService' => new MTB_Affiliate_Audit_Service(),
] as $property => $value) {
    $reflection->getProperty($property)->setValue($plugin, $value);
}
add_action('save_post', [$plugin, 'handle_save_post'], 20, 3);
add_action('save_post', [$plugin, 'handle_save_post_sync_library'], 30, 2);

function save_test_content(string $content): string {
    global $plugin;
    $GLOBALS['mtb_posts'][42] = (object) ['ID' => 42, 'post_date' => '2026-10-19 12:00:00', 'post_content' => $content];
    do_action('save_post', 42, get_post(42), true);
    $saved = get_post(42)->post_content;
    $writes = count($GLOBALS['mtb_update_posts'] ?? []);
    do_action('save_post', 42, get_post(42), true);
    same_save($saved, get_post(42)->post_content, 'Saving twice must be byte-idempotent.');
    same_save($writes, count($GLOBALS['mtb_update_posts'] ?? []), 'Second save must not write again.');
    return $saved;
}

$base = 'https://www.amazon.de/dp/B0VALID001';
$tag = 'meintechblog-261019-21';
foreach ([
    '?tag=meintechblog-240101-21&x=a+b#keep',
    '?a=1&amp;tag=foreign-21&b=%2f#keep',
    '?a=1\\u0026tag=other-21\\u0026b=2',
    '?tag=&a=1',
    '?%74ag=encoded-21',
    '?tag=foreign-21&tag=another-21',
] as $query) {
    $content = '<p><a href="' . $base . $query . '">Alt</a></p>';
    $writes = count($GLOBALS['mtb_update_posts'] ?? []);
    same_save($content, save_test_content($content), 'Any existing tag must stay byte-identical.');
    same_save($writes, count($GLOBALS['mtb_update_posts'] ?? []), 'Tagged links must not trigger writes.');
}
foreach ([
    ['', '?tag=' . $tag],
    ['?', '?tag=' . $tag],
    ['#fragment', '?tag=' . $tag . '#fragment'],
    ['?a=a+b&b=%2f#f', '?a=a+b&b=%2f&tag=' . $tag . '#f'],
    ['?a=1&amp;b=2#f', '?a=1&amp;b=2&amp;tag=' . $tag . '#f'],
    ['?a=1\\u0026b=2#f', '?a=1\\u0026b=2\\u0026tag=' . $tag . '#f'],
    ['?a=1&amp;b=2&c=3\\u0026d=4', '?a=1&amp;b=2&c=3\\u0026d=4&amp;tag=' . $tag],
    ['?notag=1#tag=fragment', '?notag=1&tag=' . $tag . '#tag=fragment'],
] as [$before, $after]) {
    foreach ([$base, 'http://amazon.de/gp/product/B0VALID001'] as $url) {
        $content = '<p><a href="' . $url . $before . '">Neu</a></p>';
        same_save('<p><a href="' . $url . $after . '">Neu</a></p>', save_test_content($content), 'Only append a missing tag; preserve query and fragment.');
    }
}
same_save('<p>https://amazon.com/dp/B0VALID001</p>', save_test_content('<p>https://amazon.com/dp/B0VALID001</p>'), 'Other marketplaces remain unchanged.');

$escaped = <<<'HTML'
<!-- wp:html -->
<script>const text = "Zitat: \"Hallo\""; const url = "a\u0026b";</script>
<!-- /wp:html -->
<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"OLDASIN001","title":"Alt \"Zitat\"","detail_url":"https://www.amazon.de/dp/OLDASIN001?tag=foreign-21\u0026x=1"}]} /-->
HTML;
$tokenInput = $escaped . "\n<p>amazon:B0VALID001</p>\n<a href=\"$base\">Neu</a>";
$saved = save_test_content($tokenInput);
same_save(true, str_contains($saved, $escaped), 'Token conversion must preserve backslashes, escaped quotes and old card data.');
same_save(true, str_contains($saved, $base . '?tag=' . $tag), 'Priority 30 must tag links using freshly reloaded content.');
same_save(2, substr_count($saved, '<!-- wp:meintechblog/affiliate-cards'), 'Priority 30 must not overwrite the card added at priority 20.');
same_save('Alt "Zitat"', $library->synced['OLDASIN001']['title'] ?? null, 'Library sync must see existing block data intact.');
same_save(true, str_contains($GLOBALS['mtb_last_parsed_content'], '"asin":"B0VALID001"'), 'Library sync must see the new card from priority 20.');

$emptyTokenInput = $escaped . "\n<p>amazon:last</p>";
$expected = (new MTB_Affiliate_Token_Prepass($library))->resolve($emptyTokenInput);
same_save($expected, save_test_content($emptyTokenInput), 'The empty-token save path must preserve backslashes.');
same_save(1, $GLOBALS['mtb_max_save_depth'], 'Internal saves must not recursively call either own callback.');
same_save(true, $GLOBALS['mtb_update_error_requested'], 'All writes must request WP_Error results.');

// Each callback must reload even when save_post passes a stale object.
$GLOBALS['mtb_posts'][42]->post_content = '<p>amazon:B0FRESH001</p>';
$plugin->handle_save_post(42, (object) ['post_content' => '<p>Stale</p>'], true);
same_save(true, str_contains(get_post(42)->post_content, '"asin":"B0FRESH001"'), 'Token hook must reload current content.');
$GLOBALS['mtb_posts'][42]->post_content = '<p><a href="' . $base . '">Neu</a></p>';
$plugin->handle_save_post_sync_library(42, (object) ['post_content' => '<p>Stale</p>']);
same_save(true, str_contains(get_post(42)->post_content, '?tag=' . $tag), 'Sync hook must reload current content.');

foreach (['mtb_autosaves', 'mtb_revisions'] as $flag) {
    $GLOBALS[$flag][42] = true;
    $input = '<p>amazon:B0VALID001</p><p><a href="' . $base . '">Neu</a></p>';
    same_save($input, save_test_content($input), 'Both callbacks must skip autosaves and revisions.');
    unset($GLOBALS[$flag][42]);
}

$save = $reflection->getMethod('save_post_content');
$callbacks = $GLOBALS['mtb_save_actions'];
$original = get_post(42)->post_content;
$GLOBALS['mtb_update_error'] = true;
same_save(false, $save->invoke($plugin, 42, 'Changed'), 'WP_Error must return false.');
same_save($original, get_post(42)->post_content, 'Failed saves must not change stored content.');
same_save($callbacks, $GLOBALS['mtb_save_actions'], 'WP_Error must restore both callbacks with priorities and argument counts.');
unset($GLOBALS['mtb_update_error']);
$GLOBALS['mtb_update_exception'] = true;
try {
    $save->invoke($plugin, 42, 'Changed');
    throw new LogicException('Expected simulated failure.');
} catch (RuntimeException $exception) {
    same_save('Simulierter Speicherfehler', $exception->getMessage(), 'Exceptions must propagate.');
}
same_save($callbacks, $GLOBALS['mtb_save_actions'], 'Exceptions must also restore both callbacks.');
unset($GLOBALS['mtb_update_exception']);
same_save(false, $save->invoke($plugin, 999, 'Missing'), 'Missing posts must return false.');

// Audit uses the same slashed write path and must preserve foreign/date tags.
$GLOBALS['mtb_posts'][42]->post_content = $escaped . '<p><a href="' . $base . '">Valid Product (Affiliate-Link)</a></p>';
$audit = $reflection->getMethod('run_affiliate_audit');
$audit->invoke($plugin, 42, true);
$auditSaved = get_post(42)->post_content;
same_save(true, str_contains($auditSaved, $escaped), 'Audit must preserve existing card bytes and tagged URLs.');
same_save(true, str_contains($auditSaved, '?tag=' . $tag), 'Audit must add missing tags.');
$audit->invoke($plugin, 42, true);
same_save($auditSaved, get_post(42)->post_content, 'Audit straightening must be idempotent.');

$GLOBALS['mtb_posts'][42]->post_content = '<a href="' . $base . '">Neu</a>';
$GLOBALS['mtb_update_error'] = true;
$failedAudit = $audit->invoke($plugin, 42, true);
same_save('fehler', $failedAudit['status'], 'Audit must report a failed write.');
same_save('<a href="' . $base . '">Neu</a>', get_post(42)->post_content, 'Audit must not pretend failed changes were saved.');
unset($GLOBALS['mtb_update_error']);

echo "ok\n";
