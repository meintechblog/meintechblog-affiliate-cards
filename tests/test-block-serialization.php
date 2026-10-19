<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';

function check_serialization(bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$title = 'Titel --> <!-- <script> & "Zitat" \\Pfad ä';
$item = ['asin' => 'B0SERIAL01', 'title' => $title, 'detail_url' => 'https://example.com/?a=1&b=2'];
$processor = new MTB_Affiliate_Post_Processor(null, ['ctaLabel' => $title], static fn(array $asins): array => [$item]);

function verify_serialized_blocks(MTB_Affiliate_Post_Processor $processor, string $title): array {
    $outputs = [];
    foreach (['<p>amazon:B0SERIAL01</p>', '<p>Im Text amazon:B0SERIAL01 verwenden.</p>'] as $input) {
        $content = $processor->process($input)['content'];
        check_serialization((bool) preg_match('/<!-- wp:meintechblog\/affiliate-cards (.*?) -->/s', $content, $matches), 'New block must have a complete opening comment.');
        $json = $matches[1];
        foreach (['--', '<', '>', '&', '\\"'] as $forbidden) {
            check_serialization(! str_contains($json, $forbidden), 'Block JSON must escape comment-sensitive characters: ' . $forbidden);
        }
        foreach (['\\u002d\\u002d', '\\u003c', '\\u003e', '\\u0026', '\\u0022'] as $escape) {
            check_serialization(str_contains($json, $escape), 'Missing WordPress-compatible escape: ' . $escape);
        }
        $attrs = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        check_serialization($attrs['items'][0]['title'] === $title && $attrs['ctaLabel'] === $title, 'Escaping must preserve attribute values on decoding.');
        check_serialization($processor->process($content)['content'] === $content, 'Repeated processing must preserve generated blocks byte-for-byte.');
        $outputs[] = $content;
    }
    return $outputs;
}

$fallback = verify_serialized_blocks($processor, $title);
// An existing, differently formatted block must never be reserialized.
$existing = '<!-- wp:meintechblog/affiliate-cards { "items": [{"asin":"B0EXIST001","title":"Alt \\u0026 \\u0022"}] } /-->' . "\n\n";
check_serialization(str_starts_with($processor->process($existing . '<p>amazon:B0SERIAL01</p>')['content'], $existing), 'Existing block markup must stay byte-identical while adding new blocks.');

// Exercise the WordPress path as well as the standalone fallback in this process.
if (! function_exists('serialize_block_attributes')) {
    function serialize_block_attributes(array $attrs): string {
        $GLOBALS['serialize_block_attributes_calls'] = ($GLOBALS['serialize_block_attributes_calls'] ?? 0) + 1;
        return preg_replace(
            ['/--/', '/</', '/>/', '/&/', '/\\\\"/'],
            ['\\\\u002d\\\\u002d', '\\\\u003c', '\\\\u003e', '\\\\u0026', '\\\\u0022'],
            json_encode($attrs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );
    }
}
check_serialization(verify_serialized_blocks($processor, $title) === $fallback, 'WordPress and fallback serialization must match exactly.');
check_serialization(($GLOBALS['serialize_block_attributes_calls'] ?? 0) >= 2, 'Use the WordPress serializer when available.');

echo "ok\n";
