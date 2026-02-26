<?php
/**
 * Block Registration
 *
 * Registers the WHMCS Product Price and WHMCS Domain Price Gutenberg blocks.
 * Both blocks use server-side rendering (render.php) so they share the same
 * caching and data-fetching logic as the [whmcs] shortcode.
 *
 * Add this file to includes/ and require it from whmcs_price.php, OR copy
 * the add_action call directly into whmcs_price.php alongside the other hooks.
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
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
	register_block_type( plugin_dir_path( __FILE__ ) . '../blocks/whmcs-price-product' );
	register_block_type( plugin_dir_path( __FILE__ ) . '../blocks/whmcs-price-domain' );
} );
