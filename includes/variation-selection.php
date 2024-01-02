<?php

require_once 'utilities.php';

function get_variation_attributes_for_cart($variation_id)
{
    $variation = wc_get_product($variation_id);
    if (!$variation || !$variation instanceof WC_Product_Variation) {
        return array();
    }

    $attributes = $variation->get_variation_attributes();
    $variation_attributes = array();

    foreach ($attributes as $key => $value) {
        if (strpos($key, 'attribute_') !== 0) {
            $key = 'attribute_' . $key;
        }
        $variation_attributes[$key] = $value;
    }

    return $variation_attributes;
}

add_action('wp_ajax_handle_set_variation_selection', 'handle_set_variation_selection');
add_action('wp_ajax_nopriv_handle_set_variation_selection', 'handle_set_variation_selection');
function handle_set_variation_selection()
{
    global $logger_service_instance;
    $action_name = "handle_set_variation_selection";

    error_log("Entering $action_name function");

    $token = isset($_SERVER['HTTP_X_AUTH_RADICAL_FORM']) ? $_SERVER['HTTP_X_AUTH_RADICAL_FORM'] : '';

    if (!validate_radical_session_token($token, true)) {
        $logger_service_instance->log_warning($action_name, 'Session token is invalid or expired');
        status_header(412);
        wp_send_json_error(array('code' => 'invalid_token', 'message' => 'Session token is invalid or expired'));
        wp_die();
    }

    $json_data = json_decode(file_get_contents('php://input'), true);

    error_log("$action_name - Received JSON data: " . print_r($json_data, true));

    $is_logged_in = wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE']) || is_user_logged_in();

    if (!$is_logged_in) {
        $logger_service_instance->log_warning($action_name, 'user is not logged in');
        status_header(401);
        wp_send_json_error(array('code' => 'not_logged_in', 'message' => 'User must be logged in to perform this action'));
        wp_die();
    }

    $user = wp_get_current_user();
    if (in_array('subscriber', $user->roles)) {
        $logger_service_instance->log_warning($action_name, 'user is a subscriber');
        status_header(400);
        wp_send_json_error(array('code' => 'existing_subscriber', 'message' => 'User already exists as a subscriber'));
        wp_die();
    }


    if (function_exists('WC') && !isset(WC()->session)) {
        WC()->initialize_session();
        WC()->session->init();
        error_log("WooCommerce session initialized");
    }

    $options = get_option('radical_form_options');
    $product_id = $options['product_id'];
    $custom_price_variation_id = $options['custom_price_variation_id'];
    $variation_id = isset($json_data['variationId']) ? intval($json_data['variationId']) : null;
    $price = isset($json_data['price']) ? intval($json_data['price']) : null;

    if (isset(WC()->cart)) {
        WC()->cart->empty_cart();
    } else {
        $logger_service_instance->log_error($action_name, 'WooCommerce cart not available');
        status_header(500);
        wp_send_json_error(array('code' => 'woocommerce_cart_unavailable', 'message' => 'WooCommerce cart unavailable'));
        wp_die();
    }

    $cart_item_data = array();
    $custom_price = $price >= 25 ? $price : 25;

    if ($variation_id == $custom_price_variation_id) {
        $cart_item_data['attribute_pa_membership_pay'] = $custom_price;
        $cart_item_data['custom_variation_price'] = $custom_price;
    }

    $variation_attributes = get_variation_attributes_for_cart($variation_id);
    $added = WC()->cart->add_to_cart($product_id, 1, $variation_id, $variation_attributes, $cart_item_data);

    if (!$added) {
        $logger_service_instance->log_error($action_name, "Failed to add variation to cart - $added", $variation_id);
        status_header(400);
        wp_send_json_error(array('code' => 'add_to_cart_failed', 'message' => 'Failed to add variation to cart'));
        wp_die();
    }

    if ($variation_id == $custom_price_variation_id) {

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $cart_item['data']->set_price($custom_price);
            $cart_item['data']->set_regular_price($custom_price);
            $cart_item['data']->save();
            error_log(print_r($cart_item, true));
        }

        WC()->cart->calculate_totals();
    }

    $logger_service_instance->log_info($action_name, 'variation added to cart', $variation_id);

    wp_send_json_success(array('message' => 'Variation added to cart successfully'));
    wp_die();
}
