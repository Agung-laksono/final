<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$dbPath = database_path('wilayah.sqlite');
if(file_exists($dbPath)) unlink($dbPath);
$pdo = new PDO('sqlite:' . $dbPath);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$pdo->exec('CREATE TABLE regions (id VARCHAR(20) PRIMARY KEY, parent_id VARCHAR(20), name VARCHAR(255), type VARCHAR(50))');
$pdo->exec('CREATE INDEX idx_parent ON regions(parent_id)');
$pdo->exec('CREATE INDEX idx_name ON regions(name COLLATE NOCASE)');
$pdo->exec('CREATE INDEX idx_type ON regions(type)');

$pdo->beginTransaction();
$stmt = $pdo->prepare('INSERT INTO regions (id, parent_id, name, type) VALUES (?, ?, ?, ?)');

// Provinces
$f = fopen(public_path("api-wilayah/data/provinces.csv"), "r");
while(($row = fgetcsv($f)) !== FALSE) { if(count($row)>=2) $stmt->execute([trim($row[0]), null, trim($row[1]), 'province']); } fclose($f);

// Regencies
$f = fopen(public_path("api-wilayah/data/regencies.csv"), "r");
while(($row = fgetcsv($f)) !== FALSE) { if(count($row)>=3) $stmt->execute([trim($row[0]), trim($row[1]), trim($row[2]), 'regency']); } fclose($f);

// Districts
$f = fopen(public_path("api-wilayah/data/districts.csv"), "r");
while(($row = fgetcsv($f)) !== FALSE) { if(count($row)>=3) $stmt->execute([trim($row[0]), trim($row[1]), trim($row[2]), 'district']); } fclose($f);

// Villages
$f = fopen(public_path("api-wilayah/data/villages.csv"), "r");
while(($row = fgetcsv($f)) !== FALSE) { if(count($row)>=3) $stmt->execute([trim($row[0]), trim($row[1]), trim($row[2]), 'village']); } fclose($f);

$pdo->commit();
echo "Done building $dbPath\n";
