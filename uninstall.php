<?php
/**
 * Plugin Uninstall
 *
 * Runs automatically when the plugin is deleted via the WordPress admin.
 * Removes all plugin options and cached transients from the database.
 *
 * @package WHMCS_Price
 * @since   2.5.1
 */

// Exit if not called from WordPress uninstall process.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete plugin options.
 */
delete_option( 'whmcs_price_option' );

/**
 * Delete all WHMCS Price transients.
 *
 * WordPress has no bulk transient delete API, so we query the options
 * table directly and then use delete_transient() for each key.
 *
 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
 */
global $wpdb;

$transient_keys = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( '_transient_whmcs_' ) . '%'
	)
);
// @phpcs:enable

foreach ( $transient_keys as $key ) {
	delete_transient( $key );
}
