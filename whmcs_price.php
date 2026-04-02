<?php
/*
 * Plugin Name:       Mornolink for WHMCS
 * Plugin URI:        https://github.com/morno/whmcs-price
 * Description:       A modernized and secure way to display real-time pricing for products and domains from your WHMCS instance.
 * Version:           2.8.0
 * Requires at least: 6.4
 * Tested up to:      7.0
 * Requires PHP:      8.1
 * Requires Plugins:  
 * Author:            Tobias Sörensson (Morno), MohammadReza Kamali
 * Author URI:        https://github.com/morno
 * Text Domain:       whmcs-price
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

// Exit if accessed directly to prevent unauthorized access to the plugin file.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Define plugin constants.
 * * Used for file pathing and versioning throughout the plugin.
 * @since 2.2.0
 */
define( 'WHMCS_PRICE_VERSION', '2.8.0' );
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
 * Load shared rendering helpers.
 * Functions available to all output modules: shortcodes, blocks, Elementor.
 * Add new shared formatting/rendering utilities here.
 * @since 2.6.0
 */
require_once WHMCS_PRICE_DIR . 'includes/class-whmcs-price-helpers.php';

/**
 * Load the Gutenberg Block Registration.
 * Handles block registration, Pattern Overrides (Block Bindings), and related hooks.
 */
require_once WHMCS_PRICE_DIR . 'includes/gutenberg/blocks.php';

/**
 * Load the REST API endpoints.
 * Provides /wp-json/whmcs-price/v1/product/{pid} and /domain/{tld} for headless use.
 */
require_once WHMCS_PRICE_DIR . 'includes/rest-api.php';

/**
 * Load the Dashboard Widget.
 * Shows cache status on the WordPress admin dashboard.
 */
require_once WHMCS_PRICE_DIR . 'includes/dashboard-widget.php';

/**
 * Load WP-CLI commands.
 * Only loaded when WP-CLI is running — has no effect on web requests.
 */
require_once WHMCS_PRICE_DIR . 'includes/cli.php';

/**
 * Load the Elementor Class.
 * This class handles the registration and rendering of Elementor for WHMCS pricing.
 */
require_once WHMCS_PRICE_DIR . 'includes/elementor/class-whmcs-elementor.php';

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
    $shortcode_file = WHMCS_PRICE_DIR . 'includes/shortcode/shortcode.php';
    if ( file_exists( $shortcode_file ) ) {
        require_once $shortcode_file;
    }

    // Load Admin Settings (Dashboard only)
    if ( is_admin() && ! wp_doing_ajax() ) {
        $settings_file = WHMCS_PRICE_DIR . 'includes/settings.php';
        if ( file_exists( $settings_file ) ) {
            require_once $settings_file;

            /**
             * Instantiate the WHMCSPrice settings class.
             * On Multisite, settings are loaded per-site in the regular admin
             * and also available to network admins via the standard admin menu.
             */
            if ( class_exists( 'WHMCSPrice' ) ) {
                new WHMCSPrice();
            }
        }
    }
}
add_action( 'plugins_loaded', 'whmcs_price_init' );

/**
 * Multisite: Register a Network Admin settings page so network admins can
 * configure the plugin from the Network Admin dashboard. Settings are stored
 * per-site — this page just provides navigation to each site's settings.
 *
 * Only registered when running in a Multisite network.
 *
 * @since 2.8.0
 */
if ( is_multisite() ) {
    add_action( 'network_admin_menu', function() {
        add_menu_page(
            __( 'WHMCS Price', 'whmcs-price' ),
            __( 'WHMCS Price', 'whmcs-price' ),
            'manage_network_options',
            'whmcs_price_network',
            'whmcs_price_network_admin_page',
            'dashicons-tag',
            81
        );
    } );
}

/**
 * Render the Network Admin page.
 *
 * Shows a list of all sites in the network with direct links to each
 * site's own WHMCS Price settings page. Settings are always per-site.
 *
 * @since 2.8.0
 * @return void
 */
function whmcs_price_network_admin_page(): void {
    if ( ! current_user_can( 'manage_network_options' ) ) {
        wp_die( esc_html__( 'You do not have permission to access this page.', 'whmcs-price' ) );
    }

    $sites = get_sites( array( 'fields' => 'ids', 'number' => 100 ) );
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'WHMCS Price — Network Overview', 'whmcs-price' ); ?></h1>
        <p><?php esc_html_e( 'Settings are configured per site. Click a site below to manage its WHMCS Price settings.', 'whmcs-price' ); ?></p>
        <table class="widefat">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Site', 'whmcs-price' ); ?></th>
                    <th><?php esc_html_e( 'WHMCS URL', 'whmcs-price' ); ?></th>
                    <th><?php esc_html_e( 'Actions', 'whmcs-price' ); ?></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $sites as $site_id ) :
                switch_to_blog( $site_id );
                $blog_details = get_blog_details( $site_id );
                $options      = get_option( 'whmcs_price_option', array() );
                $whmcs_url    = ! empty( $options['whmcs_url'] ) ? $options['whmcs_url'] : esc_html__( '(not configured)', 'whmcs-price' );
                $settings_url = get_admin_url( $site_id, 'options-general.php?page=whmcs_price' );
                restore_current_blog();
                ?>
                <tr>
                    <td><strong><?php echo esc_html( $blog_details->blogname ); ?></strong><br><small><?php echo esc_html( $blog_details->siteurl ); ?></small></td>
                    <td><?php echo esc_html( $whmcs_url ); ?></td>
                    <td><a href="<?php echo esc_url( $settings_url ); ?>" class="button button-secondary"><?php esc_html_e( 'Settings', 'whmcs-price' ); ?></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}