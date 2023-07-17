<?php
// Check if the user has reached the rate limit

require __DIR__ . '/../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// Get the client's IP address
if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
    $ip = $_SERVER['HTTP_CLIENT_IP'];
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
} else {
    $ip = $_SERVER['REMOTE_ADDR'];
}

// Check if the client's IP address has changed
if (isset($_COOKIE['client_ip']) && $ip !== $_COOKIE['client_ip']) {
    session_destroy();
    $error = 'For security reasons, your session has been logged out due to a change in your IP address.';
    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
    header('Location: login');
    exit; 
} else {
    // The IP address has not changed or no previous IP is stored

    // Set the client's IP address as a cookie
    setcookie('client_ip', $ip, time() + 60, '/');
}

if (!isset($_COOKIE['rate_limit'])) {
    // Set the initial request count and timestamp
    $requests = 1;
    $timestamp = time();
    setcookie('rate_limit', $requests . 'T' . $timestamp, time() + 60, '/'); // Cookie expires in 60 seconds
} else {
    // Retrieve the request count and timestamp from the cookie
    $cookieData = explode('T', $_COOKIE['rate_limit']);
    $requests = intval($cookieData[0]);
    $timestamp = intval($cookieData[1]);

    // Check if the rate limit has been exceeded
    if ($requests > 15 && (time() - $timestamp) < 60) {
        // Display an error message or take appropriate action
        http_response_code(429);
        $retryAfter = 60 - (time() - $timestamp); // Calculate the remaining time until the next minute
        header('Retry-After: ' . $retryAfter);
       // header('Content-Type: application/json');
        $errorMessage = array(
            'message' => 'The HTTP 429 Too Many Requests. Please wait for a while before making a new request.',
            'retryAfter' => $retryAfter
        );
        echo json_encode($errorMessage);
        exit();
    } elseif ((time() - $timestamp) > 60) {
        // Reset the request count and timestamp for a new minute
        $requests = 1;
        $timestamp = time();
    } else {
        // Increment the request count
        $requests++;
    }

    // Update the cookie with the new request count and timestamp
    setcookie('rate_limit', $requests . 'T' . $timestamp, time() + 60, '/'); // Cookie expires in 60 seconds
}
?>

<style>
        .discord-icon {
            position: fixed;
            bottom: 160px;
            right: 20px;
            z-index: 0;
        }
        .discord-icon2 {
            position: fixed;
            bottom: 90px;
            right: 20px;
            z-index: 1000;
        }
        .discord-icon3 {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 500;
        }
    </style>
    <!-- Discord icon -->
    <a href="<?php echo $_ENV['DISCORD_SERVER']; ?>" target="_blank" class="discord-icon3">
        <img src="https://assets-global.website-files.com/6257adef93867e50d84d30e2/636e0a6a49cf127bf92de1e2_icon_clyde_blurple_RGB.png" alt="Discord Icon" width="50" >
    </a>
    