<!DOCTYPE html>
<html>
<head>
  <title>Upload Successful</title>
  <link href=/assets/css/success.css rel=stylesheet>
</head>
<body>
  <div class="container">
    <?php 
    if (isset($_COOKIE['success_upload'])) { 
        echo '<h1 class="text-2xl font-bold">Upload Successful!</h1>';
    } else if (!isset($_COOKIE['success_upload'])) {
        $error = 'Upload Session Expired, visit manage images to view file again';
        setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
        header('Location: /');
        exit; 
    }
      // Retrieve the uploaded image URLs from the query parameters
    if (isset($_COOKIE['success_upload'])) {
        $successData = json_decode($_COOKIE['success_upload'], true);
        $resourceUrl = $successData['resourceUrl'];
        $thumbnailUrl = $successData['thumbnailUrl'];
        $deletionUrl = $successData['deletionUrl'];
        // Clear the success cookie
        //setcookie('success_upload', '', time() - 3600, '/');
    }
      // Display the uploaded image and its URLs
          // Check the status code of the thumbnailUrl response
    $thumbnailResponse = get_headers($thumbnailUrl);
    $thumbnailStatusCode = intval(substr($thumbnailResponse[0], 9, 3)); // Extract the status code
    
    // Display the uploaded image and its URLs
    if ($thumbnailStatusCode === 200) {
        echo '<img src="' . htmlspecialchars($thumbnailUrl) . '" alt="Uploaded Image" class="mb-4"><br>';
    } else {
        echo '<center><div style="word-break: break-word;" class="error-message">Your file is being processed and securely saved to the database. Kindly allow some time for the operation to complete successfully.</div></center>';
        echo '<meta http-equiv="refresh" content="2" > ';
    }
      echo '<p>Image URL: <a href="' . htmlspecialchars($resourceUrl) . '">' . htmlspecialchars($resourceUrl) . '</a></p>';
      echo '<p>Direct File URL: <a href="' . htmlspecialchars($resourceUrl) . '/direct' . '">' . htmlspecialchars($resourceUrl) . '/direct' . '</a></p>';
      echo '<p>Thumbnail URL: <a href="' . htmlspecialchars($thumbnailUrl) . '">' . htmlspecialchars($thumbnailUrl) . '</a></p>';
      echo '<p class="deletion-url">Deletion URL: <a href="' . htmlspecialchars($deletionUrl) . '">HIDDEN</a></p>';

      // echo '<p class="deletion-url">Deletion URL: <a href="' . htmlspecialchars($deletionUrl) . '">' . htmlspecialchars($deletionUrl) . '</a></p>';
    ?>

    <p><a href="/" class="text-blue-500">Upload Another Image</a></p>
  </div>
</body>
</html>
