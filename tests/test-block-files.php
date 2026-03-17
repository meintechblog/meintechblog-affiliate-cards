<?php

declare(strict_types=1);

$blockJsonPath = dirname(__DIR__) . '/blocks/affiliate-cards/block.json';
$editJsPath = dirname(__DIR__) . '/blocks/affiliate-cards/edit.js';
$indexJsPath = dirname(__DIR__) . '/blocks/affiliate-cards/index.js';
$templatePath = dirname(__DIR__) . '/templates/affiliate-cards.php';

if (! file_exists($blockJsonPath) || ! file_exists($editJsPath) || ! file_exists($indexJsPath) || ! file_exists($templatePath)) {
    fwrite(STDERR, "Expected block.json, index.js, edit.js and template files to exist.\n");
    exit(1);
}

$config = json_decode((string) file_get_contents($blockJsonPath), true, 512, JSON_THROW_ON_ERROR);

if (($config['name'] ?? '') !== 'meintechblog/affiliate-cards') {
    fwrite(STDERR, "Block name is wrong.\n");
    exit(1);
}

if (($config['attributes']['items']['type'] ?? '') !== 'array') {
    fwrite(STDERR, "Items attribute should be an array.\n");
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

if (! str_contains((string) file_get_contents($indexJsPath), 'replaceBlocks')) {
    fwrite(STDERR, "Editor index should replace paragraph blocks with affiliate blocks.\n");
    exit(1);
}

if (! str_contains((string) file_get_contents($templatePath), 'mtb-aff-card')) {
    fwrite(STDERR, "Template should render affiliate card markup.\n");
    exit(1);
}

echo "ok\n";
