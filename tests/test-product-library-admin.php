<?php
declare(strict_types=1);

/**
 * Tests for MTB_Affiliate_Product_Library::delete_by_ids()
 * Run: php tests/test-product-library-admin.php
 *
 * This test file uses a lightweight stub for $wpdb to avoid needing a real DB.
 */

class WpdbStub {
    public array $queries = [];
    public string $prefix  = 'wp_';
    public int    $affected = 0;

    public function prepare(string $sql, mixed ...$args): string {
        // Minimal: substitute %d placeholders in order
        $i = 0;
        return preg_replace_callback('/%d/', function () use (&$i, $args) {
            return (int) $args[$i++];
        }, $sql);
    }

    public function query(string $sql): int|false {
        $this->queries[] = $sql;
        return $this->affected;
    }
}

// ---- Bootstrap library class without WordPress ----
define('ABSPATH', __DIR__ . '/../');
// Minimal global stubs
function current_time(string $type, bool $gmt = false): string { return '2026-01-01 00:00:00'; }
function update_option(string $key, mixed $val, bool $autoload = true): bool { return true; }
function get_option(string $key, mixed $default = false): mixed { return $default; }

require_once __DIR__ . '/../includes/class-mtb-affiliate-product-library.php';

// ---- Test runner ----
$passed = 0;
$failed = 0;

function assert_eq(mixed $expected, mixed $actual, string $label): void {
    global $passed, $failed;
    if ($expected === $actual) {
        echo "PASS: {$label}\n";
        $passed++;
    } else {
        echo "FAIL: {$label} — expected " . var_export($expected, true) . ", got " . var_export($actual, true) . "\n";
        $failed++;
    }
}

// ---- Tests ----

// Test 1: delete_by_ids with empty array returns 0 and issues no query
$wpdb = new WpdbStub();
$wpdb->affected = 3;
$lib = new MTB_Affiliate_Product_Library();
$result = $lib->delete_by_ids([]);
assert_eq(0, $result, 'delete_by_ids([]) returns 0');
assert_eq(0, count($wpdb->queries), 'delete_by_ids([]) issues no SQL');

// Test 2: delete_by_ids with valid IDs returns affected count
$wpdb = new WpdbStub();
$wpdb->affected = 2;
$lib = new MTB_Affiliate_Product_Library();
$result = $lib->delete_by_ids([1, 2]);
assert_eq(2, $result, 'delete_by_ids([1, 2]) returns 2');
assert_eq(1, count($wpdb->queries), 'delete_by_ids issues exactly one query');

// Test 3: string IDs are cast to int
$wpdb = new WpdbStub();
$wpdb->affected = 1;
$lib = new MTB_Affiliate_Product_Library();
$lib->delete_by_ids(['5']);
$sql = $wpdb->queries[0] ?? '';
assert_eq(true, str_contains($sql, 'WHERE id IN (5)'), 'string ID cast to int in SQL');

// Test 4: table_name returns prefixed name
$wpdb = new WpdbStub();
$lib = new MTB_Affiliate_Product_Library();
assert_eq('wp_mtb_affiliate_products', $lib->table_name(), 'table_name uses wpdb prefix');

// ---- Summary ----
echo "\n{$passed} passed, {$failed} failed\n";
exit($failed > 0 ? 1 : 0);
