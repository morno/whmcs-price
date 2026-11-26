<?php
/**
 * WordPress Abilities API integration
 *
 * Registers read-only abilities so AI agents, MCP clients and other plugins
 * can query WHMCS prices through WordPress in a standard, discoverable way:
 *
 *   whmcs-price/get-product-price   Name, description, price and setup fee
 *                                   for one or more WHMCS products.
 *   whmcs-price/get-domain-price    Register / renew / transfer prices for a TLD.
 *
 * Both abilities reuse WHMCS_Price_API, so they share the cache with the
 * shortcodes, blocks and REST endpoints and never add new HTTP paths.
 *
 * The Abilities API ships with WordPress 6.9. On older versions the
 * registration hooks never fire, so this file is a no-op there.
 *
 * @package    WHMCS_Price
 * @subpackage Abilities
 * @since      2.10.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Allowed billing cycles, in WHMCS's internal naming.
 *
 * @since  2.10.0
 * @return string[]
 */
function whmcs_price_ability_billing_cycles(): array {
	return array( 'monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially' );
}

/**
 * Register the plugin's ability category.
 *
 * @since 2.10.0
 * @return void
 */
function whmcs_price_register_ability_category(): void {
	if ( ! function_exists( 'wp_register_ability_category' ) ) {
		return;
	}

	wp_register_ability_category(
		'whmcs-price',
		array(
			'label'       => __( 'WHMCS Pricing', 'whmcs-price' ),
			'description' => __( 'Live product and domain prices from the connected WHMCS installation.', 'whmcs-price' ),
		)
	);
}
add_action( 'wp_abilities_api_categories_init', 'whmcs_price_register_ability_category' );

/**
 * Register the plugin's abilities.
 *
 * @since 2.10.0
 * @return void
 */
function whmcs_price_register_abilities(): void {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}

	$meta = array(
		'show_in_rest' => true,
		'annotations'  => array(
			'readonly'   => true,
			'idempotent' => true,
		),
	);

	wp_register_ability(
		'whmcs-price/get-product-price',
		array(
			'label'               => __( 'Get WHMCS product price', 'whmcs-price' ),
			'description'         => __( 'Returns the name, description, recurring price and setup fee of one or more WHMCS products for a billing cycle. Prices are formatted strings exactly as WHMCS displays them, including currency.', 'whmcs-price' ),
			'category'            => 'whmcs-price',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'product_ids'   => array(
						'type'        => 'array',
						'description' => __( 'WHMCS product IDs (pid).', 'whmcs-price' ),
						'items'       => array(
							'type'    => 'integer',
							'minimum' => 1,
						),
						'minItems'    => 1,
						'maxItems'    => 20,
					),
					'billing_cycle' => array(
						'type'        => 'string',
						'description' => __( 'Billing cycle. Defaults to monthly.', 'whmcs-price' ),
						'enum'        => whmcs_price_ability_billing_cycles(),
						'default'     => 'monthly',
					),
				),
				'required'             => array( 'product_ids' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'billing_cycle' => array( 'type' => 'string' ),
					'products'      => array(
						'type'  => 'array',
						'items' => array(
							'type'       => 'object',
							'properties' => array(
								'product_id'  => array( 'type' => 'integer' ),
								'available'   => array( 'type' => 'boolean' ),
								'name'        => array( 'type' => array( 'string', 'null' ) ),
								'description' => array( 'type' => array( 'string', 'null' ) ),
								'price'       => array( 'type' => array( 'string', 'null' ) ),
								'setup_fee'   => array( 'type' => array( 'string', 'null' ) ),
							),
						),
					),
				),
			),
			// Public data, same policy as GET /whmcs-price/v1/product/{pid}.
			'permission_callback' => '__return_true',
			'execute_callback'    => 'whmcs_price_ability_get_product_price',
			'meta'                => $meta,
		)
	);

	wp_register_ability(
		'whmcs-price/get-domain-price',
		array(
			'label'               => __( 'Get WHMCS domain price', 'whmcs-price' ),
			'description'         => __( 'Returns register, renew and transfer prices for a domain extension (TLD) and registration period. Prices are formatted strings exactly as WHMCS displays them, including currency.', 'whmcs-price' ),
			'category'            => 'whmcs-price',
			'input_schema'        => array(
				'type'                 => 'object',
				'properties'           => array(
					'tld'        => array(
						'type'        => 'string',
						'description' => __( 'Domain extension, with or without leading dot, e.g. "se" or ".com".', 'whmcs-price' ),
						'pattern'     => '^\.?[A-Za-z0-9\-]{1,24}$',
					),
					'reg_period' => array(
						'type'        => 'integer',
						'description' => __( 'Registration period in years (1-10). Defaults to 1.', 'whmcs-price' ),
						'minimum'     => 1,
						'maximum'     => 10,
						'default'     => 1,
					),
					'types'      => array(
						'type'        => 'array',
						'description' => __( 'Transaction types to return. Defaults to all three.', 'whmcs-price' ),
						'items'       => array(
							'type' => 'string',
							'enum' => array( 'register', 'renew', 'transfer' ),
						),
						'minItems'    => 1,
						'uniqueItems' => true,
					),
				),
				'required'             => array( 'tld' ),
				'additionalProperties' => false,
			),
			'output_schema'       => array(
				'type'       => 'object',
				'properties' => array(
					'tld'        => array( 'type' => 'string' ),
					'reg_period' => array( 'type' => 'integer' ),
					'available'  => array( 'type' => 'boolean' ),
					'prices'     => array(
						'type'       => 'object',
						'properties' => array(
							'register' => array( 'type' => array( 'string', 'null' ) ),
							'renew'    => array( 'type' => array( 'string', 'null' ) ),
							'transfer' => array( 'type' => array( 'string', 'null' ) ),
						),
					),
				),
			),
			'permission_callback' => '__return_true',
			'execute_callback'    => 'whmcs_price_ability_get_domain_price',
			'meta'                => $meta,
		)
	);
}
add_action( 'wp_abilities_api_init', 'whmcs_price_register_abilities' );

/**
 * Apply the plugin's REST read rate limit when an ability runs over REST.
 *
 * In-process calls (other plugins, WP-CLI) are not limited, matching how
 * the shortcodes and blocks behave.
 *
 * @since  2.10.0
 * @param  string $endpoint  Bucket name shared with the REST endpoints.
 * @param  bool   $cache_hit Whether every requested value is already cached.
 * @return true|WP_Error
 */
function whmcs_price_ability_rate_limit( string $endpoint, bool $cache_hit ): bool|WP_Error {
	if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
		return true;
	}
	return whmcs_price_rest_read_rate_limit( $endpoint, $cache_hit );
}

/**
 * Execute callback for whmcs-price/get-product-price.
 *
 * @since  2.10.0
 * @param  mixed $input Prepared input (array) — see the input schema.
 * @return array|WP_Error
 */
function whmcs_price_ability_get_product_price( $input = null ) {
	$input = is_array( $input ) ? $input : array();

	// WordPress 7.2 normalises "1,2" to array( 1, 2 ); accept both on 6.9.
	$raw_ids = $input['product_ids'] ?? array();
	if ( is_string( $raw_ids ) || is_int( $raw_ids ) ) {
		$raw_ids = explode( ',', (string) $raw_ids );
	}
	$ids = array_values( array_unique( array_filter( array_map( 'intval', (array) $raw_ids ), static fn( $id ) => $id > 0 ) ) );

	if ( empty( $ids ) ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'At least one valid product ID is required.', 'whmcs-price' ), array( 'status' => 400 ) );
	}
	if ( count( $ids ) > 20 ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'At most 20 product IDs can be requested at once.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$cycle = isset( $input['billing_cycle'] ) ? (string) $input['billing_cycle'] : 'monthly';
	if ( ! in_array( $cycle, whmcs_price_ability_billing_cycles(), true ) ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'Unknown billing cycle.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$all_cached = true;
	foreach ( $ids as $id ) {
		foreach ( array( 'name', 'description', 'price' ) as $attr ) {
			$all_cached = $all_cached && whmcs_price_rest_product_cache_hit( $id, $cycle, $attr );
		}
	}
	$limited = whmcs_price_ability_rate_limit( 'product', $all_cached );
	if ( is_wp_error( $limited ) ) {
		return $limited;
	}

	$to_null = static fn( string $value ): ?string => ( 'NA' === $value || '' === trim( $value ) ) ? null : $value;

	$products = array();
	foreach ( $ids as $id ) {
		$name        = $to_null( wp_strip_all_tags( WHMCS_Price_API::get_product_data( $id, $cycle, 'name' ) ) );
		$description = $to_null( wp_strip_all_tags( WHMCS_Price_API::get_product_data( $id, $cycle, 'description' ) ) );
		$price_raw   = WHMCS_Price_API::get_product_data( $id, $cycle, 'price' );
		$price       = $to_null( 'NA' === $price_raw ? 'NA' : wp_strip_all_tags( whmcs_price_strip_setup_fee( $price_raw ) ) );
		$setup_fee   = null === $price ? null : $to_null( WHMCS_Price_API::get_product_setup_fee( $id, $cycle ) );

		$products[] = array(
			'product_id'  => $id,
			'available'   => null !== $price,
			'name'        => $name,
			'description' => $description,
			'price'       => $price,
			'setup_fee'   => $setup_fee,
		);
	}

	return array(
		'billing_cycle' => $cycle,
		'products'      => $products,
	);
}

/**
 * Execute callback for whmcs-price/get-domain-price.
 *
 * @since  2.10.0
 * @param  mixed $input Prepared input (array) — see the input schema.
 * @return array|WP_Error
 */
function whmcs_price_ability_get_domain_price( $input = null ) {
	$input = is_array( $input ) ? $input : array();

	$tld = strtolower( ltrim( trim( (string) ( $input['tld'] ?? '' ) ), '.' ) );
	$tld = substr( (string) preg_replace( '/[^a-z0-9\-]/', '', $tld ), 0, 24 );
	if ( '' === $tld ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'A domain extension (TLD) is required.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$period = isset( $input['reg_period'] ) ? (int) $input['reg_period'] : 1;
	if ( $period < 1 || $period > 10 ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'Registration period must be between 1 and 10 years.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$all_types = array( 'register', 'renew', 'transfer' );
	$types     = isset( $input['types'] ) ? array_values( array_intersect( $all_types, (array) $input['types'] ) ) : $all_types;
	if ( empty( $types ) ) {
		return new WP_Error( 'whmcs_price_invalid_input', __( 'Unknown transaction type.', 'whmcs-price' ), array( 'status' => 400 ) );
	}

	$all_cached = true;
	foreach ( $types as $type ) {
		$all_cached = $all_cached && whmcs_price_rest_domain_cache_hit( $tld, $type, $period );
	}
	$limited = whmcs_price_ability_rate_limit( 'domain', $all_cached );
	if ( is_wp_error( $limited ) ) {
		return $limited;
	}

	$prices    = array();
	$available = false;
	foreach ( $types as $type ) {
		$value           = WHMCS_Price_API::get_domain_price( $tld, $type, $period );
		$prices[ $type ] = ( 'NA' === $value || '' === trim( $value ) ) ? null : wp_strip_all_tags( $value );
		$available       = $available || null !== $prices[ $type ];
	}

	return array(
		'tld'        => $tld,
		'reg_period' => $period,
		'available'  => $available,
		'prices'     => $prices,
	);
}
