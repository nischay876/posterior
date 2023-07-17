<?php
session_start();

require 'src/db/psql.php';


require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

try {
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (isset($_GET['code'])) {
    $verification_code = $_GET['code'];
    $email = $_GET['email'];

    $sql = "SELECT * FROM users WHERE verification_code = :verification_code AND email = :email";
    $stmt = $conn->prepare($sql);
    $stmt->bindParam(':verification_code', $verification_code);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user['id'];

        $update_sql = "UPDATE users SET is_verified = CAST(:is_verified AS BOOLEAN) WHERE id = :user_id";
        $update_stmt = $conn->prepare($update_sql);
        $is_verified = true; // Cast the expression as a boolean
        $update_stmt->bindParam(':is_verified', $is_verified, PDO::PARAM_BOOL);
        $update_stmt->bindParam(':user_id', $user_id);
        
        if ($update_stmt->execute()) {
            // User is verified, make API call to create a new user
            $api_url = $_ENV['ASS_DOMAIN'] . "/api/user/";

            $data = array(
            'username' => $user['username'],
            'password' => $user['password']
            // Optionally include 'admin' or 'meta' fields in the $data array
            );

            $options = array(
            'http' => array(
                'header' => "Content-type: application/json\r\n" .
                "Authorization: " . $_ENV['ASS_ADMIN_TOKEN'] . "\r\n",
                'method' => 'POST',
                'content' => json_encode($data)
            )
            );

            $context = stream_context_create($options);
            $response = file_get_contents($api_url, false, $context);

            if ($response === false) {
                echo "API call failed.";
            } else {
                $response_data = json_decode($response, true);
                if (isset($response_data['token'])) {
                    $api_token = $response_data['token'];
                    $userr_id = $response_data['unid'];
                    // Update the user's API key in the database
                    $update_apikey_sql = "UPDATE users SET apikey = :api_token WHERE id = :user_id";
                    $update_userr_id_sql = "UPDATE users SET userr_id = :userr_id WHERE id = :user_id";
                    $update_apikey_stmt = $conn->prepare($update_apikey_sql);
                    $update_apikey_stmt->bindParam(':api_token', $api_token);
                    $update_apikey_stmt->bindParam(':user_id', $user_id);
                    $update_userr_id_stmt = $conn->prepare($update_userr_id_sql);
                    $update_userr_id_stmt->bindParam(':userr_id', $userr_id);
                    $update_userr_id_stmt->bindParam(':user_id', $user_id);
                    $update_apikey_stmt->execute();
                    $update_userr_id_stmt->execute();
                
                    // Auto-login the user
                    $_SESSION['user_id'] = $user_id;
                
                    // Set the session and timestamp
                    $_SESSION['timestamp'] = time(); // Store the current timestamp
                
                    // Set the cookie with a 7-days expiration time
                    $cookie_name = 'user_id';
                    $cookie_value = $user_id;
                    $cookie_expiration = time() + (7 * 24 * 60 * 60); // 7 days
                    setcookie($cookie_name, $cookie_value, $cookie_expiration, '/');

                    // Update verification_code to null
                    $update_query = "UPDATE users SET verification_code = NULL WHERE id = :user_id";
                    $update_stmt = $conn->prepare($update_query);
                    $update_stmt->bindParam(':user_id', $user_id);
                    $update_stmt->execute();
                
                    // Redirect to welcome.php
                    header("Location: /");
                    exit();
                }
            }
        } else {
            echo "Error: " . $update_stmt->errorInfo()[2];
        }
    } else {
        echo "Invalid verification code.";
    }
}

$conn = null;
?>

<h1>Account Verification</h1>
<p>Please wait while we verify your account...</p>
