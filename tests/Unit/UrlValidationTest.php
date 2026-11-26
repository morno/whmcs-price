<?php
/**
 * SSRF validation in WHMCS_Price_API::get_url().
 *
 * Only IP-literal hosts are used so the tests never depend on DNS.
 *
 * @package WHMCS_Price\Tests
 */

namespace WHMCS_Price\Tests\Unit;

use WHMCS_Price\Tests\TestCase;
use WHMCS_Price_API;

final class UrlValidationTest extends TestCase {

	public function test_accepts_public_https_url(): void {
		$this->assertSame( self::WHMCS_URL, WHMCS_Price_API::get_url() );
	}

	public function test_accepts_explicit_port_443(): void {
		$this->set_plugin_option( 'whmcs_url', 'https://93.184.216.34:443/billing' );

		$this->assertSame( 'https://93.184.216.34:443/billing', WHMCS_Price_API::get_url() );
	}

	/**
	 * @dataProvider rejected_urls
	 */
	public function test_rejects_unsafe_urls( string $url ): void {
		$this->set_plugin_option( 'whmcs_url', $url );

		$this->assertSame( '', WHMCS_Price_API::get_url() );
	}

	public static function rejected_urls(): array {
		return array(
			'plain http'           => array( 'http://93.184.216.34/billing' ),
			'embedded credentials' => array( 'https://user:pass@93.184.216.34/billing' ),
			'non-standard port'    => array( 'https://93.184.216.34:8443/billing' ),
			'localhost'            => array( 'https://localhost/billing' ),
			'loopback ipv4'        => array( 'https://127.0.0.1/billing' ),
			'private 10/8'         => array( 'https://10.0.0.5/billing' ),
			'private 192.168/16'   => array( 'https://192.168.1.10/billing' ),
			'private 172.16/12'    => array( 'https://172.16.0.1/billing' ),
			'link-local metadata'  => array( 'https://169.254.169.254/latest/meta-data' ),
			'alibaba metadata'     => array( 'https://100.100.100.200/' ),
			'gcp metadata host'    => array( 'https://metadata.google.internal/' ),
		);
	}

	public function test_empty_setting_returns_empty_string(): void {
		$this->options['whmcs_price_option'] = array( 'whmcs_url' => '' );

		$this->assertSame( '', WHMCS_Price_API::get_url() );
	}

	public function test_missing_option_returns_empty_string(): void {
		unset( $this->options['whmcs_price_option'] );

		$this->assertSame( '', WHMCS_Price_API::get_url() );
	}
}
