<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-renderer.php';

function assert_same($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$shortener = new MTB_Affiliate_Title_Shortener();
$badgeResolver = new MTB_Affiliate_Badge_Resolver();
$renderer = new MTB_Affiliate_Renderer();

assert_same(
    'USB-C Tester Messgerät',
    $shortener->shorten(
        'B0DF2KFDC8',
        'YEREADW USB C Tester Messgerät Strommessgerät Multimeter Typ-C PD Strommesser',
        'USB C Tester Messgerät'
    ),
    'Shortener should prefer the clean short live title.'
);

assert_same(
    'Im Video verwendet',
    $badgeResolver->resolve('<!-- wp:embed {"providerNameSlug":"youtube"} --><figure>https://www.youtube.com/watch?v=test</figure>', 'auto'),
    'Badge resolver should detect YouTube embeds.'
);

$html = $renderer->render_cards(
    [
        [
            'asin' => 'B0CLTV6YB2',
            'title' => 'Miuzei Metallgehäuse für Raspberry Pi 5',
            'image_url' => 'https://example.com/case.jpg',
            'detail_url' => 'https://example.com/case',
            'benefit' => 'Metallgehäuse für bessere Kühlung',
        ],
    ],
    [
        'badge_label' => 'Im Video verwendet',
        'cta_label' => 'Preis auf Amazon checken',
    ]
);

assert_contains('mtb-aff-card', $html, 'Renderer should output the affiliate card wrapper.');
assert_contains('Preis auf Amazon checken', $html, 'Renderer should use the configured CTA label.');
assert_contains('Affiliate-Link', $html, 'Renderer should include the affiliate subline.');
assert_contains('Im Video verwendet', $html, 'Renderer should render the badge label.');

echo "ok\n";
