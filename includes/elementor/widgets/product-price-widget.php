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
		// Content Section
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
				'label'       => __( 'Product IDs', 'whmcs-price' ),
				'type'        => \Elementor\Controls_Manager::TEXT,
				'default'     => '',
				'placeholder' => '1,2,3',
				'description' => __( 'Comma-separated WHMCS Product IDs', 'whmcs-price' ),
			)
		);

		$this->add_control(
			'billing_cycle',
			array(
				'label'   => __( 'Billing Cycle', 'whmcs-price' ),
				'type'    => \Elementor\Controls_Manager::SELECT,
				'default' => '1y',
				'options' => array(
					'1m' => __( 'Monthly', 'whmcs-price' ),
					'3m' => __( 'Quarterly', 'whmcs-price' ),
					'6m' => __( 'Semi-Annually', 'whmcs-price' ),
					'1y' => __( 'Annually', 'whmcs-price' ),
					'2y' => __( 'Biennially', 'whmcs-price' ),
					'3y' => __( 'Triennially', 'whmcs-price' ),
				),
			)
		);

		$this->add_control(
			'show_columns',
			array(
				'label'    => __( 'Display Columns', 'whmcs-price' ),
				'type'     => \Elementor\Controls_Manager::SELECT2,
				'multiple' => true,
				'default'  => array( 'name', 'price' ),
				'options'  => array(
					'name'        => __( 'Name', 'whmcs-price' ),
					'description' => __( 'Description', 'whmcs-price' ),
					'price'       => __( 'Price', 'whmcs-price' ),
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
					'table' => __( 'Table (Classic)', 'whmcs-price' ),
					'cards' => __( 'Cards', 'whmcs-price' ),
					'grid'  => __( 'Pricing Grid', 'whmcs-price' ),
				),
			)
		);

		$this->end_controls_section();
	}

	protected function render() {
		$settings = $this->get_settings_for_display();

		if ( empty( $settings['product_ids'] ) ) {
			if ( \Elementor\Plugin::$instance->editor->is_edit_mode() ) {
				echo '<div style="padding: 20px; background: #f0f0f1; border: 1px dashed #ccc; text-align: center;">';
				echo esc_html__( 'Please enter Product IDs in the widget settings.', 'whmcs-price' );
				echo '</div>';
			}
			return;
		}

		// Build attributes array mimicking block attributes
		$attributes = array(
			'pid'          => sanitize_text_field( $settings['product_ids'] ),
			'billingCycle' => sanitize_text_field( $settings['billing_cycle'] ),
			'show'         => ! empty( $settings['show_columns'] ) ? $settings['show_columns'] : array( 'name', 'price' ),
			'displayStyle' => sanitize_text_field( $settings['display_style'] ),
		);

		// Render using the block's render.php logic
		$this->render_product_pricing( $attributes );
	}

	/**
	 * Render product pricing (reuses block render logic).
	 *
	 * @param array $attributes Widget attributes.
	 * @return void
	 */
	private function render_product_pricing( $attributes ) {
		$whmcs_pid           = $attributes['pid'];
		$whmcs_billing_cycle = $attributes['billingCycle'];
		$whmcs_show          = $attributes['show'];
		$whmcs_display_style = $attributes['displayStyle'];

		$whmcs_billing_cycles = array(
			'1m' => 'monthly',
			'3m' => 'quarterly',
			'6m' => 'semiannually',
			'1y' => 'annually',
			'2y' => 'biennially',
			'3y' => 'triennially',
		);

		$whmcs_bc_mapped = isset( $whmcs_billing_cycles[ $whmcs_billing_cycle ] ) ? $whmcs_billing_cycles[ $whmcs_billing_cycle ] : 'annually';
		$whmcs_pids = array_filter(
    		array_map( 'intval', explode( ',', $whmcs_pid ) ),
    		fn( $p ) => $p > 0
		);

		$whmcs_header_labels = array(
			'name'        => __( 'Name', 'whmcs-price' ),
			'description' => __( 'Description', 'whmcs-price' ),
			'price'       => __( 'Price', 'whmcs-price' ),
		);

		$whmcs_wrapper_class = 'whmcs-product-display whmcs-product-display--' . esc_attr( $whmcs_display_style );

		// Allowlist display style
		$allowed_styles = array( 'table', 'cards', 'grid' );
		if ( ! in_array( $whmcs_display_style, $allowed_styles, true ) ) {
    		$whmcs_display_style = 'table';
		}

		// Allowlist show columns
		$allowed_columns = array( 'name', 'description', 'price' );
		$whmcs_show = array_filter(
    		$whmcs_show,
    		fn( $col ) => in_array( $col, $allowed_columns, true )
		);

		echo '<div class="' . esc_attr( $whmcs_wrapper_class ) . '">';

		if ( 'table' === $whmcs_display_style ) {
			echo '<table class="whmcs-product-table"><thead><tr>';
			foreach ( $whmcs_show as $whmcs_column ) {
				$label = $whmcs_header_labels[ strtolower( trim( $whmcs_column ) ) ] ?? ucfirst( $whmcs_column );
				echo '<th>' . esc_html( $label ) . '</th>';
			}
			echo '</tr></thead><tbody>';

			foreach ( $whmcs_pids as $whmcs_single_pid ) {
				echo '<tr>';
				foreach ( $whmcs_show as $whmcs_attr ) {
					$val = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) );
					echo '<td>' . esc_html( $val ) . '</td>';
				}
				echo '</tr>';
			}
			echo '</tbody></table>';

		} elseif ( 'cards' === $whmcs_display_style ) {
			echo '<div class="whmcs-product-cards">';
			foreach ( $whmcs_pids as $whmcs_single_pid ) {
				echo '<div class="whmcs-product-card">';
				foreach ( $whmcs_show as $whmcs_attr ) {
					$whmcs_value      = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) );
					$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );

					if ( 'price' === $whmcs_attr_clean ) {
						echo '<span class="whmcs-product-card__price-value">' . esc_html( $whmcs_value ) . '</span>';
					} elseif ( 'name' === $whmcs_attr_clean ) {
						echo '<h3 class="whmcs-product-card__title">' . esc_html( $whmcs_value ) . '</h3>';
					} else {
						echo '<p class="whmcs-product-card__description">' . esc_html( $whmcs_value ) . '</p>';
					}
				}
				echo '</div>';
			}
			echo '</div>';

		} elseif ( 'grid' === $whmcs_display_style ) {
			echo '<div class="whmcs-product-grid">';
			foreach ( $whmcs_pids as $whmcs_single_pid ) {
				echo '<div class="whmcs-product-grid-item">';
				foreach ( $whmcs_show as $whmcs_attr ) {
					$whmcs_value      = WHMCS_Price_API::get_product_data( intval( $whmcs_single_pid ), $whmcs_bc_mapped, sanitize_text_field( $whmcs_attr ) );
					$whmcs_attr_clean = strtolower( trim( $whmcs_attr ) );
					$whmcs_label      = $whmcs_header_labels[ $whmcs_attr_clean ] ?? ucfirst( $whmcs_attr );

					echo '<div class="whmcs-product-grid-item__field">';
					echo '<span class="whmcs-product-grid-item__label">' . esc_html( $whmcs_label ) . '</span>';
					echo '<span class="whmcs-product-grid-item__value">' . esc_html( $whmcs_value ) . '</span>';
					echo '</div>';
				}
				echo '</div>';
			}
			echo '</div>';
		}

		echo '</div>';
	}
}
