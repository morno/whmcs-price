<?php
/*
 * Plugin Name: WHMCS Price
 * Plugin URI:  https://github.com/morno/whmcs-price
 * Description: Dynamic way for extracting product & domain price from WHMCS for use on the pages of your website!
 * Version:     2.2.0
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Author:      MohammadReza Kamali, Tobias Sörensson
 * Text Domain: whmcs-price
 * Domain Path: /languages
 * Author URI:  https://weconnect.se
 * License:     GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Developer : MohammadReza Kamali, Tobias Sörensson
 * Web Site  : IRANWebServer.Net, weconnect.se
 * E-Mail    : kamali@iranwebsv.net, tobias@weconnect.se
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// 2. Define constants with unique prefix
define( 'WHMCS_PRICE_VERSION', '2.2.0' );
define( 'WHMCS_PRICE_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHMCS_PRICE_URL', plugin_dir_url( __FILE__ ) );

// Backwards compatibility for existing files (short_code.php and settings.php)
define( 'WP_WHMCS_Prices_DIR', WHMCS_PRICE_DIR );
define( 'WP_WHMCS_Prices_URL', WHMCS_PRICE_URL );

/**
 * 3. Load API Service First
 * This class is the engine for both Shortcodes and Gutenberg.
 */
require_once WHMCS_PRICE_DIR . 'includes/class-whmcs-api.php';

/**
 * 4. Load text domain for translations
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
 * 5. Initialize the plugin functionality
 */
function whmcs_price_init() {
    // Load Shortcodes (Required for current functionality)
    $shortcode_file = WHMCS_PRICE_DIR . 'includes/short_code/short_code.php';
    if ( file_exists( $shortcode_file ) ) {
        require_once $shortcode_file;
    }

    // Load Settings and Admin logic
    if ( is_admin() ) {
        // Compatibility check for Multisite if you wish to maintain your original restriction
        if ( is_multisite() ) {
            return;
        }

        $settings_file = WHMCS_PRICE_DIR . 'includes/settings.php';
        if ( file_exists( $settings_file ) ) {
            require_once $settings_file;
            
            if ( class_exists( 'WHMCSPrice' ) ) {
                new WHMCSPrice();
            }
        }
    }
}
add_action( 'plugins_loaded', 'whmcs_price_init' );