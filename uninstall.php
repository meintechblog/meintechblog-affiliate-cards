<?php

declare(strict_types=1);

if (! defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

if (function_exists('delete_option')) {
    delete_option('mtb_affiliate_cards_settings');
}
