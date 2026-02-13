<?php
/*
 * Plugin Name:       WHMCS Price
 * Plugin URI:        https://github.com/morno/whmcs-price
 * Description:       A modernized and secure way to display real-time pricing for products and domains from your WHMCS instance.
 * Version:           2.2.2
 * Requires at least: 6.0
 * Tested up to:      6.9
 * Requires PHP:      8.1
 * Author:            Tobias Sörensson (Morno), MohammadReza Kamali
 * Author URI:        https://github.com/morno
 * Text Domain:       whmcs-price
 * Domain Path:       /languages
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

/**
 * Main Plugin File
 *
 * This file initializes the plugin, defines constants, and loads
 * the necessary includes for the API, settings, and shortcodes.
 *
 * @package   WHMCS_Price
 * @author    Tobias Sörensson, MohammadReza Kamali
 * @license   GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants.
 * * Used for file pathing and versioning throughout the plugin.
 * @since 2.2.0
 */
define( 'WHMCS_PRICE_VERSION', '2.2.2' );
define( 'WHMCS_PRICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHMCS_PRICE_URL', plugin_dir_url( __FILE__ ) );

/**
 * Backwards compatibility for legacy files.
 * Provides support if older add-ons or custom code use the old naming convention.
 */
define( 'WP_WHMCS_Prices_DIR', WHMCS_PRICE_DIR );
define( 'WP_WHMCS_Prices_URL', WHMCS_PRICE_URL );

/**
 * Load the API Service Class.
 * This class handles all data retrieval and caching from WHMCS.
 */
require_once WHMCS_PRICE_DIR . 'includes/class-whmcs-api.php';

/**
 * Load plugin text domain for translations.
 *
 * Tells WordPress where to find the .mo and .po files for the 
 * 'whmcs-price' text domain.
 *
 * @since 2.2.0
 * @return void
 */
function whmcs_price_load_textdomain() {
    load_plugin_textdomain( 
        'whmcs-price', 
        false, 
        dirname( plugin_basename( __FILE__ ) ) . '/languages' 
    );
}
add_action( 'init', 'whmcs_price_load_textdomain' );

/**
 * Initialize the plugin functionality.
 *
 * Hooks into 'plugins_loaded' to ensure all dependencies are available.
 * Loads shortcodes for the frontend and settings for the admin area.
 *
 * @since 2.2.0
 * @return void
 */
function whmcs_price_init() {
    // Load Shortcodes (Frontend)
    $shortcode_file = WHMCS_PRICE_DIR . 'includes/short_code/short_code.php';
    if ( file_exists( $shortcode_file ) ) {
        require_once $shortcode_file;
    }

    // Load Admin Settings (Dashboard only)
    if ( is_admin() ) {
        // We typically don't run the settings UI on multisite networks unless activated there
        if ( is_multisite() ) {
            return;
        }

        $settings_file = WHMCS_PRICE_DIR . 'includes/settings.php';
        if ( file_exists( $settings_file ) ) {
            require_once $settings_file;
            
            /**
             * Instantiate the WHMCSPrice settings class.
             */
            if ( class_exists( 'WHMCSPrice' ) ) {
                new WHMCSPrice();
            }
        }
    }
}
add_action( 'plugins_loaded', 'whmcs_price_init' );