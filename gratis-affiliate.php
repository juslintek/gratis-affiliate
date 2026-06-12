<?php
/**
 * Plugin Name: Gratis Affiliate
 * Plugin URI: https://github.com/juslintek/gratis-affiliate
 * Description: Affiliate program management: referral tracking, commissions, and payouts. Replaces AffiliateWP ($299/yr).
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 8.1
 * Author: Linas Jusys
 * Author URI: https://github.com/juslintek
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: gratis-affiliate
 */

defined('ABSPATH') || exit;

define('GRATIS_AFFILIATE_VERSION', '1.0.0');
define('GRATIS_AFFILIATE_FILE', __FILE__);
define('GRATIS_AFFILIATE_DIR', plugin_dir_path(__FILE__));
define('GRATIS_AFFILIATE_URL', plugin_dir_url(__FILE__));

require_once __DIR__ . '/autoload.php';

add_action('init', [\Gratis\Affiliate\AffiliateSystem::class, 'register']);

// Admin settings page + payout action.
add_action('admin_menu', [\Gratis\Affiliate\Settings::class, 'register_menu']);
add_action('admin_init', [\Gratis\Affiliate\Settings::class, 'register_settings']);
\Gratis\Affiliate\Settings::register_actions();

add_action('plugins_loaded', function () {
    if (defined('WP_CLI') && WP_CLI) {
        \WP_CLI::add_command('gratis-affiliate', \Gratis\Affiliate\CLI\AffiliateCommand::class);
    }
});
