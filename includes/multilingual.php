<?php
/**
 * WPML and Polylang compatibility
 *
 * Prices come from WHMCS already formatted for a WHMCS currency, so the
 * plugin never re-formats them per language. What differs between languages
 * is *which currency* to request and the admin-entered texts.
 *
 * Currency is resolved in this order:
 *   1. An explicit currency on the block / shortcode / REST request.
 *   2. The currency mapped to the current language (Settings → WHMCS Price
 *      → Connection → Currency per language), when WPML or Polylang is active.
 *   3. The WHMCS default currency (no currency parameter is sent).
 *
 * Admin-entered visitor texts (fallback price, promo notice) are registered
 * with WPML String Translation / Polylang Strings so they can be translated.
 *
 * Nothing here is required: with neither plugin active every function falls
 * back to single-language behaviour.
 *
 * @package    WHMCS_Price
 * @subpackage Multilingual
 * @since      2.10.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Which multilingual plugin is active, if any.
 *
 * @since  2.10.0
 * @return string 'wpml', 'polylang' or ''.
 */
function whmcs_price_multilingual_plugin(): string {
	if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
		return 'wpml';
	}
	if ( function_exists( 'pll_current_language' ) ) {
		return 'polylang';
	}
	return '';
}

/**
 * Current language code (e.g. "sv", "en"), or '' when unknown.
 *
 * @since  2.10.0
 * @return string
 */
function whmcs_price_current_language(): string {
	$lang = '';
	switch ( whmcs_price_multilingual_plugin() ) {
		case 'wpml':
			$lang = (string) apply_filters( 'wpml_current_language', null ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API hook.
			break;
		case 'polylang':
			$lang = (string) pll_current_language( 'slug' );
			break;
	}

	/**
	 * Filter the language used to pick a WHMCS currency.
	 *
	 * @since 2.10.0
	 * @param string $lang Language code, or '' when no multilingual plugin is active.
	 */
	return (string) apply_filters( 'whmcs_price_current_language', 'all' === $lang ? '' : $lang );
}

/**
 * Active languages: code => display name.
 *
 * @since  2.10.0
 * @return array<string, string>
 */
function whmcs_price_multilingual_languages(): array {
	$languages = array();
	switch ( whmcs_price_multilingual_plugin() ) {
		case 'wpml':
			$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API hook.
			foreach ( is_array( $active ) ? $active : array() as $code => $info ) {
				$languages[ (string) $code ] = (string) ( $info['translated_name'] ?? $info['native_name'] ?? $code );
			}
			break;
		case 'polylang':
			if ( function_exists( 'pll_languages_list' ) ) {
				$slugs = (array) pll_languages_list( array( 'fields' => 'slug' ) );
				$names = (array) pll_languages_list( array( 'fields' => 'name' ) );
				foreach ( $slugs as $i => $slug ) {
					$languages[ (string) $slug ] = (string) ( $names[ $i ] ?? $slug );
				}
			}
			break;
	}
	return $languages;
}

/**
 * Configured language => WHMCS currency ID map (only entries > 0).
 *
 * @since  2.10.0
 * @return array<string, int>
 */
function whmcs_price_currency_map(): array {
	$options = get_option( 'whmcs_price_option', array() );
	$map     = is_array( $options ) && is_array( $options['currency_by_language'] ?? null ) ? $options['currency_by_language'] : array();
	return array_filter( array_map( 'intval', $map ), static fn( $id ) => $id > 0 );
}

/**
 * Every currency ID mapped to a language (used to invalidate all variants).
 *
 * @since  2.10.0
 * @return int[]
 */
function whmcs_price_mapped_currencies(): array {
	return array_values( array_unique( whmcs_price_currency_map() ) );
}

/**
 * WHMCS currency ID for the current request (0 = WHMCS default).
 *
 * @since  2.10.0
 * @return int
 */
function whmcs_price_resolve_currency(): int {
	$lang     = whmcs_price_current_language();
	$map      = whmcs_price_currency_map();
	$currency = '' !== $lang ? (int) ( $map[ $lang ] ?? 0 ) : 0;

	/**
	 * Filter the WHMCS currency ID used when a block or shortcode does not
	 * set one explicitly.
	 *
	 * @since 2.10.0
	 * @param int    $currency WHMCS currency ID, 0 for the WHMCS default.
	 * @param string $lang     Current language code ('' if unknown).
	 */
	return max( 0, (int) apply_filters( 'whmcs_price_currency', $currency, $lang ) );
}

/**
 * Turn a block/shortcode/REST currency value into a getter argument.
 *
 * '' or null means automatic; anything else is an explicit currency ID
 * (0 = WHMCS default). Returns the resolved ID so callers can also use it
 * for cache-hit checks.
 *
 * @since  2.10.0
 * @param  mixed $value Raw attribute value.
 * @return int
 */
function whmcs_price_currency_arg( $value ): int {
	if ( null === $value || '' === trim( (string) $value ) ) {
		return whmcs_price_resolve_currency();
	}
	return max( 0, (int) $value );
}

// ---------------------------------------------------------------------------
// Translatable admin-entered texts
// ---------------------------------------------------------------------------

/**
 * Option keys of visitor-facing texts entered in the settings, with labels.
 *
 * @since  2.10.0
 * @return array<string, string>
 */
function whmcs_price_translatable_options(): array {
	return array(
		'fallback_price' => 'Fallback price',
		'promo_text'     => 'Promo notice text',
	);
}

/**
 * Register the admin-entered texts with WPML String Translation / Polylang.
 *
 * Polylang needs strings registered on every admin request; WPML stores
 * them, so registering again when they change is enough (and harmless).
 *
 * @since  2.10.0
 * @return void
 */
function whmcs_price_register_translatable_strings(): void {
	$plugin = whmcs_price_multilingual_plugin();
	if ( '' === $plugin ) {
		return;
	}
	$options = get_option( 'whmcs_price_option', array() );
	foreach ( whmcs_price_translatable_options() as $key => $label ) {
		$value = is_array( $options ) ? (string) ( $options[ $key ] ?? '' ) : '';
		if ( '' === trim( $value ) ) {
			continue;
		}
		if ( 'wpml' === $plugin ) {
			do_action( 'wpml_register_single_string', 'Mornolink for WHMCS', $label, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API hook.
		} elseif ( function_exists( 'pll_register_string' ) ) {
			pll_register_string( $label, $value, 'Mornolink for WHMCS' );
		}
	}
}
add_action( 'admin_init', 'whmcs_price_register_translatable_strings' );
add_action( 'update_option_whmcs_price_option', 'whmcs_price_register_translatable_strings' );

/**
 * Translate an admin-entered text into the current language.
 *
 * @since  2.10.0
 * @param  string $key   Option key (see whmcs_price_translatable_options()).
 * @param  string $value Text in the default language.
 * @return string
 */
function whmcs_price_translate_option( string $key, string $value ): string {
	$labels = whmcs_price_translatable_options();
	if ( '' === $value || ! isset( $labels[ $key ] ) ) {
		return $value;
	}
	switch ( whmcs_price_multilingual_plugin() ) {
		case 'wpml':
			return (string) apply_filters( 'wpml_translate_single_string', $value, 'Mornolink for WHMCS', $labels[ $key ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML's public API hook.
		case 'polylang':
			return function_exists( 'pll__' ) ? (string) pll__( $value ) : $value;
	}
	return $value;
}
