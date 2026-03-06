<?php
/**
 * WHMCS Price Helpers
 *
 * Shared rendering and formatting utilities used by all output modules:
 * shortcodes, Gutenberg blocks, and Elementor widgets.
 *
 * Adding a new output module? Reuse these helpers instead of duplicating logic.
 * Adding a new formatting feature? Add it here so all modules benefit automatically.
 *
 * @package    WHMCS_Price
 * @subpackage Helpers
 * @since      2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Format a price string with an optional per-period breakdown.
 *
 * Takes the raw WHMCS price string, computes the per-period value and
 * returns both combined, e.g: "$99.00/yr ($8.25/mo)"
 *
 * Works with any WHMCS price format:
 *   "$9.99"  "€12.50"  "99.00 kr"  "9,99 kr"  "kr 99.00"
 *
 * Usage examples:
 *   whmcs_price_format_per( '$99.00', 'annually', 1, 'month' )
 *   → "$99.00/yr ($8.25/mo)"
 *
 *   whmcs_price_format_per( '199.00 kr', 'annually', 2, 'month' )
 *   → "199.00 kr/2yr (8.29 kr/mo)"
 *
 * @since  2.6.0
 * @param  string $price       Raw price string from WHMCS (e.g. "$99.00").
 * @param  string $bc_internal Internal WHMCS billing cycle (e.g. "annually").
 * @param  int    $reg_years   Registration period in years (for domains; use 1 for products).
 * @param  string $per         Desired per-period unit: month | week | day.
 * @return string              Combined price string, or original price if per is empty/invalid.
 */
function whmcs_price_format_per( string $price, string $bc_internal, int $reg_years, string $per ): string {
	$allowed_per = array( 'month', 'week', 'day' );
	$per         = strtolower( trim( $per ) );

	if ( ! in_array( $per, $allowed_per, true ) || empty( trim( $price ) ) ) {
		return esc_html( $price );
	}

	// Total months in this billing cycle × registration period in years.
	$total_months = WHMCS_Price_API::billing_cycle_months( $bc_internal ) * max( 1, $reg_years );

	$divisor = match ( $per ) {
		'month' => (float) $total_months,
		'week'  => $total_months * 4.33,
		'day'   => $total_months * 30.44,
		default => 1.0,
	};

	// Suffix for the original (full-period) price.
	$cycle_suffix = array(
		'monthly'      => __( '/mo', 'whmcs-price' ),
		'quarterly'    => __( '/qtr', 'whmcs-price' ),
		'semiannually' => __( '/6mo', 'whmcs-price' ),
		'annually'     => __( '/yr', 'whmcs-price' ),
		'biennially'   => __( '/2yr', 'whmcs-price' ),
		'triennially'  => __( '/3yr', 'whmcs-price' ),
	);

	// Multi-year domain registration gets its own suffix.
	if ( $reg_years > 1 ) {
		/* translators: %d: number of years */
		$orig_suffix = sprintf( __( '/%dyr', 'whmcs-price' ), $reg_years );
	} else {
		$orig_suffix = $cycle_suffix[ $bc_internal ] ?? '';
	}

	$per_suffix = match ( $per ) {
		'month' => __( '/mo', 'whmcs-price' ),
		'week'  => __( '/wk', 'whmcs-price' ),
		'day'   => __( '/day', 'whmcs-price' ),
		default => '',
	};

	$divided = WHMCS_Price_API::divide_price( $price, $divisor );

	// Return HTML with two separate elements so each can be styled independently.
	return '<span class="whmcs-price-full">' . esc_html( $price . $orig_suffix ) . '</span>'
		. '<span class="whmcs-price-per">' . esc_html( $divided . $per_suffix ) . '</span>';
}
