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

defined( 'ABSPATH' ) || exit;

/**
 * The main shortcode handler function.
 *
 * @since 2.2.0
 * @param array $atts {
 *   Shortcode attributes provided by the user.
 *
 *   @type string $pid  Comma-separated list of WHMCS Product IDs.
 *   @type string $bc   Billing cycle code (1m, 3m, 6m, 1y, 2y, 3y).
 *   @type string $show Comma-separated list of attributes to show (name, description, price)
 *                      or transaction types for domains (register, renew, transfer).
 *   @type string $tld  The domain extension (for domain pricing).
 *   @type string $type The domain transaction type (register, renew, transfer). Legacy — use show instead.
 *   @type string $reg  The registration period in years (e.g. '1', '2').
 *   @type string $per  Optional. Per-period breakdown: month | week | day.
 *                      E.g. per="month" on bc="1y" shows "$99.00/yr ($8.25/mo)".
 * }
 * @return string HTML output containing the requested data or an empty string on failure.
 */
function whmcs_price_shortcode_handler( $atts ) {
    // Skip WHMCS API calls during Gutenberg saves and autosaves.
    // Shortcodes run via the_content filter which fires on every REST save request,
    // causing live HTTP calls to WHMCS on each keypress/save in the block editor.
    // Frontend page loads are unaffected — REST_REQUEST is never set there.
    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return '<!-- whmcs-price shortcode -->';
    }

    /**
     * Set default attributes and merge with user-provided ones.
     */
    $atts = shortcode_atts([
        'pid'  => '',
        'bc'   => '',
        'show' => 'name,description,price',
        'tld'  => '',
        'type' => '',
        'reg'  => '',
        'per'  => '',   // Optional: month | week | day
    ], $atts, 'whmcs');

    /**
     * 1. PRODUCT PRICING LOGIC
     * * If 'pid' and 'bc' are provided, the shortcode generates an HTML table
     * with product information fetched from WHMCS.
     */
    if (!empty($atts['pid']) && !empty($atts['bc'])) {
        // Map short cycle codes to WHMCS internal billing cycle names
        $billing_cycles = array(
            '1m' => 'monthly', '3m' => 'quarterly', '6m' => 'semiannually',
            '1y' => 'annually', '2y' => 'biennially', '3y' => 'triennially',
        );

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
        $header_labels = array(
            'name'        => __('Name', 'whmcs-price'),
            'description' => __('Description', 'whmcs-price'),
            'price'       => __('Price', 'whmcs-price'),
        );

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

                // If per-period is requested and this column is 'price', append divided price.
                if ( 'price' === $attr && ! empty( $atts['per'] ) ) {
                    $val = whmcs_price_format_per( $val, $bc_r, (int) preg_replace( '/[^0-9]/', '', $atts['reg'] ) ?: 1, $atts['per'] );
                }

                // Only the price field may contain HTML (e.g. <span> for currency styling).
                // Name and description are always plain text — strip tags and escape.
                if ( 'price' === $attr ) {
                    $output .= '<td>' . wp_kses( $val, array( 'span' => array( 'class' => true ) ) ) . '</td>';
                } else {
                    $output .= '<td>' . esc_html( wp_strip_all_tags( $val ) ) . '</td>';
                }
            }
            $output .= "</tr>";
        }
        $output .= "</tbody></table>";
        return $output;
    }

    /**
     * 2) DOMAIN PRICING
     *
     * Parameters:
     *   tld   - Domain extension, e.g. "se" or "com". Required for single-TLD pricing.
     *   show  - Comma-separated transaction types to display: register, renew, transfer.
     *           Defaults to "register". Multiple values render a comparison table.
     *   reg   - Registration period in years, e.g. "1" or "2". Defaults to "1".
     *   type  - Legacy single-type parameter. Used as fallback if show is not provided.
     *
     * Examples:
     *   [whmcs tld="se" show="register,renew"]
     *   [whmcs tld="com" show="register,transfer,renew" reg="2"]
     *   [whmcs tld="se"]  (defaults to register, 1 year)
     *
     * If tld is provided => show one or more prices.
     * If no tld => show full TLD list from domainpricing.php.
     */
    if ( ! empty( $atts['tld'] ) ) {
        // Sanitize: strip dot prefix, lowercase, allow only valid TLD characters, max 24 chars.
        $tld = strtolower( sanitize_text_field( ltrim( $atts['tld'], '.' ) ) );
        $tld = preg_replace( '/[^a-z0-9\-]/', '', $tld );
        $tld = substr( $tld, 0, 24 );
        if ( empty( $tld ) ) {
            return '';
        }

        // Sanitize reg period: numeric only, 1–10.
        $reg_period = (string) preg_replace( '/[^0-9]/', '', (string) $atts['reg'] );
        $reg_period = ( $reg_period !== '' && (int) $reg_period >= 1 && (int) $reg_period <= 10 ) ? $reg_period : '1';

        // Build list of transaction types to display.
        // `show` takes priority; fall back to legacy `type` param; default to register.
        $allowed_types = array( 'register', 'renew', 'transfer' );

        $show_raw   = ! empty( $atts['show'] ) ? $atts['show'] : $atts['type'];
        $show_types = array_values( array_filter(
            array_map( 'trim', explode( ',', $show_raw ) ),
            fn( $t ) => in_array( $t, $allowed_types, true )
        ) );

        if ( empty( $show_types ) ) {
            $show_types = array( 'register' );
        }

        // Translatable labels.
        $type_labels = array(
            'register' => __( 'Registration', 'whmcs-price' ),
            'renew'    => __( 'Renewal', 'whmcs-price' ),
            'transfer' => __( 'Transfer', 'whmcs-price' ),
        );

        // Single type: return a simple inline value (backwards compatible).
        if ( count( $show_types ) === 1 ) {
            $type      = $show_types[0];
            $price     = WHMCS_Price_API::get_domain_price( $tld, $type, $reg_period );

            if ( ! empty( $atts['per'] ) ) {
                $price = whmcs_price_format_per( $price, 'annually', (int) $reg_period, $atts['per'] );
            }

            $domain_id = 'whmcs-price-' . esc_attr( sanitize_title( $tld ) );
            return "<div id='{$domain_id}' class='whmcs-price'>" . wp_kses( $price, array( 'span' => array( 'class' => array() ) ) ) . '</div>';
        }

        // Multiple types: render a comparison table.
        $period_label = (int) $reg_period === 1
            ? __( '1 year', 'whmcs-price' )
            /* translators: %d: number of years */
            : sprintf( __( '%d years', 'whmcs-price' ), (int) $reg_period );

        $table_id = 'whmcs-domain-' . esc_attr( $tld ) . '-' . esc_attr( $reg_period );

        $output  = "<table id='" . esc_attr( $table_id ) . "' class='whmcs-domain-table'>";
        $output .= '<thead><tr>';
        $output .= '<th>' . esc_html__( 'TLD', 'whmcs-price' ) . '</th>';
        foreach ( $show_types as $type ) {
            $output .= '<th>' . esc_html( $type_labels[ $type ] ) . '</th>';
        }
        $output .= '</tr></thead><tbody><tr>';
        $output .= '<td><strong>.' . esc_html( $tld ) . '</strong><br><small>' . esc_html( $period_label ) . '</small></td>';
        foreach ( $show_types as $type ) {
            $price = WHMCS_Price_API::get_domain_price( $tld, $type, $reg_period );
            if ( ! empty( $atts['per'] ) ) {
                $price = whmcs_price_format_per( $price, 'annually', (int) $reg_period, $atts['per'] );
            }
            $output .= '<td>' . wp_kses( $price, array( 'span' => array( 'class' => array() ) ) ) . '</td>';
        }
        $output .= '</tr></tbody></table>';
        return $output;
    }

    // Fallback: no TLD => list all TLD prices.
    // A strict allowlist is used instead of wp_kses_post() to limit exposure
    // if the remote WHMCS endpoint is ever compromised or misconfigured.
    $allowed_html = array(
        'table'  => array( 'class' => true, 'id' => true ),
        'thead'  => array(),
        'tbody'  => array(),
        'tfoot'  => array(),
        'tr'     => array( 'class' => true ),
        'th'     => array( 'scope' => true, 'class' => true ),
        'td'     => array( 'class' => true ),
        'strong' => array(),
        'small'  => array(),
        'span'   => array( 'class' => true ),
        'p'      => array( 'class' => true ),
        'ul'     => array( 'class' => true ),
        'li'     => array( 'class' => true ),
    );
    return wp_kses( WHMCS_Price_API::get_all_domain_prices(), $allowed_html );
}
/**
 * Register the [whmcs] shortcode on WordPress initialization.
 * * @since 2.2.0
 */
add_action('init', function() {
    add_shortcode('whmcs', 'whmcs_price_shortcode_handler');
});