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
    delete_option( 'whmcs_price_cache_version' );
    delete_option( 'whmcs_price_stale_stats' );
    delete_option( 'whmcs_price_cache_registry' );
    delete_transient( 'whmcs_price_outage_notified' );

    // Pending stale-while-revalidate refreshes (2.10.0+).
    wp_unschedule_hook( 'whmcs_price_refresh_entry' );
    wp_clear_scheduled_hook( 'whmcs_price_cron_warm' );

    // Remove per-user settings preferences.
    $user_meta_keys = array(
        'whmcs_price_active_tab',
    );
    foreach ( $user_meta_keys as $meta_key ) {
        // phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- One-off cleanup on uninstall.
        $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $meta_key ) );
        // phpcs:enable
    }

    // Prefixes to clean up: cache transients (legacy unversioned and the
    // versioned "v{N}_whmcs_" keys used since 2.9.0) and legacy lock transients.
    $prefixes = array(
        $wpdb->esc_like( '_transient_whmcs_' ) . '%',
        $wpdb->esc_like( '_transient_v' ) . '%' . $wpdb->esc_like( '_whmcs_' ) . '%',
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

    // Fetch locks are stored as plain options since 2.9.0 (atomic add_option()).
    // phpcs:disable WordPress.DB.DirectDatabaseQuery
    $lock_options = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
            $wpdb->esc_like( 'lock_whmcs_' ) . '%'
        )
    );
    // phpcs:enable
    foreach ( $lock_options as $lock_option ) {
        delete_option( $lock_option );
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