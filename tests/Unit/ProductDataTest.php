<?php
/**
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class ProductDataTest extends TestCase {

	public function test_fetches_and_unwraps_document_write_response(): void {
		$this->queue_response( "document.write('99,00 kr');" );

		$this->assertSame( '99,00 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 1, $this->http_log );
		$this->assertStringContainsString( '/feeds/productsinfo.php?pid=1&get=price&billingcycle=monthly', $this->http_log[0] );
	}

	public function test_unwraps_multiline_document_write(): void {
		$this->queue_response( "document.write('<p>Line one\nLine two</p>');" );

		$this->assertSame( "<p>Line one\nLine two</p>", WHMCS_Price_API::get_product_data( 1, 'monthly', 'description' ) );
	}

	public function test_plain_body_is_returned_trimmed(): void {
		$this->queue_response( "  Web Hosting  \n" );

		$this->assertSame( 'Web Hosting', WHMCS_Price_API::get_product_data( 1, 'monthly', 'name' ) );
	}

	public function test_empty_body_returns_na(): void {
		$this->queue_response( '' );

		$this->assertSame( 'NA', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_second_call_is_served_from_persistent_cache(): void {
		$this->queue_response( "document.write('99 kr');" );
		WHMCS_Price_API::get_product_data( 1, 'annually', 'price' );

		// Simulate a new PHP request: in-memory cache gone, transients kept.
		$this->reset_api_state();

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'annually', 'price' ) );
		$this->assertCount( 1, $this->http_log, 'Cached value must not trigger a second HTTP request.' );
	}

	public function test_cache_entry_is_stored_under_versioned_key(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 7, 'monthly', 'price' );

		$expected_key = 'v1_whmcs_product_' . md5( '7_monthly_price' );
		$this->assertSame( '99 kr', $this->transients[ $expected_key ] ?? null );
		$this->assertSame( '99 kr', $this->object['whmcs_price'][ $expected_key ] ?? null );
	}

	public function test_cache_key_matches_rest_cache_hit_helper(): void {
		$this->assertFalse( whmcs_price_rest_product_cache_hit( 3, 'monthly', 'price' ) );

		$this->queue_response( '10 kr' );
		WHMCS_Price_API::get_product_data( 3, 'monthly', 'price' );

		$this->assertTrue( whmcs_price_rest_product_cache_hit( 3, 'monthly', 'price' ) );
	}

	public function test_bump_cache_version_invalidates_all_entries(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertSame( 2, WHMCS_Price_API::bump_cache_version() );
		$this->assertSame( 2, $this->options['whmcs_price_cache_version'] );

		$this->queue_response( '109 kr' );
		$this->assertSame( '109 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
	}

	public function test_invalid_cache_version_option_falls_back_to_one(): void {
		$this->options['whmcs_price_cache_version'] = 0;
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertArrayHasKey( 'v1_whmcs_product_' . md5( '1_monthly_price' ), $this->transients );
	}

	/**
	 * @dataProvider invalid_arguments
	 */
	public function test_invalid_arguments_return_na_without_http( $pid, string $cycle, string $attribute ): void {
		$this->assertSame( 'NA', WHMCS_Price_API::get_product_data( $pid, $cycle, $attribute ) );
		$this->assertSame( array(), $this->http_log );
	}

	public static function invalid_arguments(): array {
		return array(
			'unknown attribute'      => array( 1, 'monthly', 'setupfee' ),
			'injected attribute'     => array( 1, 'monthly', 'price&evil=1' ),
			'unknown billing cycle'  => array( 1, 'weekly', 'price' ),
			'uppercase cycle'        => array( 1, 'Monthly', 'price' ),
		);
	}

	public function test_pid_is_cast_to_int_in_request(): void {
		$this->queue_response( '1 kr' );
		WHMCS_Price_API::get_product_data( '5abc', 'monthly', 'price' );

		$this->assertStringContainsString( 'pid=5&', $this->http_log[0] );
	}

	public function test_unconfigured_url_returns_na(): void {
		$this->options['whmcs_price_option'] = array();

		$this->assertSame( 'NA', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertSame( array(), $this->http_log );
	}

	public function test_wp_error_returns_na_releases_lock_and_notifies(): void {
		$this->queue_error();

		$this->assertSame( 'NA', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertArrayNotHasKey( 'lock_whmcs_product_' . md5( '1_monthly_price' ), $this->options );
		$this->assertCount( 1, $this->mail_log, 'Outage e-mail should be sent once.' );
		$this->assertSame( 1, $this->transients['whmcs_price_outage_notified'] ?? null );
	}

	public function test_outage_notification_is_sent_only_once_per_window(): void {
		$this->queue_error();
		$this->queue_error();

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		WHMCS_Price_API::get_product_data( 2, 'monthly', 'price' );

		$this->assertCount( 1, $this->mail_log );
	}

	public function test_outage_notification_respects_opt_out(): void {
		$this->set_plugin_option( 'outage_notify', '0' );
		$this->queue_error();

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertSame( array(), $this->mail_log );
	}

	public function test_http_error_is_not_cached(): void {
		$this->queue_response( 'Internal Server Error', 500 );
		$this->assertSame( 'NA', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );

		$this->queue_response( '99 kr' );
		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
	}

	public function test_successful_response_clears_outage_flag(): void {
		$this->transients['whmcs_price_outage_notified'] = 1;
		$this->queue_response( '99 kr' );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertArrayNotHasKey( 'whmcs_price_outage_notified', $this->transients );
	}

	public function test_lock_is_released_after_success(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertArrayNotHasKey( 'lock_whmcs_product_' . md5( '1_monthly_price' ), $this->options );
	}

	public function test_product_data_filter_is_applied_on_fresh_fetch(): void {
		Filters\expectApplied( 'whmcs_price_product_data' )
			->once()
			->with( '99 kr', 1, 'monthly', 'price' )
			->andReturn( '99 kr incl. VAT' );

		$this->queue_response( '99 kr' );

		$this->assertSame( '99 kr incl. VAT', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_product_data_filter_is_applied_on_cached_reads(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		$this->reset_api_state();

		Filters\expectApplied( 'whmcs_price_product_data' )
			->once()
			->with( '99 kr', 1, 'monthly', 'price' )
			->andReturn( 'filtered' );

		$this->assertSame( 'filtered', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
	}

	public function test_filtered_value_is_not_written_to_persistent_cache(): void {
		Filters\expectApplied( 'whmcs_price_product_data' )->andReturn( 'filtered' );
		$this->queue_response( '99 kr' );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertSame( '99 kr', $this->transients[ 'v1_whmcs_product_' . md5( '1_monthly_price' ) ] );
	}

	public function test_uses_object_cache_lock_when_persistent_cache_is_available(): void {
		$this->ext_object_cache = true;
		$this->queue_response( '99 kr' );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertArrayNotHasKey( 'lock_whmcs_product_' . md5( '1_monthly_price' ), $this->options );
		$this->assertArrayNotHasKey( 'lock_whmcs_product_' . md5( '1_monthly_price' ), $this->object['whmcs_price_locks'] ?? array() );
	}
}
