<?php
/**
 * Site Health Integration
 *
 * Adds WHMCS Price checks to the WordPress Site Health screen
 * (Tools -> Site Health -> Status). Gives administrators visibility
 * into the plugin's configuration and connectivity.
 *
 * Checks:
 *   1. WHMCS URL configured (direct)
 *   2. WHMCS connection + feed responding (direct)
 *
 * @package    WHMCS_Price
 * @subpackage SiteHealth
 * @since      2.9.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register WHMCS Price checks with the Site Health API.
 *
 * @since 2.9.0
 * @param  array $tests Existing tests.
 * @return array
 */
add_filter( 'site_status_tests', function( array $tests ): array {
	$tests['direct']['whmcs_price_url_configured'] = array(
		'label' => __( 'WHMCS Price: URL configured', 'whmcs-price' ),
		'test'  => 'whmcs_price_health_url_configured',
	);
	$tests['direct']['whmcs_price_connection'] = array(
		'label' => __( 'WHMCS Price: Connection', 'whmcs-price' ),
		'test'  => 'whmcs_price_health_connection',
	);
	return $tests;
} );

/**
 * Check: Is the WHMCS URL configured?
 *
 * @since  2.9.0
 * @return array Site Health result.
 */
function whmcs_price_health_url_configured(): array {
	$options   = get_option( 'whmcs_price_option', array() );
	$whmcs_url = ! empty( $options['whmcs_url'] ) ? $options['whmcs_url'] : '';
	$actions   = sprintf(
		'<a href="%s" class="button button-secondary">%s</a>',
		esc_url( admin_url( 'options-general.php?page=whmcs_price' ) ),
		esc_html__( 'Open Settings', 'whmcs-price' )
	);

	if ( empty( $whmcs_url ) ) {
		return array(
			'label'       => __( 'WHMCS URL is not configured', 'whmcs-price' ),
			'status'      => 'critical',
			'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'red' ),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'No WHMCS URL is set. Prices cannot be fetched until a URL is configured under Settings -> WHMCS Price Settings -> Connection.', 'whmcs-price' )
			),
			'actions'     => $actions,
			'test'        => 'whmcs_price_url_configured',
		);
	}

	return array(
		'label'       => sprintf(
			/* translators: %s: WHMCS URL */
			__( 'WHMCS URL is configured: %s', 'whmcs-price' ),
			esc_html( $whmcs_url )
		),
		'status'      => 'good',
		'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'green' ),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html__( 'The WHMCS URL is configured. The connection test below verifies it is reachable.', 'whmcs-price' )
		),
		'actions'     => $actions,
		'test'        => 'whmcs_price_url_configured',
	);
}

/**
 * Check: Is WHMCS reachable and returning pricing data?
 *
 * @since  2.9.0
 * @return array Site Health result.
 */
function whmcs_price_health_connection(): array {
	// Use the same SSRF-validated URL the rest of the plugin uses for
	// outbound calls. Reading the raw option here would let an admin probe
	// internal IPs / cloud metadata endpoints through Site Health.
	$whmcs_url     = WHMCS_Price_API::get_url();
	$raw_url_set   = ! empty( ( get_option( 'whmcs_price_option', array() )['whmcs_url'] ?? '' ) );
	$actions       = sprintf(
		'<a href="%s" class="button button-secondary">%s</a>',
		esc_url( admin_url( 'options-general.php?page=whmcs_price' ) ),
		esc_html__( 'Open Settings', 'whmcs-price' )
	);

	if ( empty( $whmcs_url ) ) {
		// Distinguish "not configured" from "configured but failed validation".
		if ( $raw_url_set ) {
			return array(
				'label'       => __( 'WHMCS URL failed validation', 'whmcs-price' ),
				'status'      => 'critical',
				'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'red' ),
				'description' => sprintf(
					'<p>%s</p>',
					esc_html__( 'The configured WHMCS URL was rejected. Common causes: not HTTPS, uses a non-443 port, embedded credentials, or points to a private/loopback/cloud-metadata address.', 'whmcs-price' )
				),
				'actions'     => $actions,
				'test'        => 'whmcs_price_connection',
			);
		}
		return array(
			'label'       => __( 'WHMCS connection not tested: no URL configured', 'whmcs-price' ),
			'status'      => 'recommended',
			'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'orange' ),
			'description' => sprintf( '<p>%s</p>', esc_html__( 'Configure a WHMCS URL first.', 'whmcs-price' ) ),
			'actions'     => $actions,
			'test'        => 'whmcs_price_connection',
		);
	}

	$test_url = $whmcs_url . '/feeds/domainpricing.php';
	$response = wp_remote_get( $test_url, array(
		'timeout'             => 10,
		'redirection'         => 0,
		'reject_unsafe_urls'  => true,
		'sslverify'           => true,
		'limit_response_size' => 65536,
	) );

	if ( is_wp_error( $response ) ) {
		return array(
			'label'       => __( 'WHMCS is not reachable', 'whmcs-price' ),
			'status'      => 'critical',
			'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'red' ),
			'description' => sprintf(
				'<p>%s</p><p><code>%s</code></p>',
				esc_html__( 'WordPress could not connect to WHMCS. Error:', 'whmcs-price' ),
				esc_html( $response->get_error_message() )
			),
			'actions'     => $actions,
			'test'        => 'whmcs_price_connection',
		);
	}

	$code = (int) wp_remote_retrieve_response_code( $response );
	if ( 200 !== $code ) {
		return array(
			'label'       => sprintf(
				/* translators: %d: HTTP status code */
				__( 'WHMCS returned HTTP %d', 'whmcs-price' ),
				$code
			),
			'status'      => 'critical',
			'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'red' ),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'The WHMCS pricing feed returned an unexpected status. Check your WHMCS URL and that data feeds are enabled.', 'whmcs-price' )
			),
			'actions'     => $actions,
			'test'        => 'whmcs_price_connection',
		);
	}

	$body     = wp_remote_retrieve_body( $response );
	$has_data = strlen( trim( $body ) ) > 10;

	if ( ! $has_data ) {
		return array(
			'label'       => __( 'WHMCS feed returned an empty response', 'whmcs-price' ),
			'status'      => 'recommended',
			'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'orange' ),
			'description' => sprintf(
				'<p>%s</p>',
				esc_html__( 'WHMCS responded but the pricing feed returned no data. Check that data feeds are enabled in WHMCS.', 'whmcs-price' )
			),
			'actions'     => $actions,
			'test'        => 'whmcs_price_connection',
		);
	}

	return array(
		'label'       => sprintf(
			/* translators: %s: WHMCS URL */
			__( 'WHMCS is reachable at %s', 'whmcs-price' ),
			esc_html( $whmcs_url )
		),
		'status'      => 'good',
		'badge'       => array( 'label' => __( 'WHMCS Price', 'whmcs-price' ), 'color' => 'green' ),
		'description' => sprintf(
			'<p>%s</p>',
			esc_html__( 'WordPress can connect to WHMCS and the pricing feed is returning data.', 'whmcs-price' )
		),
		'actions'     => '',
		'test'        => 'whmcs_price_connection',
	);
}
