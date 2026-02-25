<?php
/**
 * Shortcode Implementation
 *
 * Provides the [whmcs] shortcode to display product and domain pricing
 * by utilizing the WHMCS_Price_API service class.
 *
 * @package    WHMCS_Price
 * @subpackage Shortcodes
 * @since      2.2.0
 */

defined('ABSPATH') || exit;

/**
 * The main shortcode handler function.
 * * This function parses the shortcode attributes, determines if the user
 * wants to display product data (in a table) or domain pricing (as a string),
 * and fetches the data accordingly via the API service.
 *
 * @since 2.2.0
 * @param array $atts {
 * Shortcode attributes provided by the user.
 *
 * @type string $pid  Comma-separated list of WHMCS Product IDs.
 * @type string $bc   Billing cycle code (1m, 3m, 6m, 1y, 2y, 3y).
 * @type string $show Comma-separated list of attributes to show (name, description, price).
 * @type string $tld  The domain extension (for domain pricing).
 * @type string $type The domain transaction type (register, renew, transfer).
 * @type string $reg  The registration period (e.g., '1y').
 * }
 * @return string HTML output containing the requested data or an empty string on failure.
 */
function whmcs_func($atts) {
    /**
     * Set default attributes and merge with user-provided ones.
     */
    $atts = shortcode_atts([
        'pid'  => '',
        'bc'   => '',
        'show' => 'name,description,price',
        'tld'  => '',
        'type' => '',
        'reg'  => ''
    ], $atts, 'whmcs');

    /**
     * 1. PRODUCT PRICING LOGIC
     * * If 'pid' and 'bc' are provided, the shortcode generates an HTML table
     * with product information fetched from WHMCS.
     */
    if (!empty($atts['pid']) && !empty($atts['bc'])) {
        // Map short cycle codes to WHMCS internal billing cycle names
        $billing_cycles = [
            '1m' => 'monthly', '3m' => 'quarterly', '6m' => 'semiannually',
            '1y' => 'annually', '2y' => 'biennially', '3y' => 'triennially',
        ];

        // Allowlist: only permit known billing cycle codes.
        $bc_r = $billing_cycles[ $atts['bc'] ] ?? '';
        if ( empty( $bc_r ) ) {
            return '';
        }

        $pids = array_map( 'intval', explode( ',', $atts['pid'] ) );
        // Remove any zero/invalid PIDs that resulted from intval().
        $pids = array_filter( $pids, fn($p) => $p > 0 );

        // Allowlist: only permit known column names.
        $allowed_attrs = array( 'name', 'description', 'price' );
        $show = array_filter(
            array_map( 'trim', explode( ',', $atts['show'] ) ),
            fn( $a ) => in_array( $a, $allowed_attrs, true )
        );

        if ( empty( $pids ) || empty( $show ) ) {
            return '';
        }

        /**
         * Translatable labels for table headers.
         * These strings are prepared for Poedit/Translation.
         */
        $header_labels = [
            'name'        => __('Name', 'whmcs-price'),
            'description' => __('Description', 'whmcs-price'),
            'price'       => __('Price', 'whmcs-price'),
        ];

        // Create a unique ID for the table based on PIDs to satisfy browser requirements
        $table_id = 'whmcs-table-' . md5($atts['pid'] . $atts['bc']);

        // Start table output
        $output = "<table id='" . esc_attr($table_id) . "' class='whmcs-product-table'><thead><tr>";
        foreach ($show as $header) { 
            $label = $header_labels[strtolower(trim($header))] ?? ucfirst($header);
            $output .= "<th>" . esc_html($label) . "</th>"; 
        }
        $output .= "</tr></thead><tbody>";

        // Loop through each Product ID and fetch requested attributes
        foreach ($pids as $pid) {
            $output .= "<tr>";
            foreach ($show as $attr) {
                $val = WHMCS_Price_API::get_product_data(intval($pid), $bc_r, sanitize_text_field($attr));
                $output .= "<td>" . esc_html($val) . "</td>";
            }
            $output .= "</tr>";
        }
        $output .= "</tbody></table>";
        return $output;
    }

    /**
     * 2) DOMAIN PRICING
     * If tld is provided => show a single price.
     * If no tld => show full TLD list from domainpricing.php.
     */
    if (!empty($atts['tld'])) {
        // Sanitize: strip dot prefix and allow only alphanumeric + hyphen (valid TLD characters).
        $tld = sanitize_text_field( ltrim( $atts['tld'], '.' ) );
        $tld = preg_replace( '/[^a-zA-Z0-9\-]/', '', $tld );
        if ( empty( $tld ) ) {
            return '';
        }

        // Allowlist: only permit known transaction types.
        $allowed_types = array( 'register', 'renew', 'transfer' );
        $type = in_array( $atts['type'], $allowed_types, true ) ? $atts['type'] : 'register';

        $reg_period = (string) preg_replace('/[^0-9]/', '', (string) $atts['reg']);
        $reg_period = $reg_period !== '' ? $reg_period : '1';

        $price = WHMCS_Price_API::get_domain_price( $tld, $type, $reg_period );

        $domain_id = 'whmcs-price-' . esc_attr( sanitize_title( $tld ) );
        return "<div id='{$domain_id}' class='whmcs-price'>" . esc_html($price) . '</div>';
    }

    // Fallback: no TLD => list all TLD prices.
    // Note: get_all_domain_prices() returns raw HTML from WHMCS (domainpricing.php).
    // wp_kses_post() is used intentionally here to allow table/list markup from a
    // trusted admin-configured source, while still stripping scripts and unsafe attributes.
    return wp_kses_post( WHMCS_Price_API::get_all_domain_prices() );
}
/**
 * Register the [whmcs] shortcode on WordPress initialization.
 * * @since 2.2.0
 */
add_action('init', function() {
    add_shortcode('whmcs', 'whmcs_func');
});