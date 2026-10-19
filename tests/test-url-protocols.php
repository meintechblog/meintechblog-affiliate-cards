<?php

declare(strict_types=1);

foreach (['title-shortener', 'badge-resolver', 'renderer', 'settings', 'amazon-client', 'block', 'token-scanner', 'post-processor'] as $class) {
    require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-' . $class . '.php';
}
define('MTB_AFFILIATE_CARDS_DIR', dirname(__DIR__) . '/');

function check_url(bool $condition, string $message): void {
    if (! $condition) {
        fwrite(STDERR, $message . PHP_EOL);
        exit(1);
    }
}

$renderer = new MTB_Affiliate_Renderer();
$block = new MTB_Affiliate_Block();
$invalidUrls = [
    'javascript:alert(1)', 'JaVaScRiPt:alert(1)', 'data:image/svg+xml,<svg onload="alert(1)">',
    'vbscript:msgbox(1)', 'ftp://example.com/image.jpg', '//example.com/image.jpg',
    'javascript&#58;alert(1)', "java\nscript:alert(1)", '',
];
foreach ($invalidUrls as $url) {
    $item = ['asin' => 'B0URLTEST1', 'title' => '<img onerror="alert(1)">', 'detail_url' => $url, 'image_url' => $url];
    $html = $renderer->render_cards([$item]);
    check_url(substr_count($html, 'href=""') === 2, 'Invalid card URLs must become empty links: ' . $url);
    check_url(! str_contains($html, '<img '), 'Invalid image URLs must not produce an image: ' . $url);
    check_url(str_contains($html, '&lt;img onerror=&quot;alert(1)&quot;&gt;'), 'Titles must remain escaped text.');
    if ($url !== '') {
        $html = $block->render(['items' => [$item], 'detailUrl' => $url, 'images' => [$url]]);
        check_url(substr_count($html, 'href=""') === 2 && ! str_contains($html, '<img '), 'Persisted block URLs and gallery images must pass the output check.');
    }

    $processor = new MTB_Affiliate_Post_Processor(null, [], static fn(array $asins): array => [$item]);
    $processed = $processor->process('<p>Produkt amazon:B0URLTEST1 im Text.</p>')['content'];
    check_url(! str_contains($processed, '<a '), 'Invalid resolver URLs must not produce inline links: ' . $url);
}

// Preserve the previous escaping and all valid query/fragment bytes, including entities.
foreach (['https://example.com/item?tag=old-21&x=%2f#A', 'http://example.com/a?x=1&amp;y=2', 'HTTPS://example.com/ä?q="test"&a=\'b\''] as $url) {
    $escaped = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    $item = ['asin' => 'B0URLTEST1', 'title' => 'Produkt', 'detail_url' => $url, 'image_url' => $url];
    $html = $renderer->render_cards([$item]);
    check_url(substr_count($html, 'href="' . $escaped . '"') === 3, 'Valid card links must stay byte-identical.');
    check_url(str_contains($html, 'src="' . $escaped . '"'), 'Valid image URLs must stay byte-identical.');
    $processor = new MTB_Affiliate_Post_Processor(null, [], static fn(array $asins): array => [$item]);
    check_url(str_contains($processor->process('<p>Produkt amazon:B0URLTEST1 im Text.</p>')['content'], '<a href="' . $escaped . '"'), 'Valid inline URLs must keep their previous escaping.');
}

echo "ok\n";
