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
		// Content Section
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
				'description' => __( 'Domain extension without dot (e.g. com, net, se). Leave empty to show all TLDs.', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'reg_period',
			array(
				'label'   => __( 'Registration Period', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1y',
				'options' => array(
					'1y'  => __( '1 Year', 'whmcs-price' ),
					'2y'  => __( '2 Years', 'whmcs-price' ),
					'3y'  => __( '3 Years', 'whmcs-price' ),
					'4y'  => __( '4 Years', 'whmcs-price' ),
					'5y'  => __( '5 Years', 'whmcs-price' ),
					'6y'  => __( '6 Years', 'whmcs-price' ),
					'7y'  => __( '7 Years', 'whmcs-price' ),
					'8y'  => __( '8 Years', 'whmcs-price' ),
					'9y'  => __( '9 Years', 'whmcs-price' ),
					'10y' => __( '10 Years', 'whmcs-price' ),
				),
			)
		);

		$this->add_control(
			'show_all_types',
			array(
				'label'        => __( 'Show All Transaction Types', 'whmcs-price' ),
				'type'         => \Elementor\Controls_Manager::SWITCHER,
				'label_on'     => __( 'Yes', 'whmcs-price' ),
				'label_off'    => __( 'No', 'whmcs-price' ),
				'return_value' => 'yes',
				'default'      => 'no',
				'description'  => __( 'Show Register, Renew, and Transfer prices together.', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'transaction_type',
			array(
				'label'     => __( 'Transaction Type', 'whmcs-price' ),
				'type'      => \Elementor\Controls_Manager::SELECT,
				'default'   => 'register',
				'options'   => array(
					'register' => __( 'Register', 'whmcs-price' ),
					'renew'    => __( 'Renew', 'whmcs-price' ),
					'transfer' => __( 'Transfer', 'whmcs-price' ),
				),
				'condition' => array(
					'show_all_types' => '',
				),
			)
		);

		$this->end_controls_section();

		// Style Section
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
				'label'   => __( 'Layout', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => 'table',
				'options' => array(
					'table'  => __( 'Table', 'whmcs-price' ),
					'badge'  => __( 'Badge', 'whmcs-price' ),
					'inline' => __( 'Inline', 'whmcs-price' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		$attributes = array(
			'tld'             => sanitize_text_field( $settings['tld'] ?? '' ),
			'transactionType' => sanitize_text_field( $settings['transaction_type'] ?? 'register' ),
			'regPeriod'       => sanitize_text_field( $settings['reg_period'] ?? '1y' ),
			'showAll'         => 'yes' === $settings['show_all_types'],
			'displayStyle'    => sanitize_text_field( $settings['display_style'] ?? 'table' ),
		);

		$this->render_domain_pricing( $attributes );
	}

	/**
	 * Render domain pricing (reuses block render logic).
	 *
	 * @param array $attributes Widget attributes.
	 * @return void
	 */
	private function render_domain_pricing( $attributes ) {
		$whmcs_tld              = $attributes['tld'];
		$whmcs_transaction_type = $attributes['transactionType'];
		$whmcs_reg_period_raw   = $attributes['regPeriod'];
		$whmcs_show_all         = $attributes['showAll'];
		$whmcs_display_style    = $attributes['displayStyle'];

		$whmcs_reg_period    = str_replace( 'y', '', $whmcs_reg_period_raw );
		$whmcs_wrapper_class = 'whmcs-domain-display whmcs-domain-display--' . esc_attr( $whmcs_display_style );

		// Allowlist display style
		$allowed_styles = array( 'table', 'badge', 'inline' );
		if ( ! in_array( $whmcs_display_style, $allowed_styles, true ) ) {
    		$whmcs_display_style = 'table';
		}

		// Allowlist transaction type
		$allowed_types = array( 'register', 'renew', 'transfer' );
		if ( ! in_array( $whmcs_transaction_type, $allowed_types, true ) ) {
    		$whmcs_transaction_type = 'register';
		}

		echo '<div class="' . esc_attr( $whmcs_wrapper_class ) . '">';

		if ( empty( $whmcs_tld ) ) {
			// Show all TLDs
			$whmcs_all_prices = WHMCS_Price_API::get_all_domain_prices();
			echo '<div class="whmcs-domain-all">' . wp_kses_post( $whmcs_all_prices ) . '</div>';

		} elseif ( $whmcs_show_all ) {
			// Show all three transaction types
			$whmcs_types = array(
				'register' => __( 'Register', 'whmcs-price' ),
				'renew'    => __( 'Renew', 'whmcs-price' ),
				'transfer' => __( 'Transfer', 'whmcs-price' ),
			);

			if ( 'table' === $whmcs_display_style ) {
				echo '<table class="whmcs-domain-table"><thead><tr>';
				foreach ( $whmcs_types as $label ) {
					echo '<th>' . esc_html( $label ) . '</th>';
				}
				echo '</tr></thead><tbody><tr>';
				foreach ( array_keys( $whmcs_types ) as $type_key ) {
					echo '<td>' . esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $type_key, $whmcs_reg_period ) ) . '</td>';
				}
				echo '</tr></tbody></table>';

			} elseif ( 'badge' === $whmcs_display_style ) {
				echo '<div class="whmcs-domain-badges">';
				echo '<div class="whmcs-domain-badges__tld">.' . esc_html( $whmcs_tld ) . '</div>';
				foreach ( $whmcs_types as $type_key => $type_label ) {
					echo '<div class="whmcs-domain-badge">';
					echo '<span class="whmcs-domain-badge__label">' . esc_html( $type_label ) . '</span>';
					echo '<span class="whmcs-domain-badge__price">' . esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $type_key, $whmcs_reg_period ) ) . '</span>';
					echo '</div>';
				}
				echo '</div>';

			} else { // inline
				echo '<div class="whmcs-domain-inline">';
				echo '<strong class="whmcs-domain-inline__tld">.' . esc_html( $whmcs_tld ) . '</strong>';
				foreach ( $whmcs_types as $type_key => $type_label ) {
					echo '<span class="whmcs-domain-inline__item">';
					echo esc_html( $type_label ) . ': <strong>' . esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $type_key, $whmcs_reg_period ) ) . '</strong>';
					echo '</span>';
				}
				echo '</div>';
			}
		} else {
			// Single TLD + single transaction type
			if ( 'badge' === $whmcs_display_style ) {
				$whmcs_type_labels = array(
					'register' => __( 'Register', 'whmcs-price' ),
					'renew'    => __( 'Renew', 'whmcs-price' ),
					'transfer' => __( 'Transfer', 'whmcs-price' ),
				);
				echo '<div class="whmcs-domain-badge whmcs-domain-badge--single">';
				echo '<span class="whmcs-domain-badge__tld">.' . esc_html( $whmcs_tld ) . '</span>';
				echo '<span class="whmcs-domain-badge__label">' . esc_html( $whmcs_type_labels[ $whmcs_transaction_type ] ?? ucfirst( $whmcs_transaction_type ) ) . '</span>';
				echo '<span class="whmcs-domain-badge__price">' . esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) ) . '</span>';
				echo '</div>';

			} elseif ( 'inline' === $whmcs_display_style ) {
				echo '<span class="whmcs-domain-inline whmcs-domain-inline--single">';
				echo '<strong>.' . esc_html( $whmcs_tld ) . '</strong> — ';
				echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) );
				echo '</span>';

			} else {
				echo '<div class="whmcs-price">';
				echo esc_html( WHMCS_Price_API::get_domain_price( $whmcs_tld, $whmcs_transaction_type, $whmcs_reg_period ) );
				echo '</div>';
			}
		}

		echo '</div>';
	}
}
