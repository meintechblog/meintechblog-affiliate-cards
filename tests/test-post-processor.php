<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-token-scanner.php';
require_once dirname(__DIR__) . '/includes/class-mtb-affiliate-post-processor.php';

function assert_same_processor($expected, $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, $message . PHP_EOL . 'Expected: ' . var_export($expected, true) . PHP_EOL . 'Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function assert_contains_processor(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) === false) {
        fwrite(STDERR, $message . PHP_EOL . 'Missing: ' . $needle . PHP_EOL);
        exit(1);
    }
}

function assert_not_contains_processor(string $needle, string $haystack, string $message): void {
    if (strpos($haystack, $needle) !== false) {
        fwrite(STDERR, $message . PHP_EOL . 'Unexpected: ' . $needle . PHP_EOL);
        exit(1);
    }
}

$processor = new MTB_Affiliate_Post_Processor(new MTB_Affiliate_Token_Scanner());

$content = <<<HTML
<!-- wp:paragraph -->
<p>Vor dem Block.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>B0D7955R6N</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>amazon:B0CLTV6YB2</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Nach dem Block.</p>
<!-- /wp:paragraph -->
HTML;

$result = $processor->process($content);

assert_same_processor(
    ['B0D7955R6N', 'B0CLTV6YB2'],
    $result['asins'],
    'Processor should collect all standalone ASIN markers.'
);

assert_contains_processor('<!-- wp:meintechblog/affiliate-cards', $result['content'], 'Processor should insert the affiliate cards block.');
assert_contains_processor('"asin":"B0D7955R6N"', $result['content'], 'Processor should serialize the first ASIN into block attrs.');
assert_contains_processor('"asin":"B0CLTV6YB2"', $result['content'], 'Processor should serialize the second ASIN into block attrs.');
assert_not_contains_processor('<p>B0D7955R6N</p>', $result['content'], 'Processor should remove the raw ASIN marker.');
assert_not_contains_processor('<p>amazon:B0CLTV6YB2</p>', $result['content'], 'Processor should remove the amazon:ASIN marker.');

$firstAffiliatePos = strpos($result['content'], '<!-- wp:meintechblog/affiliate-cards');
$afterIntroPos = strpos($result['content'], '<p>Vor dem Block.</p>');
$afterOutroPos = strpos($result['content'], '<p>Nach dem Block.</p>');

if ($firstAffiliatePos === false || $afterIntroPos === false || $afterOutroPos === false || ! ($afterIntroPos < $firstAffiliatePos && $firstAffiliatePos < $afterOutroPos)) {
    fwrite(STDERR, "Processor should insert the block at the first marker position.\n");
    exit(1);
}

$existingBlockContent = <<<HTML
<!-- wp:paragraph -->
<p>Intro.</p>
<!-- /wp:paragraph -->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"OLDASIN000","title":"Alt"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->

<!-- wp:paragraph -->
<p>B0CK3L9WD3</p>
<!-- /wp:paragraph -->
HTML;

$updated = $processor->process($existingBlockContent);

assert_same_processor(['B0CK3L9WD3'], $updated['asins'], 'Processor should still detect new markers when a block already exists.');

if (substr_count($updated['content'], '<!-- wp:meintechblog/affiliate-cards') !== 1) {
    fwrite(STDERR, "Processor should update the existing affiliate block instead of duplicating it.\n");
    exit(1);
}

assert_contains_processor('"asin":"B0CK3L9WD3"', $updated['content'], 'Updated block should contain the new ASIN.');
assert_not_contains_processor('"asin":"OLDASIN000"', $updated['content'], 'Updated block should replace stale items.');

$enrichingProcessor = new MTB_Affiliate_Post_Processor(
    new MTB_Affiliate_Token_Scanner(),
    [
        'badgeMode' => 'video',
        'ctaLabel' => 'Preis auf Amazon checken',
        'autoShortenTitles' => true,
    ],
    static function (array $asins): array {
        return [
            [
                'asin' => $asins[0],
                'title' => 'USB-C Tester Messgerät',
                'image_url' => 'https://images.example/tester.jpg',
                'detail_url' => 'https://www.amazon.de/dp/B0DF2KFDC8?tag=meintechblog-260317-21',
                'benefit' => 'USB-C Stromwerte direkt prüfen',
            ],
        ];
    }
);

$enriched = $enrichingProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>B0DF2KFDC8</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor('"title":"USB-C Tester Messgerät"', $enriched['content'], 'Processor should serialize resolved item titles.');
assert_contains_processor('"image_url":"https://images.example/tester.jpg"', $enriched['content'], 'Processor should serialize resolved item images.');
assert_contains_processor('"benefit":"USB-C Stromwerte direkt prüfen"', $enriched['content'], 'Processor should serialize resolved benefit lines.');
assert_contains_processor('"badgeMode":"video"', $enriched['content'], 'Processor should keep configured badge mode.');

echo "ok\n";
