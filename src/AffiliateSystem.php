<?php declare(strict_types=1);

namespace Gratis\Affiliate;

defined('ABSPATH') || exit;

/**
 * Affiliate + referral CPTs, click tracking and commission recording.
 */
final class AffiliateSystem {

    private const COOKIE = 'gratis_ref';

    /** Register CPTs, meta and tracking hooks. */
    public static function register(): void {
        register_post_type('gratis_affiliate', [
            'labels'      => ['name' => __('Affiliates', 'gratis-affiliate'), 'singular_name' => __('Affiliate', 'gratis-affiliate')],
            'public'      => false,
            'show_ui'     => true,
            'menu_icon'   => 'dashicons-money-alt',
            'supports'    => ['title', 'custom-fields'],
        ]);
        register_post_type('gratis_referral', [
            'labels'       => ['name' => __('Referrals', 'gratis-affiliate'), 'singular_name' => __('Referral', 'gratis-affiliate')],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=gratis_affiliate',
        ]);

        register_post_meta('gratis_affiliate', '_user_id', ['type' => 'integer', 'single' => true, 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can('edit_posts')]);
        register_post_meta('gratis_affiliate', '_ref_code', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_key', 'auth_callback' => fn() => current_user_can('edit_posts')]);
        register_post_meta('gratis_affiliate', '_commission_rate', ['type' => 'number', 'single' => true, 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can('edit_posts')]);
        register_post_meta('gratis_affiliate', '_total_earned', ['type' => 'number', 'single' => true, 'show_in_rest' => true, 'auth_callback' => fn() => current_user_can('edit_posts')]);
        register_post_meta('gratis_affiliate', '_status', ['type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_key', 'auth_callback' => fn() => current_user_can('edit_posts')]);

        add_action('template_redirect', [__CLASS__, 'trackClick']);
        add_action('gratis_commerce_order_completed', [__CLASS__, 'recordCommission'], 10, 2);
    }

    /** Look up an affiliate post by its referral code. */
    public static function findByRefCode(string $code): ?\WP_Post {
        $code = sanitize_key($code);
        if ($code === '') {
            return null;
        }
        $found = get_posts([
            'post_type'   => 'gratis_affiliate',
            'numberposts' => 1,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key, WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- ref-code lookup is the intended query.
            'meta_key'    => '_ref_code',
            'meta_value'  => $code,
        ]);
        return $found ? $found[0] : null;
    }

    /** Drop a referral cookie when a valid ?ref= code is present. */
    public static function trackClick(): void {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public referral link, read-only lookup.
        $ref = isset($_GET['ref']) ? sanitize_key(wp_unslash($_GET['ref'])) : '';
        if ($ref === '') {
            return;
        }
        if (self::findByRefCode($ref) instanceof \WP_Post) {
            $days = Settings::cookieDays();
            setcookie(self::COOKIE, $ref, time() + ($days * DAY_IN_SECONDS), defined('COOKIEPATH') ? COOKIEPATH : '/');
        }
    }

    /** Record a pending commission for the cookied affiliate on order completion. */
    public static function recordCommission(int $productId, array $session): void {
        $ref = isset($_COOKIE[self::COOKIE]) ? sanitize_key(wp_unslash($_COOKIE[self::COOKIE])) : '';
        if ($ref === '') {
            return;
        }
        $aff = self::findByRefCode($ref);
        if (! $aff instanceof \WP_Post) {
            return;
        }

        $rate   = (float) get_post_meta($aff->ID, '_commission_rate', true);
        $rate   = $rate > 0 ? $rate : Settings::defaultRate();
        $amount = (((float) ($session['amount_total'] ?? 0)) / 100) * $rate;

        wp_insert_post([
            'post_type'   => 'gratis_referral',
            'post_status' => 'publish',
            'post_title'  => sprintf('Referral #%d - %s', $aff->ID, number_format($amount, 2)),
            'meta_input'  => [
                '_affiliate_id' => $aff->ID,
                '_amount'       => $amount,
                '_product_id'   => $productId,
                '_status'       => 'pending',
            ],
        ]);

        $total = (float) get_post_meta($aff->ID, '_total_earned', true) + $amount;
        update_post_meta($aff->ID, '_total_earned', $total);
    }

    /** Create an affiliate for a WordPress user; returns the post ID. */
    public static function createAffiliate(int $userId, float $rate): int {
        $user = get_userdata($userId);
        if (! $user) {
            return 0;
        }
        $ref_code = substr(md5($user->user_email . wp_rand()), 0, 8);
        $id       = wp_insert_post([
            'post_type'   => 'gratis_affiliate',
            'post_title'  => $user->display_name,
            'post_status' => 'publish',
            'meta_input'  => [
                '_user_id'         => $user->ID,
                '_ref_code'        => $ref_code,
                '_commission_rate' => $rate > 0 ? $rate : Settings::defaultRate(),
                '_total_earned'    => 0,
                '_status'          => 'active',
            ],
        ]);
        return is_wp_error($id) ? 0 : (int) $id;
    }
}
