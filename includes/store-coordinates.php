<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Initialize store coordinates on plugin activation
function initialize_store_coordinates_on_activation() {
    $store_address = get_option('woocommerce_store_address');
    $store_city = get_option('woocommerce_store_city');
    $store_postcode = get_option('woocommerce_store_postcode');

    if ($store_address && $store_city && $store_postcode) {
        $query = urlencode("$store_address, $store_city, $store_postcode");
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
        $response = wp_remote_get($url);

        if (!is_wp_error($response)) {
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body);
            if (!empty($data) && isset($data[0]->lat, $data[0]->lon)) {
                update_option('store_latitude', $data[0]->lat);
                update_option('store_longitude', $data[0]->lon);
            }
        }
    }
}

register_activation_hook(__FILE__, 'initialize_store_coordinates_on_activation');
