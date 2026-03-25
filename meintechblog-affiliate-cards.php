<?php
/**
 * Plugin Name: MeinTechBlog Affiliate Cards
 * Description: Native Gutenberg affiliate cards for Amazon products on meintechblog.de.
 * Version: 0.2.30
 * Author: meintechblog.de
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('MTB_AFFILIATE_CARDS_VERSION', '0.2.30');
define('MTB_AFFILIATE_CARDS_FILE', __FILE__);
define('MTB_AFFILIATE_CARDS_DIR', plugin_dir_path(__FILE__));
define('MTB_AFFILIATE_CARDS_URL', plugin_dir_url(__FILE__));

require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-settings.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-title-shortener.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-badge-resolver.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-renderer.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-token-scanner.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-post-processor.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-audit-service.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-amazon-client.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-block.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-product-library.php';
require_once MTB_AFFILIATE_CARDS_DIR . 'includes/class-mtb-affiliate-plugin.php';

if (function_exists('register_activation_hook')) {
    register_activation_hook(MTB_AFFILIATE_CARDS_FILE, ['MTB_Affiliate_Plugin', 'activate']);
}

MTB_Affiliate_Plugin::instance()->boot();
