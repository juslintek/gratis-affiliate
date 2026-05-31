<?php declare(strict_types=1);
namespace Gratis\Affiliate\CLI;

final class AffiliateCommand
{
    /** @when after_wp_load */
    public function list($args, $assoc_args): void
    {
        $affiliates = get_posts(['post_type' => 'gratis_affiliate', 'numberposts' => -1]);
        if (empty($affiliates)) { \WP_CLI::log("No affiliates."); return; }
        $rows = array_map(fn($a) => [
            'ID' => $a->ID, 'Name' => $a->post_title,
            'Rate' => (get_post_meta($a->ID, '_commission_rate', true) * 100) . '%',
            'Earned' => '$' . number_format((float) get_post_meta($a->ID, '_total_earned', true), 2),
            'Status' => get_post_meta($a->ID, '_status', true) ?: 'active',
        ], $affiliates);
        \WP_CLI\Utils\format_items('table', $rows, ['ID', 'Name', 'Rate', 'Earned', 'Status']);
    }

    /**
     * Create an affiliate.
     * <user_id> : WordPress user ID.
     * [--rate=<rate>] : Commission rate (0.1 = 10%).
     * @when after_wp_load
     */
    public function create($args, $assoc_args): void
    {
        $user = get_userdata((int) $args[0]);
        if (!$user) { \WP_CLI::error("User not found."); }
        $ref_code = substr(md5($user->user_email . time()), 0, 8);
        $id = wp_insert_post([
            'post_type' => 'gratis_affiliate', 'post_title' => $user->display_name, 'post_status' => 'publish',
            'meta_input' => [
                '_user_id' => $user->ID, '_ref_code' => $ref_code,
                '_commission_rate' => (float) ($assoc_args['rate'] ?? 0.1),
                '_total_earned' => 0, '_status' => 'active',
            ],
        ]);
        \WP_CLI::success("Affiliate #{$id} created. Ref code: {$ref_code}");
        \WP_CLI::log("Referral URL: " . home_url("?ref={$ref_code}"));
    }
}
