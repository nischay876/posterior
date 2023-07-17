<?php

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$secret = $_ENV['TURNSTILE_SECRET_KEY'];
$token = $_POST['cf-turnstile-response'];

if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

    // Validate the token by calling the "/siteverify" API endpoint.
    $formData = array(
        'secret' => $secret,
        'response' => $token,
        'remoteip' => $ip
    );

    $url = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';
    $options = array(
        'http' => array(
            'header' => 'Content-type: application/x-www-form-urlencoded',
            'method' => 'POST',
            'content' => http_build_query($formData)
        )
    );

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    $outcome = json_decode($result, true);
    ?>