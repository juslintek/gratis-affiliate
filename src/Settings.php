<?php declare(strict_types=1);

namespace Gratis\Affiliate;

defined('ABSPATH') || exit;

/**
 * Admin settings + payout tool for Gratis Affiliate.
 */
final class Settings {

    private const GROUP = 'gratis_affiliate_settings_group';
    private const PAGE  = 'gratis-affiliate-settings';

    private const OPT_RATE      = 'gratis_affiliate_default_rate';
    private const OPT_COOKIE    = 'gratis_affiliate_cookie_days';
    private const OPT_THRESHOLD = 'gratis_affiliate_payout_threshold';

    /** Register the settings submenu under the Affiliates menu. */
    public static function register_menu(): void {
        add_submenu_page(
            'edit.php?post_type=gratis_affiliate',
            __('Affiliate Settings', 'gratis-affiliate'),
            __('Settings', 'gratis-affiliate'),
            'manage_options',
            self::PAGE,
            [__CLASS__, 'render_page']
        );
    }

    /** Register options with typed sanitisers. */
    public static function register_settings(): void {
        register_setting(self::GROUP, self::OPT_RATE, [
            'type'              => 'number',
            'sanitize_callback' => [__CLASS__, 'sanitize_rate'],
            'default'           => 0.1,
        ]);
        register_setting(self::GROUP, self::OPT_COOKIE, [
            'type'              => 'integer',
            'sanitize_callback' => 'absint',
            'default'           => 30,
        ]);
        register_setting(self::GROUP, self::OPT_THRESHOLD, [
            'type'              => 'number',
            'sanitize_callback' => [__CLASS__, 'sanitize_money'],
            'default'           => 50,
        ]);
    }

    /** Register the mark-paid admin-post handler. */
    public static function register_actions(): void {
        add_action('admin_post_gratis_affiliate_payout', [__CLASS__, 'handle_payout']);
    }

    /** Clamp a commission rate to a 0–1 fraction. */
    public static function sanitize_rate($value): float {
        $rate = (float) $value;
        if ($rate < 0) {
            $rate = 0.0;
        }
        return $rate > 1 ? 1.0 : $rate;
    }

    /** Non-negative money value. */
    public static function sanitize_money($value): float {
        return max(0.0, (float) $value);
    }

    /** Default commission rate (fraction). */
    public static function defaultRate(): float {
        $rate = (float) get_option(self::OPT_RATE, 0.1);
        return $rate > 0 ? $rate : 0.1;
    }

    /** Referral cookie lifetime in days. */
    public static function cookieDays(): int {
        return max(1, (int) get_option(self::OPT_COOKIE, 30));
    }

    /** Minimum balance before payout. */
    public static function payoutThreshold(): float {
        return max(0.0, (float) get_option(self::OPT_THRESHOLD, 50));
    }

    /** Mark all pending referrals as paid (nonce + capability gated). */
    public static function handle_payout(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'gratis-affiliate'));
        }
        check_admin_referer('gratis_affiliate_payout');

        $pending = get_posts([
            'post_type'   => 'gratis_referral',
            // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- bounded payout batch.
            'numberposts' => 500,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- payout batch over pending referrals.
            'meta_key'    => '_status',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- payout batch over pending referrals.
            'meta_value'  => 'pending',
        ]);
        $count = 0;
        foreach ($pending as $referral) {
            update_post_meta($referral->ID, '_status', 'paid');
            ++$count;
        }

        wp_safe_redirect(add_query_arg(
            ['post_type' => 'gratis_affiliate', 'page' => self::PAGE, 'gratis_affiliate_paid' => $count],
            admin_url('edit.php')
        ));
        exit;
    }

    /** Render the settings screen. */
    public static function render_page(): void {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to access this page.', 'gratis-affiliate'));
        }

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Affiliate Settings', 'gratis-affiliate') . '</h1>';

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
        if (isset($_GET['gratis_affiliate_paid'])) {
            // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display flag.
            $paid = absint(wp_unslash($_GET['gratis_affiliate_paid']));
            echo '<div class="notice notice-success is-dismissible"><p>';
            printf(
                /* translators: %s: number of referrals. */
                esc_html__('Marked %s referral(s) as paid.', 'gratis-affiliate'),
                esc_html(number_format_i18n($paid))
            );
            echo '</p></div>';
        }

        echo '<form method="post" action="options.php">';
        settings_fields(self::GROUP);
        echo '<table class="form-table" role="presentation"><tbody>';

        echo '<tr><th scope="row"><label for="ga_rate">' . esc_html__('Default commission rate', 'gratis-affiliate') . '</label></th><td>';
        echo '<input type="number" step="0.01" min="0" max="1" id="ga_rate" class="small-text" name="' . esc_attr(self::OPT_RATE) . '" value="' . esc_attr((string) self::defaultRate()) . '">';
        echo '<p class="description">' . esc_html__('Fraction, e.g. 0.1 = 10%.', 'gratis-affiliate') . '</p>';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_cookie">' . esc_html__('Cookie duration (days)', 'gratis-affiliate') . '</label></th><td>';
        echo '<input type="number" min="1" id="ga_cookie" class="small-text" name="' . esc_attr(self::OPT_COOKIE) . '" value="' . esc_attr((string) self::cookieDays()) . '">';
        echo '</td></tr>';

        echo '<tr><th scope="row"><label for="ga_threshold">' . esc_html__('Payout threshold', 'gratis-affiliate') . '</label></th><td>';
        echo '<input type="number" step="0.01" min="0" id="ga_threshold" class="small-text" name="' . esc_attr(self::OPT_THRESHOLD) . '" value="' . esc_attr((string) self::payoutThreshold()) . '">';
        echo '</td></tr>';

        echo '</tbody></table>';
        submit_button(__('Save settings', 'gratis-affiliate'));
        echo '</form>';

        echo '<h2>' . esc_html__('Payouts', 'gratis-affiliate') . '</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('gratis_affiliate_payout');
        echo '<input type="hidden" name="action" value="gratis_affiliate_payout">';
        echo '<p class="description">' . esc_html__('Mark all pending referrals as paid.', 'gratis-affiliate') . '</p>';
        submit_button(__('Process payouts', 'gratis-affiliate'), 'secondary', 'submit', false);
        echo '</form>';

        echo '</div>';
    }
}
