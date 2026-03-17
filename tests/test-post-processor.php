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

$inlineContent = <<<HTML
<!-- wp:paragraph -->
<p>Im Text: amazon:B0INLINE01 und hier ein Link <a href="https://www.amazon.de/dp/B0INLINE02">Produkt</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Mehrfach im Text: amazon:B0INLINE03, amazon:B0INLINE04, amazon:B0INLINE03.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Kleinschreibung im Token: amazon:b0inline05.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Kein Amazon-Link: <a href="https://example.com/dp/B0INLINE99">soll ignoriert werden</a>.</p>
<!-- /wp:paragraph -->
HTML;

$inlineResult = $processor->process($inlineContent);

assert_same_processor(
    ['B0INLINE01', 'B0INLINE02', 'B0INLINE03', 'B0INLINE04', 'B0INLINE05'],
    $inlineResult['asins'],
    'Processor should detect inline amazon:ASIN markers and Amazon /dp/ASIN links, deduped.'
);

assert_contains_processor('amazon:B0INLINE01', $inlineResult['content'], 'Inline amazon:ASIN markers should not remove the paragraph.');
assert_contains_processor('/dp/B0INLINE02', $inlineResult['content'], 'Inline Amazon dp link should remain in content.');
assert_contains_processor('amazon:B0INLINE04', $inlineResult['content'], 'Inline markers should remain in content.');
assert_contains_processor('amazon:b0inline05', $inlineResult['content'], 'Lowercase inline markers should remain untouched in content.');
assert_not_contains_processor('B0INLINE99', json_encode($inlineResult['asins']), 'Non-Amazon /dp/ASIN paths should not be collected.');

$inlineResolverProcessor = new MTB_Affiliate_Post_Processor(
    new MTB_Affiliate_Token_Scanner(),
    [],
    static function (array $asins): array {
        $items = [];
        foreach ($asins as $asin) {
            $items[] = [
                'asin' => $asin,
                'title' => 'Amazon Titel ' . $asin,
                'detail_url' => 'https://www.amazon.de/dp/' . $asin,
            ];
        }
        return $items;
    }
);

$inlineReplace = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Inline Start amazon:B0INLINE05, nochmal amazon:b0inline06 und am Ende amazon:B0INLINE05.</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor(
    'Inline Start <a href="https://www.amazon.de/dp/B0INLINE05"',
    $inlineReplace['content'],
    'Inline amazon:ASIN markers should be replaced with linked Amazon titles.'
);
assert_contains_processor(
    'Amazon Titel B0INLINE06 (Affiliate-Link)',
    $inlineReplace['content'],
    'Inline replacement should include the resolved title and Affiliate-Link suffix even for lowercase markers.'
);
assert_not_contains_processor('amazon:B0INLINE05', $inlineReplace['content'], 'Inline amazon:ASIN markers should be removed after replacement.');
assert_not_contains_processor('amazon:b0inline06', $inlineReplace['content'], 'Lowercase inline markers should also be removed after replacement.');

$inlineReplaceMulti = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Absatz eins amazon:B0INLINE07 im Text.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Absatz zwei amazon:B0INLINE08 im Text.</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor(
    'Absatz eins <a href="https://www.amazon.de/dp/B0INLINE07"',
    $inlineReplaceMulti['content'],
    'Inline replacements should occur in every paragraph, not only the last one.'
);
assert_contains_processor(
    'Absatz zwei <a href="https://www.amazon.de/dp/B0INLINE08"',
    $inlineReplaceMulti['content'],
    'Inline replacements should occur in every paragraph, not only the last one.'
);

$inlineProtected = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Bitte <code>amazon:B0INLINE09</code> so lassen und <a href="https://example.com">amazon:B0INLINE10</a> ebenfalls.</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor('<code>amazon:B0INLINE09</code>', $inlineProtected['content'], 'Inline markers inside code tags should stay untouched.');
assert_contains_processor('<a href="https://example.com">amazon:B0INLINE10</a>', $inlineProtected['content'], 'Inline markers inside existing links should stay untouched.');
assert_not_contains_processor('https://www.amazon.de/dp/B0INLINE09', $inlineProtected['content'], 'Processor should not create Amazon links inside code tags.');
assert_not_contains_processor('https://www.amazon.de/dp/B0INLINE10', $inlineProtected['content'], 'Processor should not nest Amazon links inside existing anchors.');

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
assert_contains_processor('"asin":"OLDASIN000"', $updated['content'], 'Updated block should preserve existing items.');

$dedupeContent = <<<HTML
<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0D7955R6N","title":"Schon da"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->

<!-- wp:paragraph -->
<p>B0D7955R6N</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>B0CLTV6YB2</p>
<!-- /wp:paragraph -->
HTML;

$deduped = $processor->process($dedupeContent);

if (substr_count($deduped['content'], '"asin":"B0D7955R6N"') !== 1) {
    fwrite(STDERR, "Processor should not duplicate existing ASIN entries when the same marker is added again.\n");
    exit(1);
}

assert_contains_processor('"title":"Schon da"', $deduped['content'], 'Processor should preserve enriched data for existing items.');
assert_contains_processor('"asin":"B0CLTV6YB2"', $deduped['content'], 'Processor should append new ASINs to the existing block.');

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
