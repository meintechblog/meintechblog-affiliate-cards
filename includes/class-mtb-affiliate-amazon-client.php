<?php

declare(strict_types=1);

final class MTB_Affiliate_Amazon_Client {
    private const TOKEN_URL = 'https://api.amazon.co.uk/auth/o2/token';
    private const CATALOG_URL = 'https://creatorsapi.amazon/catalog/v1/getItems';
    private const BENEFIT_OVERRIDES = [
        'B0CK3L9WD3' => 'Leistungsstarkes Basis-System',
        'B08JC5DH9Q' => 'Robuste Industrial-microSD',
        'B0DF2KFDC8' => 'USB-C Stromwerte direkt prüfen',
        'B0CLTV6YB2' => 'Metallgehäuse für bessere Kühlung',
        'B0D7955R6N' => 'PoE sauber auf USB-C aufteilen',
    ];

    private MTB_Affiliate_Title_Shortener $shortener;
    /** @var callable */
    private $transport;
    private ?string $accessToken = null;

    public function __construct(?MTB_Affiliate_Title_Shortener $shortener = null, ?callable $transport = null) {
        $this->shortener = $shortener ?? new MTB_Affiliate_Title_Shortener();
        $this->transport = $transport ?? [$this, 'default_transport'];
    }

    public function derive_partner_tag(string $postDate): string {
        $dateDigits = preg_replace('/[^0-9]/', '', substr($postDate, 0, 10)) ?? '';
        if (strlen($dateDigits) < 8) {
            return 'meintechblog-000000-21';
        }

        return sprintf(
            'meintechblog-%s%s%s-21',
            substr($dateDigits, 2, 2),
            substr($dateDigits, 4, 2),
            substr($dateDigits, 6, 2)
        );
    }

    public function extract_partner_tag(string $url): ?string {
        $query = parse_url($url, PHP_URL_QUERY);
        if (! is_string($query) || $query === '') {
            return null;
        }

        parse_str($query, $params);
        $tag = trim((string) ($params['tag'] ?? ''));
        return $tag !== '' ? $tag : null;
    }

    public function resolve_partner_tag(string $postDate, string $existingTag = '', ?callable $validator = null): string {
        $derivedTag = $this->derive_partner_tag($postDate);
        $existingTag = trim($existingTag);

        if ($validator !== null) {
            if ($validator($derivedTag) === true) {
                return $derivedTag;
            }

            if ($existingTag !== '') {
                return $existingTag;
            }
        }

        return $existingTag !== '' ? $existingTag : $derivedTag;
    }

    public function get_items(array $asins, array $context): array {
        $asins = array_values(array_filter(array_unique(array_map('strval', $asins))));
        if ($asins === []) {
            return [];
        }

        $marketplace = trim((string) ($context['marketplace'] ?? 'www.amazon.de'));
        $partnerTag = trim((string) ($context['partner_tag'] ?? ''));
        $token = $this->get_access_token(
            trim((string) ($context['client_id'] ?? '')),
            trim((string) ($context['client_secret'] ?? ''))
        );

        [$status, $payload] = $this->request(
            'POST',
            self::CATALOG_URL,
            [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
                'x-marketplace' => $marketplace,
            ],
            [
                'itemIds' => $asins,
                'itemIdType' => 'ASIN',
                'marketplace' => $marketplace,
                'partnerTag' => $partnerTag,
                'resources' => [
                    'images.primary.large',
                    'images.primary.medium',
                    'images.primary.small',
                    'images.variants.large',
                    'images.variants.medium',
                    'images.variants.small',
                    'itemInfo.title',
                    'offersV2.listings.price',
                ],
            ]
        );

        if ($status !== 200) {
            throw new RuntimeException('Catalog request failed.');
        }

        $items = $payload['itemsResult']['items'] ?? $payload['itemResults']['items'] ?? [];

        return array_map(fn(array $item): array => $this->map_item($item), $items);
    }

    private function get_access_token(string $clientId, string $clientSecret): string {
        if ($this->accessToken !== null) {
            return $this->accessToken;
        }

        [$status, $payload] = $this->request(
            'POST',
            self::TOKEN_URL,
            ['Content-Type' => 'application/json'],
            [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'scope' => 'creatorsapi::default',
            ]
        );

        if ($status !== 200 || empty($payload['access_token'])) {
            throw new RuntimeException('Token request failed.');
        }

        $this->accessToken = (string) $payload['access_token'];
        return $this->accessToken;
    }

    private function map_item(array $item): array {
        $asin = (string) ($item['asin'] ?? '');
        $title = (string) ($item['itemInfo']['title']['displayValue'] ?? $asin);
        $images = $this->resolve_image_urls($item['images'] ?? []);
        $imageUrl = $images[0] ?? '';
        $detailUrl = (string) ($item['detailPageURL'] ?? ('https://www.amazon.de/dp/' . $asin));
        $priceText = $this->normalize_price($item['offersV2']['listings'] ?? []);

        return [
            'asin' => $asin,
            'title' => $this->shortener->shorten($asin, $title),
            'image_url' => $imageUrl,
            'images' => $images,
            'detail_url' => $detailUrl,
            'price_text' => $priceText,
            'benefit' => self::BENEFIT_OVERRIDES[$asin] ?? '',
        ];
    }

    private function resolve_image_urls(array $images): array {
        $orderedUrls = [];
        $seen = [];

        $append = static function ($url) use (&$orderedUrls, &$seen): void {
            if (! is_string($url) || $url === '' || isset($seen[$url])) {
                return;
            }
            $seen[$url] = true;
            $orderedUrls[] = $url;
        };

        foreach (['large', 'medium', 'small'] as $size) {
            $append($images['primary'][$size]['url'] ?? '');
        }

        $variants = $images['variants'] ?? [];
        if (is_array($variants)) {
            foreach ($variants as $variant) {
                if (! is_array($variant)) {
                    continue;
                }
                foreach (['large', 'medium', 'small'] as $size) {
                    $append($variant[$size]['url'] ?? '');
                }
            }
        }

        return $orderedUrls;
    }

    private function normalize_price(array $listings): ?string {
        foreach ($listings as $listing) {
            $price = $listing['price']['displayAmount'] ?? null;
            if (is_string($price) && $price !== '') {
                return $price;
            }
        }

        return null;
    }

    private function request(string $method, string $url, array $headers, ?array $body = null): array {
        $transport = $this->transport;
        return $transport($method, $url, $headers, $body);
    }

    private function default_transport(string $method, string $url, array $headers, ?array $body): array {
        $encodedBody = $body === null ? null : wp_json_encode($body);
        $args = [
            'method' => $method,
            'headers' => $headers,
            'body' => $encodedBody,
            'timeout' => 20,
        ];

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            throw new RuntimeException($response->get_error_message());
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        $payload = json_decode((string) wp_remote_retrieve_body($response), true);

        return [$status, is_array($payload) ? $payload : []];
    }
}
