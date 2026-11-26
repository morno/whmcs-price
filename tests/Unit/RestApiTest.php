<?php
/**
 * REST endpoint callbacks and the purge-token permission check.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RestApiTest extends TestCase {

	private const TOKEN = 'a-very-secret-purge-token-1234';

	protected function setUp(): void {
		parent::setUp();

		// rest-api.php registers its routes with add_action() at load time.
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'do_action' )->justReturn( null );
		require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';

		$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
	}

	public function test_purge_is_disabled_without_token(): void {
		$result = whmcs_price_rest_purge_permission( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'whmcs_price_purge_disabled', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
	}

	public function test_purge_rejects_wrong_token(): void {
		$this->set_plugin_option( 'purge_token', self::TOKEN );

		$result = whmcs_price_rest_purge_permission(
			new WP_REST_Request( array(), array( 'X-WHMCS-Price-Token' => 'wrong' ) )
		);

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 401, $result->get_error_data()['status'] );
	}

	public function test_purge_rejects_missing_header(): void {
		$this->set_plugin_option( 'purge_token', self::TOKEN );

		$result = whmcs_price_rest_purge_permission( new WP_REST_Request() );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'whmcs_price_purge_unauthorized', $result->get_error_code() );
	}

	public function test_purge_accepts_correct_token(): void {
		$this->set_plugin_option( 'purge_token', self::TOKEN );

		$result = whmcs_price_rest_purge_permission(
			new WP_REST_Request( array(), array( 'X-WHMCS-Price-Token' => self::TOKEN ) )
		);

		$this->assertTrue( $result );
	}

	public function test_purge_auth_is_rate_limited_after_repeated_failures(): void {
		$this->set_plugin_option( 'purge_token', self::TOKEN );
		$this->set_plugin_option( 'purge_auth_limit', 3 );
		$bad = new WP_REST_Request( array(), array( 'X-WHMCS-Price-Token' => 'wrong' ) );

		for ( $i = 0; $i < 3; $i++ ) {
			$this->assertSame( 401, whmcs_price_rest_purge_permission( $bad )->get_error_data()['status'] );
		}

		// Even the correct token is blocked while the window is active.
		$result = whmcs_price_rest_purge_permission(
			new WP_REST_Request( array(), array( 'X-WHMCS-Price-Token' => self::TOKEN ) )
		);
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}

	public function test_purge_bumps_cache_version_and_throttles_repeat(): void {
		$first = whmcs_price_rest_purge_cache( new WP_REST_Request( array( 'scope' => 'all' ) ) );

		$this->assertInstanceOf( WP_REST_Response::class, $first );
		$this->assertSame( 200, $first->get_status() );
		$this->assertTrue( $first->get_data()['purged'] );
		$this->assertSame( 2, $first->get_data()['cache_version'] );

		$second = whmcs_price_rest_purge_cache( new WP_REST_Request( array( 'scope' => 'all' ) ) );
		$this->assertSame( 429, $second->get_status() );
		$this->assertArrayHasKey( 'Retry-After', $second->headers );
		$this->assertSame( 2, $this->options['whmcs_price_cache_version'], 'Throttled purge must not bump the version.' );
	}

	public function test_product_endpoint_returns_price(): void {
		$this->queue_response( '99 kr' );

		$response = whmcs_price_rest_product(
			new WP_REST_Request( array( 'pid' => 1, 'billing_cycle' => 'monthly', 'attribute' => 'price' ) )
		);

		$this->assertInstanceOf( WP_REST_Response::class, $response );
		$this->assertSame( array( 'price' => '99 kr' ), $response->get_data() );
	}

	public function test_product_endpoint_returns_503_when_unavailable(): void {
		$this->queue_error();

		$response = whmcs_price_rest_product(
			new WP_REST_Request( array( 'pid' => 1, 'billing_cycle' => 'monthly', 'attribute' => 'price' ) )
		);

		$this->assertInstanceOf( WP_Error::class, $response );
		$this->assertSame( 503, $response->get_error_data()['status'] );
	}

	public function test_domain_endpoint_strips_leading_dot(): void {
		$this->queue_response( '239 kr' );

		$response = whmcs_price_rest_domain(
			new WP_REST_Request( array( 'tld' => '.se', 'type' => 'register', 'reg_period' => 1 ) )
		);

		$this->assertSame( array( 'price' => '239 kr' ), $response->get_data() );
	}

	public function test_read_rate_limit_is_off_by_default(): void {
		for ( $i = 0; $i < 100; $i++ ) {
			$this->assertTrue( whmcs_price_rest_read_rate_limit( 'product' ) );
		}
	}

	public function test_read_rate_limit_blocks_after_limit(): void {
		$this->set_plugin_option( 'rest_rate_enabled', '1' );
		$this->set_plugin_option( 'rest_rate_limit', 2 );

		$this->assertTrue( whmcs_price_rest_read_rate_limit( 'product' ) );
		$this->assertTrue( whmcs_price_rest_read_rate_limit( 'product' ) );
		$result = whmcs_price_rest_read_rate_limit( 'product' );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 429, $result->get_error_data()['status'] );
	}

	public function test_read_rate_limit_miss_only_skips_cache_hits(): void {
		$this->set_plugin_option( 'rest_rate_enabled', '1' );
		$this->set_plugin_option( 'rest_rate_limit', 1 );
		$this->set_plugin_option( 'rest_rate_miss_only', '1' );

		for ( $i = 0; $i < 5; $i++ ) {
			$this->assertTrue( whmcs_price_rest_read_rate_limit( 'product', true ) );
		}
	}
}
