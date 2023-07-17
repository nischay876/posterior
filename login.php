<?php
        session_start();
        require 'src/db/psql.php';

use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\PHPMailer;

require 'vendor/autoload.php';  // Make sure to include the PHPMailer library

//if (!session('access_token')) {
//    header('Location: ' . $_SERVER['PHP_SELF'] . '?action=login');
//}

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'src/ratelimit.php';

define('OAUTH2_CLIENT_ID', $_ENV['OAUTH2_CLIENT_ID']);
define('OAUTH2_CLIENT_SECRET', $_ENV['OAUTH2_CLIENT_SECRET']);

$authorizeURL = 'https://ptb.discord.com/api/oauth2/authorize';
$tokenURL = 'https://ptb.discord.com/api/oauth2/token';
$apiURLBase = 'https://ptb.discord.com/api/users/@me';
$revokeURL = 'https://ptb.discord.com/api/oauth2/token/revoke';

// When Discord redirects the user back here, there will be a "code" and "state" parameter in the query string

if (get('action') == 'discord') {

    $params = array(
    'client_id' => OAUTH2_CLIENT_ID,
    'redirect_uri' => $_ENV['DASHBOARD_DOMAIN'] . '/login',
    'response_type' => 'code',
    'scope' => 'identify email'
    );

    // Redirect the user to Discord's authorization page
    header('Location: https://ptb.discord.com/api/oauth2/authorize' . '?' . http_build_query($params));
    die();
}

if (get('code')) {

    // Exchange the auth code for a token
    $token = apiRequest(
        $tokenURL, array(
        "grant_type" => "authorization_code",
        'client_id' => OAUTH2_CLIENT_ID,
        'client_secret' => OAUTH2_CLIENT_SECRET,
        'redirect_uri' => $_ENV['DASHBOARD_DOMAIN'] . '/login',
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

function generateVerificationCode($length = 16)
{
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ@!&+-';
    $verification_code = '';
    for ($i = 0; $i < $length; $i++) {
        $verification_code .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $verification_code;
}

    require __DIR__ . '/vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    
if (isset($_POST['login'])) {

    include 'src/turnstile.php';

    if ($outcome['success']) {
        include 'src/db/psql.php';

        if (isset($_POST['login'])) {
            $usernameOrEmail = $_POST['username_or_email'];
            $password = $_POST['password'];
    
            // Sanitize user input to prevent SQL injection
            $usernameOrEmail = htmlspecialchars($usernameOrEmail, ENT_QUOTES, 'UTF-8');
    
            $stmt = $conn->prepare('SELECT * FROM users WHERE username = :username OR email = :email');
            $stmt->bindParam(':username', $usernameOrEmail);
            $stmt->bindParam(':email', $usernameOrEmail);
            $stmt->execute();
    
            $user = $stmt->fetch();
    
            if ($stmt->rowCount() === 1) {
                if (password_verify($password, $user['password'])) {
                    if ($user['suspended'] == 1) {
                        $error = 'Your account is suspended. Please feel free to join the Discord server for support <a href='. $_ENV['DISCORD_SERVER'] . '>discord.gg</a>.';
                        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                        header('Location: login');
                        exit;
                    }
    
                    if ($user['is_verified'] == 1) {
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
                            $mail->addAddress($user['email']);
                       
                            // Email content
                            // Read the email template HTML file
                            $templateFilePath = __DIR__ . '/email_templates/new_login.html';
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
                            $templateContent = str_replace('{email_discord}', 'email', $templateContent);

                            // Email content
                            $mail->isHTML(true);
                            $mail->Subject = 'IMG3 - Successful Login';
                            $mail->AltBody = $reset_link;
                            $mail->Body = $templateContent;

                            // Send the email
                            $mail->send();
                            // Set the session and timestamp
                            $_SESSION['user_id'] = $user['id'];
                            $_SESSION['timestamp'] = time();  // Store the current timestamp
    
                            // Set the cookie with a 7-day expiration time
                            $cookie_name = 'user_id';
                            $cookie_value = $user['id'];
                            $cookie_expiration = time() + (7 * 24 * 60 * 60);  // 7 days
                            setcookie($cookie_name, $cookie_value, $cookie_expiration, '/');
                            $error = 'Successfully Logged in as: ' . $user['email'];
                            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                            header('Location: /');
                            exit();
                        } catch (Exception $e) {
                            $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                            header('Location: login');
                            exit;    
                        }
                    } else {
                        $error = 'Your account is not verified. Please check your email for the verification link';
                        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                        header('Location: login');
                        exit;
                    }
                } else {
                    $error = 'Invalid password';
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: login');
                    exit;
                }
            } else {
                $error = 'Invalid username or email';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: login');
                exit;
            }
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: login');
        exit;
    }
} else if (isset($_POST['loginwd'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) { 

        if (isset($_POST['loginwd'])) {

            $user = apiRequest($apiURLBase);
            unset($_SESSION['access_token']);
                // Check if username already exists
                $check_query = "SELECT * FROM users WHERE email = :email";
                $check_stmt = $conn->prepare($check_query);
                $check_stmt->bindParam(':email', $user->email);
                $check_stmt->execute();
        
            if ($check_stmt->rowCount() > 0) {
                $email = $user->email;
                $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $user['id'];
                $_SESSION['user_id'] = $user_id;
                $_SESSION['timestamp'] = time();
                $cookie_name = 'user_id';
                $cookie_value = $user_id;
                $cookie_expiration = time() + (7 * 24 * 60 * 60); // 1 week
                setcookie($cookie_name, $cookie_value, $cookie_expiration, '/');


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
                    $mail->addAddress($user['email']);
               
                    // Email content
                    // Read the email template HTML file
                    $templateFilePath = __DIR__ . '/email_templates/new_login.html';
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
                    $templateContent = str_replace('{email_discord}', 'discord', $templateContent);

                    // Email content
                    $mail->isHTML(true);
                    $mail->Subject = 'IMG3 - Successful Login';
                    $mail->AltBody = $reset_link;
                    $mail->Body = $templateContent;

                    // Send the email
                    $mail->send();
                    header("Location: /");
                    exit();
                } catch (Exception $e) {
                    $error = 'Email could not be sent. Error: ' . $mail->ErrorInfo;
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: login');
                    exit;    
                }
            } else {
                // Username already exists, handle the error
                $error = 'Discord Email Not Fount In Database';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: login');
                exit;
            }

        }

    }
}
?>

<?php
if (isset($_SESSION['user_id'])) {
    header('Location: /');
    exit();
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Login</title>
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    <link href=/assets/css/form.css rel=stylesheet>
</head>
<body>
<div class="container">
<?php    
if (session('access_token')) {
    ?>
    <h1>Login With Discord</h1>
    <form action="login" method="POST">
        <center><div class="cf-turnstile" data-theme="dark" data-action="loginwd" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
        <button type="submit" name="loginwd">Continue</button>
    </form>
    <?php
} else {
    ?>
        <h1>Login</h1>
        <form action="login" method="POST">
            <input type="text" name="username_or_email" placeholder="Username or Email" required>
            <input type="password" name="password" placeholder="Password" required>
            <center><div class="cf-turnstile" data-theme="dark" data-action="login" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="login">Login</button>
            <button type="button" class="login-button" onclick="location.href='?action=discord'">
    <span class="button-text">Login with Discord</span>
    <span class="icon-container">
        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-discord" viewBox="0 0 16 16">
            <path d="M13.545 2.907a13.227 13.227 0 0 0-3.257-1.011.05.05 0 0 0-.052.025c-.141.25-.297.577-.406.833a12.19 12.19 0 0 0-3.658 0 8.258 8.258 0 0 0-.412-.833.051.051 0 0 0-.052-.025c-1.125.194-2.22.534-3.257 1.011a.041.041 0 0 0-.021.018C.356 6.024-.213 9.047.066 12.032c.001.014.01.028.021.037a13.276 13.276 0 0 0 3.995 2.02.05.05 0 0 0 .056-.019c.308-.42.582-.863.818-1.329a.05.05 0 0 0-.01-.059.051.051 0 0 0-.018-.011 8.875 8.875 0 0 1-1.248-.595.05.05 0 0 1-.02-.066.051.051 0 0 1 .015-.019c.084-.063.168-.129.248-.195a.05.05 0 0 1 .051-.007c2.619 1.196 5.454 1.196 8.041 0a.052.052 0 0 1 .053.007c.08.066.164.132.248.195a.051.051 0 0 1-.004.085 8.254 8.254 0 0 1-1.249.594.05.05 0 0 0-.03.03.052.052 0 0 0 .003.041c.24.465.515.909.817 1.329a.05.05 0 0 0 .056.019 13.235 13.235 0 0 0 4.001-2.02.049.049 0 0 0 .021-.037c.334-3.451-.559-6.449-2.366-9.106a.034.034 0 0 0-.02-.019Zm-8.198 7.307c-.789 0-1.438-.724-1.438-1.612 0-.889.637-1.613 1.438-1.613.807 0 1.45.73 1.438 1.613 0 .888-.637 1.612-1.438 1.612Zm5.316 0c-.788 0-1.438-.724-1.438-1.612 0-.889.637-1.613 1.438-1.613.807 0 1.451.73 1.438 1.613 0 .888-.631 1.612-1.438 1.612Z"/>
        </svg>
    </span>
</button>
            <button class="login-button" type="button" onclick="location.href='passwordless'">Login with magic link</button>
            <button class="login-button" type="button" onclick="location.href='register'">Register</button>
            <button class="login-button" type="button" onclick="location.href='reset_password'">Forgot password?</button>
        </form>

        <?php
}
?>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div class="error-message">' . $_COOKIE['error'] . '</div></center>';
            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>
    </div>
</body>
</html>