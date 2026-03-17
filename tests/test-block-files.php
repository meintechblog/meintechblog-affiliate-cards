<?php

declare(strict_types=1);

$blockJsonPath = dirname(__DIR__) . '/blocks/affiliate-cards/block.json';
$editJsPath = dirname(__DIR__) . '/blocks/affiliate-cards/edit.js';
$indexJsPath = dirname(__DIR__) . '/blocks/affiliate-cards/index.js';
$templatePath = dirname(__DIR__) . '/templates/affiliate-cards.php';
$pluginClassPath = dirname(__DIR__) . '/includes/class-mtb-affiliate-plugin.php';
$readmePath = dirname(__DIR__) . '/README.md';
$howtoPath = dirname(__DIR__) . '/docs/HOWTO-USE.md';

if (! file_exists($blockJsonPath) || ! file_exists($editJsPath) || ! file_exists($indexJsPath) || ! file_exists($templatePath) || ! file_exists($pluginClassPath) || ! file_exists($readmePath) || ! file_exists($howtoPath)) {
    fwrite(STDERR, "Expected block, template, plugin class and docs files to exist.\n");
    exit(1);
}

$config = json_decode((string) file_get_contents($blockJsonPath), true, 512, JSON_THROW_ON_ERROR);

if (($config['name'] ?? '') !== 'meintechblog/affiliate-cards') {
    fwrite(STDERR, "Block name is wrong.\n");
    exit(1);
}

if (($config['title'] ?? '') !== 'Affiliate Card') {
    fwrite(STDERR, "Block title should use the singular Affiliate Card naming.\n");
    exit(1);
}

if (($config['attributes']['items']['type'] ?? '') !== 'array') {
    fwrite(STDERR, "Items attribute should be an array.\n");
    exit(1);
}

if (($config['attributes']['amazonTitle']['type'] ?? '') !== 'string') {
    fwrite(STDERR, "amazonTitle attribute should be declared as string for editor hydration.\n");
    exit(1);
}

if (($config['attributes']['detailUrl']['type'] ?? '') !== 'string') {
    fwrite(STDERR, "detailUrl attribute should be declared as string for editor hydration.\n");
    exit(1);
}

if (($config['attributes']['images']['type'] ?? '') !== 'array') {
    fwrite(STDERR, "images attribute should be declared as array for image galleries.\n");
    exit(1);
}

if (($config['attributes']['selectedImageIndex']['type'] ?? '') !== 'number') {
    fwrite(STDERR, "selectedImageIndex attribute should be declared as number for image selection.\n");
    exit(1);
}

if (($config['attributes']['loadState']['type'] ?? '') !== 'string') {
    fwrite(STDERR, "loadState attribute should be declared as string for editor fetch status.\n");
    exit(1);
}

if (($config['attributes']['loadError']['type'] ?? '') !== 'string') {
    fwrite(STDERR, "loadError attribute should be declared as string for editor fetch errors.\n");
    exit(1);
}

if (($config['usesContext'] ?? []) !== ['postId', 'postType']) {
    fwrite(STDERR, "Block should request postId and postType context for dynamic enrichment.\n");
    exit(1);
}

if (($config['supports']['html'] ?? null) !== false) {
    fwrite(STDERR, "Block should keep raw HTML editing disabled.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($editJsPath), 'InspectorControls')) {
    fwrite(STDERR, "Editor file should use InspectorControls.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'amazon:')) {
    fwrite(STDERR, "Editor index should detect amazon:ASIN triggers.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Affiliate Card')) {
    fwrite(STDERR, "Editor index should use the singular Affiliate Card naming.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($pluginClassPath), 'Affiliate Card')) {
    fwrite(STDERR, "Settings UI should use the singular Affiliate Card naming.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($readmePath), '`Affiliate Card`-Block')) {
    fwrite(STDERR, "README should describe the singular Affiliate Card block.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($howtoPath), '`Affiliate Card`-Block')) {
    fwrite(STDERR, "How-to should describe the singular Affiliate Card block.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'replaceBlocks')) {
    fwrite(STDERR, "Editor index should replace paragraph blocks with affiliate blocks.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'loadState')) {
    fwrite(STDERR, "Editor index should initialize and update loadState for hydration.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'mtb-affiliate-cards/v1/item')) {
    fwrite(STDERR, "Editor index should call the affiliate hydration REST endpoint.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'updateBlockAttributes')) {
    fwrite(STDERR, "Editor index should update block attributes after hydration.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'loadError')) {
    fwrite(STDERR, "Editor index should set loadError when hydration fails.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'getBlock( clientId )')) {
    fwrite(STDERR, "Editor hydration should guard against missing blocks before attribute updates.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'currentAsin !== asin')) {
    fwrite(STDERR, "Editor hydration should guard against stale ASIN mismatches.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), "attrs.loadState !== 'loading'")) {
    fwrite(STDERR, "Editor hydration should only apply while the block is still in loading state.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), '! currentItem.benefit && suggestedBenefit')) {
    fwrite(STDERR, "Editor hydration should not overwrite existing benefit edits.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), "attrs.badgeMode === 'auto'")) {
    fwrite(STDERR, "Editor hydration should only apply suggested badge mode when badge is still auto.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'currentItem.title === asin')) {
    fwrite(STDERR, "Editor hydration should avoid overriding existing title edits.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Badge über dem Bild')) {
    fwrite(STDERR, "Editor should provide an in-block badge dropdown control.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Kurztitel überschreiben')) {
    fwrite(STDERR, "Editor should provide an in-block short title override field.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Nutzenzeile')) {
    fwrite(STDERR, "Editor should provide an in-block benefit line field.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Bild zurück') || ! str_contains((string) file_get_contents($indexJsPath), 'Bild weiter')) {
    fwrite(STDERR, "Editor should provide left/right controls for image selection.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($indexJsPath), 'Produktdaten neu laden')) {
    fwrite(STDERR, "Editor should provide a retry action for hydration errors.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($templatePath), 'mtb-aff-card')) {
    fwrite(STDERR, "Template should render affiliate card markup.\n");
    exit(1);
}

echo "ok\n";
