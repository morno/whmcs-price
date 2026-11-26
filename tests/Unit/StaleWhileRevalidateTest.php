<?php
/**
 * Stale-while-revalidate caching (#15).
 *
 * Time is simulated by moving stored entries back in time with age_entry()
 * rather than by mocking time().
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class StaleWhileRevalidateTest extends TestCase {

	private const TTL = 3600;

	private function product_key( int $pid = 1, string $cycle = 'monthly', string $attr = 'price' ): string {
		return 'whmcs_product_' . md5( $pid . '_' . $cycle . '_' . $attr );
	}

	/**
	 * Cache a product price, then make it look $age seconds old.
	 */
	private function seed_product( string $value, int $age, int $pid = 1 ): void {
		$this->queue_response( $value );
		WHMCS_Price_API::get_product_data( $pid, 'monthly', 'price' );
		$this->age_entry( $this->product_key( $pid ), $age );
		$this->reset_api_state();
	}

	public function test_entry_is_stored_as_envelope_for_the_stale_window(): void {
		$before = time();
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$key   = $this->storage_key( $this->product_key() );
		$entry = $this->transients[ $key ];

		$this->assertSame( '99 kr', $entry['v'] );
		$this->assertGreaterThanOrEqual( $before, $entry['t'] );
		$this->assertSame( $entry['t'] + self::TTL, $entry['e'] );
		$this->assertSame( 2 * self::TTL, $this->transient_ttl[ $key ], 'Stored for TTL + stale window (default 2 × TTL).' );
	}

	public function test_fresh_entry_does_not_schedule_refresh(): void {
		$this->seed_product( '99 kr', 60 );

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->scheduled );
		$this->assertCount( 1, $this->http_log );
	}

	public function test_expired_entry_is_served_stale_and_refresh_is_scheduled(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 1, $this->http_log, 'Visitor must not wait for WHMCS.' );
		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( WHMCS_Price_API::REFRESH_HOOK, $this->scheduled[0][1] );
		$this->assertSame( array( 'product', array( 1, 'monthly', 'price' ) ), $this->scheduled[0][2] );
	}

	public function test_stale_value_still_passes_through_filters(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );

		Filters\expectApplied( 'whmcs_price_product_data' )->once()->andReturn( 'filtered' );

		$this->assertSame( 'filtered', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_refresh_is_scheduled_only_once(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		$this->reset_api_state();
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertCount( 1, $this->scheduled );
	}

	public function test_stale_value_is_served_even_while_lock_is_held(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );
		$this->options[ 'lock_' . $this->product_key() ] = (string) ( time() + 10 );

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_entry_older_than_max_stale_age_is_fetched_synchronously(): void {
		$this->seed_product( '99 kr', 2 * self::TTL + 1 );
		$this->queue_response( '109 kr' );

		$this->assertSame( '109 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_cold_cache_still_fetches_synchronously(): void {
		$this->queue_response( '99 kr' );

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_max_stale_age_filter_can_disable_stale_serving(): void {
		Filters\expectApplied( 'whmcs_price_max_stale_age' )->andReturnUsing( static fn( $max, $ttl ) => $ttl );

		$this->seed_product( '99 kr', self::TTL + 60 );
		$this->queue_response( '109 kr' );

		$this->assertSame( '109 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_max_stale_age_is_never_below_ttl(): void {
		Filters\expectApplied( 'whmcs_price_max_stale_age' )->andReturn( 10 );

		$this->assertSame( self::TTL, WHMCS_Price_API::get_max_stale_age() );
		$this->assertSame( 0, WHMCS_Price_API::get_stale_window() );
	}

	public function test_refresh_entry_fetches_and_replaces_stale_value(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );
		$this->queue_response( '109 kr' );

		WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) );

		$this->assertCount( 2, $this->http_log );
		$this->assertSame( '109 kr', $this->cached_value( $this->product_key() ) );

		// Next visitor gets the fresh value from cache, without HTTP or scheduling.
		$this->reset_api_state();
		$this->assertSame( '109 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_failed_refresh_keeps_stale_value(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );
		$this->queue_error();

		WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) );

		$this->reset_api_state();
		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_refresh_entry_resets_force_mode(): void {
		$this->queue_error();
		WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) );

		$prop = new \ReflectionProperty( WHMCS_Price_API::class, 'force_refresh' );
		$prop->setAccessible( true );
		$this->assertFalse( $prop->getValue() );
	}

	public function test_refresh_entry_ignores_unknown_kind_and_bad_args(): void {
		WHMCS_Price_API::refresh_entry( 'nonsense', array( 1 ) );
		WHMCS_Price_API::refresh_entry( 'product', array( 1 ) );
		WHMCS_Price_API::refresh_entry( 'domain', 'not-an-array' );

		$this->assertSame( array(), $this->http_log );
	}

	public function test_domain_price_is_served_stale_with_domain_refresh(): void {
		$this->queue_response( '129 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );
		$this->age_entry( 'whmcs_domain_' . md5( 'se_register_1' ), self::TTL + 60 );
		$this->reset_api_state();

		$this->assertSame( '129 kr', WHMCS_Price_API::get_domain_price( 'se', 'register', 1 ) );
		$this->assertSame( array( 'domain', array( 'se', 'register', 1 ) ), $this->scheduled[0][2] );

		$this->queue_response( '139 kr' );
		WHMCS_Price_API::refresh_entry( 'domain', array( 'se', 'register', 1 ) );
		$this->reset_api_state();
		$this->assertSame( '139 kr', WHMCS_Price_API::get_domain_price( 'se', 'register', 1 ) );
	}

	public function test_all_domain_prices_are_served_stale(): void {
		$this->queue_response( '<table></table>' );
		WHMCS_Price_API::get_all_domain_prices();
		$this->age_entry( 'whmcs_domain_all', self::TTL + 60 );
		$this->reset_api_state();

		$this->assertSame( '<table></table>', WHMCS_Price_API::get_all_domain_prices() );
		$this->assertSame( array( 'domain_all', array() ), $this->scheduled[0][2] );
	}

	public function test_pricing_feed_is_served_stale_for_setup_fee_and_cycles(): void {
		$this->queue_response( '<option value="monthly">99 kr + 10 kr</option>' );
		WHMCS_Price_API::get_product_setup_fee( 4, 'monthly' );
		$this->age_entry( 'whmcs_pricefeed_' . md5( '4' ), self::TTL + 60 );
		$this->reset_api_state();

		$this->assertSame( '10 kr', WHMCS_Price_API::get_product_setup_fee( 4, 'monthly' ) );
		$this->assertSame( array( 'monthly' => '99 kr' ), WHMCS_Price_API::get_all_product_cycles( 4 ) );
		$this->assertCount( 1, $this->http_log );
		$this->assertCount( 1, $this->scheduled, 'Both callers share one pricefeed refresh.' );
		$this->assertSame( array( 'pricefeed', array( 4 ) ), $this->scheduled[0][2] );
	}

	public function test_legacy_plain_cache_value_is_treated_as_fresh(): void {
		$this->transients[ $this->storage_key( $this->product_key() ) ] = '79 kr';

		$this->assertSame( '79 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->http_log );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_stale_stats_are_flushed_once_per_request(): void {
		$this->seed_product( '99 kr', self::TTL + 60, 1 );
		$this->seed_product( '49 kr', self::TTL + 60, 2 );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		WHMCS_Price_API::get_product_data( 2, 'monthly', 'price' );
		WHMCS_Price_API::flush_stale_stats();

		$stats = WHMCS_Price_API::get_stale_stats();
		$this->assertSame( 2, $stats['served'] );
		$this->assertGreaterThan( 0, $stats['last'] );

		// A second flush without new stale hits changes nothing.
		WHMCS_Price_API::flush_stale_stats();
		$this->assertSame( 2, WHMCS_Price_API::get_stale_stats()['served'] );
	}

	public function test_stale_entry_counts_as_cache_hit_for_rest_rate_limit(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );

		$this->assertTrue( whmcs_price_rest_product_cache_hit( 1, 'monthly', 'price' ) );
	}

	public function test_bump_cache_version_also_drops_stale_values(): void {
		$this->seed_product( '99 kr', self::TTL + 60 );
		WHMCS_Price_API::bump_cache_version();
		$this->queue_response( '109 kr' );

		$this->assertSame( '109 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_refresh_overwrites_the_same_key_for_non_normalised_input(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( '01', 'monthly', 'price' );
		$key = 'whmcs_product_' . md5( '01_monthly_price' );
		$this->age_entry( $key, self::TTL + 60 );
		$this->reset_api_state();

		WHMCS_Price_API::get_product_data( '01', 'monthly', 'price' );
		$args = $this->scheduled[0][2];

		$this->queue_response( '109 kr' );
		WHMCS_Price_API::refresh_entry( ...$args );

		$this->assertSame( '109 kr', $this->cached_value( $key ) );
	}
}
