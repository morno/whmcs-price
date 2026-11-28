<?php
/**
 * Shared rendering and options for page builder integrations
 *
 * Every page builder integration (Elementor today, others later) must look and
 * behave exactly like the Gutenberg blocks. To guarantee that, integrations do
 * not render prices themselves: they map their settings to the block attributes
 * from block.json and call whmcs_price_render_block(), which runs the real block
 * render.php through render_block(). New block features then reach every
 * builder automatically; the builder only needs a control for any new attribute.
 *
 * tests/Unit/BuilderParityTest.php fails when a block attribute has no
 * matching control in a builder widget.
 *
 * @package    WHMCS_Price
 * @subpackage Builders
 * @since      2.10.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Render a WHMCS Price block from a page builder.
 *
 * @since  2.10.1
 * @param  string $block      'product' or 'domain'.
 * @param  array  $attributes Block attributes as defined in block.json.
 * @return string Block HTML (escaped by the block's render.php).
 */
function whmcs_price_render_block( string $block, array $attributes ): string {
	if ( ! in_array( $block, array( 'product', 'domain' ), true ) ) {
		return '';
	}

	// The inserter preview renders placeholder data client-side only.
	unset( $attributes['preview'] );

	return render_block(
		array(
			'blockName'    => 'whmcs-price/' . $block,
			'attrs'        => $attributes,
			'innerBlocks'  => array(),
			'innerHTML'    => '',
			'innerContent' => array(),
		)
	);
}

/**
 * Option lists shared by the builder controls, matching the block editor.
 *
 * @since  2.10.1
 * @return array<string,array<string,string>>
 */
function whmcs_price_builder_options(): array {
	$reg_periods = array();
	for ( $i = 1; $i <= 10; $i++ ) {
		/* translators: %d: number of years */
		$reg_periods[ $i . 'y' ] = sprintf( _n( '%d Year', '%d Years', $i, 'whmcs-price' ), $i );
	}

	return array(
		'billing_cycles'    => array(
			'1m' => __( 'Monthly (1m)', 'whmcs-price' ),
			'3m' => __( 'Quarterly (3m)', 'whmcs-price' ),
			'6m' => __( 'Semi-Annually (6m)', 'whmcs-price' ),
			'1y' => __( 'Annually (1y)', 'whmcs-price' ),
			'2y' => __( 'Biennially (2y)', 'whmcs-price' ),
			'3y' => __( 'Triennially (3y)', 'whmcs-price' ),
		),
		'product_fields'    => array(
			'name'        => __( 'Name', 'whmcs-price' ),
			'description' => __( 'Description', 'whmcs-price' ),
			'price'       => __( 'Price', 'whmcs-price' ),
			'setupfee'    => __( 'Setup Fee', 'whmcs-price' ),
		),
		'product_styles'    => array(
			'table' => __( 'Table (Classic)', 'whmcs-price' ),
			'cards' => __( 'Cards', 'whmcs-price' ),
			'grid'  => __( 'Pricing Grid', 'whmcs-price' ),
		),
		'per_period'        => array(
			''      => __( 'Disabled', 'whmcs-price' ),
			'month' => __( 'Per month — e.g. $99/yr ($8.25/mo)', 'whmcs-price' ),
			'week'  => __( 'Per week — e.g. $99/yr ($1.90/wk)', 'whmcs-price' ),
			'day'   => __( 'Per day — e.g. $99/yr ($0.27/day)', 'whmcs-price' ),
		),
		'transaction_types' => array(
			'register' => __( 'Register', 'whmcs-price' ),
			'renew'    => __( 'Renew', 'whmcs-price' ),
			'transfer' => __( 'Transfer', 'whmcs-price' ),
		),
		'reg_periods'       => $reg_periods,
		'domain_styles'     => array(
			'table'  => __( 'Table (Classic)', 'whmcs-price' ),
			'badge'  => __( 'Badge', 'whmcs-price' ),
			'inline' => __( 'Inline', 'whmcs-price' ),
		),
	);
}

/**
 * Keep only digits of a WHMCS currency ID ('' means automatic).
 *
 * @since  2.10.1
 * @param  mixed $value Raw control value.
 * @return string
 */
function whmcs_price_builder_currency( $value ): string {
	return preg_replace( '/[^0-9]/', '', (string) $value );
}
