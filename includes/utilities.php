<?php

function generate_radical_session_token() {
    $token = bin2hex(openssl_random_pseudo_bytes(16)); // 32 characters
    $expiry = date('Y-m-d H:i:s', strtotime('+20 minutes')); // 20 minutes from now

    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    $wpdb->insert($table_name, array(
        'token' => $token,
        'expiry' => $expiry
    ));

    return $token;
}

function validate_radical_session_token($request) {
    $token = $request->get_header('x-auth-radical-form');

    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens'; // Adjust the table name as needed

    $result = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table_name} WHERE token = %s AND expiry > NOW()",
        $token
    ));

    return $result != null;
}

add_action('radical_form_cleanup_tokens', 'radical_form_cleanup_expired_tokens');

function radical_form_cleanup_expired_tokens() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'radical_session_tokens';

    $sql = "DELETE FROM $table_name WHERE expiry < NOW()";
    $wpdb->query($sql);
}