<?php
/**
 * WHMCS Domain Price Elementor Widget
 *
 * @package    WHMCS_Price
 * @subpackage Elementor
 * @since      2.4.0
 */

defined( 'ABSPATH' ) || exit;

class WHMCS_Price_Elementor_Domain_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'whmcs-domain-price';
	}

	public function get_title() {
		return __( 'WHMCS Domain Price', 'whmcs-price' );
	}

	public function get_icon() {
		return 'eicon-site-identity';
	}

	public function get_categories() {
		return array( 'whmcs-price' );
	}

	public function get_keywords() {
		return array( 'whmcs', 'price', 'domain', 'tld' );
	}

	protected function register_controls() {
		$options = whmcs_price_builder_options();

		// Domain Settings (same order and labels as the block's sidebar).
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Domain Settings', 'whmcs-price' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'tld',
			array(
				'label'       => __( 'TLD', 'whmcs-price' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => 'com',
				'description' => __( 'Domain extension without dot, e.g. com, net, se. Leave empty to show all available TLDs.', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'reg_period',
			array(
				'label'   => __( 'Registration Period', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1y',
				'options' => $options['reg_periods'],
			)
		);

		$this->add_control(
			'show_all_types',
			array(
				'label'        => __( 'Show all transaction types', 'whmcs-price' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Show Register, Renew, and Transfer prices together.', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'transaction_type',
			array(
				'label'     => __( 'Transaction Type', 'whmcs-price' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'register',
				'options'   => $options['transaction_types'],
				'condition' => array( 'show_all_types!' => 'yes' ),
			)
		);

		$this->end_controls_section();

		// Display Style.
		$this->start_controls_section(
			'style_section',
			array(
				'label' => __( 'Display Style', 'whmcs-price' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'display_style',
			array(
				'label'   => __( 'Choose how to display pricing', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'table',
				'options' => $options['domain_styles'],
			)
		);

		$this->add_control(
			'per_period',
			array(
				'label'       => __( 'Per-Period Breakdown', 'whmcs-price' ),
				'type'        => \Elementor\Controls_Manager::SELECT,
				'default'     => '',
				'options'     => $options['per_period'],
				'description' => __( 'Show the price divided by period alongside the full price.', 'whmcs-price' ),
			)
		);

		$this->end_controls_section();

		// Currency.
		$this->start_controls_section(
			'currency_section',
			array(
				'label' => __( 'Currency', 'whmcs-price' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'currency',
			array(
				'label'       => __( 'WHMCS currency ID', 'whmcs-price' ),
				'type'        => \Elementor\Controls_Manager::NUMBER,
				'min'         => 0,
				'default'     => '',
				'description' => __( 'Optional. Leave empty to use the currency set for the page language, or the WHMCS default currency.', 'whmcs-price' ),
			)
		);

		$this->end_controls_section();
	}

	/**
	 * Map widget settings to the domain block's attributes (block.json).
	 *
	 * @since  2.10.1
	 * @param  array $settings Widget settings.
	 * @return array Block attributes.
	 */
	public function to_block_attributes( array $settings ): array {
		return array(
			'tld'             => sanitize_text_field( (string) ( $settings['tld'] ?? '' ) ),
			'transactionType' => sanitize_text_field( (string) ( $settings['transaction_type'] ?? 'register' ) ),
			'regPeriod'       => sanitize_text_field( (string) ( $settings['reg_period'] ?? '1y' ) ),
			'showAll'         => 'yes' === ( $settings['show_all_types'] ?? '' ),
			'displayStyle'    => sanitize_text_field( (string) ( $settings['display_style'] ?? 'table' ) ),
			'perPeriod'       => sanitize_text_field( (string) ( $settings['per_period'] ?? '' ) ),
			'currency'        => whmcs_price_builder_currency( $settings['currency'] ?? '' ),
		);
	}

	/**
	 * Render through the domain block, so the output is identical to Gutenberg.
	 */
	protected function render() {
		echo whmcs_price_render_block( 'domain', $this->to_block_attributes( $this->get_settings_for_display() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the block's render.php.
	}
}
