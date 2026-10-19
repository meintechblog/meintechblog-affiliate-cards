<?php

declare(strict_types=1);

// These tests run outside WordPress and do not render the product library table.
if (! class_exists('WP_List_Table')) {
    class WP_List_Table {}
}
if (! function_exists('__')) {
    function __(string $text, string $domain = ''): string { return $text; }
}
if (! function_exists('esc_html__')) {
    function esc_html__(string $text, string $domain = ''): string {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
}

// Dependencies normally loaded by meintechblog-affiliate-cards.php.
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-tracking-registry.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-url-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-product-library.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-telegram-handler.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-click-tracker.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-availability-checker.php';
