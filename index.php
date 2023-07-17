<?php
session_start();

require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

require 'src/ratelimit.php';
require 'src/db/psql.php';

if (!isset($_SESSION['user_id']) || (time() - $_SESSION['timestamp']) > (7 * 24 * 60 * 60)) {
    // User is not logged in or session has expired
    // Clear session variables and destroy the session
    session_unset();
    session_destroy();

    // Redirect to the login page
    header('Location: login');
    exit();
}

require 'src/db/psql.php';

$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $conn->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

$user = $stmt->fetch(PDO::FETCH_ASSOC);
$api_key = $user['apikey'];
$userr_id = $user['userr_id'];

if (!$user['userr_id']) {
    // Retrieve user ID (unid) from the API
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $_ENV['ASS_DOMAIN'] . '/api/user/token/' . $api_key);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, true);
    $userr_id = $data['unid'];

    $update_userr_id_sql = "UPDATE users SET userr_id = :userr_id WHERE id = :user_id";
    $update_stmt = $conn->prepare($update_userr_id_sql);
    $update_stmt->bindParam(':userr_id', $userr_id);
    $update_stmt->bindParam(':user_id', $user_id);
    $update_stmt->execute();
}

$stmt = $conn->prepare('SELECT * FROM users WHERE id = :user_id');
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

if ($stmt->rowCount() === 1) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $api_key = $user['apikey'];
    $userr_id = $user['userr_id'];
    $email = $user['email'];
    $username = $user['username'];

    if ($user['suspended'] == 1) {
        $error = 'Your account is suspended. Please feel free to join the Discord server for support <a href='. $_ENV['DISCORD_SERVER'] . '>discord.gg</a>.';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: login');
        exit;
    }

    try {
        $conn = new PDO($dsn);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $sql = "SELECT COUNT(*) AS total_rows FROM ass WHERE jsonb_extract_path_text(data, 'uploader') = '$userr_id'";
    
        $result = $conn->query($sql);
        $row = $result->fetch(PDO::FETCH_ASSOC);
        $totalRows = $row['total_rows'];

        
        $sql = "SELECT data->>'size' AS size FROM ass WHERE jsonb_extract_path_text(data, 'uploader') = '$userr_id'";

        $result = $conn->query($sql);
        $rows = $result->fetchAll(PDO::FETCH_ASSOC);

        $totalSize = 0;
        foreach ($rows as $row) {
            $totalSize += $row['size'];
        }
    } catch (PDOException $e) {
        $error = "Psql Connection failed: " . $e->getMessage();
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        //header('Location: /');//
        exit; 
    };

    // File Upload Handling
    if (isset($_FILES['file'])) {
        $file = $_FILES['file'];

        // Check for errors during file upload
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            $apiUrl = "http://ip-api.com/json/$ip";
            $apiResponse = file_get_contents($apiUrl);
            $apiData = json_decode($apiResponse, true);
            // Check if the API call was successful
            if ($apiData['status'] === 'success') {
                $timezone = $apiData['timezone'];
            } else {
                $timezone = 'UTC+0';
            }
            $allowedTypes = array('image/jpeg', 'image/png', 'video/mp4');
            if (!in_array($file['type'], $allowedTypes)) {
                $error = 'Invalid file type. Only JPEG, PNG images, and MP4 videos are allowed.';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: /');
                exit; 
            }
            // Check file size
            $maxFileSize = 99 * 1024 * 1024; // 10MB in bytes
            if ($file['size'] > $maxFileSize) {
                $error = 'The file size exceeds the maximum limit of 25MB. For larger files up to 100MB, please consider using ShareX.';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: /');
                exit; 
            }
        
            // Set the destination URL to your ShareX endpoint
            $uploadUrl = $_ENV['ASS_DOMAIN'];

            // Prepare the file for uploading
            $postData = array(
                'file' => new CURLFile($file['tmp_name'], $file['type'], $file['name'])
            );

            // Create a cURL request
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            // Set the required headers
            $headers = array(
                'Authorization: ' . $api_key,
                //'X-Domain: ' . str_replace("https://", "", $_ENV['ASS_DOMAIN']),
                'X-Timeoffset: ' . $timezone,
            );

            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

            // Execute the request
            $response = curl_exec($ch);

            // Check if the request was successful
            if ($response !== false) {
                // Decode the JSON response
                $responseData = json_decode($response, true);

                // Check if the upload was successful
                if (isset($responseData['resource'])) {
                    $resourceUrl = $responseData['resource'];
                    $thumbnailUrl = $responseData['thumbnail'];
                    $deletionUrl = $responseData['delete'];
                    $successData = array(
                        'resourceUrl' => $resourceUrl,
                        'thumbnailUrl' => $thumbnailUrl,
                        'deletionUrl' => $deletionUrl
                    );
                    setcookie('success_upload', json_encode($successData), time() + 600, '/');
                    header('Location: success.php');
                    exit();
                } else {
                    // Display the error message
                    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $error = 'Upload failed: ' . $statusCode . '';
                    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                    header('Location: /');
                    exit; 
                }
            } else {
                // Display the cURL error, if any
                $error = 'Upload failed2: ' . curl_error($ch) . '';
                setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
                header('Location: /');
                exit; 
            }

            // Close the cURL session
            curl_close($ch);
        } else {
            // Display the file upload error
            $error = 'Upload failed3: ' . $file['error'] . '';
            setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
            header('Location: /');
            exit; 
        }
    }
    ?>

<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <link href=/assets/css/index.css rel=stylesheet>
    <script>
        function copyCode() {
            var code = document.getElementById("code").textContent;
            navigator.clipboard.writeText(code)
                .then(function() {
                    alert("Code copied to clipboard!");
                })
                .catch(function() {
                    alert("Failed to copy code to clipboard!");
                });
        }
    </script>
</head>
<body>
    <div class="container">
        <h1>Welcome!</h1>
        <?php
        if (isset($_COOKIE['error'])) {
            echo '<center><div style="word-break: break-word;" class="error-message">' . $_COOKIE['error'] . '</div></center>';
            setcookie('error', '', time() - 3600); // Clear the error cookie
        }
        ?>
        <p>You are logged in as <b><?php echo $email . ', ' . $username; ?></b></p>
        <p>API key: <b><?php echo $api_key; ?></b></p>
        <p>User ID: <b><?php echo $userr_id; ?></b></p>
        <p>Total Files: <b><?php echo $totalRows; ?></b></p>
        <?php
        // Convert the total size to MB, KB, or GB
        if ($totalSize < 1000) { // Size less than 1 KB
            $totalSizeFormatted = number_format($totalSize, 2);
            echo "<p>Total Files: <b>" . $totalSizeFormatted . " bytes</b></p>";
        } elseif ($totalSize < 1000000) { // Size less than 1 MB
            $totalSizeKB = number_format($totalSize / 1000, 2);
            echo "<p>Total Files: <b>" . $totalSizeKB . " KB</b></p>";
        } else { // Size greater than or equal to 1 MB
            if ($totalSize < 1000000000) { // Size less than 1 GB
                $totalSizeMB = number_format($totalSize / 1000000, 2);
                echo "<p>Total Files: <b>" . $totalSizeMB . " MB</b></p>";
            } else { // Size greater than or equal to 1 GB
                $totalSizeGB = number_format($totalSize / 1000000000, 2);
                echo "<p>Total Files: <b>" . $totalSizeGB . " GB</b></p>";
            }
        }
        ?>
        <p>User Created: <b><?php echo date('m/d/Y', $user['timestamp']); ?></b></p>
        <a href="logout" class="logout-button">Logout</a>

        <h3>Upload a File:</h3>
        <form action="/" method="POST" enctype="multipart/form-data" class="upload-form">
    <label for="fileInput" class="upload-button">Upload File</label>
    <input type="file" name="file" id="fileInput" required onchange="submitForm()">
</form>

<script>
function submitForm() {
    document.getElementsByClassName('upload-form')[0].submit();
}

function showSelectedFile() {
    var fileInput = document.getElementById('fileInput');
    var fileNameSpan = document.getElementById('fileName');
    fileNameSpan.textContent = fileInput.files[0].name;
}
</script>

        <a href="/fm/images" class="download-button">Manage Images</a>
        <a href="/fm/videos" class="download-button">Manage Videos</a>
        <a href="/dl?key=<?php echo $api_key; ?>" class="download-button">Download ShareX Config File</a>
                <h3>ShareX Config Code:</h3>
                <div class="code-container">
<pre id="code">{
  "Version": "14.0.1",
  "DestinationType": "ImageUploader, TextUploader, FileUploader",
  "RequestMethod": "POST",
  "RequestURL": "<?php echo $_ENV['ASS_DOMAIN']; ?>",
  "Headers": {
    "Authorization": "<?php echo $api_key; ?>",
    "X-OG-Title": null,
    "X-OG-Description": null,
    "X-Webhook-Url": null,
    "X-OG-Author": null,
    "X-OG-Author-Url": null,
    "X-OG-Provider": null,
    "X-OG-Provider-Url": null,
    "X-OG-Color": "#2f3136",
    "X-Domain": "<?php echo str_replace("https://", "", $_ENV['ASS_DOMAIN']); ?>"
  },
  "Body": "MultipartFormData",
  "FileFormName": "file",
  "URL": "{json:.resource}",
  "ThumbnailURL": "{json:.thumbnail}",
  "DeletionURL": "{json:.delete}",
  "ErrorMessage": "{response}"
}</pre><button class="copy-button" onclick="copyCode()">Copy</button>
</div>
</body>
</html>
    <?php
} else {
    echo 'Error fetching user details. try logging in again';
    echo '<a href="/logout">logout</a>';
}
?>

<?php
if ($_ENV['DONATION'] === 'true') {
    // Perform the desired action here
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

  body {
    font-family: Arial, sans-serif;
  }

  .overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 9999;
  }

  .modal {
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.3);
    max-width: 400px;
    width: 100%;
    text-align: center;
    position: relative;
  }

  .modal h2 {
    margin-top: 0;
  }

  .modal p {
    margin-bottom: 20px;
  }

  .close-btn {
    position: absolute;
    top: 10px;
    right: 10px;
    background-color: transparent;
    border: none;
    font-size: 20px;
    color: #888;
    cursor: pointer;
  }

  .donation-form {
    display: flex;
    justify-content: center;
    align-items: center;
    flex-wrap: wrap;
    margin-top: 20px;
  }

  .donation-option {
    margin: 10px;
  }

  .donation-option label {
    display: flex;
    align-items: center;
  }

  .donation-option input[type="radio"] {
    margin-right: 5px;
  }

  .custom-amount {
    margin-top: 10px;
  }

  .custom-amount input[type="number"] {
    width: 100%;
    padding: 5px;
    margin-top: 5px;
    border: 1px solid #ccc;
    border-radius: 4px;
  }

  .submit-btn {
    margin-top: 20px;
    background-color: #4caf50;
    color: #fff;
    border: none;
    padding: 10px 20px;
    border-radius: 4px;
    cursor: pointer;
  }
</style>
<div class="overlay" id="modal-overlay">
  <div class="modal">
    <button class="close-btn" onclick="closeModal()">&times;</button>
    <h2>Make a Donation</h2>
    <p>Choose an amount:</p>
    <form class="donation-form" onsubmit="submitForm(event)">
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="3" checked />
          $3
        </label>
      </div>
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="5" />
          $5
        </label>
      </div>
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="10" />
          $10
        </label>
      </div>
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="25" />
          $25
        </label>
      </div>
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="50" />
          $50
        </label>
      </div>
      <div class="donation-option">
        <label>
          <input type="radio" name="donation-amount" value="100" />
          $100
        </label>
      </div>
      <input
        type="email"
        name="email"
        placeholder="Enter your email"
        required
      />
      <button class="submit-btn" type="submit">Submit</button>
    </form>
  </div>
</div>

<script>
  var modalOverlay = document.getElementById("modal-overlay");

  function openModal() {
    modalOverlay.style.display = "flex";
  }

  function closeModal() {
    modalOverlay.style.display = "none";
  }

  function submitForm(event) {
    event.preventDefault();
    var donationAmount = document.querySelector(
      'input[name="donation-amount"]:checked'
    ).value;
    var email = document.querySelector('input[name="email"]').value;
    var url =
      "https://webhuuiikk.vercel.app/url?value=" +
      donationAmount +
      "&email=" +
      email;
    window.location.href = url;
  }

  // Check if URL contains #donate and open modal
  if (window.location.hash === "#donate") {
    openModal();
  }
</script>
<!-- Discord icon -->
<a href="#" onclick="openModal()" class="discord-icon2">
  <img
    src="https://cdn-icons-png.flaticon.com/512/6203/6203343.png"
    alt="Discord Icon"
    width="50"
  />
</a>
<?php
}
?>