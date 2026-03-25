<?php
/**
 * Backfill ~170 existing tracking IDs into the registry table.
 *
 * Usage: wp eval-file scripts/backfill-tracking-ids.php
 * Or:    cd /path/to/wordpress && php -r "define('ABSPATH',__DIR__.'/'); require 'wp-load.php';" && php wp-content/plugins/meintechblog-affiliate-cards/scripts/backfill-tracking-ids.php
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    // Allow direct execution from plugin directory if WP is already loaded
    // Otherwise require wp-load.php from standard location
    $wp_load = dirname(__FILE__, 5) . '/wp-load.php';
    if (file_exists($wp_load)) {
        require_once $wp_load;
    } else {
        echo "Error: WordPress not found. Run via: wp eval-file scripts/backfill-tracking-ids.php\n";
        exit(1);
    }
}

$backfill_file = __DIR__ . '/../.planning/data/tracking-ids-backfill.txt';
if (!file_exists($backfill_file)) {
    echo "Error: Backfill file not found at {$backfill_file}\n";
    exit(1);
}

$lines = file($backfill_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$registry = new MTB_Affiliate_Tracking_Registry();
if (MTB_Affiliate_Tracking_Registry::needs_upgrade()) {
    MTB_Affiliate_Tracking_Registry::create_table();
}

$imported = 0;
$skipped  = 0;
$total    = count($lines);

foreach ($lines as $line) {
    $trackingId = trim($line);
    if ($trackingId === '') {
        continue;
    }
    if ($registry->register($trackingId)) {
        $imported++;
    } else {
        $skipped++;
    }
}

echo "Backfill complete: {$imported} imported, {$skipped} skipped (already exist or invalid), {$total} total lines.\n";
