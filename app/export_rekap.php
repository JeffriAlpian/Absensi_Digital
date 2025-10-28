<?php
include "config.php";
// --- [DIUBAH] LOGIKA EXPORT EXCEL VIA POST (Menggunakan PhpSpreadsheet) ---
// Sertakan autoloader Composer
require '../vendor/autoload.php'; // Sesuaikan path jika perlu

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

// Cek JIKA ADA REQUEST POST dengan action='export'
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] == 'export') {

  // Ambil parameter dari POST (sama seperti sebelumnya)
  $bulan_awal = $_POST['bulan_awal'] ?? date('m');
  $tahun_awal = $_POST['tahun_awal'] ?? date('Y');
  $bulan_akhir = $_POST['bulan_akhir'] ?? date('m');
  $tahun_akhir = $_POST['tahun_akhir'] ?? date('Y');
  $id_kelas = isset($_POST['id_kelas']) ? intval($_POST['id_kelas']) : 0;

  // Tentukan nama file (sama seperti sebelumnya)
  $kelasNama = "semua_kelas";
  if ($id_kelas > 0) { /* ... (logika ambil nama kelas) ... */
  }
  $filename = "absensi_{$kelasNama}_{$tahun_awal}-{$bulan_awal}_sampai_{$tahun_akhir}-{$bulan_akhir}.xlsx"; // Ubah ekstensi ke .xlsx

  // Tentukan tanggal awal & akhir (sama seperti sebelumnya)
  $tanggal_awal  = date("Y-m-01", strtotime("$tahun_awal-$bulan_awal-01"));
  $tanggal_akhir = date("Y-m-t", strtotime("$tahun_akhir-$bulan_akhir-01"));

  // Query data (sama seperti sebelumnya, gunakan prepared statement)
  $query = "SELECT a.tanggal, a.jam, a.jam_pulang, a.status, a.keterangan,
                     s.nis, s.nisn, s.nama, k.nama_kelas
              FROM absensi a
              JOIN siswa s ON a.siswa_id = s.id
              LEFT JOIN kelas k ON s.id_kelas = k.id
              WHERE a.tanggal BETWEEN ? AND ? AND s.status='aktif'";
  $params = [$tanggal_awal, $tanggal_akhir];
  $types = "ss";
  if ($id_kelas > 0) {
    $query .= " AND s.id_kelas = ?";
    $params[] = $id_kelas;
    $types .= "i";
  }
  $query .= " ORDER BY a.tanggal, k.nama_kelas, s.nama";

  // --- Pembuatan Spreadsheet ---
  $spreadsheet = new Spreadsheet();
  $sheet = $spreadsheet->getActiveSheet();
  $sheet->setTitle('Data Absensi'); // Nama sheet

  // Header Kolom
  $headers = ['Tanggal', 'NIS', 'NISN', 'Nama', 'Kelas', 'Jam Masuk', 'Jam Pulang', 'Status', 'Keterangan'];
  $col = 'A';
  foreach ($headers as $header) {
    $sheet->setCellValue($col . '1', $header);
    // Style Header (Bold, Warna, Tengah)
    $sheet->getStyle($col . '1')->getFont()->setBold(true);
    $sheet->getStyle($col . '1')->getFill()
      ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
      ->getStartColor()->setARGB('FFFF00');
    $sheet->getStyle($col . '1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
    $col++;
  }

  // Isi Data
  $rowNum = 2; // Mulai dari baris ke-2
  $stmt = $conn->prepare($query);
  if ($stmt) {
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
      $jam_masuk = $row['jam'] ? date('H:i:s', strtotime($row['jam'])) : '';
      $jam_pulang = $row['jam_pulang'] ? date('H:i:s', strtotime($row['jam_pulang'])) : '';
      $keterangan_clean = str_replace(["\t", "\n", "\r"], " ", $row['keterangan'] ?? '');

      $sheet->setCellValue('A' . $rowNum, $row['tanggal']);
      $sheet->setCellValue('B' . $rowNum, $row['nis']);
      $sheet->setCellValue('C' . $rowNum, $row['nisn']);
      $sheet->setCellValue('D' . $rowNum, $row['nama']);
      $sheet->setCellValue('E' . $rowNum, $row['nama_kelas'] ?? 'N/A');
      $sheet->setCellValue('F' . $rowNum, $jam_masuk);
      $sheet->setCellValue('G' . $rowNum, $jam_pulang);
      $sheet->setCellValue('H' . $rowNum, $row['status']);
      $sheet->setCellValue('I' . $rowNum, $keterangan_clean);
      $rowNum++;
    }
    $stmt->close();
  } else {
    // Handle error jika query gagal prepare
    $sheet->setCellValue('A2', 'Error: Query database gagal disiapkan.');
  }
  $lastRow = $rowNum - 1; // Baris terakhir yang berisi data
  $lastCol = 'I'; // Kolom terakhir

  // --- Styling ---

  // 1. Border untuk seluruh tabel data (A1 sampai kolom terakhir, baris terakhir)
  if ($lastRow >= 1) { // Pastikan ada data atau header
    $styleArray = [
      'borders' => [
        'allBorders' => [ // Terapkan ke semua sisi (outline dan inside)
          'borderStyle' => Border::BORDER_THIN,
          'color' => ['argb' => 'FF000000'], // Warna hitam
        ],
      ],
    ];
    $sheet->getStyle('A1:' . $lastCol . $lastRow)->applyFromArray($styleArray);
  }

  // 2. Auto Size Kolom (dari A sampai kolom terakhir)
  foreach (range('A', $lastCol) as $columnID) {
    $sheet->getColumnDimension($columnID)->setAutoSize(true);
  }

  // Atur alignment dasar (opsional, misal rata kiri)
  if ($lastRow >= 2) {
    $sheet->getStyle('A2:' . $lastCol . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    // Tengahkan kolom Status
    $sheet->getStyle('H2:H' . $lastRow)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
  }


  // --- Output File ---
  // Set header untuk download XLSX
  header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
  header('Content-Disposition: attachment;filename="' . $filename . '"');
  header('Cache-Control: max-age=0');
  // Bersihkan buffer output sebelumnya (jika ada)
  if (ob_get_length()) ob_end_clean();

  // Tulis ke output PHP
  $writer = new Xlsx($spreadsheet);
  $writer->save('php://output');

  // Hentikan script setelah file dikirim
  exit;
}
// --- AKHIR LOGIKA EXPORT EXCEL ---