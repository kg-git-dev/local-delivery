<?php
/*
Plugin Name: Local Delivery
Description: Adds a local delivery shipping method to Woocommerce with radius checks using OpenStreetMap.
Version: 1.0
Author: Kushal Ghimire
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Include non-shipping method files
require_once plugin_dir_path(__FILE__) . 'includes/store-coordinates.php';
require_once plugin_dir_path(__FILE__) . 'includes/product-fields.php';

// Initialize the shipping method
add_action('woocommerce_shipping_init', 'local_delivery_shipping_method_init');
function local_delivery_shipping_method_init() {
    // Only include the shipping method file after WooCommerce has initialized
    require_once plugin_dir_path(__FILE__) . 'includes/class-wc-local-delivery-shipping-method.php';
}

// Add the shipping method to WooCommerce
add_filter('woocommerce_shipping_methods', 'add_local_delivery_shipping_method');
function add_local_delivery_shipping_method($methods) {
    $methods['local_delivery'] = 'WC_Local_Delivery_Shipping_Method';
    return $methods;
}

// Initialize store coordinates on plugin activation
register_activation_hook(__FILE__, 'initialize_store_coordinates_on_activation');