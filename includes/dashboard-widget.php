<?php
/**
 * Dashboard Widget — WHMCS Price Cache Status
 *
 * Adds a small widget to the WordPress admin dashboard showing:
 *   - How many price entries are currently cached
 *   - How many stampede-lock transients are active
 *   - When the earliest cache entry expires
 *   - A one-click cache-clear button
 *
 * Visible only to users with manage_options capability.
 *
 * @package    WHMCS_Price
 * @subpackage Dashboard
 * @since      2.8.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Register the dashboard widget.
 *
 * @since 2.8.0
 * @return void
 */
add_action( 'wp_dashboard_setup', function() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	wp_add_dashboard_widget(
		'whmcs_price_cache_status',
		__( 'WHMCS Price — Cache Status', 'whmcs-price' ),
		'whmcs_price_dashboard_widget_render'
	);
} );

/**
 * Render the dashboard widget content.
 *
 * @since  2.8.0
 * @return void
 */
function whmcs_price_dashboard_widget_render(): void {
	global $wpdb;

	$using_obj_cache = wp_using_ext_object_cache();
	$version         = (int) get_option( 'whmcs_price_cache_version', 1 );

	// SQL-based counts only have meaning when transients are written to the
	// options table. On persistent object cache backends they are stored in
	// cache, so we surface "—" with a tooltip rather than misleading zeros.
	if ( $using_obj_cache ) {
		$cache_count = null;
		$lock_count  = null;
		$min_timeout = null;
	} else {
		$cache_like   = $wpdb->esc_like( '_transient_v' . $version . '_whmcs_' ) . '%';
		$lock_like    = $wpdb->esc_like( 'lock_whmcs_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_v' . $version . '_whmcs_' ) . '%';

		$cache_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $cache_like )
		);
		$lock_count  = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $lock_like )
		);
		$min_timeout = $wpdb->get_var(
			$wpdb->prepare( "SELECT MIN(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like )
		);
	}

	$options    = get_option( 'whmcs_price_option', array() );
	$whmcs_url  = ! empty( $options['whmcs_url'] ) ? $options['whmcs_url'] : '';
	$is_outage  = false !== get_transient( 'whmcs_price_outage_notified' );

	$clear_url = wp_nonce_url(
		add_query_arg( 'whmcs_clear_cache', '1', admin_url( 'options-general.php?page=whmcs_price' ) ),
		'whmcs_clear_cache_admin_bar'
	);
	?>
	<div style="display:flex;flex-direction:column;gap:10px;">

		<?php if ( $is_outage ) : ?>
		<div class="notice notice-warning inline" style="margin:0;padding:8px 12px;">
			<strong><?php esc_html_e( 'Active outage:', 'whmcs-price' ); ?></strong>
			<?php esc_html_e( 'WHMCS is currently unreachable. Visitors see the fallback or unavailable message.', 'whmcs-price' ); ?>
		</div>
		<?php endif; ?>

		<?php if ( $using_obj_cache ) : ?>
		<p class="description" style="margin:0;">
			<em><?php esc_html_e( 'Using persistent object cache — entry counts unavailable.', 'whmcs-price' ); ?></em>
		</p>
		<?php endif; ?>

		<table class="widefat striped" style="border:none;">
			<tbody>
				<tr>
					<td><?php esc_html_e( 'Cached entries', 'whmcs-price' ); ?></td>
					<td><strong><?php echo esc_html( null === $cache_count ? '—' : (string) $cache_count ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Active locks', 'whmcs-price' ); ?></td>
					<td><strong><?php echo esc_html( null === $lock_count ? '—' : (string) $lock_count ); ?></strong></td>
				</tr>
				<tr>
					<td><?php esc_html_e( 'Earliest expiry', 'whmcs-price' ); ?></td>
					<td>
						<strong>
						<?php
						if ( null === $min_timeout ) {
							echo esc_html( '—' );
						} elseif ( $min_timeout ) {
							$diff = (int) $min_timeout - time();
							if ( $diff > 0 ) {
								$hours   = floor( $diff / 3600 );
								$minutes = floor( ( $diff % 3600 ) / 60 );
								echo $hours > 0
									/* translators: 1: hours, 2: minutes */
									? esc_html( sprintf( __( '%1$dh %2$dm', 'whmcs-price' ), $hours, $minutes ) )
									/* translators: %d: minutes */
									: esc_html( sprintf( __( '%d min', 'whmcs-price' ), $minutes ) );
							} else {
								esc_html_e( 'Expired', 'whmcs-price' );
							}
						} else {
							esc_html_e( 'No cache', 'whmcs-price' );
						}
						?>
						</strong>
					</td>
				</tr>
				<?php if ( $whmcs_url ) : ?>
				<tr>
					<td><?php esc_html_e( 'WHMCS URL', 'whmcs-price' ); ?></td>
					<td style="word-break:break-all;"><small><?php echo esc_html( $whmcs_url ); ?></small></td>
				</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<div style="display:flex;gap:8px;align-items:center;">
			<a href="<?php echo esc_url( $clear_url ); ?>" class="button button-secondary">
				<?php esc_html_e( 'Clear Cache', 'whmcs-price' ); ?>
			</a>
			<a href="<?php echo esc_url( admin_url( 'options-general.php?page=whmcs_price' ) ); ?>" style="font-size:12px;">
				<?php esc_html_e( 'Settings ↗', 'whmcs-price' ); ?>
			</a>
		</div>

	</div>
	<?php
}
