<?php
function get_product_variation()
{
    $options = get_option('radical_form_options');
    $product_id = $options['product_id'];
    $custom_price_variation_id = $options['custom_price_variation_id'];
    $product = wc_get_product($product_id);

    if (!$product || !$product->is_type('variable')) {
        return new WP_Error('invalid_product', 'Invalid product ID or not a variable product', array('status' => 404));
    }

    $variations = $product->get_available_variations();
    $variation_data = array();

    foreach ($variations as $variation) {
        $variation_data[] = array(
            'variationId' => $variation['variation_id'],
            'price' => $variation['display_price'],
            'isCustomPriceId' => $variation['variation_id'] == $custom_price_variation_id,
        );
    }

    return rest_ensure_response(array('options' => $variation_data));
}
