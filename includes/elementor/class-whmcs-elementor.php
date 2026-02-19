<?php
/**
 * Elementor Integration for WHMCS Price
 *
 * Registers custom Elementor widgets for displaying WHMCS pricing.
 *
 * @package    WHMCS_Price
 * @subpackage Elementor
 * @since      2.4.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Check if Elementor is active before initializing widgets.
 */
function whmcs_price_elementor_init() {
	// Check if Elementor is installed and activated
	if ( ! did_action( 'elementor/loaded' ) ) {
		return;
	}

	// Register widget category
	add_action( 'elementor/elements/categories_registered', 'whmcs_price_add_elementor_category' );

	// Register widgets
	add_action( 'elementor/widgets/register', 'whmcs_price_register_elementor_widgets' );
}
add_action( 'plugins_loaded', 'whmcs_price_elementor_init' );

/**
 * Add custom widget category for WHMCS Price widgets.
 *
 * @since 2.4.0
 * @param \Elementor\Elements_Manager $elements_manager Elementor elements manager.
 * @return void
 */
function whmcs_price_add_elementor_category( $elements_manager ) {
	$elements_manager->add_category(
		'whmcs-price',
		array(
			'title' => __( 'WHMCS Price', 'whmcs-price' ),
			'icon'  => 'fa fa-dollar',
		)
	);
}

/**
 * Register WHMCS Price Elementor widgets.
 *
 * @since 2.4.0
 * @param \Elementor\Widgets_Manager $widgets_manager Elementor widgets manager.
 * @return void
 */
function whmcs_price_register_elementor_widgets( $widgets_manager ) {
	require_once WHMCS_PRICE_DIR . 'includes/elementor/widgets/product-price-widget.php';
	require_once WHMCS_PRICE_DIR . 'includes/elementor/widgets/domain-price-widget.php';

	$widgets_manager->register( new \WHMCS_Price_Elementor_Product_Widget() );
	$widgets_manager->register( new \WHMCS_Price_Elementor_Domain_Widget() );
}

/**
 * Enqueue frontend CSS for Elementor widgets.
 *
 * @since 2.4.0
 * @return void
 */
function whmcs_price_elementor_enqueue_styles() {
	// Reuse the block CSS files that are already compiled
	if ( file_exists( WHMCS_PRICE_DIR . 'blocks/build/whmcs-price-product.css' ) ) {
		wp_enqueue_style(
			'whmcs-price-product-elementor',
			WHMCS_PRICE_URL . 'blocks/build/whmcs-price-product.css',
			array(),
			WHMCS_PRICE_VERSION
		);
	}

	if ( file_exists( WHMCS_PRICE_DIR . 'blocks/build/whmcs-price-domain.css' ) ) {
		wp_enqueue_style(
			'whmcs-price-domain-elementor',
			WHMCS_PRICE_URL . 'blocks/build/whmcs-price-domain.css',
			array(),
			WHMCS_PRICE_VERSION
		);
	}
}
add_action( 'elementor/frontend/after_enqueue_styles', 'whmcs_price_elementor_enqueue_styles' );
