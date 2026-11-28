<?php
/**
 * Minimal Elementor stubs: enough to register a widget's controls and read
 * them back, without loading Elementor.
 *
 * @package WHMCS_Price\Tests
 */

// phpcs:ignoreFile

namespace Elementor;

class Controls_Manager {
	const TAB_CONTENT = 'content';
	const TEXT        = 'text';
	const NUMBER      = 'number';
	const SELECT      = 'select';
	const SELECT2     = 'select2';
	const SWITCHER    = 'switcher';
}

class Plugin {
	public static $instance;
}

abstract class Widget_Base {
	/** @var array<string,array> Registered controls by ID. */
	public array $controls = array();

	/** @var array Settings returned by get_settings_for_display(). */
	public array $settings = array();

	public function start_controls_section( $id, $args = array() ) {}
	public function end_controls_section() {}

	public function add_control( $id, $args = array() ) {
		$this->controls[ $id ] = $args;
	}

	public function get_settings_for_display() {
		return $this->settings;
	}

	/** Register controls and return them (register_controls() is protected). */
	public function stub_controls(): array {
		$this->controls = array();
		$this->register_controls();
		return $this->controls;
	}

	/** Settings a freshly added widget has: every control's default. */
	public function stub_defaults(): array {
		return array_map( static fn( $c ) => $c['default'] ?? '', $this->stub_controls() );
	}

	/** Run the protected render() and capture its output. */
	public function stub_render( array $settings ): string {
		$this->settings = $settings;
		ob_start();
		$this->render();
		return (string) ob_get_clean();
	}

	abstract protected function register_controls();
	abstract protected function render();
}
