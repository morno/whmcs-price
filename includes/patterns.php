<?php
/**
 * Block patterns for common pricing layouts
 *
 * Registers a "WHMCS Pricing" pattern category with ready-made layouts built
 * from the plugin's own blocks (whmcs-price/product, whmcs-price/domain) and
 * core layout blocks. No new block types.
 *
 * Product blocks are inserted without a product ID, so the editor shows the
 * block's "enter a Product ID" placeholder and nothing renders on the site
 * until one is set. Domain blocks use common example TLDs. Every pattern
 * starts with a note telling the editor what to replace; it is meant to be
 * deleted.
 *
 * @package    WHMCS_Price
 * @subpackage Patterns
 * @since      2.10.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Block comment for one of the plugin's blocks.
 *
 * @since  2.10.0
 * @param  string $name       Block name without namespace: product | domain.
 * @param  array  $attributes Block attributes.
 * @return string
 */
function whmcs_price_pattern_block( string $name, array $attributes ): string {
	return '<!-- wp:whmcs-price/' . $name . ' ' . wp_json_encode( $attributes, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . ' /-->';
}

/**
 * The "replace the placeholders" note shown at the top of each pattern.
 *
 * @since  2.10.0
 * @param  string $text What to replace.
 * @return string
 */
function whmcs_price_pattern_note( string $text ): string {
	return '<!-- wp:paragraph {"className":"whmcs-price-pattern-note","fontSize":"small"} -->'
		. '<p class="whmcs-price-pattern-note has-small-font-size">✏️ ' . esc_html( $text ) . '</p>'
		. '<!-- /wp:paragraph -->';
}

/**
 * Heading block.
 *
 * @since  2.10.0
 * @param  string $text  Heading text.
 * @param  int    $level Heading level.
 * @param  string $align Optional text alignment.
 * @return string
 */
function whmcs_price_pattern_heading( string $text, int $level = 2, string $align = '' ): string {
	$attrs = array();
	if ( 2 !== $level ) {
		$attrs['level'] = $level;
	}
	if ( $align ) {
		$attrs['textAlign'] = $align;
	}
	$class = 'wp-block-heading' . ( $align ? ' has-text-align-' . $align : '' );
	return '<!-- wp:heading' . ( $attrs ? ' ' . wp_json_encode( $attrs ) : '' ) . ' -->'
		. '<h' . $level . ' class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</h' . $level . '>'
		. '<!-- /wp:heading -->';
}

/**
 * Paragraph block.
 *
 * @since  2.10.0
 * @param  string $text  Paragraph text.
 * @param  string $align Optional alignment.
 * @return string
 */
function whmcs_price_pattern_paragraph( string $text, string $align = '' ): string {
	return '<!-- wp:paragraph' . ( $align ? ' {"align":"' . $align . '"}' : '' ) . ' -->'
		. '<p' . ( $align ? ' class="has-text-align-' . esc_attr( $align ) . '"' : '' ) . '>' . esc_html( $text ) . '</p>'
		. '<!-- /wp:paragraph -->';
}

/**
 * Columns block with one column per inner content string.
 *
 * @since  2.10.0
 * @param  string[] $columns Inner block markup per column.
 * @return string
 */
function whmcs_price_pattern_columns( array $columns ): string {
	$html = '<!-- wp:columns --><div class="wp-block-columns">';
	foreach ( $columns as $inner ) {
		$html .= '<!-- wp:column --><div class="wp-block-column">' . $inner . '</div><!-- /wp:column -->';
	}
	return $html . '</div><!-- /wp:columns -->';
}

/**
 * Pattern definitions: slug => args for register_block_pattern().
 *
 * @since  2.10.0
 * @return array<string, array>
 */
function whmcs_price_pattern_definitions(): array {
	$product_note = __( 'Add your WHMCS product IDs in the block settings (sidebar), then delete this note.', 'whmcs-price' );
	$domain_note  = __( 'Replace the example TLDs with the ones you sell (block settings), then delete this note.', 'whmcs-price' );

	return array(
		'whmcs-price/three-column-pricing' => array(
			'title'       => __( 'Pricing comparison (3 products)', 'whmcs-price' ),
			'description' => __( 'Three hosting plans side by side as cards, with name, price and description.', 'whmcs-price' ),
			'keywords'    => array( 'pricing', 'plans', 'hosting', 'comparison' ),
			'content'     => whmcs_price_pattern_note( __( 'Enter three WHMCS product IDs separated by commas (e.g. 1,2,3) in the block settings, then delete this note.', 'whmcs-price' ) )
				. whmcs_price_pattern_heading( __( 'Choose your plan', 'whmcs-price' ), 2, 'center' )
				. whmcs_price_pattern_paragraph( __( 'All plans include free SSL, daily backups and support.', 'whmcs-price' ), 'center' )
				. whmcs_price_pattern_block(
					'product',
					array(
						'pid'          => '',
						'billingCycle' => '1y',
						'show'         => array( 'name', 'price', 'description' ),
						'displayStyle' => 'cards',
						'perPeriod'    => 'month',
						'align'        => 'wide',
					)
				),
		),

		'whmcs-price/featured-product' => array(
			'title'       => __( 'Featured product', 'whmcs-price' ),
			'description' => __( 'A single highlighted product with heading, price card and an order button.', 'whmcs-price' ),
			'keywords'    => array( 'hero', 'featured', 'product', 'offer' ),
			'content'     => whmcs_price_pattern_note( $product_note . ' ' . __( 'Point the button at your WHMCS order link.', 'whmcs-price' ) )
				. '<!-- wp:group {"layout":{"type":"constrained","contentSize":"560px"}} --><div class="wp-block-group">'
				. whmcs_price_pattern_heading( __( 'Our most popular plan', 'whmcs-price' ), 2, 'center' )
				. whmcs_price_pattern_block(
					'product',
					array(
						'pid'          => '',
						'billingCycle' => '1y',
						'show'         => array( 'name', 'price', 'setupfee', 'description' ),
						'displayStyle' => 'cards',
						'perPeriod'    => 'month',
					)
				)
				. '<!-- wp:buttons {"layout":{"type":"flex","justifyContent":"center"}} --><div class="wp-block-buttons">'
				. '<!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#">' . esc_html__( 'Order now', 'whmcs-price' ) . '</a></div><!-- /wp:button -->'
				. '</div><!-- /wp:buttons -->'
				. '</div><!-- /wp:group -->',
		),

		'whmcs-price/domain-grid' => array(
			'title'       => __( 'Domain prices (6 TLDs)', 'whmcs-price' ),
			'description' => __( 'Registration prices for six domain extensions in a grid.', 'whmcs-price' ),
			'keywords'    => array( 'domains', 'tld', 'registration', 'grid' ),
			'content'     => whmcs_price_pattern_note( $domain_note )
				. whmcs_price_pattern_heading( __( 'Find your domain', 'whmcs-price' ), 2, 'center' )
				. whmcs_price_pattern_columns(
					array_map(
						static fn( $tld ) => whmcs_price_pattern_block( 'domain', array( 'tld' => $tld, 'transactionType' => 'register', 'regPeriod' => '1y', 'displayStyle' => 'badge' ) ),
						array( 'com', 'net', 'org' )
					)
				)
				. whmcs_price_pattern_columns(
					array_map(
						static fn( $tld ) => whmcs_price_pattern_block( 'domain', array( 'tld' => $tld, 'transactionType' => 'register', 'regPeriod' => '1y', 'displayStyle' => 'badge' ) ),
						array( 'io', 'eu', 'se' )
					)
				),
		),

		'whmcs-price/domain-table' => array(
			'title'       => __( 'Domain price table (1 TLD)', 'whmcs-price' ),
			'description' => __( 'Register, renew and transfer prices for one domain extension.', 'whmcs-price' ),
			'keywords'    => array( 'domains', 'tld', 'renew', 'transfer' ),
			'content'     => whmcs_price_pattern_note( $domain_note )
				. whmcs_price_pattern_heading( __( '.com domains', 'whmcs-price' ) )
				. whmcs_price_pattern_block( 'domain', array( 'tld' => 'com', 'showAll' => true, 'regPeriod' => '1y', 'displayStyle' => 'table' ) ),
		),

		'whmcs-price/product-and-domains' => array(
			'title'       => __( 'Hosting plan with domains', 'whmcs-price' ),
			'description' => __( 'One featured hosting plan with a strip of three domain prices below.', 'whmcs-price' ),
			'keywords'    => array( 'hosting', 'domains', 'bundle' ),
			'content'     => whmcs_price_pattern_note( __( 'Add your WHMCS product ID and replace the example TLDs in the block settings, then delete this note.', 'whmcs-price' ) )
				. whmcs_price_pattern_heading( __( 'Everything you need to get online', 'whmcs-price' ), 2, 'center' )
				. whmcs_price_pattern_block(
					'product',
					array(
						'pid'          => '',
						'billingCycle' => '1y',
						'show'         => array( 'name', 'price', 'description' ),
						'displayStyle' => 'cards',
						'perPeriod'    => 'month',
					)
				)
				. whmcs_price_pattern_heading( __( 'Add a domain', 'whmcs-price' ), 3, 'center' )
				. whmcs_price_pattern_columns(
					array_map(
						static fn( $tld ) => whmcs_price_pattern_block( 'domain', array( 'tld' => $tld, 'transactionType' => 'register', 'regPeriod' => '1y', 'displayStyle' => 'inline' ) ),
						array( 'com', 'net', 'se' )
					)
				),
		),

		'whmcs-price/monthly-vs-yearly' => array(
			'title'       => __( 'Monthly vs yearly price', 'whmcs-price' ),
			'description' => __( 'The same product billed monthly and yearly side by side, with the yearly price also shown per month.', 'whmcs-price' ),
			'keywords'    => array( 'billing', 'monthly', 'yearly', 'annual', 'save' ),
			'content'     => whmcs_price_pattern_note( __( 'Enter the same WHMCS product ID in both blocks (block settings), then delete this note.', 'whmcs-price' ) )
				. whmcs_price_pattern_columns(
					array(
						whmcs_price_pattern_heading( __( 'Monthly', 'whmcs-price' ), 3, 'center' )
							. whmcs_price_pattern_block( 'product', array( 'pid' => '', 'billingCycle' => '1m', 'show' => array( 'name', 'price' ), 'displayStyle' => 'grid' ) ),
						whmcs_price_pattern_heading( __( 'Yearly', 'whmcs-price' ), 3, 'center' )
							. whmcs_price_pattern_block( 'product', array( 'pid' => '', 'billingCycle' => '1y', 'show' => array( 'name', 'price' ), 'displayStyle' => 'grid', 'perPeriod' => 'month' ) ),
					)
				),
		),
	);
}

/**
 * Register the pattern category and patterns.
 *
 * @since  2.10.0
 * @return void
 */
function whmcs_price_register_patterns(): void {
	if ( ! function_exists( 'register_block_pattern' ) ) {
		return;
	}

	register_block_pattern_category(
		'whmcs-pricing',
		array(
			'label'       => __( 'WHMCS Pricing', 'whmcs-price' ),
			'description' => __( 'Pricing layouts using live prices from WHMCS.', 'whmcs-price' ),
		)
	);

	foreach ( whmcs_price_pattern_definitions() as $slug => $pattern ) {
		register_block_pattern(
			$slug,
			$pattern + array(
				'categories'    => array( 'whmcs-pricing' ),
				'viewportWidth' => 1100,
			)
		);
	}
}
add_action( 'init', 'whmcs_price_register_patterns' );
