<?php
/**
 * REST API Endpoints
 *
 * Provides read-only JSON endpoints for WHMCS pricing data.
 * Useful for headless WordPress setups, JavaScript price loaders,
 * and any client that cannot execute PHP shortcodes or blocks.
 *
 * Endpoints:
 *
 *   GET  /wp-json/whmcs-price/v1/product/{pid}   (public)
 *       Query params: billing_cycle (default: monthly), attribute (default: price)
 *       Returns: { "price": "9.99 kr" }
 *
 *   GET  /wp-json/whmcs-price/v1/domain/{tld}    (public)
 *       Query params: type (default: register), reg_period (default: 1)
 *       Returns: { "price": "239 kr" }
 *
 *   POST /wp-json/whmcs-price/v1/purge-cache      (requires secret token)
 *       Header: X-WHMCS-Price-Token: <token>
 *       Body (optional): { "scope": "all" | "product" | "domain" }
 *       Returns: { "cleared": 12, "message": "Cache cleared." }
 *       Used by WHMCS hooks, n8n, Zapier, or any external system to
 *       trigger an immediate cache purge when prices change in WHMCS.
 *
 *   POST /wp-json/whmcs-price/v1/webhook/invalidate   (requires secret token, 2.10.0+)
 *       Header: X-WHMCS-Price-Token: <token>
 *       Body: { "scope": "product:5" | "domain:com" | "product:5,domain:se" | "all" }
 *       Returns: { "invalidated": true, "scope": "product:5", "entries": 7 }
 *       Targeted invalidation for WHMCS hooks (ProductEdit,
 *       TopLevelDomainPricingUpdate). See docs/whmcs-webhook.md.
 *
 * All GET responses are served from the same transient cache used by
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
				'currency'      => array(
					'description'       => 'WHMCS currency ID. Omit for the currency mapped to the request language (WPML/Polylang), else the WHMCS default.',
					'validate_callback' => function( $v ) { return is_numeric( $v ) && (int) $v >= 0; },
					'sanitize_callback' => 'absint',
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
				'currency'   => array(
					'description'       => 'WHMCS currency ID. Omit for the currency mapped to the request language (WPML/Polylang), else the WHMCS default.',
					'validate_callback' => function( $v ) { return is_numeric( $v ) && (int) $v >= 0; },
					'sanitize_callback' => 'absint',
				),
			),
		)
	);
	// Cache purge endpoint — for WHMCS hooks, n8n, Zapier, cron etc.
	register_rest_route(
		'whmcs-price/v1',
		'/purge-cache',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'whmcs_price_rest_purge_cache',
			'permission_callback' => 'whmcs_price_rest_purge_permission',
			'args'                => array(
				'scope' => array(
					'default'           => 'all',
					'sanitize_callback' => 'sanitize_key',
					'validate_callback' => function( $v ) {
						return in_array( $v, array( 'all', 'product', 'domain' ), true );
					},
				),
			),
		)
	);

	// Webhook for WHMCS hooks: targeted invalidation of one product or TLD.
	register_rest_route(
		'whmcs-price/v1',
		'/webhook/invalidate',
		array(
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => 'whmcs_price_rest_webhook_invalidate',
			'permission_callback' => 'whmcs_price_rest_purge_permission',
			'args'                => array(
				'scope' => array(
					'description'       => 'Comma-separated targets: "product:<pid>", "domain:<tld>" or "all". Default "all".',
					'type'              => 'string',
					'default'           => 'all',
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		)
	);
} );

/**
 * Permission callback for the cache purge endpoint.
 *
 * Validates the secret token supplied in the X-WHMCS-Price-Token header.
 * The token is configured under Settings → Advanced → Purge Token.
 * If no token is configured the endpoint returns 403 — it is opt-in.
 *
 * @since  2.9.0
 * @param  WP_REST_Request $request
 * @return true|WP_Error
 */
function whmcs_price_rest_purge_permission( WP_REST_Request $request ): bool|WP_Error {
	$options  = get_option( 'whmcs_price_option', array() );
	$token    = ! empty( $options['purge_token'] ) ? $options['purge_token'] : '';
	$security = whmcs_price_get_security_settings();

	if ( '' === $token ) {
		return new WP_Error(
			'whmcs_price_purge_disabled',
			__( 'Cache purge endpoint is disabled. Set a Purge Token under Settings → Advanced.', 'whmcs-price' ),
			array( 'status' => 403 )
		);
	}

	// Block brute-force token guessing before comparing secrets.
	$auth_limit  = (int) $security['purge_auth_limit'];
	$auth_window = (int) $security['purge_auth_window'];
	$auth_check  = whmcs_price_rate_limit_check( 'purge_auth', $auth_limit, $auth_window );
	if ( is_wp_error( $auth_check ) ) {
		return $auth_check;
	}

	$supplied = $request->get_header( 'X-WHMCS-Price-Token' );

	if ( ! hash_equals( $token, (string) $supplied ) ) {
		whmcs_price_rate_limit_record( 'purge_auth', $auth_window );
		return new WP_Error(
			'whmcs_price_purge_unauthorized',
			__( 'Invalid or missing X-WHMCS-Price-Token header.', 'whmcs-price' ),
			array( 'status' => 401 )
		);
	}

	return true;
}

/**
 * Shared cooldown for full cache purges (/purge-cache and /webhook/invalidate).
 *
 * @since  2.10.0 Extracted from whmcs_price_rest_purge_cache().
 * @return WP_REST_Response|null 429 response while cooling down, null when a purge may run.
 */
function whmcs_price_purge_throttle(): ?WP_REST_Response {
	$security     = whmcs_price_get_security_settings();
	$min_interval = (int) $security['purge_success_interval'];

	/**
	 * Filter the minimum interval (in seconds) between successful purges.
	 *
	 * Defaults to the value configured under Settings → Advanced → Rate limiting.
	 * Set to 0 to disable throttling entirely.
	 *
	 * @since 2.9.0
	 * @since 2.9.0 Default read from plugin settings.
	 * @param int $seconds Cooldown in seconds.
	 */
	$min_interval = (int) apply_filters( 'whmcs_price_purge_min_interval', $min_interval );

	if ( $min_interval > 0 ) {
		$last_purge = (int) get_option( 'whmcs_price_last_purge', 0 );
		$elapsed    = time() - $last_purge;

		if ( $last_purge > 0 && $elapsed < $min_interval ) {
			$retry_after = $min_interval - $elapsed;
			$response    = new WP_REST_Response(
				array(
					'purged'      => false,
					'retry_after' => $retry_after,
					'message'     => sprintf(
						/* translators: %d: seconds until next purge allowed */
						__( 'Purge throttled — try again in %d seconds.', 'whmcs-price' ),
						$retry_after
					),
				),
				429
			);
			$response->header( 'Retry-After', (string) $retry_after );
			return $response;
		}
	}

	return null;
}

/**
 * Handle POST /whmcs-price/v1/purge-cache
 *
 * Invalidates all WHMCS price caches by bumping the cache version. This
 * works identically on database transients and persistent object caches
 * (Redis, Memcached) because every previously-stored key becomes
 * unreachable in one atomic option update. Old entries expire naturally
 * via their existing TTL.
 *
 * The `scope` parameter is retained for API compatibility but is no longer
 * needed for correctness — version bump invalidates everything. It is
 * recorded in the response for caller telemetry.
 *
 * @since  2.9.0
 * @param  WP_REST_Request $request
 * @return WP_REST_Response
 */
function whmcs_price_rest_purge_cache( WP_REST_Request $request ): WP_REST_Response {
	$scope = $request->get_param( 'scope' ) ?? 'all';

	$throttled = whmcs_price_purge_throttle();
	if ( null !== $throttled ) {
		return $throttled;
	}

	update_option( 'whmcs_price_last_purge', time(), false );

	$version = WHMCS_Price_API::bump_cache_version();
	whmcs_price_flush_page_cache();

	return new WP_REST_Response(
		array(
			'purged'        => true,
			'scope'         => $scope,
			'cache_version' => $version,
			'message'       => __( 'Cache invalidated.', 'whmcs-price' ),
		),
		200
	);
}

/**
 * Handle POST /whmcs-price/v1/webhook/invalidate
 *
 * Called from WHMCS hooks (see docs/whmcs-webhook.md) when a product or
 * domain price changes. Authenticated with the Cache Purge Token, same as
 * /purge-cache.
 *
 * scope (comma-separated, max 50 targets):
 *   product:<pid>   Every cached value for that product (all cycles,
 *                   name, description, price, setup fee feed).
 *   domain:<tld>    Every cached price for that TLD, plus the all-domains table.
 *   all             Full purge (cache version bump), same cooldown as /purge-cache.
 *
 * Targeted invalidations are cheap and WHMCS can fire many in a row (bulk
 * edits), so they use a per-client rate limit instead of the full-purge
 * cooldown.
 *
 * @since  2.10.0
 * @param  WP_REST_Request $request
 * @return WP_REST_Response|WP_Error
 */
function whmcs_price_rest_webhook_invalidate( WP_REST_Request $request ): WP_REST_Response|WP_Error {
	$raw     = (string) ( $request->get_param( 'scope' ) ?? 'all' );
	$targets = array_values( array_filter( array_map( 'trim', explode( ',', '' === trim( $raw ) ? 'all' : $raw ) ), 'strlen' ) );

	if ( count( $targets ) > 50 ) {
		return new WP_Error( 'whmcs_price_invalid_scope', __( 'At most 50 targets per request.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$products = array();
	$domains  = array();
	$full     = false;
	foreach ( $targets as $target ) {
		if ( 'all' === strtolower( $target ) ) {
			$full = true;
			continue;
		}
		$parts = explode( ':', $target, 2 );
		$kind  = strtolower( trim( $parts[0] ) );
		$value = trim( $parts[1] ?? '' );

		if ( 'product' === $kind && ctype_digit( $value ) && (int) $value > 0 ) {
			$products[ (int) $value ] = true;
			continue;
		}
		if ( 'domain' === $kind && preg_match( '/^\.?[A-Za-z0-9\-]{1,24}$/', $value ) ) {
			$domains[ strtolower( ltrim( $value, '.' ) ) ] = true;
			continue;
		}

		return new WP_Error(
			'whmcs_price_invalid_scope',
			/* translators: %s: the rejected scope value */
			sprintf( __( 'Invalid scope "%s". Use "product:<pid>", "domain:<tld>" or "all".', 'whmcs-price' ), $target ),
			array( 'status' => 400 )
		);
	}

	if ( $full ) {
		$throttled = whmcs_price_purge_throttle();
		if ( null !== $throttled ) {
			return $throttled;
		}
		update_option( 'whmcs_price_last_purge', time(), false );
		$version = WHMCS_Price_API::bump_cache_version();
		whmcs_price_flush_page_cache();

		return new WP_REST_Response(
			array(
				'invalidated'   => true,
				'scope'         => 'all',
				'cache_version' => $version,
				'message'       => __( 'Cache invalidated.', 'whmcs-price' ),
			),
			200
		);
	}

	/**
	 * Filter the number of targeted webhook invalidations allowed per client
	 * per minute. 0 disables the limit.
	 *
	 * @since 2.10.0
	 * @param int $limit Default 120.
	 */
	$limit   = (int) apply_filters( 'whmcs_price_webhook_rate_limit', 120 );
	$limited = whmcs_price_rate_limit_enforce( 'webhook', $limit, MINUTE_IN_SECONDS );
	if ( is_wp_error( $limited ) ) {
		return whmcs_price_rate_limit_rest_response( $limited );
	}

	$removed = 0;
	foreach ( array_keys( $products ) as $pid ) {
		$removed += WHMCS_Price_API::invalidate_product( $pid );
	}
	foreach ( array_keys( $domains ) as $tld ) {
		$removed += WHMCS_Price_API::invalidate_domain( $tld );
	}

	if ( $removed > 0 ) {
		whmcs_price_flush_page_cache();
	}

	$scope = array();
	foreach ( array_keys( $products ) as $pid ) {
		$scope[] = 'product:' . $pid;
	}
	foreach ( array_keys( $domains ) as $tld ) {
		$scope[] = 'domain:' . $tld;
	}

	return new WP_REST_Response(
		array(
			'invalidated' => true,
			'scope'       => implode( ',', $scope ),
			'entries'     => $removed,
			'message'     => __( 'Cache invalidated.', 'whmcs-price' ),
		),
		200
	);
}

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

	$currency  = whmcs_price_currency_arg( $request->get_param( 'currency' ) );
	$cache_hit = whmcs_price_rest_product_cache_hit( (int) $pid, (string) $billing_cycle, (string) $attribute, $currency );
	$limited   = whmcs_price_rest_read_rate_limit( 'product', $cache_hit );
	if ( is_wp_error( $limited ) ) {
		return whmcs_price_rate_limit_rest_response( $limited );
	}

	$value = WHMCS_Price_API::get_product_data( $pid, $billing_cycle, $attribute, $currency );

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

	$tld_clean = preg_replace( '/[^a-zA-Z0-9\-]/', '', ltrim( (string) $tld, '.' ) );
	$currency  = whmcs_price_currency_arg( $request->get_param( 'currency' ) );
	$cache_hit = whmcs_price_rest_domain_cache_hit( $tld_clean, (string) $type, (int) $reg_period, $currency );
	$limited   = whmcs_price_rest_read_rate_limit( 'domain', $cache_hit );
	if ( is_wp_error( $limited ) ) {
		return whmcs_price_rate_limit_rest_response( $limited );
	}

	$value = WHMCS_Price_API::get_domain_price( $tld, $type, $reg_period, $currency );

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
