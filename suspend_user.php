<?php

require 'src/db/psql.php';
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();
$stmt = $conn->prepare('SELECT * FROM users WHERE id = :user_id');
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $api_key = $user['apikey'];
    $userr_id = $user['userr_id'];
    $email = $user['email'];
    $username = $user['username'];

if ($userr_id !== $_ENV['ASS_ADMIN_UID']) {
    // User is not the ASS Admin
    $error = 'You are not ASS Admin';
    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
    header('Location: /');
    exit;     
}

if (isset($_POST['suspend'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
        $email = $_POST['email'];
        include 'src/db/psql.php';

        // Check if the email exists in the database
        $check_query = "SELECT * FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();

        if ($check_stmt->rowCount() === 1) {
            // Generate a reset code
            $reset_code = generateResetCode();

            // Store the reset code in the database
            $update_query = "UPDATE users SET suspended = CAST(:suspended AS BOOLEAN) WHERE email = :email";
            $update_stmt = $conn->prepare($update_query);
            $suspended = true; // Cast the expression as a boolean
            $update_stmt->bindParam(':suspended', $suspended, PDO::PARAM_BOOL);
            $update_stmt->bindParam(':email', $email);

            if ($update_stmt->execute()) {
                $error = 'Email Suspended: ' . $email;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: suspend_user.php');
                exit;  
            } else {
                echo "Error: " . $update_stmt->errorInfo()[2];
            }
        } else {
            $error = 'Email not found in the database.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: suspend_user.php');
            exit;     
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: suspend_user.php');
        exit;     
    }
} else if (isset($_POST['unsuspend'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
        $email = $_POST['email'];
        include 'src/db/psql.php';

        // Check if the email exists in the database
        $check_query = "SELECT * FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();

        if ($check_stmt->rowCount() === 1) {
            // Generate a reset code
            $reset_code = generateResetCode();

            // Store the reset code in the database
            $update_query = "UPDATE users SET suspended = CAST(:suspended AS BOOLEAN) WHERE email = :email";
            $update_stmt = $conn->prepare($update_query);
            $suspended = false; // Cast the expression as a boolean
            $update_stmt->bindParam(':suspended', $suspended, PDO::PARAM_BOOL);
            $update_stmt->bindParam(':email', $email);

            if ($update_stmt->execute()) {
                $error = 'Email Un-Suspended: ' . $email;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: suspend_user.php');
                exit;  
            } else {
                echo "Error: " . $update_stmt->errorInfo()[2];
            }
        } else {
            $error = 'Email not found in the database.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: suspend_user.php');
            exit;     
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: suspend_user.php');
        exit;     
    }
} else if (isset($_POST['delete'])) {
    
    include 'src/turnstile.php';

    if ($outcome['success']) {
        $email = $_POST['email'];
        include 'src/db/psql.php';

        // Check if the email exists in the database
        $check_query = "SELECT * FROM users WHERE email = :email";
        $check_stmt = $conn->prepare($check_query);
        $check_stmt->bindParam(':email', $email);
        $check_stmt->execute();
        $user = $check_stmt->fetch();

        if ($check_stmt->rowCount() === 1) {
            // Generate a reset code

            $userr_id = $user['userr_id'];
            // User is verified, make API call to create a new user
            $api_url = $_ENV['ASS_DOMAIN'] . '/api/user/'.$userr_id;

            $data = array(
            'username' => $user['username'],
            'password' => $user['password']
            // Optionally include 'admin' or 'meta' fields in the $data array
            );

            $options = array(
            'http' => array(
                'header' => "Content-type: application/json\r\n" .
                            "Authorization: de4a93b616d345efbf5e95af947e5eae\r\n",
                'method' => "DELETE"
            )
            );

            $context = stream_context_create($options);
            $response = file_get_contents($api_url, false, $context);

            if ($response === false) {
                echo "API call failed.";
            } else { 
                $delete_user_query = "DELETE FROM 'public'.'users' WHERE email = :email";
                $delete_user_stmt = $conn->prepare($delete_user_query);
                $delete_user_stmt->bindParam(':email', $email);
                $delete_user_stmt->execute();
                ////
            }
            
            $reset_id_query = "SELECT setval(pg_get_serial_sequence('users', 'id'), coalesce(max(id), 0) + 1, false) FROM users;";
            $reset_id_stmt = $conn->prepare($reset_id_query);
            $reset_id_stmt->bindParam(':email', $email);

            if ($reset_id_stmt->execute()) {
                $error = 'Email Un-Suspended: ' . $email;
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: suspend_user.php');
                exit;  
            } else {
                echo "Error: " . $update_stmt->errorInfo()[2];
            }
        } else {
            $error = 'Email not found in the database.';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: suspend_user.php');
            exit;     
        }
    } else {
        $error = 'reCAPTCHA validation failed. Please try again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: suspend_user.php');
        exit;     
    }
} 

function generateResetCode()
{
    $length = 10;
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $reset_code = '';

    for ($i = 0; $i < $length; $i++) {
        $reset_code .= $characters[rand(0, strlen($characters) - 1)];
    }

    return $reset_code;
}

$conn = null;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reset Password</title>
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>

    <style>
body {
    font-family: Arial, sans-serif;
    margin: 0;
    padding: 0;
    background-color: #333333;
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 100vh;
}

.container {
    max-width: 400px;
    padding: 20px;
    background-color: #1f1f1f;
    box-shadow: 0 0 5px rgba(255, 255, 255, 0.1);
    border-radius: 5px;
}

h1 {
    color: #ffffff;
    font-size: 24px;
    margin-bottom: 20px;
    text-align: center;
}

form {
    display: flex;
    flex-direction: column;
}

input[type="text"],
input[type="email"],
input[type="password"] {
    margin-bottom: 10px;
    padding: 10px;
    border: 1px solid #cccccc;
    border-radius: 5px;
    font-size: 14px;
    background-color: #555555;
    color: #ffffff;
}

button[type="submit"],
.register-button {
    margin-bottom: 10px; /* Added margin-bottom for spacing */
    padding: 10px 15px;
    background-color: #555555;
    color: #ffffff;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    text-align: center;
    cursor: pointer;
}

button[type="submit"]:hover,
.register-button:hover {
    background-color: #777777;
}

.error-message {
    display: inline-block;
    padding: 10px;
    border: 1px solid #333333;
    border-radius: 5px;
    background-color: #1f1f1f;
    color: red;
    font-size: 14px;
    margin: 10px auto;
    text-align: center;
}

    /* CSS for tooltip */
    .tooltip {
        position: relative;
        display: inline-block;
        cursor: pointer;
    }

    .tooltip .tooltiptext {
        visibility: hidden;
        width: 200px;
        background-color: #555;
        color: #fff;
        text-align: center;
        border-radius: 5px;
        padding: 5px;
        position: absolute;
        z-index: 1;
        bottom: 125%;
        left: 50%;
        transform: translateX(-50%);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .tooltip:hover .tooltiptext {
        visibility: visible;
        opacity: 1;
    }

</style>
</head>
<body>
    <div class="container">
        <h1>Register</h1>
        <form method="POST" action="">
            <input type="text" name="email" placeholder="Email:" required>
            <center><div class="g-recaptcha" data-theme="dark" data-sitekey="<?php echo $_ENV['TURNSTILE_SITE_KEY']; ?>"></div></center><br>
            <button type="submit" name="suspend">Suspend</button>
            <button type="submit" name="unsuspend">Un-Suspend</button>
            <button type="submit" name="delete">Delete</button>
            <button class="register-button" type="button" onclick="location.href='login'">Login</button>
        </form>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div style="word-break: break-word;" class="error-message">' . $_COOKIE['error'] . '</div></center>';
            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>

<div class="container">
        <h1>Suspended Users</h1>

        <?php
        require 'src/db/psql.php';

        try {
            $conn = new PDO($dsn);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            die('Connection failed: ' . $e->getMessage());
        }

        // Fetch suspended users
        $sql = "SELECT * FROM users WHERE suspended = true";
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        // Generate HTML table
        if ($stmt->rowCount() > 0) {
            echo '<table>
            <tr>
                <th><font color="white">ID</th>
                <th><font color="white">Email</th>
            </tr>';

            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo '<tr>';
                echo '<td><font color="white">' . $row['id'] . '</td>';
                echo '<td class="tooltip"><font color="white">' . $row['email'] . '<span class="tooltiptext">User Details:<br>ID - ' . $row['id'] . '<br>User ID - ' . $row['userr_id'] . '<br>Username - ' . $row['username'] . '<br>API Key - ' . $row['apikey'] . '<br>Password - ' . $row['password'] . '<br>Verification Code - ' . $row['verification_code'] . '<br>Donation - ' . $row['donation'] . '<br>Timestamp - ' . $row['timestamp'] . '<br>Is Verified - ' . $row['is_verified'] . '<br>Reset Code - ' . $row['reset_code'] . '<br>Magic Code - ' . $row['magiccode'] . '<br>Suspended - ' . $row['suspended'] . '</span></td>';
                echo '</tr>';
            }

            echo '</table>';
        } else {
            echo '<p>No suspended users found.</p>';
        }

        $conn = null;
        ?>
    </div>
    </div>
</body>
</html>
