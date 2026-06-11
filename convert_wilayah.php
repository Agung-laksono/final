<?php

$sqlUrl = 'https://raw.githubusercontent.com/cahyadsn/wilayah/master/db/wilayah.sql';
$sqlContent = file_get_contents($sqlUrl);

if (!$sqlContent) {
    die("Failed to download wilayah.sql");
}

$lines = explode("\n", $sqlContent);

$provinces = [];
$cities = [];
$districts = [];
$villages = [];

foreach ($lines as $line) {
    if (preg_match("/^\s*\('([^']+)',\s*'([^']+)'\)/", $line, $matches)) {
        $kode = trim($matches[1]);
        $nama = trim($matches[2]);
        
        $parts = explode('.', $kode);
        $level = count($parts);
        
        if ($level === 1) {
            $provinces[] = [$kode, $nama];
        } elseif ($level === 2) {
            $cities[] = [str_replace('.', '', $kode), $parts[0], $nama];
        } elseif ($level === 3) {
            $districts[] = [str_replace('.', '', $kode), $parts[0] . $parts[1], $nama];
        } elseif ($level === 4) {
            $villages[] = [str_replace('.', '', $kode), $parts[0] . $parts[1] . $parts[2], $nama];
        }
    }
}

$outputDir = __DIR__ . '/public/api-wilayah/data';
if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

function writeCsv($filename, $data) {
    global $outputDir;
    $fp = fopen($outputDir . '/' . $filename, 'w');
    if (!$fp) die("Failed to open file: $filename");
    foreach ($data as $row) {
        fputcsv($fp, $row);
    }
    fclose($fp);
    echo "Created $filename with " . count($data) . " rows.\n";
}

writeCsv('provinces.csv', $provinces);
writeCsv('regencies.csv', $cities);
writeCsv('districts.csv', $districts);
writeCsv('villages.csv', $villages);

echo "Done!\n";
