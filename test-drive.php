<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$credentialsPath = storage_path('app/google-drive-credentials.json');
$client = new \Google_Client();
$credentials = json_decode(file_get_contents($credentialsPath), true);
$client->setAuthConfig($credentials);
$client->setScopes([\Google_Service_Drive::DRIVE]);

$service = new \Google_Service_Drive($client);
$folderId = '16kyAn9-5l6szkp87n46fhvTQM8CexNv6';

// Test 1: Can we see the folder?
try {
    $folder = $service->files->get($folderId);
    echo "Folder found: " . $folder->getName() . "\n";
} catch (\Exception $e) {
    echo "Error getting folder: " . $e->getMessage() . "\n";
}

// Test 2: Upload directly using API
try {
    $fileMetadata = new \Google_Service_Drive_DriveFile([
        'name' => 'test-direct-api.txt',
        'parents' => [$folderId]
    ]);
    
    $content = 'Test direct api upload';
    $file = $service->files->create($fileMetadata, [
        'data' => $content,
        'mimeType' => 'text/plain',
        'uploadType' => 'multipart',
        'fields' => 'id'
    ]);
    echo "File created directly with ID: " . $file->id . "\n";
} catch (\Exception $e) {
    echo "Error creating file directly: " . $e->getMessage() . "\n";
}

// Test 3: Upload via Flysystem
try {
    \Illuminate\Support\Facades\Config::set('filesystems.disks.google.folder', $folderId);
    app('filesystem')->forgetDisk('google');
    
    \Illuminate\Support\Facades\Storage::disk('google')->put('test-flysystem.txt', 'Test flysystem upload');
    echo "File created via Flysystem\n";
} catch (\Exception $e) {
    echo "Error creating file via Flysystem: " . $e->getMessage() . "\n";
}
