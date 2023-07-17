<?php
require 'src/db/psql.php';
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

session_start();
$stmt = $conn->prepare('SELECT * FROM tusers WHERE id = :user_id');
$stmt->bindParam(':user_id', $_SESSION['user_id']);
$stmt->execute();

    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $api_key = $user['apikey'];
    $userr_id = $user['userr_id'];
    $email = $user['email'];
    $username = $user['username'];

//if ($userr_id !== $_ENV['ASS_ADMIN_UID']) {
    // User is not the ASS Admin
//    $error = 'You are not ASS Admin';
//    setcookie('error', $error, time() + 60, '/'); // Set the error cookie to expire in 1 minute
//    header('Location: /');
//    exit;     
//}

// Fetch suspended tusers

// Fetch suspended tusers
$currentPage = isset($_GET['page']) ? $_GET['page'] : 1;
$itemsPerPage = 50;
$offset = ($currentPage - 1) * $itemsPerPage;

$check_query = "SELECT * FROM tusers ORDER BY id ASC LIMIT $itemsPerPage OFFSET $offset";
$check_stmt = $conn->prepare($check_query);
$check_stmt->execute();

//$check_query = "SELECT * FROM tusers ORDER BY timestamp ASC";
//$check_stmt = $conn->prepare($check_query);
//$check_stmt->execute();
$results = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suspended Users</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.15/dist/tailwind.min.css">
    <style>
        body {
            background-color: #1a202c;
            color: #fff;
        }

        .tooltip {
            position: relative;
            cursor: pointer;
        }

        .tooltip .tooltip-text {
            visibility: hidden;
            background-color: rgba(0, 0, 0, 0.8);
            color: #fff;
            text-align: center;
            padding: 5px;
            border-radius: 4px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            transition: visibility 0s, opacity 0.5s linear;
        }

        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }

        .tooltip:hover .tooltip-text::selection {
            background-color: rgba(0, 0, 0, 0.3);
        }

        .tooltip .tooltip-text.double-click {
            cursor: pointer;
        }

        .table-divider td:not(:last-child) {
            border-right: 1px solid #718096;
        }

        .editable-cell {
            cursor: pointer;
        }

        .editable-cell input,
        .editable-cell select {
            background-color: transparent;
            border: none;
            color: inherit;
        }

        .editable-cell input:focus,
        .editable-cell select:focus {
            outline: none;
        }

        .editable-cell.editing input,
        .editable-cell.editing select {
            display: inline-block;
        }
        
.logout-button,
.homepage-button {
    text-align: center;
    margin-bottom: 20px;
}

.logout-button a,
.homepage-button a {
    display: inline-block;
    padding: 5px 10px;
    background-color: #3333338a;
    color: #fff;
    border: none;
    border-radius: 5px;
    text-decoration: none;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s;
}

.homepage-button select {
    display: inline-block;
    padding: 5px 10px;
    background-color: #3333338a;
    color: #fff;
    border: none;
    border-radius: 5px;
    font-size: 14px;
    cursor: pointer;
    transition: background-color 0.3s;
    z-index: 1;
}

.logout-button a:hover,
.homepage-button a:hover {
    background-color: #555;
}

.pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }

        .pagination button {
            padding: 5px 10px;
            margin: 0 5px;
            background-color: #3333338a;
            color: #fff;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .pagination button:hover {
            background-color: #555;
        }
        
    </style>
</head>

<div class="homepage-button">
    <a href="logout">Logout</a>
    <a href="/">Homepage</a>
    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="refresh-button">Refresh</a>
    </select>
</div>

<body>
    <div class="container mx-auto p-4">
        <div class="overflow-x-auto">
            <table class="w-full table-auto text-sm text-left table-divider">
                <thead class="text-xs bg-gray-900">
                    <tr>
                        <th class="px-4 py-2">ID</th>
                        <th class="px-4 py-2">User ID</th>
                        <th class="px-4 py-2">Username</th>
                        <th class="px-4 py-2">Email</th>
                        <th class="px-4 py-2">API Key</th>
                        <th class="px-4 py-2">Password</th>
                        <th class="px-4 py-2">Verification Code</th>
                        <th class="px-4 py-2">Timestamp</th>
                        <th class="px-4 py-2">Reset Code</th>
                        <th class="px-4 py-2">Magic Code</th>
                        <th class="px-4 py-2">Suspended</th>
                        <th class="px-4 py-2">Is Verified</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if (count($results) > 0) {
                        foreach ($results as $row) {
                            echo '<tr class="bg-gray-800">';
                            echo '<td class="px-4 py-2">' . $row['id'] . '</td>';
                            echo '<td class="px-4 py-2">' . ($row['userr_id'] !== null ? $row['userr_id'] : 'null') . '</td>';
                            echo '<td class="px-4 py-2">' . $row['username'] . '</td>';
                            echo '<td class="px-4 py-2">' . $row['email'] . '</td>';
                            echo '<td class="px-4 py-2 tooltip">' .
                                '<span class="tooltip-text">' . htmlspecialchars($row['apikey']) . '</span>' .
                                '</td>';
                            echo '<td class="px-4 py-2 tooltip">' .
                                '<span class="tooltip-text double-click" ondblclick="copyText(event)">' . htmlspecialchars($row['password']) . '</span>' .
                                '</td>';
                            echo '<td class="px-4 py-2">' . ($row['verification_code'] !== null ? $row['verification_code'] : 'null') . '</td>';
                            echo '<td class="px-4 py-2">' . $row['timestamp'] . '</td>';
                            echo '<td class="px-4 py-2">' . ($row['reset_code'] !== null ? $row['reset_code'] : 'null') . '</td>';
                            echo '<td class="px-4 py-2">' . ($row['magiccode'] !== null ? $row['magiccode'] : 'null') . '</td>';
                            echo '<td class="px-4 py-2 editable-cell" data-column="suspended" data-id="' . $row['id'] . '">' .
                                '<select onchange="updateValue(event)">' .
                                '<option value="0" ' . ($row['suspended'] == 0 ? 'selected' : '') . '>False</option>' .
                                '<option value="1" ' . ($row['suspended'] == 1 ? 'selected' : '') . '>True</option>' .
                                '</select>' .
                                '</td>';
                            echo '<td class="px-4 py-2 editable-cell" data-column="is_verified" data-id="' . $row['id'] . '">' .
                                '<select onchange="updateValue(event)">' .
                                '<option value="0" ' . ($row['is_verified'] == 0 ? 'selected' : '') . '>False</option>' .
                                '<option value="1" ' . ($row['is_verified'] == 1 ? 'selected' : '') . '>True</option>' .
                                '</select>' .
                                '</td>';
                            echo '</tr>';
                        }
                    } else {
                        echo '<tr><td colspan="12" class="px-4 py-2">No suspended tusers found.</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function copyText(event) {
            const text = event.target.innerText;
            navigator.clipboard.writeText(text).then(() => {
                console.log('Text copied:', text);
            });
        }

        function updateValue(event) {
            const select = event.target;
            const column = select.parentNode.getAttribute('data-column');
            const id = select.parentNode.getAttribute('data-id');
            const value = select.value;

            // Update the database value
            var xhttp = new XMLHttpRequest();
    xhttp.onreadystatechange = function() {
        if (this.readyState === 4 && this.status === 200) {
            console.log(this.responseText);
        }
    };
    xhttp.open('POST', 'view_users.php', true);
    xhttp.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
    xhttp.send('id=' + id + '&column=' + column + '&value=' + value);
}
    </script>

<?php
// Get the total number of records from the database
$count_query = "SELECT COUNT(*) as total FROM tusers";
$count_stmt = $conn->prepare($count_query);
$count_stmt->execute();
$totalRecords = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Calculate the total number of pages
$totalPages = ceil($totalRecords / $itemsPerPage);

// Determine the current page
$currentPage = isset($_GET['page']) ? intval($_GET['page']) : 1;
?>

<!-- Footer -->
<footer class="mt-4">
    <div class="container mx-auto">
        <ul class="flex justify-center space-x-4">
            <?php
            // Set the number of page links to display
            $numLinks = 10;

            // Calculate the start and end page numbers
            $startPage = max(1, $currentPage - floor($numLinks / 2));
            $endPage = min($startPage + $numLinks - 1, $totalPages);

            // Adjust the start page if necessary
            $startPage = max(1, $endPage - $numLinks + 1);

            // Display the page links
            if ($startPage > 1) {
                echo '<li><a href="?page=1" class="text-white">1</a></li>';
                echo '<li><span class="text-white">...</span></li>';
            }

            for ($i = $startPage; $i <= $endPage; $i++) {
                $isActive = ($i === $currentPage) ? ' active' : '';
                echo '<li><a href="?page=' . $i . '" class="text-white' . $isActive . '">' . $i . '</a></li>';
            }
            if ($endPage < $totalPages) {
                if ($endPage + 1 !== $totalPages) {
                    echo '<li><span class="text-white">...</span></li>';
                }
                echo '<li><input type="number" placeholder="'. '1'. '-' . $totalPages .'" class="custom-page-input" min="' . ($endPage + 1) . '" max="' . $totalPages . '"></li>';
                echo '<li><button class="go-to-page-btn">Go</button></li>';
            }
            ?>
        </ul>
    </div>
</footer>

<script>
    const goToPageBtn = document.querySelector('.go-to-page-btn');
    const customPageInput = document.querySelector('.custom-page-input');

    goToPageBtn.addEventListener('click', () => {
        const customPage = parseInt(customPageInput.value);
        if (!isNaN(customPage) && customPage >= 1 && customPage <= <?php echo $totalPages; ?>) {
            window.location.href = '?page=' + customPage;
        }
    });
</script>

</body>
</html>

<?php

require 'src/db/psql.php';

if (isset($_POST['id']) && isset($_POST['value']) && isset($_POST['column'])) {
    $id = $_POST['id'];
    $value = $_POST['value'];
    $column = $_POST['column'];

    // Check if the user exists in the database
    $check_query = "SELECT * FROM tusers WHERE id = :id";
    $check_stmt = $conn->prepare($check_query);
    $check_stmt->bindParam(':id', $id);
    $check_stmt->execute();

    if ($check_stmt->rowCount() === 1) {
        // Store the value in the database
        $update_query = "UPDATE tusers SET $column = :value WHERE id = :id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':value', $value);
        $update_stmt->bindParam(':id', $id);

        if ($update_stmt->execute()) {
            echo 'Success'; // Send a success response
        } else {
            echo 'Error: ' . $update_stmt->errorInfo()[2]; // Send an error response
        }
    } else {
        echo 'User not found'; // Send a response if user is not found
    }
}

?>
