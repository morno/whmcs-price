<?php
/**
 * Abilities API integration.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WP_Error;

final class AbilitiesTest extends TestCase {

	/** @var array<string, array> Abilities captured from wp_register_ability(). */
	private array $registered = array();

	protected function setUp(): void {
		parent::setUp();

		Functions\when( 'add_action' )->justReturn( true );
		require_once dirname( __DIR__, 2 ) . '/includes/abilities.php';

		$this->registered = array();
		$t                = $this;
		Functions\when( 'wp_register_ability' )->alias(
			static function ( string $name, array $args ) use ( $t ) {
				$t->registered[ $name ] = $args;
				return null;
			}
		);
		Functions\when( 'wp_register_ability_category' )->justReturn( null );
	}

	public function test_registers_both_abilities_in_plugin_category(): void {
		whmcs_price_register_abilities();

		$this->assertSame(
			array( 'whmcs-price/get-product-price', 'whmcs-price/get-domain-price' ),
			array_keys( $this->registered )
		);
		foreach ( $this->registered as $args ) {
			$this->assertSame( 'whmcs-price', $args['category'] );
			$this->assertTrue( $args['meta']['show_in_rest'] );
			$this->assertTrue( $args['meta']['annotations']['readonly'] );
			$this->assertIsCallable( $args['execute_callback'] );
			$this->assertIsCallable( $args['permission_callback'] );
			$this->assertSame( 'object', $args['input_schema']['type'] );
			$this->assertFalse( $args['input_schema']['additionalProperties'] );
		}
	}

	public function test_product_ability_returns_all_fields(): void {
		$this->queue_response( 'Web Hosting' );
		$this->queue_response( '<b>10 GB</b> SSD' );
		$this->queue_response( '99 kr + 100 kr Setup Fee' );
		$this->queue_response( '<option value="monthly">99 kr + 100 kr Setup Fee</option>' );

		$result = whmcs_price_ability_get_product_price( array( 'product_ids' => array( 1 ), 'billing_cycle' => 'monthly' ) );

		$this->assertSame(
			array(
				'billing_cycle' => 'monthly',
				'products'      => array(
					array(
						'product_id'  => 1,
						'available'   => true,
						'name'        => 'Web Hosting',
						'description' => '10 GB SSD',
						'price'       => '99 kr',
						'setup_fee'   => '100 kr',
					),
				),
			),
			$result
		);
	}

	public function test_product_ability_marks_unavailable_products(): void {
		$this->queue_error();
		$this->queue_error();
		$this->queue_error();

		$result = whmcs_price_ability_get_product_price( array( 'product_ids' => array( 5 ) ) );

		$this->assertSame( 'monthly', $result['billing_cycle'], 'Defaults to monthly.' );
		$this->assertFalse( $result['products'][0]['available'] );
		$this->assertNull( $result['products'][0]['price'] );
		$this->assertNull( $result['products'][0]['setup_fee'] );
		$this->assertCount( 3, $this->http_log, 'No setup-fee request when the price is unavailable.' );
	}

	public function test_product_ability_accepts_comma_string_and_dedupes(): void {
		foreach ( array( 1, 2 ) as $pid ) {
			$this->queue_response( "Plan $pid" );
			$this->queue_response( '' );
			$this->queue_response( '10 kr' );
			$this->queue_response( '<option value="annually">10 kr</option>' );
		}

		$result = whmcs_price_ability_get_product_price( array( 'product_ids' => '1,2,1', 'billing_cycle' => 'annually' ) );

		$this->assertSame( array( 1, 2 ), array_column( $result['products'], 'product_id' ) );
		$this->assertNull( $result['products'][0]['description'] );
		$this->assertNull( $result['products'][0]['setup_fee'] );
	}

	/**
	 * @dataProvider invalid_product_input
	 */
	public function test_product_ability_rejects_invalid_input( $input ): void {
		$result = whmcs_price_ability_get_product_price( $input );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'whmcs_price_invalid_input', $result->get_error_code() );
		$this->assertSame( array(), $this->http_log );
	}

	public static function invalid_product_input(): array {
		return array(
			'no input'        => array( null ),
			'empty ids'       => array( array( 'product_ids' => array() ) ),
			'negative id'     => array( array( 'product_ids' => array( -1 ) ) ),
			'too many ids'    => array( array( 'product_ids' => range( 1, 21 ) ) ),
			'unknown cycle'   => array( array( 'product_ids' => array( 1 ), 'billing_cycle' => 'weekly' ) ),
		);
	}

	public function test_domain_ability_returns_all_types_by_default(): void {
		$this->queue_response( '129 kr' );
		$this->queue_response( '149 kr' );
		$this->queue_error();

		$result = whmcs_price_ability_get_domain_price( array( 'tld' => '.SE' ) );

		$this->assertSame(
			array(
				'tld'        => 'se',
				'reg_period' => 1,
				'available'  => true,
				'prices'     => array(
					'register' => '129 kr',
					'renew'    => '149 kr',
					'transfer' => null,
				),
			),
			$result
		);
	}

	public function test_domain_ability_limits_types_and_period(): void {
		$this->queue_response( '299 kr' );

		$result = whmcs_price_ability_get_domain_price( array( 'tld' => 'com', 'reg_period' => 3, 'types' => array( 'renew' ) ) );

		$this->assertSame( array( 'renew' => '299 kr' ), $result['prices'] );
		$this->assertStringContainsString( 'type=renew&regperiod=3', $this->http_log[0] );
	}

	/**
	 * @dataProvider invalid_domain_input
	 */
	public function test_domain_ability_rejects_invalid_input( $input ): void {
		$result = whmcs_price_ability_get_domain_price( $input );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( array(), $this->http_log );
	}

	public static function invalid_domain_input(): array {
		return array(
			'no input'      => array( null ),
			'empty tld'     => array( array( 'tld' => '.' ) ),
			'period zero'   => array( array( 'tld' => 'se', 'reg_period' => 0 ) ),
			'period eleven' => array( array( 'tld' => 'se', 'reg_period' => 11 ) ),
			'unknown type'  => array( array( 'tld' => 'se', 'types' => array( 'restore' ) ) ),
		);
	}

	public function test_domain_ability_shares_cache_with_api(): void {
		$this->queue_response( '129 kr' );
		\WHMCS_Price_API::get_domain_price( 'se', 'register', 1 );

		$result = whmcs_price_ability_get_domain_price( array( 'tld' => 'se', 'types' => array( 'register' ) ) );

		$this->assertSame( '129 kr', $result['prices']['register'] );
		$this->assertCount( 1, $this->http_log );
	}
}
