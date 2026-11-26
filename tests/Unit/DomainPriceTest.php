<?php
/**
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class DomainPriceTest extends TestCase {

	public function test_fetches_domain_price_with_leading_dot_in_request(): void {
		$this->queue_response( "document.write('239 kr');" );

		$this->assertSame( '239 kr', WHMCS_Price_API::get_domain_price( 'se', 'register', '1' ) );
		$this->assertStringContainsString( '/feeds/domainprice.php?tld=.se&type=register&regperiod=1&format=1', $this->http_log[0] );
	}

	/**
	 * @dataProvider tld_inputs
	 */
	public function test_tld_is_sanitized( string $input, string $expected_in_url ): void {
		$this->queue_response( '100 kr' );
		WHMCS_Price_API::get_domain_price( $input, 'register', 1 );

		$this->assertStringContainsString( 'tld=' . rawurlencode( '.' . $expected_in_url ) . '&', $this->http_log[0] );
	}

	public static function tld_inputs(): array {
		return array(
			'leading dot'        => array( '.com', 'com' ),
			'second level'       => array( 'co-uk', 'co-uk' ),
			'strips punctuation' => array( 'se/../x', 'sex' ),
			'strips query chars' => array( 'com&type=renew', 'comtyperenew' ),
			'max 24 chars'       => array( str_repeat( 'a', 40 ), str_repeat( 'a', 24 ) ),
		);
	}

	public function test_tld_that_sanitizes_to_empty_returns_na(): void {
		$this->assertSame( 'NA', WHMCS_Price_API::get_domain_price( '...', 'register', 1 ) );
		$this->assertSame( array(), $this->http_log );
	}

	/**
	 * @dataProvider invalid_arguments
	 */
	public function test_invalid_arguments_return_na_without_http( string $type, $period ): void {
		$this->assertSame( 'NA', WHMCS_Price_API::get_domain_price( 'se', $type, $period ) );
		$this->assertSame( array(), $this->http_log );
	}

	public static function invalid_arguments(): array {
		return array(
			'unknown type'   => array( 'restore', 1 ),
			'period zero'    => array( 'register', 0 ),
			'period eleven'  => array( 'register', 11 ),
			'period garbage' => array( 'register', 'abc' ),
		);
	}

	public function test_cache_key_matches_rest_cache_hit_helper(): void {
		$this->queue_response( '239 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'renew', 2 );

		$this->assertTrue( whmcs_price_rest_domain_cache_hit( 'se', 'renew', 2 ) );
		$this->assertFalse( whmcs_price_rest_domain_cache_hit( 'se', 'register', 2 ) );
	}

	public function test_cached_read_does_not_hit_http(): void {
		$this->queue_response( '239 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );
		$this->reset_api_state();

		$this->assertSame( '239 kr', WHMCS_Price_API::get_domain_price( 'se', 'register', 1 ) );
		$this->assertCount( 1, $this->http_log );
	}

	public function test_domain_filter_is_applied_on_cached_reads(): void {
		$this->queue_response( '239 kr' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );
		$this->reset_api_state();

		Filters\expectApplied( 'whmcs_price_domain_price' )
			->once()
			->with( '239 kr', 'se', 'register', 1 )
			->andReturn( '299 kr' );

		$this->assertSame( '299 kr', WHMCS_Price_API::get_domain_price( 'se', 'register', 1 ) );
	}

	public function test_all_domain_prices_are_cached(): void {
		$this->queue_response( "document.write('<table><tr><td>.se</td></tr></table>');" );

		$this->assertSame( '<table><tr><td>.se</td></tr></table>', WHMCS_Price_API::get_all_domain_prices() );
		$this->reset_api_state();
		WHMCS_Price_API::get_all_domain_prices();

		$this->assertCount( 1, $this->http_log );
		$this->assertStringEndsWith( '/feeds/domainpricing.php', $this->http_log[0] );
	}

	public function test_all_domain_prices_http_error_returns_na(): void {
		$this->queue_response( 'Bad Gateway', 502 );

		$this->assertSame( 'NA', WHMCS_Price_API::get_all_domain_prices() );
		$this->assertArrayNotHasKey( 'v1_whmcs_domain_all', $this->transients );
	}
}
