<?php
/**
 * WHMCS Data Service
 * Handles API requests and caching for both Shortcodes and Gutenberg Blocks.
 */

defined('ABSPATH') || exit;

class WHMCS_Price_API {

    private static $cache_expiry = 3600;

    /**
     * Get WHMCS URL from settings.
     */
    private static function get_url() {
        $options = get_option('whmcs_price_option');
        return isset($options['whmcs_url']) ? esc_url_raw($options['whmcs_url']) : '';
    }

    /**
     * Shared logic to clean WHMCS JS-feed responses.
     */
    private static function clean_response($body) {
        $body = preg_replace('/document\.write\(\'/', '', $body);
        $body = preg_replace('/\'\);/', '', $body);
        return trim($body);
    }

    /**
     * Fetch Product Data.
     */
    public static function get_product_data($pid, $billing_cycle, $attribute) {
        $whmcs_url = self::get_url();
        if (empty($whmcs_url)) return 'NA';

        $cache_key = "whmcs_product_{$pid}_{$billing_cycle}_{$attribute}";
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $url = "{$whmcs_url}/feeds/productsinfo.php?pid={$pid}&get={$attribute}&billingcycle={$billing_cycle}";
        $response = wp_remote_get($url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return 'NA';
        }

        $data = self::clean_response(wp_remote_retrieve_body($response));
        set_transient($cache_key, $data, self::$cache_expiry);

        return $data;
    }

    /**
     * Fetch Domain Pricing.
     */
    public static function get_domain_price($tld, $type, $reg_period) {
        $whmcs_url = self::get_url();
        if (empty($whmcs_url)) return 'NA';

        $tld = ltrim($tld, '.');
        $cache_key = "whmcs_domain_{$tld}_{$type}_{$reg_period}";
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $url = "{$whmcs_url}/feeds/domainprice.php?tld=.{$tld}&type={$type}&regperiod={$reg_period}&format=1";
        $response = wp_remote_get($url);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return 'NA';
        }

        $data = self::clean_response(wp_remote_retrieve_body($response));
        set_transient($cache_key, $data, self::$cache_expiry);

        return $data;
    }
}