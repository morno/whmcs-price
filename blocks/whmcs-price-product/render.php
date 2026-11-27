<?php
/**
 * WHMCS Product Price Block - Server-Side Render
 *
 * Renders the product pricing block with different visual styles based
 * on the displayStyle attribute (table, cards, grid).
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
 * @since      2.3.0
 *
 * @var array    $attributes Block attributes set in the editor.
 * @var string   $content    Inner block content (unused — no inner blocks).
 * @var WP_Block $block      Block instance.
 */

defined( 'ABSPATH' ) || exit;

// Inserter preview (block.json "example") is rendered client-side in edit.js
// with placeholder data. Never fetch live prices for it.
if ( ! empty( $attributes['preview'] ) ) {
	return;
}

// Skip during autosave — no need to fetch live prices for a draft save.
if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	echo '<!-- whmcs-price block -->';
	return;
}

// Variables are locally scoped inside this render file — not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$whmcs_pid           = ! empty( $attributes['pid'] ) ? sanitize_text_field( $attributes['pid'] ) : '';
$whmcs_billing_cycle = ! empty( $attributes['billingCycle'] ) ? sanitize_text_field( $attributes['billingCycle'] ) : '1y';
$whmcs_show          = ! empty( $attributes['show'] ) && is_array( $attributes['show'] ) ? $attributes['show'] : array( 'name', 'price' );
$whmcs_display_style = ! empty( $attributes['displayStyle'] ) ? sanitize_text_field( $attributes['displayStyle'] ) : 'table';
$whmcs_per_period    = ! empty( $attributes['perPeriod'] ) ? sanitize_text_field( $attributes['perPeriod'] ) : '';
// Empty = currency for the current language (WPML/Polylang), else WHMCS default.
$whmcs_currency      = whmcs_price_currency_arg( $attributes['currency'] ?? '' );
// phpcs:enable

// Require a Product ID to render anything meaningful.
if ( empty( $whmcs_pid ) ) {
	if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		echo '<p class="whmcs-block-placeholder">' . esc_html__( 'WHMCS Product Price: Please enter a Product ID in the block settings.', 'whmcs-price' ) . '</p>';
	}
	return;
}

// Map short billing cycle codes to WHMCS internal names.
$whmcs_billing_cycles = array(
	'1m' => 'monthly',
	'3m' => 'quarterly',
	'6m' => 'semiannually',
	'1y' => 'annually',
	'2y' => 'biennially',
	'3y' => 'triennially',
);

$whmcs_bc_mapped = isset( $whmcs_billing_cycles[ $whmcs_billing_cycle ] ) ? $whmcs_billing_cycles[ $whmcs_billing_cycle ] : 'annually';
$whmcs_pids = array_filter(
    array_map( 'intval', explode( ',', $whmcs_pid ) ),
    fn( $p ) => $p > 0
);

// Allowlist: only permit known display styles.
$whmcs_allowed_styles = array( 'table', 'cards', 'grid' );
if ( ! in_array( $whmcs_display_style, $whmcs_allowed_styles, true ) ) {
	$whmcs_display_style = 'table';
}

// Allowlist: only permit known column values in the show array.
$whmcs_allowed_columns = array( 'name', 'description', 'price', 'setupfee' );
$whmcs_show = array_values( array_filter(
	$whmcs_show,
	fn( $col ) => in_array( $col, $whmcs_allowed_columns, true )
) );

// Translatable column header labels.
$whmcs_header_labels = array(
	'name'        => __( 'Name', 'whmcs-price' ),
	'description' => __( 'Description', 'whmcs-price' ),
	'price'       => __( 'Price', 'whmcs-price' ),
	'setupfee'    => __( 'Setup Fee', 'whmcs-price' ),
);

$whmcs_wrapper_class = 'whmcs-product-display whmcs-product-display--' . esc_attr( $whmcs_display_style );

?>
<div <?php echo get_block_wrapper_attributes( array( 'class' => $whmcs_wrapper_class ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped; ?>>

	<?php if ( 'table' === $whmcs_display_style ) : ?>
		<?php // Table style - classic presentation. ?>
		<table class="whmcs-product-table">
			<thead>
				<tr>
					<?php foreach ( $whmcs_show as $whmcs_column ) : ?>
						<th scope="col"><?php echo esc_html( $whmcs_header_labels[ strtolower( trim( $whmcs_column ) ) ] ?? ucfirst( $whmcs_column ) ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
					<tr>
						<?php foreach ( $whmcs_show as $whmcs_attr ) : ?>
							<?php
							$whmcs_value      = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) , $whmcs_currency );
							$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
							if ( 'price' === $whmcs_attr_clean ) {
								$whmcs_value = whmcs_price_strip_setup_fee( $whmcs_value );
							}
							if ( 'price' === $whmcs_attr_clean && ! empty( $whmcs_per_period ) ) {
								$whmcs_value = whmcs_price_format_per( $whmcs_value, $whmcs_bc_mapped, 1, $whmcs_per_period );
							}
							// The product name identifies each row for screen readers.
							$whmcs_cell = 'name' === $whmcs_attr_clean ? 'th scope="row"' : 'td';
							?>
							<<?php echo $whmcs_cell; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed literal. ?>><?php if ( 'setupfee' === $whmcs_attr_clean ) {
								echo esc_html( WHMCS_Price_API::get_product_setup_fee( intval( $whmcs_single_pid ), $whmcs_bc_mapped , $whmcs_currency ) );
							} elseif ( 'NA' === $whmcs_value ) {
								echo whmcs_price_unavailable_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} elseif ( 'price' === $whmcs_attr_clean ) {
								echo wp_kses( $whmcs_value, whmcs_price_price_kses() );
							} else {
								echo esc_html( wp_strip_all_tags( $whmcs_value ) );
							} ?></<?php echo 'name' === $whmcs_attr_clean ? 'th' : 'td'; ?>>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php elseif ( 'cards' === $whmcs_display_style ) : ?>
		<?php // Cards style - modern card-based layout. ?>
		<?php
		// Reorder: setupfee always renders before description for consistent card layout.
		$whmcs_show_cards = $whmcs_show;
		$sf_pos   = array_search( 'setupfee', $whmcs_show_cards, true );
		$desc_pos = array_search( 'description', $whmcs_show_cards, true );
		if ( false !== $sf_pos && false !== $desc_pos && $sf_pos > $desc_pos ) {
			unset( $whmcs_show_cards[ $sf_pos ] );
			array_splice( $whmcs_show_cards, $desc_pos, 0, array( 'setupfee' ) );
		}
		?>
		<div class="whmcs-product-cards">
			<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
				<?php
				// Each card is a self-contained article, labelled by its title.
				$whmcs_title_id = in_array( 'name', $whmcs_show_cards, true ) ? wp_unique_id( 'whmcs-product-title-' ) : '';
				$whmcs_heading  = whmcs_price_card_heading_tag();
				?>
				<article class="whmcs-product-card"<?php echo $whmcs_title_id ? ' aria-labelledby="' . esc_attr( $whmcs_title_id ) . '"' : ''; ?>>
					<?php foreach ( $whmcs_show_cards as $whmcs_attr ) : ?>
						<?php
						$whmcs_value      = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) , $whmcs_currency );
						$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
						if ( 'price' === $whmcs_attr_clean ) {
							$whmcs_value = whmcs_price_strip_setup_fee( $whmcs_value );
						}
						if ( 'price' === $whmcs_attr_clean && ! empty( $whmcs_per_period ) ) {
							$whmcs_value = whmcs_price_format_per( $whmcs_value, $whmcs_bc_mapped, 1, $whmcs_per_period );
						}
						?>
						<div class="whmcs-product-card__<?php echo esc_attr( $whmcs_attr_clean ); ?>">
							<?php if ( 'setupfee' === $whmcs_attr_clean ) : ?>
								<span class="whmcs-product-card__setupfee-label"><?php echo esc_html__( 'Setup Fee', 'whmcs-price' ); ?>:</span>
								<span class="whmcs-product-card__setupfee-value"><?php echo esc_html( WHMCS_Price_API::get_product_setup_fee( intval( $whmcs_single_pid ), $whmcs_bc_mapped , $whmcs_currency ) ); ?></span>
							<?php elseif ( 'NA' === $whmcs_value ) : ?>
								<?php echo whmcs_price_unavailable_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php elseif ( 'price' === $whmcs_attr_clean ) : ?>
								<span class="whmcs-product-card__price-value"><?php echo wp_kses( $whmcs_value, whmcs_price_price_kses() ); ?></span>
							<?php elseif ( 'name' === $whmcs_attr_clean ) : ?>
								<<?php echo esc_attr( $whmcs_heading ); ?> class="whmcs-product-card__title" id="<?php echo esc_attr( $whmcs_title_id ); ?>"><?php echo esc_html( $whmcs_value ); ?></<?php echo esc_attr( $whmcs_heading ); ?>>
							<?php else : ?>
								<p class="whmcs-product-card__description"><?php echo esc_html( $whmcs_value ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</article>
			<?php endforeach; ?>
		</div>

	<?php elseif ( 'grid' === $whmcs_display_style ) : ?>
		<?php // Grid style - compact pricing grid. ?>
		<div class="whmcs-product-grid">
			<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
				<?php
				// Group each product's fields under its name for screen readers.
				$whmcs_group_label = in_array( 'name', $whmcs_show, true )
					? wp_strip_all_tags( WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, 'name' , $whmcs_currency ) )
					: '';
				?>
				<div class="whmcs-product-grid-item" role="group"<?php echo ( '' !== $whmcs_group_label && 'NA' !== $whmcs_group_label ) ? ' aria-label="' . esc_attr( $whmcs_group_label ) . '"' : ''; ?>>
					<?php foreach ( $whmcs_show as $whmcs_attr ) : ?>
						<?php
						$whmcs_value      = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) , $whmcs_currency );
						$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
						$whmcs_label      = $whmcs_header_labels[ $whmcs_attr_clean ] ?? ucfirst( $whmcs_attr );
						if ( 'price' === $whmcs_attr_clean ) {
							$whmcs_value = whmcs_price_strip_setup_fee( $whmcs_value );
						}
						if ( 'price' === $whmcs_attr_clean && ! empty( $whmcs_per_period ) ) {
							$whmcs_value = whmcs_price_format_per( $whmcs_value, $whmcs_bc_mapped, 1, $whmcs_per_period );
						}
						?>
						<div class="whmcs-product-grid-item__field">
							<span class="whmcs-product-grid-item__label"><?php echo esc_html( $whmcs_label ); ?></span>
							<span class="whmcs-product-grid-item__value"><?php if ( 'setupfee' === $whmcs_attr_clean ) {
								echo esc_html( WHMCS_Price_API::get_product_setup_fee( intval( $whmcs_single_pid ), $whmcs_bc_mapped , $whmcs_currency ) );
							} elseif ( 'NA' === $whmcs_value ) {
								echo whmcs_price_unavailable_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
							} elseif ( 'price' === $whmcs_attr_clean ) {
								echo wp_kses( $whmcs_value, whmcs_price_price_kses() );
							} else {
								echo esc_html( wp_strip_all_tags( $whmcs_value ) );
							} ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

	<?php echo whmcs_price_promo_notice( 'product' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
</div>
