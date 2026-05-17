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
		$allowed = array( 'connection', 'performance', 'notifications', 'advanced', 'generator' );
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
			'generator'     => '✨ ' . __( 'Shortcode Generator', 'whmcs-price' ),
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
					<?php if ( 'generator' === $active_tab ) : ?>
						<?php whmcs_price_render_shortcode_generator(); ?>
					<?php else : ?>
					<form method="post" action="options.php">
						<?php settings_fields( 'price_option_group' ); ?>
						<?php settings_errors( 'whmcs_price_option' ); ?>

						<?php
						switch ( $active_tab ) {
							case 'connection':    $this->render_section_connection(); break;
							case 'performance':   $this->render_section_performance(); break;
							case 'notifications': $this->render_section_notifications(); $this->render_section_promo(); break;
							case 'advanced':      $this->render_section_advanced(); break;
						}
						?>

						<?php submit_button( __( 'Save Changes', 'whmcs-price' ) ); ?>
					</form>
					<?php endif; ?>
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

	private function render_section_promo(): void {
		?>
		<input type="hidden" name="whmcs_price_option[_tab]" value="notifications" />
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="promo_code"><?php esc_html_e( 'Promo Code', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="text" id="promo_code" class="regular-text"
						name="whmcs_price_option[promo_code]"
						value="<?php echo esc_attr( $this->options['promo_code'] ?? '' ); ?>"
						placeholder="HOSTING20" />
					<p class="description"><?php esc_html_e( 'Optional. Leave blank to disable the promo notice.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="promo_text"><?php esc_html_e( 'Promo Text', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="text" id="promo_text" class="large-text"
						name="whmcs_price_option[promo_text]"
						value="<?php echo esc_attr( $this->options['promo_text'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'Use code {code} to get 20% off your first year', 'whmcs-price' ); ?>" />
					<p class="description">
						<?php esc_html_e( 'Text shown below the price. Use {code} to insert the promo code.', 'whmcs-price' ); ?>
						<?php if ( ! empty( $this->options['promo_code'] ?? '' ) && ! empty( $this->options['promo_text'] ?? '' ) ) : ?>
							<br><strong><?php esc_html_e( 'Preview:', 'whmcs-price' ); ?></strong>
							<?php echo esc_html( str_replace( '{code}', $this->options['promo_code'], $this->options['promo_text'] ) ); ?>
						<?php endif; ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="promo_target"><?php esc_html_e( 'Show for', 'whmcs-price' ); ?></label></th>
				<td>
					<select id="promo_target" name="whmcs_price_option[promo_target]">
						<option value="both" <?php selected( $this->options['promo_target'] ?? 'both', 'both' ); ?>><?php esc_html_e( 'Products and Domains', 'whmcs-price' ); ?></option>
						<option value="product" <?php selected( $this->options['promo_target'] ?? 'both', 'product' ); ?>><?php esc_html_e( 'Products only', 'whmcs-price' ); ?></option>
						<option value="domain" <?php selected( $this->options['promo_target'] ?? 'both', 'domain' ); ?>><?php esc_html_e( 'Domains only', 'whmcs-price' ); ?></option>
					</select>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * Inline notice: how to revoke or rotate the cache purge token.
	 *
	 * @since 2.9.0
	 * @return void
	 */
	private function render_purge_token_revoke_notice(): void {
		$docs_url = esc_url( self::DOCS_URL . '/FAQ#how-do-i-revoke-the-cache-purge-api-token-if-someone-unauthorized-gets-it' );
		?>
		<div class="notice notice-info inline" style="margin:0 0 12px;padding:8px 12px;max-width:640px;">
			<p style="margin:0 0 6px;">
				<strong><?php esc_html_e( 'Revoking a compromised token', 'whmcs-price' ); ?></strong>
			</p>
			<ul style="margin:0 0 8px 1.2em;list-style:disc;">
				<li><?php esc_html_e( 'Click Generate (or paste a new secret), then Save Changes — the previous token stops working immediately.', 'whmcs-price' ); ?></li>
				<li><?php esc_html_e( 'Or clear this field and save to disable the purge endpoint until you set a new token.', 'whmcs-price' ); ?></li>
			</ul>
			<p style="margin:0;">
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: link to REST API wiki page (revoke section) */
						__( 'Then update the token in WHMCS hooks, n8n, Zapier, or any other automation. %s', 'whmcs-price' ),
						'<a href="' . $docs_url . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Full instructions in the documentation ↗', 'whmcs-price' ) . '</a>'
					),
					array(
						'a' => array(
							'href'   => true,
							'target' => true,
							'rel'    => true,
						),
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	private function render_section_advanced() {
		$current_ua     = $this->options['custom_user_agent'] ?? '';
		$site_url       = get_bloginfo( 'url' );
		$plugin_version = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : '';
		$default_ua     = "WordPress ({$site_url}) whmcs-price/{$plugin_version}";
		$sec_defaults   = whmcs_price_security_defaults();
		$purge_interval = isset( $this->options['purge_success_interval'] ) ? (int) $this->options['purge_success_interval'] : (int) $sec_defaults['purge_success_interval'];
		$purge_auth_lim = isset( $this->options['purge_auth_limit'] ) ? (int) $this->options['purge_auth_limit'] : (int) $sec_defaults['purge_auth_limit'];
		$purge_auth_win = isset( $this->options['purge_auth_window'] ) ? (int) $this->options['purge_auth_window'] : (int) $sec_defaults['purge_auth_window'];
		$rest_enabled   = isset( $this->options['rest_rate_enabled'] ) ? (string) $this->options['rest_rate_enabled'] : (string) $sec_defaults['rest_rate_enabled'];
		$rest_limit     = isset( $this->options['rest_rate_limit'] ) ? (int) $this->options['rest_rate_limit'] : (int) $sec_defaults['rest_rate_limit'];
		$rest_window    = isset( $this->options['rest_rate_window'] ) ? (int) $this->options['rest_rate_window'] : (int) $sec_defaults['rest_rate_window'];
		$rest_miss_only = isset( $this->options['rest_rate_miss_only'] ) ? (string) $this->options['rest_rate_miss_only'] : (string) $sec_defaults['rest_rate_miss_only'];
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
				<th scope="row"><label for="purge_token"><?php esc_html_e( 'Cache Purge Token', 'whmcs-price' ); ?></label></th>
				<td>
					<?php $this->render_purge_token_revoke_notice(); ?>
					<input type="password" id="purge_token" class="regular-text" style="direction:ltr;font-family:monospace;"
						name="whmcs_price_option[purge_token]"
						value="<?php echo esc_attr( $this->options['purge_token'] ?? '' ); ?>"
						placeholder="<?php esc_attr_e( 'Leave blank to disable', 'whmcs-price' ); ?>"
						autocomplete="off" spellcheck="false" />
					<button type="button" class="button button-link" style="margin-left:4px;"
						onclick="(function(b){var f=document.getElementById('purge_token');f.type=f.type==='password'?'text':'password';b.textContent=f.type==='password'?'<?php echo esc_js( __( 'Show', 'whmcs-price' ) ); ?>':'<?php echo esc_js( __( 'Hide', 'whmcs-price' ) ); ?>';})(this);"
					><?php esc_html_e( 'Show', 'whmcs-price' ); ?></button>
					<?php if ( empty( $this->options['purge_token'] ?? '' ) ) : ?>
						<button type="button" class="button button-secondary" style="margin-left:6px;"
							onclick="(function(){var b=new Uint8Array(24);(window.crypto||window.msCrypto).getRandomValues(b);document.getElementById('purge_token').value=Array.from(b,function(x){return ('0'+x.toString(16)).slice(-2);}).join('');})();"
						><?php esc_html_e( 'Generate', 'whmcs-price' ); ?></button>
					<?php endif; ?>
					<p class="description">
						<?php esc_html_e( 'Secret token required for the cache purge REST endpoint (POST /wp-json/whmcs-price/v1/purge-cache). Send it in the X-WHMCS-Price-Token header. Minimum 16 characters (letters, digits, hyphen, underscore). Leave blank to disable the endpoint.', 'whmcs-price' ); ?>
					</p>
					<?php if ( ! empty( $this->options['purge_token'] ?? '' ) ) : ?>
						<p class="description" style="margin-top:6px;">
							<strong><?php esc_html_e( 'Endpoint:', 'whmcs-price' ); ?></strong>
							<code><?php echo esc_html( rest_url( 'whmcs-price/v1/purge-cache' ) ); ?></code>
						</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2 class="title" style="margin-top:24px;"><?php esc_html_e( 'Rate limiting', 'whmcs-price' ); ?></h2>
		<p class="description"><?php esc_html_e( 'Protect the REST API and cache-purge endpoint from abuse. Set any limit to 0 to disable that rule. Limits apply per visitor IP address.', 'whmcs-price' ); ?></p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="purge_success_interval"><?php esc_html_e( 'Purge success cooldown', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="number" id="purge_success_interval" class="small-text" min="0" max="3600" step="1"
						name="whmcs_price_option[purge_success_interval]" value="<?php echo absint( $purge_interval ); ?>" />
					<?php esc_html_e( 'seconds between successful cache purges', 'whmcs-price' ); ?>
					<p class="description"><?php esc_html_e( 'Prevents accidental purge loops from webhooks or automation. Default: 5. Set to 0 to allow back-to-back purges.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="purge_auth_limit"><?php esc_html_e( 'Purge failed-auth limit', 'whmcs-price' ); ?></label></th>
				<td>
					<input type="number" id="purge_auth_limit" class="small-text" min="0" max="1000" step="1"
						name="whmcs_price_option[purge_auth_limit]" value="<?php echo absint( $purge_auth_lim ); ?>" />
					<?php esc_html_e( 'failed token attempts per', 'whmcs-price' ); ?>
					<input type="number" id="purge_auth_window" class="small-text" min="0" max="86400" step="1"
						name="whmcs_price_option[purge_auth_window]" value="<?php echo absint( $purge_auth_win ); ?>" />
					<?php esc_html_e( 'seconds (per IP)', 'whmcs-price' ); ?>
					<p class="description"><?php esc_html_e( 'Blocks brute-force guessing of the purge token. Default: 10 attempts per 60 seconds. Set either field to 0 to disable.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'REST API rate limit', 'whmcs-price' ); ?></th>
				<td>
					<label>
						<input type="checkbox" name="whmcs_price_option[rest_rate_enabled]" value="1"
							<?php checked( '1', $rest_enabled ); ?> />
						<?php esc_html_e( 'Enable rate limiting on public GET endpoints (/product, /domain)', 'whmcs-price' ); ?>
					</label>
					<p style="margin:12px 0 6px;">
						<input type="number" id="rest_rate_limit" class="small-text" min="0" max="10000" step="1"
							name="whmcs_price_option[rest_rate_limit]" value="<?php echo absint( $rest_limit ); ?>" />
						<?php esc_html_e( 'requests per', 'whmcs-price' ); ?>
						<input type="number" id="rest_rate_window" class="small-text" min="0" max="86400" step="1"
							name="whmcs_price_option[rest_rate_window]" value="<?php echo absint( $rest_window ); ?>" />
						<?php esc_html_e( 'seconds (per IP)', 'whmcs-price' ); ?>
					</p>
					<label>
						<input type="checkbox" name="whmcs_price_option[rest_rate_miss_only]" value="1"
							<?php checked( '1', $rest_miss_only ); ?> />
						<?php esc_html_e( 'Only count cache misses (requests that would fetch from WHMCS)', 'whmcs-price' ); ?>
					</label>
					<p class="description"><?php esc_html_e( 'Disabled by default so headless integrations keep working. When enabled, defaults to 60 requests per 60 seconds. Returns HTTP 429 with Retry-After when exceeded.', 'whmcs-price' ); ?></p>
				</td>
			</tr>
		</table>

		<table class="form-table" role="presentation">
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

		// Counts are only meaningful when transients live in wp_options.
		// On persistent object cache backends they don't, so present a
		// clear "not applicable" indicator instead of misleading zeroes.
		$cache_count_display = null === $overview['cache_count'] ? '—' : (string) $overview['cache_count'];
		$lock_count_display  = null === $overview['lock_count']  ? '—' : (string) $overview['lock_count'];
		?>
		<div class="card" style="max-width:none; margin-bottom:16px; padding:16px;">
			<h2 style="margin-top:0; font-size:14px;"><?php esc_html_e( 'Operational Status', 'whmcs-price' ); ?></h2>
			<p class="description" style="margin-top:0;"><?php esc_html_e( 'Read-only diagnostics — no outbound WHMCS calls.', 'whmcs-price' ); ?></p>
			<?php if ( ! empty( $overview['object_cache_active'] ) ) : ?>
				<p class="description" style="margin:0 0 8px 0;">
					<em><?php esc_html_e( 'Persistent object cache detected — entry counts are kept in cache, not the database, so DB-based counts are unavailable.', 'whmcs-price' ); ?></em>
				</p>
			<?php endif; ?>
			<p><strong><?php esc_html_e( 'Cached entries', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( $cache_count_display ); ?></p>
			<p><strong><?php esc_html_e( 'Active locks', 'whmcs-price' ); ?>:</strong> <?php echo esc_html( $lock_count_display ); ?></p>
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
			$sec_defaults = whmcs_price_security_defaults();

			$new_input['purge_success_interval'] = self::sanitize_rate_int(
				$input['purge_success_interval'] ?? $sec_defaults['purge_success_interval'],
				0,
				3600,
				(int) $sec_defaults['purge_success_interval']
			);
			$new_input['purge_auth_limit']  = self::sanitize_rate_int(
				$input['purge_auth_limit'] ?? $sec_defaults['purge_auth_limit'],
				0,
				1000,
				(int) $sec_defaults['purge_auth_limit']
			);
			$new_input['purge_auth_window'] = self::sanitize_rate_int(
				$input['purge_auth_window'] ?? $sec_defaults['purge_auth_window'],
				0,
				86400,
				(int) $sec_defaults['purge_auth_window']
			);
			$new_input['rest_rate_enabled']   = isset( $input['rest_rate_enabled'] ) && '1' === (string) $input['rest_rate_enabled'] ? '1' : '0';
			$new_input['rest_rate_miss_only'] = isset( $input['rest_rate_miss_only'] ) && '1' === (string) $input['rest_rate_miss_only'] ? '1' : '0';
			$new_input['rest_rate_limit']     = self::sanitize_rate_int(
				$input['rest_rate_limit'] ?? $sec_defaults['rest_rate_limit'],
				0,
				10000,
				(int) $sec_defaults['rest_rate_limit']
			);
			$new_input['rest_rate_window']    = self::sanitize_rate_int(
				$input['rest_rate_window'] ?? $sec_defaults['rest_rate_window'],
				0,
				86400,
				(int) $sec_defaults['rest_rate_window']
			);

			// Purge token: alphanumeric and common safe chars, min 16 / max 64 chars.
			// Minimum length matters: a short token (e.g. 4 chars) is trivially
			// brute-forceable against the REST endpoint. We reject silently
			// short tokens with a settings error so admins can see the reason.
			if ( ! empty( $input['purge_token'] ) ) {
				$pt = sanitize_text_field( wp_unslash( $input['purge_token'] ) );
				$pt = preg_replace( '/[^a-zA-Z0-9\-_]/', '', $pt );
				$pt = substr( $pt, 0, 64 );

				if ( strlen( $pt ) >= 16 ) {
					$new_input['purge_token'] = $pt;
				} else {
					// Preserve existing token if a new one fails validation,
					// to avoid accidentally disabling the endpoint.
					if ( ! empty( $existing['purge_token'] ?? '' ) ) {
						$new_input['purge_token'] = $existing['purge_token'];
					} else {
						unset( $new_input['purge_token'] );
					}
					add_settings_error(
						'whmcs_price_option',
						'whmcs_price_purge_token_too_short',
						__( 'Cache Purge Token must be at least 16 characters (letters, digits, hyphen, underscore). Token not saved.', 'whmcs-price' ),
						'error'
					);
				}
			} else {
				unset( $new_input['purge_token'] );
			}

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

			$new_input['promo_code']   = isset( $input['promo_code'] ) ? sanitize_text_field( wp_unslash( $input['promo_code'] ) ) : '';
			$new_input['promo_text']   = isset( $input['promo_text'] ) ? sanitize_text_field( wp_unslash( $input['promo_text'] ) ) : '';
			$new_input['promo_target'] = isset( $input['promo_target'] ) && in_array( $input['promo_target'], array( 'both', 'product', 'domain' ), true ) ? $input['promo_target'] : 'both';
		}

		return $new_input;
	}

	/**
	 * Sanitize a non-negative integer rate-limit setting within bounds.
	 *
	 * @since  2.9.0
	 * @param  mixed $value    Raw input.
	 * @param  int   $min      Minimum allowed value.
	 * @param  int   $max      Maximum allowed value.
	 * @param  int   $fallback Value when input is invalid.
	 * @return int
	 */
	private static function sanitize_rate_int( $value, int $min, int $max, int $fallback ): int {
		if ( ! is_numeric( $value ) ) {
			return $fallback;
		}
		$int = (int) $value;
		if ( $int < $min || $int > $max ) {
			return $fallback;
		}
		return $int;
	}

	// =========================================================
	// CACHE MANAGEMENT
	// =========================================================

	public function clear_whmcs_cache() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Invalidate every cached entry in one atomic version bump — works
		// on both database transients and persistent object caches (Redis,
		// Memcached). Old entries expire naturally via their existing TTL.
		WHMCS_Price_API::bump_cache_version();

		// Locks self-expire (10s TTL) so explicit cleanup is best-effort.
		// On DB-backed sites locks are stored as plain options named
		// `lock_whmcs_*`. On object-cache sites they're in the
		// `whmcs_price_locks` group and don't touch the DB at all.
		if ( ! wp_using_ext_object_cache() ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$lock_keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
					$wpdb->esc_like( 'lock_whmcs_' ) . '%'
				)
			);
			// phpcs:enable
			foreach ( $lock_keys as $key ) {
				delete_option( $key );
			}
		}

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

		// On persistent object cache sites, transients/data live in cache
		// (not wp_options), so SQL counts are always 0 there. Surface this
		// rather than misleadingly reporting "no cache". The reading code
		// in the admin page should treat null counts as "unavailable".
		if ( wp_using_ext_object_cache() ) {
			return array(
				'cache_count'         => null,
				'lock_count'          => null,
				'min_timeout'         => null,
				'max_timeout'         => null,
				'object_cache_active' => true,
			);
		}

		// Versioned-key prefix: _transient_v{N}_whmcs_*
		$version      = (int) get_option( 'whmcs_price_cache_version', 1 );
		$cache_like   = $wpdb->esc_like( '_transient_v' . $version . '_whmcs_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_v' . $version . '_whmcs_' ) . '%';
		$lock_like    = $wpdb->esc_like( 'lock_whmcs_' ) . '%';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$cache_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $cache_like ) );
		$lock_count  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $lock_like ) );
		$min_timeout = $wpdb->get_var( $wpdb->prepare( "SELECT MIN(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
		$max_timeout = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like ) );
		// phpcs:enable
		return array(
			'cache_count'         => $cache_count,
			'lock_count'          => $lock_count,
			'min_timeout'         => null !== $min_timeout ? (int) $min_timeout : null,
			'max_timeout'         => null !== $max_timeout ? (int) $max_timeout : null,
			'object_cache_active' => false,
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
