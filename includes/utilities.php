<?php

function generate_radical_session_token()
{
    $token = bin2hex(openssl_random_pseudo_bytes(16)); // 32 characters
    $expiry = time() + (20 * 60); // 20 minutes

    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    $wpdb->insert($table_name, array(
        'token' => $token,
        'expiry' => $expiry
    ));

    return rest_ensure_response(array('token' => $token), 200);
}

function validate_radical_session_token($request, $isAjax = false)
{
    if ($isAjax) {
        $token = $request;
    } else {
        $token = $request->get_header('X-Auth-Radical-Form');
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE token = %s AND expiry > %d",
        $token,
        time()
    ));

    return $result != null;
}

add_action('radical_form_cleanup_tokens', 'radical_form_cleanup_expired_tokens');

function radical_form_cleanup_expired_tokens()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    $sql = "DELETE FROM $table_name WHERE expiry < %d";
    $wpdb->query($wpdb->prepare($sql, time()));
}
