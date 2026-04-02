<?php
/**
 * WP-CLI Commands
 *
 * Provides CLI commands for managing the WHMCS Price plugin from the command line.
 * Useful for automated deployments, server environments without admin UI access,
 * and scripts that need to manage cache programmatically.
 *
 * Usage:
 *   wp whmcs-price cache status
 *   wp whmcs-price cache clear
 *   wp whmcs-price price product <pid> [--bc=<billing_cycle>] [--attr=<attribute>]
 *   wp whmcs-price price domain <tld> [--type=<type>] [--reg=<period>]
 *
 * @package    WHMCS_Price
 * @subpackage CLI
 * @since      2.8.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Manage the Mornolink for WHMCS plugin from the command line.
 *
 * ## EXAMPLES
 *
 *     # Show cache status
 *     $ wp whmcs-price cache status
 *
 *     # Clear all cached WHMCS prices
 *     $ wp whmcs-price cache clear
 *
 *     # Fetch an annual product price
 *     $ wp whmcs-price price product 1 --bc=annually --attr=price
 *
 *     # Fetch a domain registration price
 *     $ wp whmcs-price price domain se --type=register --reg=1
 *
 * @since 2.8.0
 */
class WHMCS_Price_CLI extends WP_CLI_Command {

	/**
	 * Manage the WHMCS price cache.
	 *
	 * ## SUBCOMMANDS
	 *
	 *   status    Show cache statistics.
	 *   clear     Delete all cached WHMCS price data.
	 *
	 * @subcommand cache
	 */
	public function cache( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? '';

		switch ( $subcommand ) {
			case 'status':
				$this->cache_status();
				break;
			case 'clear':
				$this->cache_clear();
				break;
			default:
				WP_CLI::error( 'Unknown subcommand. Use: status, clear' );
		}
	}

	/**
	 * Show cache statistics.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp whmcs-price cache status
	 *     +----------------+-------+
	 *     | Field          | Value |
	 *     +----------------+-------+
	 *     | Cached entries | 12    |
	 *     | Active locks   | 0     |
	 *     | Earliest expiry| 47 min|
	 *     | Outage active  | No    |
	 *     +----------------+-------+
	 *
	 * @since 2.8.0
	 */
	private function cache_status(): void {
		global $wpdb;

		$cache_like   = $wpdb->esc_like( '_transient_whmcs_' ) . '%';
		$lock_like    = $wpdb->esc_like( '_transient_lock_whmcs_' ) . '%';
		$timeout_like = $wpdb->esc_like( '_transient_timeout_whmcs_' ) . '%';

		$cache_count = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $cache_like )
		);
		$lock_count  = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->options} WHERE option_name LIKE %s", $lock_like )
		);
		$min_timeout = $wpdb->get_var(
			$wpdb->prepare( "SELECT MIN(option_value) FROM {$wpdb->options} WHERE option_name LIKE %s", $timeout_like )
		);

		$is_outage = false !== get_transient( 'whmcs_price_outage_notified' );

		$expiry = 'No cache';
		if ( $min_timeout ) {
			$diff = (int) $min_timeout - time();
			if ( $diff > 0 ) {
				$hours   = floor( $diff / 3600 );
				$minutes = floor( ( $diff % 3600 ) / 60 );
				$expiry  = $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes} min";
			} else {
				$expiry = 'Expired';
			}
		}

		$options   = get_option( 'whmcs_price_option', array() );
		$whmcs_url = ! empty( $options['whmcs_url'] ) ? $options['whmcs_url'] : '(not configured)';

		WP_CLI\Utils\format_items(
			'table',
			array(
				array( 'Field' => 'WHMCS URL',        'Value' => $whmcs_url ),
				array( 'Field' => 'Cached entries',   'Value' => $cache_count ),
				array( 'Field' => 'Active locks',     'Value' => $lock_count ),
				array( 'Field' => 'Earliest expiry',  'Value' => $expiry ),
				array( 'Field' => 'Outage active',    'Value' => $is_outage ? 'Yes' : 'No' ),
			),
			array( 'Field', 'Value' )
		);
	}

	/**
	 * Clear all cached WHMCS price data.
	 *
	 * Also flushes supported page cache plugins, identical to the Admin Bar button.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp whmcs-price cache clear
	 *     Success: WHMCS price cache cleared. 12 entries removed.
	 *
	 * @since 2.8.0
	 */
	private function cache_clear(): void {
		global $wpdb;

		$cache_like = $wpdb->esc_like( '_transient_whmcs_' ) . '%';
		$lock_like  = $wpdb->esc_like( '_transient_lock_whmcs_' ) . '%';

		$transient_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options} WHERE option_name LIKE %s",
				$cache_like
			)
		);

		$count = 0;
		foreach ( $transient_keys as $key ) {
			delete_transient( $key );
			$count++;
		}

		// Also clear lock transients.
		$lock_keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT REPLACE(option_name, '_transient_', '') FROM {$wpdb->options} WHERE option_name LIKE %s",
				$lock_like
			)
		);
		foreach ( $lock_keys as $key ) {
			delete_transient( $key );
		}

		// Flush page cache plugins.
		whmcs_price_flush_page_cache();

		WP_CLI::success( "WHMCS price cache cleared. {$count} entries removed." );
	}

	/**
	 * Fetch live pricing data from WHMCS.
	 *
	 * ## SUBCOMMANDS
	 *
	 *   product   Fetch a product attribute (name, description, price).
	 *   domain    Fetch a domain price.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp whmcs-price price product 1 --bc=annually --attr=price
	 *     999 Kr
	 *
	 *     $ wp whmcs-price price domain se --type=register --reg=1
	 *     239 Kr
	 *
	 * @subcommand price
	 */
	public function price( array $args, array $assoc_args ): void {
		$subcommand = $args[0] ?? '';

		switch ( $subcommand ) {
			case 'product':
				$this->price_product( $args, $assoc_args );
				break;
			case 'domain':
				$this->price_domain( $args, $assoc_args );
				break;
			default:
				WP_CLI::error( 'Unknown subcommand. Use: product, domain' );
		}
	}

	/**
	 * Fetch a product attribute from WHMCS.
	 *
	 * ## OPTIONS
	 *
	 * <pid>
	 * : The WHMCS Product ID.
	 *
	 * [--bc=<billing_cycle>]
	 * : Billing cycle. Options: monthly, quarterly, semiannually, annually, biennially, triennially.
	 * ---
	 * default: annually
	 * ---
	 *
	 * [--attr=<attribute>]
	 * : Attribute to fetch. Options: name, description, price.
	 * ---
	 * default: price
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp whmcs-price price product 1 --bc=annually --attr=price
	 *     999 Kr
	 *
	 *     $ wp whmcs-price price product 1 --attr=name
	 *     Hosting Ekonomi
	 *
	 * @since 2.8.0
	 */
	private function price_product( array $args, array $assoc_args ): void {
		$pid           = isset( $args[1] ) ? absint( $args[1] ) : 0;
		$billing_cycle = $assoc_args['bc'] ?? 'annually';
		$attribute     = $assoc_args['attr'] ?? 'price';

		$allowed_cycles = array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' );
		$allowed_attrs  = array( 'name', 'description', 'price' );

		if ( $pid <= 0 ) {
			WP_CLI::error( 'Product ID must be a positive integer.' );
		}
		if ( ! in_array( $billing_cycle, $allowed_cycles, true ) ) {
			WP_CLI::error( 'Invalid billing cycle. Options: ' . implode( ', ', $allowed_cycles ) );
		}
		if ( ! in_array( $attribute, $allowed_attrs, true ) ) {
			WP_CLI::error( 'Invalid attribute. Options: ' . implode( ', ', $allowed_attrs ) );
		}

		$result = WHMCS_Price_API::get_product_data( $pid, $billing_cycle, $attribute );

		if ( 'NA' === $result ) {
			WP_CLI::error( 'WHMCS returned no data. Check your WHMCS URL and that the product/billing cycle exists.' );
		}

		WP_CLI::line( $result );
	}

	/**
	 * Fetch a domain price from WHMCS.
	 *
	 * ## OPTIONS
	 *
	 * <tld>
	 * : Domain extension without the leading dot, e.g. se, com.
	 *
	 * [--type=<type>]
	 * : Transaction type. Options: register, renew, transfer.
	 * ---
	 * default: register
	 * ---
	 *
	 * [--reg=<period>]
	 * : Registration period in years (1-10).
	 * ---
	 * default: 1
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp whmcs-price price domain se --type=register --reg=1
	 *     239 Kr
	 *
	 *     $ wp whmcs-price price domain com --type=renew
	 *     290 Kr
	 *
	 * @since 2.8.0
	 */
	private function price_domain( array $args, array $assoc_args ): void {
		$tld    = isset( $args[1] ) ? sanitize_key( ltrim( $args[1], '.' ) ) : '';
		$type   = $assoc_args['type'] ?? 'register';
		$period = isset( $assoc_args['reg'] ) ? absint( $assoc_args['reg'] ) : 1;

		$allowed_types = array( 'register', 'renew', 'transfer' );

		if ( empty( $tld ) ) {
			WP_CLI::error( 'TLD is required. Example: se, com, net' );
		}
		if ( ! in_array( $type, $allowed_types, true ) ) {
			WP_CLI::error( 'Invalid type. Options: ' . implode( ', ', $allowed_types ) );
		}
		if ( $period < 1 || $period > 10 ) {
			WP_CLI::error( 'Registration period must be between 1 and 10.' );
		}

		$result = WHMCS_Price_API::get_domain_price( $tld, $type, $period );

		if ( 'NA' === $result ) {
			WP_CLI::error( 'WHMCS returned no data. Check your WHMCS URL and that the TLD exists.' );
		}

		WP_CLI::line( $result );
	}
}

WP_CLI::add_command( 'whmcs-price', 'WHMCS_Price_CLI' );
