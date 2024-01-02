<?php

if (!function_exists('radical_form_shortcode')) {
    function radical_form_shortcode()
    {
        $options = get_option('radical_form_options');
        if (!empty($options['activate_form'])) {
            return '<div id="radical-subscription-form"></div>';
        }
        return '';
    }

    add_shortcode('radical_form_shortcode', 'radical_form_shortcode');
}
