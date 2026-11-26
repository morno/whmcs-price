<?php
/**
 * Pure parsing/formatting helpers: divide_price, setup fees, billing cycles.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class PriceParsingTest extends TestCase {

	/**
	 * @dataProvider divide_cases
	 */
	public function test_divide_price( string $input, float $divisor, string $expected ): void {
		$this->assertSame( $expected, WHMCS_Price_API::divide_price( $input, $divisor ) );
	}

	public static function divide_cases(): array {
		return array(
			'usd prefix'              => array( '$99.00', 12, '$8.25' ),
			'euro prefix'             => array( '€12.50', 2, '€6.25' ),
			'sek suffix period'       => array( '99.00 kr', 12, '8.25 kr' ),
			'sek suffix comma'        => array( '9,99 kr', 3, '3,33 kr' ),
			'sek prefix'              => array( 'kr 99.00', 12, 'kr 8.25' ),
			'integer'                 => array( '959 kr', 12, '79.92 kr' ),
			'en thousands + decimal'  => array( '1,499.50', 2, '749.75' ),
			'eu thousands + decimal'  => array( '1.499,50 kr', 2, '749,75 kr' ),
			'space thousands'         => array( '1 200 kr', 12, '100.00 kr' ),
			'single sep = thousands'  => array( '1.200 kr', 12, '100.00 kr' ),
			'divisor one is no-op'    => array( '99 kr', 1, '99 kr' ),
			'NA passthrough'          => array( 'NA', 12, 'NA' ),
			'no number passthrough'   => array( 'Free', 12, 'Free' ),
		);
	}

	/**
	 * @dataProvider setup_fee_text_cases
	 */
	public function test_parse_setup_fee_from_text( string $text, string $expected ): void {
		$this->assertSame( $expected, $this->call_private( 'parse_setup_fee_from_text', $text ) );
	}

	public static function setup_fee_text_cases(): array {
		return array(
			'prefix dollar'     => array( '12 months - $8.25/mo + $10.00 Setup Fee', '$ 10.00' ),
			'suffix kr'         => array( '99 kr + 100 kr Setup Fee', '100 kr' ),
			'suffix comma kr'   => array( '99,00 kr + 49,50 kr', '49,50 kr' ),
			'code prefix'       => array( 'SEK 99 + SEK 25 Setup', 'SEK 25' ),
			'bare number'       => array( '99 + 25', '25' ),
			'no separator'      => array( '99 kr per month', '' ),
			'separator no fee'  => array( '99 kr + Setup Fee', '' ),
		);
	}

	public function test_setup_fee_from_productpricing_feed(): void {
		$html = '<select name="billingcycle">'
			. '<option value="monthly">1 Month - 99 kr</option>'
			. '<option value="annually">12 Months - 999 kr + 100 kr Setup Fee</option>'
			. '</select>';
		$this->queue_response( "document.write('" . $html . "');" );

		$this->assertSame( '100 kr', WHMCS_Price_API::get_product_setup_fee( 4, 'annually' ) );
		$this->assertSame( '', WHMCS_Price_API::get_product_setup_fee( 4, 'monthly' ) );
		$this->assertCount( 1, $this->http_log, 'All billing cycles share one cached pricing feed.' );
	}

	public function test_setup_fee_for_onetime_product_without_select(): void {
		$this->queue_response( '499 kr + 50 kr Setup Fee' );

		$this->assertSame( '50 kr', WHMCS_Price_API::get_product_setup_fee( 9, 'monthly' ) );
	}

	public function test_setup_fee_invalid_cycle_returns_empty_without_http(): void {
		$this->assertSame( '', WHMCS_Price_API::get_product_setup_fee( 4, 'weekly' ) );
		$this->assertSame( array(), $this->http_log );
	}

	public function test_get_all_product_cycles_parses_and_skips_zero_prices(): void {
		$html = '<select>'
			. '<option value="monthly">99 kr</option>'
			. '<option value="quarterly">0,00 kr</option>'
			. '<option value="annually" selected>999 kr + 100 kr</option>'
			. '<option value="onetime">5 kr</option>'
			. '</select>';
		$this->queue_response( $html );

		$this->assertSame(
			array(
				'monthly'  => '99 kr',
				'annually' => '999 kr',
			),
			WHMCS_Price_API::get_all_product_cycles( 4 )
		);
	}

	public function test_get_all_product_cycles_shares_cache_with_setup_fee(): void {
		$this->queue_response( '<option value="monthly">99 kr + 10 kr</option>' );

		WHMCS_Price_API::get_product_setup_fee( 4, 'monthly' );
		WHMCS_Price_API::get_all_product_cycles( 4 );

		$this->assertCount( 1, $this->http_log );
	}

	public function test_get_all_product_cycles_rejects_invalid_pid(): void {
		$this->assertSame( array(), WHMCS_Price_API::get_all_product_cycles( 0 ) );
		$this->assertSame( array(), $this->http_log );
	}

	/**
	 * @dataProvider cycle_months
	 */
	public function test_billing_cycle_months( string $cycle, int $months ): void {
		$this->assertSame( $months, WHMCS_Price_API::billing_cycle_months( $cycle ) );
	}

	public static function cycle_months(): array {
		return array(
			array( 'monthly', 1 ),
			array( 'quarterly', 3 ),
			array( 'semiannually', 6 ),
			array( 'annually', 12 ),
			array( 'biennially', 24 ),
			array( 'triennially', 36 ),
			array( 'unknown', 1 ),
		);
	}
}
