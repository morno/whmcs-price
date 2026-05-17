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
	 * The third argument is intentionally omitted so WordPress falls back to
	 * WP_LANG_DIR/plugins/ — that is where the wp.org language packs land.
	 * The plugin does not ship its own /languages folder (excluded from SVN
	 * deploy in 2.8.0), so pointing at a local path would silently skip
	 * loading on every deployed install.
	 *
	 * @since 2.8.0
	 * @since 2.9.0 Use WP_LANG_DIR/plugins/ fallback instead of bundled folder.
	 */
	wp_set_script_translations(
		'whmcs-price-whmcs-price-product-editor-script',
		'whmcs-price'
	);
	wp_set_script_translations(
		'whmcs-price-whmcs-price-domain-editor-script',
		'whmcs-price'
	);
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

/**
 * Register Block Variations for the WHMCS Product Price block.
 *
 * Block Variations appear as separate entries in the block inserter, giving
 * users convenient presets without requiring manual configuration. Each
 * variation pre-sets specific attributes — users can still change them after
 * insertion.
 *
 * Variations registered:
 *   - Hosting Card    (cards style, name + price + setupfee, annual)
 *   - Pricing Grid    (grid style, name + price, annual)
 *   - Monthly Table   (table style, name + price, monthly, per-month breakdown)
 *
 * @since 2.9.0
 */
add_action( 'init', function() {
	// Variations are registered via wp_add_inline_script on the editor script.
	// We use enqueue_block_editor_assets so the script runs only in the editor.
} );

add_action( 'enqueue_block_editor_assets', function() {
	if ( ! wp_script_is( 'whmcs-price-whmcs-price-product-editor-script', 'registered' ) ) {
		return;
	}

	$variations_product = array(
		array(
			'name'        => 'whmcs-price/product-hosting-card',
			'title'       => __( 'Hosting Card', 'whmcs-price' ),
			'description' => __( 'Product price in card layout with name, price and setup fee.', 'whmcs-price' ),
			'icon'        => 'id-alt',
			'attributes'  => array(
				'displayStyle' => 'cards',
				'show'         => array( 'name', 'price', 'setupfee' ),
				'billingCycle' => '1y',
			),
			'isDefault'   => false,
		),
		array(
			'name'        => 'whmcs-price/product-pricing-grid',
			'title'       => __( 'Pricing Grid', 'whmcs-price' ),
			'description' => __( 'Compact grid of products showing name and price.', 'whmcs-price' ),
			'icon'        => 'grid-view',
			'attributes'  => array(
				'displayStyle' => 'grid',
				'show'         => array( 'name', 'price' ),
				'billingCycle' => '1y',
			),
			'isDefault'   => false,
		),
		array(
			'name'        => 'whmcs-price/product-monthly-table',
			'title'       => __( 'Monthly Table', 'whmcs-price' ),
			'description' => __( 'Product price table billed monthly with per-month breakdown.', 'whmcs-price' ),
			'icon'        => 'editor-table',
			'attributes'  => array(
				'displayStyle' => 'table',
				'show'         => array( 'name', 'price' ),
				'billingCycle' => '1m',
				'perPeriod'    => 'month',
			),
			'isDefault'   => false,
		),
	);

	$variations_domain = array(
		array(
			'name'        => 'whmcs-price/domain-badge',
			'title'       => __( 'Domain Badge', 'whmcs-price' ),
			'description' => __( 'Single domain registration price as a badge.', 'whmcs-price' ),
			'icon'        => 'admin-site-alt3',
			'attributes'  => array(
				'displayStyle'    => 'badge',
				'transactionType' => 'register',
			),
			'isDefault'   => false,
		),
		array(
			'name'        => 'whmcs-price/domain-comparison',
			'title'       => __( 'Domain Comparison', 'whmcs-price' ),
			'description' => __( 'Shows register, renew, and transfer prices in a table.', 'whmcs-price' ),
			'icon'        => 'editor-table',
			'attributes'  => array(
				'displayStyle' => 'table',
				'showAll'      => true,
			),
			'isDefault'   => false,
		),
	);

	// Inline script registers variations on the client side.
	$js = sprintf(
		'(function(){
			if(!window.wp||!wp.blocks){return;}
			%s.forEach(function(v){wp.blocks.registerBlockVariation("whmcs-price/product",v);});
			%s.forEach(function(v){wp.blocks.registerBlockVariation("whmcs-price/domain",v);});
		})();',
		wp_json_encode( $variations_product ),
		wp_json_encode( $variations_domain )
	);

	wp_add_inline_script( 'whmcs-price-whmcs-price-product-editor-script', $js );
} );
