<?php
/**
 * Page builder widgets must match the Gutenberg blocks 1:1.
 *
 * Every attribute in block.json (except the inserter-only "preview") needs a
 * builder control, and a freshly added widget must produce the same
 * attributes as a freshly inserted block. Rendering goes through the block
 * itself (whmcs_price_render_block), so matching attributes means matching
 * output.
 *
 * When adding a block attribute: add the control to every builder widget in
 * builder_widgets() below, or this test fails.
 *
 * @package WHMCS_Price\Tests
 */

declare( strict_types=1 );

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;

final class BuilderParityTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		require_once dirname( __DIR__ ) . '/stubs/elementor.php';
		require_once dirname( __DIR__, 2 ) . '/includes/builders.php';
		require_once dirname( __DIR__, 2 ) . '/includes/elementor/widgets/product-price-widget.php';
		require_once dirname( __DIR__, 2 ) . '/includes/elementor/widgets/domain-price-widget.php';
		Functions\when( '_n' )->alias( static fn( $s, $p, $n ) => 1 === $n ? $s : $p );
	}

	/**
	 * Builder widgets per block. Add future builders here.
	 */
	public static function builder_widgets(): array {
		return array(
			'Elementor product' => array( 'product', \WHMCS_Price_Elementor_Product_Widget::class ),
			'Elementor domain'  => array( 'domain', \WHMCS_Price_Elementor_Domain_Widget::class ),
		);
	}

	private function block_attributes( string $block ): array {
		$json = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . "/blocks/whmcs-price-{$block}/block.json" ), true );
		$attributes = $json['attributes'];
		unset( $attributes['preview'] );
		return $attributes;
	}

	/**
	 * @dataProvider builder_widgets
	 */
	public function test_every_block_attribute_is_mapped( string $block, string $class ): void {
		$widget = new $class();
		$mapped = $widget->to_block_attributes( $widget->stub_defaults() );

		$this->assertEqualsCanonicalizing(
			array_keys( $this->block_attributes( $block ) ),
			array_keys( $mapped ),
			"{$class} must map every {$block} block attribute (and nothing else)."
		);
	}

	/**
	 * @dataProvider builder_widgets
	 */
	public function test_widget_defaults_equal_block_defaults( string $block, string $class ): void {
		$widget = new $class();
		$mapped = $widget->to_block_attributes( $widget->stub_defaults() );

		foreach ( $this->block_attributes( $block ) as $name => $schema ) {
			$this->assertSame( $schema['default'] ?? null, $mapped[ $name ], "Default of {$name} differs from block.json." );
		}
	}

	/**
	 * @dataProvider builder_widgets
	 */
	public function test_mapped_types_match_block_schema( string $block, string $class ): void {
		$widget = new $class();
		$types  = array(
			'string'  => 'is_string',
			'boolean' => 'is_bool',
			'array'   => 'is_array',
		);
		foreach ( $widget->to_block_attributes( $widget->stub_defaults() ) as $name => $value ) {
			$type = $this->block_attributes( $block )[ $name ]['type'];
			$this->assertTrue( $types[ $type ]( $value ), "{$name} should be {$type}." );
		}
	}

	public function test_select_options_match_what_the_block_accepts(): void {
		$options = whmcs_price_builder_options();
		$this->assertSame( array_keys( whmcs_price_cycle_codes() ), array_keys( $options['billing_cycles'] ) );
		$this->assertSame( array( 'table', 'cards', 'grid' ), array_keys( $options['product_styles'] ) );
		$this->assertSame( array( 'table', 'badge', 'inline' ), array_keys( $options['domain_styles'] ) );
		$this->assertSame( array( '', 'month', 'week', 'day' ), array_keys( $options['per_period'] ) );
		$this->assertSame( array( 'register', 'renew', 'transfer' ), array_keys( $options['transaction_types'] ) );
		$this->assertCount( 10, $options['reg_periods'] );
	}

	public function test_product_widget_maps_every_setting(): void {
		$widget = new \WHMCS_Price_Elementor_Product_Widget();
		$this->assertSame(
			array(
				'pid'          => '1,2',
				'billingCycle' => '1m',
				'show'         => array( 'name', 'price', 'setupfee' ),
				'displayStyle' => 'cards',
				'perPeriod'    => 'month',
				'cycleToggle'  => true,
				'toggleCycles' => array( '1m', '3m', '1y' ),
				'currency'     => '2',
			),
			$widget->to_block_attributes(
				array(
					'product_ids'   => '1,2',
					'billing_cycle' => '1m',
					'show_columns'  => array( 'name', 'price', 'setupfee' ),
					'display_style' => 'cards',
					'per_period'    => 'month',
					'cycle_toggle'  => 'yes',
					'toggle_cycles' => array( '1m', '3m', '1y' ),
					'currency'      => '2x',
				)
			)
		);
	}

	public function test_domain_widget_maps_every_setting(): void {
		$widget = new \WHMCS_Price_Elementor_Domain_Widget();
		$this->assertSame(
			array(
				'tld'             => 'se',
				'transactionType' => 'renew',
				'regPeriod'       => '2y',
				'showAll'         => true,
				'displayStyle'    => 'badge',
				'perPeriod'       => 'month',
				'currency'        => '3',
			),
			$widget->to_block_attributes(
				array(
					'tld'              => 'se',
					'transaction_type' => 'renew',
					'reg_period'       => '2y',
					'show_all_types'   => 'yes',
					'display_style'    => 'badge',
					'per_period'       => 'month',
					'currency'         => 3,
				)
			)
		);
	}

	/**
	 * @dataProvider builder_widgets
	 */
	public function test_widgets_render_through_the_block( string $block, string $class ): void {
		$calls = array();
		Functions\when( 'render_block' )->alias(
			static function ( $parsed ) use ( &$calls ) {
				$calls[] = $parsed;
				return '<div>block</div>';
			}
		);

		$widget   = new $class();
		$settings = $widget->stub_defaults();
		if ( 'product' === $block ) {
			$settings['product_ids'] = '7';
		}

		$this->assertSame( '<div>block</div>', $widget->stub_render( $settings ) );
		$this->assertCount( 1, $calls );
		$this->assertSame( 'whmcs-price/' . $block, $calls[0]['blockName'] );
		$this->assertSame( $widget->to_block_attributes( $settings ), $calls[0]['attrs'] );
	}

	public function test_render_block_helper_rejects_unknown_blocks_and_drops_preview(): void {
		$seen = null;
		Functions\when( 'render_block' )->alias(
			static function ( $parsed ) use ( &$seen ) {
				$seen = $parsed;
				return 'x';
			}
		);
		$this->assertSame( '', whmcs_price_render_block( 'core/paragraph', array() ) );
		$this->assertNull( $seen );
		whmcs_price_render_block( 'product', array( 'pid' => '1', 'preview' => true ) );
		$this->assertSame( array( 'pid' => '1' ), $seen['attrs'] );
	}
}
