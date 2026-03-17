<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';

function assert_same_scanner($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$scanner = new MTB_Affiliate_Token_Scanner();

$content = <<<HTML
<!-- wp:paragraph -->
<p>B0D7955R6N</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>amazon:B0CLTV6YB2</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Das ist B0D7955R6N mitten im Satz.</p>
<!-- /wp:paragraph -->
HTML;

$result = $scanner->scan($content);

assert_same_scanner(
    ['B0D7955R6N', 'B0CLTV6YB2'],
    $result['asins'],
    'Scanner should find standalone ASIN blocks only.'
);

if (str_contains($result['content'], '<p>B0D7955R6N</p>') || str_contains($result['content'], '<p>amazon:B0CLTV6YB2</p>')) {
    fwrite(STDERR, "Scanner should remove standalone ASIN blocks from content.\n");
    exit(1);
}

if (! str_contains($result['content'], 'Das ist B0D7955R6N mitten im Satz.')) {
    fwrite(STDERR, "Scanner should keep ASINs inside normal prose blocks.\n");
    exit(1);
}

echo "ok\n";
