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

    /**
     * Non-throwing availability probe for the dead-link checker. Unlike get_items() this
     * surfaces an HTTP-status-driven auth state, per-ASIN errors, and a POSITIVE offers-presence
     * signal — so the checker can tell account-wide auth failure from a transient error from a
     * real "no offer" (Codex-Refute RC1/RC2/RC3, 2026-06-03). Never throws.
     *
     * @return array{auth_state:string,http_status:int,items:array,errors:array,raw_errors:array,raw:?array}
     */
    public function classify_items(array $asins, array $context, bool $debug = false): array {
        $out = [
            'auth_state'  => 'ok',
            'http_status' => 0,
            'items'       => [],
            'errors'      => [],
            'raw_errors'  => [],
            'raw'         => null,
        ];

        $asins        = array_values(array_filter(array_unique(array_map('strval', $asins))));
        $clientId     = trim((string) ($context['client_id'] ?? ''));
        $clientSecret = trim((string) ($context['client_secret'] ?? ''));
        $partnerTag   = trim((string) ($context['partner_tag'] ?? ''));
        $marketplace  = trim((string) ($context['marketplace'] ?? 'www.amazon.de'));

        // RC2: stub/config guard — never classify against a stub/placeholder response.
        if ($asins === [] || $clientId === '' || $clientSecret === '' || $partnerTag === '') {
            $out['auth_state'] = 'config_error';
            return $out;
        }

        // RC1: token failure = account-wide auth problem.
        try {
            $token = $this->get_access_token($clientId, $clientSecret);
        } catch (\Throwable $e) {
            $out['auth_state'] = 'token_failed';
            return $out;
        }

        try {
            [$status, $payload] = $this->request(
                'POST',
                self::CATALOG_URL,
                [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                    'x-marketplace' => $marketplace,
                ],
                [
                    'itemIds'     => $asins,
                    'itemIdType'  => 'ASIN',
                    'marketplace' => $marketplace,
                    'partnerTag'  => $partnerTag,
                    'resources'   => [
                        'images.primary.medium',
                        'itemInfo.title',
                        'offersV2.listings.price',
                    ],
                ]
            );
        } catch (\Throwable $e) {
            $out['auth_state'] = 'transient_error'; // network / WP_Error
            return $out;
        }

        $out['http_status'] = (int) $status;

        // RC1: HTTP-status-driven auth/transient split.
        if ($status === 401 || $status === 403) {
            $out['auth_state'] = 'access_revoked';
            return $out;
        }
        if ($status === 429) {
            $out['auth_state'] = 'rate_limited';
            return $out;
        }
        if ($status !== 200) {
            $out['auth_state'] = 'transient_error';
            return $out;
        }

        if ($debug) {
            $out['raw'] = $payload;
        }

        $items = $payload['itemsResult']['items'] ?? $payload['itemResults']['items'] ?? [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $asin = strtoupper((string) ($item['asin'] ?? ''));
            if ($asin === '') {
                continue;
            }
            $offersNode    = $item['offersV2'] ?? null;
            $offersPresent = is_array($offersNode);
            $listings      = ($offersPresent && isset($offersNode['listings']) && is_array($offersNode['listings']))
                ? $offersNode['listings'] : [];
            $out['items'][$asin] = [
                'title'          => (string) ($item['itemInfo']['title']['displayValue'] ?? ''),
                'image_url'      => (string) ($this->resolve_image_urls($item['images'] ?? [])[0] ?? ''),
                'detail_url'     => (string) ($item['detailPageURL'] ?? ('https://www.amazon.de/dp/' . $asin)),
                'price_text'     => $this->extract_price_text($listings),
                'offers_present' => $offersPresent,
            ];
        }

        // Errors keyed per-ASIN where resolvable; never invent not_found from request-level errors (RC3).
        $rawErrors = $payload['errors'] ?? $payload['itemsResult']['errors'] ?? [];
        if (is_array($rawErrors)) {
            $out['raw_errors'] = $rawErrors;
            foreach ($rawErrors as $err) {
                if (! is_array($err)) {
                    continue;
                }
                $code = (string) ($err['code'] ?? $err['Code'] ?? '');
                $asin = strtoupper((string) ($err['asin'] ?? $err['itemId'] ?? $err['ItemId'] ?? ''));
                if (! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
                    $msg = strtoupper((string) ($err['message'] ?? $err['Message'] ?? ''));
                    if (preg_match('/\b([A-Z0-9]{10})\b/', $msg, $m)) {
                        $asin = $m[1];
                    } else {
                        continue; // unresolvable → request-level, do NOT map to not_found
                    }
                }
                if ($code !== '') {
                    $out['errors'][$asin] = $code;
                }
            }
        }

        return $out;
    }

    private function extract_price_text(array $listings): ?string {
        foreach ($listings as $listing) {
            if (! is_array($listing)) {
                continue;
            }
            $candidates = [
                $listing['price']['displayAmount'] ?? null,
                $listing['price']['money']['displayAmount'] ?? null,
                $listing['price']['amount'] ?? null,
            ];
            foreach ($candidates as $p) {
                if (is_string($p) && $p !== '') {
                    return $p;
                }
                if (is_int($p) || is_float($p)) {
                    return (string) $p;
                }
            }
        }
        return null;
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
