<?php
/**
 * Minimal stand-ins for the WordPress core classes the plugin type-hints.
 *
 * Only the behaviour the plugin relies on is implemented.
 *
 * @package WHMCS_Price\Tests
 */

// phpcs:disable Generic.Files.OneObjectStructurePerFile.MultipleFound

class WP_Error {
	public array $errors     = array();
	public array $error_data = array();

	public function __construct( string $code = '', string $message = '', mixed $data = '' ) {
		if ( '' === $code ) {
			return;
		}
		$this->errors[ $code ][] = $message;
		if ( '' !== $data ) {
			$this->error_data[ $code ] = $data;
		}
	}

	public function get_error_code(): string {
		$codes = array_keys( $this->errors );
		return (string) ( $codes[0] ?? '' );
	}

	public function get_error_message(): string {
		$code = $this->get_error_code();
		return (string) ( $this->errors[ $code ][0] ?? '' );
	}

	public function get_error_data(): mixed {
		return $this->error_data[ $this->get_error_code() ] ?? null;
	}
}

class WP_REST_Server {
	const READABLE  = 'GET';
	const CREATABLE = 'POST';
}

class WP_REST_Request {
	private array $params  = array();
	private array $headers = array();

	public function __construct( array $params = array(), array $headers = array() ) {
		$this->params = $params;
		foreach ( $headers as $name => $value ) {
			$this->headers[ strtolower( $name ) ] = $value;
		}
	}

	public function get_param( string $key ): mixed {
		return $this->params[ $key ] ?? null;
	}

	public function get_header( string $key ): ?string {
		return $this->headers[ strtolower( $key ) ] ?? null;
	}
}

class WP_REST_Response {
	public mixed $data;
	public int $status;
	public array $headers = array();

	public function __construct( mixed $data = null, int $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function header( string $key, string $value ): void {
		$this->headers[ $key ] = $value;
	}

	public function get_status(): int {
		return $this->status;
	}

	public function get_data(): mixed {
		return $this->data;
	}
}
