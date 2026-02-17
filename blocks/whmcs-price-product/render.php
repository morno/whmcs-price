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

// Variables are locally scoped inside this render file — not global.
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$whmcs_pid           = ! empty( $attributes['pid'] ) ? sanitize_text_field( $attributes['pid'] ) : '';
$whmcs_billing_cycle = ! empty( $attributes['billingCycle'] ) ? sanitize_text_field( $attributes['billingCycle'] ) : '1y';
$whmcs_show          = ! empty( $attributes['show'] ) && is_array( $attributes['show'] ) ? $attributes['show'] : array( 'name', 'price' );
$whmcs_display_style = ! empty( $attributes['displayStyle'] ) ? sanitize_text_field( $attributes['displayStyle'] ) : 'table';
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
$whmcs_pids      = array_map( 'trim', explode( ',', $whmcs_pid ) );

// Translatable column header labels.
$whmcs_header_labels = array(
	'name'        => __( 'Name', 'whmcs-price' ),
	'description' => __( 'Description', 'whmcs-price' ),
	'price'       => __( 'Price', 'whmcs-price' ),
);

$whmcs_wrapper_class = 'whmcs-product-display whmcs-product-display--' . esc_attr( $whmcs_display_style );

?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => $whmcs_wrapper_class ) ) ); ?>>

	<?php if ( 'table' === $whmcs_display_style ) : ?>
		<?php // Table style - classic presentation. ?>
		<table class="whmcs-product-table">
			<thead>
				<tr>
					<?php foreach ( $whmcs_show as $whmcs_column ) : ?>
						<th><?php echo esc_html( $whmcs_header_labels[ strtolower( trim( $whmcs_column ) ) ] ?? ucfirst( $whmcs_column ) ); ?></th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
					<tr>
						<?php foreach ( $whmcs_show as $whmcs_attr ) : ?>
							<td><?php echo esc_html( WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) ) ); ?></td>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

	<?php elseif ( 'cards' === $whmcs_display_style ) : ?>
		<?php // Cards style - modern card-based layout. ?>
		<div class="whmcs-product-cards">
			<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
				<div class="whmcs-product-card">
					<?php foreach ( $whmcs_show as $whmcs_attr ) : ?>
						<?php
						$whmcs_value = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) );
						$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
						?>
						<div class="whmcs-product-card__<?php echo esc_attr( $whmcs_attr_clean ); ?>">
							<?php if ( 'price' === $whmcs_attr_clean ) : ?>
								<span class="whmcs-product-card__price-value"><?php echo esc_html( $whmcs_value ); ?></span>
							<?php elseif ( 'name' === $whmcs_attr_clean ) : ?>
								<h3 class="whmcs-product-card__title"><?php echo esc_html( $whmcs_value ); ?></h3>
							<?php else : ?>
								<p class="whmcs-product-card__description"><?php echo esc_html( $whmcs_value ); ?></p>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php elseif ( 'grid' === $whmcs_display_style ) : ?>
		<?php // Grid style - compact pricing grid. ?>
		<div class="whmcs-product-grid">
			<?php foreach ( $whmcs_pids as $whmcs_single_pid ) : ?>
				<div class="whmcs-product-grid-item">
					<?php foreach ( $whmcs_show as $whmcs_attr ) : ?>
						<?php
						$whmcs_value = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) );
						$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
						$whmcs_label = $whmcs_header_labels[ $whmcs_attr_clean ] ?? ucfirst( $whmcs_attr );
						?>
						<div class="whmcs-product-grid-item__field">
							<span class="whmcs-product-grid-item__label"><?php echo esc_html( $whmcs_label ); ?></span>
							<span class="whmcs-product-grid-item__value"><?php echo esc_html( $whmcs_value ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

	<?php endif; ?>

</div>
