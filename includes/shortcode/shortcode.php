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
    // Enqueue CSS lazily — only on pages where this shortcode actually runs.
    whmcs_price_shortcode_maybe_enqueue();

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
        'pid'   => '',
        'bc'    => '',
        'show'  => 'name,description,price',
        'tld'   => '',
        'type'  => '',
        'reg'   => '',
        'per'   => '',     // Optional: month | week | day
        'style' => 'table', // Product display style: table | cards | grid
    ], $atts, 'whmcs');

    // Allowlist: validate 'per' at the entry point so all downstream code receives a clean value.
    $atts['per'] = in_array( $atts['per'], array( 'month', 'week', 'day' ), true ) ? $atts['per'] : '';

    // Allowlist: validate 'style'.
    $atts['style'] = in_array( $atts['style'], array( 'table', 'cards', 'grid' ), true ) ? $atts['style'] : 'table';

    /**
     * 1. PRODUCT PRICING LOGIC
     * * If 'pid' and 'bc' are provided, the shortcode generates an HTML table
     * with product information fetched from WHMCS.
     */
    if ( ! empty( $atts['pid'] ) && ! empty( $atts['bc'] ) ) {
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
        $allowed_attrs = array( 'name', 'description', 'price', 'setupfee' );
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
            'setupfee'    => __('Setup Fee', 'whmcs-price'),
        );

        // Create a unique ID for the table based on PIDs to satisfy browser requirements
        $table_id = 'whmcs-table-' . md5($atts['pid'] . $atts['bc']);

        $display_style = $atts['style'];
        $wrapper_class = 'whmcs-product-display whmcs-product-display--' . esc_attr( $display_style );

        /**
         * Helper: fetch and format a single product attribute value.
         *
         * @param int    $pid  Product ID.
         * @param string $attr Column key (name, description, price, setupfee).
         * @return string
         */
        $get_val = function( int $pid, string $attr ) use ( $bc_r, $atts ): string {
            if ( 'setupfee' === $attr ) {
                return WHMCS_Price_API::get_product_setup_fee( $pid, $bc_r );
            }
            $val = WHMCS_Price_API::get_product_data( $pid, $bc_r, $attr );
            if ( 'NA' === $val ) { return 'NA'; }
            if ( 'price' === $attr ) {
                $val = whmcs_price_strip_setup_fee( $val );
                if ( ! empty( $atts['per'] ) ) {
                    $val = whmcs_price_format_per( $val, $bc_r, (int) preg_replace( '/[^0-9]/', '', $atts['reg'] ) ?: 1, $atts['per'] );
                }
            }
            return $val;
        };

        /**
         * Helper: render a single field value safely.
         *
         * @param string $attr  Column key.
         * @param string $val   Value string.
         * @return string       Safe HTML.
         */
        $render_val = function( string $attr, string $val ): string {
            if ( 'NA' === $val ) { return whmcs_price_unavailable_html(); }
            if ( in_array( $attr, array( 'price', 'setupfee' ), true ) ) {
                return wp_kses( $val, array( 'span' => array( 'class' => true ) ) );
            }
            return esc_html( wp_strip_all_tags( $val ) );
        };

        $output = '<div class="' . esc_attr( $wrapper_class ) . '">';

        if ( 'cards' === $display_style ) {
            $output .= '<div class="whmcs-product-cards">';
            foreach ( $pids as $pid ) {
                $output .= '<div class="whmcs-product-card">';
                foreach ( $show as $attr ) {
                    $val        = $get_val( $pid, $attr );
                    $attr_clean = strtolower( trim( $attr ) );
                    $output    .= '<div class="whmcs-product-card__' . esc_attr( $attr_clean ) . '">';
                    if ( 'name' === $attr_clean && 'NA' !== $val ) {
                        $output .= '<h3 class="whmcs-product-card__title">' . esc_html( $val ) . '</h3>';
                    } elseif ( 'setupfee' === $attr_clean ) {
                        $output .= '<span class="whmcs-product-card__setupfee-label">' . esc_html__( 'Setup Fee', 'whmcs-price' ) . ':</span>';
                        $output .= '<span class="whmcs-product-card__setupfee-value">' . esc_html( $val ) . '</span>';
                    } else {
                        $output .= $render_val( $attr_clean, $val );
                    }
                    $output .= '</div>';
                }
                $output .= '</div>';
            }
            $output .= '</div>';

        } elseif ( 'grid' === $display_style ) {
            $output .= '<div class="whmcs-product-grid">';
            foreach ( $pids as $pid ) {
                $output .= '<div class="whmcs-product-grid-item">';
                foreach ( $show as $attr ) {
                    $val        = $get_val( $pid, $attr );
                    $attr_clean = strtolower( trim( $attr ) );
                    $label      = $header_labels[ $attr_clean ] ?? ucfirst( $attr );
                    $output    .= '<div class="whmcs-product-grid-item__field">';
                    $output    .= '<span class="whmcs-product-grid-item__label">' . esc_html( $label ) . '</span>';
                    $output    .= '<span class="whmcs-product-grid-item__value">' . $render_val( $attr_clean, $val ) . '</span>';
                    $output    .= '</div>';
                }
                $output .= '</div>';
            }
            $output .= '</div>';

        } else {
            // Table (default)
            $output .= "<table id='" . esc_attr( $table_id ) . "' class='whmcs-product-table'><thead><tr>";
            foreach ( $show as $header ) {
                $label   = $header_labels[ strtolower( trim( $header ) ) ] ?? ucfirst( $header );
                $output .= '<th>' . esc_html( $label ) . '</th>';
            }
            $output .= '</tr></thead><tbody>';
            foreach ( $pids as $pid ) {
                $output .= '<tr>';
                foreach ( $show as $attr ) {
                    $val        = $get_val( $pid, $attr );
                    $attr_clean = strtolower( trim( $attr ) );
                    $output    .= '<td>' . $render_val( $attr_clean, $val ) . '</td>';
                }
                $output .= '</tr>';
            }
            $output .= '</tbody></table>';
        }

        $output .= '</div>';
        $output .= whmcs_price_promo_notice( 'product' );
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

            if ( 'NA' === $price ) {
                return "<div class='whmcs-price'>" . whmcs_price_unavailable_html() . '</div>';
            }

            if ( ! empty( $atts['per'] ) ) {
                $price = whmcs_price_format_per( $price, 'annually', (int) $reg_period, $atts['per'] );
            }

            $domain_id = 'whmcs-price-' . esc_attr( sanitize_title( $tld ) );
            return "<div id='{$domain_id}' class='whmcs-price'>" . wp_kses( $price, array( 'span' => array( 'class' => array() ) ) ) . '</div>' . whmcs_price_promo_notice( 'domain' );
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
            if ( 'NA' === $price ) {
                $output .= '<td>' . whmcs_price_unavailable_html() . '</td>';
            } else {
                if ( ! empty( $atts['per'] ) ) {
                    $price = whmcs_price_format_per( $price, 'annually', (int) $reg_period, $atts['per'] );
                }
                $output .= '<td>' . wp_kses( $price, array( 'span' => array( 'class' => array() ) ) ) . '</td>';
            }
        }
        $output .= '</tr></tbody></table>';
        $output .= whmcs_price_promo_notice( 'domain' );
        return $output;
    }

    // Fallback: no TLD => list all TLD prices.
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
    $all_prices = WHMCS_Price_API::get_all_domain_prices();
    if ( 'NA' === $all_prices ) {
        return whmcs_price_unavailable_html();
    }
    return '<div class="whmcs-domain-all">' . wp_kses( $all_prices, $allowed_html ) . '</div>';
}
/**
 * Register the [whmcs] shortcode on WordPress initialization.
 * * @since 2.2.0
 */
add_action( 'init', function() {
    add_shortcode( 'whmcs', 'whmcs_price_shortcode_handler' );
    add_shortcode( 'whmcs_cycles', 'whmcs_price_cycles_shortcode_handler' );
} );

/**
 * [whmcs_cycles] shortcode handler.
 *
 * Renders a table of all available billing cycles and prices for a product,
 * using the productpricing.php feed. Useful when you want to show all pricing
 * options without hardcoding each billing cycle.
 *
 * Usage:
 *   [whmcs_cycles pid="1"]
 *   [whmcs_cycles pid="1" style="table"]   (default)
 *   [whmcs_cycles pid="1" style="cards"]
 *
 * @since 2.9.0
 * @param  array $atts Shortcode attributes.
 * @return string      HTML output.
 */
function whmcs_price_cycles_shortcode_handler( $atts ): string {
    whmcs_price_shortcode_maybe_enqueue();

    if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) ) {
        return '<!-- whmcs-price-cycles -->';
    }

    $atts = shortcode_atts( array(
        'pid'   => '',
        'style' => 'table',
    ), $atts, 'whmcs_cycles' );

    $pid = absint( $atts['pid'] );
    if ( $pid <= 0 ) {
        return '';
    }

    $style = in_array( $atts['style'], array( 'table', 'cards', 'grid' ), true ) ? $atts['style'] : 'table';

    $cycles = WHMCS_Price_API::get_all_product_cycles( $pid );
    if ( empty( $cycles ) ) {
        return '<div class="whmcs-price">' . whmcs_price_unavailable_html() . '</div>';
    }

    // Translatable cycle labels.
    $cycle_labels = array(
        'monthly'     => __( 'Monthly', 'whmcs-price' ),
        'quarterly'   => __( 'Quarterly', 'whmcs-price' ),
        'semiannually' => __( 'Semi-annually', 'whmcs-price' ),
        'annually'    => __( 'Annually', 'whmcs-price' ),
        'biennially'  => __( 'Biennially', 'whmcs-price' ),
        'triennially' => __( 'Triennially', 'whmcs-price' ),
    );

    $wrapper_class = 'whmcs-product-display whmcs-product-display--' . esc_attr( $style );
    $output        = '<div class="' . esc_attr( $wrapper_class ) . '">';

    if ( 'cards' === $style ) {
        $output .= '<div class="whmcs-product-cards">';
        foreach ( $cycles as $cycle => $price ) {
            $label   = $cycle_labels[ $cycle ] ?? ucfirst( $cycle );
            $output .= '<div class="whmcs-product-card">';
            $output .= '<div class="whmcs-product-card__name"><h3 class="whmcs-product-card__title">' . esc_html( $label ) . '</h3></div>';
            $output .= '<div class="whmcs-product-card__price"><span class="whmcs-product-card__price-value">' . esc_html( $price ) . '</span></div>';
            $output .= '</div>';
        }
        $output .= '</div>';

    } elseif ( 'grid' === $style ) {
        $output .= '<div class="whmcs-product-grid">';
        foreach ( $cycles as $cycle => $price ) {
            $label   = $cycle_labels[ $cycle ] ?? ucfirst( $cycle );
            $output .= '<div class="whmcs-product-grid-item">';
            $output .= '<div class="whmcs-product-grid-item__field">';
            $output .= '<span class="whmcs-product-grid-item__label">' . esc_html( $label ) . '</span>';
            $output .= '<span class="whmcs-product-grid-item__value">' . esc_html( $price ) . '</span>';
            $output .= '</div></div>';
        }
        $output .= '</div>';

    } else {
        // Table (default).
        $output .= '<table class="whmcs-product-table">';
        $output .= '<thead><tr>';
        $output .= '<th>' . esc_html__( 'Billing Cycle', 'whmcs-price' ) . '</th>';
        $output .= '<th>' . esc_html__( 'Price', 'whmcs-price' ) . '</th>';
        $output .= '</tr></thead><tbody>';
        foreach ( $cycles as $cycle => $price ) {
            $label   = $cycle_labels[ $cycle ] ?? ucfirst( $cycle );
            $output .= '<tr>';
            $output .= '<td>' . esc_html( $label ) . '</td>';
            $output .= '<td>' . esc_html( $price ) . '</td>';
            $output .= '</tr>';
        }
        $output .= '</tbody></table>';
    }

    $output .= '</div>';
    $output .= whmcs_price_promo_notice( 'product' );
    return $output;
}

/**
 * Register and lazily enqueue frontend CSS for the [whmcs] shortcode.
 *
 * Styles are registered on wp_enqueue_scripts but only enqueued when the
 * shortcode actually renders on a given page. This avoids loading CSS on
 * pages where the shortcode is not present.
 *
 * A static flag is set inside the shortcode handler the first time it runs,
 * and a wp_footer hook triggers the enqueue after the page has been parsed.
 * Because wp_footer fires after the <head> has been sent, late_enqueue uses
 * wp_print_styles() to output the <link> tags inline at the footer if needed.
 *
 * @since 2.7.3
 */
add_action( 'wp_enqueue_scripts', function() {
    if ( ! defined( 'WHMCS_PRICE_DIR' ) || ! defined( 'WHMCS_PRICE_URL' ) ) {
        return;
    }
    $ver = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : null;

    if ( file_exists( WHMCS_PRICE_DIR . 'blocks/build/whmcs-price-product.css' ) ) {
        wp_register_style(
            'whmcs-price-product',
            WHMCS_PRICE_URL . 'blocks/build/whmcs-price-product.css',
            array(),
            $ver
        );
    }
    if ( file_exists( WHMCS_PRICE_DIR . 'blocks/build/whmcs-price-domain.css' ) ) {
        wp_register_style(
            'whmcs-price-domain',
            WHMCS_PRICE_URL . 'blocks/build/whmcs-price-domain.css',
            array(),
            $ver
        );
    }
} );

/**
 * Enqueue the shortcode CSS in wp_footer if the shortcode ran on this page.
 * Called by whmcs_price_shortcode_maybe_enqueue() from inside the shortcode handler.
 *
 * @since 2.7.3
 */
function whmcs_price_shortcode_maybe_enqueue(): void {
    static $hooked = false;
    if ( $hooked ) {
        return;
    }
    $hooked = true;
    add_action( 'wp_footer', function() {
        wp_enqueue_style( 'whmcs-price-product' );
        wp_enqueue_style( 'whmcs-price-domain' );
        wp_print_styles( array( 'whmcs-price-product', 'whmcs-price-domain' ) );
    }, 1 );
}