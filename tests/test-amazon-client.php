<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';

function assert_same_amazon($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$calls = [];

$transport = static function (string $method, string $url, array $headers, ?array $body) use (&$calls): array {
    $calls[] = [
        'method' => $method,
        'url' => $url,
        'headers' => $headers,
        'body' => $body,
    ];

    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-123']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        return [
            200,
            [
                'itemsResult' => [
                    'items' => [
                        [
                            'asin' => 'B0DF2KFDC8',
                            'detailPageURL' => 'https://www.amazon.de/dp/B0DF2KFDC8?tag=meintechblog-260317-21',
                            'images' => [
                                'primary' => [
                                    'large' => ['url' => 'https://images.example/tester.jpg'],
                                ],
                            ],
                            'itemInfo' => [
                                'title' => [
                                    'displayValue' => 'YEREADW USB C Tester Messgerät Strommessgerät Multimeter Typ-C PD Strommesser',
                                ],
                            ],
                            'offersV2' => [
                                'listings' => [
                                    [
                                        'price' => [
                                            'displayAmount' => '19,99 EUR',
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    return [500, ['message' => 'unexpected']];
};

$client = new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $transport);

assert_same_amazon(
    'meintechblog-260317-21',
    $client->derive_partner_tag('2026-03-17T10:38:49'),
    'Partner tag should be derived from the post date.'
);

$items = $client->get_items(
    ['B0DF2KFDC8'],
    [
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
        'marketplace' => 'www.amazon.de',
        'partner_tag' => 'meintechblog-260317-21',
    ]
);

assert_same_amazon(2, count($calls), 'Client should perform one token request and one catalog request.');
assert_same_amazon('POST', $calls[0]['method'], 'Token endpoint should use POST.');
assert_same_amazon('creatorsapi::default', $calls[0]['body']['scope'], 'Token request should include the creators API scope.');
assert_same_amazon('client-id', $calls[0]['body']['client_id'], 'Token request should pass the client id.');
assert_same_amazon('client-secret', $calls[0]['body']['client_secret'], 'Token request should pass the client secret.');
assert_same_amazon('Bearer token-123', $calls[1]['headers']['Authorization'], 'Catalog request should include the bearer token.');
assert_same_amazon('www.amazon.de', $calls[1]['body']['marketplace'], 'Catalog request should use the configured marketplace.');
assert_same_amazon('meintechblog-260317-21', $calls[1]['body']['partnerTag'], 'Catalog request should use the derived tracking id.');
assert_same_amazon(['B0DF2KFDC8'], $calls[1]['body']['itemIds'], 'Catalog request should ask for the requested ASINs.');

assert_same_amazon('USB-C Tester Messgerät', $items[0]['title'], 'Client should shorten the Amazon title to the live display title.');
assert_same_amazon('USB-C Stromwerte direkt prüfen', $items[0]['benefit'], 'Client should attach the known benefit line when available.');
assert_same_amazon('https://images.example/tester.jpg', $items[0]['image_url'], 'Client should expose the primary image URL.');
assert_same_amazon('https://www.amazon.de/dp/B0DF2KFDC8?tag=meintechblog-260317-21', $items[0]['detail_url'], 'Client should expose the detail URL.');
assert_same_amazon('19,99 EUR', $items[0]['price_text'], 'Client should normalize the price field.');

echo "ok\n";
