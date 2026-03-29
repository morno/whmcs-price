<?php
/**
 * Plugin Settings and Admin UI
 *
 * @package    WHMCS_Price
 * @subpackage Admin
 * @since      2.2.0
 */

defined( 'ABSPATH' ) || exit;

class WHMCSPrice {

	private array $options = array();

	private const DOCS_URL = 'https://github.com/morno/whmcs-price/wiki';

	private function get_query_flag( string $key ): int {
		if ( ! isset( $_GET[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return 0;
		}
		return absint( wp_unslash( $_GET[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'whmcs_price_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'whmcs_price_settings_init' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_clear_cache' ), 100 );
		add_action( 'admin_init', array( $this, 'handle_admin_bar_clear_cache_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( WHMCS_PRICE_DIR . 'whmcs_price.php' ), array( $this, 'add_settings_link' ) );
	}

	// =========================================================
	// ADMIN PAGE
	// =========================================================

	public function add_settings_link( $links ) {
		array_unshift( $links, '<a href="' . esc_url( admin_url( 'options-general.php?page=whmcs_price' ) ) . '">' . esc_html__( 'Settings', 'whmcs-price' ) . '</a>' );
		return $links;
	}

	public function whmcs_price_plugin_page() {
		add_options_page(
			__( 'WHMCS Price Options', 'whmcs-price' ),
			__( 'WHMCS Price Settings', 'whmcs-price' ),
			'manage_options',
			'whmcs_price',
			array( $this, 'whmcs_price_admin_page' )
		);
	}

	/**
	 * Active tab — read from $_GET or saved user meta.
	 */
	private function get_active_tab(): string {
		$allowed = array( 'connection', 'performance', 'notifications', 'advanced' );
		if ( isset( $_GET['tab'] ) && in_array( $_GET['tab'], $allowed, true ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return sanitize_key( wp_unslash( $_GET['tab'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		}
		$saved = get_user_meta( get_current_user_id(), 'whmcs_price_active_tab', true );
		return in_array( $saved, $allowed, true ) ? $saved : 'connection';
	}

	public function whmcs_price_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }

		if ( 1 === $this->get_query_flag( 'cache_cleared' ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Cache cleared successfully!', 'whmcs-price' ) . '</p></div>';
		}

		$this->options = get_option( 'whmcs_price_option', array() );
		$active_tab    = $this->get_active_tab();

		// Save active tab to user meta so it persists across page loads.
		update_user_meta( get_current_user_id(), 'whmcs_price_active_tab', $active_tab );

		$tabs = array(
			'connection'    => '🔗 ' . __( 'Connection', 'whmcs-price' ),
			'performance'   => '⚡ ' . __( 'Performance', 'whmcs-price' ),
			'notifications' => '🔔 ' . __( 'Notifications', 'whmcs-price' ),
			'advanced'      => '🔧 ' . __( 'Advanced', 'whmcs-price' ),
		);

		$base_url = admin_url( 'options-general.php?page=whmcs_price' );
		?>
		<div class="wrap">
			<h1>
				<?php esc_html_e( 'WHMCS Price Settings', 'whmcs-price' ); ?>
				<a href="<?php echo esc_url( self::DOCS_URL ); ?>" target="_blank" rel="noopener noreferrer"
					class="page-title-action" style="text-decoration:none;">
					<?php esc_html_e( 'Documentation ↗', 'whmcs-price' ); ?>
				</a>
			</h1>

			<nav class="nav-tab-wrapper" style="margin-bottom:20px;">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'tab', $slug, $base_url ) ); ?>"
					   class="nav-tab <?php echo $slug === $active_tab ? 'nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div style="display:flex; gap:24px; align-items:flex-start;">

				<!-- Main settings form -->
				<div style="flex:1; min-width:0;">
					<form method="post" action="options.php">
						<?php settings_fields( 'price_option_group' ); ?>
						<?php settings_errors( 'whmcs_price_option' ); ?>

						<?php
						switch ( $active_tab ) {
							case 'connection':    $this->render_section_connection(); break;
							case 'performance':   $this->render_section_performance(); break;
							case 'notifications': $this->render_section_notifications(); break;
							case 'advanced':      $this->render_section_advanced(); break;
						}
						?>

						<?php submit_button( __( 'Save Changes', 'whmcs-price' ) ); ?>
					</form>
				</div>

				<!-- Sidebar -->
				<div style="width:280px; flex-shrink:0;">
					<?php $this->render_sidebar_status(); ?>
					<?php $this->render_sidebar_product_ref(); ?>
					<?php $this->render_sidebar_domain_ref(); ?>
				</div>

			</div>
		</div>
		<?php
	}

	// =========================================================
	// SECTION RENDERERS
	// =========================================================

	private function render_section_connection() {
		$whmcs_url  = $this->options['whmcs_url'] ?? '';
		$bypass_cdn = isset( $this->options['bypass_cdn_cache'] ) ? (bool) $this->options['bypass_cdn_cache'] : false;
		?>
		<input type="hidden" name="whmcs_price_option[_tab]" value="connection" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="whmcs_url"><?php esc_html_e( 'WHMCS URL', 'whmcs-price' ); ?></label></th>
				<td>
					<?php if ( ! empty( $whmcs_url ) && ! filter_var( $whmcs_url, FILTER_VALIDATE_URL ) ) : ?>
						<div class="notice notice-error inline" style="margin:0 0 8px;"><p><?php esc_html_e( 'The saved URL does not appear to be valid.', 'whmcs-price' ); ?></p></div>
					<?php endif; ?>
					<?php if ( ! empty( $whmcs_url ) && ! str_starts_with( strtolower( $whmcs_url ), 'https://' ) ) : ?>
						<div class="notice notice-warning inline" style="margin:0 0 8px;"><p>
							<strong><?php esc_html_e( 'Warning:', 'whmcs-price' ); ?></strong>
							<?php esc_html_e( 'The WHMCS URL must use HTTPS. HTTP URLs are blocked for security reasons.', 'whmcs-price' ); ?>
						</p></div>
					<?php endif; ?>
					<input type="url" id="whmcs_url" class="regular-text" style="direction:ltr;"
						name="whmcs_price_option[whmcs_url]" value="<?php echo esc_attr( $whmcs_url ); ?>"
						placeholder="https://billing.yourdomain.com" />
					<p class="description"><?php esc_html_e( 'Base URL of your WHMCS installation. Must use HTTPS. No trailing slash.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Bypass CDN Cache', 'whmcs-price' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="whmcs_price_option[bypass_cdn_cache]" value="1"
							<?php checked( $bypass_cdn ); ?> />
						<?php esc_html_e( 'Send cache-bypass headers with every request to WHMCS', 'whmcs-price' ); ?>
					</label>
					<p class="description">
						<?php esc_html_e( 'Enable only if your WHMCS installation is behind Cloudflare or another CDN/reverse proxy that is configured to cache PHP responses. Sends Cache-Control: no-cache and Pragma: no-cache so the CDN fetches fresh prices from origin. Most WHMCS installations do not need this — Cloudflare does not cache dynamic PHP by default.', 'whmcs-price' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

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
		<input type="hidden" name="whmcs_price_option[_tab]" value="performance" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="cache_ttl"><?php esc_html_e( 'Cache Duration', 'whmcs-price' ); ?></label></th>
				<td>
					<select id="cache_ttl" name="whmcs_price_option[cache_ttl]">
						<?php foreach ( $ttl_options as $value => $label ) : ?>
							<option value="<?php echo absint( $value ); ?>" <?php selected( $current_ttl, $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
					<p class="description"><?php esc_html_e( 'How long prices are cached before fetching fresh data from WHMCS.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Clear Cache', 'whmcs-price' ); ?></th>
				<td>
					<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( 'whmcs_clear_cache', '1' ), 'whmcs_clear_cache_admin_bar' ) ); ?>" class="button button-secondary">
						<?php esc_html_e( 'Clear Cache Now', 'whmcs-price' ); ?>
					</a>
					<p class="description"><?php esc_html_e( 'Force fresh prices to be fetched from WHMCS on the next page load. Also available from the Admin Bar.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_section_notifications() {
		$notify  = isset( $this->options['outage_notify'] ) ? (string) $this->options['outage_notify'] : '1';
		$email   = ! empty( $this->options['outage_email'] ) ? $this->options['outage_email'] : get_option( 'admin_email' );
		$pending = false !== get_transient( 'whmcs_price_outage_notified' );
		?>
		<?php if ( $pending ) : ?>
			<div class="notice notice-warning inline" style="margin:0 0 12px;"><p>
				<strong><?php esc_html_e( 'Active outage detected.', 'whmcs-price' ); ?></strong>
				<?php esc_html_e( 'WHMCS pricing data could not be fetched. Visitors are seeing the unavailability message. An e-mail notification has already been sent.', 'whmcs-price' ); ?>
			</p></div>
		<?php endif; ?>
		<input type="hidden" name="whmcs_price_option[_tab]" value="notifications" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="outage_notify"><?php esc_html_e( 'Outage Alerts', 'whmcs-price' ); ?></label></th>
				<td>
					<label>
						<input type="checkbox" id="outage_notify" name="whmcs_price_option[outage_notify]" value="1" <?php checked( '1', $notify ); ?> />
						<?php esc_html_e( 'Send an e-mail when WHMCS pricing data becomes unavailable', 'whmcs-price' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'At most one notification is sent per outage. A fresh alert is sent if WHMCS recovers and then goes down again.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="outage_email"><?php esc_html_e( 'Alert Address', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="email" id="outage_email" class="regular-text" name="whmcs_price_option[outage_email]" value="<?php echo esc_attr( $email ); ?>" />
					<p class="description"><?php esc_html_e( 'Defaults to the site admin e-mail address.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
		</table>
		<?php
	}

	private function render_section_advanced() {
		$current_ua     = $this->options['custom_user_agent'] ?? '';
		$site_url       = get_bloginfo( 'url' );
		$plugin_version = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : '';
		$default_ua     = "WordPress ({$site_url}) whmcs-price/{$plugin_version}";
		?>
		<input type="hidden" name="whmcs_price_option[_tab]" value="advanced" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="fallback_price"><?php esc_html_e( 'Fallback Price', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="text" id="fallback_price" class="regular-text" style="direction:ltr;"
						name="whmcs_price_option[fallback_price]"
						value="<?php echo esc_attr( $this->options['fallback_price'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'e.g. from 9.99 kr/mo', 'whmcs-price' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Shown instead of "Pricing unavailable" when WHMCS cannot be reached. Leave blank to show the default message.', 'whmcs-price' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="custom_user_agent"><?php esc_html_e( 'Custom User-Agent', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="text" id="custom_user_agent" class="large-text"
						style="direction:ltr; font-family:monospace;"
						name="whmcs_price_option[custom_user_agent]"
						value="<?php echo esc_attr( $current_ua ); ?>"
						placeholder="<?php echo esc_attr( $default_ua ); ?>" />
					<p class="description">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %s: default User-Agent string */
								__( 'Override the User-Agent sent to WHMCS. Useful for firewall allow-rules. Leave blank to use the default: %s', 'whmcs-price' ),
								'<code>' . esc_html( $default_ua ) . '</code>'
							),
							array( 'code' => array() )
						);
						?>
					</p>
				</td>
			</tr>
		</table>
		<?php
	}

	// =========================================================
	// SIDEBAR RENDERERS — use WP's .card class
	// =========================================================

	private function render_sidebar_status() {
		$overview = $this->get_cache_overview();
		$now      = time();
		$min_eta  = ( null !== $overview['min_timeout'] ) ? ( $overview['min_timeout'] - $now ) : 0;
		$max_eta  = ( null !== $overview['max_timeout'] ) ? ( $overview['max_timeout'] - $now ) : 0;
		?>
		<div class="card" style="max-width:none; margin-bottom:16px; padding:16px;">
			<h2 style="margin-top:0; font-size:14px;"><?php esc_html_e( 'Operational Status', 'whmcs-price' ); ?></h2>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'Read-only diagnostics — no outbound WHMCS calls.', 'whmcs-price' ); ?></p>
			<p><strong><?php esc_html_e( 'Cached entries', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( (string) $overview['cache_count'] ); ?></p>
			<p><strong><?php esc_html_e( 'Active locks', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( (string) $overview['lock_count'] ); ?></p>
			<p><strong><?php esc_html_e( 'Nearest expiry', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( $this->format_seconds( $min_eta ) ); ?></p>
			<p style="margin-bottom:0;"><strong><?php esc_html_e( 'Farthest expiry', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( $this->format_seconds( $max_eta ) ); ?></p>
		</div>
		<?php
	}

	private function render_sidebar_product_ref() {
		?>
		<div class="card" style="max-width:none; margin-bottom:16px; padding:16px;">
			<h2 style="margin-top:0; font-size:14px;">📦 <?php esc_html_e( 'Product Pricing', 'whmcs-price' ); ?></h2>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'Click to select shortcode:', 'whmcs-price' ); ?></p>
			<input type="text" style="width:100%; direction:ltr; cursor:pointer; font-family:monospace; font-size:11px;"
				value="[whmcs pid=&quot;1&quot; show=&quot;name,price&quot; bc=&quot;1y&quot; per=&quot;month&quot;]"
				onclick="this.select()" readonly />
			<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Billing cycles:', 'whmcs-price' ); ?> <code>1m</code> <code>3m</code> <code>6m</code> <code>1y</code> <code>2y</code> <code>3y</code></p>
			<p class="description"><?php esc_html_e( 'Show:', 'whmcs-price' ); ?> <code>name</code> <code>description</code> <code>price</code> <code>setupfee</code></p>
			<a href="<?php echo esc_url( self::DOCS_URL . '/Displaying-Prices#product-pricing' ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;"><?php esc_html_e( 'Full reference ↗', 'whmcs-price' ); ?></a>
		</div>
		<?php
	}

	private function render_sidebar_domain_ref() {
		?>
		<div class="card" style="max-width:none; margin-bottom:16px; padding:16px;">
			<h2 style="margin-top:0; font-size:14px;">🌐 <?php esc_html_e( 'Domain Pricing', 'whmcs-price' ); ?></h2>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'Click to select shortcode:', 'whmcs-price' ); ?></p>
			<input type="text" style="width:100%; direction:ltr; cursor:pointer; font-family:monospace; font-size:11px;"
				value="[whmcs tld=&quot;com&quot; show=&quot;register,renew&quot; reg=&quot;1&quot; per=&quot;month&quot;]"
				onclick="this.select()" readonly />
			<p class="description" style="margin-top:8px;"><?php esc_html_e( 'Show:', 'whmcs-price' ); ?> <code>register</code> <code>renew</code> <code>transfer</code></p>
			<p class="description"><?php esc_html_e( 'Period (years):', 'whmcs-price' ); ?> <code>1</code> – <code>10</code></p>
			<p class="description"><?php esc_html_e( 'Leave tld empty to list all TLDs.', 'whmcs-price' ); ?></p>
			<a href="<?php echo esc_url( self::DOCS_URL . '/Displaying-Prices#domain-pricing' ); ?>" target="_blank" rel="noopener noreferrer" style="font-size:12px;"><?php esc_html_e( 'Full reference ↗', 'whmcs-price' ); ?></a>
		</div>
		<?php
	}

	// =========================================================
	// SETTINGS API
	// =========================================================

	public function whmcs_price_settings_init() {
		register_setting( 'price_option_group', 'whmcs_price_option', array( $this, 'sanitize' ) );
	}

	public function sanitize( $input ): array {
		// Start with the existing saved values so that fields on other tabs
		// are not lost when saving a single tab's form. Each tab only submits
		// its own fields, so missing keys must fall back to what was already stored.
		$existing  = get_option( 'whmcs_price_option', array() );
		$new_input = is_array( $existing ) ? $existing : array();

		// Use the hidden _tab field to determine which tab was saved.
		// Only fields belonging to that tab are updated — all other fields
		// keep their existing values loaded from the database above.
		$active_tab = isset( $input['_tab'] ) ? sanitize_key( $input['_tab'] ) : '';

		if ( 'connection' === $active_tab ) {
			if ( ! empty( $input['whmcs_url'] ) ) {
				$url = esc_url_raw( trim( $input['whmcs_url'] ) );
				if ( ! str_starts_with( strtolower( $url ), 'https://' ) ) {
					add_settings_error( 'whmcs_price_option', 'http_url_blocked', __( 'WHMCS URL must use HTTPS. HTTP URLs are blocked for security reasons.', 'whmcs-price' ) );
				} else {
					$new_input['whmcs_url'] = $url;
				}
			} else {
				unset( $new_input['whmcs_url'] );
			}
			$new_input['bypass_cdn_cache'] = isset( $input['bypass_cdn_cache'] ) && '1' === (string) $input['bypass_cdn_cache'] ? '1' : '0';
		}

		if ( 'performance' === $active_tab ) {
			$allowed_ttls           = array( 3600, 7200, 10800, 21600, 43200, 86400 );
			$new_input['cache_ttl'] = ( ! empty( $input['cache_ttl'] ) && in_array( (int) $input['cache_ttl'], $allowed_ttls, true ) )
				? (int) $input['cache_ttl'] : 3600;
		}

		if ( 'advanced' === $active_tab ) {
			// Fallback price: free-form string, sanitized as text field, max 60 chars.
			if ( ! empty( $input['fallback_price'] ) ) {
				$fp = sanitize_text_field( wp_unslash( $input['fallback_price'] ) );
				$new_input['fallback_price'] = substr( $fp, 0, 60 );
			} else {
				unset( $new_input['fallback_price'] );
			}

			if ( ! empty( $input['custom_user_agent'] ) ) {
				$ua = sanitize_text_field( trim( $input['custom_user_agent'] ) );
				$ua = preg_replace( '/[^\x20-\x7E]/', '', $ua );
				if ( strlen( $ua ) > 255 ) { $ua = substr( $ua, 0, 255 ); }
				if ( ! empty( $ua ) ) {
					$new_input['custom_user_agent'] = $ua;
				} else {
					unset( $new_input['custom_user_agent'] );
				}
			} else {
				unset( $new_input['custom_user_agent'] );
			}
		}

		if ( 'notifications' === $active_tab ) {
			$new_input['outage_notify'] = isset( $input['outage_notify'] ) && '1' === (string) $input['outage_notify'] ? '1' : '0';
			if ( ! empty( $input['outage_email'] ) ) {
				$email = sanitize_email( $input['outage_email'] );
				if ( is_email( $email ) ) { $new_input['outage_email'] = $email; }
			} else {
				unset( $new_input['outage_email'] );
			}
		}

		return $new_input;
	}

	// =========================================================
	// CACHE MANAGEMENT
	// =========================================================

	public function clear_whmcs_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		global $wpdb;
		$prefixes = array(
			$wpdb->esc_like( '_transient_whmcs_' ) . '%',
			$wpdb->esc_like( '_transient_lock_whmcs_' ) . '%',
		);
		foreach ( $prefixes as $like ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$keys = $wpdb->get_col( $wpdb->prepare( "SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );
			// phpcs:enable
			foreach ( $keys as $key ) { delete_transient( $key ); }
		}
		delete_expired_transients( true );
		whmcs_price_flush_page_cache();
	}

	public function add_admin_bar_clear_cache( $admin_bar ) {
		if ( current_user_can( 'manage_options' ) ) {
			$admin_bar->add_menu( array(
				'id'    => 'whmcs-clear-cache',
				'title' => __( 'Clear WHMCS Cache', 'whmcs-price' ),
				'href'  => wp_nonce_url( add_query_arg( 'whmcs_clear_cache', '1' ), 'whmcs_clear_cache_admin_bar' ),
				'meta'  => array( 'title' => __( 'Clear WHMCS Cache', 'whmcs-price' ) ),
			) );
		}
	}

	public function handle_admin_bar_clear_cache_action() {
		if ( 1 === $this->get_query_flag( 'whmcs_clear_cache' ) ) {
			check_admin_referer( 'whmcs_clear_cache_admin_bar' );
			if ( ! current_user_can( 'manage_options' ) ) { wp_die( esc_html__( 'Unauthorized access.', 'whmcs-price' ) ); }
			$this->clear_whmcs_cache();
			wp_safe_redirect( remove_query_arg( array( 'whmcs_clear_cache', '_wpnonce' ), add_query_arg( 'cache_cleared', '1' ) ) );
			exit;
		}
	}

	// =========================================================
	// CACHE DIAGNOSTICS
	// =========================================================

	private function get_cache_overview(): array {
		global $wpdb;
		$cache_like   = $wpdb->esc_like( '_transient_whmcs_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_whmcs_' ) . '%';
		$lock_like    = $wpdb->esc_like( '_transient_lock_whmcs_' ) . '%';
		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$cache_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $cache_like ) );
		$lock_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $lock_like ) );
		$min_timeout = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
		$max_timeout = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
		// phpcs:enable
		return array(
			'cache_count' => $cache_count,
			'lock_count'  => $lock_count,
			'min_timeout' => null !== $min_timeout ? (int) $min_timeout : null,
			'max_timeout' => null !== $max_timeout ? (int) $max_timeout : null,
		);
	}

	private function format_seconds( int $seconds ): string {
		if ( $seconds <= 0 ) { return __( 'Now', 'whmcs-price' ); }
		$days     = intdiv( $seconds, DAY_IN_SECONDS );
		$seconds -= $days * DAY_IN_SECONDS;
		$hours    = intdiv( $seconds, HOUR_IN_SECONDS );
		$seconds -= $hours * HOUR_IN_SECONDS;
		$mins     = intdiv( $seconds, MINUTE_IN_SECONDS );
		$parts    = array();
		if ( $days  > 0 ) { $parts[] = sprintf( _n( '%d day',    '%d days',    $days,  'whmcs-price' ), $days ); }
		if ( $hours > 0 ) { $parts[] = sprintf( _n( '%d hour',   '%d hours',   $hours, 'whmcs-price' ), $hours ); }
		if ( $mins  > 0 && 0 === $days ) { $parts[] = sprintf( _n( '%d minute', '%d minutes', $mins, 'whmcs-price' ), $mins ); }
		return ! empty( $parts ) ? implode( ' ', $parts ) : __( 'Soon', 'whmcs-price' );
	}
}
