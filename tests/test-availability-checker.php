<?php

declare(strict_types=1);

/**
 * Tests for the Availability-Checker + the client's classify_items() (Codex-Refute RC1/RC3/RC7).
 * Plain-PHP harness like the other tests: stub WP funcs, assert, exit(1) on failure.
 * Covers exactly the risky bits the refute flagged: HTTP-status auth states, positive no-offer
 * classification, not_found-only-from-InvalidParameterValue, and hysteresis transitions.
 */

if (! defined('ARRAY_A'))                 { define('ARRAY_A', 'ARRAY_A'); }
if (! defined('WEEK_IN_SECONDS'))         { define('WEEK_IN_SECONDS', 604800); }
if (! defined('MTB_AFFILIATE_CARDS_VERSION')) { define('MTB_AFFILIATE_CARDS_VERSION', '0.4.0-test'); }

$GLOBALS['mtb_options'] = [];

if (! function_exists('register_rest_route')) {
    $GLOBALS['mtb_routes'] = [];
    function register_rest_route($ns, $route, $args) { $GLOBALS['mtb_routes'][] = [$ns, $route, $args]; return true; }
}
if (! function_exists('current_user_can')) { function current_user_can($c) { return true; } }
if (! function_exists('get_option'))   { function get_option($k, $d = false) { return $GLOBALS['mtb_options'][$k] ?? $d; } }
if (! function_exists('update_option')){ function update_option($k, $v, $a = null) { $GLOBALS['mtb_options'][$k] = $v; return true; } }
if (! function_exists('delete_option')){ function delete_option($k) { unset($GLOBALS['mtb_options'][$k]); return true; } }
if (! function_exists('get_transient')){ function get_transient($k) { return $GLOBALS['mtb_tr'][$k] ?? false; } }
if (! function_exists('set_transient')){ function set_transient($k, $v, $t) { $GLOBALS['mtb_tr'][$k] = $v; return true; } }
if (! function_exists('delete_transient')){ function delete_transient($k) { unset($GLOBALS['mtb_tr'][$k]); return true; } }
if (! function_exists('wp_json_encode')){ function wp_json_encode($d) { return json_encode($d); } }

if (! class_exists('WP_REST_Request')) {
    class WP_REST_Request {
        private array $p;
        public function __construct(array $p = []) { $this->p = $p; }
        public function get_param($k) { return $this->p[$k] ?? null; }
    }
}
if (! class_exists('WP_REST_Response')) {
    class WP_REST_Response { public $data; public int $status;
        public function __construct($d = null, int $s = 200) { $this->data = $d; $this->status = $s; } }
}

/** Stateful fake $wpdb: stores availability rows keyed by ASIN. */
class MTB_Fake_Avail_Wpdb {
    public string $prefix = 'wp_';
    public array $rows = [];        // asin => row
    public array $replaced = [];    // replaced asins
    public function get_charset_collate(): string { return ''; }
    public function prepare($sql, ...$args) {
        foreach ($args as $a) {
            $sql = preg_replace('/%[ds]/', is_int($a) ? (string) $a : "'" . $a . "'", $sql, 1);
        }
        return $sql;
    }
    private function asin_from(string $sql): string {
        return preg_match("/'([A-Z0-9]{10})'/", $sql, $m) ? $m[1] : '';
    }
    public function get_row($sql, $out = null) {
        $a = $this->asin_from($sql);
        return $this->rows[$a] ?? null;
    }
    public function get_col($sql) { return $this->replaced; }
    public function get_results($sql, $out = null) { return array_values($this->rows); }
    public function insert($table, $data) { $this->rows[$data['asin']] = $data; return 1; }
    public function update($table, $data, $where) {
        $a = $where['asin'];
        $this->rows[$a] = array_merge($this->rows[$a] ?? ['asin' => $a], $data);
        return 1;
    }
    public function query($sql) { return 1; }
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-availability-checker.php';

function assert_true($cond, string $msg): void {
    if (! $cond) { fwrite(STDERR, "FAIL: $msg\n"); exit(1); }
    echo "ok: $msg\n";
}

/** Build a client with a scripted transport. $catalog = [status, payload]; $token = [status, payload]. */
function make_client(array $token, array $catalog): MTB_Affiliate_Amazon_Client {
    $transport = function (string $method, string $url, array $headers, ?array $body) use ($token, $catalog): array {
        if (str_contains($url, 'o2/token')) { return $token; }
        return $catalog;
    };
    return new MTB_Affiliate_Amazon_Client(null, $transport);
}

$ctx = ['client_id' => 'cid', 'client_secret' => 'sec', 'marketplace' => 'www.amazon.de', 'partner_tag' => 'meintechblog-230807-21'];
$okToken = [200, ['access_token' => 't']];

// ============ A) classify_items() auth-state machine (RC1/RC2) =================
$c = make_client($okToken, [200, []]);
$r = $c->classify_items([], $ctx);
assert_true($r['auth_state'] === 'config_error', 'empty asins -> config_error');

$c = make_client($okToken, [200, []]);
$r = $c->classify_items(['B07ZQMG51R'], ['client_id' => '', 'client_secret' => '', 'partner_tag' => '']);
assert_true($r['auth_state'] === 'config_error', 'empty creds -> config_error (stub guard)');

$c = make_client([400, []], [200, []]);
$r = $c->classify_items(['B07ZQMG51R'], $ctx);
assert_true($r['auth_state'] === 'token_failed', 'token non-200 -> token_failed');

$c = make_client($okToken, [403, []]);
$r = $c->classify_items(['B07ZQMG51R'], $ctx);
assert_true($r['auth_state'] === 'access_revoked', 'catalog 403 -> access_revoked (the BLOCKER fix)');

$c = make_client($okToken, [429, []]);
$r = $c->classify_items(['B07ZQMG51R'], $ctx);
assert_true($r['auth_state'] === 'rate_limited', 'catalog 429 -> rate_limited');

$c = make_client($okToken, [503, []]);
$r = $c->classify_items(['B07ZQMG51R'], $ctx);
assert_true($r['auth_state'] === 'transient_error', 'catalog 5xx -> transient_error');

// ============ A2) classify_items() payload parsing ============================
$payload = ['itemsResult' => ['items' => [
    ['asin' => 'B07ZQMG51R', 'itemInfo' => ['title' => ['displayValue' => 'DS18B20']],
     'offersV2' => ['listings' => [['price' => ['displayAmount' => '9,99 €']]]]],
    ['asin' => 'BNOPRICE01', 'itemInfo' => ['title' => ['displayValue' => 'BuyBox, no price (MAP)']],
     'offersV2' => ['listings' => [['isBuyBoxWinner' => true]]]], // live listing, NO price (real canary case)
    ['asin' => 'BSOLDOUT01', 'itemInfo' => ['title' => ['displayValue' => 'Sold out']],
     'offersV2' => ['listings' => []]],                       // offers present, empty
    ['asin' => 'BNOOFFER01', 'itemInfo' => ['title' => ['displayValue' => 'No offers node']]], // offers absent
]]];
$payload['errors'] = [
    ['code' => 'InvalidParameterValue', 'message' => 'The ItemId BTYPO00000 provided is invalid'],
    ['code' => 'ItemNotAccessible',     'message' => 'B0BLOCK0AA is not accessible via the API'],
];
$c = make_client($okToken, [200, $payload]);
$r = $c->classify_items(['B07ZQMG51R', 'BNOPRICE01', 'BSOLDOUT01', 'BNOOFFER01', 'BTYPO00000', 'B0BLOCK0AA'], $ctx, true);
assert_true($r['auth_state'] === 'ok', 'catalog 200 -> ok');
assert_true($r['items']['B07ZQMG51R']['price_text'] === '9,99 €', 'price parsed from offersV2.listings.price');
assert_true($r['items']['B07ZQMG51R']['has_listing'] === true, 'listing present -> has_listing true');
assert_true($r['items']['BNOPRICE01']['has_listing'] === true && $r['items']['BNOPRICE01']['price_text'] === null, 'live listing w/o price: has_listing true, price null');
assert_true($r['items']['BSOLDOUT01']['offers_present'] === true && $r['items']['BSOLDOUT01']['has_listing'] === false, 'sold-out: offers present, listings empty -> has_listing false');
assert_true($r['items']['BNOOFFER01']['offers_present'] === false, 'no offers node -> offers_present false');
assert_true(($r['errors']['BTYPO00000'] ?? '') === 'InvalidParameterValue', 'error ASIN recovered from message text');
assert_true(($r['errors']['B0BLOCK0AA'] ?? '') === 'ItemNotAccessible', 'ItemNotAccessible captured');

// ============ B) classify_asin() — the 5 classes (RC3) ========================
$GLOBALS['wpdb'] = new MTB_Fake_Avail_Wpdb();
$checker = new MTB_Affiliate_Availability_Checker(make_client($okToken, [200, $payload]));
assert_true($checker->classify_asin('B07ZQMG51R', $r) === 'available', 'available: item + listing + price');
assert_true($checker->classify_asin('BNOPRICE01', $r) === 'available', 'available: live listing even WITHOUT price (the canary fix)');
assert_true($checker->classify_asin('BSOLDOUT01', $r) === 'unavailable_temp', 'unavailable_temp: offers present but listings empty');
assert_true($checker->classify_asin('BNOOFFER01', $r) === 'error', 'offers absent/unparseable -> error (NOT unavailable_temp)');
assert_true($checker->classify_asin('BTYPO00000', $r) === 'not_found', 'not_found ONLY from InvalidParameterValue');
assert_true($checker->classify_asin('B0BLOCK0AA', $r) === 'api_inaccessible', 'ItemNotAccessible -> api_inaccessible (never dead)');
assert_true($checker->classify_asin('BMISSING00', $r) === 'error', 'requested but absent + no error -> error (ambiguous)');

// ============ C) hysteresis via record_status() (RC1/RC7) =====================
$GLOBALS['wpdb'] = new MTB_Fake_Avail_Wpdb();
$wpdb = $GLOBALS['wpdb'];
$checker = new MTB_Affiliate_Availability_Checker(make_client($okToken, [200, []]));
$rm = new ReflectionMethod($checker, 'record_status');
$rm->setAccessible(true);

// three consecutive unavailable_temp -> count 3 = repair candidate
$rm->invoke($checker, 'BSOLDOUT01', 'unavailable_temp', []);
assert_true($wpdb->rows['BSOLDOUT01']['consecutive_unavailable_count'] === 1, 'unavailable scan 1 -> count 1');
$rm->invoke($checker, 'BSOLDOUT01', 'unavailable_temp', []);
$rm->invoke($checker, 'BSOLDOUT01', 'unavailable_temp', []);
assert_true($wpdb->rows['BSOLDOUT01']['consecutive_unavailable_count'] === 3, 'three consecutive -> count 3 (candidate)');

// available resets the streak + status
$rm->invoke($checker, 'BSOLDOUT01', 'available', ['price_text' => '5,00 €']);
assert_true($wpdb->rows['BSOLDOUT01']['status'] === 'available' && $wpdb->rows['BSOLDOUT01']['consecutive_unavailable_count'] === 0, 'available resets count + status');

// transient error must NOT clobber the good status (the parser-shape-mismatch safety)
$rm->invoke($checker, 'BSOLDOUT01', 'error', ['http_note' => 'transient_503']);
assert_true($wpdb->rows['BSOLDOUT01']['status'] === 'available', 'error keeps last good status (available)');
assert_true($wpdb->rows['BSOLDOUT01']['http_note'] === 'transient_503', 'error updates http_note only');

// replaced is terminal — a later scan never downgrades it
$wpdb->rows['BREPLACED1'] = ['asin' => 'BREPLACED1', 'status' => 'replaced', 'consecutive_unavailable_count' => 0];
$rm->invoke($checker, 'BREPLACED1', 'not_found', []);
assert_true($wpdb->rows['BREPLACED1']['status'] === 'replaced', 'replaced status is terminal');

// ============ D) routes register without fatal ================================
$GLOBALS['mtb_routes'] = [];
$checker->register_routes();
assert_true(count($GLOBALS['mtb_routes']) === 3, 'registers 3 availability routes');

echo "\nALL AVAILABILITY-CHECKER TESTS PASSED\n";
