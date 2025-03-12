<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly
require_once plugin_dir_path(__FILE__) . 'class-distance-calculator.php';

class WC_Local_Delivery_Shipping_Method extends WC_Shipping_Method
{
    public function __construct($instance_id = 0)
    {
        $this->id = 'local_delivery';
        $this->instance_id = absint($instance_id);
        $this->method_title = __('Local Delivery', 'woocommerce');
        $this->method_description = __('Delivers within a specified mile radius.', 'woocommerce');
        $this->supports = array(
            'shipping-zones',
            'instance-settings',
        );
        
        $this->init();
        
        $this->enabled = $this->get_option('enabled');
        $this->title = $this->get_option('title', 'Local Delivery');
        
        // Hook to filter shipping methods
        add_filter('woocommerce_package_rates', array($this, 'disable_other_shipping_methods'), 100, 2);
        
        // Add hooks for persistent error messages and checkout validation
        add_action('woocommerce_before_checkout_form', array($this, 'check_local_delivery_availability'));
        add_action('woocommerce_after_checkout_validation', array($this, 'validate_local_delivery_checkout'), 10, 2);
        add_action('woocommerce_check_cart_items', array($this, 'check_local_delivery_availability'));
        add_action('woocommerce_cart_calculate_fees', array($this, 'check_local_delivery_availability'));
    }

    public function init()
    {
        $this->init_form_fields();
        $this->init_settings();
        add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
    }

    public function init_form_fields()
    {
        $this->instance_form_fields = array(
            'title' => array(
                'title' => __('Method Title', 'woocommerce'),
                'type' => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                'default' => __('Local Delivery', 'woocommerce'),
            ),
            'delivery_radius' => array(
                'title' => __('Delivery Radius (miles)', 'woocommerce'),
                'type' => 'number',
                'description' => __('Set the maximum delivery radius for local delivery.', 'woocommerce'),
                'default' => 10,
                'custom_attributes' => array('min' => 1),
            ),
            'delivery_cost' => array(
                'title' => __('Delivery Cost ($)', 'woocommerce'),
                'type' => 'number',
                'description' => __('Set the delivery cost for local delivery.', 'woocommerce'),
                'default' => 5.00,
                'custom_attributes' => array('min' => 0),
            ),
        );
    }

    /**
     * Calculate shipping based on delivery radius
     */
    public function calculate_shipping($package = array())
    {
        // Check if we have local delivery products in cart
        $local_delivery_product_names = $this->cart_has_local_delivery_product();
        
        if (empty($local_delivery_product_names)) {
            return; // Skip if no products marked for local delivery
        }
        
        // Get shipping address details
        $shipping_address = $package['destination']['address'];
        $shipping_city = $package['destination']['city'];
        $shipping_postcode = $package['destination']['postcode'];
        
        // Don't calculate if address isn't fully entered
        if (empty($shipping_address) || empty($shipping_city) || empty($shipping_postcode)) {
            return;
        }
        
        $max_radius = (float) $this->get_option('delivery_radius', 10);
        $delivery_cost = (float) $this->get_option('delivery_cost', 5.00);
        
        // Get distance
        $distance = Distance_Calculator::get_distance($shipping_address, $shipping_city, $shipping_postcode);
        
        // Store calculated distance in session for later use
        WC()->session->set('local_delivery_calculated_distance', $distance);
        WC()->session->set('local_delivery_products', $local_delivery_product_names);
        WC()->session->set('local_delivery_max_radius', $max_radius);
        
        // Add rate if within delivery radius
        if ($distance <= $max_radius) {
            $this->add_rate(array(
                'id' => $this->get_rate_id(),
                'label' => $this->title,
                'cost' => $delivery_cost,
            ));
        }
    }

    /**
     * Check local delivery availability and display persistent error
     */
    public function check_local_delivery_availability()
    {
        // Skip in admin
        if (is_admin()) {
            return;
        }
        
        // Check if cart exists and if we have local delivery products
        if (empty(WC()->cart) || WC()->cart->is_empty()) {
            return;
        }
        
        $local_delivery_product_names = $this->cart_has_local_delivery_product();
        
        if (empty($local_delivery_product_names)) {
            return; // No local delivery products in cart
        }
        
        // Get distance from session if available
        $distance = WC()->session->get('local_delivery_calculated_distance');
        $max_radius = WC()->session->get('local_delivery_max_radius', $this->get_option('delivery_radius', 10));
        
        // If distance isn't set yet, we might not have a complete address
        if ($distance === null) {
            return;
        }
        
        // Check if outside delivery radius
        if ($distance > $max_radius) {
            $error_message = __('The following products cannot be delivered to your address as you are outside our delivery area: ', 'woocommerce') . 
                             implode(', ', $local_delivery_product_names) . 
                             __(' Please remove these items to proceed with checkout.', 'woocommerce');
            
            // Add error notice - wc_add_notice ensures it's only added once
            wc_add_notice($error_message, 'error');
        }
    }
    
    /**
     * Validate checkout to block completion when local delivery is not available
     */
    public function validate_local_delivery_checkout($data, $errors)
    {
        $local_delivery_product_names = $this->cart_has_local_delivery_product();
        
        if (empty($local_delivery_product_names)) {
            return; // No local delivery products in cart
        }
        
        // Get distance from session
        $distance = WC()->session->get('local_delivery_calculated_distance');
        $max_radius = WC()->session->get('local_delivery_max_radius', $this->get_option('delivery_radius', 10));
        
        // If no distance is set, we need to calculate it
        if ($distance === null) {
            $shipping_address = $data['shipping_address_1'];
            $shipping_city = $data['shipping_city'];
            $shipping_postcode = $data['shipping_postcode'];
            
            if (empty($shipping_address) || empty($shipping_city) || empty($shipping_postcode)) {
                $errors->add('shipping', __('Please enter your shipping address to check delivery availability.', 'woocommerce'));
                return;
            }
            
            $distance = Distance_Calculator::get_distance($shipping_address, $shipping_city, $shipping_postcode);
        }
        
        // Block checkout if outside delivery radius
        if ($distance > $max_radius) {
            $error_message = __('Your order cannot be processed because the following products cannot be delivered to your address: ', 'woocommerce') . 
                             implode(', ', $local_delivery_product_names) . 
                             __(' Please remove these items to complete checkout.', 'woocommerce');
            
            $errors->add('shipping', $error_message);
        }
    }

    /**
     * Disable other shipping methods when local delivery is available
     */
    public function disable_other_shipping_methods($rates, $package)
    {
        // If there are no local delivery products in the cart, return all rates
        if (empty($this->cart_has_local_delivery_product())) {
            return $rates;
        }
        
        $local_delivery_rates = array();
        
        // Find all local delivery rates
        foreach ($rates as $rate_id => $rate) {
            if (strpos($rate_id, 'local_delivery') !== false) {
                $local_delivery_rates[$rate_id] = $rate;
            }
        }
        
        // If local delivery is available, return only local delivery rates
        if (!empty($local_delivery_rates)) {
            return $local_delivery_rates;
        }
        
        // If no local delivery rates are available but we have local delivery products,
        // return an empty array to force no shipping methods available
        if (!empty($this->cart_has_local_delivery_product())) {
            return array();
        }
        
        // Otherwise return all rates
        return $rates;
    }

    /**
     * Check if cart has products marked for local delivery
     */
    private function cart_has_local_delivery_product()
    {
        $local_delivery_product_names = [];
        
        if (is_admin() || empty(WC()->cart)) {
            return $local_delivery_product_names;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item) {
            $product_id = $cart_item['product_id'];
            if (get_post_meta($product_id, '_local_delivery_enabled', true) === 'yes') {
                $local_delivery_product_names[] = get_the_title($product_id);
            }
        }
        
        return $local_delivery_product_names;
    }
}