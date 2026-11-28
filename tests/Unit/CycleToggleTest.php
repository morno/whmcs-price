<?php
/**
 * Billing cycle toggle (#27): helpers, block render.php and [whmcs] shortcode.
 *
 * @package WHMCS_Price\Tests
 */

declare( strict_types=1 );

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use DOMDocument;
use DOMXPath;
use WHMCS_Price\Tests\TestCase;

final class CycleToggleTest extends TestCase {

	private static int $uid = 0;

	/** Every WHMCS request made by the code under test. */
	private array $requests = array();

	/** Script modules enqueued while rendering. */
	private array $enqueued = array();

	protected function setUp(): void {
		parent::setUp();

		$esc = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
		Functions\when( 'esc_html' )->alias( $esc );
		Functions\when( 'esc_attr' )->alias( $esc );
		Functions\when( 'esc_html__' )->alias( $esc );
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_unique_id' )->alias( static fn( $prefix = '' ) => $prefix . ( ++self::$uid ) );
		Functions\when( 'checked' )->alias( static fn( $a, $b = true, $echo = true ) => (string) $a === (string) $b ? " checked='checked'" : '' );
		Functions\when( 'shortcode_atts' )->alias( static fn( $defaults, $atts ) => array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ) );
		Functions\when( 'get_block_wrapper_attributes' )->alias( static fn( $extra = array() ) => 'class="wp-block ' . $esc( $extra['class'] ?? '' ) . '"' );
		Functions\when( 'add_shortcode' )->justReturn( true );

		$t = $this;
		Functions\when( 'wp_enqueue_script_module' )->alias(
			static function ( $id ) use ( $t ) {
				$t->enqueued[] = $id;
			}
		);

		// Price depends on the billing cycle, so each cycle is distinguishable.
		$prices = array(
			'monthly'   => '10.00 kr',
			'quarterly' => '27.00 kr',
			'annually'  => '100.00 kr',
		);
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) use ( $t, $prices ) {
				$t->requests[] = $url;
				parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
				if ( str_contains( $url, 'productsinfo.php' ) ) {
					$body = 'price' === $q['get']
						? ( $prices[ $q['billingcycle'] ] ?? '0.00 kr' )
						: 'Plan ' . $q['pid'];
				} else {
					$body = '<option value="monthly">10.00 kr + 5.00 kr</option><option value="annually">100.00 kr + 0.00 kr</option>';
				}
				return array(
					'body'     => $body,
					'response' => array( 'code' => 200 ),
				);
			}
		);

		require_once dirname( __DIR__, 2 ) . '/includes/shortcode/shortcode.php';
	}

	private function block( array $attributes ): string {
		ob_start();
		include dirname( __DIR__, 2 ) . '/blocks/whmcs-price-product/render.php';
		return (string) ob_get_clean();
	}

	private function xpath( string $html ): DOMXPath {
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="root">' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	private function render( string $source, string $style, string $cycles, string $bc = '1y', string $show = 'name,price' ): string {
		if ( 'shortcode' === $source ) {
			return whmcs_price_shortcode_handler( array( 'pid' => '1,2', 'bc' => $bc, 'show' => $show, 'style' => $style, 'toggle' => $cycles ) );
		}
		return $this->block(
			array(
				'pid'          => '1,2',
				'billingCycle' => $bc,
				'show'         => explode( ',', $show ),
				'displayStyle' => $style,
				'cycleToggle'  => '' !== $cycles,
				'toggleCycles' => '' === $cycles ? array() : explode( ',', $cycles ),
			)
		);
	}

	public static function sources_and_styles(): array {
		return array(
			'shortcode cards' => array( 'shortcode', 'cards' ),
			'shortcode grid'  => array( 'shortcode', 'grid' ),
			'block cards'     => array( 'block', 'cards' ),
			'block grid'      => array( 'block', 'grid' ),
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	public function test_toggle_cycles_are_validated_deduplicated_and_ordered(): void {
		$this->assertSame( array( '1m', '1y' ), whmcs_price_toggle_cycles( '1y, 1m ,1y,5y,<b>' ) );
		$this->assertSame( array( '1m', '3m', '3y' ), whmcs_price_toggle_cycles( array( '3y', '3m', '1M' ) ) );
		$this->assertSame( array(), whmcs_price_toggle_cycles( null ) );
	}

	public function test_default_cycle_falls_back_to_first_offered(): void {
		$this->assertSame( '1y', whmcs_price_toggle_default( array( '1m', '1y' ), '1y' ) );
		$this->assertSame( '1m', whmcs_price_toggle_default( array( '1m', '1y' ), '2y' ) );
	}

	// ------------------------------------------------------------------
	// Rendering
	// ------------------------------------------------------------------

	/**
	 * @dataProvider sources_and_styles
	 */
	public function test_renders_every_cycle_with_default_selected( string $source, string $style ): void {
		$x = $this->xpath( $this->render( $source, $style, '1m,1y' ) );

		$switch = $x->query( '//div[contains(@class,"whmcs-cycle-switch")]' );
		$this->assertSame( 1, $switch->length );
		$this->assertSame( 'whmcs-price/cycle-toggle', $switch->item( 0 )->getAttribute( 'data-wp-interactive' ) );
		$this->assertSame( '1y', $switch->item( 0 )->getAttribute( 'data-whmcs-selected' ), 'Configured cycle is shown first (no-JS fallback).' );
		$this->assertSame( array( 'selected' => '1y', 'announce' => '' ), json_decode( $switch->item( 0 )->getAttribute( 'data-wp-context' ), true ) );

		// Two products x two cycles, each price in its own span.
		$spans = $x->query( '//*[@data-whmcs-cycle]' );
		$this->assertSame( 4, $spans->length );
		$this->assertSame( '10.00 kr', trim( $x->query( '//*[@data-whmcs-cycle="1m"]' )->item( 0 )->textContent ) );
		$this->assertSame( '100.00 kr', trim( $x->query( '//*[@data-whmcs-cycle="1y"]' )->item( 0 )->textContent ) );

		$this->assertSame( array( WHMCS_PRICE_TOGGLE_MODULE ), array_unique( $this->enqueued ) );
	}

	/**
	 * @dataProvider sources_and_styles
	 */
	public function test_toggle_is_an_accessible_radio_group_hidden_without_js( string $source, string $style ): void {
		$x = $this->xpath( $this->render( $source, $style, '1m,3m,1y', '3m' ) );

		$fieldset = $x->query( '//fieldset[contains(@class,"whmcs-cycle-toggle")]' )->item( 0 );
		$this->assertNotNull( $fieldset );
		$this->assertTrue( $fieldset->hasAttribute( 'hidden' ), 'Radios do nothing without JS, so they start hidden.' );
		$this->assertSame( 'callbacks.reveal', $fieldset->getAttribute( 'data-wp-init---reveal' ), 'Three hyphens: WordPress 7.2 ignores two-hyphen IDs.' );
		$this->assertSame( 'Billing period', trim( $x->query( 'legend', $fieldset )->item( 0 )->textContent ) );

		$radios = $x->query( './/input[@type="radio"]', $fieldset );
		$this->assertSame( 3, $radios->length );
		$names   = array();
		$checked = array();
		foreach ( $radios as $radio ) {
			$names[] = $radio->getAttribute( 'name' );
			if ( $radio->hasAttribute( 'checked' ) ) {
				$checked[] = $radio->getAttribute( 'value' );
			}
			$this->assertSame( 'actions.select', $radio->getAttribute( 'data-wp-on--change' ) );
			$this->assertNotSame( '', $radio->getAttribute( 'data-announce' ) );
			// Each radio is wrapped in its label, so it has an accessible name.
			$this->assertSame( 'label', $radio->parentNode->nodeName );
		}
		$this->assertCount( 1, array_unique( $names ), 'One radio group per toggle.' );
		$this->assertSame( array( '3m' ), $checked );

		$live = $x->query( '//*[@aria-live="polite"]' )->item( 0 );
		$this->assertNotNull( $live );
		$this->assertSame( 'context.announce', $live->getAttribute( 'data-wp-text' ) );
	}

	/**
	 * @dataProvider sources_and_styles
	 */
	public function test_setup_fee_follows_the_cycle( string $source, string $style ): void {
		$x = $this->xpath( $this->render( $source, $style, '1m,1y', '1y', 'name,price,setupfee' ) );

		// Product 1, 2 x (price + setup fee) x 2 cycles.
		$this->assertSame( 8, $x->query( '//*[@data-whmcs-cycle]' )->length );
		$fees = array();
		foreach ( $x->query( '//*[@data-whmcs-cycle]' ) as $span ) {
			$fees[ $span->getAttribute( 'data-whmcs-cycle' ) ][] = trim( $span->textContent );
		}
		$this->assertContains( '5.00 kr', $fees['1m'] );
		$this->assertContains( '0.00 kr', $fees['1y'] );
	}

	public function test_switching_cycles_needs_no_extra_whmcs_requests_after_first_render(): void {
		$this->render( 'shortcode', 'cards', '1m,1y' );
		$first = count( $this->requests );
		\WHMCS_Price_API::reset_request_state();
		$this->render( 'shortcode', 'cards', '1m,1y' );
		$this->assertSame( $first, count( $this->requests ), 'Second render is served from cache.' );
	}

	public function test_toggle_one_offers_monthly_and_annually(): void {
		$x = $this->xpath( $this->render( 'shortcode', 'grid', '1' ) );
		$values = array();
		foreach ( $x->query( '//input[@type="radio"]' ) as $radio ) {
			$values[] = $radio->getAttribute( 'value' );
		}
		$this->assertSame( array( '1m', '1y' ), $values );
	}

	public static function no_toggle_cases(): array {
		return array(
			'table style (shortcode)' => array( 'shortcode', 'table', '1m,1y' ),
			'table style (block)'     => array( 'block', 'table', '1m,1y' ),
			'one cycle (shortcode)'   => array( 'shortcode', 'cards', '1m' ),
			'one cycle (block)'       => array( 'block', 'grid', '1y' ),
			'off (shortcode)'         => array( 'shortcode', 'cards', '' ),
			'off (block)'             => array( 'block', 'cards', '' ),
		);
	}

	/**
	 * @dataProvider no_toggle_cases
	 */
	public function test_without_toggle_output_is_unchanged( string $source, string $style, string $cycles ): void {
		$html = $this->render( $source, $style, $cycles );
		$this->assertStringNotContainsString( 'whmcs-cycle-switch', $html );
		$this->assertStringNotContainsString( 'data-whmcs-cycle', $html );
		$this->assertStringContainsString( '100.00 kr', $html );
		$this->assertSame( array(), $this->enqueued );
	}

	public function test_stylesheet_hides_every_unselected_cycle(): void {
		$css = (string) file_get_contents( dirname( __DIR__, 2 ) . '/blocks/whmcs-price-product/styles.css' );
		foreach ( array_keys( whmcs_price_cycle_codes() ) as $code ) {
			$this->assertStringContainsString(
				'.whmcs-cycle-switch[data-whmcs-selected="' . $code . '"] [data-whmcs-cycle]:not([data-whmcs-cycle="' . $code . '"])',
				$css
			);
		}
	}
}
