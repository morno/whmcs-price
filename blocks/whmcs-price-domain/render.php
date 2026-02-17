<?php
/**
 * WHMCS Domain Price Block - Server-Side Render
 *
 * @package    WHMCS_Price
 * @subpackage Blocks
 * @since      2.3.0
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$whmcs_tld              = ! empty( $attributes['tld'] ) ? sanitize_text_field( $attributes['tld'] ) : '';
$whmcs_transaction_type = ! empty( $attributes['transactionType'] ) ? sanitize_text_field( $attributes['transactionType'] ) : 'register';
$whmcs_reg_period_raw   = ! empty( $attributes['regPeriod'] ) ? sanitize_text_field( $attributes['regPeriod'] ) : '1y';
$whmcs_show_all         = ! empty( $attributes['showAll'] ) && true === $attributes['showAll'];
$whmcs_display_style    = ! empty( $attributes['displayStyle'] ) ? sanitize_text_field( $attributes['displayStyle'] ) : 'table';
// phpcs:enable

$whmcs_reg_period = str_replace( 'y', '', $whmcs_reg_period_raw );
$whmcs_wrapper_class = 'whmcs-domain-display whmcs-domain-display--' . esc_attr( $whmcs_display_style );

?>
<div <?php echo wp_kses_post( get_block_wrapper_attributes( array( 'class' => $whmcs_wrapper_class ) ) ); ?>>

	<?php if ( empty( $whmcs_tld ) ) : ?>
		<?php
		// No TLD = show all TLDs from WHMCS domainpricing.php
		$whmcs_all_prices = WHMCS_Price_API::get_all_domain_prices();
		?>
		<div class="whmcs-domain-all">
			<?php echo wp_kses_post( $whmcs_all_prices ); ?>
		</div>

	<?php elseif ( $whmcs_show_all ) : ?>
		<?php
		$whmcs_types = array(
			'register' => __( 'Register', 'whmcs-price' ),
			'renew'    => __( 'Renew', 'whmcs-price' ),
			'transfer' => __( 'Transfer', 'whmcs-price' ),
		);
		?>

		<?php if ( 'table' === $whmcs_display_style ) : ?>
			<table class="whmcs-domain-table">
				<thead>
					<tr>
						<?php foreach ( $whmcs_types as $whmcs_type_label ) : ?>
							<th><?php echo esc_html( $whmcs_type_label ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
						<?php foreach ( array_keys( $whmcs_types ) as $whmcs_type_key ) : ?>
							<td><?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_type_key, $whmcs_reg_period ) ); ?></td>
						<?php endforeach; ?>
					</tr>
				</tbody>
			</table>
		<?php elseif ( 'badge' === $whmcs_display_style ) : ?>
			<div class="whmcs-domain-badges">
				<div class="whmcs-domain-badges__tld">.<?php echo esc_html( $whmcs_tld ); ?></div>
				<?php foreach ( $whmcs_types as $whmcs_type_key => $whmcs_type_label ) : ?>
					<div class="whmcs-domain-badge">
						<span class="whmcs-domain-badge__label"><?php echo esc_html( $whmcs_type_label ); ?></span>
						<span class="whmcs-domain-badge__price"><?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_type_key, $whmcs_reg_period ) ); ?></span>
					</div>
				<?php endforeach; ?>
			</div>
		<?php else : // inline ?>
			<div class="whmcs-domain-inline">
				<strong class="whmcs-domain-inline__tld">.<?php echo esc_html( $whmcs_tld ); ?></strong>
				<?php foreach ( $whmcs_types as $whmcs_type_key => $whmcs_type_label ) : ?>
					<span class="whmcs-domain-inline__item">
						<?php echo esc_html( $whmcs_type_label ); ?>: <strong><?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_type_key, $whmcs_reg_period ) ); ?></strong>
					</span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

	<?php else : ?>

		<?php if ( 'badge' === $whmcs_display_style ) : ?>
			<div class="whmcs-domain-badge whmcs-domain-badge--single">
				<span class="whmcs-domain-badge__tld">.<?php echo esc_html( $whmcs_tld ); ?></span>
				<span class="whmcs-domain-badge__label">
					<?php
					$whmcs_type_labels = array(
						'register' => __( 'Register', 'whmcs-price' ),
						'renew'    => __( 'Renew', 'whmcs-price' ),
						'transfer' => __( 'Transfer', 'whmcs-price' ),
					);
					echo esc_html( $whmcs_type_labels[ $whmcs_transaction_type ] ?? ucfirst( $whmcs_transaction_type ) );
					?>
				</span>
				<span class="whmcs-domain-badge__price"><?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) ); ?></span>
			</div>
		<?php elseif ( 'inline' === $whmcs_display_style ) : ?>
			<span class="whmcs-domain-inline whmcs-domain-inline--single">
				<strong>.<?php echo esc_html( $whmcs_tld ); ?></strong> —
				<?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) ); ?>
			</span>
		<?php else : ?>
			<div class="whmcs-price">
				<?php echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) ); ?>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div>
