<?php
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';  // Make sure to include the PHPMailer library

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'src/db/psql.php';

if (isset($_POST['submit'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
        $email = $_POST['email'];

        $stmt = $conn->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->bindParam(':email', $email);
        $stmt->execute();

        if ($stmt->rowCount() === 1) {
            // Generate a reset code
            $reset_codes = generateResetCode();
            $reset_code_for_db = $reset_codes['database'];
            $reset_code_for_email = $reset_codes['email'];

            // Store the reset code in the database
            $stmt = $conn->prepare("UPDATE users SET reset_code = :reset_code WHERE email = :email");
            $stmt->bindParam(':reset_code', $reset_code_for_db);
            $stmt->bindParam(':email', $email);
            $stmt->execute();

            // Send the reset password email
            $reset_link = $_ENV['DASHBOARD_DOMAIN'] . "/reset_password?code=$reset_code_for_email&email=$email";

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
                       
                // Email content
                // Read the email template HTML file
                $templateFilePath = __DIR__ . '/email_templates/password_reset.html';
                $templateContent = file_get_contents($templateFilePath);

                // Replace placeholders with actual data
                $templateContent = str_replace('{email}', $email, $templateContent);
                $templateContent = str_replace('{discord_link}', $_ENV['DISCORD_SERVER'], $templateContent);
                $templateContent = str_replace('{reset_link}', $reset_link, $templateContent);

                // Email content
                $mail->isHTML(true);
                $mail->Subject = 'IMG3 - Password Reset';
                $mail->AltBody = $reset_link;
                $mail->Body = $templateContent;

                // Send the email
                $mail->send();   
                $error = 'Check your email: ' . $email;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: reset_password');
                exit;    
            } catch (Exception $e) {
                $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: reset_password');
                exit;    
            }
        } else {
            $error = 'Email not found in the database.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: reset_password');
            exit;     
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: reset_password');
        exit;     
    }
} elseif (isset($_POST['reset_password'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
        $reset_code = $_GET['code'];
        $email = $_GET['email'];

                    // Extract the code and timestamp from the reset_code
                    $code_parts = explode('_', $reset_code);
                    $rcode = $code_parts[0];
                    $timestamp = $code_parts[1];

        if (isset($_POST['reset_password'])) {
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];

            // Validate the new password and confirm password
            if ($new_password !== $confirm_password) {
                $error = 'New password and confirm password do not match.';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: reset_password');
                exit;     
            }
            
            // Check if the reset code exists in the database
            $check_query = "SELECT * FROM users WHERE email = :email AND reset_code LIKE :reset_code";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->execute(array(':email' => $email, ':reset_code' => $reset_code . '%'));

            if ($check_stmt->rowCount() === 1) {
                // Reset the password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                            // Check if the reset code exists in the database
                $stmt = $conn->prepare('UPDATE users SET password = :hashed_password, reset_code = NULL WHERE email = :email');
                $stmt->bindParam(':hashed_password', $hashed_password);
                $stmt->bindParam(':email', $email);
                $stmt->execute();

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
                       
                    // Email content
                    // Read the email template HTML file
                    $templateFilePath = __DIR__ . '/email_templates/password_changed.html';
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

                    // Email content
                    $mail->isHTML(true);
                    $mail->Subject = 'IMG3 - Password Changed';
                    $mail->AltBody = 'Password Changed from' . $country . 'with ip:' . $ip ;
                    $mail->Body = $templateContent;

                    // Send the email
                    $mail->send();

                    $error = 'Password has been successfully reset. <a href="/login">Login</a>';
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: reset_password');
                    exit;   
                } catch (Exception $e) {
                    $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: reset_password');
                    exit;    
                }
            } else {
                $error = 'Invalid reset code';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: reset_password');
                exit;     
            }
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: reset_password');
        exit;      
    }
}

function generateResetCode()
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
    <title>Reset Password</title>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <link href=/assets/css/form.css rel=stylesheet>
</head>
<body>
    <?php if (!isset($_GET['code'])) { ?>
        <div class="container">
        <h1>Reset Password</h1>
        <form method="POST" action="">
            <input type="email" name="email" placeholder="Email:" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="resetpass" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="submit">Reset Password</button>
            <button type="button" class="register-button" onclick="location.href='login'">Login</button>
        </form>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div style="word-break: break-word;" class="error-message">' . $_COOKIE['error'] . '</div></center>';            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>
    </div>
    <?php } else {  
                $reset_code = $_GET['code'];
                $email = $_GET['email'];
        // Check if the reset code exists in the database
        $check_query = "SELECT * FROM users WHERE email = :email AND reset_code LIKE :reset_code";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->execute(array(':email' => $email, ':reset_code' => $reset_code . '%'));

        if ($check_stmt->rowCount() === 1) {
            $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
            $reset_code = $user['reset_code'];
    
            // Extract the code and timestamp from the reset_code
            $code_parts = explode('_', $reset_code);
            $code = $code_parts[0];
            $timestamp = $code_parts[1];
    
            // Check if the timestamp is within the valid range (e.g., 5 minutes)
            $expirationTime = strtotime('+5 minutes', $timestamp);
            if (time() <= $expirationTime) {
                // Reset code is valid, continue with the rest of the code

            } else {
                $stmt = $conn->prepare('UPDATE users SET reset_code = NULL WHERE email = :email');
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                $error = 'Reset code has expired.';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: reset_password.php');
                exit;       
            }
        } else {
            $error = 'Invalid reset code.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: reset_password.php');
            exit;   
        }
        ?>
        <div class="container">
        <h1>Reset Password</h1>
        <form method="POST" action="">
            <input type="password" name="new_password" placeholder="New Password:" required>
            <input type="password" name="confirm_password" placeholder="Confirm Password:" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="resetpassws" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="reset_password">Reset Password</button>
            <button class="register-button" type="button" onclick="location.href='login'">Login</button>
        </form>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div style="word-break: break-word;" class="error-message">' . $_COOKIE['error'] . '</div></center>';            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>
        </div>
    <?php } ?>
</body>
</html>
