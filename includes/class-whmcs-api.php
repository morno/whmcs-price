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

defined( 'ABSPATH' ) || exit;

class WHMCS_Price_API {

	/**
	 * Default cache expiry time in seconds (1 hour).
	 *
	 * @since 2.2.0
	 * @var int
	 */
	private static $cache_expiry = 3600; // Fallback if TTL value in WHMCS Price Settings is missed

	/**
	 * In-memory request cache.
	 *
	 * Stores results for the duration of the current PHP process so that the
	 * same data is never fetched twice in a single request — even if multiple
	 * shortcodes, blocks, or Elementor widgets on the same page ask for it.
	 * This is a second caching layer above WordPress transients; it survives
	 * only for the lifetime of the request and uses no persistent storage.
	 *
	 * @since  2.7.1
	 * @var    array<string, string>
	 */
	private static array $request_cache = array();

	/**
	 * Cached cache-version integer for the current request.
	 *
	 * Read once per process from the `whmcs_price_cache_version` option and
	 * reused for every subsequent cache-key construction. Avoids a get_option()
	 * call on every cache hit.
	 *
	 * @since  2.9.0
	 * @var    int|null
	 */
	private static ?int $cache_version = null;

	/**
	 * Return the current cache version. Lazy-loaded and request-cached.
	 *
	 * Stored in the `whmcs_price_cache_version` option. Incremented by
	 * bump_cache_version() to invalidate all cached entries at once.
	 *
	 * @since  2.9.0
	 * @access private
	 * @return int
	 */
	private static function get_cache_version(): int {
		if ( null === self::$cache_version ) {
			self::$cache_version = (int) get_option( 'whmcs_price_cache_version', 1 );
			if ( self::$cache_version < 1 ) {
				self::$cache_version = 1;
			}
		}
		return self::$cache_version;
	}

	/**
	 * Wrap a cache key with the current cache-version prefix.
	 *
	 * Including the version in the key means a single option update
	 * invalidates every cached entry atomically — no key inventory needed,
	 * works identically on database transients and persistent object caches.
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $key Base cache key.
	 * @return string      Versioned cache key, e.g. "v3_whmcs_product_<md5>".
	 */
	private static function versioned_key( string $key ): string {
		return 'v' . self::get_cache_version() . '_' . $key;
	}

	/**
	 * Bump the cache version, invalidating all cached entries at once.
	 *
	 * This is the canonical "purge cache" operation: it works on every cache
	 * backend (database transients, Redis, Memcached) because it simply makes
	 * every previously-stored key unreachable. Old entries expire naturally
	 * via their existing TTL.
	 *
	 * Also flushes the local 'whmcs_price' object-cache group so any in-process
	 * caches are cleared immediately, and the request-scoped static cache is
	 * reset so the same PHP process picks up the new version straight away.
	 *
	 * @since  2.9.0
	 * @access public
	 * @return int The new cache version.
	 */
	public static function bump_cache_version(): int {
		$current = (int) get_option( 'whmcs_price_cache_version', 1 );
		$next    = $current + 1;
		update_option( 'whmcs_price_cache_version', $next, false );

		// Reset the request-scoped caches so this process sees the bump.
		self::$cache_version = $next;
		self::$request_cache = array();

		// Flush our own object-cache group — old versioned entries become
		// unreachable anyway, but this releases memory immediately.
		if ( function_exists( 'wp_cache_flush_group' ) ) {
			wp_cache_flush_group( 'whmcs_price' );
		}

		return $next;
	}

	/**
	 * Retrieve a cached value — checks object cache first, then transient.
	 *
	 * On sites with a persistent object cache (Redis, Memcached), the object
	 * cache is significantly faster than a database transient lookup. This
	 * method checks the object cache first and falls back to the transient,
	 * keeping the two layers in sync automatically.
	 *
	 * The key is automatically wrapped with the current cache version so that
	 * bump_cache_version() invalidates every entry in one operation.
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $transient_key Transient key (without _transient_ prefix).
	 * @return mixed                  Cached value or false if not found.
	 */
	private static function get_cache( string $transient_key ): mixed {
		$key = self::versioned_key( $transient_key );

		// Try object cache first (Redis/Memcached) — faster than DB.
		$cached = wp_cache_get( $key, 'whmcs_price' );
		if ( false !== $cached ) {
			return $cached;
		}
		// Fall back to transient (database).
		return get_transient( $key );
	}

	/**
	 * Store a value in both object cache and transient.
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $transient_key Transient key.
	 * @param  mixed  $value         Value to cache.
	 * @param  int    $expiry        TTL in seconds.
	 * @return void
	 */
	private static function set_cache( string $transient_key, mixed $value, int $expiry ): void {
		$key = self::versioned_key( $transient_key );
		wp_cache_set( $key, $value, 'whmcs_price', $expiry );
		set_transient( $key, $value, $expiry );
	}

	/**
	 * Delete a value from both object cache and transient.
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $transient_key Transient key.
	 * @return void
	 */
	private static function delete_cache( string $transient_key ): void {
		$key = self::versioned_key( $transient_key );
		wp_cache_delete( $key, 'whmcs_price' );
		delete_transient( $key );
	}

	/**
	 * Retrieve the WHMCS base URL from plugin settings.
	 *
	 * Validated against SSRF: rejects URLs with embedded credentials,
	 * non-standard ports, private/loopback IPs (IPv4 + IPv6), cloud-metadata
	 * endpoints, plain HTTP, and hostnames that resolve to private IPs.
	 *
	 * Public so other plugin subsystems (Site Health, admin diagnostics)
	 * can reuse the same validation instead of touching raw options.
	 *
	 * @since 2.2.0
	 * @since 2.9.0 Made public.
	 * @return string The validated WHMCS URL or an empty string if not configured/invalid.
	 */
	public static function get_url(): string {
		$options = get_option( 'whmcs_price_option' );
		if ( empty( $options['whmcs_url'] ) ) {
			return '';
		}

		$url    = esc_url_raw( $options['whmcs_url'] );
		$parsed = wp_parse_url( $url );
		$host   = strtolower( $parsed['host'] ?? '' );

		// Block URLs with embedded credentials (https://user:pass@host).
		// These serve no legitimate purpose for a WHMCS feed URL and
		// could be used to obscure the real destination.
		if ( isset( $parsed['user'] ) || isset( $parsed['pass'] ) ) {
			return '';
		}

		// Block non-standard ports. WHMCS feeds should always be on 443.
		// Allowing arbitrary ports widens the SSRF attack surface.
		if ( isset( $parsed['port'] ) && 443 !== (int) $parsed['port'] ) {
			return '';
		}

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

		// SSRF: resolve hostname and reject if any DNS record points to a private/reserved IP.
		// Fail closed: if the hostname cannot be resolved, the URL is rejected.
		if ( ! filter_var( $host, FILTER_VALIDATE_IP ) ) {
			$resolved_ok = false;

			if ( function_exists( 'dns_get_record' ) ) {
				// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				$resolved = @dns_get_record( $host, DNS_A | DNS_AAAA );
				if ( is_array( $resolved ) && ! empty( $resolved ) ) {
					$resolved_ok = true;
					foreach ( $resolved as $record ) {
						$ip = $record['ip'] ?? $record['ipv6'] ?? '';
						if ( $ip && filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
							return '';
						}
					}
				}
			} else {
				// Fallback when dns_get_record() is unavailable (A records only).
				$resolved_ip = gethostbyname( $host );
				if ( $resolved_ip && $resolved_ip !== $host ) {
					$resolved_ok = true;
					if ( filter_var( $resolved_ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) === false ) {
						return '';
					}
				}
			}

			if ( ! $resolved_ok ) {
				return '';
			}
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
		error_log( $log_message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
	}

	/**
	 * Lock TTL in seconds. Locks self-expire after this period — a request
	 * that takes longer than this to reach WHMCS will lose its lock and may
	 * be raced by another process, but that is preferable to a deadlock
	 * caused by a crashed/timed-out process leaving an indefinite lock.
	 *
	 * @since 2.9.0
	 */
	private const LOCK_TTL = 10;

	/**
	* Acquire a short-lived lock to prevent cache stampede.
	*
	* Atomic across processes:
	*   - On sites with a persistent object cache: wp_cache_add() is an
	*     atomic add-if-absent primitive backed by the cache server
	*     (Redis SETNX, Memcached ADD, etc.).
	*   - On sites without object cache: add_option() is atomic via the
	*     UNIQUE constraint on options.option_name. If two processes call
	*     add_option() concurrently, only one row insert succeeds.
	*
	* The previous implementation used get_transient() then set_transient(),
	* which is a non-atomic check-then-set — both processes could see the
	* slot empty and both proceed to hammer WHMCS.
	*
	* @since  2.3.1
	* @since  2.9.0 Made atomic.
	* @access private
	* @param  string $lock_key Unique key for this lock.
	* @return bool True if lock was acquired, false if already locked.
	*/
	private static function acquire_lock( string $lock_key ): bool {
		// Persistent object cache path: atomic SETNX-equivalent with native TTL.
		if ( wp_using_ext_object_cache() ) {
			return (bool) wp_cache_add( $lock_key, 1, 'whmcs_price_locks', self::LOCK_TTL );
		}

		// DB path: encode expiry timestamp in the value so we can detect
		// stale locks left behind by crashed/timed-out processes.
		$expires_at = time() + self::LOCK_TTL;
		if ( add_option( $lock_key, (string) $expires_at, '', 'no' ) ) {
			return true;
		}

		// add_option returned false → row exists. If the existing lock has
		// expired, try to claim it. Worst case in a race here is that two
		// processes both delete and add_option — the second add_option will
		// return false, so only one process ends up holding the lock.
		$existing = (int) get_option( $lock_key, 0 );
		if ( $existing > 0 && $existing < time() ) {
			delete_option( $lock_key );
			return (bool) add_option( $lock_key, (string) $expires_at, '', 'no' );
		}

		return false;
	}

	/**
	 * Check whether a lock is currently held (and not yet expired).
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $lock_key Lock key.
	 * @return bool             True if locked, false otherwise.
	 */
	private static function is_locked( string $lock_key ): bool {
		if ( wp_using_ext_object_cache() ) {
			return false !== wp_cache_get( $lock_key, 'whmcs_price_locks' );
		}
		// add_option() with autoload='no' is NOT in the alloptions cache;
		// get_option() will hit the DB on miss. Bypass the runtime cache so
		// we see writes from other processes immediately.
		wp_cache_delete( $lock_key, 'options' );
		$expires_at = (int) get_option( $lock_key, 0 );
		return $expires_at > time();
	}

	/**
	 * Release a held lock.
	 *
	 * @since  2.9.0
	 * @access private
	 * @param  string $lock_key Lock key.
	 * @return void
	 */
	private static function release_lock( string $lock_key ): void {
		if ( wp_using_ext_object_cache() ) {
			wp_cache_delete( $lock_key, 'whmcs_price_locks' );
			return;
		}
		delete_option( $lock_key );
	}

	/**
	 * Wait for a lock to be released and return the cached value once available.
	 *
	 * When a concurrent PHP process (e.g. a ServerSideRender REST request) finds
	 * that another process is already fetching the same data from WHMCS, instead
	 * of immediately returning 'NA' it polls the transient cache at short intervals.
	 * Once the first process writes the result, this process reads it from cache
	 * and returns it without making a second WHMCS HTTP request.
	 *
	 * @since  2.7.1
	 * @access private
	 * @param  string $cache_key Transient key to poll.
	 * @param  string $lock_key  Lock transient key to monitor.
	 * @return string            Cached value once available, or 'NA' on timeout.
	 */
	private static function wait_for_cache( string $cache_key, string $lock_key ): string {
		$attempts  = 0;
		$max_attempts = 3;   // 3 × 150ms = 450ms max wait
		$sleep_us  = 150000; // 150ms in microseconds

		while ( $attempts < $max_attempts ) {
			usleep( $sleep_us );
			$attempts++;

			// Check if the lock was released and the cache was populated.
			$cached = self::get_cache( $cache_key );
			if ( false !== $cached ) {
				self::debug_log( 'Data available after waiting for lock', array(
					'cache_key' => $cache_key,
					'attempts'  => $attempts,
				) );
				return $cached;
			}

			// If the lock is also gone but cache is still empty, the other
			// process failed — stop waiting to avoid unnecessary delay.
			if ( ! self::is_locked( $lock_key ) ) {
				self::debug_log( 'Lock released without cache entry — other process failed', array(
					'cache_key' => $cache_key,
				) );
				return 'NA';
			}
		}

		self::debug_log( 'Timed out waiting for lock to release', array(
			'cache_key' => $cache_key,
		) );
		return 'NA';
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

		$bypass_cdn = isset( $options['bypass_cdn_cache'] ) ? (bool) $options['bypass_cdn_cache'] : false;

		$args = array(
			'user-agent'          => $user_agent,
			'timeout'             => 15,
			'redirection'         => 0,
			'reject_unsafe_urls'  => true,
			'sslverify'           => true,
			'limit_response_size' => 1024 * 1024, // 1 MB max
		);

		// When enabled, tell Cloudflare and other CDNs/reverse proxies in front of
		// the WHMCS server to bypass their cache and fetch fresh data from origin.
		// Without this, a CDN could serve stale prices even after updating in WHMCS.
		// Enabled by default. Can be turned off in Settings → Connection.
		if ( $bypass_cdn ) {
			$args['headers'] = array(
				'Cache-Control' => 'no-cache',
				'Pragma'        => 'no-cache',
			);
		}

		return $args;
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
	private static function unwrap_response_body($body) {
		if ( ! is_string( $body ) || '' === $body ) {
			return 'NA';
		}

		$body = trim( $body );

		// Handle WHMCS JS-wrapped responses: document.write('...content...'); 
		// Uses a non-greedy match and the /s flag to handle multi-line content.
		if ( preg_match( "/^document\.write\('(.*?)'\);$/s", $body, $matches ) ) {
			$body = $matches[1];
		}

		return trim( wp_kses_no_null( $body ) );
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

		// Check the in-memory request cache first — avoids a DB round-trip when the
		// same data is needed more than once within a single PHP request (e.g. both
		// a shortcode and a Gutenberg block on the same page request the same product).
		if ( isset( self::$request_cache[ $cache_key ] ) ) {
			self::debug_log( 'Product data served from request cache', array( 'cache_key' => $cache_key ) );
			return self::$request_cache[ $cache_key ];
		}

		$cached = self::get_cache( $cache_key );

		if ( false !== $cached ) {
			self::debug_log( 'Product data served from cache', array(
				'cache_key' => $cache_key,
				'length'    => strlen( $cached ),
			) );
			self::$request_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'Product data request waiting: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return self::wait_for_cache( $cache_key, $lock_key );
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
			self::release_lock( $lock_key ); // Release lock on failure.
			whmcs_price_notify_outage( $response->get_error_message() );
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			self::debug_log( 'Product data request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			self::release_lock( $lock_key ); // Release lock on HTTP error.
			/* translators: %d: HTTP response code */
			whmcs_price_notify_outage( sprintf( __( 'HTTP %d', 'whmcs-price' ), $response_code ) );
			return 'NA';
		}

		$data = self::unwrap_response_body( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'Product data fetched successfully', array(
			'cache_key' => $cache_key,
			'length'    => strlen( $data ),
		) );

		self::set_cache( $cache_key, $data, self::get_cache_expiry() );
		self::release_lock( $lock_key ); // Release lock after successful cache write.
		whmcs_price_clear_outage();

		/**
		 * Filter the product data returned from WHMCS before it is cached and displayed.
		 *
		 * @since 2.8.0
		 * @param string $data          The raw price/name/description string from WHMCS.
		 * @param int    $pid           The WHMCS product ID.
		 * @param string $billing_cycle The billing cycle (e.g. 'monthly', 'annually').
		 * @param string $attribute     The requested attribute ('name', 'description', 'price').
		 */
		$data = (string) apply_filters( 'whmcs_price_product_data', $data, $pid, $billing_cycle, $attribute );

		self::$request_cache[ $cache_key ] = $data;
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

		if ( isset( self::$request_cache[ $cache_key ] ) ) {
			self::debug_log( 'Domain price served from request cache', array( 'cache_key' => $cache_key ) );
			return self::$request_cache[ $cache_key ];
		}

		$cached = self::get_cache( $cache_key );

		if ( false !== $cached ) {
			self::debug_log( 'Domain price served from cache', array(
				'cache_key' => $cache_key,
				'length'    => strlen( $cached ),
			) );
			self::$request_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'Domain price request waiting: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return self::wait_for_cache( $cache_key, $lock_key );
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
			self::release_lock( $lock_key ); // Release lock on failure.
			whmcs_price_notify_outage( $response->get_error_message() );
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			self::debug_log( 'Domain price request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			self::release_lock( $lock_key ); // Release lock on HTTP error.
			/* translators: %d: HTTP response code */
			whmcs_price_notify_outage( sprintf( __( 'HTTP %d', 'whmcs-price' ), $response_code ) );
			return 'NA';
		}

		$data = self::unwrap_response_body( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'Domain price fetched successfully', array(
			'cache_key' => $cache_key,
			'length'    => strlen( $data ),
		) );

		self::set_cache( $cache_key, $data, self::get_cache_expiry() );
		self::release_lock( $lock_key ); // Release lock after successful cache write.
		whmcs_price_clear_outage();

		/**
		 * Filter the domain price returned from WHMCS before it is cached and displayed.
		 *
		 * @since 2.8.0
		 * @param string $data       The raw price string from WHMCS.
		 * @param string $tld        The domain extension (without leading dot).
		 * @param string $type       Transaction type: 'register', 'renew', or 'transfer'.
		 * @param int    $reg_period Registration period in years.
		 */
		$data = (string) apply_filters( 'whmcs_price_domain_price', $data, $tld, $type, $reg_period_int );

		self::$request_cache[ $cache_key ] = $data;
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

		if ( isset( self::$request_cache[ $cache_key ] ) ) {
			self::debug_log( 'All domain prices served from request cache', array( 'cache_key' => $cache_key ) );
			return self::$request_cache[ $cache_key ];
		}

		$cached = self::get_cache( $cache_key );

		if ( false !== $cached ) {
			self::debug_log( 'All domain prices served from cache', array(
				'cache_key'   => $cache_key,
				'data_length' => strlen( $cached ),
			) );
			self::$request_cache[ $cache_key ] = $cached;
			return $cached;
		}

		// Acquire a lock to prevent multiple simultaneous requests to WHMCS.
		$lock_key = 'lock_' . $cache_key;
		if ( ! self::acquire_lock( $lock_key ) ) {
			self::debug_log( 'All domain prices request waiting: lock already acquired', array(
				'lock_key' => $lock_key,
			) );
			return self::wait_for_cache( $cache_key, $lock_key );
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
			self::release_lock( $lock_key ); // Release lock on failure.
			whmcs_price_notify_outage( $response->get_error_message() );
			return 'NA';
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			self::debug_log( 'All domain prices request failed with HTTP error', array(
				'response_code' => $response_code,
				'url'           => $url,
			) );
			self::release_lock( $lock_key ); // Release lock on HTTP error.
			/* translators: %d: HTTP response code */
			whmcs_price_notify_outage( sprintf( __( 'HTTP %d', 'whmcs-price' ), $response_code ) );
			return 'NA';
		}

		$data = self::unwrap_response_body( wp_remote_retrieve_body( $response ) );

		self::debug_log( 'All domain prices fetched successfully', array(
			'cache_key'       => $cache_key,
			'data_length'     => strlen( $data ),
			'length'          => strlen( $data ),
		) );

		self::set_cache( $cache_key, $data, self::get_cache_expiry() );
		self::$request_cache[ $cache_key ] = $data;
		self::release_lock( $lock_key ); // Release lock after successful cache write.
		whmcs_price_clear_outage();

		return $data;
	}

	/**
	 * Divide a price string by a given divisor, preserving the currency symbol.
	 *
	 * Handles common WHMCS price formats:
	 *   "$9.99"  "€12.50"  "99.00 kr"  "9,99 kr"  "kr 99.00"
	 *
	 * Returns an empty string if the price string contains no parseable number,
	 * or if the value is "NA" / "N/A".
	 *
	 * @since  2.6.0
	 * @param  string $price_str  The raw price string returned by WHMCS.
	 * @param  float  $divisor    Number to divide by (e.g. 12 for monthly from annual).
	 * @return string             Divided price string with same currency formatting.
	 */
	public static function divide_price( string $price_str, float $divisor ): string {
		$trimmed = trim( $price_str );

		if ( $divisor <= 1 || empty( $trimmed ) || in_array( strtoupper( $trimmed ), array( 'NA', 'N/A', '-' ), true ) ) {
			return $price_str;
		}

		// Match the first number, including optional thousands/decimal separators.
		// Handles: 959, 9.99, 9,99, 1 499, 1.499, 1,499, 1,499.50, 1.499,50
		if ( ! preg_match( '/(\d[\d\s.,]*)/', $trimmed, $matches ) ) {
			return $price_str;
		}

		$raw        = trim( $matches[1] );
		$dec_sep    = '.'; // Default output decimal separator — overridden below.

		// Remove space-based thousands separators.
		$normalized = preg_replace( '/(\d)\s(\d{3})(?!\d)/', '$1$2', $raw );

		// Mixed: comma=thousands + period=decimal, e.g. "1,499.50" (EN format)
		if ( preg_match( '/^(\d{1,3}(?:,\d{3})+)\.(\d{1,2})$/', $normalized, $m ) ) {
			$float_str = str_replace( ',', '', $m[1] ) . '.' . $m[2];
			$dec_sep   = '.';

		// Mixed: period=thousands + comma=decimal, e.g. "1.499,50" (SE/EU format)
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{3})+),(\d{1,2})$/', $normalized, $m ) ) {
			$float_str = str_replace( '.', '', $m[1] ) . '.' . $m[2];
			$dec_sep   = ',';

		// Single separator + exactly 3 digits → thousands separator, integer result
		} elseif ( preg_match( '/^(\d+)([.,])(\d{3})$/', $normalized, $m ) ) {
			$float_str = $m[1] . $m[3];
			// Keep same decimal separator as input for consistency.
			$dec_sep   = $m[2];

		// Single separator + 1–2 digits → decimal separator
		} elseif ( preg_match( '/^(\d+)([.,])(\d{1,2})$/', $normalized, $m ) ) {
			$float_str = $m[1] . '.' . $m[3];
			$dec_sep   = $m[2];

		// Integer only
		} else {
			$float_str = preg_replace( '/[.,]/', '', $normalized );
			$dec_sep   = '.';
		}

		$divided = (float) $float_str / $divisor;

		// Format output using same decimal separator as the original price.
		$formatted = number_format( $divided, 2, $dec_sep, '' );

		// Reconstruct: replace the matched number with the divided value.
		return preg_replace(
			'/' . preg_quote( $raw, '/' ) . '/',
			$formatted,
			$trimmed,
			1
		);
	}

	/**
	 * Fetch the setup fee for a product and billing cycle from WHMCS.
	 *
	 * Fetches productpricing.php, unwraps the JS response, and extracts the
	 * setup fee for the requested billing cycle. The full pricing feed is
	 * cached per PID so all billing cycles share a single HTTP request.
	 *
	 * Returns the formatted setup fee string (e.g. "$10.00") or an empty
	 * string if the fee is zero, absent, or the feed is unavailable.
	 *
	 * @since  2.6.0
	 * @access public
	 * @param  int    $pid           The Product ID in WHMCS.
	 * @param  string $billing_cycle The billing cycle (e.g., annually).
	 * @return string                Formatted setup fee string, or '' if none / zero.
	 */
	public static function get_product_setup_fee( int $pid, string $billing_cycle ): string {
		$allowed_billing_cycles = array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' );
		if ( ! in_array( $billing_cycle, $allowed_billing_cycles, true ) ) {
			return '';
		}

		$whmcs_url = self::get_url();
		if ( empty( $whmcs_url ) ) {
			return '';
		}

		// Cache the full pricing feed per PID — one HTTP request serves all billing cycles.
		$cache_key = 'whmcs_pricefeed_' . md5( (string) $pid );

		// Unique key for the final setup-fee result (pid + billing cycle combination).
		$result_key = 'whmcs_setupfee_' . md5( $pid . '_' . $billing_cycle );
		if ( isset( self::$request_cache[ $result_key ] ) ) {
			self::debug_log( 'Setup fee served from request cache', array( 'result_key' => $result_key ) );
			return self::$request_cache[ $result_key ];
		}

		$cached = self::get_cache( $cache_key );

		if ( false === $cached ) {
			$lock_key = 'lock_' . $cache_key;
			if ( ! self::acquire_lock( $lock_key ) ) {
				self::debug_log( 'Product pricing feed request waiting: lock already acquired', array(
					'lock_key' => $lock_key,
				) );
				$waited = self::wait_for_cache( $cache_key, $lock_key );
				return ( 'NA' !== $waited && '' !== $waited )
					? self::extract_setup_fee_from_html( $waited, $billing_cycle )
					: '';
			}

			$url = add_query_arg(
				array( 'pid' => $pid ),
				$whmcs_url . '/feeds/productpricing.php'
			);

			self::debug_log( 'Fetching product pricing feed from WHMCS', array(
				'url' => $url,
				'pid' => $pid,
			) );

			$response = wp_remote_get( $url, self::get_request_args() );

			if ( is_wp_error( $response ) ) {
				self::debug_log( 'Product pricing feed request error', array(
					'error' => $response->get_error_message(),
				) );
				self::release_lock( $lock_key );
				whmcs_price_notify_outage( $response->get_error_message() );
				return '';
			}

			$response_code = wp_remote_retrieve_response_code( $response );
			if ( 200 !== $response_code ) {
				self::debug_log( 'Product pricing feed HTTP error', array(
					'response_code' => $response_code,
				) );
				self::release_lock( $lock_key );
				/* translators: %d: HTTP response code */
				whmcs_price_notify_outage( sprintf( __( 'HTTP %d', 'whmcs-price' ), $response_code ) );
				return '';
			}

			$cached = self::unwrap_response_body( wp_remote_retrieve_body( $response ) );

			self::debug_log( 'Product pricing feed fetched successfully', array(
				'cache_key' => $cache_key,
				'length'    => strlen( $cached ),
			) );

			self::set_cache( $cache_key, $cached, self::get_cache_expiry() );
			self::release_lock( $lock_key );
			whmcs_price_clear_outage();
		} else {
			self::debug_log( 'Product pricing feed served from cache', array(
				'cache_key' => $cache_key,
				'length'    => strlen( $cached ),
			) );
		}

		if ( 'NA' === $cached || empty( $cached ) ) {
			return '';
		}

		$result = self::extract_setup_fee_from_html( $cached, $billing_cycle );
		self::$request_cache[ $result_key ] = $result;
		return $result;
	}

	/**
	 * Extract the setup fee for a billing cycle from a productpricing.php response.
	 *
	 * WHMCS only outputs setup fee text when the fee is non-zero. The separator
	 * ' + ' is hardcoded in WHMCS PHP (not translated), making it a reliable anchor.
	 *
	 * @since  2.6.0
	 * @access private
	 * @param  string $html          Unwrapped HTML from productpricing.php.
	 * @param  string $billing_cycle WHMCS billing cycle name (e.g. 'annually').
	 * @return string                Setup fee string (e.g. '$10.00'), or '' if zero/absent.
	 */
	private static function extract_setup_fee_from_html( string $html, string $billing_cycle ): string {
		// For recurring products: parse the <option> for this billing cycle.
		if ( preg_match(
			'/<option[^>]+value=["\']' . preg_quote( $billing_cycle, '/' ) . '["\'][^>]*>(.*?)<\/option>/si',
			$html,
			$matches
		) ) {
			return self::parse_setup_fee_from_text( wp_strip_all_tags( $matches[1] ) );
		}

		// Fallback for onetime products: no <select>, just raw text content.
		return self::parse_setup_fee_from_text( wp_strip_all_tags( $html ) );
	}

	/**
	 * Parse a setup fee currency string from a plain-text price segment.
	 *
	 * Looks for WHMCS's hardcoded ' + ' separator and extracts the currency
	 * amount that follows. Handles common WHMCS currency formats: prefix
	 * symbol ("$10.00"), suffix symbol ("10.00 kr"), comma decimal ("10,00 kr").
	 *
	 * @since  2.6.0
	 * @access private
	 * @param  string $text  E.g. "12 months - $8.25/mo + $10.00 Setup Fee".
	 * @return string        Currency string (e.g. "$10.00"), or '' if not found.
	 */
	private static function parse_setup_fee_from_text( string $text ): string {
		if ( ! str_contains( $text, ' + ' ) ) {
			return '';
		}

		$parts    = explode( ' + ', $text, 2 );
		$fee_part = trim( $parts[1] );

		// Match a currency amount at the start of $fee_part.
		// Two patterns to handle:
		//   Prefix symbol:  "$10.00"  "€10.00"  "£10.00"  "kr 10.00"  "SEK 10.00"
		//   Suffix symbol:  "10.00 kr"  "10,00 kr"  "10.00 SEK"  "10.00"
		// Numbers: digits with optional thousands (space/dot/comma) and decimal separator.
		if ( preg_match(
			'/^((?:[\$€£¥₹]|kr|SEK|NOK|DKK|CHF|USD|EUR|GBP)\s*)?(\d[\d.,]*)\s*((?:[\$€£¥₹]|kr|SEK|NOK|DKK|CHF|USD|EUR|GBP))?/ui',
			$fee_part,
			$m
		) ) {
			$prefix = trim( $m[1] ?? '' );
			$number = trim( $m[2] ?? '' );
			$suffix = trim( $m[3] ?? '' );

			if ( empty( $number ) ) {
				return '';
			}

			if ( ! empty( $prefix ) ) {
				return $prefix . ' ' . $number;
			}
			if ( ! empty( $suffix ) ) {
				return $number . ' ' . $suffix;
			}
			return $number;
		}

		return '';
	}

	/**
	 * Return the number of months in a WHMCS billing cycle.
	 *
	 * @since  2.6.0
	 * @param  string $bc_internal  Internal billing cycle name (e.g. "annually").
	 * @return int                  Number of months (1–36).
	 */
	public static function billing_cycle_months( string $bc_internal ): int {
		$map = array(
			'monthly'      => 1,
			'quarterly'    => 3,
			'semiannually' => 6,
			'annually'     => 12,
			'biennially'   => 24,
			'triennially'  => 36,
		);
		return $map[ $bc_internal ] ?? 1;
	}
	/**
	 * Fetch all available billing cycles and prices for a product.
	 *
	 * Uses productpricing.php (already fetched for setup fees) to extract
	 * every billing cycle that WHMCS has configured for this product.
	 * Returns an associative array keyed by the WHMCS billing cycle name,
	 * with price strings as values. Cycles with price "0.00" or missing
	 * are excluded automatically.
	 *
	 * Example return value:
	 *   [
	 *     'monthly'     => '99 Kr',
	 *     'annually'    => '999 Kr',
	 *     'biennially'  => '1799 Kr',
	 *   ]
	 *
	 * @since  2.9.0
	 * @access public
	 * @param  int $pid Product ID.
	 * @return array<string,string> Keyed by cycle name, value is price string. Empty on failure.
	 */
	public static function get_all_product_cycles( int $pid ): array {
		if ( $pid <= 0 ) {
			return array();
		}

		$whmcs_url = self::get_url();
		if ( empty( $whmcs_url ) ) {
			return array();
		}

		// Reuse the productpricing.php cache — already populated by get_product_setup_fee().
		$cache_key  = 'whmcs_pricefeed_' . md5( (string) $pid );
		$result_key = 'whmcs_allcycles_' . md5( (string) $pid );

		if ( isset( self::$request_cache[ $result_key ] ) ) {
			return self::$request_cache[ $result_key ];
		}

		$cached = self::get_cache( $cache_key );

		if ( false === $cached ) {
			$lock_key = 'lock_' . $cache_key;
			if ( ! self::acquire_lock( $lock_key ) ) {
				$waited = self::wait_for_cache( $cache_key, $lock_key );
				$cached = ( false !== $waited ) ? $waited : 'NA';
			} else {
				$url      = add_query_arg( array( 'pid' => $pid ), $whmcs_url . '/feeds/productpricing.php' );
				$response = wp_remote_get( $url, self::get_request_args() );

				if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
					self::release_lock( $lock_key );
					return array();
				}

				$cached = self::unwrap_response_body( wp_remote_retrieve_body( $response ) );
				self::set_cache( $cache_key, $cached, self::get_cache_expiry() );
				self::release_lock( $lock_key );
			}
		}

		if ( 'NA' === $cached || empty( $cached ) ) {
			return array();
		}

		// Parse all <option> elements from the productpricing.php HTML.
		// Each option value is the billing cycle name and text contains the price.
		$cycles = array();
		$allowed = array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' );

		if ( preg_match_all(
			'/<option[^>]+value=["\']([a-z]+)["\'](.*?)>(.*?)<\\/option>/si',
			$cached,
			$matches,
			PREG_SET_ORDER
		) ) {
			foreach ( $matches as $m ) {
				$cycle = strtolower( trim( $m[1] ) );
				if ( ! in_array( $cycle, $allowed, true ) ) {
					continue;
				}

				// Strip setup fee suffix (e.g. "99 Kr + 10 Kr") — keep only the recurring price.
				$price_text = trim( strip_tags( $m[3] ) );
				if ( str_contains( $price_text, ' + ' ) ) {
					$price_text = trim( strstr( $price_text, ' + ', true ) );
				}

				// Skip cycles with a zero or empty price.
				$numeric = preg_replace( '/[^0-9.]/', '', $price_text );
				if ( empty( $numeric ) || (float) $numeric <= 0 ) {
					continue;
				}

				$cycles[ $cycle ] = $price_text;
			}
		}

		self::$request_cache[ $result_key ] = $cycles;
		return $cycles;
	}

}