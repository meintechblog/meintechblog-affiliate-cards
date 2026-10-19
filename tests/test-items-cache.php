<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-availability-checker.php';

// Expiring, request-independent WordPress cache stub; advance time without sleeping.
if (! function_exists('get_transient')) {
    function get_transient($key) {
        $entry = $GLOBALS['items_cache_transients'][$key] ?? null;
        if ($entry === null || $entry['expires_at'] <= $GLOBALS['items_cache_now']) {
            delete_transient($key);
            return false;
        }
        return $entry['value'];
    }
}
if (! function_exists('set_transient')) {
    function set_transient($key, $value, $ttl) {
        $GLOBALS['items_cache_transients'][$key] = [
            'value' => $value,
            'ttl' => $ttl,
            'expires_at' => $GLOBALS['items_cache_now'] + $ttl,
        ];
        return true;
    }
}
if (! function_exists('delete_transient')) {
    function delete_transient($key) {
        unset($GLOBALS['items_cache_transients'][$key]);
        return true;
    }
}
if (! function_exists('apply_filters')) {
    function apply_filters($hook, $value) {
        return $hook === 'mtb_affiliate_items_cache_ttl'
            ? ($GLOBALS['items_cache_ttl'] ?? $value) : $value;
    }
}

function assert_same_cache($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true)
            . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function reset_items_cache(): array {
    $GLOBALS['items_cache_transients'] = [];
    $GLOBALS['items_cache_now'] = time();
    $GLOBALS['items_cache_ttl'] = null;
    $items = [];
    foreach (['B0CACHE001', 'B0CACHE002', 'B0CACHE003'] as $asin) {
        $items[$asin] = [
            'asin' => $asin,
            'detailPageURL' => 'https://www.amazon.de/dp/' . $asin,
            'itemInfo' => ['title' => ['displayValue' => 'Produkt ' . $asin]],
            'images' => ['primary' => ['large' => ['url' => 'https://images.example/' . $asin . '.jpg']]],
            'offersV2' => ['listings' => [['price' => ['displayAmount' => '19,99 EUR']]]],
        ];
    }
    return [
        'calls' => [], 'catalog_calls' => [], 'token_calls' => [], 'items' => $items,
        'catalog_status' => 200, 'catalog_error' => null, 'token_status' => 200,
        'token_payload' => ['access_token' => 'cached-token', 'expires_in' => 3600],
    ];
}

function items_cache_client(array &$state): MTB_Affiliate_Amazon_Client {
    return new MTB_Affiliate_Amazon_Client(null, static function (
        string $method, string $url, array $headers, ?array $body
    ) use (&$state): array {
        $state['calls'][] = compact('method', 'url', 'headers', 'body');
        if (str_contains($url, '/o2/token')) {
            $state['token_calls'][] = $body;
            return [$state['token_status'], $state['token_payload']];
        }
        $state['catalog_calls'][] = $body;
        if ($state['catalog_error'] !== null) {
            throw $state['catalog_error'];
        }
        $items = [];
        foreach ($body['itemIds'] as $asin) {
            if (isset($state['items'][$asin])) {
                $item = $state['items'][$asin];
                $item['detailPageURL'] = ($item['detailPageURL'] ?? ('https://www.amazon.de/dp/' . $asin))
                    . '?tag=' . $body['partnerTag'];
                $items[] = $item;
            }
        }
        return [$state['catalog_status'], ['itemsResult' => ['items' => $items]]];
    });
}

function cache_entries_with_ttl(int $ttl): array {
    return array_filter($GLOBALS['items_cache_transients'], static fn(array $entry): bool => $entry['ttl'] === $ttl);
}

$context = [
    'client_id' => 'cache-client-id', 'client_secret' => 'never-store-this-secret',
    'marketplace' => 'www.amazon.de', 'partner_tag' => 'meintechblog-260317-21',
];
$a = 'B0CACHE001';
$b = 'B0CACHE002';
$c = 'B0CACHE003';

// A fresh client simulates the next page request. Warm hits require NO transport at all.
$state = reset_items_cache();
assert_same_cache([], items_cache_client($state)->get_items([], $context), 'Empty input stays empty.');
assert_same_cache([], $state['calls'], 'Empty input must not contact Amazon.');
$first = items_cache_client($state)->get_items([$a], $context);
assert_same_cache([
    'asin' => $a, 'title' => 'Produkt ' . $a,
    'image_url' => 'https://images.example/' . $a . '.jpg',
    'images' => ['https://images.example/' . $a . '.jpg'],
    'detail_url' => 'https://www.amazon.de/dp/' . $a . '?tag=' . $context['partner_tag'],
    'price_text' => '19,99 EUR', 'benefit' => '',
], $first[0], 'The public mapped item format must remain unchanged.');
assert_same_cache(1, count(cache_entries_with_ttl(86400)), 'Fresh data defaults to 24 hours.');
assert_same_cache(1, count(cache_entries_with_ttl(604800)), 'Last good data lasts seven days.');
$before = count($state['calls']);
assert_same_cache($first, items_cache_client($state)->get_items([$a], $context), 'Warm hit returns identical data.');
assert_same_cache($before, count($state['calls']), 'Warm hit must bypass token AND catalog transport.');
echo "ok: items cache hit and unchanged format\n";

// Only misses go to Amazon. Preserve requested order, duplicates/empty input normalization.
$mixed = items_cache_client($state)->get_items([$b, $a, $b, '', $c], $context);
assert_same_cache([$b, $c], $state['catalog_calls'][1]['itemIds'], 'Only uncached ASINs go to Amazon.');
assert_same_cache([$b, $a, $c], array_column($mixed, 'asin'), 'Merge results in requested order.');
assert_same_cache($first[0], $mixed[1], 'Mixed results preserve the complete cached item.');
$before = count($state['calls']);
assert_same_cache($mixed, items_cache_client($state)->get_items([$b, $a, $c], $context), 'Full warm batch is identical.');
assert_same_cache($before, count($state['calls']), 'Full warm batch bypasses transport.');

$otherTag = array_replace($context, ['partner_tag' => 'other-tag-21']);
$tagItems = items_cache_client($state)->get_items([$a], $otherTag);
assert_same_cache(true, str_contains($tagItems[0]['detail_url'], 'other-tag-21'), 'Partner tags must not share cached links.');
$otherMarket = array_replace($context, ['marketplace' => 'www.amazon.fr']);
items_cache_client($state)->get_items([$a], $otherMarket);
assert_same_cache('www.amazon.fr', $state['catalog_calls'][3]['marketplace'], 'Marketplaces must not share cached data.');
echo "ok: partial hits, order and cache scope\n";

// Expire the real default TTL and observe the refreshed price and renewed fallback.
$state = reset_items_cache();
items_cache_client($state)->get_items([$a], $context);
$GLOBALS['items_cache_now'] += 86399;
items_cache_client($state)->get_items([$a], $context);
assert_same_cache(1, count($state['catalog_calls']), 'Fresh data remains valid before expiry.');
$GLOBALS['items_cache_now']++;
$state['items'][$a]['offersV2']['listings'][0]['price']['displayAmount'] = '29,99 EUR';
$updated = items_cache_client($state)->get_items([$a], $context);
assert_same_cache(2, count($state['catalog_calls']), 'Expired data must be fetched again.');
assert_same_cache('29,99 EUR', $updated[0]['price_text'], 'Refresh returns changed data.');

$state = reset_items_cache();
$GLOBALS['items_cache_ttl'] = 120;
items_cache_client($state)->get_items([$a], $context);
assert_same_cache(1, count(cache_entries_with_ttl(120)), 'The TTL filter controls positive entries.');
$GLOBALS['items_cache_now'] += 120;
items_cache_client($state)->get_items([$a], $context);
assert_same_cache(2, count($state['catalog_calls']), 'Filtered TTL expires normally.');
foreach ([0, -1] as $ttl) {
    $state = reset_items_cache();
    $GLOBALS['items_cache_ttl'] = $ttl;
    items_cache_client($state)->get_items([$a], $context);
    items_cache_client($state)->get_items([$a], $context);
    assert_same_cache(2, count($state['catalog_calls']), 'Non-positive TTL disables fresh caching, never caches forever.');
    assert_same_cache(1, count(cache_entries_with_ttl(604800)), 'Disabling fresh caching retains the good fallback.');
}
echo "ok: expiry and TTL filter\n";

// Missing ASINs are omitted, remembered briefly, then retried even if once unavailable.
$state = reset_items_cache();
unset($state['items'][$b]);
$positive = items_cache_client($state)->get_items([$a, $b], $context);
assert_same_cache([$a], array_column($positive, 'asin'), 'Missing ASIN stays omitted.');
assert_same_cache(1, count(cache_entries_with_ttl(3600)), 'Negative result lasts exactly one hour.');
assert_same_cache(1, count(cache_entries_with_ttl(604800)), 'Missing ASIN never creates a good fallback.');
$GLOBALS['items_cache_now'] += 3599;
assert_same_cache([], items_cache_client($state)->get_items([$b], $context), 'Negative hit stays empty.');
assert_same_cache(1, count($state['catalog_calls']), 'Negative hit must bypass transport.');
$state['items'][$b] = ['asin' => $b];
$GLOBALS['items_cache_now']++;
$recovered = items_cache_client($state)->get_items([$b, $a], $context);
assert_same_cache([$b], $state['catalog_calls'][1]['itemIds'], 'Retry only the expired negative entry.');
assert_same_cache([$b, $a], array_column($recovered, 'asin'), 'Recovered ASIN rejoins the batch in order.');
echo "ok: negative cache and recovery\n";

// Catalog exceptions (including transport/network), HTTP failures and token failures
// must all use the same last-good fallback, without silently extending its lifetime.
foreach (['exception', 'http', 'token'] as $failure) {
    $state = reset_items_cache();
    $good = items_cache_client($state)->get_items([$a, $b], $context);
    $GLOBALS['items_cache_now'] += 86400;
    if ($failure === 'exception') {
        $state['catalog_error'] = new RuntimeException('Network unavailable');
    } elseif ($failure === 'http') {
        $state['catalog_status'] = 503;
    } else {
        $state['token_status'] = 401;
    }
    $stored = $GLOBALS['items_cache_transients'];
    $fallback = items_cache_client($state)->get_items([$b, $a], $context);
    assert_same_cache([$good[1], $good[0]], $fallback, $failure . ': return ordered last good data.');
    assert_same_cache(cache_entries_with_ttl(604800), array_filter($stored,
        static fn(array $entry): bool => $entry['ttl'] === 604800), $failure . ': do not prolong fallback lifetime.');
    assert_same_cache(0, count(cache_entries_with_ttl(3600)), $failure . ': failure is not a negative result.');

    // At seven days the fallback must disappear and the original error must propagate.
    $GLOBALS['items_cache_now'] += 604800 - 86400;
    $caught = null;
    try {
        items_cache_client($state)->get_items([$a], $context);
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    assert_same_cache(true, $caught instanceof RuntimeException, $failure . ': expired fallback must not mask failures.');

    $state = reset_items_cache();
    $expected = new RuntimeException('Original transport exception');
    if ($failure === 'exception') {
        $state['catalog_error'] = $expected;
    } elseif ($failure === 'http') {
        $state['catalog_status'] = 503;
    } else {
        $state['token_payload'] = []; // HTTP 200 without a token is also a token failure.
    }
    $caught = null;
    try {
        items_cache_client($state)->get_items([$a], $context);
    } catch (Throwable $exception) {
        $caught = $exception;
    }
    assert_same_cache(true, $caught instanceof RuntimeException, $failure . ': no good data keeps existing error behavior.');
    if ($failure === 'exception') {
        assert_same_cache($expected, $caught, 'Rethrow the original exception unchanged.');
    }
    assert_same_cache(0, count(cache_entries_with_ttl(3600)), 'Errors must never seed the negative cache.');
}

// A fresh hit can be combined with stale data; a miss with no history still throws.
$state = reset_items_cache();
$good = items_cache_client($state)->get_items([$a], $context);
$GLOBALS['items_cache_now'] += 86400;
items_cache_client($state)->get_items([$b], $context);
$state['catalog_status'] = 500;
$fallback = items_cache_client($state)->get_items([$a, $b], $context);
assert_same_cache([$a, $b], array_column($fallback, 'asin'), 'Combine last good data and fresh hits.');
assert_same_cache($good[0], $fallback[0], 'Stale item keeps its entire original format.');
$caught = null;
try {
    items_cache_client($state)->get_items([$b, $a, $c], $context);
} catch (Throwable $exception) {
    $caught = $exception;
}
assert_same_cache(true, $caught instanceof RuntimeException, 'Incomplete fallback must preserve the caller catch.');
$wrongScope = array_replace($context, ['partner_tag' => 'different-tag-21']);
$caught = null;
try {
    items_cache_client($state)->get_items([$a], $wrongScope);
} catch (Throwable $exception) {
    $caught = $exception;
}
assert_same_cache(true, $caught instanceof RuntimeException, 'Last good links must not leak across partner tags.');
echo "ok: stale fallback, all failure types and unchanged exceptions without fallback\n";

// A token outlives client instances, but never its advertised lifetime minus 60s.
$state = reset_items_cache();
items_cache_client($state)->get_items([$a], $context);
items_cache_client($state)->get_items([$b], $context);
assert_same_cache(1, count($state['token_calls']), 'Separate requests reuse the token transient.');
assert_same_cache(1, count(cache_entries_with_ttl(3540)), 'Token TTL is expires_in minus 60 seconds.');
$tokenKey = array_key_first(cache_entries_with_ttl(3540));
assert_same_cache(true, str_ends_with($tokenKey, hash('sha256', $context['client_id'])), 'Token key hashes the client ID.');
assert_same_cache(false, str_contains(serialize($GLOBALS['items_cache_transients']), $context['client_secret']), 'No cache key or value may contain the client secret.');
assert_same_cache(false, str_contains($tokenKey, $context['client_id']), 'Token key must not contain the plain client ID.');
$GLOBALS['items_cache_now'] += 3540;
items_cache_client($state)->get_items([$c], $context);
assert_same_cache(2, count($state['token_calls']), 'Expired token transient is renewed.');

$state = reset_items_cache();
unset($state['token_payload']['expires_in']);
items_cache_client($state)->get_items([$a], $context);
assert_same_cache(1, count(cache_entries_with_ttl(3000)), 'Absent expiry defaults to 50 minutes.');

// Short tokens must never create a permanent WordPress transient or memory hit.
$state = reset_items_cache();
$state['token_payload']['expires_in'] = 30;
$client = items_cache_client($state);
$client->get_items([$a], $context);
$client->get_items([$b], $context);
assert_same_cache(2, count($state['token_calls']), 'Token shorter than the safety margin is never reused.');
assert_same_cache(0, count(cache_entries_with_ttl(0)), 'Never pass a zero lifetime to WordPress.');

// One client can encounter multiple credentials: memory tokens are scoped too.
$state = reset_items_cache();
$client = items_cache_client($state);
$client->get_items([$a], $context);
$otherClient = array_replace($context, ['client_id' => 'another-client']);
$client->get_items([$b], $otherClient);
assert_same_cache(2, count($state['token_calls']), 'Different client IDs must fetch separate tokens.');
echo "ok: token reuse, expiry, fallback TTL and credential isolation\n";

// The availability probe always contacts the catalog, even with fresh, negative,
// or last-good product data. Only its token is shared with normal lookups.
$state = reset_items_cache();
items_cache_client($state)->get_items([$a], $context);
unset($state['items'][$b]);
items_cache_client($state)->get_items([$b], $context);
$state['items'][$a]['offersV2']['listings'] = [];
$state['items'][$b] = ['asin' => $b, 'offersV2' => ['listings' => [['isBuyBoxWinner' => true]]]];
$checker = new MTB_Affiliate_Availability_Checker();
$probeClient = items_cache_client($state);
for ($i = 0; $i < 2; $i++) {
    $probe = $probeClient->classify_items([$a, $b], $context);
    assert_same_cache('unavailable_temp', $checker->classify_asin($a, $probe), 'Probe sees live empty offers despite positive cache.');
    assert_same_cache('available', $checker->classify_asin($b, $probe), 'Probe sees live offer despite negative cache.');
}
assert_same_cache(4, count($state['catalog_calls']), 'Every probe makes a new catalog request.');
assert_same_cache(1, count($state['token_calls']), 'Probe may reuse the token.');
$state['catalog_status'] = 503;
$probe = $probeClient->classify_items([$a], $context);
assert_same_cache('transient_error', $probe['auth_state'], 'Probe reports live errors instead of stale success.');
assert_same_cache([], $probe['items'], 'Probe must never return last good items.');
echo "ok: availability checker stays live\n";
