<?php
/**
 * Plugin Settings and Admin UI
 *
 * Handles the admin menu, settings registration, and provides 
 * usage documentation within the WordPress dashboard.
 *
 * Developer: MohammadReza Kamali, Tobias Sörensson
 * Website: IRANWebServer.Net, weconnect.se
 * License: GPL-3.0-or-later
 *
 * @package    WHMCS_Price
 * @subpackage Admin
 * @since      2.2.0
 */

defined( 'ABSPATH' ) || exit;

class WHMCSPrice {

	private array $options = array();

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'whmcspr_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'whmcspr_init' ) );
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_clear_cache' ), 100 );
		add_action( 'admin_init', array( $this, 'handle_admin_bar_clear_cache_action' ) );
	}

	public function whmcspr_plugin_page() {
		add_menu_page(
			__( 'WHMCS Price Options', 'whmcs-price' ),
			__( 'WHMCS Price Settings', 'whmcs-price' ),
			'manage_options',
			'whmcs_price',
			array( $this, 'whmcspr_admin_page' ),
			'dashicons-admin-generic',
			100
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
			<h1><?php esc_html_e( 'WHMCS Price Options', 'whmcs-price' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'price_option_group' );
				do_settings_sections( 'whmcs_price' );
				submit_button();
				?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Maintenance', 'whmcs-price' ); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field( 'whmcs_clear_cache_action' ); ?>
				<input type="hidden" name="whmcs_clear_cache" value="1" />
				<input type="submit" class="button button-secondary" value="<?php esc_html_e( 'Clear Cache', 'whmcs-price' ); ?>" />
			</form>
		</div>
		<?php
	}

	public function whmcspr_init() {
		register_setting( 'price_option_group', 'whmcs_price_option', array( $this, 'sanitize' ) );

		add_settings_section(
			'setting_section_id',
			'',
			array( $this, 'print_section_info' ),
			'whmcs_price'
		);

		add_settings_field(
			'whmcs_url',
			__( 'WHMCS URL', 'whmcs-price' ),
			array( $this, 'whmcs_url_callback' ),
			'whmcs_price',
			'setting_section_id'
		);

		add_settings_field(
			'products',
			__( 'Product Pricing', 'whmcs-price' ),
			array( $this, 'p_price_callback' ),
			'whmcs_price',
			'setting_section_id'
		);

		add_settings_field(
			'domains',
			__( 'Domain Pricing', 'whmcs-price' ),
			array( $this, 'd_price_callback' ),
			'whmcs_price',
			'setting_section_id'
		);
		
		add_settings_field(
    	'cache_ttl',
    	__( 'Cache Duration', 'whmcs-price' ),
    	array( $this, 'cache_ttl_callback' ),
    	'whmcs_price',
    	'setting_section_id'
		);

		add_settings_field(
			'custom_user_agent',
			__( 'Custom User-Agent', 'whmcs-price' ),
			array( $this, 'custom_user_agent_callback' ),
			'whmcs_price',
			'setting_section_id'
		);
	}

	public function sanitize( $input ): array {
    	$new_input = array();

    	if ( ! empty( $input['whmcs_url'] ) ) {
        	$new_input['whmcs_url'] = esc_url_raw( trim( $input['whmcs_url'] ) );
    	}

    	$allowed_ttls = array( 3600, 7200, 10800, 21600, 43200, 86400 );
    	if ( ! empty( $input['cache_ttl'] ) && in_array( (int) $input['cache_ttl'], $allowed_ttls, true ) ) {
        	$new_input['cache_ttl'] = (int) $input['cache_ttl'];
    	} else {
        	$new_input['cache_ttl'] = 3600; // Fallback: 1 hour.
    	}

		// Custom User-Agent: allow printable ASCII only, max 255 chars.
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

	public function print_section_info() {
		echo esc_html__( 'Dynamic way for extracting price from WHMCS for use on the pages of your website!', 'whmcs-price' ) . '<br /><br />';
		echo esc_html__( 'Please input your WHMCS URL :', 'whmcs-price' );
	}

	public function whmcs_url_callback() {
		$whmcs_url = $this->options['whmcs_url'] ?? '';

		if ( ! empty( $whmcs_url ) && ! filter_var( $whmcs_url, FILTER_VALIDATE_URL ) ) {
			printf( '<p style="color:red">%s</p>', esc_html__( 'Hey! Your domain is not valid!', 'whmcs-price' ) );
		}

		// Warn if URL is not HTTPS — the plugin requires HTTPS for security.
		if ( ! empty( $whmcs_url ) && ! str_starts_with( strtolower( $whmcs_url ), 'https://' ) ) {
			printf(
				'<p style="color:orange"><strong>%s</strong> %s</p>',
				esc_html__( 'Warning:', 'whmcs-price' ),
				esc_html__( 'The WHMCS URL must use HTTPS. HTTP URLs are blocked for security reasons.', 'whmcs-price' )
			);
		}

		printf(
			'<input type="url" id="whmcs_url" class="regular-text" style="direction:ltr;" name="whmcs_price_option[whmcs_url]" value="%s" placeholder="https://whmcsdomain.tld" />',
			esc_attr( $whmcs_url )
		);

		echo '<p style="color:green">' . esc_html__( "Valid URL Format: https://whmcs.com (Don't use \"/\" at the end of WHMCS URL)", 'whmcs-price' ) . '</p>';
		echo '<p>' . esc_html__( 'Note: After changing price in WHMCS, if you are using a cache plugin in your WordPress, to update price you must remove the cache for posts and pages.', 'whmcs-price' ) . '</p>';
		echo '<hr>';
	}

	public function p_price_callback() {
		?>
		<strong><?php esc_html_e( 'How to use shortcode in:', 'whmcs-price' ); ?></strong><br /><br />
		<?php esc_html_e( 'Post / Pages:', 'whmcs-price' ); ?>
		<input type="text" id="sample_product_shortcode" name="sample_product_shortcode" style="width:380px; direction:ltr; cursor: pointer;"
				value="[whmcs pid=&quot;YOUR_PRODUCT_ID&quot; show=&quot;name,description,price&quot; bc=&quot;1m&quot;]"
				onclick="this.select()" readonly />
		<br /><br />
		<?php esc_html_e( 'Theme:', 'whmcs-price' ); ?>
		<input type="text" id="sample_product_php" name="sample_product_php" style="width:600px; direction:ltr; cursor: pointer;"
				value="&lt;?php echo do_shortcode(&#39;[whmcs pid=&quot;1&quot; show=&quot;name,description,price&quot; bc=&quot;1m&quot;]&#39;); ?&gt;"
				onclick="this.select()" readonly />
		<br /><br />
		<pre><strong><?php esc_html_e( 'Documentation:', 'whmcs-price' ); ?></strong><br />
1. <?php esc_html_e( 'Change pid value in shortcode with your Product ID.', 'whmcs-price' ); ?><br />
2. <?php esc_html_e( 'Add show="" to toggle the name, description, price from the data feed of WHMCS', 'whmcs-price' ); ?><br />
3. <?php esc_html_e( 'Change bc value in shortcode with your Billing Cycle Product. Billing Cycles are:', 'whmcs-price' ); ?><br /><br />
<code><?php esc_html_e( 'Monthly (1 Month): bc="1m"', 'whmcs-price' ); ?><br />
<?php esc_html_e( 'Quarterly (3 Month): bc="3m"', 'whmcs-price' ); ?><br />
<?php esc_html_e( 'Semiannually (6 Month): bc="6m"', 'whmcs-price' ); ?><br />
<?php esc_html_e( 'Annually (1 Year): bc="1y"', 'whmcs-price' ); ?><br />
<?php esc_html_e( 'Biennially (2 Year): bc="2y"', 'whmcs-price' ); ?><br />
<?php esc_html_e( 'Triennially (3 Year): bc="3y"', 'whmcs-price' ); ?></code></pre>
		<hr>
		<?php
	}

	public function d_price_callback() {
		?>
		<strong><?php esc_html_e( 'How to use shortcode in:', 'whmcs-price' ); ?></strong><br /><br />
		<?php esc_html_e( 'Post / Pages:', 'whmcs-price' ); ?> 
		<input type="text" id="sample_domain_shortcode" name="sample_domain_shortcode" style="width:500px; direction:ltr; cursor: pointer;" value="[whmcs tld=&quot;com&quot; type=&quot;register&quot; reg=&quot;1y&quot;]" onclick="this.select()" readonly /><br /><br />
		<?php esc_html_e( 'Theme:', 'whmcs-price' ); ?> 
		<input type="text" id="sample_domain_theme" name="sample_domain_theme" style="width:500px; direction:ltr; cursor: pointer;" value="&lt;?php echo do_shortcode(&#39;[whmcs tld=&quot;com&quot; type=&quot;register&quot; reg=&quot;1y&quot;]&#39;); ?&gt;" onclick="this.select()" readonly /><br /><br />
		
		<pre><strong><?php esc_html_e( 'Documentation:', 'whmcs-price' ); ?></strong><br />
1. <?php esc_html_e( 'Change tld value in shortcode with your Domain TLD (com, org, net, ...).', 'whmcs-price' ); ?><br />
2. <?php esc_html_e( 'Change type value in shortcode with register, renew, transfer.', 'whmcs-price' ); ?><br />
3. <?php esc_html_e( 'Change reg value in shortcode with your Register Period of TLD. Register Periods are:', 'whmcs-price' ); ?><br /><br /><code><?php esc_html_e( 'Annually (1 Year): reg="1y"', 'whmcs-price' ); ?><br /><?php esc_html_e( 'Biennially (2 Year): reg="2y"', 'whmcs-price' ); ?><br /><?php esc_html_e( 'Triennially (3 Year): reg="3y"', 'whmcs-price' ); ?></code><br />
4. <?php esc_html_e( 'If left like this [whmcs tld] (without specifying a tld value), it will display all available TLDs from WHMCS.', 'whmcs-price' ); ?></pre>
		<hr>
		<?php
	}

	/**
 	* Renders the cache TTL dropdown field in the admin settings page.
 	*
 	* @since  2.3.1
 	* @access public
 	* @return void
 	*/
	public function cache_ttl_callback() {
   		$current_ttl = isset( $this->options['cache_ttl'] ) ? (int) $this->options['cache_ttl'] : 3600;

    	$ttl_options = array(
        	3600  => __( '1 hour', 'whmcs-price' ),
        	7200  => __( '2 hours', 'whmcs-price' ),
        	10800 => __( '3 hours', 'whmcs-price' ),
        	21600 => __( '6 hours', 'whmcs-price' ),
        	43200 => __( '12 hours', 'whmcs-price' ),
        	86400 => __( '24 hours', 'whmcs-price' ),
    	);

    	echo '<select id="cache_ttl" name="whmcs_price_option[cache_ttl]">';
    	foreach ( $ttl_options as $value => $label ) {
        	printf(
            	'<option value="%d" %s>%s</option>',
            	absint( $value ),
            	selected( $current_ttl, $value, false ),
            	esc_html( $label )
        	);
    	}
    	echo '</select>';
    	echo '<p class="description">' . esc_html__( 'How long prices are cached before fetching fresh data from WHMCS.', 'whmcs-price' ) . '</p>';
    	echo '<hr>';
	}

	/**
	 * Renders the Custom User-Agent field in the admin settings page.
	 *
	 * @since  2.5.0
	 * @access public
	 * @return void
	 */
	public function custom_user_agent_callback() {
		$current_ua = isset( $this->options['custom_user_agent'] ) ? $this->options['custom_user_agent'] : '';

		$site_url       = get_bloginfo( 'url' );
		$plugin_version = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : '';
		$default_ua     = "WordPress ({$site_url}) whmcs-price/{$plugin_version}";

		printf(
			'<input type="text" id="custom_user_agent" class="large-text" style="direction:ltr; font-family:monospace;" name="whmcs_price_option[custom_user_agent]" value="%s" placeholder="%s" />',
			esc_attr( $current_ua ),
			esc_attr( $default_ua )
		);
		echo '<p class="description">';
		printf(
			/* translators: %s: the auto-generated default User-Agent string */
			esc_html__( 'Override the User-Agent sent to WHMCS. Useful for matching server or firewall allow-rules, or identifying requests in access logs. Leave blank to use the default: %s', 'whmcs-price' ),
			'<code>' . esc_html( $default_ua ) . '</code>'
		);
		echo '</p>';
		echo '<hr>';
	}

	public function clear_whmcs_cache() {
		global $wpdb;

		/**
		 * Direct DB query is necessary as WordPress has no bulk transient delete API.
		 * We use delete_transient() for actual deletion (WordPress API).
		 *
		 * @phpcs:disable WordPress.DB.DirectDatabaseQuery
		 */
		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT REPLACE(option_name, '_transient_', '') FROM $wpdb->options WHERE option_name LIKE %s",
				$wpdb->esc_like( '_transient_whmcs_' ) . '%'
			)
		);
		// @phpcs:enable

		foreach ( $transient_keys as $key ) {
			delete_transient( $key );
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
