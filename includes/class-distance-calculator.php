<?php

if (!defined('ABSPATH')) exit;

class Distance_Calculator {

    public static function get_distance($address, $city, $postcode)
    {
        $store_lat = get_option('store_latitude');
        $store_lng = get_option('store_longitude');
        
        if (empty($store_lat) || empty($store_lng)) {
            return 99999; // Fallback if store coordinates not set
        }
        
        $query = urlencode("$address, $city, $postcode");
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
        $response = wp_remote_get($url, array(
            'timeout' => 10,
            'user-agent' => 'WordPress/WooCommerce Local Delivery Plugin'
        ));
        
        if (is_wp_error($response)) return 99999;
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        
        if (!empty($data) && isset($data[0]->lat, $data[0]->lon)) {
            $lat = $data[0]->lat;
            $lng = $data[0]->lon;
            return self::haversine_distance($store_lat, $store_lng, $lat, $lng);
        }
        
        return 99999;
    }

    private static function haversine_distance($lat1, $lon1, $lat2, $lon2)
    {
        $earth_radius = 3959; // miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c;
    }
}
