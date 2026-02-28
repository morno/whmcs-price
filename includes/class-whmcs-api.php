<?php
/**
 * WHMCS Data Service Class
 *
 * This class handles all communication with WHMCS API feeds. It manages
 * data retrieval, cleaning of Javascript-wrapped responses, and implements
 * caching via the WordPress Transients API to optimize performance.
 *
 * @package    WHMCS_Price
 * @subpackage API
 * @since      2.2.0
 */

defined('ABSPATH') || exit;

class WHMCS_Price_API {

    /**
     * Default cache expiry time in seconds (1 hour).
     *
     * @since 2.2.0
     * @var int
     */
    private static $cache_expiry = 3600; // Fallback if TTL value in WHMCS Price Settings is missed

    /**
     * Retrieve the WHMCS base URL from plugin settings.
     *
     * @since 2.2.0
     * @access private
     * @return string The saved WHMCS URL or an empty string if not configured.
     */
    private static function get_url() {
        $options = get_option('whmcs_price_option');
        if ( empty( $options['whmcs_url'] ) ) {
            return '';
        }

        $url    = esc_url_raw( $options['whmcs_url'] );
        $parsed = wp_parse_url( $url );
        $host   = strtolower( $parsed['host'] ?? '' );

        // Block private/internal IP ranges and localhost (SSRF protection)
        $blocked_hosts = array( 'localhost', 'localhost.localdomain' );
        if ( in_array( $host, $blocked_hosts, true ) ) {
            return '';
        }

        // Block private IPv4 ranges and loopback
        if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
            if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
                return '';
            }
        }

        // Block IPv6 loopback
        if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
            if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
                return '';
            }
        }

        // Block cloud metadata endpoints and known internal hostnames (SSRF protection).
        // These can bypass IP-based checks via DNS or hostname patterns.
        $blocked_patterns = array(
            '169.254.169.254',       // AWS / Azure / GCP instance metadata.
            '100.100.100.200',       // Alibaba Cloud metadata.
            'metadata.google.internal',
            'metadata.google',
            'instance-data',         // Some cloud providers use this hostname.
        );
        foreach ( $blocked_patterns as $pattern ) {
            if ( str_contains( $host, $pattern ) ) {
                return '';
            }
        }

        // Enforce HTTPS to prevent credentials or data from leaking over plain HTTP.
        $scheme = strtolower( $parsed['scheme'] ?? '' );
        if ( 'https' !== $scheme ) {
            return '';
        }

        return $url;
    }

    /**
    * Retrieve the configured cache TTL from plugin settings.
    *
    * Falls back to the default value of 3600 seconds (1 hour)
    * if no TTL has been saved in the options.
    *
    * @since  2.3.1
    * @access private
    * @return int Cache expiry time in seconds.
    */
    private static function get_cache_expiry(): int {
        $options = get_option( 'whmcs_price_option' );
        return isset( $options['cache_ttl'] ) ? (int) $options['cache_ttl'] : self::$cache_expiry;
    }

    /**
     * Log debug messages when WP_DEBUG is enabled.
     *
     * @since 2.3.0
     * @access private
     * @param string $message The message to log.
     * @param array  $context Additional context data.
     * @return void
     */
    private static function debug_log( $message, $context = array() ) {
        if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
            $log_message = '[WHMCS Price] ' . $message;
            if ( ! empty( $context ) ) {
                $log_message .= ' | Context: ' . wp_json_encode( $context );
            }
            error_log( $log_message );
        }
    }

    /**
    * Acquire a short-lived lock to prevent cache stampede.
    *
    * Prevents multiple simultaneous requests from hammering WHMCS
    * when the cache is cold or has just been cleared.
    *
    * @since  2.3.1
    * @access private
    * @param  string $lock_key Unique key for this lock.
    * @return bool True if lock was acquired, false if already locked.
    */
    private static function acquire_lock( string $lock_key ): bool {
        $lock = get_transient( $lock_key );
        if ( false !== $lock ) {
            return false; // Already locked
        }
        set_transient( $lock_key, 1, 10 ); // Lock expires after 10 seconds
        return true;
    }

    /**
     * Build HTTP request arguments for all WHMCS API calls.
     *
     * Default User-Agent: WordPress (https://yoursite.com) whmcs-price/2.5.0
     * Can be overridden via the Custom User-Agent setting in the admin.
     *
     * @since  2.5.0
     * @access private
     * @return array WordPress HTTP API argument array.
     */
    private static function get_request_args(): array {
        $options    = get_option( 'whmcs_price_option', array() );
        $custom_ua  = ! empty( $options['custom_user_agent'] ) ? trim( $options['custom_user_agent'] ) : '';

        if ( ! empty( $custom_ua ) ) {
            $user_agent = $custom_ua;
        } else {
            $site_url       = get_bloginfo( 'url' );
            $plugin_version = defined( 'WHMCS_PRICE_VERSION' ) ? WHMCS_PRICE_VERSION : 'unknown';
            $user_agent     = "WordPress ({$site_url}) whmcs-price/{$plugin_version}";
        }

        return array(
            'user-agent' => $user_agent,
            'timeout'    => 15,
        );
    }

	/**
	 * Clean WHMCS JS-feed responses by stripping Javascript wrappers.
	 *
	 * WHMCS feeds are often delivered as 'document.write' JS strings.
	 * This method extracts the raw content so it can be safely used in HTML.
	 *
	 * @since  2.2.0
	 * @access private
	 * @param  string $body The raw response body from the API request.
	 * @return string The cleaned text string.
	 */
	private static function clean_response($body) {
        $body = preg_replace('/document\.write\(\'/', '', $body);
        $body = preg_replace('/\'\);/', '', $body);
        return trim($body);
    }

	/**
	 * Fetch Product Data (name, description, or price) from WHMCS.
	 *
	 * Utilizes WordPress transients to store the result based on a unique key
	 * consisting of the Product ID, billing cycle, and requested attribute.
	 * A short-lived lock prevents cache stampede on simultaneous requests.
	 *
	 * @since  2.2.0
	 * @since  2.3.1 Added cache stampede protection via acquire_lock().
	 * @access public
	 * @param  int    $pid           The Product ID in WHMCS.
	 * @param  string $billing_cycle The billing cycle (e.g., monthly, annually).
	 * @param  string $attribute     The field to retrieve (e.g., name, description, price).
	 * @return string Returns the data from WHMCS or 'NA' on failure.
	 */
	public static function get_product_data($pid, $billing_cycle, $attribute) {
		$whmcs_url = self::get_url();

		if ( empty( $whmcs_url ) ) {
			self::debug_log( 'Product data request failed: WHMCS URL not configured', array(
				'pid'           => $pid,
				'billing_cycle' => $billing_cycle,
				'attribute'     => $attribute,
			) );
			return 'NA';
		}

		// Allowlist: only permit known valid attribute values to prevent parameter injection.
		$allowed_attributes = array( 'name', 'description', 'price' );
		if ( ! in_array( $attribute, $allowed_attributes, true ) ) {
			self::debug_log( 'Product data request blocked: invalid attribute', array(
				'attribute' => $attribute,
			) );
			return 'NA';
		}

		// Allowlist: only permit known valid billing cycles.
		$allowed_billing_cycles = array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' );
		if ( ! in_array( $billing_cycle, $allowed_billing_cycles, true ) ) {
			self::debug_log( 'Product data request blocked: invalid billing cycle', array(
				'billing_cycle' => $billing_cycle,
			) );
			return 'NA';
		}

		$cache_key = 'whmcs_product_' . md5( $pid . '_' . $billing_cycle . '_' . $attribute );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			self::debug_log( 'Product data served from cache', array(
				'cache_key' => $cache_key,
				'value'     => $cached,
			) );
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'Product data request skipped: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return 'NA';
		}

		// Use add_query_arg() to properly URL-encode all parameters and prevent query injection.
		$url = add_query_arg(
			array(
				'pid'          => intval( $pid ),
				'get'          => $attribute,
				'billingcycle' => $billing_cycle,
			),
			$whmcs_url . '/feeds/productsinfo.php'
		);

		self::debug_log( 'Fetching product data from WHMCS', array(
			'url'           => $url,
			'pid'           => $pid,
			'billing_cycle' => $billing_cycle,
			'attribute'     => $attribute,
		) );

		$response = wp_remote_get( $url, self::get_request_args() );

		if ( is_wp_error( $response ) ) {
			self::debug_log( 'Product data request error', array(
				'error' => $response->get_error_message(),
				'url'   => $url,
			) );
			delete_transient( $lock_key ); // Release lock on failure.
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			self::debug_log( 'Product data request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			delete_transient( $lock_key ); // Release lock on HTTP error.
			return 'NA';
		}

		$data = self::clean_response( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'Product data fetched successfully', array(
			'cache_key' => $cache_key,
			'value'     => $data,
		) );

		set_transient( $cache_key, $data, self::get_cache_expiry() );
		delete_transient( $lock_key ); // Release lock after successful cache write.

		return $data;
	}

	/**
	 * Fetch Domain Pricing for a specific TLD and type.
	 *
	 * A short-lived lock prevents cache stampede on simultaneous requests.
	 *
	 * @since  2.2.0
	 * @since  2.3.1 Added cache stampede protection via acquire_lock().
	 * @access public
	 * @param  string $tld        The domain extension (e.g., com, net, org).
	 * @param  string $type       Transaction type (register, renew, transfer).
	 * @param  string $reg_period Registration period in years (e.g., 1, 2, 3).
	 * @return string Returns the formatted price string or 'NA' on failure.
	 */
	public static function get_domain_price($tld, $type, $reg_period) {
		$whmcs_url = self::get_url();

		if ( empty( $whmcs_url ) ) {
			self::debug_log( 'Domain price request failed: WHMCS URL not configured', array(
				'tld'        => $tld,
				'type'       => $type,
				'reg_period' => $reg_period,
			) );
			return 'NA';
		}

		// Allowlist: only permit known valid transaction types to prevent parameter injection.
		$allowed_types = array( 'register', 'renew', 'transfer' );
		if ( ! in_array( $type, $allowed_types, true ) ) {
			self::debug_log( 'Domain price request blocked: invalid type', array(
				'type' => $type,
			) );
			return 'NA';
		}

		// Allowlist: reg_period must be a positive integer between 1 and 10.
		$reg_period_int = intval( $reg_period );
		if ( $reg_period_int < 1 || $reg_period_int > 10 ) {
			self::debug_log( 'Domain price request blocked: invalid reg_period', array(
				'reg_period' => $reg_period,
			) );
			return 'NA';
		}

		$tld = ltrim( $tld, '.' );
		$tld = preg_replace( '/[^a-zA-Z0-9\-]/', '', $tld );
		$tld = substr( $tld, 0, 24 );

		if ( empty( $tld ) ) {
    		self::debug_log( 'Domain price request blocked: invalid TLD after sanitization' );
    		return 'NA';
		}

		$cache_key = 'whmcs_domain_' . md5( $tld . '_' . $type . '_' . $reg_period );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			self::debug_log( 'Domain price served from cache', array(
				'cache_key' => $cache_key,
				'value'     => $cached,
			) );
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'Domain price request skipped: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return 'NA';
		}

		// Use add_query_arg() to properly URL-encode all parameters and prevent query injection.
		$url = add_query_arg(
			array(
				'tld'       => '.' . $tld,
				'type'      => $type,
				'regperiod' => $reg_period_int,
				'format'    => '1',
			),
			$whmcs_url . '/feeds/domainprice.php'
		);

		self::debug_log( 'Fetching domain price from WHMCS', array(
			'url'        => $url,
			'tld'        => $tld,
			'type'       => $type,
			'reg_period' => $reg_period,
		) );

		$response = wp_remote_get( $url, self::get_request_args() );

		if ( is_wp_error( $response ) ) {
			self::debug_log( 'Domain price request error', array(
				'error' => $response->get_error_message(),
				'url'   => $url,
			) );
			delete_transient( $lock_key ); // Release lock on failure.
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			self::debug_log( 'Domain price request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			delete_transient( $lock_key ); // Release lock on HTTP error.
			return 'NA';
		}

		$data = self::clean_response( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'Domain price fetched successfully', array(
			'cache_key' => $cache_key,
			'value'     => $data,
		) );

		set_transient( $cache_key, $data, self::get_cache_expiry() );
		delete_transient( $lock_key ); // Release lock after successful cache write.

		return $data;
	}

    /**
	 * Fetch All Domain Prices from WHMCS (no specific TLD).
	 *
	 * When no TLD is specified, WHMCS returns pricing for all available
	 * domain extensions as a raw string or HTML table depending on the feed.
	 * A short-lived lock prevents cache stampede on simultaneous requests.
	 *
	 * @since  2.3.0
	 * @since  2.3.1 Added cache stampede protection via acquire_lock().
	 * @access public
	 * @return string Returns the raw domain pricing data or 'NA' on failure.
	 */
	public static function get_all_domain_prices() {
		$whmcs_url = self::get_url();

		if ( empty( $whmcs_url ) ) {
			self::debug_log( 'All domain prices request failed: WHMCS URL not configured' );
			return 'NA';
		}

		$cache_key = 'whmcs_domain_all';
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			self::debug_log( 'All domain prices served from cache', array(
				'cache_key'   => $cache_key,
				'data_length' => strlen( $cached ),
			) );
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'All domain prices request skipped: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return 'NA';
		}

		$url = "{$whmcs_url}/feeds/domainpricing.php";

		self::debug_log( 'Fetching all domain prices from WHMCS', array(
			'url' => $url,
		) );

		$response = wp_remote_get( $url, self::get_request_args() );

		if ( is_wp_error( $response ) ) {
			self::debug_log( 'All domain prices request error', array(
				'error' => $response->get_error_message(),
				'url'   => $url,
			) );
			delete_transient( $lock_key ); // Release lock on failure.
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( $response_code !== 200 ) {
			self::debug_log( 'All domain prices request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			delete_transient( $lock_key ); // Release lock on HTTP error.
			return 'NA';
		}

		$data = self::clean_response( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'All domain prices fetched successfully', array(
			'cache_key'       => $cache_key,
			'data_length'     => strlen( $data ),
			'first_100_chars' => substr( $data, 0, 100 ),
		) );

		set_transient( $cache_key, $data, self::get_cache_expiry() );
		delete_transient( $lock_key ); // Release lock after successful cache write.

		return $data;
	}
}