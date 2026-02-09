<?php
/**
 * Shortcode implementation using the WHMCS_Price_API service.
 */

defined('ABSPATH') || exit;

function whmcs_func($atts) {
    // Standardize attributes
    $atts = shortcode_atts([
        'pid'  => '',
        'bc'   => '',
        'show' => 'name,description,price',
        'tld'  => '',
        'type' => '',
        'reg'  => ''
    ], $atts, 'whmcs');

    // 1. PRODUCT PRICING
    if (!empty($atts['pid']) && !empty($atts['bc'])) {
        $billing_cycles = [
            '1m' => 'monthly', '3m' => 'quarterly', '6m' => 'semiannually',
            '1y' => 'annually', '2y' => 'biennially', '3y' => 'triennially',
        ];

        $bc_r = $billing_cycles[$atts['bc']] ?? 'monthly';
        $pids = explode(',', $atts['pid']);
        $show = explode(',', $atts['show']);

        $output = "<table class='whmcs-product-table'><thead><tr>";
        foreach ($show as $header) { $output .= "<th>" . esc_html(ucfirst($header)) . "</th>"; }
        $output .= "</tr></thead><tbody>";

        foreach ($pids as $pid) {
            $output .= "<tr>";
            foreach ($show as $attr) {
                $val = WHMCS_Price_API::get_product_data(intval($pid), $bc_r, sanitize_text_field($attr));
                $output .= "<td>" . esc_html($val) . "</td>";
            }
            $output .= "</tr>";
        }
        $output .= "</tbody></table>";
        return $output;
    }

    // 2. DOMAIN PRICING
    if (!empty($atts['tld'])) {
        $reg_period = str_replace('y', '', $atts['reg']);
        $price = WHMCS_Price_API::get_domain_price($atts['tld'], $atts['type'], $reg_period);
        return "<div class='whmcs-price'>" . esc_html($price) . "</div>";
    }

    return '';
}

add_action('init', function() {
    add_shortcode('whmcs', 'whmcs_func');
});