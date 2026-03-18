<?php

declare(strict_types=1);

$_GET['tab'] = 'audit';
$GLOBALS['mtb_render_posts'] = [
    (object) [
        'ID' => 42,
        'post_title' => 'Neuster Affiliate Beitrag',
        'post_date' => '2026-03-18 11:30:00',
        'post_content' => '<!-- wp:paragraph --><p>Ich nutze amazon:B0D7955R6N im Setup.</p><!-- /wp:paragraph -->',
    ],
];
$GLOBALS['mtb_render_meta'] = [];
$_GET['mtb-audit-search'] = 'Affiliate';
$_GET['mtb-audit-status'] = 'offen';

function current_user_can(string $capability): bool {
    return $capability === 'manage_options';
}

function esc_attr(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function selected($actual, $expected): string {
    return $actual === $expected ? 'selected' : '';
}

function checked($actual): string {
    return $actual ? 'checked' : '';
}

function settings_fields(string $group): void {
    echo '<input type="hidden" name="settings-group" value="' . $group . '">';
}

function submit_button(string $text): void {
    echo '<button>' . $text . '</button>';
}

function get_posts(array $args = []): array {
    return $GLOBALS['mtb_render_posts'];
}

function get_post_meta(int $postId, string $key, bool $single = false) {
    $value = $GLOBALS['mtb_render_meta'][$postId][$key] ?? ($single ? '' : []);
    return $single ? $value : [$value];
}

function get_edit_post_link(int $postId): string {
    return 'post.php?post=' . $postId . '&action=edit';
}

function admin_url(string $path = ''): string {
    return '/wp-admin/' . ltrim($path, '/');
}

function wp_nonce_field(string $action, string $name): void {
    echo '<input type="hidden" name="' . $name . '" value="nonce-for-' . $action . '">';
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-renderer.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-audit-service.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-block.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-plugin.php';

function assert_contains_render(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

ob_start();
MTB_Affiliate_Plugin::instance()->render_settings_page();
$html = (string) ob_get_clean();

assert_contains_render('Affiliate Audit', $html, 'Audit tab render should keep the tab heading visible.');
assert_contains_render('Offen', $html, 'Audit tab should show the Offen summary card.');
assert_contains_render('Manuell prüfen', $html, 'Audit tab should show the Manuell prüfen summary card.');
assert_contains_render('Geradegezogen', $html, 'Audit tab should show the Geradegezogen summary card.');
assert_contains_render('Fehler', $html, 'Audit tab should show the Fehler summary card.');
assert_contains_render('name="mtb-audit-search"', $html, 'Audit tab should render a search field.');
assert_contains_render('name="mtb-audit-status"', $html, 'Audit tab should render a status filter.');
assert_contains_render('value="Affiliate"', $html, 'Audit tab should keep the current search value visible.');
assert_contains_render('value="offen" selected', $html, 'Audit tab should keep the current status filter selected.');
assert_contains_render('<th scope="col">Datum</th>', $html, 'Audit matrix should include the Datum column.');
assert_contains_render('<th scope="col">Beitrag</th>', $html, 'Audit matrix should include the Beitrag column.');
assert_contains_render('<th scope="col">Status</th>', $html, 'Audit matrix should include the Status column.');
assert_contains_render('<th scope="col">Affiliate-Funde</th>', $html, 'Audit matrix should include the Affiliate-Funde column.');
assert_contains_render('<th scope="col">Tracking-ID</th>', $html, 'Audit matrix should include the Tracking-ID column.');
assert_contains_render('<th scope="col">Cards</th>', $html, 'Audit matrix should include the Cards column.');
assert_contains_render('<th scope="col">Letzte Prüfung</th>', $html, 'Audit matrix should include the Letzte Prüfung column.');
assert_contains_render('<th scope="col">Kurzlog</th>', $html, 'Audit matrix should include the Kurzlog column.');
assert_contains_render('<th scope="col">Aktionen</th>', $html, 'Audit matrix should include the Aktionen column.');
assert_contains_render('Neuster Affiliate Beitrag', $html, 'Audit matrix should render recent posts as rows.');
assert_contains_render('post.php?post=42&amp;action=edit', $html, 'Audit matrix should link to the edit screen of the post.');
assert_contains_render('1', $html, 'Audit matrix should show detected affiliate finds for recent posts.');
assert_contains_render('admin-post.php', $html, 'Audit matrix should post actions through admin-post.php.');
assert_contains_render('>Prüfen<', $html, 'Audit matrix should render a Prüfen action button.');
assert_contains_render('>Geradeziehen<', $html, 'Audit matrix should render a Geradeziehen action button.');
assert_contains_render('mtb_affiliate_audit_nonce', $html, 'Audit matrix should include a nonce field for admin actions.');

echo "ok\n";
