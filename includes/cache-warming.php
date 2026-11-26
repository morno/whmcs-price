<?php
/**
 * Proactive cache warming via WP-Cron
 *
 * When enabled (Settings → Performance), a recurring WP-Cron job refreshes
 * cached prices shortly before they expire, so visitors are never the ones
 * who trigger a WHMCS round trip. Which entries to refresh comes from the
 * registry WHMCS_Price_API keeps of entries visitors actually use.
 *
 * Works together with stale-while-revalidate: if a warm-up is missed (e.g.
 * WP-Cron did not run), visitors still get the stale value instantly.
 *
 * @package    WHMCS_Price
 * @subpackage Cache
 * @since      2.10.0
 */

defined( 'ABSPATH' ) || exit;

/** Recurring cron hook for the warm-up job. */
const WHMCS_PRICE_WARM_HOOK = 'whmcs_price_cron_warm';

/**
 * Allowed warm-up intervals: setting value => array( cron schedule, seconds, label ).
 *
 * @since  2.10.0
 * @return array<string, array{schedule: string, seconds: int, label: string}>
 */
function whmcs_price_warm_intervals(): array {
	return array(
		'5min'   => array(
			'schedule' => 'whmcs_price_5min',
			'seconds'  => 5 * MINUTE_IN_SECONDS,
			'label'    => __( 'Every 5 minutes', 'whmcs-price' ),
		),
		'15min'  => array(
			'schedule' => 'whmcs_price_15min',
			'seconds'  => 15 * MINUTE_IN_SECONDS,
			'label'    => __( 'Every 15 minutes', 'whmcs-price' ),
		),
		'hourly' => array(
			'schedule' => 'hourly',
			'seconds'  => HOUR_IN_SECONDS,
			'label'    => __( 'Hourly', 'whmcs-price' ),
		),
	);
}

/**
 * Current warm-up settings.
 *
 * @since  2.10.0
 * @return array{enabled: bool, interval: string}
 */
function whmcs_price_warm_settings(): array {
	$options  = get_option( 'whmcs_price_option', array() );
	$options  = is_array( $options ) ? $options : array();
	$interval = (string) ( $options['cache_warm_interval'] ?? '15min' );

	return array(
		'enabled'  => '1' === (string) ( $options['cache_warm'] ?? '0' ),
		'interval' => array_key_exists( $interval, whmcs_price_warm_intervals() ) ? $interval : '15min',
	);
}

/**
 * Seconds before expiry at which an entry is refreshed.
 *
 * Must be longer than the interval between runs, otherwise entries would
 * expire between two runs. Default: one interval plus one minute.
 *
 * @since  2.10.0
 * @return int
 */
function whmcs_price_warm_threshold(): int {
	$settings = whmcs_price_warm_settings();
	$seconds  = whmcs_price_warm_intervals()[ $settings['interval'] ]['seconds'];

	/**
	 * Filter how close to expiry an entry must be before the warm-up job
	 * refreshes it.
	 *
	 * @since 2.10.0
	 * @param int    $threshold Seconds. Default interval + 60.
	 * @param string $interval  Configured interval key (5min, 15min, hourly).
	 */
	return max( 0, (int) apply_filters( 'whmcs_price_warm_threshold', $seconds + MINUTE_IN_SECONDS, $settings['interval'] ) );
}

/**
 * Register the plugin's custom cron intervals.
 *
 * @since  2.10.0
 * @param  array $schedules Existing schedules.
 * @return array
 */
function whmcs_price_warm_cron_schedules( $schedules ) {
	$schedules = is_array( $schedules ) ? $schedules : array();

	$schedules['whmcs_price_5min']  = array(
		'interval' => 5 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 5 minutes (WHMCS Price)', 'whmcs-price' ),
	);
	$schedules['whmcs_price_15min'] = array(
		'interval' => 15 * MINUTE_IN_SECONDS,
		'display'  => __( 'Every 15 minutes (WHMCS Price)', 'whmcs-price' ),
	);

	return $schedules;
}
add_filter( 'cron_schedules', 'whmcs_price_warm_cron_schedules' );

/**
 * Make the scheduled event match the settings: schedule, reschedule or remove.
 *
 * Called when settings change, on activation and (cheaply) on admin_init so
 * a lost event heals itself.
 *
 * @since  2.10.0
 * @return void
 */
function whmcs_price_warm_sync_schedule(): void {
	$settings = whmcs_price_warm_settings();
	$event    = function_exists( 'wp_get_scheduled_event' ) ? wp_get_scheduled_event( WHMCS_PRICE_WARM_HOOK ) : false;

	if ( ! $settings['enabled'] ) {
		if ( $event ) {
			wp_clear_scheduled_hook( WHMCS_PRICE_WARM_HOOK );
		}
		return;
	}

	$schedule = whmcs_price_warm_intervals()[ $settings['interval'] ]['schedule'];
	if ( $event && $event->schedule === $schedule ) {
		return;
	}

	wp_clear_scheduled_hook( WHMCS_PRICE_WARM_HOOK );
	wp_schedule_event( time() + MINUTE_IN_SECONDS, $schedule, WHMCS_PRICE_WARM_HOOK );
}
add_action( 'add_option_whmcs_price_option', 'whmcs_price_warm_sync_schedule' );
add_action( 'update_option_whmcs_price_option', 'whmcs_price_warm_sync_schedule' );
add_action( 'admin_init', 'whmcs_price_warm_sync_schedule' );

/**
 * WP-Cron callback: refresh entries that are about to expire.
 *
 * @since  2.10.0
 * @return void
 */
function whmcs_price_run_warm(): void {
	if ( ! whmcs_price_warm_settings()['enabled'] ) {
		return;
	}
	WHMCS_Price_API::warm( whmcs_price_warm_threshold() );
}
add_action( WHMCS_PRICE_WARM_HOOK, 'whmcs_price_run_warm' );
