<?php

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Add custom fields to product pages
add_action('woocommerce_product_options_shipping', 'add_local_delivery_checkbox');
function add_local_delivery_checkbox() {
    echo '<div class="options_group">';
    
    // Checkbox to enable local delivery
    woocommerce_wp_checkbox(array(
        'id' => '_local_delivery_enabled',
        'label' => __('Enable Local Delivery', 'woocommerce'),
    ));

    // Number input for custom delivery radius
    woocommerce_wp_text_input(array(
        'id' => '_local_delivery_radius',
        'label' => __('Delivery Radius (miles)', 'woocommerce'),
        'desc_tip' => true,
        'description' => __('Enter the delivery radius in miles.', 'woocommerce'),
        'type' => 'number',
        'custom_attributes' => array(
            'step' => '0.1', // Allow fractional miles
            'min' => '1',    // Minimum value allowed
        ),
    ));
    
    echo '</div>';
}

// Save custom fields
add_action('woocommerce_process_product_meta', 'save_local_delivery_fields');
function save_local_delivery_fields($post_id) {
    $local_delivery_enabled = isset($_POST['_local_delivery_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_local_delivery_enabled', $local_delivery_enabled);

    if (isset($_POST['_local_delivery_radius'])) {
        update_post_meta($post_id, '_local_delivery_radius', sanitize_text_field($_POST['_local_delivery_radius']));
    }
}
