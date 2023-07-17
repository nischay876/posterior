<?php
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'vendor/autoload.php';  // Make sure to include the PHPMailer library

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'src/db/psql.php';

session_start();
    
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}

if (isset($_POST['submit'])) {

    include 'src/turnstile.php';

    if ($outcome['success']) {
        $email = $_POST['email'];

        // Check if the email exists in the database
        $check_query = "SELECT * FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();

        if ($check_stmt->rowCount() === 1) {
            $user = $check_stmt->fetch();

            if ($user['suspended'] == true) {
                $error = 'Your account is suspended. Please feel free to join the Discord server for support <a href='. $_ENV['DISCORD_SERVER'] . '>discord.gg</a>.';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: passwordless');
                exit;
            }
        }
        // Generate a reset code
        $magic_codes = generateMagicCode();
        $magic_code_for_db = $magic_codes['database'];
        $magic_code_for_email = $magic_codes['email'];

        // Store the reset code in the database
        $update_query = "UPDATE users SET magiccode = :magiccode WHERE email = :email";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':magiccode', $magic_code_for_db);
        $update_stmt->bindParam(':email', $email);

        if ($update_stmt->execute()) {
            // Send the reset password email
            $magic_link = $_ENV['DASHBOARD_DOMAIN'] . "/passwordless?code=$magic_code_for_email&email=$email";

                    // Send the email using SMTP
                    $mail = new PHPMailer(true);
                    
            
            try {
                // SMTP configuration
                $mail->isSMTP();
                $mail->Host = $_ENV['SMTP_HOST'];
                $mail->SMTPAuth = true;
                $mail->Username = $_ENV['SMTP_USERNAME'];
                $mail->Password = $_ENV['SMTP_PASSWORD'];
                $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                $mail->Port = intval($_ENV['SMTP_PORT']);
                $mail->setFrom($_ENV['SMTP_FROM'], $_ENV['SMTP_NAME']);
                $mail->addAddress($email);
                $mail->isHTML(true);
                $mail->Subject = 'IMG3 - Passwordless Authentication';
                $mail->AltBody  = $magic_link;
                $templateFilePath = __DIR__ . '/email_templates/password_less.html';
                $templateContent = file_get_contents($templateFilePath);
                $ip = $_SERVER['REMOTE_ADDR'];
                $apiUrl = "http://ip-api.com/json/$ip";
                $apiResponse = file_get_contents($apiUrl);
                $apiData = json_decode($apiResponse, true);
                if ($apiData['status'] === 'success') {
                    $country = $apiData['country'] . ', ' . $apiData['regionName'] . ', ' . $apiData['city'];
                } else if (!$country) {
                    $country = 'Unknown';
                }
                // Replace the placeholders in the email template with the actual values
                $templateContent = str_replace('{location}', $country, $templateContent);
                $templateContent = str_replace('{ip_address}', $ip, $templateContent);
                $templateContent = str_replace('{discord_link}', $_ENV['DISCORD_SERVER'], $templateContent);
                $templateContent = str_replace('{email}', $email, $templateContent);
                $templateContent = str_replace('{DASHBOARD_DOMAIN}', $_ENV['DASHBOARD_DOMAIN'], $templateContent);
                $templateContent = str_replace('{magic_link}', $magic_link, $templateContent);
                $mail->Body = $templateContent;
                $mail->send();
            
                $error = 'Check your email: ' . $email;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: passwordless');
                exit;    
            } catch (Exception $e) {
                $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: passwordless');
                exit;    
            }
        } else {
            $error = 'Email not found in the database.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: passwordless');
            exit;    
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: passwordless');
        exit;        
    }
} elseif (isset($_GET['code'])) {
    session_start();

    $reset_code = $_GET['code'];
    $email = $_GET['email'];
    
    // Check if the reset code exists in the database
    $check_query = "SELECT * FROM users WHERE email = :email AND magiccode LIKE :reset_code";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->execute(array(':email' => $email, ':reset_code' => $reset_code . '%'));

    if ($check_stmt->rowCount() === 1) {
        $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
        $magiccode = $user['magiccode'];
    
        // Extract the code and timestamp from the magiccode
        $code_parts = explode('_', $magiccode);
        $code = $code_parts[0];
        $timestamp = $code_parts[1];
    
        // Check if the timestamp is within the valid range (e.g., 5 minutes)
        $expirationTime = strtotime('+5 minutes', $timestamp);
        if (time() <= $expirationTime) {
            // Reset code is valid, continue with the rest of the code
            $user_id = $user['id'];

            // Update magiccode to null
            $update_query = "UPDATE users SET magiccode = NULL WHERE magiccode = :magiccode";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':magiccode', $magiccode);
            $update_stmt->execute();

            $_SESSION['user_id'] = $user_id;
            $_SESSION['timestamp'] = time();
            $cookie_name = 'user_id';
            $cookie_value = $user_id;
            $cookie_expiration = time() + (7 * 24 * 60 * 60); // 1 week
            setcookie($cookie_name, $cookie_value, $cookie_expiration, '/');

            // Redirect to welcome.php
            header("Location: /");
            exit();
        } else {
            $error = 'Reset code has expired.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: passwordless');
            exit;       
        }
    } else {
        $error = 'Invalid reset code.';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: passwordless');
        exit;   
    }
}
function generateMagicCode()
{
    $length = 8;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $reset_code = '';

    for ($i = 0; $i < $length; $i++) {
        $reset_code .= $characters[rand(0, strlen($characters) - 1)];
    }

    // Get the current timestamp
    $timestamp = time();

    // Combine the reset code and timestamp for the database
    $reset_code_with_timestamp = $reset_code . '_' . $timestamp;

    // Only use the reset code without timestamp for email
    $reset_code_without_timestamp = $reset_code;

    return array(
        'database' => $reset_code_with_timestamp,
        'email' => $reset_code_without_timestamp
    );
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>IMG3 - Passwordless Authentication</title>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <link href=/assets/css/form.css rel=stylesheet>
</head>
<body>
        <div class="container">
        <h1>Passwordless Authentication</h1>
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email:" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="passwordless" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="submit">Send magic link</button>
            <button type="button" class="register-button" onclick="window.location.href='login'">Login</button>
        </form>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div style="word-break: break-word;" class="error-message">' . $_COOKIE['error'] . '</div></center>';
            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>
    </div>
</body>
</html>
</html>
