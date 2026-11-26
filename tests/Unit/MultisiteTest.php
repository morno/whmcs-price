<?php
/**
 * Multisite isolation (#18).
 *
 * Simulates a network the way WordPress stores data: each site has its own
 * options table (options + transients) and the object cache prefixes
 * non-global groups with the blog ID. switch_to_blog() fires 'switch_blog'.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class MultisiteTest extends TestCase {

	private int $blog = 1;

	/** @var int[] */
	private array $blog_stack = array();

	/** @var array<int, array<string, mixed>> Options (incl. transients) per blog. */
	private array $site_options = array();

	/** @var array<string, mixed> Object cache, keys prefixed with blog ID like WP core. */
	private array $site_object = array();

	protected function setUp(): void {
		parent::setUp();

		$this->blog         = 1;
		$this->blog_stack   = array();
		$this->site_options = array(
			1 => array( 'whmcs_price_option' => array( 'whmcs_url' => 'https://93.184.216.34/site-one' ) ),
			2 => array( 'whmcs_price_option' => array( 'whmcs_url' => 'https://93.184.216.35/site-two' ) ),
		);
		$this->site_object  = array();

		$t = $this;

		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'get_current_blog_id' )->alias( static fn() => $t->blog );
		Functions\when( 'switch_to_blog' )->alias(
			static function ( $id ) use ( $t ) {
				$t->blog_stack[] = $t->blog;
				$t->blog         = (int) $id;
				WHMCS_Price_API::reset_request_state(); // What the 'switch_blog' hook does.
				return true;
			}
		);
		Functions\when( 'restore_current_blog' )->alias(
			static function () use ( $t ) {
				$t->blog = (int) array_pop( $t->blog_stack );
				WHMCS_Price_API::reset_request_state();
				return true;
			}
		);

		// Per-site options.
		Functions\when( 'get_option' )->alias(
			static fn( $name, $default = false ) => array_key_exists( $name, $t->site_options[ $t->blog ] ?? array() ) ? $t->site_options[ $t->blog ][ $name ] : $default
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( $t ) {
				$t->site_options[ $t->blog ][ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value = '' ) use ( $t ) {
				if ( array_key_exists( $name, $t->site_options[ $t->blog ] ?? array() ) ) {
					return false;
				}
				$t->site_options[ $t->blog ][ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( $t ) {
				unset( $t->site_options[ $t->blog ][ $name ] );
				return true;
			}
		);

		// Per-site transients (stored in the site's options table).
		Functions\when( 'get_transient' )->alias(
			static fn( $key ) => $t->site_options[ $t->blog ][ '_transient_' . $key ] ?? false
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value ) use ( $t ) {
				$t->site_options[ $t->blog ][ '_transient_' . $key ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( $key ) use ( $t ) {
				unset( $t->site_options[ $t->blog ][ '_transient_' . $key ] );
				return true;
			}
		);

		// Object cache: non-global groups are blog-prefixed, as in WP_Object_Cache.
		$ok = static fn( $key, $group ) => $t->blog . ':' . $group . ':' . $key;
		Functions\when( 'wp_cache_get' )->alias( static fn( $key, $group = '' ) => $t->site_object[ $ok( $key, $group ) ] ?? false );
		Functions\when( 'wp_cache_set' )->alias(
			static function ( $key, $value, $group = '' ) use ( $t, $ok ) {
				$t->site_object[ $ok( $key, $group ) ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->alias(
			static function ( $key, $group = '' ) use ( $t, $ok ) {
				unset( $t->site_object[ $ok( $key, $group ) ] );
				return true;
			}
		);
	}

	public function test_same_product_on_two_sites_uses_each_sites_whmcs(): void {
		$this->queue_response( '99 kr' );
		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );

		switch_to_blog( 2 );
		$this->queue_response( '€9' );
		$this->assertSame( '€9', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		restore_current_blog();

		$this->assertStringContainsString( '/site-one/', $this->http_log[0] );
		$this->assertStringContainsString( '/site-two/', $this->http_log[1] );

		// Back on site 1: its own cached value, no request, nothing leaked from site 2.
		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
	}

	public function test_all_domains_table_is_per_site(): void {
		$this->queue_response( '<table>one</table>' );
		WHMCS_Price_API::get_all_domain_prices();

		switch_to_blog( 2 );
		$this->queue_response( '<table>two</table>' );
		$this->assertSame( '<table>two</table>', WHMCS_Price_API::get_all_domain_prices() );
		restore_current_blog();

		$this->assertSame( '<table>one</table>', WHMCS_Price_API::get_all_domain_prices() );
	}

	public function test_cache_version_is_per_site(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		switch_to_blog( 2 );
		WHMCS_Price_API::bump_cache_version();
		$this->assertSame( 2, $this->site_options[2]['whmcs_price_cache_version'] );
		restore_current_blog();

		// Purging site 2 must not invalidate site 1.
		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ) );
		$this->assertCount( 1, $this->http_log );
		$this->assertArrayNotHasKey( 'whmcs_price_cache_version', $this->site_options[1] );
	}

	public function test_request_cache_does_not_leak_across_switch(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		switch_to_blog( 2 );
		$this->queue_response( '€9' );
		$value = WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		restore_current_blog();

		$this->assertSame( '€9', $value, 'Site 2 must not get site 1 value from the in-memory cache.' );
	}

	public function test_registry_entries_are_written_to_the_site_that_used_them(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		switch_to_blog( 2 );
		$this->queue_response( '129 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );
		restore_current_blog();

		// Shutdown runs on the original site.
		WHMCS_Price_API::flush_registry();

		$this->assertSame( array( 'product' ), array_column( $this->site_options[1][ WHMCS_Price_API::REGISTRY_OPTION ], 'k' ) );
		$this->assertSame( array( 'domain' ), array_column( $this->site_options[2][ WHMCS_Price_API::REGISTRY_OPTION ], 'k' ) );
		$this->assertSame( 1, $this->blog, 'Back on the original site after flushing.' );
	}

	public function test_stale_stats_are_written_to_the_site_that_served_them(): void {
		switch_to_blog( 2 );
		$this->queue_response( '€9' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );
		$key = 'v1_whmcs_product_' . md5( '1_monthly_price' );
		$this->site_options[2][ '_transient_' . $key ]['t'] -= 3700;
		$this->site_options[2][ '_transient_' . $key ]['e'] -= 3700;
		unset( $this->site_object[ '2:whmcs_price:' . $key ] );
		WHMCS_Price_API::reset_request_state();

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' ); // Served stale on site 2.
		restore_current_blog();

		WHMCS_Price_API::flush_stale_stats();

		$this->assertSame( 1, $this->site_options[2]['whmcs_price_stale_stats']['served'] ?? null );
		$this->assertArrayNotHasKey( 'whmcs_price_stale_stats', $this->site_options[1] );
	}

	public function test_invalidation_only_affects_current_site(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 5, 'monthly', 'price' );
		switch_to_blog( 2 );
		$this->queue_response( '€9' );
		WHMCS_Price_API::get_product_data( 5, 'monthly', 'price' );

		$this->assertSame( 1, WHMCS_Price_API::invalidate_product( 5 ) );
		restore_current_blog();

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 5, 'monthly', 'price' ) );
		$this->assertCount( 2, $this->http_log );
	}
}
