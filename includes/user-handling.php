<?php

function populate_user_data_form()
{
    $is_logged_in = wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE']);
    error_log("is_logged_in: $is_logged_in, is_user_logged_in: " . is_user_logged_in());

    if (is_user_logged_in() || $is_logged_in) {
        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $user_data = array(
            'firstName' => $user_info->first_name ?? '',
            'lastName' => $user_info->last_name ?? '',
            'email' => $user_info->user_email ?? '',
            'phoneNumber' => get_user_meta($user_id, 'billing_phone', true) ?: '',
            'dateOfBirth' => get_user_meta($user_id, 'date_of_birth', true) ?: ''
        );

        return rest_ensure_response($user_data);
    } else {
        return rest_ensure_response(new stdClass());
    }
}

function handle_user_status_and_role($email, $check_logged_in = true)
{
    global $logger_service_instance;
    $action_name = "validating_user";
    $is_logged_in = wp_verify_nonce($_SERVER['HTTP_X_WP_NONCE']) || is_user_logged_in();

    $user_id = email_exists($email);

    if ($user_id && user_can($user_id, 'subscriber')) {
        $logger_service_instance->log_warning($action_name, "user is a subscriber", $email);

        return new WP_Error(
            'existing_subscriber',
            'User already exists as a subscriber',
            array(
                'status' => 400
            )
        );
    }

    if ($check_logged_in && $user_id && !$is_logged_in) {
        $logger_service_instance->log_warning($action_name, "user is not logged in", $email);

        return new WP_Error(
            'not_logged_in',
            'You must be logged in to update your information.',
            array(
                'status' => 401
            )
        );
    }

    return $user_id;
}

function check_user_exists($request)
{
    global $logger_service_instance;
    $email = sanitize_email($request['email']);
    $user_id = handle_user_status_and_role($email, false);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    $is_user_exists = (bool) $user_id;

    $logger_service_instance->log_info(
        "check_user_exists",
        "checks made and user exists === $is_user_exists",
        array(
            'email' => $email, 'exists' => $is_user_exists
        )
    );

    return rest_ensure_response(array('isNew' => !$is_user_exists));
}

function handle_user_submission($request)
{
    global $logger_service_instance;
    $action_name = "user_submission";


    $email = sanitize_email($request['email']);

    $logger_service_instance->log_info(
        $action_name,
        "starting user submission and validation",
        $email
    );

    $user_id = handle_user_status_and_role($email);
    $is_new_user = !$user_id;

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    $userdata = array(
        'user_email' => $email,
        'first_name' => sanitize_text_field($request['firstName']),
        'last_name' => sanitize_text_field($request['lastName']),
    );

    if ($user_id) {
        $userdata['ID'] = $user_id;
        $user_id = wp_update_user($userdata);
    } else {
        $userdata['role'] = 'customer';
        $user_id = wp_insert_user($userdata);
        wp_new_user_notification($user_id, null, 'both');
    }

    if (is_wp_error($user_id)) {
        $logger_service_instance->log_error($action_name, "user update/creation failed", $email);
        return $user_id;
    }

    // Update custom user meta fields
    update_user_meta($user_id, 'billing_phone', sanitize_text_field($request['phoneNumber']));
    update_user_meta($user_id, 'date_of_birth', sanitize_text_field($request['dateOfBirth'])); // YYYYDDMM

    if ($is_new_user) {
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);

        $new_nonce = wp_create_nonce('wp_rest');
        header('X-WP-Nonce: ' . $new_nonce);
    }

    $logger_service_instance->log_info($action_name, "user updated/created successfully", $email);
    return rest_ensure_response(array('message' => 'User processed successfully'));
}
