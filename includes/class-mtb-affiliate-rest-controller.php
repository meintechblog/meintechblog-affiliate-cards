<?php

declare(strict_types=1);

final class MTB_Affiliate_Rest_Controller {
    private MTB_Affiliate_Settings $settings;
    private MTB_Affiliate_Amazon_Client $amazonClient;
    private MTB_Affiliate_Badge_Resolver $badgeResolver;

    public function __construct(
        MTB_Affiliate_Settings $settings,
        MTB_Affiliate_Amazon_Client $amazonClient,
        ?MTB_Affiliate_Badge_Resolver $badgeResolver = null
    ) {
        $this->settings = $settings;
        $this->amazonClient = $amazonClient;
        $this->badgeResolver = $badgeResolver ?? new MTB_Affiliate_Badge_Resolver();
    }

    public function register_routes(): void {
        if (! function_exists('register_rest_route')) {
            return;
        }

        register_rest_route('mtb-affiliate-cards/v1', '/item', [
            'methods' => 'GET',
            'callback' => [$this, 'get_item'],
            'permission_callback' => [$this, 'can_view_item'],
            'args' => [
                'asin' => [
                    'type' => 'string',
                    'required' => true,
                ],
                'postId' => [
                    'type' => 'integer',
                    'required' => false,
                ],
            ],
        ]);
    }

    public function can_view_item(): bool {
        if (! function_exists('current_user_can')) {
            return true;
        }

        return (bool) current_user_can('edit_posts');
    }

    public function get_item($request) {
        $asin = $this->normalize_asin($this->request_param($request, 'asin'));
        if ($asin === '') {
            return $this->error_response('mtb_missing_asin', 'Missing or invalid ASIN.');
        }

        $postId = (int) $this->request_param($request, 'postId');
        $postDate = '';
        $postContent = '';
        if ($postId > 0 && function_exists('get_post_field')) {
            $postDate = (string) get_post_field('post_date', $postId);
            $postContent = (string) get_post_field('post_content', $postId);
        }

        $response = [
            'asin' => $asin,
            'title' => $asin,
            'detailUrl' => 'https://www.amazon.de/dp/' . $asin,
            'images' => [],
            'imageUrl' => '',
            'suggestedBadgeMode' => $this->suggested_badge_mode($postContent),
            'suggestedBenefit' => '',
        ];

        $settings = $this->settings->get_all();
        if ($settings['client_id'] !== '' && $settings['client_secret'] !== '') {
            try {
                $partnerTag = $this->amazonClient->derive_partner_tag($postDate);
                $items = $this->amazonClient->get_items([$asin], [
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'marketplace' => $settings['marketplace'],
                    'partner_tag' => $partnerTag,
                ]);

                if (isset($items[0]) && is_array($items[0])) {
                    $item = $items[0];
                    $images = $this->normalize_images($item['images'] ?? []);
                    if ($images === [] && ! empty($item['image_url'])) {
                        $images = [(string) $item['image_url']];
                    }

                    $response['title'] = (string) ($item['title'] ?? $response['title']);
                    $response['detailUrl'] = (string) ($item['detail_url'] ?? $response['detailUrl']);
                    $response['images'] = $images;
                    $response['imageUrl'] = $images[0] ?? '';
                    $response['suggestedBenefit'] = (string) ($item['benefit'] ?? '');
                }
            } catch (Throwable $exception) {
                // Keep the fallback payload stable for editor hydration.
            }
        }

        return $this->rest_response($response);
    }

    private function request_param($request, string $key) {
        if (is_object($request) && method_exists($request, 'get_param')) {
            return $request->get_param($key);
        }
        if (is_array($request)) {
            return $request[$key] ?? null;
        }

        return null;
    }

    private function normalize_asin($value): string {
        $asin = strtoupper(trim((string) $value));
        if (! preg_match('/^[A-Z0-9]{10}$/', $asin)) {
            return '';
        }

        return $asin;
    }

    private function normalize_images($value): array {
        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $url) {
            if (! is_string($url)) {
                continue;
            }
            $url = trim($url);
            if ($url === '') {
                continue;
            }
            $normalized[] = $url;
        }

        return array_values(array_unique($normalized));
    }

    private function suggested_badge_mode(string $postContent): string {
        $label = $this->badgeResolver->resolve($postContent, 'auto');
        return $label === 'Im Video verwendet' ? 'video' : 'setup';
    }

    private function error_response(string $code, string $message) {
        if (class_exists('WP_Error')) {
            return new WP_Error($code, $message, ['status' => 400]);
        }

        return $this->rest_response([
            'code' => $code,
            'message' => $message,
            'status' => 400,
        ]);
    }

    private function rest_response(array $payload) {
        if (function_exists('rest_ensure_response')) {
            return rest_ensure_response($payload);
        }

        return $payload;
    }
}

