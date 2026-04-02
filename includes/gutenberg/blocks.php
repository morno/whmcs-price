<?php
/**
 * Block Registration
 *
 * Registers the WHMCS Product Price and WHMCS Domain Price Gutenberg blocks.
 * Both blocks use server-side rendering (render.php) so they share the same
 * caching and data-fetching logic as the [whmcs] shortcode.
 *
 * @package    WHMCS_Price
 * @subpackage Gutenberg
 * @since      2.3.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register all plugin blocks on init.
 *
 * block.json in each block folder handles enqueueing the editor script,
 * editor style, and declaring the render callback automatically.
 *
 * @since 2.3.0
 * @return void
 */
add_action( 'init', function () {
	register_block_type( plugin_dir_path( __FILE__ ) . '../../blocks/whmcs-price-product' );
	register_block_type( plugin_dir_path( __FILE__ ) . '../../blocks/whmcs-price-domain' );

	/**
	 * Register translation files for block editor scripts.
	 *
	 * wp_set_script_translations() makes strings in the block editor JS
	 * (edit.js, index.js) translatable via the WordPress i18n system.
	 * The script handles are derived from block.json editorScript + block name.
	 *
	 * Without this call, strings like "Product ID", "Billing Cycle", and
	 * "Display Style" in the block sidebar are untranslatable.
	 *
	 * @since 2.8.0
	 */
	$lang_dir = defined( 'WHMCS_PRICE_DIR' ) ? WHMCS_PRICE_DIR . 'languages' : '';
	if ( $lang_dir && is_dir( $lang_dir ) ) {
		wp_set_script_translations(
			'whmcs-price-whmcs-price-product-editor-script',
			'whmcs-price',
			$lang_dir
		);
		wp_set_script_translations(
			'whmcs-price-whmcs-price-domain-editor-script',
			'whmcs-price',
			$lang_dir
		);
	}
} );

/**
 * Enable Pattern Overrides for the WHMCS Product Price block.
 *
 * As of WordPress 7.0, any block attribute that supports Block Bindings also
 * supports Pattern Overrides. Registering attributes here allows site editors
 * to create synced patterns where the product ID and billing cycle can be
 * overridden per pattern instance — e.g. one pattern showing hosting plans
 * with different PIDs on each pricing page.
 *
 * Attributes registered: pid, billingCycle
 *
 * @since  2.8.0
 * @param  string[] $supported_attributes Currently supported attributes for this block.
 * @return string[] Updated list of supported attributes.
 */
add_filter(
	'block_bindings_supported_attributes_whmcs-price/product',
	function ( array $supported_attributes ): array {
		$supported_attributes[] = 'pid';
		$supported_attributes[] = 'billingCycle';
		return $supported_attributes;
	}
);

/**
 * Enable Pattern Overrides for the WHMCS Domain Price block.
 *
 * Registers tld and regPeriod as overridable attributes so site editors can
 * build synced patterns showing prices for different domains — e.g. a domain
 * comparison section where each row uses a different TLD.
 *
 * Attributes registered: tld, regPeriod
 *
 * @since  2.8.0
 * @param  string[] $supported_attributes Currently supported attributes for this block.
 * @return string[] Updated list of supported attributes.
 */
add_filter(
	'block_bindings_supported_attributes_whmcs-price/domain',
	function ( array $supported_attributes ): array {
		$supported_attributes[] = 'tld';
		$supported_attributes[] = 'regPeriod';
		return $supported_attributes;
	}
);
