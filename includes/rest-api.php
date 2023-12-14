<?php
require_once 'user-handling.php';
require_once 'product-variation.php';
require_once 'variation-selection.php';
require_once 'utilities.php';

class Radical_Form_REST_API
{
    function __construct()
    {
        add_action('rest_api_init', array($this, 'register_routes'));
    }

    public function register_routes()
    {
        register_rest_route('radical-form/v1', '/start-session', array(
            'methods' => 'POST',
            'callback' => array($this, 'generate_session_token'),
        ));

        register_rest_route('radical-form/v1', '/form-data', array(
            'methods' => 'GET',
            'callback' => 'populate_user_data_form',
        ));

        register_rest_route('radical-form/v1', '/check-email', array(
            'methods' => 'POST',
            'callback' => 'check_user_exists',
            'permission_callback' => array($this, 'validate_token_permission')
        ));

        register_rest_route('radical-form/v1', '/product-variations', array(
            'methods' => 'GET',
            'callback' => 'get_product_variation',
        ));

        register_rest_route('radical-form/v1', '/user-submit', array(
            'methods' => 'POST',
            'callback' => 'handle_user_submission',
            'permission_callback' => array($this, 'validate_token_permission')
        ));

        register_rest_route('radical-form/v1', '/set-selection', array(
            'methods' => 'POST',
            'callback' => 'post_variation_selection',
            'permission_callback' => array($this, 'validate_token_permission')
        ));
    }

    private function generate_session_token()
    {
        $token = generate_radical_session_token();
        return new WP_REST_Response(array('token' => $token), 200);
    }

    public function validate_token_permission($request)
    {
        if (!validate_radical_session_token($request)) {
            return new WP_Error(
                'invalid_token',
                'Session token is invalid or expired',
                array('status' => 412)
            );
        }
        return true;
    }
}
