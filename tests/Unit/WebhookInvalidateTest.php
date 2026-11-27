<?php
/**
 * Targeted cache invalidation and the WHMCS webhook endpoint (#17).
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class WebhookInvalidateTest extends TestCase {

	private int $page_cache_flushes = 0;

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'add_action' )->justReturn( true );
		require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';
		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';

		$this->page_cache_flushes = 0;
		$t                        = $this;
		// whmcs_price_flush_page_cache() fires actions for cache plugins.
		Functions\when( 'do_action' )->alias(
			static function ( $hook ) use ( $t ) {
				if ( 'litespeed_purge_all' === $hook ) {
					++$t->page_cache_flushes;
				}
			}
		);
	}

	private function cache_product( int $pid, string $cycle = 'monthly', string $attr = 'price', string $value = '99 kr' ): void {
		$this->queue_response( $value );
		WHMCS_Price_API::get_product_data( $pid, $cycle, $attr );
	}

	private function webhook( string $scope ): WP_REST_Response|WP_Error {
		return whmcs_price_rest_webhook_invalidate( new WP_REST_Request( array( 'scope' => $scope ) ) );
	}

	// ------------------------------------------------------------------
	// WHMCS_Price_API::invalidate_*()
	// ------------------------------------------------------------------

	public function test_invalidate_product_removes_all_cycles_attributes_and_feed(): void {
		$this->cache_product( 5, 'monthly', 'price' );
		$this->cache_product( 5, 'annually', 'name', 'Hosting' );
		$this->queue_response( '<option value="monthly">99 kr + 10 kr</option>' );
		WHMCS_Price_API::get_product_setup_fee( 5, 'monthly' );
		$this->cache_product( 6 );

		$this->assertSame( 3, WHMCS_Price_API::invalidate_product( 5 ) );

		$this->assertNull( $this->cached_value( 'whmcs_product_' . md5( '5_monthly_price' ) ) );
		$this->assertNull( $this->cached_value( 'whmcs_product_' . md5( '5_annually_name' ) ) );
		$this->assertNull( $this->cached_value( 'whmcs_pricefeed_' . md5( '5' ) ) );
		$this->assertSame( '99 kr', $this->cached_value( 'whmcs_product_' . md5( '6_monthly_price' ) ), 'Other products untouched.' );
	}

	public function test_invalidate_product_also_removes_stale_copies(): void {
		$this->cache_product( 5 );
		$this->age_entry( 'whmcs_product_' . md5( '5_monthly_price' ), 3600 + 60 );
		$this->reset_api_state();

		WHMCS_Price_API::invalidate_product( 5 );
		$this->queue_response( '149 kr' );

		$this->assertSame( '149 kr', WHMCS_Price_API::get_product_data( 5, 'monthly', 'price' ), 'Old price must not be served stale.' );
	}

	public function test_invalidate_product_uses_registry_for_non_normalised_keys(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( '05', 'monthly', 'price' );
		WHMCS_Price_API::flush_registry();

		$this->assertSame( 1, WHMCS_Price_API::invalidate_product( 5 ) );
		$this->assertNull( $this->cached_value( 'whmcs_product_' . md5( '05_monthly_price' ) ) );
	}

	public function test_invalidate_product_rewarms_entries_visitors_use(): void {
		$this->cache_product( 5 );
		WHMCS_Price_API::flush_registry();

		WHMCS_Price_API::invalidate_product( 5 );

		$this->assertCount( 1, $this->scheduled );
		$this->assertSame( array( 'product', array( 5, 'monthly', 'price', 0 ) ), $this->scheduled[0][2] );
	}

	public function test_invalidate_product_clears_request_cache(): void {
		$this->cache_product( 5 );

		WHMCS_Price_API::invalidate_product( 5 );
		$this->queue_response( '149 kr' );

		$this->assertSame( '149 kr', WHMCS_Price_API::get_product_data( 5, 'monthly', 'price' ) );
	}

	public function test_invalidate_product_with_nothing_cached_returns_zero(): void {
		$this->assertSame( 0, WHMCS_Price_API::invalidate_product( 5 ) );
		$this->assertSame( 0, WHMCS_Price_API::invalidate_product( 0 ) );
		$this->assertSame( array(), $this->scheduled );
	}

	public function test_invalidate_domain_removes_all_types_periods_and_table(): void {
		foreach ( array( array( 'register', 1 ), array( 'renew', 3 ) ) as $case ) {
			$this->queue_response( '129 kr' );
			WHMCS_Price_API::get_domain_price( 'se', $case[0], $case[1] );
		}
		$this->queue_response( '139 kr' );
		WHMCS_Price_API::get_domain_price( 'com', 'register', 1 );
		$this->queue_response( '<table></table>' );
		WHMCS_Price_API::get_all_domain_prices();

		$this->assertSame( 3, WHMCS_Price_API::invalidate_domain( '.se' ) );

		$this->assertNull( $this->cached_value( 'whmcs_domain_' . md5( 'se_register_1' ) ) );
		$this->assertNull( $this->cached_value( 'whmcs_domain_' . md5( 'se_renew_3' ) ) );
		$this->assertNull( $this->cached_value( 'whmcs_domain_all' ) );
		$this->assertSame( '139 kr', $this->cached_value( 'whmcs_domain_' . md5( 'com_register_1' ) ) );
	}

	public function test_invalidate_domain_is_case_insensitive(): void {
		$this->queue_response( '129 kr' );
		WHMCS_Price_API::get_domain_price( 'SE', 'register', 1 );

		$this->assertSame( 1, WHMCS_Price_API::invalidate_domain( 'se' ) );
	}

	public function test_invalidate_domain_rejects_empty_tld(): void {
		$this->assertSame( 0, WHMCS_Price_API::invalidate_domain( '..' ) );
	}

	// ------------------------------------------------------------------
	// REST endpoint
	// ------------------------------------------------------------------

	public function test_webhook_product_scope(): void {
		$this->cache_product( 5 );

		$response = $this->webhook( 'product:5' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame(
			array(
				'invalidated' => true,
				'scope'       => 'product:5',
				'entries'     => 1,
				'message'     => 'Cache invalidated.',
			),
			$response->get_data()
		);
		$this->assertSame( 1, $this->page_cache_flushes );
	}

	public function test_webhook_multiple_targets_are_deduplicated(): void {
		$this->cache_product( 5 );
		$this->queue_response( '129 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );

		$response = $this->webhook( 'product:5, domain:.SE ,product:5' );

		$this->assertSame( 'product:5,domain:se', $response->get_data()['scope'] );
		$this->assertSame( 2, $response->get_data()['entries'] );
	}

	public function test_webhook_does_not_flush_page_cache_when_nothing_changed(): void {
		$response = $this->webhook( 'product:5' );

		$this->assertSame( 0, $response->get_data()['entries'] );
		$this->assertSame( 0, $this->page_cache_flushes );
	}

	public function test_webhook_all_bumps_cache_version(): void {
		$response = $this->webhook( 'all' );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'all', $response->get_data()['scope'] );
		$this->assertSame( 2, $response->get_data()['cache_version'] );
	}

	public function test_webhook_empty_scope_means_full_purge(): void {
		$this->assertSame( 2, $this->webhook( '' )->get_data()['cache_version'] );
	}

	public function test_webhook_full_purge_shares_cooldown_with_purge_cache(): void {
		whmcs_price_rest_purge_cache( new WP_REST_Request() );

		$response = $this->webhook( 'all' );

		$this->assertSame( 429, $response->get_status() );
		$this->assertArrayHasKey( 'Retry-After', $response->headers );
	}

	public function test_targeted_invalidation_ignores_full_purge_cooldown(): void {
		whmcs_price_rest_purge_cache( new WP_REST_Request() );
		$this->cache_product( 5 );

		$response = $this->webhook( 'product:5' );

		$this->assertSame( 200, $response->get_status() );
	}

	public function test_targeted_invalidation_has_its_own_rate_limit(): void {
		Filters\expectApplied( 'whmcs_price_webhook_rate_limit' )->andReturn( 2 );

		$this->assertSame( 200, $this->webhook( 'product:1' )->get_status() );
		$this->assertSame( 200, $this->webhook( 'product:2' )->get_status() );
		$third = $this->webhook( 'product:3' );

		$this->assertSame( 429, $third->get_status() );
		$this->assertArrayHasKey( 'Retry-After', $third->headers );
	}

	/**
	 * @dataProvider invalid_scopes
	 */
	public function test_webhook_rejects_invalid_scope( string $scope ): void {
		$response = $this->webhook( $scope );

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 'whmcs_price_invalid_scope', $response->get_error_code() );
		$this->assertSame( 400, $response->get_error_data()['status'] );
	}

	public static function invalid_scopes(): array {
		return array(
			'unknown kind'    => array( 'addon:5' ),
			'product no id'   => array( 'product:' ),
			'product zero'    => array( 'product:0' ),
			'product text'    => array( 'product:abc' ),
			'domain bad chars' => array( 'domain:se/../x' ),
			'bare word'       => array( 'everything' ),
			'too many'        => array( implode( ',', array_map( static fn( $i ) => "product:$i", range( 1, 51 ) ) ) ),
		);
	}

	public function test_webhook_uses_purge_token_permission(): void {
		// Same permission callback as /purge-cache, covered in RestApiTest.
		$this->assertInstanceOf( WP_Error::class, whmcs_price_rest_purge_permission( new WP_REST_Request() ) );
	}
}
