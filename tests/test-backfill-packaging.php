<?php

declare(strict_types=1);

function check_backfill(bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
function run_backfill_test(array $command, ?array $environment = null): array {
    $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, $environment);
    check_backfill(is_resource($process), 'Cannot start test subprocess.');
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    return [proc_close($process), $output, $error];
}

$root = dirname(__DIR__);
$temp = sys_get_temp_dir() . '/mtb-backfill-' . bin2hex(random_bytes(6));
$plugin = $temp . '/wp-content/plugins/test-plugin';
mkdir($plugin . '/scripts', 0777, true);
mkdir($plugin . '/.planning/data', 0777, true);
$script = $plugin . '/scripts/backfill-tracking-ids.php';
copy($root . '/scripts/backfill-tracking-ids.php', $script);
$marker = $temp . '/side-effect';
file_put_contents($temp . '/wp-load.php', '<?php file_put_contents(' . var_export($marker, true) . ', "bootstrapped"); echo "BOOTSTRAP_REACHED"; exit(0);');
file_put_contents($plugin . '/.planning/data/tracking-ids-backfill.txt', "meintechblog-261019-21\n");
$runner = $temp . '/loaded-wordpress.php';
file_put_contents($runner, '<?php
    define("ABSPATH", __DIR__ . "/");
    define("WP_CLI", true);
    class MTB_Affiliate_Tracking_Registry {
        public static function needs_upgrade(): bool { return false; }
        public function register(string $id): bool { file_put_contents(' . var_export($marker, true) . ', $id); return true; }
    }
    require ' . var_export($script, true) . ';');

try {
    $cgi = PHP_BINDIR . '/php-cgi';
    check_backfill(is_executable($cgi), 'php-cgi is required to test real non-CLI execution.');
    foreach ([$script, $runner] as $entry) {
        [$status, $output, $error] = run_backfill_test([$cgi, '-n'], array_merge(getenv(), [
            'REDIRECT_STATUS' => '1', 'REQUEST_METHOD' => 'GET', 'SCRIPT_FILENAME' => $entry,
            'GATEWAY_INTERFACE' => 'CGI/1.1', 'SERVER_PROTOCOL' => 'HTTP/1.1',
        ]));
        check_backfill(str_contains($output, '403 Forbidden') && str_contains($output, 'only available via CLI'), 'HTTP must be rejected before bootstrap or import: ' . $output . $error);
        check_backfill(! file_exists($marker), 'HTTP must not bootstrap WordPress or write to the registry, even if WordPress is already loaded.');
    }
    [$status, $output, $error] = run_backfill_test([PHP_BINARY, $script]);
    check_backfill($status === 0 && str_contains($output, 'BOOTSTRAP_REACHED'), 'Direct CLI execution must still reach its WordPress bootstrap: ' . $error);
    unlink($marker);
    [$status, $output, $error] = run_backfill_test([PHP_BINARY, $runner]);
    check_backfill($status === 0 && str_contains($output, '1 imported') && file_get_contents($marker) === 'meintechblog-261019-21', 'WP-CLI with loaded WordPress must still import tracking IDs: ' . $output . $error);

    [$status, $output, $error] = run_backfill_test(['bash', $root . '/scripts/build-zip.sh']);
    check_backfill($status === 0, 'ZIP build must succeed: ' . $output . $error);
    [$status, $listing, $error] = run_backfill_test(['unzip', '-Z1', $root . '/build/meintechblog-affiliate-cards.zip']);
    check_backfill($status === 0, 'Built ZIP must be readable: ' . $error);
    foreach (['scripts', '.planning', 'tests', 'docs'] as $directory) {
        check_backfill(! str_contains($listing, 'meintechblog-affiliate-cards/' . $directory . '/'), 'Production ZIP must exclude ' . $directory);
    }
    check_backfill(! str_contains($listing, '/.codex-'), 'Local task and response files must not ship in the ZIP.');
    foreach (['meintechblog-affiliate-cards.php', 'includes/class-mtb-affiliate-renderer.php', 'blocks/affiliate-cards/index.js', 'templates/affiliate-cards.php'] as $file) {
        check_backfill(str_contains($listing, 'meintechblog-affiliate-cards/' . $file . "\n"), 'Production ZIP must retain runtime file: ' . $file);
    }
} finally {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($iterator as $entry) {
        $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
    }
    rmdir($temp);
}
echo "ok\n";
