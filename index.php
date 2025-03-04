<?php
/*
Plugin Name: Local Delivery
Description: Adds a local delivery shipping method to Woocommerce with radius checks using OpenStreetMap.
Version: 1.0
Author: Kushal Ghimire
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Add custom shipping method class
add_action('woocommerce_shipping_init', 'local_delivery_shipping_method_init');
function local_delivery_shipping_method_init() {
    class WC_Local_Delivery_Shipping_Method extends WC_Shipping_Method {

        public function __construct() {
            $this->id = 'local_delivery';
            $this->method_title = __('Local Delivery', 'woocommerce');
            $this->method_description = __('Delivers within a specified mile radius.', 'woocommerce');
            $this->enabled = 'yes';
            $this->init();
        }

        function init() {
            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        }

        function init_form_fields() {
            $this->form_fields = array(
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Enter a title for the shipping method.', 'woocommerce'),
                    'default' => __('Local Delivery', 'woocommerce'),
                ),
            );
        }

        public function calculate_shipping($package = array()) {
            $destination = $package['destination'];
            $distance = $this->get_distance($destination['address'], $destination['city'], $destination['postcode']);
            $selected_radius = $this->get_max_radius_for_cart();

            if ($distance <= $selected_radius) {
                $rate = array(
                    'id' => $this->id,
                    'label' => $this->title,
                    'cost' => '5.00',
                );
                $this->add_rate($rate);
            }
        }

        private function get_distance($address, $city, $postcode) {
            $store_lat = get_option('store_latitude');
            error_log("lat");
            error_log($store_lat);
            $store_lng = get_option('store_longitude');
            error_log($store_lng);
            $query = urlencode("$address, $city, $postcode");
            $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
            $response = wp_remote_get($url);
            
            if (is_wp_error($response)) return 99999; // Fallback to large distance

            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            
            if (!empty($data) && isset($data[0]->lat, $data[0]->lon)) {
                $lat = $data[0]->lat;
                $lng = $data[0]->lon;
                error_log("consumer");
                error_log($lat);
                error_log($lng);
                return $this->haversine_distance($store_lat, $store_lng, $lat, $lng);
            }
            return 99999; // If no result, return large distance
        }

        private function haversine_distance($lat1, $lon1, $lat2, $lon2) {
            $earth_radius = 3959; // miles
            $dLat = deg2rad($lat2 - $lat1);
            $dLon = deg2rad($lon2 - $lon1);
            $a = sin($dLat/2) * sin($dLat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon/2) * sin($dLon/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            return $earth_radius * $c;
        }

        private function get_max_radius_for_cart() {
            $max_radius = 0;
            foreach (WC()->cart->get_cart() as $cart_item) {
                $product_id = $cart_item['product_id'];
                if (get_post_meta($product_id, '_local_delivery_enabled', true) === 'yes') {
                    $radius = (int) get_post_meta($product_id, '_local_delivery_radius', true);
                    $max_radius = max($max_radius, $radius);
                }
            }
            return $max_radius;
        }
    }
}

// Add method to WooCommerce
add_filter('woocommerce_shipping_methods', 'add_local_delivery_shipping_method');
function add_local_delivery_shipping_method($methods) {
    $methods['local_delivery'] = 'WC_Local_Delivery_Shipping_Method';
    return $methods;
}

// Add custom field to product page
add_action('woocommerce_product_options_shipping', 'add_local_delivery_checkbox');
function add_local_delivery_checkbox() {
    echo '<div class="options_group">';
    woocommerce_wp_checkbox(array(
        'id' => '_local_delivery_enabled',
        'label' => __('Enable Local Delivery', 'woocommerce'),
    ));

    woocommerce_wp_select(array(
        'id' => '_local_delivery_radius',
        'label' => __('Delivery Radius (miles)', 'woocommerce'),
        'options' => array(
            '10' => __('10 miles', 'woocommerce'),
            '15' => __('15 miles', 'woocommerce'),
            '25' => __('25 miles', 'woocommerce'),
        ),
    ));
    echo '</div>';
}

// Save custom field
add_action('woocommerce_process_product_meta', 'save_local_delivery_fields');
function save_local_delivery_fields($post_id) {
    $local_delivery_enabled = isset($_POST['_local_delivery_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_local_delivery_enabled', $local_delivery_enabled);

    if (isset($_POST['_local_delivery_radius'])) {
        update_post_meta($post_id, '_local_delivery_radius', sanitize_text_field($_POST['_local_delivery_radius']));
    }
}

// Initialize store coordinates on plugin activation
register_activation_hook(__FILE__, 'initialize_store_coordinates_on_activation');
function initialize_store_coordinates_on_activation() {
    error_log("activation hook");
    $store_address = get_option('woocommerce_store_address');
    error_log($store_address);
    $store_city = get_option('woocommerce_store_city');
    error_log($store_city);
    $store_postcode = get_option('woocommerce_store_postcode');
    $query = urlencode("$store_address, $store_city, $store_postcode");
    $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
    $response = wp_remote_get($url);
    
    error_log( print_r($response, true) );

    if (!is_wp_error($response)) {
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        if (!empty($data) && isset($data[0]->lat, $data[0]->lon)) {
            update_option('store_latitude', $data[0]->lat);
            update_option('store_longitude', $data[0]->lon);
        }
    }
}
