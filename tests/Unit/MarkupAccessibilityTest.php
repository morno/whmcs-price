<?php
/**
 * Accessibility of the rendered markup (#19).
 *
 * Renders every product and domain display style through the shortcode and
 * the Gutenberg block render.php files and checks the semantics with DOM
 * queries. Set WHMCS_PRICE_A11Y_DUMP=/some/dir to also write each rendered
 * fragment as a standalone HTML page (with the plugin CSS) for axe-core or
 * Lighthouse.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use DOMDocument;
use DOMXPath;
use WHMCS_Price\Tests\TestCase;

final class MarkupAccessibilityTest extends TestCase {

	private static int $uid = 0;

	protected function setUp(): void {
		parent::setUp();

		$esc = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
		Functions\when( 'esc_html' )->alias( $esc );
		Functions\when( 'esc_attr' )->alias( $esc );
		Functions\when( 'esc_url' )->alias( $esc );
		Functions\when( 'esc_attr__' )->alias( $esc );
		Functions\when( 'esc_html__' )->alias( $esc );
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'wp_unique_id' )->alias( static fn( $prefix = '' ) => $prefix . ( ++self::$uid ) );
		Functions\when( 'shortcode_atts' )->alias( static fn( $defaults, $atts ) => array_merge( $defaults, array_intersect_key( (array) $atts, $defaults ) ) );
		Functions\when( 'get_block_wrapper_attributes' )->alias( static fn( $extra = array() ) => 'class="wp-block ' . $esc( $extra['class'] ?? '' ) . '"' );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'add_shortcode' )->justReturn( true );

		// Answer every WHMCS feed from the URL.
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) {
				parse_str( (string) parse_url( $url, PHP_URL_QUERY ), $q );
				if ( str_contains( $url, 'productsinfo.php' ) ) {
					$body = array(
						'name'        => 'Plan ' . $q['pid'],
						'description' => 'Fast SSD hosting',
						'price'       => '99.00 kr',
					)[ $q['get'] ];
				} elseif ( str_contains( $url, 'productpricing.php' ) ) {
					$body = '<option value="monthly">99.00 kr + 10.00 kr</option><option value="annually">999.00 kr</option>';
				} elseif ( str_contains( $url, 'domainprice.php' ) ) {
					$body = '129.00 kr';
				} else {
					$body = '<table><tr><th>TLD</th></tr><tr><td>.se</td></tr></table>';
				}
				return array(
					'body'     => $body,
					'response' => array( 'code' => 200 ),
				);
			}
		);

		require_once dirname( __DIR__, 2 ) . '/includes/shortcode/shortcode.php';
	}

	private function block( string $name, array $attributes ): string {
		ob_start();
		include dirname( __DIR__, 2 ) . "/blocks/whmcs-price-{$name}/render.php";
		return (string) ob_get_clean();
	}

	private function xpath( string $html, string $label ): DOMXPath {
		$this->dump( $html, $label );
		$doc = new DOMDocument();
		libxml_use_internal_errors( true );
		$doc->loadHTML( '<?xml encoding="utf-8"?><div id="root">' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $doc );
	}

	private function dump( string $html, string $label ): void {
		$dir = getenv( 'WHMCS_PRICE_A11Y_DUMP' );
		if ( ! $dir ) {
			return;
		}
		$root = dirname( __DIR__, 2 ) . '/blocks/build/';
		$css  = file_get_contents( $root . 'whmcs-price-product.css' ) . file_get_contents( $root . 'whmcs-price-domain.css' );
		$page = '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $label . '</title><style>' . $css
			. '</style></head><body><main><h1>' . $label . '</h1><h2>Pricing</h2>' . $html . '</main></body></html>';
		file_put_contents( rtrim( $dir, '/' ) . '/' . preg_replace( '/[^a-z0-9]+/', '-', strtolower( $label ) ) . '.html', $page );
	}

	// ------------------------------------------------------------------
	// Products
	// ------------------------------------------------------------------

	public static function product_sources(): array {
		return array( 'shortcode' => array( 'shortcode' ), 'block' => array( 'block' ) );
	}

	private function product( string $source, string $style, string $per = '' ): string {
		if ( 'shortcode' === $source ) {
			return whmcs_price_shortcode_handler( array( 'pid' => '1,2', 'bc' => '1m', 'show' => 'name,description,price,setupfee', 'style' => $style, 'per' => $per ) );
		}
		return $this->block( 'product', array( 'pid' => '1,2', 'billingCycle' => '1m', 'show' => array( 'name', 'description', 'price', 'setupfee' ), 'displayStyle' => $style, 'perPeriod' => $per ) );
	}

	/**
	 * @dataProvider product_sources
	 */
	public function test_product_table_has_column_and_row_headers( string $source ): void {
		$x = $this->xpath( $this->product( $source, 'table' ), "product table $source" );

		$this->assertSame( 4, $x->query( '//table/thead//th[@scope="col"]' )->length );
		$this->assertSame( 0, $x->query( '//table//th[not(@scope)]' )->length, 'Every th has a scope.' );
		$row_headers = $x->query( '//table/tbody//th[@scope="row"]' );
		$this->assertSame( 2, $row_headers->length, 'Product name is the row header.' );
		$this->assertSame( 'Plan 1', trim( $row_headers->item( 0 )->textContent ) );
	}

	/**
	 * @dataProvider product_sources
	 */
	public function test_product_cards_are_labelled_articles( string $source ): void {
		$x = $this->xpath( $this->product( $source, 'cards' ), "product cards $source" );

		$articles = $x->query( '//article[contains(@class,"whmcs-product-card")]' );
		$this->assertSame( 2, $articles->length );
		foreach ( $articles as $article ) {
			$id      = $article->getAttribute( 'aria-labelledby' );
			$heading = $x->query( './/h3[@id="' . $id . '"]', $article );
			$this->assertNotSame( '', $id );
			$this->assertSame( 1, $heading->length, 'aria-labelledby points at the card heading.' );
		}
	}

	/**
	 * @dataProvider product_sources
	 */
	public function test_card_heading_level_is_filterable( string $source ): void {
		\Brain\Monkey\Filters\expectApplied( 'whmcs_price_card_heading_level' )->andReturn( 2 );

		$x = $this->xpath( $this->product( $source, 'cards' ), "product cards h2 $source" );

		$this->assertSame( 2, $x->query( '//article//h2' )->length );
		$this->assertSame( 0, $x->query( '//article//h3' )->length );
	}

	/**
	 * @dataProvider product_sources
	 */
	public function test_product_grid_items_are_named_groups( string $source ): void {
		$x = $this->xpath( $this->product( $source, 'grid' ), "product grid $source" );

		$groups = $x->query( '//div[contains(@class,"whmcs-product-grid-item") and @role="group"]' );
		$this->assertSame( 2, $groups->length );
		$this->assertSame( 'Plan 1', $groups->item( 0 )->getAttribute( 'aria-label' ) );
	}

	/**
	 * @dataProvider product_sources
	 */
	public function test_per_period_abbreviations_have_spoken_text( string $source ): void {
		$x = $this->xpath( $this->product( $source, 'table', 'month' ), "product per-period $source" );

		$hidden = $x->query( '//span[@aria-hidden="true"]' );
		$spoken = $x->query( '//span[contains(@class,"whmcs-price-sr")]' );

		$this->assertGreaterThan( 0, $hidden->length );
		$this->assertSame( '/mo', trim( $hidden->item( 0 )->textContent ) );
		$texts = array_map( static fn( $n ) => trim( $n->textContent ), iterator_to_array( $spoken ) );
		$this->assertContains( 'per month', $texts );
		$this->assertContains( 'equivalent to', $texts );
	}

	public function test_cycles_shortcode_table_and_cards(): void {
		$x = $this->xpath( whmcs_price_cycles_shortcode_handler( array( 'pid' => 4, 'style' => 'table' ) ), 'cycles table' );
		$this->assertSame( 2, $x->query( '//thead//th[@scope="col"]' )->length );
		$this->assertSame( 'Monthly', trim( $x->query( '//tbody//th[@scope="row"]' )->item( 0 )->textContent ) );

		$x = $this->xpath( whmcs_price_cycles_shortcode_handler( array( 'pid' => 4, 'style' => 'cards' ) ), 'cycles cards' );
		$this->assertSame( 2, $x->query( '//article[@aria-labelledby]' )->length );
	}

	// ------------------------------------------------------------------
	// Domains
	// ------------------------------------------------------------------

	public function test_domain_shortcode_table_headers(): void {
		$x = $this->xpath( whmcs_price_shortcode_handler( array( 'tld' => 'se', 'show' => 'register,renew,transfer' ) ), 'domain table shortcode' );

		$this->assertSame( 4, $x->query( '//thead//th[@scope="col"]' )->length );
		$this->assertStringContainsString( '.se', $x->query( '//tbody//th[@scope="row"]' )->item( 0 )->textContent );
	}

	public function test_domain_block_table_headers(): void {
		$x = $this->xpath( $this->block( 'domain', array( 'tld' => 'se', 'showAll' => true, 'displayStyle' => 'table' ) ), 'domain table block' );

		$this->assertSame( 3, $x->query( '//thead//th[@scope="col"]' )->length );
	}

	public function test_domain_badges_are_a_labelled_list(): void {
		$x = $this->xpath( $this->block( 'domain', array( 'tld' => 'se', 'showAll' => true, 'displayStyle' => 'badge' ) ), 'domain badges block' );

		$list = $x->query( '//div[@role="list"]' );
		$this->assertSame( 1, $list->length );
		$this->assertSame( '.se domain prices', $list->item( 0 )->getAttribute( 'aria-label' ) );
		$this->assertSame( 3, $x->query( '//div[@role="list"]/div[@role="listitem"]' )->length );
	}

	public function test_domain_block_per_period_renders_html_not_escaped_tags(): void {
		$html = $this->block( 'domain', array( 'tld' => 'se', 'showAll' => true, 'displayStyle' => 'inline', 'perPeriod' => 'month' ) );
		$this->dump( $html, 'domain inline per-period block' );

		$this->assertStringNotContainsString( '&lt;span', $html, 'Per-period markup must not be printed as text.' );
		$this->assertStringContainsString( 'class="whmcs-price-per"', $html );
	}

	public function test_domain_block_shows_unavailable_message_instead_of_na(): void {
		Functions\when( 'wp_remote_get' )->justReturn( new \WP_Error( 'http_request_failed', 'down' ) );

		$html = $this->block( 'domain', array( 'tld' => 'se', 'showAll' => true, 'displayStyle' => 'badge' ) );

		$this->assertStringNotContainsString( '>NA<', $html );
		$this->assertStringContainsString( 'whmcs-price-unavailable', $html );
	}

	public function test_dump_single_domain_styles(): void {
		foreach ( array( 'badge', 'inline', 'price' ) as $style ) {
			$html = $this->block( 'domain', array( 'tld' => 'se', 'displayStyle' => $style ) );
			$this->dump( $html, "domain single $style" );
			$this->assertStringContainsString( '129.00 kr', $html );
		}
	}
}
