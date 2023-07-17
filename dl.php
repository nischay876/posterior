<?php
require __DIR__ . '/vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

if (isset($_GET['key'])) {
    $key = $_GET['key'];

    // Set the content type and headers for downloading the file
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="'. str_replace("https://", "", $_ENV['ASS_DOMAIN']) . '.sxcu"');

    // Create the API key JSON object
    $data = [
        'Version' => '14.0.1',
        'DestinationType' => 'ImageUploader, TextUploader, FileUploader',
        'RequestMethod' => 'POST',
        'RequestURL' => $_ENV['ASS_DOMAIN'],
        'Headers' => [
            'Authorization' => $key
        ],
        'Body' => 'MultipartFormData',
        'FileFormName' => 'file',
        'URL' => '{json:.resource}',
        'ThumbnailURL' => '{json:.thumbnail}',
        'DeletionURL' => '{json:.delete}',
        'ErrorMessage' => '{response}'
    ];

    // Output the JSON data
    echo json_encode($data, JSON_PRETTY_PRINT);
    exit;
} 