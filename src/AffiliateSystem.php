<?php declare(strict_types=1);
namespace Gratis\Affiliate;

final class AffiliateSystem
{
    public static function register(): void
    {
        register_post_type('gratis_affiliate', [
            'labels' => ['name' => 'Affiliates'], 'public' => false, 'show_ui' => true,
            'menu_icon' => 'dashicons-money-alt', 'supports' => ['title', 'custom-fields'],
        ]);
        register_post_type('gratis_referral', [
            'labels' => ['name' => 'Referrals'], 'public' => false, 'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=gratis_affiliate',
        ]);

        register_post_meta('gratis_affiliate', '_user_id', ['type' => 'integer', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_affiliate', '_commission_rate', ['type' => 'number', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_affiliate', '_total_earned', ['type' => 'number', 'single' => true, 'show_in_rest' => true]);
        register_post_meta('gratis_affiliate', '_status', ['type' => 'string', 'single' => true, 'show_in_rest' => true]);

        // Track referral clicks via ?ref= parameter
        add_action('template_redirect', [__CLASS__, 'trackClick']);

        // Record commission on order
        add_action('gratis_commerce_order_completed', [__CLASS__, 'recordCommission'], 10, 2);
    }

    public static function trackClick(): void
    {
        $ref = $_GET['ref'] ?? '';
        if (!$ref) return;
        $affiliate = get_posts(['post_type' => 'gratis_affiliate', 'meta_key' => '_ref_code', 'meta_value' => $ref, 'numberposts' => 1]);
        if ($affiliate) {
            setcookie('gratis_ref', $ref, time() + (30 * 86400), '/');
        }
    }

    public static function recordCommission(int $productId, array $session): void
    {
        $ref = $_COOKIE['gratis_ref'] ?? '';
        if (!$ref) return;
        $affiliate = get_posts(['post_type' => 'gratis_affiliate', 'meta_key' => '_ref_code', 'meta_value' => $ref, 'numberposts' => 1]);
        if (!$affiliate) return;

        $aff = $affiliate[0];
        $rate = (float) get_post_meta($aff->ID, '_commission_rate', true) ?: 0.1;
        $amount = (($session['amount_total'] ?? 0) / 100) * $rate;

        wp_insert_post([
            'post_type' => 'gratis_referral', 'post_status' => 'publish',
            'post_title' => "Referral #{$aff->ID} - $" . number_format($amount, 2),
            'meta_input' => ['_affiliate_id' => $aff->ID, '_amount' => $amount, '_product_id' => $productId, '_status' => 'pending'],
        ]);

        $total = (float) get_post_meta($aff->ID, '_total_earned', true) + $amount;
        update_post_meta($aff->ID, '_total_earned', $total);
    }
}
