<?php
/**
 * REST API Endpoints
 *
 * Provides read-only JSON endpoints for WHMCS pricing data.
 * Useful for headless WordPress setups, JavaScript price loaders,
 * and any client that cannot execute PHP shortcodes or blocks.
 *
 * Endpoints (all public, no authentication required):
 *
 *   GET /wp-json/whmcs-price/v1/product/{pid}
 *       Query params: billing_cycle (default: monthly), attribute (default: price)
 *       Returns: { "price": "9.99 kr" }
 *
 *   GET /wp-json/whmcs-price/v1/domain/{tld}
 *       Query params: type (default: register), reg_period (default: 1)
 *       Returns: { "price": "239 kr" }
 *
 * All responses are served from the same transient cache used by
 * shortcodes and blocks — no extra WHMCS requests are made.
 *
 * @package    WHMCS_Price
 * @subpackage REST_API
 * @since      2.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register REST API routes.
 *
 * @since 2.8.0
 * @return void
 */
add_action( 'rest_api_init', function() {

	// Product price endpoint.
	register_rest_route(
		'whmcs-price/v1',
		'/product/(?P<pid>\d+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'whmcs_price_rest_product',
			'permission_callback' => '__return_true', // Public — returns only cached prices.
			'args'                => array(
				'pid'           => array(
					'required'          => true,
					'validate_callback' => function( $v ) { return is_numeric( $v ) && (int) $v > 0; },
					'sanitize_callback' => 'absint',
				),
				'billing_cycle' => array(
					'default'           => 'monthly',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $v ) {
						return in_array( $v, array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' ), true );
					},
				),
				'attribute'     => array(
					'default'           => 'price',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $v ) {
						return in_array( $v, array( 'name', 'description', 'price' ), true );
					},
				),
			),
		)
	);

	// Domain price endpoint.
	register_rest_route(
		'whmcs-price/v1',
		'/domain/(?P<tld>[a-zA-Z0-9\-]+)',
		array(
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => 'whmcs_price_rest_domain',
			'permission_callback' => '__return_true', // Public — returns only cached prices.
			'args'                => array(
				'tld'        => array(
					'required'          => true,
					'sanitize_callback' => function( $v ) {
						return preg_replace( '/[^a-zA-Z0-9\-]/', '', ltrim( $v, '.' ) );
					},
				),
				'type'       => array(
					'default'           => 'register',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $v ) {
						return in_array( $v, array( 'register', 'renew', 'transfer' ), true );
					},
				),
				'reg_period' => array(
					'default'           => 1,
					'sanitize_callback' => 'absint',
					'validate_callback' => function( $v ) {
						return is_numeric( $v ) && (int) $v >= 1 && (int) $v <= 10;
					},
				),
			),
		)
	);
} );

/**
 * Handle GET /whmcs-price/v1/product/{pid}
 *
 * @since  2.8.0
 * @param  WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function whmcs_price_rest_product( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$pid           = $request->get_param( 'pid' );
	$billing_cycle = $request->get_param( 'billing_cycle' );
	$attribute     = $request->get_param( 'attribute' );

	$value = WHMCS_Price_API::get_product_data( $pid, $billing_cycle, $attribute );

	if ( 'NA' === $value ) {
		return new WP_Error(
			'whmcs_price_unavailable',
			__( 'Pricing data is currently unavailable.', 'whmcs-price' ),
			array( 'status' => 503 )
		);
	}

	return new WP_REST_Response(
		array( $attribute => $value ),
		200
	);
}

/**
 * Handle GET /whmcs-price/v1/domain/{tld}
 *
 * @since  2.8.0
 * @param  WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function whmcs_price_rest_domain( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$tld        = $request->get_param( 'tld' );
	$type       = $request->get_param( 'type' );
	$reg_period = $request->get_param( 'reg_period' );

	$value = WHMCS_Price_API::get_domain_price( $tld, $type, $reg_period );

	if ( 'NA' === $value ) {
		return new WP_Error(
			'whmcs_price_unavailable',
			__( 'Pricing data is currently unavailable.', 'whmcs-price' ),
			array( 'status' => 503 )
		);
	}

	return new WP_REST_Response(
		array( 'price' => $value ),
		200
	);
}
