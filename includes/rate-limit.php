<?php
/**
 * Rate limiting utilities
 *
 * Shared by the REST API (read + purge) and configurable under
 * Settings → Advanced → Rate limiting.
 *
 * @package    WHMCS_Price
 * @subpackage Security
 * @since      2.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Default security / rate-limit settings.
 *
 * @since 2.9.0
 * @return array<string, int|string>
 */
function whmcs_price_security_defaults(): array {
	return array(
		'purge_success_interval' => 5,
		'purge_auth_limit'       => 10,
		'purge_auth_window'      => 60,
		'rest_rate_enabled'      => '0',
		'rest_rate_limit'        => 60,
		'rest_rate_window'       => 60,
		'rest_rate_miss_only'    => '0',
	);
}

/**
 * Resolved security settings (options merged with defaults, filterable).
 *
 * @since 2.9.0
 * @return array<string, int|string>
 */
function whmcs_price_get_security_settings(): array {
	$options  = get_option( 'whmcs_price_option', array() );
	$defaults = whmcs_price_security_defaults();
	$merged   = array_merge( $defaults, array_intersect_key( is_array( $options ) ? $options : array(), $defaults ) );

	/**
	 * Filter all security / rate-limit settings.
	 *
	 * @since 2.9.0
	 * @param array<string, int|string> $merged Settings with defaults applied.
	 */
	return (array) apply_filters( 'whmcs_price_security_settings', $merged );
}

/**
 * Client identifier for per-IP rate buckets (respects common proxy headers).
 *
 * @since 2.9.0
 * @return string
 */
function whmcs_price_rate_limit_client_id(): string {
	$headers = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_REAL_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

	foreach ( $headers as $header ) {
		if ( empty( $_SERVER[ $header ] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			continue;
		}
		$raw = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		if ( str_contains( $raw, ',' ) ) {
			$raw = trim( explode( ',', $raw )[0] );
		}
		if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
			return $raw;
		}
	}

	return 'unknown';
}

/**
 * Increment a rate-limit counter and return the new count for the current window.
 *
 * @since  2.9.0
 * @param  string $bucket          Logical bucket name (e.g. purge_auth, rest_read).
 * @param  int    $window_seconds  Window length in seconds.
 * @param  string $identifier      Optional client id; defaults to IP.
 * @return int                     Attempt count in the active window.
 */
function whmcs_price_rate_limit_record( string $bucket, int $window_seconds, string $identifier = '' ): int {
	if ( $window_seconds <= 0 ) {
		return 0;
	}

	$identifier = '' !== $identifier ? $identifier : whmcs_price_rate_limit_client_id();
	$key        = 'whmcs_rl_' . md5( $bucket . '|' . $identifier );
	$now        = time();
	$data       = get_transient( $key );

	if ( ! is_array( $data ) || empty( $data['start'] ) || ( $now - (int) $data['start'] ) >= $window_seconds ) {
		$data = array(
			'count' => 1,
			'start' => $now,
		);
	} else {
		$data['count'] = (int) $data['count'] + 1;
	}

	set_transient( $key, $data, $window_seconds );

	return (int) $data['count'];
}

/**
 * Read the current attempt count without incrementing.
 *
 * @since  2.9.0
 * @param  string $bucket          Bucket name.
 * @param  int    $window_seconds  Window length.
 * @param  string $identifier      Client id.
 * @return array{count: int, retry_after: int}
 */
function whmcs_price_rate_limit_status( string $bucket, int $window_seconds, string $identifier = '' ): array {
	$identifier = '' !== $identifier ? $identifier : whmcs_price_rate_limit_client_id();
	$key        = 'whmcs_rl_' . md5( $bucket . '|' . $identifier );
	$now        = time();
	$data       = get_transient( $key );

	if ( ! is_array( $data ) || empty( $data['start'] ) ) {
		return array(
			'count'       => 0,
			'retry_after' => 0,
		);
	}

	$elapsed = $now - (int) $data['start'];
	if ( $elapsed >= $window_seconds ) {
		return array(
			'count'       => 0,
			'retry_after' => 0,
		);
	}

	return array(
		'count'       => (int) ( $data['count'] ?? 0 ),
		'retry_after' => max( 1, $window_seconds - $elapsed ),
	);
}

/**
 * Return WP_Error 429 if the bucket is already at or over the limit.
 *
 * @since  2.9.0
 * @param  string $bucket          Bucket name.
 * @param  int    $max_attempts    Max attempts per window (0 = disabled).
 * @param  int    $window_seconds  Window length (0 = disabled).
 * @param  string $identifier      Client id.
 * @return true|WP_Error
 */
function whmcs_price_rate_limit_check( string $bucket, int $max_attempts, int $window_seconds, string $identifier = '' ): true|WP_Error {
	if ( $max_attempts <= 0 || $window_seconds <= 0 ) {
		return true;
	}

	$status = whmcs_price_rate_limit_status( $bucket, $window_seconds, $identifier );

	if ( $status['count'] >= $max_attempts ) {
		$response = new WP_Error(
			'whmcs_price_rate_limited',
			__( 'Too many requests. Please try again later.', 'whmcs-price' ),
			array(
				'status'      => 429,
				'retry_after' => $status['retry_after'],
			)
		);
		return $response;
	}

	return true;
}

/**
 * Record one attempt and return WP_Error 429 if the limit is now exceeded.
 *
 * @since  2.9.0
 * @param  string $bucket          Bucket name.
 * @param  int    $max_attempts    Max attempts.
 * @param  int    $window_seconds  Window length.
 * @param  string $identifier      Client id.
 * @return true|WP_Error
 */
function whmcs_price_rate_limit_enforce( string $bucket, int $max_attempts, int $window_seconds, string $identifier = '' ): true|WP_Error {
	if ( $max_attempts <= 0 || $window_seconds <= 0 ) {
		return true;
	}

	$count  = whmcs_price_rate_limit_record( $bucket, $window_seconds, $identifier );
	$status = whmcs_price_rate_limit_status( $bucket, $window_seconds, $identifier );

	if ( $count > $max_attempts ) {
		return new WP_Error(
			'whmcs_price_rate_limited',
			__( 'Too many requests. Please try again later.', 'whmcs-price' ),
			array(
				'status'      => 429,
				'retry_after' => $status['retry_after'],
			)
		);
	}

	return true;
}

/**
 * Build a REST response from a rate-limit WP_Error (adds Retry-After header).
 *
 * @since  2.9.0
 * @param  WP_Error $error Rate-limit error.
 * @return WP_REST_Response
 */
function whmcs_price_rate_limit_rest_response( WP_Error $error ): WP_REST_Response {
	$retry    = (int) ( $error->get_error_data()['retry_after'] ?? 60 );
	$response = new WP_REST_Response(
		array(
			'code'        => $error->get_error_code(),
			'message'     => $error->get_error_message(),
			'retry_after' => $retry,
		),
		429
	);
	$response->header( 'Retry-After', (string) $retry );
	return $response;
}

/**
 * Enforce configurable rate limits on public REST read endpoints.
 *
 * @since  2.9.0
 * @param  string $endpoint  Endpoint slug (product, domain).
 * @param  bool   $cache_hit Whether the response will be served from cache only.
 * @return true|WP_Error
 */
function whmcs_price_rest_read_rate_limit( string $endpoint, bool $cache_hit = false ): true|WP_Error {
	$settings = whmcs_price_get_security_settings();

	if ( '1' !== (string) $settings['rest_rate_enabled'] ) {
		return true;
	}

	if ( '1' === (string) $settings['rest_rate_miss_only'] && $cache_hit ) {
		return true;
	}

	$max    = (int) $settings['rest_rate_limit'];
	$window = (int) $settings['rest_rate_window'];
	$bucket = 'rest_read_' . sanitize_key( $endpoint );

	/**
	 * Filter REST read rate-limit parameters before enforcement.
	 *
	 * @since 2.9.0
	 * @param int    $max       Max requests per window.
	 * @param int    $window    Window in seconds.
	 * @param string $bucket    Transient bucket name.
	 * @param string $endpoint  product|domain.
	 * @param bool   $cache_hit Whether this request only reads cache.
	 */
	$max    = (int) apply_filters( 'whmcs_price_rest_rate_limit_max', $max, $window, $bucket, $endpoint, $cache_hit );
	$window = (int) apply_filters( 'whmcs_price_rest_rate_limit_window', $window, $max, $bucket, $endpoint, $cache_hit );

	return whmcs_price_rate_limit_enforce( $bucket, $max, $window );
}

/**
 * Whether a product price is already cached (used for miss-only REST limiting).
 *
 * @since  2.9.0
 * @param  int    $pid           Product ID.
 * @param  string $billing_cycle WHMCS billing cycle.
 * @param  string $attribute     name|description|price.
 * @return bool
 */
function whmcs_price_rest_product_cache_hit( int $pid, string $billing_cycle, string $attribute ): bool {
	$cache_key = 'whmcs_product_' . md5( $pid . '_' . $billing_cycle . '_' . $attribute );
	$version   = (int) get_option( 'whmcs_price_cache_version', 1 );
	$key       = 'v' . max( 1, $version ) . '_' . $cache_key;

	if ( false !== wp_cache_get( $key, 'whmcs_price' ) ) {
		return true;
	}

	return false !== get_transient( $key );
}

/**
 * Whether a domain price is already cached.
 *
 * @since  2.9.0
 * @param  string $tld        TLD without dot.
 * @param  string $type       register|renew|transfer.
 * @param  int    $reg_period Years.
 * @return bool
 */
function whmcs_price_rest_domain_cache_hit( string $tld, string $type, int $reg_period ): bool {
	$cache_key = 'whmcs_domain_' . md5( $tld . '_' . $type . '_' . $reg_period );
	$version   = (int) get_option( 'whmcs_price_cache_version', 1 );
	$key       = 'v' . max( 1, $version ) . '_' . $cache_key;

	if ( false !== wp_cache_get( $key, 'whmcs_price' ) ) {
		return true;
	}

	return false !== get_transient( $key );
}
