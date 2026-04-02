<?php
/**
 * WHMCS Price Helpers
 *
 * Shared rendering and formatting utilities used by all output modules:
 * shortcodes, Gutenberg blocks, and Elementor widgets.
 *
 * Adding a new output module? Reuse these helpers instead of duplicating logic.
 * Adding a new formatting feature? Add it here so all modules benefit automatically.
 *
 * @package    WHMCS_Price
 * @subpackage Helpers
 * @since      2.6.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Flush third-party page caches after clearing WHMCS transients.
 *
 * Called automatically at the end of clear_whmcs_cache() in settings.php.
 * Covers the most common WordPress page cache plugins via their documented
 * integration points — action hooks and guarded function/class calls.
 *
 * Does nothing on sites without a supported cache plugin installed.
 * Fires do_action('whmcs_price_after_flush_page_cache') at the end so
 * site owners can hook in their own cache-clear logic if needed.
 *
 * Intentionally excluded:
 *   - wp_cache_flush() — flushes shared object cache (Redis/Memcached),
 *     too aggressive and could affect other plugins or sites.
 *   - Cloudflare — no stable PHP API exposed by their WordPress plugin.
 *
 * @since  2.7.0
 * @return void
 */
function whmcs_price_flush_page_cache(): void {

	// --- Action-hook based (safe to fire even if plugin is not active) ---

	// LiteSpeed Cache.
	do_action( 'litespeed_purge_all' );

	// Hummingbird.
	do_action( 'wphb_clear_page_cache' );

	// Breeze (Cloudways).
	do_action( 'breeze_clear_all_cache' );

	// NitroPack.
	do_action( 'nitropack_integration_purge_all' );

	// Swift Performance.
	do_action( 'swift_performance_after_clear_all_cache' );

	// Cache Enabler (KeyCDN).
	do_action( 'cache_enabler_clear_complete_cache' );

	// Cachify.
	do_action( 'cachify_flush_cache' );

	// Pantheon (managed hosting).
	do_action( 'pantheon_cache_flush' );

	// FlyingPress.
	do_action( 'flying_press_purge_all' );

	// --- Function/class based (guarded before calling) ---

	// WP Rocket.
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain();
	}

	// WP Fastest Cache.
	if ( function_exists( 'wpfc_clear_all_cache' ) ) {
		wpfc_clear_all_cache();
	}

	// W3 Total Cache.
	if ( function_exists( 'w3tc_flush_all' ) ) {
		w3tc_flush_all();
	}

	// WP Super Cache.
	if ( function_exists( 'wp_cache_clear_cache' ) ) {
		wp_cache_clear_cache();
	}

	// SG Optimizer (SiteGround).
	if ( function_exists( 'sg_cachepress_purge_cache' ) ) {
		sg_cachepress_purge_cache();
	}

	// Autoptimize.
	if ( class_exists( 'autoptimizeCache' ) && method_exists( 'autoptimizeCache', 'clearall' ) ) {
		autoptimizeCache::clearall();
	}

	// Comet Cache.
	if ( class_exists( 'comet_cache' ) && method_exists( 'comet_cache', 'clear' ) ) {
		comet_cache::clear();
	}

	// WP Engine (MU plugin).
	if ( class_exists( 'WpeCommon' ) ) {
		if ( method_exists( 'WpeCommon', 'purge_memcached' ) ) {
			WpeCommon::purge_memcached();
		}
		if ( method_exists( 'WpeCommon', 'purge_varnish_cache' ) ) {
			WpeCommon::purge_varnish_cache();
		}
	}

	// Kinsta (MU plugin).
	if ( function_exists( 'kinsta_cache_purge_all_cache' ) ) {
		kinsta_cache_purge_all_cache();
	}

	// Extensibility hook for custom integrations.
	do_action( 'whmcs_price_after_flush_page_cache' );
}

/**
 * Return a styled "pricing unavailable" HTML snippet.
 *
 * Used by all render paths (shortcode, block, Elementor) when WHMCS
 * returns 'NA' for a price or product data field.
 *
 * Wrapped in a <span> so it can be placed inside a <td>, card, or inline
 * context without breaking layout. The class allows theme/site CSS targeting.
 *
 * @since  2.7.0
 * @return string Safe HTML string — already escaped, safe to echo directly.
 */
function whmcs_price_unavailable_html(): string {
	$options        = get_option( 'whmcs_price_option', array() );
	$fallback_price = ! empty( $options['fallback_price'] ) ? trim( $options['fallback_price'] ) : '';

	if ( '' !== $fallback_price ) {
		/**
		 * Filter the fallback price string shown when WHMCS is unavailable.
		 *
		 * @since 2.8.0
		 * @param string $fallback_price The admin-configured fallback price string.
		 */
		$fallback_price = (string) apply_filters( 'whmcs_price_fallback_price', $fallback_price );
		return '<span class="whmcs-price-unavailable whmcs-price-fallback">'
			. esc_html( $fallback_price )
			. '</span>';
	}

	$label = esc_html__( 'Pricing unavailable — please check back shortly.', 'whmcs-price' );

	/**
	 * Filter the "pricing unavailable" label shown when no fallback price is set.
	 *
	 * @since 2.8.0
	 * @param string $label The translated unavailable label.
	 */
	$label = (string) apply_filters( 'whmcs_price_unavailable_label', $label );

	return '<span class="whmcs-price-unavailable">' . esc_html( $label ) . '</span>';
}

/**
 * Send a one-time admin notification when WHMCS becomes unreachable.
 *
 * Uses a transient as a circuit-breaker so at most one e-mail is sent
 * per outage window (default 6 hours). The transient is cleared by
 * whmcs_price_clear_outage() on the next successful WHMCS response,
 * which allows a fresh notification if a second outage occurs later.
 *
 * The notification address defaults to the site admin e-mail but can
 * be overridden via Settings → WHMCS Price Settings → Notifications.
 * Notifications can be disabled entirely from the same screen.
 *
 * @since  2.7.0
 * @param  string $error_context Short description of the failure for the mail body.
 * @return void
 */
function whmcs_price_notify_outage( string $error_context = '' ): void {
	$options = get_option( 'whmcs_price_option', array() );

	// Respect the admin's opt-out.
	if ( isset( $options['outage_notify'] ) && '0' === (string) $options['outage_notify'] ) {
		return;
	}

	// Circuit-breaker: only notify once per outage window.
	if ( false !== get_transient( 'whmcs_price_outage_notified' ) ) {
		return;
	}

	$to      = ! empty( $options['outage_email'] ) ? $options['outage_email'] : get_option( 'admin_email' );
	$subject = sprintf(
		/* translators: %s: site name */
		__( '[%s] WHMCS pricing data unavailable', 'whmcs-price' ),
		get_bloginfo( 'name' )
	);

	$body  = __( 'Hi,', 'whmcs-price' ) . "\n\n";
	$body .= __( 'The Mornolink for WHMCS plugin could not retrieve pricing data from your WHMCS instance.', 'whmcs-price' ) . "\n\n";

	if ( ! empty( $error_context ) ) {
		/* translators: %s: error detail string */
		$body .= sprintf( __( 'Error detail: %s', 'whmcs-price' ), $error_context ) . "\n\n";
	}

	$whmcs_url = ! empty( $options['whmcs_url'] ) ? $options['whmcs_url'] : __( '(not configured)', 'whmcs-price' );
	/* translators: %s: WHMCS URL */
	$body .= sprintf( __( 'WHMCS URL: %s', 'whmcs-price' ), $whmcs_url ) . "\n\n";
	$body .= __( 'Visitors are currently seeing a "pricing unavailable" message in place of live prices.', 'whmcs-price' ) . "\n\n";
	$body .= __( 'Please check that your WHMCS instance is running and reachable.', 'whmcs-price' ) . "\n\n";
	$body .= sprintf(
		/* translators: %s: settings page URL */
		__( 'Plugin settings: %s', 'whmcs-price' ),
		admin_url( 'options-general.php?page=whmcs_price' )
	) . "\n\n";
	$body .= '— ' . __( 'Mornolink for WHMCS', 'whmcs-price' );

	wp_mail( $to, $subject, $body );

	// Prevent repeat notifications for 6 hours.
	set_transient( 'whmcs_price_outage_notified', 1, 6 * HOUR_IN_SECONDS );
}

/**
 * Clear the outage notification circuit-breaker transient.
 *
 * Called on every successful WHMCS response so that a future outage
 * will trigger a fresh notification.
 *
 * @since  2.7.0
 * @return void
 */
function whmcs_price_clear_outage(): void {
	delete_transient( 'whmcs_price_outage_notified' );
}

/**
 * Strip an embedded setup fee suffix from a WHMCS price string.
 *
 * WHMCS's productsinfo.php?get=price may return prices with the setup fee
 * already appended, e.g. "999 kr/yr + 100 kr" or "$99.00 + $10.00 Setup Fee".
 * This function removes the " + ..." suffix so the price field contains only
 * the recurring price. Use WHMCS_Price_API::get_product_setup_fee() to fetch
 * the setup fee separately when needed.
 *
 * @since  2.6.0
 * @param  string $price  Raw price string from productsinfo.php.
 * @return string         Price string with setup fee suffix removed.
 */
function whmcs_price_strip_setup_fee( string $price ): string {
	if ( str_contains( $price, ' + ' ) ) {
		return trim( explode( ' + ', $price, 2 )[0] );
	}
	return $price;
}


/**
 * Format a price string with an optional per-period breakdown.
 *
 * Takes the raw WHMCS price string, computes the per-period value and
 * returns both combined, e.g: "$99.00/yr ($8.25/mo)"
 *
 * Works with any WHMCS price format:
 *   "$9.99"  "€12.50"  "99.00 kr"  "9,99 kr"  "kr 99.00"
 *
 * Usage examples:
 *   whmcs_price_format_per( '$99.00', 'annually', 1, 'month' )
 *   → "$99.00/yr ($8.25/mo)"
 *
 *   whmcs_price_format_per( '199.00 kr', 'annually', 2, 'month' )
 *   → "199.00 kr/2yr (8.29 kr/mo)"
 *
 * @since  2.6.0
 * @param  string $price       Raw price string from WHMCS (e.g. "$99.00").
 * @param  string $bc_internal Internal WHMCS billing cycle (e.g. "annually").
 * @param  int    $reg_years   Registration period in years (for domains; use 1 for products).
 * @param  string $per         Desired per-period unit: month | week | day.
 * @return string              Combined price string, or original price if per is empty/invalid.
 */
function whmcs_price_format_per( string $price, string $bc_internal, int $reg_years, string $per ): string {
	$allowed_per = array( 'month', 'week', 'day' );
	$per         = strtolower( trim( $per ) );

	if ( ! in_array( $per, $allowed_per, true ) || empty( trim( $price ) ) ) {
		return esc_html( $price );
	}

	// Total months in this billing cycle × registration period in years.
	$total_months = WHMCS_Price_API::billing_cycle_months( $bc_internal ) * max( 1, $reg_years );

	$divisor = match ( $per ) {
		'month' => (float) $total_months,
		'week'  => $total_months * 4.33,
		'day'   => $total_months * 30.44,
		default => 1.0,
	};

	// Suffix for the original (full-period) price.
	$cycle_suffix = array(
		'monthly'      => __( '/mo', 'whmcs-price' ),
		'quarterly'    => __( '/qtr', 'whmcs-price' ),
		'semiannually' => __( '/6mo', 'whmcs-price' ),
		'annually'     => __( '/yr', 'whmcs-price' ),
		'biennially'   => __( '/2yr', 'whmcs-price' ),
		'triennially'  => __( '/3yr', 'whmcs-price' ),
	);

	// Multi-year domain registration gets its own suffix.
	if ( $reg_years > 1 ) {
		/* translators: %d: number of years */
		$orig_suffix = sprintf( __( '/%dyr', 'whmcs-price' ), $reg_years );
	} else {
		$orig_suffix = $cycle_suffix[ $bc_internal ] ?? '';
	}

	$per_suffix = match ( $per ) {
		'month' => __( '/mo', 'whmcs-price' ),
		'week'  => __( '/wk', 'whmcs-price' ),
		'day'   => __( '/day', 'whmcs-price' ),
		default => '',
	};

	$divided = WHMCS_Price_API::divide_price( $price, $divisor );

	// Return HTML with two separate elements so each can be styled independently.
	return '<span class="whmcs-price-full">' . esc_html( $price . $orig_suffix ) . '</span>'
		. '<span class="whmcs-price-per">' . esc_html( $divided . $per_suffix ) . '</span>';
}

/**
 * Generate Schema.org JSON-LD markup for a WHMCS product price.
 *
 * Outputs a <script type="application/ld+json"> element with Product and
 * Offer structured data. This can improve search engine visibility and
 * enable rich results (pricing shown directly in Google search results).
 *
 * Only outputs when the price is available (not 'NA'). Silently returns an
 * empty string if the price cannot be fetched or is unavailable.
 *
 * Can be suppressed via the filter whmcs_price_enable_schema (return false).
 *
 * @since  2.8.0
 * @param  string $name     Product name, already fetched from WHMCS.
 * @param  string $price    Raw price string from WHMCS (e.g. "999 Kr").
 * @param  string $currency ISO 4217 currency code override. If empty,
 *                          the plugin attempts to extract it from the price
 *                          string — falls back to site locale currency.
 * @param  string $url      Canonical URL for the product. Defaults to current URL.
 * @return string           Safe JSON-LD <script> block, or empty string.
 */
function whmcs_price_schema_product( string $name, string $price, string $currency = '', string $url = '' ): string {
	/**
	 * Filter: disable Schema.org output globally or conditionally.
	 *
	 * @since 2.8.0
	 * @param bool $enabled Whether to output JSON-LD. Default true.
	 */
	if ( false === apply_filters( 'whmcs_price_enable_schema', true ) ) {
		return '';
	}

	if ( 'NA' === $price || empty( $price ) || empty( $name ) ) {
		return '';
	}

	// Extract numeric price value from raw WHMCS string (e.g. "999 Kr" → "999").
	$numeric = preg_replace( '/[^0-9.,]/', '', $price );
	$numeric = str_replace( ',', '.', $numeric );
	$numeric = is_numeric( $numeric ) ? $numeric : '';

	if ( '' === $numeric ) {
		return '';
	}

	// Attempt to detect currency from price string if not provided.
	if ( empty( $currency ) ) {
		$currency_map = array(
			'kr'  => 'SEK',
			'sek' => 'SEK',
			'nok' => 'NOK',
			'dkk' => 'DKK',
			'eur' => 'EUR',
			'€'   => 'EUR',
			'gbp' => 'GBP',
			'£'   => 'GBP',
			'usd' => 'USD',
			'$'   => 'USD',
			'chf' => 'CHF',
		);
		$price_lower = strtolower( $price );
		foreach ( $currency_map as $symbol => $iso ) {
			if ( str_contains( $price_lower, $symbol ) ) {
				$currency = $iso;
				break;
			}
		}
	}

	$url = ! empty( $url ) ? esc_url_raw( $url ) : ( is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ) );

	$schema = array(
		'@context'    => 'https://schema.org/',
		'@type'       => 'Product',
		'name'        => wp_strip_all_tags( $name ),
		'url'         => $url,
		'offers'      => array(
			'@type'         => 'Offer',
			'url'           => $url,
			'price'         => $numeric,
			'priceCurrency' => ! empty( $currency ) ? strtoupper( $currency ) : 'USD',
			'availability'  => 'https://schema.org/InStock',
		),
	);

	/**
	 * Filter the Schema.org data array before it is encoded and output.
	 *
	 * @since 2.8.0
	 * @param array  $schema   The schema.org data array.
	 * @param string $name     Product name.
	 * @param string $price    Raw price string.
	 * @param string $currency Resolved ISO currency code.
	 */
	$schema = (array) apply_filters( 'whmcs_price_schema_data', $schema, $name, $price, $currency );

	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>' . "\n";
}

/**
 * Generate Schema.org JSON-LD markup for a WHMCS domain offer.
 *
 * Similar to whmcs_price_schema_product() but typed as a Service with
 * the domain TLD as the name. Useful for TLD-specific landing pages.
 *
 * @since  2.8.0
 * @param  string $tld      Domain extension without dot, e.g. "se".
 * @param  string $price    Raw price string from WHMCS.
 * @param  string $type     Transaction type: register, renew, transfer.
 * @param  string $currency ISO 4217 currency code override.
 * @param  string $url      Canonical URL. Defaults to current URL.
 * @return string           Safe JSON-LD <script> block, or empty string.
 */
function whmcs_price_schema_domain( string $tld, string $price, string $type = 'register', string $currency = '', string $url = '' ): string {
	if ( false === apply_filters( 'whmcs_price_enable_schema', true ) ) {
		return '';
	}

	if ( 'NA' === $price || empty( $price ) || empty( $tld ) ) {
		return '';
	}

	$numeric = preg_replace( '/[^0-9.,]/', '', $price );
	$numeric = str_replace( ',', '.', $numeric );
	$numeric = is_numeric( $numeric ) ? $numeric : '';

	if ( '' === $numeric ) {
		return '';
	}

	$type_labels = array(
		'register' => 'Domain Registration',
		'renew'    => 'Domain Renewal',
		'transfer' => 'Domain Transfer',
	);
	$service_name = sprintf( '.%s %s', strtolower( $tld ), $type_labels[ $type ] ?? 'Domain' );

	// Detect currency from price string if not provided.
	if ( empty( $currency ) ) {
		$currency_map = array(
			'kr' => 'SEK', 'sek' => 'SEK', 'nok' => 'NOK', 'dkk' => 'DKK',
			'eur' => 'EUR', '€' => 'EUR', 'gbp' => 'GBP', '£' => 'GBP',
			'usd' => 'USD', '$' => 'USD', 'chf' => 'CHF',
		);
		$price_lower = strtolower( $price );
		foreach ( $currency_map as $symbol => $iso ) {
			if ( str_contains( $price_lower, $symbol ) ) {
				$currency = $iso;
				break;
			}
		}
	}

	$url = ! empty( $url ) ? esc_url_raw( $url ) : ( is_singular() ? get_permalink() : home_url( add_query_arg( array() ) ) );

	$schema = array(
		'@context' => 'https://schema.org/',
		'@type'    => 'Product',
		'name'     => $service_name,
		'url'      => $url,
		'offers'   => array(
			'@type'         => 'Offer',
			'url'           => $url,
			'price'         => $numeric,
			'priceCurrency' => ! empty( $currency ) ? strtoupper( $currency ) : 'USD',
			'availability'  => 'https://schema.org/InStock',
		),
	);

	/** @see whmcs_price_schema_data filter above */
	$schema = (array) apply_filters( 'whmcs_price_schema_data', $schema, $service_name, $price, $currency );

	$json = wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	if ( false === $json ) {
		return '';
	}

	return '<script type="application/ld+json">' . $json . '</script>' . "\n";
}
