<?php
/**
 * Proactive cache warming via WP-Cron (#16).
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class CacheWarmingTest extends TestCase {

	private const TTL = 3600;

	/** @var object|false Fake event returned by wp_get_scheduled_event(). */
	private $event = false;

	/** @var array<int, array> Calls to wp_schedule_event(). */
	private array $scheduled_events = array();

	private int $cleared = 0;

	protected function setUp(): void {
		parent::setUp();

		require_once dirname( __DIR__, 2 ) . '/includes/cache-warming.php';

		$this->event            = false;
		$this->scheduled_events = array();
		$this->cleared          = 0;
		$t                      = $this;

		Functions\when( 'wp_get_scheduled_event' )->alias( static fn() => $t->event );
		Functions\when( 'wp_schedule_event' )->alias(
			static function ( $ts, $recurrence, $hook ) use ( $t ) {
				$t->scheduled_events[] = array( $ts, $recurrence, $hook );
				return true;
			}
		);
		Functions\when( 'wp_clear_scheduled_hook' )->alias(
			static function () use ( $t ) {
				++$t->cleared;
				return 1;
			}
		);
	}

	private function product_key( int $pid ): string {
		return 'whmcs_product_' . md5( $pid . '_monthly_price' );
	}

	/**
	 * Simulate a visitor viewing a product price, then end the request.
	 */
	private function visit_product( int $pid, string $price = '99 kr' ): void {
		$this->queue_response( $price );
		WHMCS_Price_API::get_product_data( $pid, 'monthly', 'price' );
		WHMCS_Price_API::flush_registry();
		$this->reset_api_state();
	}

	// ------------------------------------------------------------------
	// Registry
	// ------------------------------------------------------------------

	public function test_visitor_fetch_is_registered_at_shutdown(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 3, 'monthly', 'price' );

		$this->assertArrayNotHasKey( WHMCS_Price_API::REGISTRY_OPTION, $this->options, 'Written at shutdown, not per fetch.' );

		WHMCS_Price_API::flush_registry();
		$entry = $this->options[ WHMCS_Price_API::REGISTRY_OPTION ][ $this->product_key( 3 ) ];

		$this->assertSame( 'product', $entry['k'] );
		$this->assertSame( array( 3, 'monthly', 'price' ), $entry['a'] );
		$this->assertGreaterThan( 0, $entry['u'] );
	}

	public function test_all_feed_kinds_are_registered(): void {
		$this->queue_response( '129 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );
		$this->queue_response( '<table></table>' );
		WHMCS_Price_API::get_all_domain_prices();
		$this->queue_response( '<option value="monthly">99 kr</option>' );
		WHMCS_Price_API::get_product_setup_fee( 4, 'monthly' );
		WHMCS_Price_API::flush_registry();

		$kinds = array_column( WHMCS_Price_API::get_registry(), 'k' );
		sort( $kinds );
		$this->assertSame( array( 'domain', 'domain_all', 'pricefeed' ), $kinds );
	}

	public function test_background_refresh_does_not_extend_registration(): void {
		$this->visit_product( 1 );
		$this->options[ WHMCS_Price_API::REGISTRY_OPTION ][ $this->product_key( 1 ) ]['u'] = 1000;

		$this->queue_response( '109 kr' );
		WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) );
		WHMCS_Price_API::flush_registry();

		$this->assertSame( 1000, $this->options[ WHMCS_Price_API::REGISTRY_OPTION ][ $this->product_key( 1 ) ]['u'] );
	}

	public function test_stale_serve_registers_entry(): void {
		$this->visit_product( 1 );
		unset( $this->options[ WHMCS_Price_API::REGISTRY_OPTION ] );
		$this->age_entry( $this->product_key( 1 ), self::TTL + 60 );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		WHMCS_Price_API::flush_registry();

		$this->assertArrayHasKey( $this->product_key( 1 ), WHMCS_Price_API::get_registry() );
	}

	public function test_registry_is_capped_keeping_most_recent(): void {
		Filters\expectApplied( 'whmcs_price_warm_registry_size' )->andReturn( 2 );

		$registry = array();
		foreach ( array( 'old' => 100, 'mid' => 200, 'new' => 300 ) as $key => $used ) {
			$registry[ $key ] = array( 'k' => 'domain_all', 'a' => array(), 'u' => $used );
		}
		$this->options[ WHMCS_Price_API::REGISTRY_OPTION ] = $registry;

		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		WHMCS_Price_API::flush_registry();

		$this->assertSame(
			array( $this->product_key( 1 ), 'new' ),
			array_keys( $this->options[ WHMCS_Price_API::REGISTRY_OPTION ] )
		);
	}

	public function test_corrupt_registry_entries_are_ignored(): void {
		$this->options[ WHMCS_Price_API::REGISTRY_OPTION ] = array(
			'bad1' => 'string',
			'bad2' => array( 'k' => 'product' ),
			'ok'   => array( 'k' => 'domain_all', 'a' => array(), 'u' => time() ),
		);

		$this->assertSame( array( 'ok' ), array_keys( WHMCS_Price_API::get_registry() ) );
	}

	// ------------------------------------------------------------------
	// warm()
	// ------------------------------------------------------------------

	public function test_warm_skips_entries_with_time_left(): void {
		$this->visit_product( 1 );

		$stats = WHMCS_Price_API::warm( 960 );

		$this->assertSame( 1, $stats['skipped'] );
		$this->assertSame( 0, $stats['refreshed'] );
		$this->assertCount( 1, $this->http_log );
	}

	public function test_warm_refreshes_entries_close_to_expiry(): void {
		$this->visit_product( 1 );
		$this->age_entry( $this->product_key( 1 ), self::TTL - 300 ); // 5 min left.
		$this->queue_response( '109 kr' );

		$stats = WHMCS_Price_API::warm( 960 );

		$this->assertSame( 1, $stats['refreshed'] );
		$this->assertSame( '109 kr', $this->cached_value( $this->product_key( 1 ) ) );
	}

	public function test_warm_refreshes_entries_missing_after_purge(): void {
		$this->visit_product( 1 );
		WHMCS_Price_API::bump_cache_version();
		$this->queue_response( '109 kr' );

		$stats = WHMCS_Price_API::warm( 960 );

		$this->assertSame( 1, $stats['refreshed'] );
		$this->assertSame( '109 kr', $this->transients[ 'v2_' . $this->product_key( 1 ) ]['v'] ?? null );
	}

	public function test_warm_all_refreshes_fresh_entries_too(): void {
		$this->visit_product( 1 );
		$this->queue_response( '109 kr' );

		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 1, $stats['refreshed'] );
		$this->assertSame( 0, $stats['skipped'] );
	}

	public function test_warm_prunes_entries_unused_for_retention_period(): void {
		$this->visit_product( 1 );
		$this->options[ WHMCS_Price_API::REGISTRY_OPTION ][ $this->product_key( 1 ) ]['u'] = time() - 8 * DAY_IN_SECONDS;

		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 1, $stats['pruned'] );
		$this->assertSame( 0, $stats['checked'] );
		$this->assertSame( array(), $this->options[ WHMCS_Price_API::REGISTRY_OPTION ] );
	}

	public function test_warm_stops_when_whmcs_is_down(): void {
		$this->visit_product( 1 );
		$this->visit_product( 2 );
		$this->queue_error();
		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 1, $stats['failed'] );
		$this->assertSame( 1, $stats['deferred'] );
		$this->assertCount( 3, $this->http_log, 'Second entry must not be attempted during an outage.' );
	}

	public function test_failed_refresh_keeps_old_value(): void {
		$this->visit_product( 1 );
		$this->queue_response( 'Bad Gateway', 502 );

		WHMCS_Price_API::warm( 960, true );

		$this->assertSame( '99 kr', $this->cached_value( $this->product_key( 1 ) ) );
	}

	public function test_warm_respects_max_per_run(): void {
		Filters\expectApplied( 'whmcs_price_warm_max_per_run' )->andReturn( 2 );
		foreach ( array( 1, 2, 3 ) as $pid ) {
			$this->visit_product( $pid );
		}
		$this->queue_response( 'a' );
		$this->queue_response( 'b' );

		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 2, $stats['refreshed'] );
		$this->assertSame( 1, $stats['deferred'] );
	}

	public function test_warm_spaces_requests_when_more_than_ten_are_due(): void {
		Filters\expectApplied( 'whmcs_price_warm_delay' )->once()->andReturn( 0 );
		for ( $pid = 1; $pid <= 11; $pid++ ) {
			$this->visit_product( $pid );
		}
		for ( $pid = 1; $pid <= 11; $pid++ ) {
			$this->queue_response( "new $pid" );
		}

		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 11, $stats['refreshed'] );
	}

	public function test_warm_with_empty_registry_does_nothing(): void {
		$stats = WHMCS_Price_API::warm( 960 );

		$this->assertSame( 0, $stats['checked'] );
		$this->assertSame( array(), $this->http_log );
	}

	// ------------------------------------------------------------------
	// Settings and scheduling
	// ------------------------------------------------------------------

	public function test_settings_default_to_disabled_every_15_minutes(): void {
		$this->assertSame( array( 'enabled' => false, 'interval' => '15min' ), whmcs_price_warm_settings() );
	}

	public function test_unknown_interval_falls_back_to_15_minutes(): void {
		$this->set_plugin_option( 'cache_warm', '1' );
		$this->set_plugin_option( 'cache_warm_interval', 'weekly' );

		$this->assertSame( array( 'enabled' => true, 'interval' => '15min' ), whmcs_price_warm_settings() );
	}

	public function test_threshold_is_interval_plus_one_minute(): void {
		$this->set_plugin_option( 'cache_warm_interval', '5min' );
		$this->assertSame( 360, whmcs_price_warm_threshold() );

		$this->set_plugin_option( 'cache_warm_interval', 'hourly' );
		$this->assertSame( 3660, whmcs_price_warm_threshold() );
	}

	public function test_cron_schedules_are_registered(): void {
		$schedules = whmcs_price_warm_cron_schedules( array( 'hourly' => array( 'interval' => 3600 ) ) );

		$this->assertSame( 300, $schedules['whmcs_price_5min']['interval'] );
		$this->assertSame( 900, $schedules['whmcs_price_15min']['interval'] );
		$this->assertArrayHasKey( 'hourly', $schedules );
	}

	public function test_sync_schedules_event_when_enabled(): void {
		$this->set_plugin_option( 'cache_warm', '1' );
		$this->set_plugin_option( 'cache_warm_interval', '5min' );

		whmcs_price_warm_sync_schedule();

		$this->assertCount( 1, $this->scheduled_events );
		$this->assertSame( array( 'whmcs_price_5min', 'whmcs_price_cron_warm' ), array_slice( $this->scheduled_events[0], 1 ) );
	}

	public function test_sync_is_noop_when_schedule_already_matches(): void {
		$this->set_plugin_option( 'cache_warm', '1' );
		$this->event = (object) array( 'schedule' => 'whmcs_price_15min' );

		whmcs_price_warm_sync_schedule();

		$this->assertSame( array(), $this->scheduled_events );
		$this->assertSame( 0, $this->cleared );
	}

	public function test_sync_reschedules_when_interval_changes(): void {
		$this->set_plugin_option( 'cache_warm', '1' );
		$this->set_plugin_option( 'cache_warm_interval', 'hourly' );
		$this->event = (object) array( 'schedule' => 'whmcs_price_15min' );

		whmcs_price_warm_sync_schedule();

		$this->assertSame( 1, $this->cleared );
		$this->assertSame( 'hourly', $this->scheduled_events[0][1] );
	}

	public function test_sync_removes_event_when_disabled(): void {
		$this->event = (object) array( 'schedule' => 'whmcs_price_15min' );

		whmcs_price_warm_sync_schedule();

		$this->assertSame( 1, $this->cleared );
		$this->assertSame( array(), $this->scheduled_events );
	}

	public function test_cron_callback_does_nothing_when_disabled(): void {
		$this->visit_product( 1 );
		$this->age_entry( $this->product_key( 1 ), self::TTL );

		whmcs_price_run_warm();

		$this->assertCount( 1, $this->http_log );
	}

	public function test_cron_callback_warms_when_enabled(): void {
		$this->set_plugin_option( 'cache_warm', '1' );
		$this->visit_product( 1 );
		$this->age_entry( $this->product_key( 1 ), self::TTL - 60 );
		$this->queue_response( '109 kr' );

		whmcs_price_run_warm();

		$this->assertSame( '109 kr', $this->cached_value( $this->product_key( 1 ) ) );
	}

	public function test_warm_stops_after_three_failures_even_without_outage_mails(): void {
		$this->set_plugin_option( 'outage_notify', '0' );
		foreach ( array( 1, 2, 3, 4, 5 ) as $pid ) {
			$this->visit_product( $pid );
		}
		$this->queue_error();
		$this->queue_error();
		$this->queue_error();

		$stats = WHMCS_Price_API::warm( 960, true );

		$this->assertSame( 3, $stats['failed'] );
		$this->assertSame( 2, $stats['deferred'] );
	}

	public function test_refresh_entry_reports_success(): void {
		$this->queue_response( '99 kr' );
		$this->assertTrue( WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) ) );

		$this->queue_error();
		$this->assertFalse( WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) ) );
	}
}
