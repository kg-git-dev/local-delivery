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
    
    echo '</div>';
}

// Save custom fields
add_action('woocommerce_process_product_meta', 'save_local_delivery_fields');
function save_local_delivery_fields($post_id) {
    $local_delivery_enabled = isset($_POST['_local_delivery_enabled']) ? 'yes' : 'no';
    update_post_meta($post_id, '_local_delivery_enabled', $local_delivery_enabled);
}
