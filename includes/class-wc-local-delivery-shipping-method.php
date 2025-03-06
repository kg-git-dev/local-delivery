<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class WC_Local_Delivery_Shipping_Method extends WC_Shipping_Method
{

    public function __construct()
    {
        $this->id = 'local_delivery';
        $this->method_title = __('Local Delivery', 'woocommerce');
        $this->method_description = __('Delivers within a specified mile radius.', 'woocommerce');
        $this->enabled = 'yes';
        $this->init();
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        $this->title = $this->get_option('title');
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_checkout_process', array($this, 'validate_local_delivery_distance'));
        add_action('woocommerce_after_calculate_totals', array($this, 'validate_local_delivery_distance'));
    }

    public function init_form_fields()
    {
        $this->form_fields = array(
            'title' => array(
                'title' => __('Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('Enter a title for the shipping method.', 'woocommerce'),
                'default' => __('Local Delivery', 'woocommerce'),
            ),
        );
    }

    public function calculate_shipping($package = array())
    {
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

    private function get_distance($address, $city, $postcode)
    {
        $store_lat = get_option('store_latitude');
        $store_lng = get_option('store_longitude');
        $query = urlencode("$address, $city, $postcode");
        $url = "https://nominatim.openstreetmap.org/search?q=$query&format=json&limit=1";
        $response = wp_remote_get($url);

        if (is_wp_error($response)) return 99999; // Fallback to large distance

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if (!empty($data) && isset($data[0]->lat, $data[0]->lon)) {
            $lat = $data[0]->lat;
            $lng = $data[0]->lon;
            return $this->haversine_distance($store_lat, $store_lng, $lat, $lng);
        }
        return 99999;
    }

    private function haversine_distance($lat1, $lon1, $lat2, $lon2)
    {
        $earth_radius = 3959; // miles
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) * sin($dLat / 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) * sin($dLon / 2);
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earth_radius * $c;
    }

    private function get_max_radius_for_cart()
    {
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

    public function validate_local_delivery_distance()
    {
        // Ensure we only validate if a shipping address is provided
        if (empty(WC()->customer->get_shipping_postcode()) || empty(WC()->customer->get_shipping_city())) {
            return; // Skip validation if address isn't set yet
        }

        $max_radius = $this->get_max_radius_for_cart();
        $out_of_range_products = [];

        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (get_post_meta($product_id, '_local_delivery_enabled', true) === 'yes') {
                $distance = $this->get_distance(
                    WC()->customer->get_shipping_address(),
                    WC()->customer->get_shipping_city(),
                    WC()->customer->get_shipping_postcode()
                );
                if ($distance > $max_radius) {
                    $out_of_range_products[] = get_the_title($product_id);
                }
            }
        }

        if (!empty($out_of_range_products)) {
            $error_message = __('The following products cannot be delivered to your address and must be removed from the cart: ') . implode(', ', $out_of_range_products);

            // Check if the error message already exists
            $notices = wc_get_notices('error');
            $error_exists = false;

            foreach ($notices as $notice) {
                if (strpos($notice['notice'], $error_message) !== false) {
                    $error_exists = true;
                    break;
                }
            }

            if (!$error_exists) {
                wc_add_notice($error_message, 'error');
            }
        }
    }
}
