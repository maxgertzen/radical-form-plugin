<?php

require_once 'utilities.php';

function post_variation_selection($request)
{
    global $logger;
    $action_name = "set_variation_selection";

    if (!is_user_logged_in()) {
        $logger->log_warning($action_name, 'user is not logged in');
        return new WP_Error('not_logged_in', 'User must be logged in to perform this action', array('status' => 401));
    }

    $options = get_option('radical_form_options');
    $product_id = $options['product_id'];
    $custom_price_variation_id = $options['custom_price_variation_id'];
    $variation_id = sanitize_text_field($request['variationId']);
    $price = sanitize_text_field($request['price']);

    WC()->cart->empty_cart();
    $cart_item_data = array(); // Custom data can be added here

    if ($variation_id == $custom_price_variation_id) {
        $cart_item_data['custom_variation_price'] = $price <= 25 ? $price : 25;
    }

    WC()->cart->add_to_cart($product_id, 1, $variation_id, array(), $cart_item_data);

    $logger->log_info($action_name, 'variation added to cart', $variation_id);

    return rest_ensure_response(array('message' => 'Variation added to cart successfully'));
}
