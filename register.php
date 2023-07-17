<?php
    use PHPMailer\PHPMailer\Exception;
    use PHPMailer\PHPMailer\PHPMailer;

    require __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();

    require 'vendor/autoload.php';  // Make sure to include the PHPMailer library

    session_start();
    
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}

if (isset($_POST['register'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
  
        include 'src/db/psql.php';

        // Function to generate a random verification code
        function generateVerificationCode($length = 8)
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $verification_code = '';
            for ($i = 0; $i < $length; $i++) {
                $verification_code .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $verification_code;
        }

        if (isset($_POST['register'])) {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $verification_code = generateVerificationCode();

            // Check if username already exists
            $check_query = "SELECT username FROM users WHERE username = :username OR email = :email";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                // Username already exists, handle the error
                $error = 'Username or Email already exists';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: register');
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $t = time();

            $sql = "INSERT INTO users (username, email, password, verification_code, timestamp) VALUES (:username, :email, :hashed_password, :verification_code, :t)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':hashed_password', $hashed_password);
            $stmt->bindParam(':verification_code', $verification_code);
            $stmt->bindParam(':t', $t);

            if ($stmt->execute()) {
                // Send verification email with the verification code
                $verification_link = $_ENV['DASHBOARD_DOMAIN'] . "/verify?code=$verification_code&email=$email";

                // Send the email using SMTP
                $mail = new PHPMailer(true);
                $smtpfrom = $_ENV['SMTP_FROM'];
                $smtpname = $_ENV['SMTP_NAME'];
                try {
                    // SMTP configuration
                    $mail->isSMTP();
                    $mail->Host = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USERNAME'];
                    $mail->Password = $_ENV['SMTP_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port = intval($_ENV['SMTP_PORT']);
                    $mail->setFrom($smtpfrom, $smtpname);
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'IMG3 - Verify Your Email to Get Started';
                    $mail->AltBody  = $verification_link;
                    $templateFilePath = __DIR__ . '/email_templates/new_user.html';
                    $templateContent = file_get_contents($templateFilePath);
                    $templateContent = str_replace('{verification_link}', $verification_link, $templateContent);
                    $templateContent = str_replace('{discord_link}', $_ENV['DISCORD_SERVER'], $templateContent);
                    $templateContent = str_replace('{email}', $email, $templateContent);
                    $mail->Body = $templateContent;
                    $mail->send();

                    $error = 'Check your email for the verification link: ' . $email;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: register');
                    exit;    
                } catch (Exception $e) {
                    $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: register');
                    exit;    
                }
            } else {
                $error = 'Error: Database Offline';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: register');
                exit;    
            }
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: register');
        exit;    
    }
} else if (isset($_POST['registerwd'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {

        include 'src/db/psql.php';

        // Function to generate a random verification code
        function generateVerificationCode($length = 8)
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $verification_code = '';
            for ($i = 0; $i < $length; $i++) {
                $verification_code .= $characters[rand(0, strlen($characters) - 1)];
            }
            return $verification_code;
        }

        if (isset($_POST['registerwd'])) {
            $username = $_POST['username'];
            $email = $_POST['email'];
            $password = $_POST['password'];
            $verification_code = generateVerificationCode();

            // Check if username already exists
            $check_query = "SELECT username FROM users WHERE username = :username OR email = :email";
            $check_stmt = $conn->prepare($check_query);
            $check_stmt->bindParam(':username', $username);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();

            if ($check_stmt->rowCount() > 0) {
                // Username already exists, handle the error
                $error = 'Username or Email already exists';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: register');
                exit;
            }

            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            $t = time();

            $sql = "INSERT INTO users (username, email, password, verification_code, timestamp) VALUES (:username, :email, :hashed_password, :verification_code, :t)";
            $stmt = $conn->prepare($sql);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':hashed_password', $hashed_password);
            $stmt->bindParam(':verification_code', $verification_code);
            $stmt->bindParam(':t', $t);

            if ($stmt->execute()) {
                // Send verification email with the verification code
                $verification_link = $_ENV['DASHBOARD_DOMAIN'] . "/verify?code=$verification_code&email=$email";

                // Send the email using SMTP
                $mail = new PHPMailer(true);
                $smtpfrom = $_ENV['SMTP_FROM'];
                $smtpname = $_ENV['SMTP_NAME'];
                try {
                    // SMTP configuration
                    $mail->isSMTP();
                    $mail->Host = $_ENV['SMTP_HOST'];
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['SMTP_USERNAME'];
                    $mail->Password = $_ENV['SMTP_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['SMTP_SECURE'];
                    $mail->Port = intval($_ENV['SMTP_PORT']);
                    $mail->setFrom($smtpfrom, $smtpname);
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'Welcome To IMG3';
                    $mail->AltBody  = $verification_link;
                    $templateFilePath = __DIR__ . '/email_templates/new_duser.html';
                    $templateContent = file_get_contents($templateFilePath);
                    $templateContent = str_replace('{login_link}', $_ENV['DASHBOARD_DOMAIN'] . '/login', $templateContent);
                    $templateContent = str_replace('{password}', $password, $templateContent);
                    $templateContent = str_replace('{discord_link}', $_ENV['DISCORD_SERVER'], $templateContent);
                    $templateContent = str_replace('{email}', $email, $templateContent);
                    $mail->Body = $templateContent;
                    $mail->send();

                    $error = 'Account Created Successfully: ' . $email;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location:'. $verification_link);
                    exit;    
                } catch (Exception $e) {
                    $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: register');
                    exit;    
                }
            } else {
                $error = 'Error: Database Offline';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: register');
                exit;    
            }
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: register');
        exit;    
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Register</title>
    <link href=/assets/css/form.css rel=stylesheet>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

</head>
<body>
<div class="container">
    <?php
    if (isset($_COOKIE['discord_user_data'])) {
        $successData = json_decode($_COOKIE['discord_user_data'], true);
        // Clear the success cookie
        //setcookie('discord_user_data', '', time() - 10, '/');
        unset($_SESSION['access_token']);
        ?>
        <h1>Register With Discord</h1>
        <form action="register" method="POST">
            <input type="hidden" value="<?php echo $successData["username"] . '#' . $successData["discriminator"] ?>" name="username" placeholder="Username" required>
            <input type="hidden" value="<?php echo $successData["email"] ?>" name="email" placeholder="Email" required>
            <input type="hidden" value="<?php echo generatePasswordCode() ?>" name="password" placeholder="Password" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="registerwd" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="registerwd">Continue</button>
        </form>
        <?php
    } else {
        ?>
        <h1>Register</h1>
        <form action="register" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="email" name="email" placeholder="Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="register" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="register">Register</button>
            <button type="button" class="register-button" onclick="location.href='login'">Login</button>
            <button type="button" class="register-button" onclick="location.href='?action=discord'">
                <span class="button-text">Register With Discord</span>
                <span class="icon-container">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-discord" viewBox="0 0 16 16">
                        <path d="M13.545 2.907a13.227 13.227 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.19 12.19 0 0 0-3.658 0 8.258 8.258 0 0 0-.412-.833.051.051 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.041.041 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032c.001.014.01.028.021.037a13.276 13.276 0 0 0 3.995 2.02a.05.05 0 0 0 .056-.019c.308-.42.582-.863.818-1.329a.05.05 0 0 0-.01-.059a.051.051 0 0 0-.018-.011a8.875 8.875 0 0 1-1.248-.595a.05.05 0 0 1-.02-.066a.051.051 0 0 1 .015-.019c.084-.063.168-.129.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.052.052 0 0 1 .053.007c.08.066.164.132.248.195a.051.051 0 0 1-.004.085a8.254 8.254 0 0 1-1.249.594a.05.05 0 0 0-.03.03a.052.052 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019a13.235 13.235 0 0 0 4.001-2.02a.049.049 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.034.034 0 0 0-.02-.019Zm-8.198 7.307c-.789 0-1.438-.724-1.438-1.612c0-.889.637-1.613 1.438-1.613c.807 0 1.45.73 1.438 1.613c0 .888-.637 1.612-1.438 1.612Zm5.316 0c-.788 0-1.438-.724-1.438-1.612c0-.889.637-1.613 1.438-1.613c.807 0 1.451.73 1.438 1.613c0 .888-.631 1.612-1.438 1.612Z"/>
                    </svg>
                </span>
            </button>
        </form>
        <?php
    }

    if (isset($_COOKIE['error'])) {
        echo '<center><div class="error-message">' . $_COOKIE['error'] . '</div></center>';
        setcookie('error', '', time() - 3600); // Clear the error cookie
    }
    ?>
</div>

</body>
</html>


<?php

define('OAUTH2_CLIENT_ID', $_ENV['OAUTH2_CLIENT_ID']);
define('OAUTH2_CLIENT_SECRET', $_ENV['OAUTH2_CLIENT_SECRET']);

$authorizeURL = 'https://ptb.discord.com/api/oauth2/authorize';
$tokenURL = 'https://ptb.discord.com/api/oauth2/token';
$apiURLBase = 'https://ptb.discord.com/api/users/@me';
$revokeURL = 'https://ptb.discord.com/api/oauth2/token/revoke';

// Start the login process by sending the user to Discord's authorization page
if (get('action') == 'discord') {

    $params = array(
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => $_ENV['DASHBOARD_DOMAIN'] . '/register',
    'response_type' => 'code',
    'scope' => 'identify email'
    );

    // Redirect the user to Discord's authorization page
    header('Location: https://ptb.discord.com/api/oauth2/authorize' . '?' . http_build_query($params));
    die();
}

// When Discord redirects the user back here, there will be a "code" and "state" parameter in the query string
// When Discord redirects the user back here, there will be a "code" and "state" parameter in the query string
if (get('code')) {

    // Exchange the auth code for a token
    $token = apiRequest(
        $tokenURL, array(
        "grant_type" => "authorization_code",
        'client_id' => OAUTH2_CLIENT_ID,
        'client_secret' => OAUTH2_CLIENT_SECRET,
        'redirect_uri' => $_ENV['DASHBOARD_DOMAIN'] . '/register',
        'code' => get('code')
        )
    );
    $logout_token = $token->access_token;
    $_SESSION['access_token'] = $token->access_token;


    header('Location: ' . $_SERVER['PHP_SELF']);
}

if (get('action') == 'logout') {
    // This should logout you
    logout(
        $revokeURL, array(
        'token' => session('access_token'),
        'token_type_hint' => 'access_token',
        'client_id' => OAUTH2_CLIENT_ID,
        'client_secret' => OAUTH2_CLIENT_SECRET,
        )
    );
    unset($_SESSION['access_token']);
    header('Location: ' . $_SERVER['PHP_SELF']);
    die();
}

require 'src/db/psql.php';

if (session('access_token')) {
    $user = apiRequest($apiURLBase);
    unset($_SESSION['access_token']);
                // Check if username already exists
                $check_query = "SELECT username FROM users WHERE email = :email";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':email', $user->email);
                $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        // Username already exists, handle the error
        unset($_SESSION['access_token']);
        $error = 'Discord Email already exists, Try to login';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: register');
        exit;
    }
    $DiscordUserData = array(
        'email' => $user->email,
        'username' => $user->username,
        'discriminator' => $user->discriminator
    );
    setcookie('discord_user_data', json_encode($DiscordUserData), time() + 120, '/');
    header('Location: register');
    exit();  
}

function apiRequest($url, $post=false, $headers=array())
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $response = curl_exec($ch);


    if ($post) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    }

    $headers[] = 'Accept: application/json';

    if (session('access_token')) {
        $headers[] = 'Authorization: Bearer ' . session('access_token');
    }

    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    return json_decode($response);
}

function logout($url, $data=array())
{
    $ch = curl_init($url);
    curl_setopt_array(
        $ch, array(
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        CURLOPT_HTTPHEADER => array('Content-Type: application/x-www-form-urlencoded'),
        CURLOPT_POSTFIELDS => http_build_query($data),
        )
    );
    $response = curl_exec($ch);
    return json_decode($response);
}

function get($key, $default=null)
{
    return array_key_exists($key, $_GET) ? $_GET[$key] : $default;
}

function session($key, $default=null)
{
    return array_key_exists($key, $_SESSION) ? $_SESSION[$key] : $default;
}

function generatePasswordCode($length = 16)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@!&+-';
    $verification_code = '';
    for ($i = 0; $i < $length; $i++) {
        $verification_code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $verification_code;
}
?>