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

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

/**
 * Delete all options and transients for a single site.
 */
function whmcs_price_uninstall_site() {
    global $wpdb;

    delete_option( 'whmcs_price_option' );

    // Prefixes to clean up: regular transients and lock transients.
    $prefixes = array(
        $wpdb->esc_like( '_transient_whmcs_' ) . '%',
        $wpdb->esc_like( '_transient_lock_whmcs_' ) . '%',
    );

    foreach ( $prefixes as $like ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery
        $transient_keys = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            )
        );
        // phpcs:enable

        foreach ( $transient_keys as $key ) {
            delete_transient( $key );
        }
    }
}

if ( is_multisite() ) {
    $whmcs_price_sites = get_sites( array( 'fields' => 'ids' ) );
    foreach ( $whmcs_price_sites as $whmcs_price_site_id ) {
        switch_to_blog( $whmcs_price_site_id );
        whmcs_price_uninstall_site();
        restore_current_blog();
    }
} else {
    whmcs_price_uninstall_site();
}