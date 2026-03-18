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

$inlineCards = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Im Absatz stehen amazon:B0INLINE11 und amazon:B0INLINE12.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Danach normaler Text.</p>
<!-- /wp:paragraph -->
HTML);

if (substr_count($inlineCards['content'], '<!-- wp:meintechblog/affiliate-cards') !== 2) {
    fwrite(STDERR, "Inline paragraphs should create one affiliate card block per unique ASIN.\n");
    exit(1);
}

$inlineParagraphPos = strpos($inlineCards['content'], 'Im Absatz stehen');
$inlineCardOnePos = strpos($inlineCards['content'], '"asin":"B0INLINE11"');
$inlineCardTwoPos = strpos($inlineCards['content'], '"asin":"B0INLINE12"');
$afterInlineParagraphPos = strpos($inlineCards['content'], 'Danach normaler Text.');

if ($inlineParagraphPos === false || $inlineCardOnePos === false || $inlineCardTwoPos === false || $afterInlineParagraphPos === false || ! ($inlineParagraphPos < $inlineCardOnePos && $inlineCardOnePos < $inlineCardTwoPos && $inlineCardTwoPos < $afterInlineParagraphPos)) {
    fwrite(STDERR, "Inline affiliate cards should be inserted directly after the matching paragraph in token order.\n");
    exit(1);
}

$introInlineCards = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Intro mit <a href="https://www.amazon.de/dp/B0INLINE18?tag=meintechblog-251007-21">Produkt</a> und amazon:B0INLINE19.</p>
<!-- /wp:paragraph -->

<!-- wp:more -->
<!--more-->
<!-- /wp:more -->

<!-- wp:paragraph -->
<p>Hauptteil startet hier.</p>
<!-- /wp:paragraph -->
HTML);

if (substr_count($introInlineCards['content'], '<!-- wp:meintechblog/affiliate-cards') !== 2) {
    fwrite(STDERR, "Intro affiliate references before the more block should still create one card per product.\n");
    exit(1);
}

$introParagraphPos = strpos($introInlineCards['content'], 'Intro mit <a href="https://www.amazon.de/dp/B0INLINE18');
$morePos = strpos($introInlineCards['content'], '<!-- wp:more -->');
$introCardOnePos = strpos($introInlineCards['content'], '"asin":"B0INLINE18"');
$introCardTwoPos = strpos($introInlineCards['content'], '"asin":"B0INLINE19"');
$bodyPos = strpos($introInlineCards['content'], 'Hauptteil startet hier.');

if (
    $introParagraphPos === false
    || $morePos === false
    || $introCardOnePos === false
    || $introCardTwoPos === false
    || $bodyPos === false
    || ! ($introParagraphPos < $morePos && $morePos < $introCardOnePos && $introCardOnePos < $introCardTwoPos && $introCardTwoPos < $bodyPos)
) {
    fwrite(STDERR, "Intro affiliate cards should be deferred until after the first more block.\n");
    exit(1);
}

$classicInlineCards = $inlineResolverProcessor->process(<<<HTML
<p>Erster Klassiker mit <a href="https://www.amazon.de/dp/B0CLASSIC1?tag=meintechblog-250325-21">Produkt 1 (Affiliate-Link)</a>.</p>

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0CLASSIC1","title":"Alter Titel","detail_url":"https://old.example/B0CLASSIC1"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->

<p>Zweiter Klassiker mit <a href="https://www.amazon.de/dp/B0CLASSIC2?tag=meintechblog-250325-21">Produkt 2 (Affiliate-Link)</a> und <a href="https://www.amazon.de/dp/B0CLASSIC3?tag=meintechblog-250325-21">Produkt 3 (Affiliate-Link)</a>.</p>

<p>Abschluss ohne Affiliate.</p>
HTML);

if (substr_count($classicInlineCards['content'], '<!-- wp:meintechblog/affiliate-cards') !== 3) {
    fwrite(STDERR, "Classic HTML paragraphs should create one affiliate card per detected Amazon product.\n");
    exit(1);
}

$classicFirstParagraphPos = strpos($classicInlineCards['content'], 'Erster Klassiker mit');
$classicFirstCardPos = strpos($classicInlineCards['content'], '"asin":"B0CLASSIC1"');
$classicSecondParagraphPos = strpos($classicInlineCards['content'], 'Zweiter Klassiker mit');
$classicSecondCardPos = strpos($classicInlineCards['content'], '"asin":"B0CLASSIC2"');
$classicThirdCardPos = strpos($classicInlineCards['content'], '"asin":"B0CLASSIC3"');
$classicTailPos = strpos($classicInlineCards['content'], 'Abschluss ohne Affiliate.');

if (
    $classicFirstParagraphPos === false
    || $classicFirstCardPos === false
    || $classicSecondParagraphPos === false
    || $classicSecondCardPos === false
    || $classicThirdCardPos === false
    || $classicTailPos === false
    || ! ($classicFirstParagraphPos < $classicFirstCardPos
        && $classicFirstCardPos < $classicSecondParagraphPos
        && $classicSecondParagraphPos < $classicSecondCardPos
        && $classicSecondCardPos < $classicThirdCardPos
        && $classicThirdCardPos < $classicTailPos)
) {
    fwrite(STDERR, "Classic HTML affiliate cards should stay attached to their matching paragraphs.\n");
    exit(1);
}

$classicIntroInlineCards = $inlineResolverProcessor->process(<<<HTML
<p>Intro klassisch mit <a href="https://www.amazon.de/dp/B0CLASSIC4?tag=meintechblog-250325-21">Produkt 4 (Affiliate-Link)</a> und amazon:B0CLASSIC5.</p>

<!--more-->

<p>Body beginnt hier.</p>
HTML);

if (substr_count($classicIntroInlineCards['content'], '<!-- wp:meintechblog/affiliate-cards') !== 2) {
    fwrite(STDERR, "Classic intro affiliate references before a raw more tag should still create one card per product.\n");
    exit(1);
}

$classicIntroParagraphPos = strpos($classicIntroInlineCards['content'], 'Intro klassisch mit');
$classicMorePos = strpos($classicIntroInlineCards['content'], '<!--more-->');
$classicIntroCardOnePos = strpos($classicIntroInlineCards['content'], '"asin":"B0CLASSIC4"');
$classicIntroCardTwoPos = strpos($classicIntroInlineCards['content'], '"asin":"B0CLASSIC5"');
$classicBodyPos = strpos($classicIntroInlineCards['content'], 'Body beginnt hier.');

if (
    $classicIntroParagraphPos === false
    || $classicMorePos === false
    || $classicIntroCardOnePos === false
    || $classicIntroCardTwoPos === false
    || $classicBodyPos === false
    || ! ($classicIntroParagraphPos < $classicMorePos
        && $classicMorePos < $classicIntroCardOnePos
        && $classicIntroCardOnePos < $classicIntroCardTwoPos
        && $classicIntroCardTwoPos < $classicBodyPos)
) {
    fwrite(STDERR, "Classic intro affiliate cards should be deferred until after a raw more tag.\n");
    exit(1);
}

$placeholderLiteral = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Literal __MTB_AFFILIATE_INLINE_BLOCK_1__ muss stehen bleiben, plus amazon:B0INLINE13.</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor('__MTB_AFFILIATE_INLINE_BLOCK_1__', $placeholderLiteral['content'], 'Literal placeholder-like user text must not be replaced.');
assert_contains_processor('"asin":"B0INLINE13"', $placeholderLiteral['content'], 'Real inline affiliate markers should still create a block.');

$inlineExistingAdjacent = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Direkt darunter steht amazon:B0INLINE14.</p>
<!-- /wp:paragraph -->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0INLINE14","title":"Alter Titel","detail_url":"https://old.example/B0INLINE14"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->

<!-- wp:paragraph -->
<p>Nachfolgeabsatz.</p>
<!-- /wp:paragraph -->
HTML);

if (substr_count($inlineExistingAdjacent['content'], '"asin":"B0INLINE14"') !== 1) {
    fwrite(STDERR, "Adjacent inline affiliate cards should be updated instead of duplicated.\n");
    exit(1);
}

assert_contains_processor('Amazon Titel B0INLINE14', $inlineExistingAdjacent['content'], 'Adjacent inline affiliate card should keep the newer resolved content.');
assert_not_contains_processor('https://old.example/B0INLINE14', $inlineExistingAdjacent['content'], 'Adjacent inline affiliate card should replace stale detail URLs.');

$repeatedAsinAcrossParagraphs = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Erster Absatz mit amazon:B0INLINE20 im Text.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Zweiter Absatz mit amazon:B0INLINE20 erneut im Text.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Dritter Absatz mit amazon:B0INLINE21.</p>
<!-- /wp:paragraph -->
HTML);

if (substr_count($repeatedAsinAcrossParagraphs['content'], '<!-- wp:meintechblog/affiliate-cards') !== 2) {
    fwrite(STDERR, "Repeated ASINs across different paragraphs should only create one card per unique ASIN.\n");
    exit(1);
}

if (substr_count($repeatedAsinAcrossParagraphs['content'], '"asin":"B0INLINE20"') !== 1) {
    fwrite(STDERR, "Repeated ASINs across different paragraphs should not create duplicate cards for the same product.\n");
    exit(1);
}

assert_contains_processor(
    'Zweiter Absatz mit <a href="https://www.amazon.de/dp/B0INLINE20"',
    $repeatedAsinAcrossParagraphs['content'],
    'Later paragraphs should keep the linked inline text even when the card was already shown earlier.'
);

$inlineResave = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Ich nutze <a href="https://www.amazon.de/dp/B0INLINE16?tag=meintechblog-260317-21">Amazon Titel B0INLINE16 (Affiliate-Link)</a> und <a href="https://www.amazon.de/dp/B0INLINE17?tag=meintechblog-260317-21">Amazon Titel B0INLINE17 (Affiliate-Link)</a> im Setup.</p>
<!-- /wp:paragraph -->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0INLINE16","title":"Amazon Titel B0INLINE16","detail_url":"https://www.amazon.de/dp/B0INLINE16?tag=meintechblog-260317-21"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->

<!-- wp:meintechblog/affiliate-cards {"items":[{"asin":"B0INLINE17","title":"Amazon Titel B0INLINE17","detail_url":"https://www.amazon.de/dp/B0INLINE17?tag=meintechblog-260317-21"}],"badgeMode":"auto","ctaLabel":"Preis auf Amazon checken","autoShortenTitles":true} /-->
HTML);

if (substr_count($inlineResave['content'], '<!-- wp:meintechblog/affiliate-cards') !== 2) {
    fwrite(STDERR, "Re-saving an already enriched inline paragraph should not create extra affiliate card blocks.\n");
    exit(1);
}

if (substr_count($inlineResave['content'], '"asin":"B0INLINE16"') !== 1 || substr_count($inlineResave['content'], '"asin":"B0INLINE17"') !== 1) {
    fwrite(STDERR, "Re-saving an already enriched inline paragraph should keep one card per ASIN.\n");
    exit(1);
}

$separatedInlineParagraphs = $inlineResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Erster Absatz mit <a href="https://www.amazon.de/dp/B016BLLINE?tag=meintechblog-250923-21">Link 1 (Affiliate-Link)</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p>Zweiter Absatz mit <a href="https://www.amazon.de/dp/B076YDPYLD?tag=meintechblog-250923-21">Link 2 (Affiliate-Link)</a> und <a href="https://www.amazon.de/dp/B00PZZ9OFC?tag=meintechblog-250923-21">Link 3 (Affiliate-Link)</a>.</p>
<!-- /wp:paragraph -->

<!-- wp:heading -->
<h2>Weiter</h2>
<!-- /wp:heading -->
HTML);

if (substr_count($separatedInlineParagraphs['content'], '<!-- wp:meintechblog/affiliate-cards') !== 3) {
    fwrite(STDERR, "Separate inline affiliate paragraphs should keep all generated affiliate card blocks.\n");
    exit(1);
}

$firstParagraphPos = strpos($separatedInlineParagraphs['content'], 'Erster Absatz mit');
$secondParagraphPos = strpos($separatedInlineParagraphs['content'], 'Zweiter Absatz mit');
$firstCardPos = strpos($separatedInlineParagraphs['content'], '"asin":"B016BLLINE"');
$secondCardPos = strpos($separatedInlineParagraphs['content'], '"asin":"B076YDPYLD"');
$thirdCardPos = strpos($separatedInlineParagraphs['content'], '"asin":"B00PZZ9OFC"');
$headingPos = strpos($separatedInlineParagraphs['content'], '<h2>Weiter</h2>');

if (
    $firstParagraphPos === false
    || $secondParagraphPos === false
    || $firstCardPos === false
    || $secondCardPos === false
    || $thirdCardPos === false
    || $headingPos === false
    || ! ($firstParagraphPos < $firstCardPos && $firstCardPos < $secondParagraphPos && $secondParagraphPos < $secondCardPos && $secondCardPos < $thirdCardPos && $thirdCardPos < $headingPos)
) {
    fwrite(STDERR, "Paragraphs between separate affiliate-card groups must remain in place.\n");
    exit(1);
}

$inlinePartialResolverProcessor = new MTB_Affiliate_Post_Processor(
    new MTB_Affiliate_Token_Scanner(),
    [],
    static function (array $asins): array {
        $items = [];
        foreach ($asins as $asin) {
            if ($asin !== 'B0INLINE15') {
                continue;
            }

            $items[] = [
                'asin' => $asin,
                'title' => 'Amazon Titel ' . $asin,
                'detail_url' => 'https://www.amazon.de/dp/' . $asin,
            ];
        }

        return $items;
    }
);

$inlineInvalidUnresolved = $inlinePartialResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Gültig amazon:B0INLINE15 und ungültig amazon:INVALID123 im gleichen Absatz.</p>
<!-- /wp:paragraph -->
HTML);

if (substr_count($inlineInvalidUnresolved['content'], '<!-- wp:meintechblog/affiliate-cards') !== 1) {
    fwrite(STDERR, "Only resolved inline affiliate markers should create affiliate card blocks.\n");
    exit(1);
}

assert_contains_processor('Amazon Titel B0INLINE15 (Affiliate-Link)', $inlineInvalidUnresolved['content'], 'Resolved inline markers should still be replaced with linked affiliate text.');
assert_contains_processor('amazon:INVALID123', $inlineInvalidUnresolved['content'], 'Unresolved inline markers should remain visible in the paragraph.');
assert_not_contains_processor('"asin":"INVALID123"', $inlineInvalidUnresolved['content'], 'Unresolved inline markers must not create empty affiliate card blocks.');

$inlineExistingLinkResolverProcessor = new MTB_Affiliate_Post_Processor(
    new MTB_Affiliate_Token_Scanner(),
    [],
    static function (array $asins): array {
        $items = [];
        foreach ($asins as $asin) {
            if ($asin === 'B0MISMCH1A') {
                $items[] = [
                    'asin' => $asin,
                    'title' => '9 PCS HSS T-Griff Schraubenschlüssel + Gewindebohrer',
                    'image_url' => 'https://images.example/gewinde.jpg',
                    'images' => ['https://images.example/gewinde.jpg'],
                    'detail_url' => 'https://www.amazon.de/dp/' . $asin . '?tag=meintechblog-241022-21',
                ];
                continue;
            }

            if ($asin === 'B0MISMCH2A') {
                $items[] = [
                    'asin' => $asin,
                    'title' => 'Amazon Business American Express Card',
                    'image_url' => 'https://images.example/card.jpg',
                    'images' => ['https://images.example/card.jpg'],
                    'detail_url' => 'https://www.amazon.de/dp/' . $asin . '?tag=meintechblog-241022-21',
                ];
            }
        }

        return $items;
    }
);

$inlineExistingLinkMismatch = $inlineExistingLinkResolverProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p><a href="https://www.amazon.de/dp/B0MISMCH1A?tag=meintechblog-241022-21">Gewindebohrerset (Affiliate-Link)</a></p>
<!-- /wp:paragraph -->

<!-- wp:paragraph -->
<p><a href="https://www.amazon.de/dp/B0MISMCH2A?tag=meintechblog-241022-21">Schrumpfschlauch 4:1 mit Kleber schwarz Ø32mm (Affiliate-Link)</a></p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor('"asin":"B0MISMCH1A"', $inlineExistingLinkMismatch['content'], 'Soft title mismatches should still create a card for the linked product.');
assert_contains_processor('"title":"Gewindebohrerset"', $inlineExistingLinkMismatch['content'], 'Soft mismatches should prefer the shorter existing link text as card title.');
assert_contains_processor('https://images.example/gewinde.jpg', $inlineExistingLinkMismatch['content'], 'Soft mismatches may still keep the resolved product image.');
assert_not_contains_processor('"asin":"B0MISMCH2A"', $inlineExistingLinkMismatch['content'], 'Hard mismatches must not auto-create a misleading affiliate card.');
assert_not_contains_processor('Amazon Business American Express Card', $inlineExistingLinkMismatch['content'], 'Hard mismatches must not leak the wrong Amazon title into the content.');
assert_contains_processor('Schrumpfschlauch 4:1 mit Kleber schwarz Ø32mm (Affiliate-Link)', $inlineExistingLinkMismatch['content'], 'Hard mismatches should leave the existing inline affiliate link text untouched.');

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

$inlineFallbackCardProcessor = new MTB_Affiliate_Post_Processor(
    new MTB_Affiliate_Token_Scanner(),
    [],
    static fn(array $asins): array => []
);

$inlineFallbackCard = $inlineFallbackCardProcessor->process(<<<HTML
<!-- wp:paragraph -->
<p>Ich nutze die <a href="https://www.amazon.de/dp/B0INLINE30?tag=meintechblog-241212-21" target="_blank" rel="noreferrer noopener">Alte Lötstation 60W (Affiliate-Link)</a> seit Jahren.</p>
<!-- /wp:paragraph -->
HTML);

assert_contains_processor('"asin":"B0INLINE30"', $inlineFallbackCard['content'], 'Existing Amazon links should still create a fallback card when the resolver returns no item.');
assert_contains_processor('"title":"Alte Lötstation 60W"', $inlineFallbackCard['content'], 'Fallback cards should reuse the existing link text without the Affiliate-Link suffix.');
assert_contains_processor('"detail_url":"https://www.amazon.de/dp/B0INLINE30?tag=meintechblog-241212-21"', $inlineFallbackCard['content'], 'Fallback cards should keep the existing affiliate URL.');

echo "ok\n";
