<?php

require __DIR__ . '/../../vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

$host = $_ENV['PSQL_HOST'];
$port = intval($_ENV['PSQL_PORT']);
$dbname = $_ENV['PSQL_DB_NAME'];
$user = $_ENV['PSQL_USERNAME'];
$password = $_ENV['PSQL_PASSWORD'];

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;user=$user;password=$password";

try {
    $conn = new PDO($dsn);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Connection failed: ' . $e->getMessage());
}
?>
