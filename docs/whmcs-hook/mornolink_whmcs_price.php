<?php
// phpcs:ignoreFile -- Runs inside WHMCS, not WordPress: wp_remote_post() and other WP APIs do not exist there, so cURL is used. Not part of the WordPress plugin package (docs/ is in .distignore).
/**
 * Mornolink for WHMCS: instant price updates on your WordPress site(s).
 *
 * Install:
 *   1. Copy this file to <your WHMCS>/includes/hooks/mornolink_whmcs_price.php
 *   2. Fill in your WordPress site URL and Cache Purge Token below.
 *      (WordPress: Settings -> WHMCS Price -> Advanced -> Cache Purge Token)
 *
 * What it does:
 *   - Product saved or deleted in WHMCS  -> that product's cached prices are cleared.
 *   - Domain pricing saved in WHMCS      -> that TLD's cached prices are cleared.
 *   - Once a day (WHMCS daily cron)      -> all cached prices are cleared, as a safety
 *                                           net for changes made without a hook
 *                                           (registrar price sync, currency updates).
 *
 * Failures are written to the WHMCS Activity Log and never block saving in WHMCS.
 * Requires Mornolink for WHMCS 2.10.0 or later on the WordPress side.
 *
 * @see https://github.com/morno/whmcs-price/blob/main/docs/whmcs-webhook.md
 */

// Only runs inside WHMCS. Blocks direct web access, and does nothing if the
// file is ever loaded by WordPress (e.g. by a code scanner) instead of WHMCS.
if ( ! defined( 'WHMCS' ) && ! defined( 'ABSPATH' ) ) {
	exit( 'This file cannot be accessed directly' );
}
if ( ! defined( 'WHMCS' ) ) {
	return;
}

/**
 * WordPress sites to notify: site URL => Cache Purge Token.
 * Add one line per site if several sites show prices from this WHMCS.
 */
function mornolink_whmcs_price_sites(): array {
	return array(
		'https://www.example.com' => 'paste-your-cache-purge-token-here',
	);
}

/**
 * Tell each WordPress site to drop cached prices for $scope.
 *
 * @param string $scope "product:<pid>", "domain:<tld>" or "all".
 */
function mornolink_whmcs_price_invalidate( string $scope ): void {
	foreach ( mornolink_whmcs_price_sites() as $site => $token ) {
		if ( '' === $token || 'paste-your-cache-purge-token-here' === $token ) {
			continue; // Not configured yet.
		}

		$ch = curl_init( rtrim( $site, '/' ) . '/wp-json/whmcs-price/v1/webhook/invalidate' );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => json_encode( array( 'scope' => $scope ) ),
				CURLOPT_HTTPHEADER     => array(
					'Content-Type: application/json',
					'X-WHMCS-Price-Token: ' . $token,
				),
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_CONNECTTIMEOUT => 3,
				CURLOPT_TIMEOUT        => 5,
			)
		);
		$body  = curl_exec( $ch );
		$code  = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error = curl_error( $ch );

		// 429 = WordPress is already cooling down after a recent full purge. Not an error.
		if ( 200 !== $code && 429 !== $code ) {
			logActivity(
				sprintf(
					'Mornolink for WHMCS: could not clear cached prices (%s) on %s - HTTP %d %s',
					$scope,
					$site,
					$code,
					$error ? $error : substr( (string) $body, 0, 200 )
				)
			);
		}
	}
}

add_hook( 'ProductEdit', 1, function ( $vars ) {
	mornolink_whmcs_price_invalidate( 'product:' . (int) $vars['pid'] );
} );

add_hook( 'ProductDelete', 1, function ( $vars ) {
	mornolink_whmcs_price_invalidate( 'product:' . (int) $vars['pid'] );
} );

add_hook( 'TopLevelDomainPricingUpdate', 1, function ( $vars ) {
	mornolink_whmcs_price_invalidate( 'domain:' . ltrim( (string) $vars['tld'], '.' ) );
} );

add_hook( 'DailyCronJob', 1, function ( $vars ) {
	mornolink_whmcs_price_invalidate( 'all' );
} );
