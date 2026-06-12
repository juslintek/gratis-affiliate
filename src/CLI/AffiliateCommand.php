<?php declare(strict_types=1);

namespace Gratis\Affiliate\CLI;

use Gratis\Affiliate\AffiliateSystem;

defined('ABSPATH') || exit;

/**
 * WP-CLI commands for Gratis Affiliate.
 */
final class AffiliateCommand {

    /**
     * List affiliates.
     *
     * @when after_wp_load
     */
    public function list($args, $assoc_args): void {
        unset($args, $assoc_args);
        $affiliates = get_posts(['post_type' => 'gratis_affiliate', 'numberposts' => -1]);
        if (empty($affiliates)) {
            \WP_CLI::log('No affiliates.');
            return;
        }
        $rows = array_map(static fn($a) => [
            'ID'     => $a->ID,
            'Name'   => $a->post_title,
            'Code'   => get_post_meta($a->ID, '_ref_code', true),
            'Rate'   => (((float) get_post_meta($a->ID, '_commission_rate', true)) * 100) . '%',
            'Earned' => number_format((float) get_post_meta($a->ID, '_total_earned', true), 2),
            'Status' => get_post_meta($a->ID, '_status', true) ?: 'active',
        ], $affiliates);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Name', 'Code', 'Rate', 'Earned', 'Status']);
    }

    /**
     * Create an affiliate.
     *
     * <user_id>
     * : WordPress user ID.
     *
     * [--rate=<rate>]
     * : Commission rate (0.1 = 10%).
     *
     * @when after_wp_load
     */
    public function create($args, $assoc_args): void {
        $rate = isset($assoc_args['rate']) ? (float) $assoc_args['rate'] : 0.0;
        $id   = AffiliateSystem::createAffiliate((int) $args[0], $rate);
        if ($id === 0) {
            \WP_CLI::error('User not found or affiliate could not be created.');
        }
        $code = get_post_meta($id, '_ref_code', true);
        \WP_CLI::success("Affiliate #{$id} created. Ref code: {$code}");
        \WP_CLI::log('Referral URL: ' . home_url("?ref={$code}"));
    }
}
