<?php

declare(strict_types=1);

// ---------------------------------------------------------------------------
// Minimal WordPress stubs so the classes can be loaded outside WordPress.
// ---------------------------------------------------------------------------

if (! defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

// ---------------------------------------------------------------------------
// Load classes under test.
// ---------------------------------------------------------------------------

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-product-library.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-prepass.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';

// ---------------------------------------------------------------------------
// Test double: in-memory product library stub.
// ---------------------------------------------------------------------------

class Test_Product_Library_Integration extends MTB_Affiliate_Product_Library {
    private array $fixtures;

    public function __construct(array $fixtures) {
        $this->fixtures = $fixtures;
    }

    public function get_last(int $n = 1): ?array {
        return $this->fixtures['last'] ?? null;
    }

    public function get_products_today(): array {
        return $this->fixtures['today'] ?? [];
    }

    public function get_products_yesterday(): array {
        return $this->fixtures['yesterday'] ?? [];
    }
}

// ---------------------------------------------------------------------------
// Assertion helpers (mirroring test-post-processor.php style).
// ---------------------------------------------------------------------------

function assert_same_integration(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains_processor(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing:  ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains_processor(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL . 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Product fixture helper.
// ---------------------------------------------------------------------------

function make_integration_product(string $asin): array {
    return [
        'id'          => 1,
        'asin'        => $asin,
        'title'       => 'Test Product ' . $asin,
        'detail_url'  => 'https://amazon.de/dp/' . $asin,
        'image_url'   => '',
        'received_at' => '2026-03-25 10:00:00',
    ];
}

// ---------------------------------------------------------------------------
// Pipeline runner: Token Prepass -> Post Processor.
// ---------------------------------------------------------------------------

/**
 * Run the full pipeline: prepass resolves shorthand tokens, processor converts ASINs to blocks.
 *
 * @param string $content  Raw post content.
 * @param array  $fixtures Library fixtures: keys 'last', 'today', 'yesterday'.
 * @return array Result from MTB_Affiliate_Post_Processor::process().
 */
function run_pipeline(string $content, array $fixtures): array {
    $library  = new Test_Product_Library_Integration($fixtures);
    $prepass  = new MTB_Affiliate_Token_Prepass($library);
    $resolved = $prepass->resolve($content);

    // Minimal itemResolver: returns synthetic item data for any requested ASIN.
    // Required for inline tokens to produce affiliate-card blocks.
    $itemResolver = static function (array $asins): array {
        return array_map(static function (string $asin): array {
            return [
                'asin'      => $asin,
                'title'     => 'Test Product ' . $asin,
                'detail_url' => 'https://www.amazon.de/dp/' . $asin . '?tag=meintechblog-21',
                'image_url' => '',
            ];
        }, $asins);
    };

    $processor = new MTB_Affiliate_Post_Processor(null, [], $itemResolver);
    return $processor->process($resolved);
}

// ---------------------------------------------------------------------------
// Test 1: amazon:last standalone -> affiliate-cards block with last ASIN.
// ---------------------------------------------------------------------------

$result = run_pipeline(
    "<!-- wp:paragraph -->\n<p>amazon:last</p>\n<!-- /wp:paragraph -->\n",
    ['last' => make_integration_product('B0TESTLAST')]
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 1: amazon:last should produce affiliate-cards block.'
);
assert_contains_processor(
    'B0TESTLAST',
    $result['content'],
    'Test 1: affiliate-cards block should contain last ASIN.'
);
assert_same_integration(
    ['B0TESTLAST'],
    $result['asins'],
    'Test 1: asins array should contain B0TESTLAST.'
);
echo "PASS: Test 1 - amazon:last standalone\n";

// ---------------------------------------------------------------------------
// Test 2: amazon:heute standalone with 3 products -> 3 ASINs in block.
// ---------------------------------------------------------------------------

$result = run_pipeline(
    "<!-- wp:paragraph -->\n<p>amazon:heute</p>\n<!-- /wp:paragraph -->\n",
    ['today' => [
        make_integration_product('B0TODAY001'),
        make_integration_product('B0TODAY002'),
        make_integration_product('B0TODAY003'),
    ]]
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 2: amazon:heute should produce affiliate-cards block.'
);
assert_contains_processor(
    'B0TODAY001',
    $result['content'],
    'Test 2: block should contain first today ASIN.'
);
assert_contains_processor(
    'B0TODAY002',
    $result['content'],
    'Test 2: block should contain second today ASIN.'
);
assert_contains_processor(
    'B0TODAY003',
    $result['content'],
    'Test 2: block should contain third today ASIN.'
);
assert_same_integration(
    3,
    count($result['asins']),
    'Test 2: asins array should have 3 items.'
);
echo "PASS: Test 2 - amazon:heute with 3 products\n";

// ---------------------------------------------------------------------------
// Test 3: amazon:gestern standalone -> 2 ASINs in block.
// ---------------------------------------------------------------------------

$result = run_pipeline(
    "<!-- wp:paragraph -->\n<p>amazon:gestern</p>\n<!-- /wp:paragraph -->\n",
    ['yesterday' => [
        make_integration_product('B0YEST0001'),
        make_integration_product('B0YEST0002'),
    ]]
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 3: amazon:gestern should produce affiliate-cards block.'
);
assert_contains_processor(
    'B0YEST0001',
    $result['content'],
    'Test 3: block should contain first yesterday ASIN.'
);
assert_contains_processor(
    'B0YEST0002',
    $result['content'],
    'Test 3: block should contain second yesterday ASIN.'
);
assert_same_integration(
    2,
    count($result['asins']),
    'Test 3: asins array should have 2 items.'
);
echo "PASS: Test 3 - amazon:gestern with 2 products\n";

// ---------------------------------------------------------------------------
// Test 4: amazon:last inline within text -> ASIN inline + card block.
// ---------------------------------------------------------------------------

$result = run_pipeline(
    "<!-- wp:paragraph -->\n<p>Check out amazon:last for details</p>\n<!-- /wp:paragraph -->\n",
    ['last' => make_integration_product('B0TESTLAST')]
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 4: inline amazon:last should produce affiliate-cards block.'
);
assert_contains_processor(
    'B0TESTLAST',
    $result['content'],
    'Test 4: ASIN should appear in output (inline or block).'
);
assert_not_contains_processor(
    'amazon:last',
    $result['content'],
    'Test 4: shorthand token amazon:last should not remain in output.'
);
echo "PASS: Test 4 - amazon:last inline\n";

// ---------------------------------------------------------------------------
// Test 5: Regular amazon:ASIN (no regression) -- prepass passes through.
// ---------------------------------------------------------------------------

$result = run_pipeline(
    "<!-- wp:paragraph -->\n<p>amazon:B0D7955R6N</p>\n<!-- /wp:paragraph -->\n",
    [] // no library fixtures -- prepass should leave ASIN tokens untouched
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 5: regular amazon:ASIN should still produce affiliate-cards block.'
);
assert_contains_processor(
    'B0D7955R6N',
    $result['content'],
    'Test 5: the ASIN B0D7955R6N should appear in the block.'
);
assert_same_integration(
    ['B0D7955R6N'],
    $result['asins'],
    'Test 5: asins array should contain B0D7955R6N.'
);
echo "PASS: Test 5 - no regression with regular amazon:ASIN\n";

// ---------------------------------------------------------------------------
// Test 6: Mixed content -- shorthand + regular ASIN -> both in output.
// ---------------------------------------------------------------------------

$mixed = <<<HTML
<!-- wp:paragraph -->
<p>amazon:last</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>amazon:B0D7955R6N</p>
<!-- /wp:paragraph -->
HTML;

$result = run_pipeline(
    $mixed,
    ['last' => make_integration_product('B0TESTLAST')]
);

assert_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 6: mixed content should produce affiliate-cards block.'
);
assert_contains_processor(
    'B0TESTLAST',
    $result['content'],
    'Test 6: shorthand ASIN B0TESTLAST should appear in output.'
);
assert_contains_processor(
    'B0D7955R6N',
    $result['content'],
    'Test 6: regular ASIN B0D7955R6N should appear in output.'
);
assert_same_integration(
    2,
    count($result['asins']),
    'Test 6: asins array should have both ASINs.'
);
echo "PASS: Test 6 - mixed shorthand + regular ASIN\n";

// ---------------------------------------------------------------------------
// Test 7: No tokens -> content unchanged.
// ---------------------------------------------------------------------------

$noTokenContent = "<!-- wp:paragraph -->\n<p>Regular text</p>\n<!-- /wp:paragraph -->\n";
$result = run_pipeline($noTokenContent, []);

assert_same_integration(
    [],
    $result['asins'],
    'Test 7: no tokens should yield empty asins array.'
);
assert_not_contains_processor(
    'wp:meintechblog/affiliate-cards',
    $result['content'],
    'Test 7: no tokens should produce no affiliate-cards block.'
);
echo "PASS: Test 7 - no tokens content unchanged\n";

echo PHP_EOL . "All integration tests passed." . PHP_EOL;
