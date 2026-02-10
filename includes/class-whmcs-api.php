<?php
/**
 * WHMCS Data Service Class
 *
 * This class handles all communication with WHMCS API feeds. It manages
 * data retrieval, cleaning of Javascript-wrapped responses, and implements
 * caching via the WordPress Transients API to optimize performance.
 *
 * @package    WHMCS_Price
 * @subpackage API
 * @since      2.2.0
 */

defined('ABSPATH') || exit;

class WHMCS_Price_API {

    /**
     * Default cache expiry time in seconds (1 hour).
     *
     * @since 2.2.0
     * @var int
     */
    private static $cache_expiry = 3600;

    /**
     * Retrieve the WHMCS base URL from plugin settings.
     *
     * @since 2.2.0
     * @access private
     * @return string The saved WHMCS URL or an empty string if not configured.
     */
    private static function get_url() {
        $options = get_option('whmcs_price_option');
        return isset($options['whmcs_url']) ? esc_url_raw($options['whmcs_url']) : '';
    }

    /**
     * Clean WHMCS JS-feed responses by stripping Javascript wrappers.
     *
     * WHMCS feeds are often delivered as 'document.write' JS strings. 
     * This method extracts the raw content so it can be safely used in HTML.
     *
     * @since 2.2.0
     * @access private
     * @param string $body The raw response body from the API request.
     * @return string The cleaned text string.
     */
    private static function clean_response($body) {
        $body = preg_replace('/document\.write\(\'/', '', $body);
        $body = preg_replace('/\'\);/', '', $body);
        return trim($body);
    }

    /**
     * Fetch Product Data (name, description, or price) from WHMCS.
     *
     * Utilizes WordPress transients to store the result based on a unique key
     * consisting of the Product ID, billing cycle, and requested attribute.
     *
     * @since 2.2.0
     * @access public
     * @param int    $pid           The Product ID in WHMCS.
     * @param string $billing_cycle The billing cycle (e.g., monthly, annually).
     * @param string $attribute     The field to retrieve (e.g., name, description, price).
     * @return string Returns the data from WHMCS or 'NA' on failure.
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
     * Fetch Domain Pricing for a specific TLD and type.
     *
     * @since 2.2.0
     * @access public
     * @param string $tld        The domain extension (e.g., com, net, org).
     * @param string $type       Transaction type (register, renew, transfer).
     * @param string $reg_period Registration period in years (e.g., 1, 2, 3).
     * @return string Returns the formatted price string or 'NA' on failure.
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