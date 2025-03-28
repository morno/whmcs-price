<?php
/**
 * Developer: MohammadReza Kamali, Tobias Sörensson
 * Website: IRANWebServer.Net, weconnect.se
 * E-Mail: kamali@iranwebsv.net, tobias@weconnect.se
 * السلام علیک یا علی ابن موسی الرضا
 */

// Prevent direct access
defined('ABSPATH') || exit;

/**
 * Load plugin text domain for translations.
 */
function whmcspr_load_textdomain() {
    load_plugin_textdomain('whmcspr', false, dirname(plugin_basename(__FILE__)) . '/languages/');
}
add_action('plugins_loaded', 'whmcspr_load_textdomain');

// Get WHMCS URL option
$options = get_option('whmcs_price_option');
$whmcs_url = isset($options['whmcs_url']) ? $options['whmcs_url'] : '';

// Ensure WHMCS URL is valid
if (!empty($whmcs_url) && filter_var($whmcs_url, FILTER_VALIDATE_URL)) {

    /**
     * Shortcode function to fetch WHMCS product and domain prices.
     *
     * @param array $atts Shortcode attributes.
     * @return string The generated output.
     */
    function whmcs_func($atts) {
        $options = get_option('whmcs_price_option');
        $whmcs_url = isset($options['whmcs_url']) ? $options['whmcs_url'] : '';

        // Sanitize input attributes
        $atts = array_map('sanitize_text_field', $atts);

        // Define cache expiry time (1 hour)
        $cache_expiry = 3600;

        // Handle product pricing shortcode with multiple PIDs
        if (isset($atts['pid']) && isset($atts['bc'])) {
            return handle_product_pricing($atts, $whmcs_url, $cache_expiry);
        } elseif (isset($atts['tld']) && isset($atts['type']) && isset($atts['reg'])) {
            return handle_domain_pricing($atts, $whmcs_url, $cache_expiry);
        } else {
            return handle_all_domain_pricing($whmcs_url, $cache_expiry);
        }
    }

    /**
     * Handle product pricing shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @param string $whmcs_url WHMCS URL.
     * @param int $cache_expiry Cache expiry time.
     * @return string HTML output.
     */
    function handle_product_pricing($atts, $whmcs_url, $cache_expiry) {
        $pids = explode(',', $atts['pid']); // Split the PIDs by commas
        $bc = sanitize_text_field($atts['bc']);
        $show = isset($atts['show']) ? explode(',', sanitize_text_field($atts['show'])) : []; // Get the show attribute
        
        $billing_cycles = [
            '1m' => 'monthly',
            '3m' => 'quarterly',
            '6m' => 'semiannually',
            '1y' => 'annually',
            '2y' => 'biennially',
            '3y' => 'triennially',
        ];

        if (!array_key_exists($bc, $billing_cycles)) {
            return 'NA'; // Handle unrecognized billing cycle
        }

        $bc_r = $billing_cycles[$bc];
        $output = "<table class='whmcs-product-table'>";
        $output .= "<tr>
                        <th>" . esc_html__('Name', 'whmcspr') . "</th>
                        <th>" . esc_html__('Description', 'whmcspr') . "</th>
                        <th>" . esc_html__('Price', 'whmcspr') . "</th>
                    </tr>";

        foreach ($pids as $pid) {
            $pid = intval($pid); // Ensure the PID is an integer
            $row_data = fetch_product_data($pid, $bc_r, $show, $whmcs_url, $cache_expiry);
            $output .= "<tr>
                            <td>" . esc_html($row_data['name'] ?? 'N/A') . "</td>
                            <td>" . esc_html($row_data['description'] ?? 'N/A') . "</td>
                            <td>" . esc_html($row_data['price'] ?? 'N/A') . "</td>
                        </tr>";
        }

        $output .= "</table>";
        return $output;
    }

    /**
     * Fetch product data for a specific PID.
     *
     * @param int $pid Product ID.
     * @param string $bc_r Billing cycle.
     * @param array $show Attributes to show.
     * @param string $whmcs_url WHMCS URL.
     * @param int $cache_expiry Cache expiry time.
     * @return array Product data.
     */
    function fetch_product_data($pid, $bc_r, $show, $whmcs_url, $cache_expiry) {
        $row_data = [];
        foreach ($show as $attribute) {
            $attribute = sanitize_text_field($attribute); // Sanitize the attribute
            $cache_key = "whmcs_product_{$pid}_{$bc_r}_{$attribute}";
            $cached_data = get_transient($cache_key);
            if ($cached_data !== false) {
                $row_data[$attribute] = $cached_data; // Use cached data if available
                continue;
            }

            // Fetch remote content for the specific attribute
            $amount = wp_remote_get("$whmcs_url/feeds/productsinfo.php?pid=$pid&get=$attribute&billingcycle=$bc_r");
            if (is_wp_error($amount) || wp_remote_retrieve_response_code($amount) !== 200) {
                return ['name' => 'NA', 'description' => 'NA', 'price' => 'NA']; // Handle failed request
            }
            
            $response_body = wp_remote_retrieve_body($amount);
            $response_body = preg_replace('/document\.write\(\'/', '', $response_body);
            $response_body = preg_replace('/\'\);/', '', $response_body);

            set_transient($cache_key, esc_html($response_body), $cache_expiry);
            $row_data[$attribute] = esc_html($response_body);
        }
        return $row_data;
    }

    /**
     * Handle domain pricing shortcode.
     *
     * @param array $atts Shortcode attributes.
     * @param string $whmcs_url WHMCS URL.
     * @param int $cache_expiry Cache expiry time.
     * @return string HTML output.
     */
    function handle_domain_pricing($atts, $whmcs_url, $cache_expiry) {
        $tld = "." . sanitize_text_field($atts['tld']);
        $type = sanitize_text_field($atts['type']);
        $reg = sanitize_text_field($atts['reg']);
        $reg_r = str_replace("y", "", $reg);

        $cache_key = "whmcs_domain_{$tld}_{$type}_{$reg_r}";
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $amount = wp_remote_get("$whmcs_url/feeds/domainprice.php?tld=$tld&type=$type&regperiod=$reg_r&format=1");
        if (is_wp_error($amount) || wp_remote_retrieve_response_code($amount) !== 200) {
            return "NA"; // Handle failed request
        }
        
		$output = wp_remote_retrieve_body($amount);
		$output = preg_replace("/document\.write\('(.*?)'\);/", '$1', $output);
		$formatted_output = "<div class='whmcs-price'>$output</div>";
		
        set_transient($cache_key, $formatted_output, $cache_expiry);
        return $formatted_output;
    }

    /**
     * Handle fetching all domain prices.
     *
     * @param string $whmcs_url WHMCS URL.
     * @param int $cache_expiry Cache expiry time.
     * @return string HTML output.
     */
    function handle_all_domain_pricing($whmcs_url, $cache_expiry) {
        $cache_key = "whmcs_all_domains";
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }

        $amount = wp_remote_get("$whmcs_url/feeds/domainpricing.php");
        if (is_wp_error($amount) || wp_remote_retrieve_response_code($amount) !== 200) {
            return "NA"; // Handle failed request
        }

		$output = wp_remote_retrieve_body($amount);
		$output = preg_replace("/document\.write\('(.*?)'\);/", '$1', $output);
		$formatted_output = "<div class='whmcs-price'>$output</div>";

        set_transient($cache_key, $formatted_output, $cache_expiry);
        return $formatted_output;
    }

    // Register ShortCodes
    function whmcspr_shortcodes() {
        add_shortcode('whmcs', 'whmcs_func');
    }
    add_action('init', 'whmcspr_shortcodes');
}
?>
