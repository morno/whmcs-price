<?php
/**
 * Base test case: Brain Monkey + an in-memory WordPress.
 *
 * Options, transients and the object cache are plain arrays, and outgoing
 * HTTP requests are answered from a queue so tests never touch the network.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

abstract class TestCase extends PHPUnitTestCase {

	/** A public IP so WHMCS_Price_API::get_url() skips DNS resolution. */
	protected const WHMCS_URL = 'https://93.184.216.34/billing';

	protected array $options    = array();
	protected array $transients = array();
	protected array $object     = array();

	/** @var array<int, array|\WP_Error> Responses returned by wp_remote_get(), in order. */
	protected array $http_queue = array();

	/** @var string[] URLs requested through wp_remote_get(). */
	protected array $http_log = array();

	/** @var array<int, array> Arguments passed to wp_mail(). */
	protected array $mail_log = array();

	protected bool $ext_object_cache = false;

	/** @var array<string, int> Storage TTL passed to set_transient(), by key. */
	protected array $transient_ttl = array();

	/** @var array<int, array{0:int,1:string,2:array}> wp_schedule_single_event() calls. */
	protected array $scheduled = array();

	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		$this->options    = array(
			'whmcs_price_option' => array( 'whmcs_url' => self::WHMCS_URL ),
		);
		$this->transients = array();
		$this->object     = array();
		$this->http_queue = array();
		$this->http_log   = array();
		$this->mail_log   = array();
		$this->ext_object_cache = false;
		$this->transient_ttl    = array();
		$this->scheduled        = array();

		$this->reset_api_state();
		$this->stub_wordpress();

		// Loaded here (not in the bootstrap) because it calls add_action() at load time.
		require_once dirname( __DIR__ ) . '/includes/multilingual.php';
	}

	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Reset the static request/version caches inside WHMCS_Price_API.
	 */
	protected function reset_api_state(): void {
		$ref = new \ReflectionClass( \WHMCS_Price_API::class );
		$props = array(
			'request_cache' => array(),
			'cache_version' => null,
			'force_refresh' => false,
			'stale_served'  => array(),
			'registry_touched' => array(),
			'refresh_wrote'    => false,
		);
		foreach ( $props as $prop => $value ) {
			$p = $ref->getProperty( $prop );
			$p->setAccessible( true );
			$p->setValue( null, $value );
		}
	}

	/**
	 * Call a private static method on WHMCS_Price_API.
	 */
	protected function call_private( string $method, ...$args ): mixed {
		$m = new \ReflectionMethod( \WHMCS_Price_API::class, $method );
		$m->setAccessible( true );
		return $m->invoke( null, ...$args );
	}

	/**
	 * Queue an HTTP 200 response with the given body.
	 */
	protected function queue_response( string $body, int $code = 200 ): void {
		$this->http_queue[] = array(
			'body'     => $body,
			'response' => array( 'code' => $code ),
		);
	}

	protected function queue_error( string $message = 'cURL error 28: timed out' ): void {
		$this->http_queue[] = new \WP_Error( 'http_request_failed', $message );
	}

	/**
	 * Versioned storage key for an unversioned cache key (cache version 1).
	 */
	protected function storage_key( string $cache_key ): string {
		return 'v1_' . $cache_key;
	}

	/**
	 * The value inside a stored cache envelope, or null when absent.
	 */
	protected function cached_value( string $cache_key ): mixed {
		$raw = $this->transients[ $this->storage_key( $cache_key ) ] ?? null;
		return is_array( $raw ) && array_key_exists( 'v', $raw ) ? $raw['v'] : $raw;
	}

	/**
	 * Move a stored entry back in time, in both the transient and object cache.
	 */
	protected function age_entry( string $cache_key, int $seconds ): void {
		$key = $this->storage_key( $cache_key );
		foreach ( array( 'transients', 'object' ) as $store ) {
			if ( 'transients' === $store && isset( $this->transients[ $key ]['t'] ) ) {
				$this->transients[ $key ]['t'] -= $seconds;
				$this->transients[ $key ]['e'] -= $seconds;
			}
			if ( 'object' === $store && isset( $this->object['whmcs_price'][ $key ]['t'] ) ) {
				$this->object['whmcs_price'][ $key ]['t'] -= $seconds;
				$this->object['whmcs_price'][ $key ]['e'] -= $seconds;
			}
		}
	}

	protected function set_plugin_option( string $key, mixed $value ): void {
		$this->options['whmcs_price_option'][ $key ] = $value;
	}

	private function stub_wordpress(): void {
		$t = $this;

		Functions\stubs(
			array(
				'__'                => null, // Return first argument.
				'esc_html__'        => null,
				'esc_url_raw'       => null,
				'wp_unslash'        => null,
				'sanitize_text_field' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
				'wp_kses_no_null'   => null,
				'wp_kses_data'      => null,
				'sanitize_key'      => static fn( $k ) => preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ),
				'absint'            => static fn( $v ) => abs( (int) $v ),
				'wp_parse_url'      => static fn( $url, $component = -1 ) => parse_url( $url, $component ),
				'wp_json_encode'    => static fn( $data ) => json_encode( $data ),
				'wp_strip_all_tags' => static fn( $s ) => trim( strip_tags( (string) $s ) ),
				'get_bloginfo'      => 'Test Site',
				'get_current_blog_id' => 1,
				'is_multisite'      => false,
				'admin_url'         => static fn( $path = '' ) => 'https://example.test/wp-admin/' . $path,
				'is_wp_error'       => static fn( $thing ) => $thing instanceof \WP_Error,
				'add_query_arg'     => static function ( $args, $url ) {
					return $url . ( str_contains( $url, '?' ) ? '&' : '?' ) . http_build_query( $args );
				},
			)
		);

		// Options.
		Functions\when( 'get_option' )->alias(
			static fn( $name, $default = false ) => array_key_exists( $name, $t->options ) ? $t->options[ $name ] : $default
		);
		Functions\when( 'update_option' )->alias(
			static function ( $name, $value ) use ( $t ) {
				$t->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'add_option' )->alias(
			static function ( $name, $value = '' ) use ( $t ) {
				if ( array_key_exists( $name, $t->options ) ) {
					return false;
				}
				$t->options[ $name ] = $value;
				return true;
			}
		);
		Functions\when( 'delete_option' )->alias(
			static function ( $name ) use ( $t ) {
				$existed = array_key_exists( $name, $t->options );
				unset( $t->options[ $name ] );
				return $existed;
			}
		);

		// Transients.
		Functions\when( 'get_transient' )->alias(
			static fn( $key ) => $t->transients[ $key ] ?? false
		);
		Functions\when( 'set_transient' )->alias(
			static function ( $key, $value, $expiration = 0 ) use ( $t ) {
				$t->transients[ $key ]    = $value;
				$t->transient_ttl[ $key ] = (int) $expiration;
				return true;
			}
		);
		Functions\when( 'delete_transient' )->alias(
			static function ( $key ) use ( $t ) {
				unset( $t->transients[ $key ] );
				return true;
			}
		);

		// Object cache. Non-persistent by default, like a site without Redis.
		Functions\when( 'wp_using_ext_object_cache' )->alias( static fn() => $t->ext_object_cache );
		Functions\when( 'wp_cache_get' )->alias(
			static fn( $key, $group = '' ) => $t->object[ $group ][ $key ] ?? false
		);
		Functions\when( 'wp_cache_set' )->alias(
			static function ( $key, $value, $group = '' ) use ( $t ) {
				$t->object[ $group ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_add' )->alias(
			static function ( $key, $value, $group = '' ) use ( $t ) {
				if ( isset( $t->object[ $group ][ $key ] ) ) {
					return false;
				}
				$t->object[ $group ][ $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->alias(
			static function ( $key, $group = '' ) use ( $t ) {
				unset( $t->object[ $group ][ $key ] );
				return true;
			}
		);
		Functions\when( 'wp_cache_flush_group' )->alias(
			static function ( $group ) use ( $t ) {
				unset( $t->object[ $group ] );
				return true;
			}
		);

		// HTTP.
		Functions\when( 'wp_remote_get' )->alias(
			static function ( $url ) use ( $t ) {
				$t->http_log[] = $url;
				if ( empty( $t->http_queue ) ) {
					throw new \RuntimeException( 'Unexpected HTTP request: ' . $url );
				}
				return array_shift( $t->http_queue );
			}
		);
		Functions\when( 'wp_remote_retrieve_body' )->alias(
			static fn( $response ) => is_array( $response ) ? (string) ( $response['body'] ?? '' ) : ''
		);
		Functions\when( 'wp_remote_retrieve_response_code' )->alias(
			static fn( $response ) => is_array( $response ) ? (int) ( $response['response']['code'] ?? 0 ) : ''
		);

		// WP-Cron.
		Functions\when( 'wp_next_scheduled' )->alias(
			static function ( $hook, $args = array() ) use ( $t ) {
				foreach ( $t->scheduled as $event ) {
					if ( $event[1] === $hook && $event[2] === $args ) {
						return $event[0];
					}
				}
				return false;
			}
		);
		Functions\when( 'wp_schedule_single_event' )->alias(
			static function ( $timestamp, $hook, $args = array() ) use ( $t ) {
				$t->scheduled[] = array( (int) $timestamp, $hook, $args );
				return true;
			}
		);

		// Mail (outage notifications).
		Functions\when( 'wp_mail' )->alias(
			static function ( ...$args ) use ( $t ) {
				$t->mail_log[] = $args;
				return true;
			}
		);
	}
}
