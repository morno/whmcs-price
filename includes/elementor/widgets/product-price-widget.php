<?php
/**
 * WHMCS Product Price Elementor Widget
 *
 * @package    WHMCS_Price
 * @subpackage Elementor
 * @since      2.4.0
 */

defined( 'ABSPATH' ) || exit;

class WHMCS_Price_Elementor_Product_Widget extends \Elementor\Widget_Base {

	public function get_name() {
		return 'whmcs-product-price';
	}

	public function get_title() {
		return __( 'WHMCS Product Price', 'whmcs-price' );
	}

	public function get_icon() {
		return 'eicon-price-table';
	}

	public function get_categories() {
		return array( 'whmcs-price' );
	}

	public function get_keywords() {
		return array( 'whmcs', 'price', 'product', 'hosting' );
	}

	protected function register_controls() {
		$options = whmcs_price_builder_options();

		// Product Settings (same order and labels as the block's sidebar).
		$this->start_controls_section(
			'content_section',
			array(
				'label' => __( 'Product Settings', 'whmcs-price' ),
				'tab'   => \Elementor\Controls_Manager::TAB_CONTENT,
			)
		);

		$this->add_control(
			'product_ids',
			array(
				'label'       => __( 'Product ID(s)', 'whmcs-price' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '1,2,3',
				'description' => __( 'Comma-separated WHMCS Product IDs, e.g. 1,2,3', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'billing_cycle',
			array(
				'label'   => __( 'Billing Cycle', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1y',
				'options' => $options['billing_cycles'],
			)
		);

		$this->add_control(
			'show_columns',
			array(
				'label'    => __( 'Display Columns', 'whmcs-price' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => array( 'name', 'price' ),
				'options'  => $options['product_fields'],
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
				'label'   => __( 'Choose how to display products', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'table',
				'options' => $options['product_styles'],
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

		$this->add_control(
			'cycle_toggle',
			array(
				'label'        => __( 'Billing period switcher', 'whmcs-price' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'return_value' => 'yes',
				'default'      => '',
				'description'  => __( 'Let visitors switch billing cycle without reloading the page. The billing cycle in Product Settings is shown first.', 'whmcs-price' ),
				'condition'    => array( 'display_style!' => 'table' ),
			)
		);

		$this->add_control(
			'toggle_cycles',
			array(
				'label'     => __( 'Cycles to offer (choose at least two)', 'whmcs-price' ),
				'type'      => \Elementor\Controls_Manager::SELECT2,
				'multiple'  => true,
				'default'   => array( '1m', '1y' ),
				'options'   => $options['billing_cycles'],
				'condition' => array(
					'display_style!' => 'table',
					'cycle_toggle'   => 'yes',
				),
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
	 * Map widget settings to the product block's attributes (block.json).
	 *
	 * @since  2.10.1
	 * @param  array $settings Widget settings.
	 * @return array Block attributes.
	 */
	public function to_block_attributes( array $settings ): array {
		return array(
			'pid'          => sanitize_text_field( (string) ( $settings['product_ids'] ?? '' ) ),
			'billingCycle' => sanitize_text_field( (string) ( $settings['billing_cycle'] ?? '1y' ) ),
			'show'         => ! empty( $settings['show_columns'] ) && is_array( $settings['show_columns'] )
				? array_values( array_map( 'sanitize_text_field', $settings['show_columns'] ) )
				: array( 'name', 'price' ),
			'displayStyle' => sanitize_text_field( (string) ( $settings['display_style'] ?? 'table' ) ),
			'perPeriod'    => sanitize_text_field( (string) ( $settings['per_period'] ?? '' ) ),
			'cycleToggle'  => 'yes' === ( $settings['cycle_toggle'] ?? '' ),
			'toggleCycles' => isset( $settings['toggle_cycles'] ) && is_array( $settings['toggle_cycles'] )
				? array_values( array_map( 'sanitize_text_field', $settings['toggle_cycles'] ) )
				: array( '1m', '1y' ),
			'currency'     => whmcs_price_builder_currency( $settings['currency'] ?? '' ),
		);
	}

	/**
	 * Render through the product block, so the output is identical to Gutenberg.
	 */
	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['product_ids'] ) ) {
			if ( isset( \Elementor\Plugin::$instance->editor ) && \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 20px; background: #f0f0f1; border: 1px dashed #ccc; text-align: center;">';
				echo esc_html__( 'Please enter Product IDs in the widget settings.', 'whmcs-price' );
				echo '</div>';
			}
			return;
		}

		echo whmcs_price_render_block( 'product', $this->to_block_attributes( $settings ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped by the block's render.php.
	}
}
