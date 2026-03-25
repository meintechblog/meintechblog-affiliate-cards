<?php

declare(strict_types=1);

final class MTB_Affiliate_Url_Resolver {
    /** ASIN pattern: B0-prefix, 10 chars total. Matches /dp/, /gp/product/, /product/, /exec/obidos/ASIN/. */
    private const ASIN_PATTERN = '/\/(?:dp|gp\/product|product|exec\/obidos\/ASIN)\/(B0[A-Z0-9]{8})\b/i';

    /**
     * Returns true if the text contains an amzn.to or amzn.eu short URL.
     * Port of the ShortURL Detector node regex from flows.json.
     */
    public function is_short_url(string $text): bool {
        return (bool) preg_match('/https?:\/\/amzn\.(to|eu)\/[^\s]+/i', $text);
    }

    /**
     * Extracts the first amzn.to or amzn.eu URL from text.
     * Returns null if no short URL is found.
     * Port of flows.json: raw.match(/https?:\/\/amzn\.(to|eu)\/[^\s]+/i).
     */
    public function extract_short_url(string $text): ?string {
        if (! preg_match('/https?:\/\/amzn\.(to|eu)\/[^\s]+/i', $text, $matches)) {
            return null;
        }

        return $matches[0];
    }

    /**
     * Follows redirects for an amzn.to/amzn.eu URL and returns the final resolved URL.
     * Uses wp_remote_get (NOT wp_safe_remote_get — safe variant blocks Amazon redirect chain, per PITFALLS Pitfall 5).
     * Returns null if resolution fails or wp_error occurs.
     */
    public function resolve(string $url): ?string {
        $response = wp_remote_get($url, [
            'timeout'     => 5,
            'redirection' => 10,
            'user-agent'  => 'Mozilla/5.0 (compatible; MTB-Affiliate-Bot/1.0)',
            'sslverify'   => true,
        ]);

        if (is_wp_error($response)) {
            return null;
        }

        // Primary: extract final URL from response object (equivalent to flows.json msg.responseUrl)
        $httpResponse = $response['http_response'] ?? null;
        if ($httpResponse !== null && is_object($httpResponse) && method_exists($httpResponse, 'get_response_object')) {
            $responseObject = $httpResponse->get_response_object();
            if (is_object($responseObject) && isset($responseObject->url) && is_string($responseObject->url) && $responseObject->url !== '') {
                return $responseObject->url;
            }
        }

        // Fallback: Location header (equivalent to flows.json msg.headers.location)
        $location = wp_remote_retrieve_header($response, 'location');
        if (is_string($location) && $location !== '') {
            return $location;
        }

        return null;
    }

    /**
     * Extracts an ASIN from an Amazon URL.
     * Supports /dp/ASIN, /gp/product/ASIN, /product/ASIN, /exec/obidos/ASIN/ASIN.
     * B0-prefix restriction matches flows.json extractAsin() exactly.
     * Returns null if no ASIN found.
     */
    public function extract_asin(string $url): ?string {
        if (! preg_match(self::ASIN_PATTERN, $url, $matches)) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
