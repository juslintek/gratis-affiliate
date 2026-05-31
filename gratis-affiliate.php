<?php
/**
 * Plugin Name: Gratis Affiliate
 * Description: Affiliate program management. Referral tracking, commissions, payouts. Replaces AffiliateWP ($299/yr).
 * Version: 1.0.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * License: GPL-2.0-or-later
 */
defined('ABSPATH') || exit;
require_once __DIR__ . '/autoload.php';
add_action('init', [\Gratis\Affiliate\AffiliateSystem::class, 'register']);
add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) \WP_CLI::add_command('gratis-affiliate', \Gratis\Affiliate\CLI\AffiliateCommand::class);
});
