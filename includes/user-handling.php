<?php

function populate_user_data_form()
{
    if (is_user_logged_in()) {
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
        return rest_ensure_response(new stdClass()); // Return empty body for not logged in users
    }
}

function handle_user_status_and_role($email)
{
    $user_id = email_exists($email);

    if ($user_id && user_can($user_id, 'subscriber')) {
        return new WP_Error('existing_subscriber', 'User already exists as a subscriber', array('status' => 400));
    }

    if ($user_id && !is_user_logged_in()) {
        return new WP_Error('not_logged_in', 'You must be logged in to update your information.', array('status' => 401));
    }

    return $user_id;
}

function check_user_exists($request)
{
    $email = sanitize_email($request['email']);
    $user_id = handle_user_status_and_role($email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    $is_user_exists = (bool) $user_id;

    return rest_ensure_response(array('isNew' => !$is_user_exists));
}

function handle_user_submission($request)
{
    $email = sanitize_email($request['email']);
    $user_id = handle_user_status_and_role($email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    $userdata = array(
        'user_email' => $email,
        'first_name' => sanitize_text_field($request['firstName']),
        'last_name' => sanitize_text_field($request['lastName']),
        'role' => 'customer',
    );

    if ($user_id) {
        $userdata['ID'] = $user_id;
        $user_id = wp_update_user($userdata);
    } else {
        $user_id = wp_insert_user($userdata);
        wp_new_user_notification($user_id, null, 'both');
    }

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Update custom user meta fields
    update_user_meta($user_id, 'billing_phone', sanitize_text_field($request['mobilePhone']));
    update_user_meta($user_id, 'date_of_birth', sanitize_text_field($request['dateOfBirth'])); // YYYYDDMM

    wp_set_current_user($user_id);
    wp_set_auth_cookie($user_id);

    return rest_ensure_response(array('message' => 'User processed successfully'));
}
