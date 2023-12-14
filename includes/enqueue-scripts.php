<?php
function radical_enqueue_react_app()
{
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    if (is_a_page_with_my_shortcode()) {

        wp_enqueue_style('radical-react-app-css', $plugin_url . '/app/assets/style.css', array(), '1.0.0');

        wp_register_script('radical-react-app', $plugin_url . '/app/assets/index.iife.js', array(), '1.0.0', true);
        wp_enqueue_script('radical-react-app');
    }
}

add_action('wp_enqueue_scripts', 'radical_enqueue_react_app');

function is_a_page_with_my_shortcode()
{
    global $post;
    return is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'radical_form_shortcode');
}

function radical_form_enqueue_admin_styles()
{
    $plugin_url = plugin_dir_url(dirname(__FILE__));
    wp_enqueue_style('radical-form-admin-icon-css', $plugin_url . '/assets/icon.css');
}

add_action('admin_enqueue_scripts', 'radical_form_enqueue_admin_styles');
