<?php
// Copyleaks API ile içerik özgünlük kontrolü (geliştirilmiş sürüm)

function copyleaks_get_auth_token($email, $api_key) {
    $response = wp_remote_post('https://id.copyleaks.com/v3/account/login/api', array(
        'headers' => array('Content-Type' => 'application/json'),
        'body' => json_encode(array(
            'email' => $email,
            'key' => $api_key
        ))
    ));

    if (is_wp_error($response)) {
        return false;
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    return $body['access_token'] ?? false;
}

function copyleaks_check_content($content, $email, $api_key) {
    $token = copyleaks_get_auth_token($email, $api_key);
    if (!$token) {
        return 'Auth token alınamadı.';
    }

    $scan_id = uniqid('wp_ai_', true);
    $scan_url = "https://api.copyleaks.com/v3/scans/submit/file/$scan_id";

    $payload = array(
        'file' => base64_encode($content),
        'properties' => array(
            'webhooks' => array(
                'status' => home_url('/copyleaks-status-webhook'),
                'completed' => home_url('/copyleaks-complete-webhook')
            ),
            'sandbox' => true
        )
    );

    $response = wp_remote_post($scan_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $token
        ),
        'body' => json_encode($payload)
    ));

    if (is_wp_error($response)) {
        return 'İstek başarısız oldu.';
    }

    $body = wp_remote_retrieve_body($response);
    return json_decode($body, true);
}
?>
