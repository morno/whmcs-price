<?php
/**
 * Block patterns (#21).
 *
 * Structural checks that run in CI. The core-block markup was additionally
 * verified with Gutenberg's own validateBlock() during development.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;

final class PatternsTest extends TestCase {

	private array $categories = array();
	private array $patterns   = array();

	protected function setUp(): void {
		parent::setUp();

		$esc = static fn( $s ) => htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' );
		Functions\when( 'esc_html' )->alias( $esc );
		Functions\when( 'esc_attr' )->alias( $esc );
		Functions\when( 'esc_html__' )->alias( $esc );
		Functions\when( 'add_action' )->justReturn( true );

		$this->categories = array();
		$this->patterns   = array();
		$t                = $this;
		Functions\when( 'register_block_pattern_category' )->alias(
			static function ( $slug, $args ) use ( $t ) {
				$t->categories[ $slug ] = $args;
				return true;
			}
		);
		Functions\when( 'register_block_pattern' )->alias(
			static function ( $slug, $args ) use ( $t ) {
				$t->patterns[ $slug ] = $args;
				return true;
			}
		);

		require_once dirname( __DIR__, 2 ) . '/includes/patterns.php';
		whmcs_price_register_patterns();
	}

	/**
	 * @return array<int, array{name: string, attrs: array, self_closing: bool, closing: bool}>
	 */
	private function block_comments( string $content ): array {
		preg_match_all( '#<!-- (/?)wp:([a-z0-9-]+(?:/[a-z0-9-]+)?)(?: (\{.*?\}))? (/?)-->#s', $content, $m, PREG_SET_ORDER );
		$out = array();
		foreach ( $m as $match ) {
			$name = str_contains( $match[2], '/' ) ? $match[2] : 'core/' . $match[2];
			$out[] = array(
				'name'         => $name,
				'attrs'        => isset( $match[3] ) && '' !== $match[3] ? json_decode( $match[3], true, 512, JSON_THROW_ON_ERROR ) : array(),
				'self_closing' => '/' === $match[4],
				'closing'      => '/' === $match[1],
			);
		}
		return $out;
	}

	private function block_json_attributes( string $block ): array {
		$meta = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . "/blocks/whmcs-price-{$block}/block.json" ), true );
		return array_merge( array_keys( $meta['attributes'] ), array( 'align', 'className' ) );
	}

	public function test_category_and_six_patterns_are_registered(): void {
		$this->assertArrayHasKey( 'whmcs-pricing', $this->categories );
		$this->assertSame( 'WHMCS Pricing', $this->categories['whmcs-pricing']['label'] );
		$this->assertCount( 6, $this->patterns );

		foreach ( $this->patterns as $slug => $pattern ) {
			$this->assertStringStartsWith( 'whmcs-price/', $slug );
			$this->assertSame( array( 'whmcs-pricing' ), $pattern['categories'] );
			$this->assertNotSame( '', $pattern['title'] );
			$this->assertNotSame( '', $pattern['description'] );
		}
	}

	public function test_block_comments_are_balanced_and_json_is_valid(): void {
		foreach ( $this->patterns as $slug => $pattern ) {
			$stack = array();
			foreach ( $this->block_comments( $pattern['content'] ) as $comment ) {
				if ( $comment['self_closing'] ) {
					continue;
				}
				if ( $comment['closing'] ) {
					$this->assertSame( array_pop( $stack ), $comment['name'], "Mismatched closer in $slug" );
					continue;
				}
				$stack[] = $comment['name'];
			}
			$this->assertSame( array(), $stack, "Unclosed blocks in $slug" );
		}
	}

	public function test_only_plugin_and_core_blocks_with_known_attributes(): void {
		$known = array(
			'whmcs-price/product' => $this->block_json_attributes( 'product' ),
			'whmcs-price/domain'  => $this->block_json_attributes( 'domain' ),
		);
		$plugin_blocks = 0;

		foreach ( $this->patterns as $slug => $pattern ) {
			foreach ( $this->block_comments( $pattern['content'] ) as $comment ) {
				if ( str_starts_with( $comment['name'], 'core/' ) ) {
					continue;
				}
				$this->assertArrayHasKey( $comment['name'], $known, "Unexpected block {$comment['name']} in $slug" );
				$this->assertTrue( $comment['self_closing'], 'Plugin blocks are dynamic and self-closing.' );
				$unknown = array_diff( array_keys( $comment['attrs'] ), $known[ $comment['name'] ] );
				$this->assertSame( array(), array_values( $unknown ), "Unknown attributes in $slug" );
				++$plugin_blocks;
			}
		}
		$this->assertGreaterThan( 10, $plugin_blocks );
	}

	public function test_product_blocks_have_no_product_id_and_patterns_start_with_a_note(): void {
		foreach ( $this->patterns as $slug => $pattern ) {
			$this->assertStringStartsWith( '<!-- wp:paragraph {"className":"whmcs-price-pattern-note"', $pattern['content'], "$slug starts with the edit note" );

			foreach ( $this->block_comments( $pattern['content'] ) as $comment ) {
				if ( 'whmcs-price/product' === $comment['name'] ) {
					$this->assertSame( '', $comment['attrs']['pid'], "Product ID left for the editor in $slug" );
				}
				if ( 'whmcs-price/domain' === $comment['name'] ) {
					$this->assertMatchesRegularExpression( '/^[a-z]{2,6}$/', $comment['attrs']['tld'] );
					$this->assertArrayNotHasKey( 'preview', $comment['attrs'] );
				}
			}
		}
	}
}
