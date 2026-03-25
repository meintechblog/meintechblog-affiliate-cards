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

// ---------------------------------------------------------------------------
// Test double: overrides DB methods with in-memory fixtures.
// ---------------------------------------------------------------------------

class Test_Product_Library extends MTB_Affiliate_Product_Library {
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
// Assertion helpers.
// ---------------------------------------------------------------------------

function assert_same_prepass(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL
            . 'Expected: ' . var_export($expected, true) . PHP_EOL
            . 'Actual:   ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains_prepass(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing:  ' . $needle . PHP_EOL
            . 'In:       ' . $haystack . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains_prepass(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL . 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

// ---------------------------------------------------------------------------
// Fixture helpers.
// ---------------------------------------------------------------------------

function make_product(string $asin): array {
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
// Test 1: Standalone amazon:last with one product -> single amazon:ASIN paragraph.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['last' => make_product('B0TEST12345')]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:last</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass(
    'amazon:B0TEST12345',
    $result,
    'Test 1: Standalone amazon:last should produce amazon:B0TEST12345 paragraph.'
);
assert_not_contains_prepass(
    'amazon:last',
    $result,
    'Test 1: Standalone amazon:last should not remain in output.'
);
echo "PASS: Test 1 - standalone amazon:last\n";

// ---------------------------------------------------------------------------
// Test 2: Standalone amazon:heute with 3 products -> 3 amazon:ASIN paragraphs.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['today' => [
    make_product('B0TODAY0001'),
    make_product('B0TODAY0002'),
    make_product('B0TODAY0003'),
]]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:heute</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass('amazon:B0TODAY0001', $result, 'Test 2: heute should include first product.');
assert_contains_prepass('amazon:B0TODAY0002', $result, 'Test 2: heute should include second product.');
assert_contains_prepass('amazon:B0TODAY0003', $result, 'Test 2: heute should include third product.');
assert_not_contains_prepass('amazon:heute', $result, 'Test 2: amazon:heute should not remain in output.');
echo "PASS: Test 2 - standalone amazon:heute with 3 products\n";

// ---------------------------------------------------------------------------
// Test 3: Standalone amazon:gestern with 2 products -> 2 amazon:ASIN paragraphs.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['yesterday' => [
    make_product('B0YEST00001'),
    make_product('B0YEST00002'),
]]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:gestern</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass('amazon:B0YEST00001', $result, 'Test 3: gestern should include first product.');
assert_contains_prepass('amazon:B0YEST00002', $result, 'Test 3: gestern should include second product.');
assert_not_contains_prepass('amazon:gestern', $result, 'Test 3: amazon:gestern should not remain in output.');
echo "PASS: Test 3 - standalone amazon:gestern with 2 products\n";

// ---------------------------------------------------------------------------
// Test 4: Standalone amazon:today (English alias) works same as amazon:heute.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['today' => [make_product('B0ENGTODAY1')]]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:today</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass('amazon:B0ENGTODAY1', $result, 'Test 4: amazon:today should resolve same as amazon:heute.');
assert_not_contains_prepass('amazon:today', $result, 'Test 4: amazon:today should not remain in output.');
echo "PASS: Test 4 - standalone amazon:today alias\n";

// ---------------------------------------------------------------------------
// Test 5: Standalone amazon:yesterday (English alias) works same as amazon:gestern.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['yesterday' => [make_product('B0ENGYEST01')]]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:yesterday</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass('amazon:B0ENGYEST01', $result, 'Test 5: amazon:yesterday should resolve same as amazon:gestern.');
assert_not_contains_prepass('amazon:yesterday', $result, 'Test 5: amazon:yesterday should not remain in output.');
echo "PASS: Test 5 - standalone amazon:yesterday alias\n";

// ---------------------------------------------------------------------------
// Test 6: Token with no matching products -> paragraph removed.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library([]); // No products.
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:last</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_not_contains_prepass('<p>', $result, 'Test 6: No-match token should remove the paragraph entirely.');
assert_not_contains_prepass('amazon:last', $result, 'Test 6: No-match amazon:last should not appear in output.');
echo "PASS: Test 6 - no matching products removes paragraph\n";

// ---------------------------------------------------------------------------
// Test 7: Inline amazon:last within text -> first ASIN substituted inline.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library(['last' => make_product('B0INLINE0001')]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>Schau dir das an: amazon:last – sehr empfehlenswert.</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_contains_prepass(
    'amazon:B0INLINE0001',
    $result,
    'Test 7: Inline amazon:last should be substituted with concrete ASIN.'
);
assert_not_contains_prepass(
    'amazon:last',
    $result,
    'Test 7: Inline amazon:last should not remain in output.'
);
assert_contains_prepass(
    'Schau dir das an:',
    $result,
    'Test 7: Surrounding paragraph text should be preserved.'
);
echo "PASS: Test 7 - inline amazon:last substitution\n";

// ---------------------------------------------------------------------------
// Test 8: Regular amazon:B0D7955R6N token passes through unchanged.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library([]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:B0D7955R6N</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_same_prepass($content, $result, 'Test 8: Regular amazon:ASIN token should pass through unchanged.');
echo "PASS: Test 8 - regular amazon:ASIN token unchanged\n";

// ---------------------------------------------------------------------------
// Test 9: Content with no amazon: tokens passes through unchanged.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library([]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>Hallo Welt! Kein Affiliate-Token hier.</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);

assert_same_prepass($content, $result, 'Test 9: Content with no amazon: tokens should be unchanged.');
echo "PASS: Test 9 - content without tokens unchanged\n";

// ---------------------------------------------------------------------------
// Test 10: Case insensitive: amazon:Last and amazon:HEUTE both resolve.
// ---------------------------------------------------------------------------

$lib = new Test_Product_Library([
    'last'  => make_product('B0CASETEST1'),
    'today' => [make_product('B0CASETEST2')],
]);
$prepass = new MTB_Affiliate_Token_Prepass($lib);

$content = "<!-- wp:paragraph -->\n<p>amazon:Last</p>\n<!-- /wp:paragraph -->\n";
$result = $prepass->resolve($content);
assert_contains_prepass('amazon:B0CASETEST1', $result, 'Test 10a: amazon:Last (mixed case) should resolve.');
assert_not_contains_prepass('amazon:Last', $result, 'Test 10a: amazon:Last should not remain in output.');

$content2 = "<!-- wp:paragraph -->\n<p>amazon:HEUTE</p>\n<!-- /wp:paragraph -->\n";
$result2 = $prepass->resolve($content2);
assert_contains_prepass('amazon:B0CASETEST2', $result2, 'Test 10b: amazon:HEUTE (uppercase) should resolve.');
assert_not_contains_prepass('amazon:HEUTE', $result2, 'Test 10b: amazon:HEUTE should not remain in output.');

echo "PASS: Test 10 - case-insensitive resolution\n";

// ---------------------------------------------------------------------------

echo "\nAll 10 tests passed.\n";
