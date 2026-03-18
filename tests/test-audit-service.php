<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-audit-service.php';

function assert_same_audit($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains_audit(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL . 'Haystack: ' . $haystack . PHP_EOL);
        exit(1);
    }
}

$service = new MTB_Affiliate_Audit_Service();

$defaults = $service->default_state();

assert_same_audit('offen', $defaults['status'] ?? null, 'Audit state should default to offen.');
assert_same_audit(0, $defaults['counts']['affiliate_finds'] ?? null, 'Audit state should start with zero affiliate finds.');
assert_same_audit(0, $defaults['counts']['card_blocks'] ?? null, 'Audit state should start with zero affiliate cards.');
assert_same_audit([], $defaults['asins'] ?? null, 'Audit state should start with an empty ASIN list.');
assert_same_audit('', $defaults['tracking'] ?? null, 'Audit state should start with no tracking verdict.');
assert_same_audit('', $defaults['short_log'] ?? null, 'Audit state should start with an empty short log.');

$log = $service->build_short_log([
    'counts' => [
        'affiliate_finds' => 2,
        'card_blocks' => 1,
    ],
    'tracking' => 'abweichend',
    'status' => 'manuell_pruefen',
]);

assert_contains_audit('2 Affiliate-Funde', $log, 'Short log should mention the affiliate find count.');
assert_contains_audit('1 Cards', $log, 'Short log should mention the card block count.');
assert_contains_audit('Tracking-ID abweichend', $log, 'Short log should explain the tracking verdict.');

$emptyLog = $service->build_short_log([
    'counts' => [
        'affiliate_finds' => 0,
        'card_blocks' => 0,
    ],
    'tracking' => 'keine_funde',
    'status' => 'geprueft',
]);

assert_same_audit('Keine Affiliate-Funde', $emptyLog, 'Short log should clearly show when a post has no affiliate finds.');

$legacyLog = $service->build_short_log([
    'counts' => [
        'affiliate_finds' => 8,
        'card_blocks' => 0,
    ],
    'tracking' => 'ok',
    'status' => 'legacy',
]);

assert_same_audit('Legacy-Fall: Affiliate-Links erkannt, aber keine sichere Auto-Card moeglich', $legacyLog, 'Short log should clearly explain legacy affiliate cases.');

$scan = $service->scan_post_content(<<<HTML
<!-- wp:paragraph -->
<p>Inline-Link <a href="https://www.amazon.de/dp/B0TRACK001?tag=meintechblog-260318-21">Produkt</a> und Marker amazon:B0MARKER01.</p>
<!-- /wp:paragraph -->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0TRACK001","title":"Produkt"}]} /-->

<!-- wp:paragraph -->
<p>Zweiter Link <a href="https://www.amazon.de/dp/B0TRACK002?tag=falsch-123-21">Produkt 2</a>.</p>
<!-- /wp:paragraph -->
HTML);

assert_same_audit(3, $scan['counts']['affiliate_finds'] ?? null, 'Scan should count inline Amazon links and amazon:ASIN markers.');
assert_same_audit(1, $scan['counts']['card_blocks'] ?? null, 'Scan should count existing affiliate card blocks.');
assert_same_audit(['B0MARKER01', 'B0TRACK001', 'B0TRACK002'], $scan['asins'] ?? null, 'Scan should collect unique ASINs in stable order.');
assert_same_audit('abweichend', $scan['tracking'] ?? null, 'Scan should mark mixed tracking tags as abweichend.');

$encodedScan = $service->scan_post_content(<<<HTML
<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0ENCODED1","detail_url":"https://www.amazon.de/dp/B0ENCODED1?tag=meintechblog-260317-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"}],"detailUrl":"https://www.amazon.de/dp/B0ENCODED1?tag=meintechblog-260317-21\u0026linkCode=ogi\u0026th=1\u0026psc=1"} /-->
HTML);

assert_same_audit(2, $encodedScan['counts']['affiliate_finds'] ?? null, 'Scan should still count both stored Amazon detail URLs inside a single affiliate card block.');
assert_same_audit('ok', $encodedScan['tracking'] ?? null, 'Scan should normalize block JSON URLs with \\u0026 before evaluating the tracking tag.');

$emptyScan = $service->scan_post_content(<<<HTML
<!-- wp:paragraph -->
<p>Kein Affiliate-Link in diesem Beitrag.</p>
<!-- /wp:paragraph -->
HTML);

assert_same_audit(0, $emptyScan['counts']['affiliate_finds'] ?? null, 'Scan should report zero affiliate finds for normal content.');
assert_same_audit('keine_funde', $emptyScan['tracking'] ?? null, 'Scan should classify posts without affiliate finds as non-actionable instead of unclear.');

echo "ok\n";
