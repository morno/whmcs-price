<?php
/**
 * Plugin Settings and Admin UI
 *
 * Handles the admin menu, settings registration, and provides
 * usage documentation within the WordPress dashboard.
 *
 * @package    WHMCS_Price
 * @subpackage Admin
 * @since      2.2.0
 */

defined( 'ABSPATH' ) || exit;

class WHMCSPrice {

	private array $options = array();

	/**
	 * GitHub Wiki base URL for documentation links.
	 *
	 * @since 2.5.5
	 * @var string
	 */
	private const DOCS_URL = 'https://github.com/morno/whmcs-price/wiki';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'whmcspr_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'whmcspr_init' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_clear_cache' ), 100 );
		add_action( 'admin_init', array( $this, 'handle_admin_bar_clear_cache_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WHMCS_PRICE_DIR . 'whmcs_price.php' ), array( $this, 'add_settings_link' ) );
	}

	public function add_settings_link( $links ) {
		$settings_link = '<a href="' . admin_url( 'options-general.php?page=whmcs_price' ) . '">' . __( 'Settings', 'whmcs-price' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	public function whmcspr_plugin_page() {
		add_options_page(
			__( 'WHMCS Price Options', 'whmcs-price' ),
			__( 'WHMCS Price Settings', 'whmcs-price' ),
			'manage_options',
			'whmcs_price',
			array( $this, 'whmcspr_admin_page' )
		);
	}

	public function whmcspr_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['whmcs_clear_cache'] ) ) {
			check_admin_referer( 'whmcs_clear_cache_action' );
			$this->clear_whmcs_cache();
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache cleared successfully!', 'whmcs-price' ) . '</p></div>';
		}

		if ( isset( $_GET['cache_cleared'] ) && '1' === $_GET['cache_cleared'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache cleared successfully!', 'whmcs-price' ) . '</p></div>';
		}

		$this->options = get_option( 'whmcs_price_option', array() );
		?>
		<div class="wrap">

			<h1>
				<?php esc_html_e( 'WHMCS Price Settings', 'whmcs-price' ); ?>
				<a href="<?php echo esc_url( self::DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer"
					class="page-title-action" style="text-decoration:none;">
					<?php esc_html_e( 'Documentation ↗', 'whmcs-price' ); ?>
				</a>
			</h1>

			<div id="whmcs-price-settings-wrap" style="display:flex; gap:24px; align-items:flex-start; margin-top:16px;">

				<!-- ── LEFT COLUMN: Settings form ───────────────────────── -->
				<div style="flex:1; min-width:0;">
					<form method="post" action="options.php">
						<?php settings_fields( 'price_option_group' ); ?>

						<?php $this->render_section_connection(); ?>
						<?php $this->render_section_performance(); ?>
						<?php $this->render_section_advanced(); ?>

						<?php submit_button( __( 'Save Changes', 'whmcs-price' ) ); ?>
					</form>
				</div>

				<!-- ── RIGHT COLUMN: Quick reference ────────────────────── -->
				<div style="width:360px; flex-shrink:0;">
					<?php $this->render_sidebar(); ?>
				</div>

			</div><!-- /#whmcs-price-settings-wrap -->

		</div><!-- /.wrap -->
		<?php
	}

	// =========================================================
	// SECTION RENDERERS
	// =========================================================

	/**
	 * Render the Connection section (WHMCS URL).
	 *
	 * @since 2.5.5
	 */
	private function render_section_connection() {
		$whmcs_url = $this->options['whmcs_url'] ?? '';
		?>
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					🔗 <?php esc_html_e( 'Connection', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="whmcs_url"><?php esc_html_e( 'WHMCS URL', 'whmcs-price' ); ?></label>
						</th>
						<td>
							<?php if ( ! empty( $whmcs_url ) && ! filter_var( $whmcs_url, FILTER_VALIDATE_URL ) ) : ?>
								<div class="notice notice-error inline" style="margin:0 0 8px;"><p>
									<?php esc_html_e( 'The saved URL does not appear to be valid.', 'whmcs-price' ); ?>
								</p></div>
							<?php endif; ?>

							<?php if ( ! empty( $whmcs_url ) && ! str_starts_with( strtolower( $whmcs_url ), 'https://' ) ) : ?>
								<div class="notice notice-warning inline" style="margin:0 0 8px;"><p>
									<strong><?php esc_html_e( 'Warning:', 'whmcs-price' ); ?></strong>
									<?php esc_html_e( 'The WHMCS URL must use HTTPS. HTTP URLs are blocked for security reasons.', 'whmcs-price' ); ?>
								</p></div>
							<?php endif; ?>

							<input
								type="url"
								id="whmcs_url"
								class="regular-text"
								style="direction:ltr;"
								name="whmcs_price_option[whmcs_url]"
								value="<?php echo esc_attr( $whmcs_url ); ?>"
								placeholder="https://billing.yourdomain.com"
							/>
							<p class="description">
								<?php esc_html_e( 'Base URL of your WHMCS installation. Must use HTTPS. No trailing slash.', 'whmcs-price' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Performance section (Cache Duration + Clear Cache).
	 *
	 * @since 2.5.5
	 */
	private function render_section_performance() {
		$current_ttl = isset( $this->options['cache_ttl'] ) ? (int) $this->options['cache_ttl'] : 3600;

		$ttl_options = array(
			3600  => __( '1 hour', 'whmcs-price' ),
			7200  => __( '2 hours', 'whmcs-price' ),
			10800 => __( '3 hours', 'whmcs-price' ),
			21600 => __( '6 hours', 'whmcs-price' ),
			43200 => __( '12 hours', 'whmcs-price' ),
			86400 => __( '24 hours', 'whmcs-price' ),
		);
		?>
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					⚡ <?php esc_html_e( 'Performance', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cache_ttl"><?php esc_html_e( 'Cache Duration', 'whmcs-price' ); ?></label>
						</th>
						<td>
							<select id="cache_ttl" name="whmcs_price_option[cache_ttl]">
								<?php foreach ( $ttl_options as $value => $label ) : ?>
									<option value="<?php echo absint( $value ); ?>" <?php selected( $current_ttl, $value ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'How long prices are cached before fetching fresh data from WHMCS.', 'whmcs-price' ); ?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Clear Cache', 'whmcs-price' ); ?></th>
						<td>
							<form method="post" action="" style="display:inline;">
								<?php wp_nonce_field( 'whmcs_clear_cache_action' ); ?>
								<input type="hidden" name="whmcs_clear_cache" value="1" />
								<input type="submit" class="button button-secondary"
									value="<?php esc_attr_e( 'Clear Cache Now', 'whmcs-price' ); ?>" />
							</form>
							<p class="description">
								<?php esc_html_e( 'Force fresh prices to be fetched from WHMCS on the next page load. Also available from the Admin Bar.', 'whmcs-price' ); ?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the Advanced section (Custom User-Agent).
	 *
	 * @since 2.5.5
	 */
	private function render_section_advanced() {
		$current_ua     = $this->options['custom_user_agent'] ?? '';
		$site_url       = get_bloginfo( 'url' );
		$plugin_version = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : '';
		$default_ua     = "WordPress ({$site_url}) whmcs-price/{$plugin_version}";
		?>
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					🔧 <?php esc_html_e( 'Advanced', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside">
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="custom_user_agent"><?php esc_html_e( 'Custom User-Agent', 'whmcs-price' ); ?></label>
						</th>
						<td>
							<input
								type="text"
								id="custom_user_agent"
								class="large-text"
								style="direction:ltr; font-family:monospace;"
								name="whmcs_price_option[custom_user_agent]"
								value="<?php echo esc_attr( $current_ua ); ?>"
								placeholder="<?php echo esc_attr( $default_ua ); ?>"
							/>
							<p class="description">
								<?php
								printf(
									/* translators: %s: the auto-generated default User-Agent string */
									esc_html__( 'Override the User-Agent sent to WHMCS. Useful for firewall allow-rules. Leave blank to use the default: %s', 'whmcs-price' ),
									'<code>' . esc_html( $default_ua ) . '</code>'
								);
								?>
							</p>
						</td>
					</tr>
				</table>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the right-hand sidebar with quick reference and docs link.
	 *
	 * @since 2.5.5
	 */
	private function render_sidebar() {
		?>
		<!-- Documentation links -->
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					📖 <?php esc_html_e( 'Documentation', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside" style="padding-bottom:12px;">
				<p style="margin-top:0;">
					<?php esc_html_e( 'Full documentation is available on the GitHub Wiki:', 'whmcs-price' ); ?>
				</p>
				<ul style="margin:0; padding-left:16px;">
					<li><a href="<?php echo esc_url( self::DOCS_URL . '/Getting-Started' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Getting Started', 'whmcs-price' ); ?> ↗</a></li>
					<li><a href="<?php echo esc_url( self::DOCS_URL . '/Displaying-Prices' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Shortcodes & Blocks', 'whmcs-price' ); ?> ↗</a></li>
					<li><a href="<?php echo esc_url( self::DOCS_URL . '/Caching' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Caching', 'whmcs-price' ); ?> ↗</a></li>
					<li><a href="<?php echo esc_url( self::DOCS_URL . '/Troubleshooting' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Troubleshooting', 'whmcs-price' ); ?> ↗</a></li>
					<li><a href="<?php echo esc_url( self::DOCS_URL . '/Security' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Security', 'whmcs-price' ); ?> ↗</a></li>
				</ul>
				<p style="margin-bottom:0;">
					<a href="https://github.com/morno/whmcs-price/issues" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="margin-top:8px; width:100%; text-align:center; box-sizing:border-box;">
						<?php esc_html_e( '🐛 Report an Issue', 'whmcs-price' ); ?>
					</a>
				</p>
			</div>
		</div>

		<!-- Product shortcode quick reference -->
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					📦 <?php esc_html_e( 'Product Pricing', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside">
				<p class="description" style="margin-top:0;"><?php esc_html_e( 'Copy shortcode — click to select:', 'whmcs-price' ); ?></p>
				<input
					type="text"
					style="width:100%; direction:ltr; cursor:pointer; font-family:monospace; font-size:11px;"
					value="[whmcs pid=&quot;1&quot; show=&quot;name,price&quot; bc=&quot;1y&quot;]"
					onclick="this.select()"
					readonly
				/>
				<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Billing cycles:', 'whmcs-price' ); ?> <code>1m</code> <code>3m</code> <code>6m</code> <code>1y</code> <code>2y</code> <code>3y</code></p>
				<p class="description"><?php esc_html_e( 'Show columns:', 'whmcs-price' ); ?> <code>name</code> <code>description</code> <code>price</code></p>
				<a href="<?php echo esc_url( self::DOCS_URL . '/Displaying-Prices#product-pricing' ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;">
					<?php esc_html_e( 'Full reference ↗', 'whmcs-price' ); ?>
				</a>
			</div>
		</div>

		<!-- Domain shortcode quick reference -->
		<div class="postbox" style="margin-bottom:16px;">
			<div class="postbox-header">
				<h2 class="hndle" style="padding:12px 16px; font-size:14px;">
					🌐 <?php esc_html_e( 'Domain Pricing', 'whmcs-price' ); ?>
				</h2>
			</div>
			<div class="inside">
				<p class="description" style="margin-top:0;"><?php esc_html_e( 'Copy shortcode — click to select:', 'whmcs-price' ); ?></p>
				<input
					type="text"
					style="width:100%; direction:ltr; cursor:pointer; font-family:monospace; font-size:11px;"
					value="[whmcs tld=&quot;com&quot; type=&quot;register&quot; reg=&quot;1y&quot;]"
					onclick="this.select()"
					readonly
				/>
				<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Type:', 'whmcs-price' ); ?> <code>register</code> <code>renew</code> <code>transfer</code></p>
				<p class="description"><?php esc_html_e( 'Period:', 'whmcs-price' ); ?> <code>1y</code> – <code>10y</code></p>
				<p class="description"><?php esc_html_e( 'Leave tld empty to list all TLDs.', 'whmcs-price' ); ?></p>
				<a href="<?php echo esc_url( self::DOCS_URL . '/Displaying-Prices#domain-pricing' ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;">
					<?php esc_html_e( 'Full reference ↗', 'whmcs-price' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	// =========================================================
	// SETTINGS API REGISTRATION
	// =========================================================

	public function whmcspr_init() {
		register_setting( 'price_option_group', 'whmcs_price_option', array( $this, 'sanitize' ) );
	}

	// =========================================================
	// SANITIZATION
	// =========================================================

	public function sanitize( $input ): array {
		$new_input = array();

		if ( ! empty( $input['whmcs_url'] ) ) {
			$url = esc_url_raw( trim( $input['whmcs_url'] ) );

			if ( ! str_starts_with( strtolower( $url ), 'https://' ) ) {
				add_settings_error(
					'whmcs_price_option',
					'http_url_blocked',
					__( 'WHMCS URL must use HTTPS. HTTP URLs are blocked for security reasons.', 'whmcs-price' )
				);
			} else {
				$new_input['whmcs_url'] = $url;
			}
		}

		$allowed_ttls = array( 3600, 7200, 10800, 21600, 43200, 86400 );
		if ( ! empty( $input['cache_ttl'] ) && in_array( (int) $input['cache_ttl'], $allowed_ttls, true ) ) {
			$new_input['cache_ttl'] = (int) $input['cache_ttl'];
		} else {
			$new_input['cache_ttl'] = 3600;
		}

		if ( ! empty( $input['custom_user_agent'] ) ) {
			$ua = sanitize_text_field( trim( $input['custom_user_agent'] ) );
			$ua = preg_replace( '/[^\x20-\x7E]/', '', $ua );
			if ( strlen( $ua ) > 255 ) {
				$ua = substr( $ua, 0, 255 );
			}
			if ( ! empty( $ua ) ) {
				$new_input['custom_user_agent'] = $ua;
			}
		}

		return $new_input;
	}

	// =========================================================
	// CACHE MANAGEMENT
	// =========================================================

	public function clear_whmcs_cache() {
		global $wpdb;

		$prefixes = array(
			$wpdb->esc_like( '_transient_whmcs_' ) . '%',
			$wpdb->esc_like( '_transient_lock_whmcs_' ) . '%',
		);

		foreach ( $prefixes as $like ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$transient_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT REPLACE(option_name, '_transient_', '') FROM $wpdb->options WHERE option_name LIKE %s",
					$like
				)
			);
			// phpcs:enable

			foreach ( $transient_keys as $key ) {
				delete_transient( $key );
			}
		}

		delete_expired_transients( true );
	}

	public function add_admin_bar_clear_cache( $admin_bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			$admin_bar->add_menu(
				array(
					'id'    => 'whmcs-clear-cache',
					'title' => __( 'Clear WHMCS Cache', 'whmcs-price' ),
					'href'  => wp_nonce_url( add_query_arg( 'whmcs_clear_cache', '1' ), 'whmcs_clear_cache_admin_bar' ),
					'meta'  => array( 'title' => __( 'Clear WHMCS Cache', 'whmcs-price' ) ),
				)
			);
		}
	}

	public function handle_admin_bar_clear_cache_action() {
		if ( isset( $_GET['whmcs_clear_cache'] ) && '1' === $_GET['whmcs_clear_cache'] ) {

			check_admin_referer( 'whmcs_clear_cache_admin_bar' );

			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'Unauthorized access.', 'whmcs-price' ) );
			}

			$this->clear_whmcs_cache();

			$redirect_url = remove_query_arg( array( 'whmcs_clear_cache', '_wpnonce' ), add_query_arg( 'cache_cleared', '1' ) );

			wp_safe_redirect( $redirect_url );
			exit;
		}
	}
}
