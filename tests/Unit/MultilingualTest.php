<?php
/**
 * WPML / Polylang compatibility (#20): currency per language and
 * translatable admin texts.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;
use WP_REST_Request;

final class MultilingualTest extends TestCase {

	private function language( string $lang ): void {
		Filters\expectApplied( 'whmcs_price_current_language' )->andReturn( $lang );
	}

	private function map( array $map ): void {
		$this->set_plugin_option( 'currency_by_language', $map );
	}

	// ------------------------------------------------------------------
	// Currency resolution
	// ------------------------------------------------------------------

	public function test_no_multilingual_plugin_means_default_currency(): void {
		$this->assertSame( '', whmcs_price_multilingual_plugin() );
		$this->assertSame( 0, whmcs_price_resolve_currency() );
	}

	public function test_language_mapping_picks_currency(): void {
		$this->map( array( 'sv' => 3, 'en' => 2 ) );
		$this->language( 'en' );

		$this->assertSame( 2, whmcs_price_resolve_currency() );
	}

	public function test_unmapped_language_uses_default(): void {
		$this->map( array( 'sv' => 3 ) );
		$this->language( 'de' );

		$this->assertSame( 0, whmcs_price_resolve_currency() );
	}

	public function test_currency_filter_overrides_mapping(): void {
		$this->map( array( 'sv' => 3 ) );
		$this->language( 'sv' );
		Filters\expectApplied( 'whmcs_price_currency' )->with( 3, 'sv' )->andReturn( 9 );

		$this->assertSame( 9, whmcs_price_resolve_currency() );
	}

	public function test_explicit_value_wins_and_empty_means_automatic(): void {
		$this->map( array( 'sv' => 3 ) );
		$this->language( 'sv' );

		$this->assertSame( 5, whmcs_price_currency_arg( '5' ) );
		$this->assertSame( 0, whmcs_price_currency_arg( '0' ), '"0" explicitly means the WHMCS default.' );
		$this->assertSame( 3, whmcs_price_currency_arg( '' ) );
		$this->assertSame( 3, whmcs_price_currency_arg( null ) );
	}

	public function test_invalid_map_entries_are_ignored(): void {
		$this->map( array( 'sv' => 3, 'en' => 0, 'de' => 'abc' ) );

		$this->assertSame( array( 'sv' => 3 ), whmcs_price_currency_map() );
		$this->assertSame( array( 3 ), whmcs_price_mapped_currencies() );
	}

	// ------------------------------------------------------------------
	// API: requests and cache keys per currency
	// ------------------------------------------------------------------

	public function test_default_currency_keeps_pre_2_10_cache_keys_and_urls(): void {
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertStringNotContainsString( 'currency=', $this->http_log[0] );
		$this->assertSame( '99 kr', $this->cached_value( 'whmcs_product_' . md5( '1_monthly_price' ) ) );
	}

	public function test_explicit_currency_is_sent_and_cached_separately(): void {
		$this->queue_response( '99 kr' );
		$this->queue_response( '€9' );

		$this->assertSame( '99 kr', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price', 0 ) );
		$this->assertSame( '€9', WHMCS_Price_API::get_product_data( 1, 'monthly', 'price', 2 ) );

		$this->assertStringContainsString( '&currency=2', $this->http_log[1] );
		$this->assertSame( '€9', $this->cached_value( 'whmcs_product_' . md5( '1_monthly_price' ) . '_c2' ) );
		$this->assertSame( '99 kr', $this->cached_value( 'whmcs_product_' . md5( '1_monthly_price' ) ) );
	}

	public function test_language_mapping_applies_when_no_currency_given(): void {
		$this->map( array( 'en' => 2 ) );
		$this->language( 'en' );
		$this->queue_response( '€9' );

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price' );

		$this->assertStringContainsString( '&currency=2', $this->http_log[0] );
	}

	public function test_all_feeds_send_currency(): void {
		$this->queue_response( '€12' );
		WHMCS_Price_API::get_domain_price( 'se', 'register', 1, 2 );
		$this->queue_response( '<table></table>' );
		WHMCS_Price_API::get_all_domain_prices( 2 );
		$this->queue_response( '<option value="monthly">€9 + €1</option>' );
		WHMCS_Price_API::get_product_setup_fee( 4, 'monthly', 2 );

		foreach ( $this->http_log as $url ) {
			$this->assertStringContainsString( 'currency=2', $url );
		}
		$this->assertSame( '<table></table>', $this->cached_value( 'whmcs_domain_all_c2' ) );
	}

	public function test_background_refresh_keeps_the_currency(): void {
		$this->queue_response( '€9' );
		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price', 2 );
		$this->age_entry( 'whmcs_product_' . md5( '1_monthly_price' ) . '_c2', 3700 );
		$this->reset_api_state();

		WHMCS_Price_API::get_product_data( 1, 'monthly', 'price', 2 );
		$this->assertSame( array( 'product', array( 1, 'monthly', 'price', 2 ) ), $this->scheduled[0][2] );

		// Cron has no visitor language: the scheduled currency must be used.
		$this->queue_response( '€10' );
		WHMCS_Price_API::refresh_entry( ...$this->scheduled[0][2] );

		$this->assertStringContainsString( '&currency=2', $this->http_log[1] );
		$this->assertSame( '€10', $this->cached_value( 'whmcs_product_' . md5( '1_monthly_price' ) . '_c2' ) );
	}

	public function test_refresh_events_scheduled_before_2_10_still_work(): void {
		$this->queue_response( '99 kr' );

		$this->assertTrue( WHMCS_Price_API::refresh_entry( 'product', array( 1, 'monthly', 'price' ) ) );
		$this->assertStringNotContainsString( 'currency=', $this->http_log[0] );
	}

	public function test_invalidation_covers_every_mapped_currency(): void {
		$this->map( array( 'sv' => 3, 'en' => 2 ) );
		$this->queue_response( '99 kr' );
		WHMCS_Price_API::get_product_data( 5, 'monthly', 'price', 0 );
		$this->queue_response( '€9' );
		WHMCS_Price_API::get_product_data( 5, 'monthly', 'price', 2 );
		$this->queue_response( '<table></table>' );
		WHMCS_Price_API::get_all_domain_prices( 3 );

		$this->assertSame( 2, WHMCS_Price_API::invalidate_product( 5 ) );
		$this->assertSame( 1, WHMCS_Price_API::invalidate_domain( 'se' ), 'All-domains table in a mapped currency.' );
	}

	// ------------------------------------------------------------------
	// Shortcode, REST and abilities
	// ------------------------------------------------------------------

	public function test_shortcode_currency_attribute(): void {
		Functions\when( 'shortcode_atts' )->alias( static fn( $d, $a ) => array_merge( $d, array_intersect_key( (array) $a, $d ) ) );
		Functions\when( 'add_shortcode' )->justReturn( true );
		Functions\when( 'add_action' )->justReturn( true );
		Functions\when( 'esc_html' )->returnArg( 1 );
		Functions\when( 'esc_attr' )->returnArg( 1 );
		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'sanitize_title' )->returnArg( 1 );
		require_once dirname( __DIR__, 2 ) . '/includes/shortcode/shortcode.php';
		$this->queue_response( '€12' );

		$html = whmcs_price_shortcode_handler( array( 'tld' => 'se', 'currency' => '2' ) );

		$this->assertStringContainsString( '€12', $html );
		$this->assertStringContainsString( 'currency=2', $this->http_log[0] );
	}

	public function test_rest_product_currency_parameter(): void {
		Functions\when( 'add_action' )->justReturn( true );
		require_once dirname( __DIR__, 2 ) . '/includes/rest-api.php';
		$this->queue_response( '€9' );

		$response = whmcs_price_rest_product(
			new WP_REST_Request( array( 'pid' => 1, 'billing_cycle' => 'monthly', 'attribute' => 'price', 'currency' => 2 ) )
		);

		$this->assertSame( array( 'price' => '€9' ), $response->get_data() );
		$this->assertStringContainsString( '&currency=2', $this->http_log[0] );
	}

	public function test_ability_currency_input(): void {
		Functions\when( 'add_action' )->justReturn( true );
		require_once dirname( __DIR__, 2 ) . '/includes/abilities.php';
		$this->queue_response( '€12' );

		$result = whmcs_price_ability_get_domain_price( array( 'tld' => 'se', 'types' => array( 'register' ), 'currency' => 2 ) );

		$this->assertSame( '€12', $result['prices']['register'] );
		$this->assertStringContainsString( 'currency=2', $this->http_log[0] );
	}

	// ------------------------------------------------------------------
	// Translatable admin texts
	// ------------------------------------------------------------------

	public function test_texts_are_untouched_without_multilingual_plugin(): void {
		$this->assertSame( 'Från 99 kr', whmcs_price_translate_option( 'fallback_price', 'Från 99 kr' ) );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_wpml_language_and_string_translation(): void {
		define( 'ICL_SITEPRESS_VERSION', '4.7.0' );
		Filters\expectApplied( 'wpml_current_language' )->andReturn( 'en' );
		Filters\expectApplied( 'wpml_translate_single_string' )
			->with( 'Från 99 kr', 'Mornolink for WHMCS', 'Fallback price' )
			->andReturn( 'From €9' );

		$this->assertSame( 'wpml', whmcs_price_multilingual_plugin() );
		$this->assertSame( 'en', whmcs_price_current_language() );

		Functions\when( 'esc_html' )->returnArg( 1 );
		$this->set_plugin_option( 'fallback_price', 'Från 99 kr' );
		$this->assertStringContainsString( 'From €9', whmcs_price_unavailable_html() );
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_polylang_language_strings_and_languages(): void {
		$registered = array();
		Functions\when( 'pll_current_language' )->justReturn( 'sv' );
		Functions\when( 'pll_languages_list' )->alias(
			static fn( $args ) => 'slug' === $args['fields'] ? array( 'sv', 'en' ) : array( 'Svenska', 'English' )
		);
		Functions\when( 'pll__' )->alias( static fn( $s ) => 'Använd {code}' === $s ? 'Use {code}' : $s );
		Functions\when( 'pll_register_string' )->alias(
			static function ( $name, $value ) use ( &$registered ) {
				$registered[ $name ] = $value;
			}
		);

		$this->assertSame( 'polylang', whmcs_price_multilingual_plugin() );
		$this->assertSame( 'sv', whmcs_price_current_language() );
		$this->assertSame( array( 'sv' => 'Svenska', 'en' => 'English' ), whmcs_price_multilingual_languages() );

		$this->set_plugin_option( 'promo_text', 'Använd {code}' );
		$this->set_plugin_option( 'promo_code', 'SALE' );
		whmcs_price_register_translatable_strings();
		$this->assertSame( array( 'Promo notice text' => 'Använd {code}' ), $registered );

		Functions\when( 'wp_kses' )->returnArg( 1 );
		Functions\when( 'esc_html' )->returnArg( 1 );
		$this->assertStringContainsString( 'Use SALE', whmcs_price_promo_notice( 'product' ) );
	}
}
