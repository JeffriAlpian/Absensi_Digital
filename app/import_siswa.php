<?php
// Pastikan hanya admin yang bisa akses
if (($user_role ?? '') !== 'admin') {
    echo "<p class='text-red-500 p-4'>Akses ditolak.</p>";
    exit;
}

// Sertakan library
require 'vendor/phpqrcode/qrlib.php';
// Gunakan PhpSpreadsheet jika memungkinkan
require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
// Atau PHPExcel jika harus (hapus baris PhpSpreadsheet di atas jika pakai ini)
// require_once 'vendor/phpoffice/phpexcel/Classes/PHPExcel.php'; // Sesuaikan path

$msg = "";
$msg_type = "info";
$berhasil = 0;
$gagal = 0;
$errors = [];
$kelas_cache = [];

// Fungsi getIdKelas (sama seperti sebelumnya, menangani kapitalisasi & insert otomatis)
function getIdKelas($conn, $nama_kelas_input, &$cache, &$errors, $row_num)
{
    // ... (Kode fungsi getIdKelas tetap sama) ...
    $nama_kelas_cleaned = trim($nama_kelas_input);
    if (empty($nama_kelas_cleaned)) {
        $errors[] = "Baris $row_num: Nama Kelas kosong.";
        return null;
    }
    $nama_kelas_upper = strtoupper($nama_kelas_cleaned);
    if (isset($cache[$nama_kelas_upper])) {
        return $cache[$nama_kelas_upper];
    }
    $stmt_cek = $conn->prepare("SELECT id FROM kelas WHERE nama_kelas = ?");
    if (!$stmt_cek) {
        $errors[] = "Baris $row_num: Gagal prepare cek kelas: " . $conn->error;
        return null;
    }
    $stmt_cek->bind_param("s", $nama_kelas_upper);
    $stmt_cek->execute();
    $result_cek = $stmt_cek->get_result();
    if ($result_cek->num_rows > 0) {
        $row_kelas = $result_cek->fetch_assoc();
        $id_kelas = $row_kelas['id'];
        $cache[$nama_kelas_upper] = $id_kelas;
        $stmt_cek->close();
        return $id_kelas;
    } else {
        $stmt_insert = $conn->prepare("INSERT INTO kelas (nama_kelas) VALUES (?)");
        if (!$stmt_insert) {
            $errors[] = "Baris $row_num: Gagal prepare insert kelas: " . $conn->error;
            $stmt_cek->close();
            return null;
        }
        $stmt_insert->bind_param("s", $nama_kelas_upper);
        if ($stmt_insert->execute()) {
            $id_kelas = $conn->insert_id;
            $cache[$nama_kelas_upper] = $id_kelas;
            $stmt_insert->close();
            $stmt_cek->close();
            return $id_kelas;
        } else {
            $errors[] = "Baris $row_num: Gagal insert kelas baru '$nama_kelas_upper': " . $stmt_insert->error;
            $stmt_insert->close();
            $stmt_cek->close();
            return null;
        }
    }
}


if (isset($_POST['import'])) {
    if (!empty($_FILES['file_excel']['tmp_name'])) {
        $inputFileName = $_FILES['file_excel']['tmp_name'];

        try {
            // Gunakan IOFactory (PhpSpreadsheet)
            $spreadsheet = IOFactory::load($inputFileName);
            $sheet = $spreadsheet->getActiveSheet();
            // Atau PHPExcel
            // $excelReader = PHPExcel_IOFactory::createReaderForFile($inputFileName);
            // $excelReader->setReadDataOnly(true);
            // $objPHPExcel = $excelReader->load($inputFileName);
            // $sheet = $objPHPExcel->getActiveSheet();

            $highestRow = $sheet->getHighestRow();

            // [DIUBAH] Siapkan prepared statement siswa dengan no_wa
            $sql_siswa = "INSERT INTO siswa (nis, nisn, nama, tempat_lahir, tanggal_lahir, id_kelas, no_wa, status)
                          VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif')
                          ON DUPLICATE KEY UPDATE nama = VALUES(nama), tempat_lahir = VALUES(tempat_lahir), tanggal_lahir = VALUES(tanggal_lahir), id_kelas = VALUES(id_kelas), no_wa = VALUES(no_wa), status = 'aktif'";
            $stmt_siswa = $conn->prepare($sql_siswa);
            if (!$stmt_siswa) {
                throw new Exception("Gagal prepare statement siswa: " . $conn->error);
            }


            for ($row = 2; $row <= $highestRow; $row++) { // Asumsi baris 1 header
                // [DIUBAH] Ambil data cell (sesuaikan PhpSpreadsheet/PHPExcel) + No WA (kolom E / index 4)
                // PhpSpreadsheet
                $nis       = trim($sheet->getCell('A' . $row)->getValue() ?? '');
                $nisn      = trim($sheet->getCell('B' . $row)->getValue() ?? '');
                $nama      = trim($sheet->getCell('C' . $row)->getValue() ?? '');
                $tempat_lahir = trim($sheet->getCell('D' . $row)->getValue() ?? '');

                // $tanggal_lahir = trim($sheet->getCell('E' . $row)->getValue() ?? '');

                // Ambil nilai mentah dari Excel
                $cellValue = $sheet->getCell('E' . $row)->getValue();

                // Cek apakah ada nilai
                if (!empty($cellValue)) {
                    // Jika berupa angka serial (contoh 39719)
                    if (is_numeric($cellValue)) {
                        $timestamp = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToTimestamp($cellValue);
                        $tanggal_lahir = date('Y-m-d', $timestamp);
                    } else {
                        // Jika berupa teks tanggal
                        $timestamp = strtotime($cellValue);
                        if ($timestamp !== false) {
                            $tanggal_lahir = date('Y-m-d', $timestamp);
                        } else {
                            // Jika format aneh, simpan apa adanya
                            $tanggal_lahir = trim((string)$cellValue);
                        }
                    }
                } else {
                    $tanggal_lahir = ''; // Kosongkan jika tidak ada tanggal
                }

                // Pastikan disimpan sebagai teks (string)
                $tanggal_lahir = strval($tanggal_lahir);
                $kelas_str = trim($sheet->getCell('F' . $row)->getValue() ?? '');
                $no_wa     = trim($sheet->getCell('G' . $row)->getValue() ?? ''); // Ambil No WA
                // PHPExcel
                // $nis   = trim($sheet->getCellByColumnAndRow(0, $row)->getValue());
                // $nisn  = trim($sheet->getCellByColumnAndRow(1, $row)->getValue());
                // $nama  = trim($sheet->getCellByColumnAndRow(2, $row)->getValue());
                // $kelas_str = trim($sheet->getCellByColumnAndRow(3, $row)->getValue());
                // $no_wa = trim($sheet->getCellByColumnAndRow(4, $row)->getValue()); // Ambil No WA

                // Validasi data dasar (termasuk kelas)
                if (empty($nis) && empty($nisn) && empty($nama) && empty($kelas_str)) {
                    continue;
                }
                if (empty($nis) || empty($nisn) || empty($nama) || empty($kelas_str)) {
                    $gagal++;
                    $errors[] = "Baris $row: NIS, NISN, Nama, dan Kelas wajib diisi.";
                    continue;
                }

                // Validasi format NIS & NISN (sama)
                if (!preg_match('/^[0-9]+$/', $nis)) {
                    $gagal++;
                    $errors[] = "Baris $row: NIS '$nis' harus angka.";
                    continue;
                }
                if (!preg_match('/^[0-9]{10}$/', $nisn)) {
                    $gagal++;
                    $errors[] = "Baris $row: NISN '$nisn' harus 10 digit angka.";
                    continue;
                }

                // [BARU] Validasi No WA (opsional)
                $no_wa_valid = null; // Default null jika kosong atau tidak valid
                if (!empty($no_wa)) {
                    // Hapus karakter non-digit
                    $no_wa_cleaned = preg_replace('/[^0-9]/', '', $no_wa);
                    // Cek jika dimulai 0, ganti 62
                    if (substr($no_wa_cleaned, 0, 1) === '0') {
                        $no_wa_valid = '62' . substr($no_wa_cleaned, 1);
                    }
                    // Cek jika sudah dimulai 62
                    elseif (substr($no_wa_cleaned, 0, 2) === '62') {
                        $no_wa_valid = $no_wa_cleaned;
                    } else {
                        // Format lain dianggap tidak valid (kosongkan saja)
                        $gagal++;
                        $errors[] = "Baris $row: Format No WA '$no_wa' tidak valid (harus 08... atau 62...).";
                        // $no_wa_valid = null; // Biarkan null
                        continue; // Atau lewati baris jika no wa wajib valid
                    }
                    // Batasi panjang (opsional)
                    if (strlen($no_wa_valid) < 10 || strlen($no_wa_valid) > 15) {
                        $gagal++;
                        $errors[] = "Baris $row: Panjang No WA '$no_wa_valid' tidak wajar.";
                        $no_wa_valid = null; // Kosongkan jika panjang tidak wajar
                        continue; // Atau lewati
                    }
                } else {
                    $no_wa_valid = null; // Jika kolom WA kosong di excel
                }

                // Dapatkan ID Kelas (memanggil fungsi)
                $id_kelas = getIdKelas($conn, $kelas_str, $kelas_cache, $errors, $row);
                if ($id_kelas === null) {
                    $gagal++;
                    continue;
                }

                // [DIUBAH] Bind parameter siswa (sssis: string, string, string, integer, string)
                $stmt_siswa->bind_param("sssssis", $nis, $nisn, $nama, $tempat_lahir, $tanggal_lahir, $id_kelas, $no_wa_valid);

                if ($stmt_siswa->execute()) {
                    $berhasil++;
                    // Buat QR Code
                    $qr_dir = "assets/qr/";
                    if (!is_dir($qr_dir)) mkdir($qr_dir, 0777, true);
                    QRcode::png($nisn, $qr_dir . "$nisn.png", QR_ECLEVEL_L, 4);
                } else {
                    $gagal++;
                    $errors[] = "Baris $row: Gagal simpan siswa (NIS: $nis, NISN: $nisn): " . $stmt_siswa->error;
                }
            } // End loop for

            $stmt_siswa->close();

            $msg = "✅ Import selesai. <br>
                    Berhasil: <b>$berhasil</b> baris.<br>
                    Gagal: <b>$gagal</b> baris.";
            $msg_type = ($gagal > 0) ? "warning" : "success";
        } catch (Exception $e) {
            $msg = "❌ Terjadi Kesalahan saat membaca file: " . $e->getMessage();
            $msg_type = "error";
        }
    } else {
        $msg = "❌ Harap pilih file Excel terlebih dahulu.";
        $msg_type = "error";
    }
}

// Helper class Tailwind
$input_class = "mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500 sm:text-sm";
$btn_class = "inline-flex items-center justify-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 transition-colors duration-150";

?>

<div class="flex-1 p-6">

    <?php if (!empty($msg)): ?>
        <?php /* Kode notifikasi Tailwind */ ?>
        <?php
        $alert_bg = "bg-blue-100 border-blue-400 text-blue-700"; // default info
        if ($msg_type == "success") $alert_bg = "bg-green-100 border-green-400 text-green-700";
        if ($msg_type == "error" || ($msg_type == "warning" && $gagal > 0)) $alert_bg = "bg-red-100 border-red-400 text-red-700";
        if ($msg_type == "warning" && $gagal == 0) $alert_bg = "bg-yellow-100 border-yellow-400 text-yellow-700";
        ?>
        <div class="mb-4 p-4 <?= $alert_bg ?> border rounded" role="alert">
            <?= $msg ?>
        </div>
    <?php endif; ?>

    <a href="?page=siswa" class="<?= $btn_class ?> bg-gray-600 hover:bg-gray-700 focus:ring-gray-500 mb-6">
        <i class="fa-solid fa-arrow-left mr-2"></i> Kembali ke Data Siswa
    </a>

    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <form method="post" enctype="multipart/form-data">
            <div class="mb-4">
                <label for="file_excel" class="block text-sm font-medium text-gray-700 mb-1">Pilih File Excel (.xls / .xlsx)</label>
                <input type="file" id="file_excel" name="file_excel"
                    accept=".xls,.xlsx,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100"
                    required>
            </div>
            <div class="flex items-center justify-between">
                <button type="submit" name="import" class="<?= $btn_class ?> bg-green-600 hover:bg-green-700 focus:ring-green-500">
                    <i class="fa-solid fa-upload mr-2"></i>Import Data
                </button>
                <a href="app/template_siswa.xlsx" download class="text-sm text-blue-600 hover:text-blue-800 hover:underline">
                    <i class="fa-solid fa-file-excel mr-1"></i>Unduh Template Excel
                </a>
            </div>
        </form>
    </div>

    <div class="bg-blue-50 p-4 rounded-lg border border-blue-200 text-blue-800 text-sm mb-6">
        <h3 class="font-semibold mb-2"><i class="fa-solid fa-info-circle mr-1"></i> Informasi Format Excel:</h3>
        <ul class="list-disc list-inside space-y-1">
            <li>Gunakan template yang disediakan untuk format yang benar.</li>
            <li>Kolom: **NIS | NISN | Nama | Kelas | No WA** (Baris pertama adalah header).</li>
            <li>NIS: Harus unik, hanya angka.</li>
            <li>NISN: Harus unik, 10 digit angka.</li>
            <li>Nama: Nama lengkap siswa.</li>
            <li>Kelas: Nama kelas (contoh: 7A). Otomatis kapital & dibuat jika belum ada.</li>
            <li>**No WA**: Nomor WhatsApp (format 08... atau 62...). Opsional, boleh kosong. Jika diisi, akan divalidasi dan diformat ke 62...</li>
            <li>Pastikan tidak ada baris kosong di tengah data.</li>
        </ul>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-50 p-4 rounded-lg border border-red-200 mt-6">
            <h3 class="font-semibold mb-2 text-red-800"><i class="fa-solid fa-times-circle mr-1"></i> Detail Error (Total Gagal: <?= $gagal ?>):</h3>
            <ul class="list-disc list-inside space-y-1 text-red-700 text-sm max-h-60 overflow-y-auto">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

</div>