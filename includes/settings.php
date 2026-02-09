<?php
/**
 * Developer: MohammadReza Kamali, Tobias Sörensson
 * Website: IRANWebServer.Net, weconnect.se
 * License: GPL-3.0-or-later
 */

// Prevent direct access
defined('ABSPATH') || exit;

class WHMCSPrice
{
    private array $options = [];

    public function __construct()
    {
        add_action('admin_menu', [$this, 'whmcspr_plugin_page']);
        add_action('admin_init', [$this, 'whmcspr_init']);
        // Removed redundant load_textdomain as it is handled in the main file
        add_action('admin_bar_menu', [$this, 'add_admin_bar_clear_cache'], 100);
    }

    /**
     * Add the settings page to the WordPress admin menu.
     */
    public function whmcspr_plugin_page()
    {
        add_menu_page(
            __('WHMCS Price Options', 'whmcs-price'),
            __('WHMCS Price Settings', 'whmcs-price'),
            'manage_options',
            'whmcs_price',
            [$this, 'whmcspr_admin_page'],
            'dashicons-admin-generic',
            100
        );
    }

    /**
     * Display the admin page content.
     */
    public function whmcspr_admin_page()
    {
        // Security Check: Ensure user has permission
        if (!current_user_can('manage_options')) {
            return;
        }

        // Handle Cache Clear with Nonce for security
        if (isset($_POST['whmcs_clear_cache'])) {
            check_admin_referer('whmcs_clear_cache_action');
            $this->clear_whmcs_cache();
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared successfully!', 'whmcs-price') . '</p></div>';
        }

        $this->options = get_option('whmcs_price_option', []);
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('price_option_group');
                do_settings_sections('whmcs_price');
                submit_button();
                ?>
            </form>

            <hr>
            <h2><?php esc_html_e('Maintenance', 'whmcs-price'); ?></h2>
            <form method="post" action="">
                <?php wp_nonce_field('whmcs_clear_cache_action'); ?>
                <input type="hidden" name="whmcs_clear_cache" value="1" />
                <?php submit_button(__('Clear WHMCS Cache', 'whmcs-price'), 'secondary'); ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register settings and fields.
     */
    public function whmcspr_init()
    {
        register_setting('price_option_group', 'whmcs_price_option', [$this, 'sanitize']);

        add_settings_section(
            'setting_section_id',
            __('API Settings', 'whmcs-price'),
            [$this, 'print_section_info'],
            'whmcs_price'
        );

        add_settings_field(
            'whmcs_url',
            __('WHMCS URL', 'whmcs-price'),
            [$this, 'whmcs_url_callback'],
            'whmcs_price',
            'setting_section_id'
        );
        
        // Shortcode instructions sections
        add_settings_section(
            'shortcode_section_id',
            __('Usage Instructions', 'whmcs-price'),
            null,
            'whmcs_price'
        );
    }

    /**
     * Sanitize user input.
     */
    public function sanitize($input): array
    {
        $new_input = [];
        if (isset($input['whmcs_url'])) {
            $new_input['whmcs_url'] = esc_url_raw(trim($input['whmcs_url']));
        }
        return $new_input;
    }

    public function print_section_info()
    {
        esc_html_e('Enter your WHMCS API details below to fetch dynamic pricing.', 'whmcs-price');
    }

    public function whmcs_url_callback()
    {
        $whmcs_url = $this->options['whmcs_url'] ?? '';
        printf(
            '<input type="url" id="whmcs_url" name="whmcs_price_option[whmcs_url]" value="%s" class="regular-text" placeholder="https://whmcs.example.com" />',
            esc_attr($whmcs_url)
        );
        echo '<p class="description">' . esc_html__('The root URL of your WHMCS installation (without trailing slash).', 'whmcs-price') . '</p>';
    }

    /**
     * Clear WHMCS cache using WordPress Transients API standard.
     */
    public function clear_whmcs_cache()
    {
        global $wpdb;
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_whmcs_%'");
        $wpdb->query("DELETE FROM $wpdb->options WHERE option_name LIKE '_transient_timeout_whmcs_%'");
    }

    /**
     * Add clear cache button to admin bar.
     */
    public function add_admin_bar_clear_cache($admin_bar)
    {
        if (current_user_can('manage_options')) {
            $admin_bar->add_menu([
                'id'    => 'whmcs-clear-cache',
                'title' => __('Clear WHMCS Cache', 'whmcs-price'),
                'href'  => wp_nonce_url(add_query_arg('whmcs_clear_cache', '1'), 'whmcs_clear_cache_admin_bar'),
                'meta'  => ['title' => __('Clear WHMCS Cache', 'whmcs-price')],
            ]);

            if (isset($_GET['whmcs_clear_cache']) && $_GET['whmcs_clear_cache'] == '1') {
                check_admin_referer('whmcs_clear_cache_admin_bar');
                $this->clear_whmcs_cache();
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Cache cleared successfully!', 'whmcs-price') . '</p></div>';
                });
            }
        }
    }
}