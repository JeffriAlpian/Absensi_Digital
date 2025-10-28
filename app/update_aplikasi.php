<?php
// ============================
// Update System (Safe Version + Progress Detail)
// ============================

// konfigurasi
$updateFile = "update.zip";
$tmpDir = __DIR__ . "/tmp_update/";
$backupDir = __DIR__ . "/backup/backup_" . date("Ymd_His") . "/";

// HTML + CSS responsif
echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Proses Update</title>
<style>
    body { font-family: Arial, sans-serif; padding: 20px; max-width: 600px; margin:auto; background:#f9f9f9; }
    .log { background:#fff; border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:15px; box-shadow:0 2px 5px rgba(0,0,0,0.1); }
    .success { color:green; font-weight:bold; }
    .error { color:red; font-weight:bold; }
    .file { font-size:13px; color:#555; margin-left:10px; }
    .btn { display:inline-block; padding:10px 16px; background:#007bff; color:#fff; text-decoration:none; border-radius:6px; margin-top:10px; }
    .btn:hover { background:#0056b3; }
    @media(max-width:600px){
        body { padding:10px; }
        .log { font-size:14px; }
    }
</style>
</head>
<body>
<h2>🔄 Proses Update Sistem</h2>
<div class="log">
HTML;

// perbesar limit
ini_set('max_execution_time', 300);
ini_set('memory_limit', '512M');
ignore_user_abort(true);

// 1. Cek URL update
if (!isset($_GET['file'])) {
    echo "<p class='error'>Tidak ada file update.</p></div></body></html>";
    exit;
}
$fileUrl = $_GET['file'];

// 2. Aktifkan maintenance mode
file_put_contents(__DIR__ . "/maintenance.flag", "Updating...");

// 3. Download update.zip
echo "Mengunduh file update...<br>";
ob_flush(); flush();
file_put_contents($updateFile, fopen($fileUrl, 'r'));

// 4. Ekstrak ke tmp_update/
$zip = new ZipArchive;
if ($zip->open($updateFile) === TRUE) {
    if (is_dir($tmpDir)) {
        exec("rm -rf " . escapeshellarg($tmpDir));
    }
    mkdir($tmpDir, 0755, true);

    $zip->extractTo($tmpDir);
    $zip->close();
    unlink($updateFile);
    echo "Ekstrak berhasil ke tmp_update/<br>";
    ob_flush(); flush();
} else {
    unlink(__DIR__ . "/maintenance.flag");
    echo "<p class='error'>Gagal membuka file update.</p></div></body></html>";
    exit;
}

// 5. Backup file lama
echo "Membuat backup...<br>";
mkdir($backupDir, 0755, true);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    if (strpos($item, 'backup') !== false || strpos($item, 'tmp_update') !== false) continue;
    $targetPath = $backupDir . str_replace(__DIR__ . DIRECTORY_SEPARATOR, '', $item);
    if ($item->isDir()) {
        mkdir($targetPath, 0755, true);
    } else {
        copy($item, $targetPath);
        echo "<span class='file'>📦 Backup: " . htmlspecialchars(str_replace(__DIR__ . "/", '', $item)) . "</span><br>";
        ob_flush(); flush();
    }
}
echo "Backup selesai di: <b>$backupDir</b><br>";
ob_flush(); flush();

// 6. Replace file lama dengan yang baru
echo "Mengganti file lama...<br>";
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);
foreach ($iterator as $item) {
    $targetPath = __DIR__ . DIRECTORY_SEPARATOR . str_replace($tmpDir, '', $item);
    if ($item->isDir()) {
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }
    } else {
        copy($item, $targetPath);
        echo "<span class='file'>📝 Update: " . htmlspecialchars(str_replace($tmpDir, '', $item)) . "</span><br>";
        ob_flush(); flush();
    }
}

// 7. Bersihkan tmp_update/
exec("rm -rf " . escapeshellarg($tmpDir));

// 8. Matikan maintenance mode
unlink(__DIR__ . "/maintenance.flag");

echo "<p class='success'>✅ Update berhasil!</p>";
echo "</div>";
echo "<a href='dashboard.php' class='btn'>⬅️ Kembali ke Dashboard</a>";
echo "</body></html>";
?>
