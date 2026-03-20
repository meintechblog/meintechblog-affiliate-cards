<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-renderer.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-block.php';

$GLOBALS['mtb_test_core_settings'] = [
    'mtb_affiliate_cards_settings' => [
        'cta_label' => 'Preis auf Amazon checken',
        'badge_mode' => 'video',
        'auto_shorten_titles' => true,
        'marketplace' => 'www.amazon.de',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ],
];

if (! function_exists('get_option')) {
    function get_option(string $option, $default = false) {
        return $GLOBALS['mtb_test_core_settings'][$option] ?? $default;
    }
}

if (! defined('MTB_AFFILIATE_CARDS_DIR')) {
    define('MTB_AFFILIATE_CARDS_DIR', dirname(__DIR__) . '/');
}

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
if (strpos($renderer->render_cards(
    [[
        'asin' => 'B0NOIMAGE1',
        'title' => 'Card Without Image',
        'image_url' => '',
        'detail_url' => 'https://example.com/no-image',
        'benefit' => '',
    ]],
    [
        'badge_label' => 'Im Video verwendet',
        'cta_label' => 'Preis auf Amazon checken',
    ]
), 'img src=""') !== false) {
    fwrite(STDERR, "Renderer should not emit an empty image tag when no image URL is available.\n");
    exit(1);
}

$transport = static function (string $method, string $url, array $headers, ?array $body): array {
    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-abc']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        $asin = (string) ($body['itemIds'][0] ?? '');

        if ($asin === 'B0PERSIST01') {
            return [
                200,
                [
                    'itemsResult' => [
                        'items' => [[
                            'asin' => 'B0PERSIST01',
                            'detailPageURL' => 'https://www.amazon.de/dp/B0PERSIST01?tag=meintechblog-260317-21',
                            'images' => [
                                'primary' => [
                                    'large' => ['url' => 'https://images.example/fetched-primary.jpg'],
                                ],
                            ],
                            'itemInfo' => [
                                'title' => ['displayValue' => 'Fetched Persisted Title'],
                            ],
                        ]],
                    ],
                ],
            ];
        }

        if ($asin === 'B0FALLBACK1') {
            return [
                200,
                [
                    'itemsResult' => [
                        'items' => [[
                            'asin' => 'B0FALLBACK1',
                            'detailPageURL' => 'https://www.amazon.de/dp/B0FALLBACK1?tag=meintechblog-260317-21',
                            'images' => [
                                'primary' => [
                                    'large' => ['url' => 'https://images.example/fallback-primary.jpg'],
                                ],
                            ],
                            'itemInfo' => [
                                'title' => ['displayValue' => 'Fetched Fallback Title'],
                            ],
                        ]],
                    ],
                ],
            ];
        }

        if ($asin === 'B0PLACE001') {
            return [
                200,
                [
                    'itemsResult' => [
                        'items' => [[
                            'asin' => 'B0PLACE001',
                            'detailPageURL' => 'https://www.amazon.de/dp/B0PLACE001?tag=meintechblog-260317-21',
                            'images' => [
                                'primary' => [
                                    'large' => ['url' => 'https://images.example/placeholder-primary.jpg'],
                                ],
                            ],
                            'itemInfo' => [
                                'title' => ['displayValue' => 'Fetched Placeholder Title'],
                            ],
                        ]],
                    ],
                ],
            ];
        }
    }

    return [500, ['message' => 'unexpected']];
};

$block = new MTB_Affiliate_Block(
    new MTB_Affiliate_Settings(),
    new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $transport),
    new MTB_Affiliate_Title_Shortener()
);

$persistedHtml = $block->render([
    'items' => [[
        'asin' => 'B0PERSIST01',
        'title' => 'Persisted Item Title',
        'detail_url' => 'https://persisted.example/item',
        'image_url' => 'https://persisted.example/original.jpg',
        'benefit' => 'Persisted Benefit',
    ]],
    'images' => [
        'https://persisted.example/image-1.jpg',
        'https://persisted.example/image-2.jpg',
    ],
    'selectedImageIndex' => 1,
    'badgeMode' => 'video',
    'autoShortenTitles' => true,
]);

assert_contains('Persisted Item Title', $persistedHtml, 'Render should prefer persisted title over fetched title.');
assert_contains('https://persisted.example/item', $persistedHtml, 'Render should prefer persisted detail URL over fetched detail URL.');
assert_contains('https://persisted.example/image-2.jpg', $persistedHtml, 'Render should use the selected persisted image.');

$fallbackHtml = $block->render([
    'items' => [[
        'asin' => 'B0FALLBACK1',
    ]],
    'badgeMode' => 'video',
    'autoShortenTitles' => true,
]);

assert_contains('Fetched Fallback Title', $fallbackHtml, 'Render should fall back to fetched title when persisted data is missing.');
assert_contains('https://www.amazon.de/dp/B0FALLBACK1?tag=meintechblog-260317-21', $fallbackHtml, 'Render should fall back to fetched detail URL when persisted data is missing.');
assert_contains('https://images.example/fallback-primary.jpg', $fallbackHtml, 'Render should fall back to fetched image when persisted data is missing.');

$placeholderWithAmazonTitleHtml = $block->render([
    'items' => [[
        'asin' => 'B0PLACE001',
        'title' => 'B0PLACE001',
    ]],
    'amazonTitle' => 'Hydrated Amazon Title',
    'badgeMode' => 'video',
    'autoShortenTitles' => true,
]);

assert_contains('Hydrated Amazon Title', $placeholderWithAmazonTitleHtml, 'Render should treat title==ASIN as placeholder so persisted amazonTitle can win.');

$placeholderFallbackHtml = $block->render([
    'items' => [[
        'asin' => 'B0PLACE001',
        'title' => 'B0PLACE001',
    ]],
    'badgeMode' => 'video',
    'autoShortenTitles' => true,
]);

assert_contains('Fetched Placeholder Title', $placeholderFallbackHtml, 'Render should treat title==ASIN as placeholder so fetched title can win.');

echo "ok\n";
