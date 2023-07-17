<!DOCTYPE html>
<html>

<head>
    <title>Browse Images</title>
    <link href=/assets/css/fm.css rel=stylesheet>
</head>

<body>
<div class="homepage-button">
    <a href="/logout">Logout</a>
    <a href="/">Homepage</a>
    <a href="videos">View Videos</a>
    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="refresh-button">Refresh</a>

    <select id="sort-dropdown" onchange="updateSortType(this.value)">
    <?php
    if ($_COOKIE['sortType'] === 'oldest') {
        echo '<option value="latest">Sort by Latest</option>';
        echo '<option value="oldest" selected>Sort by Oldest</option>';
    } else {
        echo '<option value="latest" selected>Sort by Latest</option>';
        echo '<option value="oldest">Sort by Oldest</option>';
    }
    ?>
    </select>
</div>

    <div class="gallery">
    <?php
    require __DIR__ . '/../vendor/autoload.php';
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
    $dotenv->load();

        session_start();
        require '../src/ratelimit.php';

    if (!isset($_SESSION['user_id']) || (time() - $_SESSION['timestamp']) > (7 * 24 * 60 * 60)) {
        // User is not logged in or session has expired
        // Clear session variables and destroy the session
        session_unset();
        session_destroy();

        // Redirect to the login page
        header('Location: login');
        exit();
    }

    require '../src/db/psql.php';

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
    
        if ($update_stmt->execute()) {
            // Update successful
        } else {
            echo "Error: " . $update_stmt->errorInfo()[2];
        }
    }
        //echo $userr_id;
        require '../src/db/psql.php';

    try {
        $pdo = new PDO($dsn);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // Fetch images with uploader = user ID and is.image = true
        $sortType = $_COOKIE['sortType'] ?? 'latest';
        $sortOrder = ($sortType === 'latest') ? 'DESC' : 'ASC';  
        $stmt = $pdo->prepare("SELECT id, data FROM ass WHERE jsonb_extract_path_text(data, 'uploader') = '$userr_id' AND jsonb_extract_path_text(data, 'is', 'image') = 'true' ORDER BY jsonb_extract_path_text(data, 'timestamp') $sortOrder;");
        //$stmt = $pdo->prepare("SELECT id, data FROM ass WHERE jsonb_extract_path_text(data, 'uploader') = '$userr_id' AND jsonb_extract_path_text(data, 'is', 'image') = 'true';");
        $stmt->execute();
        $hasUploads = false;
        // Iterate over the rows and display the images
        // Iterate over the rows and display the images
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $id = $row['id'];
            $data = json_decode($row['data']);
            $deleteId = $data->deleteId;

            // Output the image container and button container for each image
            echo '<div class="image-container">';
            //echo '<a>' . date('m/d/Y H:i:s', $data->timestamp) . '</a>';
            echo '<img src="'. $_ENV['ASS_DOMAIN'] . '/' . $id . '/thumbnail" data-modal-src="'. $_ENV['ASS_DOMAIN'] . '/' . $id . '.png" alt="' . $data->originalname . '">';
            echo '<div class="button-container">';
            echo '<button class="view-button">👀</button>';
            echo '<button class="delete-button"><a href="#" onclick="confirmDelete(\'' . $id . '\', \'' . $deleteId . '\')">🗑️</a></button>';
            //echo '<button class="delete-button"><a href="'. $_ENV['ASS_DOMAIN'] . '' . $id . '/delete/' . $deleteId . '" target="_blank">🗑️</a></button>';
            echo '<button class="info-button" data-info=\'' . json_encode($data) . "'>📖</button>";  // Added info button
            echo '<button><a target="_blank" href="'. $_ENV['ASS_DOMAIN'] . '/' . $id . '">↗️</a></button>';
            echo '</div>';
            echo '</div>';
            $hasUploads = true;
        }

        if (!$hasUploads) {
            echo '<p>0 Images Uploaded.</p>';
        }
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
    }
    ?>
    </div>

    <div class="modal">
        <div class="modal-content">
            <button class="modal-close">&times;</button>
            <img class="modal-image" src="" alt="Modal Image">
        </div>
    </div>

    <script>
// Define the sortImages function
function sortImages(sortType) {
    var gallery = document.querySelector('.gallery');
    var images = Array.from(gallery.getElementsByClassName('image-container'));

    // Sorting logic here...
}

// Define the updateSortType function
function updateSortType(sortType) {
    document.cookie = "sortType=" + sortType + "; path=/";
    sortImages(sortType);
    setTimeout(function() {
    window.location.reload();
}, 500);
}

// Retrieve and apply the stored sort type on page load
window.addEventListener('DOMContentLoaded', function() {
    var storedSortType = getCookie("sortType");
    if (storedSortType !== "") {
        document.getElementById("sort-dropdown").value = storedSortType;
        sortImages(storedSortType);
    }
});

// Function to retrieve the cookie value
function getCookie(name) {
    var cookies = document.cookie.split(';');
    for (var i = 0; i < cookies.length; i++) {
        var cookie = cookies[i].trim();
        if (cookie.indexOf(name) === 0) {
            return cookie.substring(name.length + 1);
        }
    }
    return "";
}

/////

function confirmDelete(id, deleteId) {
  if (confirm("Are you sure you want to delete?")) {
    // User confirmed, perform the GET request
    var xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function() {
      if (xhr.readyState === XMLHttpRequest.DONE) {
        if (xhr.status === 200) {
          // Request successful, refresh the page
          location.reload();
        }
      }
    };
    xhr.open("GET", '<?php echo $_ENV['ASS_DOMAIN']; ?>/' + id + "/delete/" + deleteId + '/confirm', true);
    xhr.send();
  }
}
</script>
    <script>
        // JavaScript code for handling button actions (e.g., delete and view)
        var viewButtons = document.querySelectorAll('.view-button');
        var deleteButtons = document.querySelectorAll('.delete-button');
        var modal = document.querySelector('.modal');
        var modalImage = document.querySelector('.modal-image');
        var modalCloseButton = document.querySelector('.modal-close');

        function handleView(event) {
            var imageSrc = event.target.parentNode.parentNode.querySelector('img').getAttribute('data-modal-src');
            modalImage.src = imageSrc;
            modal.classList.add('open');
        }

        var infoButtons = document.querySelectorAll('.info-button');

function handleInfo(event) {
    var imageData = JSON.parse(event.target.getAttribute('data-info'));
    var info = "Image Information:\n\n";
    info += "Name: " + imageData.originalname + "\n";
    if (imageData.size >= 1024 * 1024) {
    info += "Size: " + Math.floor(imageData.size / (1024 * 1024)) + " MB\n";
} else {
    info += "Size: " + Math.floor(imageData.size / 1024) + " KB\n";
}
    info += "Type: " + imageData.mimetype + "\n";
    info += "Uploaded by: " + imageData.uploader + "\n";
    info += "Upload Date: " + new Date(imageData.timestamp).toLocaleString() + "\n";
    info += "SHA1: " + imageData.sha1 + "\n";

    alert(info);
}

infoButtons.forEach(function (button) {
    button.addEventListener('click', handleInfo);
});
        function handleCloseModal() {
            modal.classList.remove('open');
            modalImage.src = '';
        }

        viewButtons.forEach(function (button) {
            button.addEventListener('click', handleView);
        });

        modalCloseButton.addEventListener('click', handleCloseModal);
    </script>
</body>

</html>
