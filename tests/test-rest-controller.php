<?php

declare(strict_types=1);

$GLOBALS['mtb_rest_test_options'] = [
    'mtb_affiliate_cards_settings' => [
        'cta_label' => 'Preis auf Amazon checken',
        'badge_mode' => 'auto',
        'auto_shorten_titles' => true,
        'marketplace' => 'www.amazon.de',
        'client_id' => 'client-id',
        'client_secret' => 'client-secret',
    ],
];

function get_option(string $option, $default = false) {
    return $GLOBALS['mtb_rest_test_options'][$option] ?? $default;
}

function current_user_can(string $capability): bool {
    return $capability === 'edit_posts';
}

function rest_ensure_response($value) {
    return $value;
}

function get_post_field(string $field, int $postId): string {
    if ($postId === 21580) {
        if ($field === 'post_date') {
            return '2026-03-20 13:35:37';
        }
        if ($field === 'post_content') {
            return '<p>Manual affiliate card post.</p>';
        }
    }

    return '';
}

function get_posts(array $args = []): array {
    return [
        (object) [
            'post_content' => '<p><a href="https://www.amazon.de/dp/B0VALID001?tag=meintechblog-260317-21&linkCode=ogi&th=1&psc=1">Valid recent affiliate link</a></p>',
        ],
    ];
}

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-settings.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-title-shortener.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-amazon-client.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-badge-resolver.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-rest-controller.php';

function assert_same_rest($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

$calls = [];

$transport = static function (string $method, string $url, array $headers, ?array $body) use (&$calls): array {
    if ($url === 'https://api.amazon.co.uk/auth/o2/token') {
        return [200, ['access_token' => 'token-rest']];
    }

    if ($url === 'https://creatorsapi.amazon/catalog/v1/getItems') {
        $partnerTag = (string) ($body['partnerTag'] ?? $body['partner_tag'] ?? '');
        $calls[] = $partnerTag;

        if ($partnerTag === 'meintechblog-260320-21') {
            return [400, ['message' => 'Invalid or unmapped partner tag']];
        }

        if ($partnerTag === 'meintechblog-260317-21') {
            return [200, ['itemsResult' => ['items' => [[
                'asin' => 'B0DJWBDNCW',
                'detailPageURL' => 'https://www.amazon.de/dp/B0DJWBDNCW?tag=meintechblog-260317-21&linkCode=ogi&th=1&psc=1',
                'images' => [
                    'primary' => [
                        'large' => ['url' => 'https://images.example/tablet.jpg'],
                    ],
                ],
                'itemInfo' => [
                    'title' => ['displayValue' => 'Lenovo Tablet MediaTek G85'],
                ],
            ]]]]];
        }
    }

    return [500, ['message' => 'unexpected']];
};

$controller = new MTB_Affiliate_Rest_Controller(
    new MTB_Affiliate_Settings(),
    new MTB_Affiliate_Amazon_Client(new MTB_Affiliate_Title_Shortener(), $transport)
);

$response = $controller->get_item([
    'asin' => 'B0DJWBDNCW',
    'postId' => 21580,
]);

assert_same_rest(
    ['meintechblog-260320-21', 'meintechblog-260317-21'],
    $calls,
    'REST hydration should retry with a recent working partner tag when the derived tag is rejected.'
);
assert_same_rest('Lenovo Tablet MediaTek G85', $response['title'], 'REST hydration should return the fetched product title.');
assert_same_rest('https://images.example/tablet.jpg', $response['imageUrl'], 'REST hydration should return the fetched product image.');
assert_same_rest('https://www.amazon.de/dp/B0DJWBDNCW?tag=meintechblog-260317-21&linkCode=ogi&th=1&psc=1', $response['detailUrl'], 'REST hydration should return the fallback affiliate detail URL.');

echo "ok\n";
