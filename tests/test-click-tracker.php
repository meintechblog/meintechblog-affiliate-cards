<?php

declare(strict_types=1);

/**
 * Tests for MTB_Affiliate_Click_Tracker (validation, rate-limit, atomic upsert SQL, query build).
 * Plain-PHP harness like the other tests: stub WP funcs, assert, exit(1) on failure.
 */

// --- minimal WP stubs ---------------------------------------------------------
if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}
if (! defined('MTB_AFFILIATE_CARDS_VERSION')) {
    define('MTB_AFFILIATE_CARDS_VERSION', '0.3.0-test');
}
if (! defined('MTB_AFFILIATE_CARDS_URL')) {
    define('MTB_AFFILIATE_CARDS_URL', 'https://example.test/wp-content/plugins/mtb/');
}

$GLOBALS['mtb_post_status'] = ['publish']; // post_id => status handled below
$GLOBALS['mtb_transients']  = [];

if (! function_exists('register_rest_route')) {
    $GLOBALS['mtb_routes'] = [];
    function register_rest_route($ns, $route, $args) {
        $GLOBALS['mtb_routes'][] = [$ns, $route, $args];
        return true;
    }
}
if (! function_exists('current_user_can')) {
    function current_user_can($cap) { return $GLOBALS['mtb_can'] ?? true; }
}
if (! function_exists('get_post_status')) {
    function get_post_status($id) { return $GLOBALS['mtb_post_statuses'][$id] ?? false; }
}
if (! function_exists('get_transient')) {
    function get_transient($k) { return $GLOBALS['mtb_transients'][$k] ?? false; }
}
if (! function_exists('set_transient')) {
    function set_transient($k, $v, $ttl) { $GLOBALS['mtb_transients'][$k] = $v; return true; }
}

// WP_REST_Request / Response stubs
if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $params;
        public function __construct(array $params = []) { $this->params = $params; }
        public function get_param($k) { return $this->params[$k] ?? null; }
    }
}
if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response {
        public $data; public int $status;
        public function __construct($data = null, int $status = 200) { $this->data = $data; $this->status = $status; }
    }
}

// Fake $wpdb capturing queries
class MTB_Fake_Wpdb {
    public string $prefix = 'wp_';
    public array $queries = [];
    public $insert_id = 1;
    public function get_charset_collate(): string { return ''; }
    public function prepare($sql, ...$args) {
        // crude but good enough: positional substitution for assertion
        foreach ($args as $a) {
            $sql = preg_replace('/%[ds]/', is_int($a) ? (string)$a : "'" . $a . "'", $sql, 1);
        }
        return $sql;
    }
    public function query($sql) { $this->queries[] = $sql; return 1; }
    public function get_results($sql, $output = null) { $this->queries[] = $sql; return []; }
}

$GLOBALS['mtb_post_statuses'] = [42 => 'publish', 99 => 'draft'];
$GLOBALS['wpdb'] = new MTB_Fake_Wpdb();

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-click-tracker.php';

// --- assert helpers -----------------------------------------------------------
function assert_true($cond, string $msg): void {
    if (! $cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
    echo "ok: $msg\n";
}

// --- tests --------------------------------------------------------------------
$tracker = new MTB_Affiliate_Click_Tracker();

// routes register without fatal
$tracker->register_routes();
assert_true(count($GLOBALS['mtb_routes']) === 2, 'registers 2 routes (/click, /clicks)');
assert_true($GLOBALS['mtb_routes'][0][2]['permission_callback'] === '__return_true', '/click is public');

// bad ASIN -> not counted
$GLOBALS['wpdb'] = new MTB_Fake_Wpdb();
$tracker = new MTB_Affiliate_Click_Tracker();
$resp = $tracker->handle_click(new WP_REST_Request(['asin' => 'NOTANASIN!!', 'postId' => 42]));
assert_true($resp->data['ok'] === false && $resp->data['reason'] === 'bad_asin', 'bad ASIN rejected');
assert_true($GLOBALS['wpdb']->queries === [], 'bad ASIN writes nothing');

// non-published post -> rejected
$resp = $tracker->handle_click(new WP_REST_Request(['asin' => 'B07ZQMG51R', 'postId' => 99]));
assert_true($resp->data['ok'] === false && $resp->data['reason'] === 'bad_post', 'draft post rejected');

// unknown post -> rejected
$resp = $tracker->handle_click(new WP_REST_Request(['asin' => 'B07ZQMG51R', 'postId' => 12345]));
assert_true($resp->data['ok'] === false && $resp->data['reason'] === 'bad_post', 'unknown post rejected');

// valid -> ok + atomic upsert issued
$GLOBALS['wpdb'] = new MTB_Fake_Wpdb();
$GLOBALS['mtb_transients'] = [];
$resp = $tracker->handle_click(new WP_REST_Request(['asin' => 'b07zqmg51r', 'postId' => 42]));
assert_true($resp->data['ok'] === true, 'valid click accepted');
assert_true(count($GLOBALS['wpdb']->queries) === 1, 'one DB write for valid click');
$sql = $GLOBALS['wpdb']->queries[0];
assert_true(str_contains($sql, 'ON DUPLICATE KEY UPDATE clicks = clicks + 1'), 'uses atomic upsert');
assert_true(str_contains($sql, "'B07ZQMG51R'"), 'asin upper-cased in upsert');
assert_true(str_contains($sql, '42'), 'post_id in upsert');

// rate limit: after RL_MAX_PER_WINDOW (40) -> rate_limited
$_SERVER['REMOTE_ADDR'] = '203.0.113.5'; // per-IP limiter needs an IP
$GLOBALS['wpdb'] = new MTB_Fake_Wpdb();
$GLOBALS['mtb_transients'] = [];
$last = null;
for ($i = 0; $i < 42; $i++) {
    $last = $tracker->handle_click(new WP_REST_Request(['asin' => 'B07ZQMG51R', 'postId' => 42]));
}
assert_true($last->data['ok'] === false && $last->data['reason'] === 'rate_limited', 'rate limit kicks in after cap');

// get_clicks builds a grouped query and returns shape
$GLOBALS['wpdb'] = new MTB_Fake_Wpdb();
$resp = $tracker->get_clicks(new WP_REST_Request(['post_id' => 42]));
assert_true(isset($resp->data['items']) && isset($resp->data['count']), 'get_clicks returns items+count');

echo "\nALL CLICK-TRACKER TESTS PASSED\n";
