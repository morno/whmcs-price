<?php
/**
 * Billing cycle toggle
 *
 * Lets visitors switch the billing cycle (e.g. monthly / annually) of the
 * Cards and Pricing Grid styles without a page reload.
 *
 * How it works:
 *   - The server renders the prices for every offered cycle, each in a
 *     <span data-whmcs-cycle="1m">. All of them come from the normal price
 *     cache, so switching never calls WHMCS or the REST API.
 *   - The wrapper carries data-whmcs-selected="1y". CSS hides the spans of
 *     every other cycle, so without JavaScript visitors see the default cycle.
 *   - A small Interactivity API module (assets/js/cycle-toggle.js) reveals the
 *     radio group and updates data-whmcs-selected when a visitor picks a cycle.
 *
 * @package    WHMCS_Price
 * @subpackage Frontend
 * @since      2.10.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Interactivity API store namespace and script module ID.
 */
const WHMCS_PRICE_TOGGLE_MODULE = 'whmcs-price-cycle-toggle';

/**
 * Short billing cycle codes mapped to WHMCS internal names, in display order.
 *
 * @since  2.10.1
 * @return array<string,string>
 */
function whmcs_price_cycle_codes(): array {
	return array(
		'1m' => 'monthly',
		'3m' => 'quarterly',
		'6m' => 'semiannually',
		'1y' => 'annually',
		'2y' => 'biennially',
		'3y' => 'triennially',
	);
}

/**
 * Visible label for a cycle in the toggle.
 *
 * @since  2.10.1
 * @param  string $code Short cycle code (1m, 3m, …).
 * @return string
 */
function whmcs_price_cycle_label( string $code ): string {
	$labels = array(
		'1m' => __( 'Monthly', 'whmcs-price' ),
		'3m' => __( 'Quarterly', 'whmcs-price' ),
		'6m' => __( 'Semi-annually', 'whmcs-price' ),
		'1y' => __( 'Annually', 'whmcs-price' ),
		'2y' => __( 'Every 2 years', 'whmcs-price' ),
		'3y' => __( 'Every 3 years', 'whmcs-price' ),
	);

	return $labels[ $code ] ?? $code;
}

/**
 * Sentence announced to screen readers after a visitor switches cycle.
 *
 * @since  2.10.1
 * @param  string $code Short cycle code.
 * @return string
 */
function whmcs_price_cycle_announcement( string $code ): string {
	$sentences = array(
		'1m' => __( 'Showing monthly prices.', 'whmcs-price' ),
		'3m' => __( 'Showing quarterly prices.', 'whmcs-price' ),
		'6m' => __( 'Showing semi-annual prices.', 'whmcs-price' ),
		'1y' => __( 'Showing annual prices.', 'whmcs-price' ),
		'2y' => __( 'Showing prices for 2 years.', 'whmcs-price' ),
		'3y' => __( 'Showing prices for 3 years.', 'whmcs-price' ),
	);

	return $sentences[ $code ] ?? '';
}

/**
 * Normalise the list of cycles offered in the toggle.
 *
 * Accepts a comma-separated string ("1m,1y") or an array. Unknown codes and
 * duplicates are dropped and the result is in the canonical order (shortest
 * cycle first), so the toggle reads naturally whatever order was entered.
 *
 * @since  2.10.1
 * @param  string|array $cycles Cycles from a block attribute or shortcode.
 * @return string[] Valid codes. Fewer than two means "no toggle".
 */
function whmcs_price_toggle_cycles( $cycles ): array {
	if ( is_string( $cycles ) ) {
		$cycles = explode( ',', $cycles );
	}
	if ( ! is_array( $cycles ) ) {
		return array();
	}

	$cycles = array_map( static fn( $c ) => strtolower( trim( (string) $c ) ), $cycles );

	return array_values( array_intersect( array_keys( whmcs_price_cycle_codes() ), $cycles ) );
}

/**
 * Pick the cycle shown first: the configured billing cycle when it is offered
 * in the toggle, otherwise the first offered cycle.
 *
 * @since  2.10.1
 * @param  string[] $cycles    Normalised toggle cycles.
 * @param  string   $preferred Configured billing cycle code.
 * @return string
 */
function whmcs_price_toggle_default( array $cycles, string $preferred ): string {
	return in_array( $preferred, $cycles, true ) ? $preferred : (string) ( $cycles[0] ?? $preferred );
}

/**
 * Register the view script module.
 *
 * Script modules need WordPress 6.5. On older versions the toggle is simply
 * not offered and the default cycle is rendered as before.
 *
 * @since 2.10.1
 */
function whmcs_price_register_toggle_module(): void {
	if ( ! function_exists( 'wp_register_script_module' ) ) {
		return;
	}

	wp_register_script_module(
		WHMCS_PRICE_TOGGLE_MODULE,
		WHMCS_PRICE_URL . 'assets/js/cycle-toggle.js',
		array( '@wordpress/interactivity' ),
		WHMCS_PRICE_VERSION
	);
}
add_action( 'init', 'whmcs_price_register_toggle_module' );

/**
 * Whether the toggle can be used on this site.
 *
 * @since  2.10.1
 * @return bool
 */
function whmcs_price_toggle_supported(): bool {
	return function_exists( 'wp_enqueue_script_module' );
}

/**
 * Render the cycle-dependent value once per offered cycle.
 *
 * @since  2.10.1
 * @param  callable $render Receives the WHMCS internal cycle name and returns safe HTML.
 * @param  string[] $cycles Normalised toggle cycles (short codes).
 * @return string Safe HTML: one span per cycle.
 */
function whmcs_price_cycle_values( callable $render, array $cycles ): string {
	$codes = whmcs_price_cycle_codes();
	$html  = '';

	foreach ( $cycles as $code ) {
		$html .= '<span class="whmcs-price-cycle" data-whmcs-cycle="' . esc_attr( $code ) . '">'
			. $render( $codes[ $code ] )
			. '</span>';
	}

	return $html;
}

/**
 * Opening markup: interactive wrapper, radio group and live region.
 *
 * Must be paired with whmcs_price_toggle_close().
 *
 * @since  2.10.1
 * @param  string[] $cycles   Normalised toggle cycles.
 * @param  string   $selected Cycle shown first.
 * @return string Safe HTML.
 */
function whmcs_price_toggle_open( array $cycles, string $selected ): string {
	wp_enqueue_script_module( WHMCS_PRICE_TOGGLE_MODULE );

	$name    = wp_unique_id( 'whmcs-cycle-' );
	$context = wp_json_encode(
		array(
			'selected' => $selected,
			'announce' => '',
		)
	);

	$html = '<div class="whmcs-cycle-switch"'
		. ' data-wp-interactive="whmcs-price/cycle-toggle"'
		. " data-wp-context='" . esc_attr( $context ) . "'"
		. ' data-wp-bind--data-whmcs-selected="context.selected"'
		. ' data-whmcs-selected="' . esc_attr( $selected ) . '">';

	// Hidden until the script runs: without JavaScript the radios would do nothing.
	$html .= '<fieldset class="whmcs-cycle-toggle" hidden data-wp-init---reveal="callbacks.reveal">';
	$html .= '<legend class="screen-reader-text whmcs-price-sr">' . esc_html__( 'Billing period', 'whmcs-price' ) . '</legend>';

	foreach ( $cycles as $code ) {
		$html .= '<label class="whmcs-cycle-toggle__option">'
			. '<input type="radio" class="whmcs-cycle-toggle__input"'
			. ' name="' . esc_attr( $name ) . '"'
			. ' value="' . esc_attr( $code ) . '"'
			. ' data-announce="' . esc_attr( whmcs_price_cycle_announcement( $code ) ) . '"'
			. ' data-wp-on--change="actions.select"'
			. checked( $code, $selected, false ) . '>'
			. '<span class="whmcs-cycle-toggle__label">' . esc_html( whmcs_price_cycle_label( $code ) ) . '</span>'
			. '</label>';
	}

	$html .= '</fieldset>';
	$html .= '<p class="screen-reader-text whmcs-price-sr" aria-live="polite" data-wp-text="context.announce"></p>';

	return $html;
}

/**
 * Closing markup for whmcs_price_toggle_open().
 *
 * @since  2.10.1
 * @return string
 */
function whmcs_price_toggle_close(): string {
	return '</div>';
}
