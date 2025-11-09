<?php
session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    header("Location: ../index.php");
    exit;
}

include 'config.php';
require '../fpdf/fpdf.php';

$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT logo, nama_sekolah, alamat FROM profil_sekolah LIMIT 1"));
$logo_path = null;
if ($profil && !empty($profil['logo']) && file_exists('../uploads/' . $profil['logo'])) {
    $logo_path = '../uploads/' . $profil['logo'];
}
$nama_sekolah = $profil['nama_sekolah'] ?? 'Nama Sekolah Anda';
$alamat_sekolah = $profil['alamat'] ?? 'Alamat Sekolah Anda';

$result = mysqli_query($conn, "SELECT s.*, k.nama_kelas
                               FROM siswa s
                               LEFT JOIN kelas k ON s.id_kelas = k.id
                               WHERE s.status='aktif'
                               ORDER BY k.nama_kelas ASC, s.nama ASC");

class PDF extends FPDF
{
    public $logo_path;
}

$pdf = new PDF('P', 'mm', 'A4');
$pdf->logo_path = $logo_path;
$pdf->SetAutoPageBreak(false);
$pdf->SetMargins(0, 0, 0);

// --- Layout Kartu ---
$card_width = 85.6;
$card_height = 54;
$margin_x = 16;
$margin_y = 7;
$spacing_x = 5;
$spacing_y = 5;

$background_image_path = 'desainkartu.png';

// --- Hitung grid per halaman ---
$page_width = 210;
$page_height = 297;
$printable_width = $page_width - 2 * $margin_x;
$printable_height = $page_height - 2 * $margin_y;

// columns: jumlah kartu per baris
$columns = (int) floor(($printable_width + $spacing_x) / ($card_width + $spacing_x));
if ($columns < 1) $columns = 1;

// rows: jumlah baris per halaman
$rows = (int) floor(($printable_height + $spacing_y) / ($card_height + $spacing_y));
if ($rows < 1) $rows = 1;

$cards_per_page = $columns * $rows;

$pdf->AddPage();

// index kartu total (0-based)
$index = 0;

while ($data = mysqli_fetch_assoc($result)) {
    // Jika penuh satu halaman dan bukan pertama, buat halaman baru
    if ($index > 0 && $index % $cards_per_page == 0) {
        $pdf->AddPage();
    }

    // posisi dalam halaman (0..cards_per_page-1)
    $pos_in_page = $index % $cards_per_page;
    $col = $pos_in_page % $columns;
    $row = (int) floor($pos_in_page / $columns);

    $x = $margin_x + $col * ($card_width + $spacing_x);
    $y = $margin_y + $row * ($card_height + $spacing_y);

    // Gambar background
    if (file_exists($background_image_path)) {
        $pdf->Image($background_image_path, $x, $y, $card_width, $card_height);
    } else {
        $pdf->SetDrawColor(200);
        $pdf->Rect($x, $y, $card_width, $card_height);
        $pdf->SetDrawColor(0);
    }

    // Border
    $pdf->Rect($x, $y, $card_width, $card_height);

    // Teks siswa
    // Teks siswa - label/value rata kiri dengan lebar label tetap
    $pdf->SetFont('Arial', 'B', 9);

    // Siapkan nama (dengan pemendekan jika > 2 kata)
    $name = trim($data['nama']);
    if ($name === '') {
        $name = '-';
    } else {
        $words = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if (count($words) > 2) {
            $first_two = array_slice($words, 0, 2);
            $rest = array_slice($words, 2);
            $abbreviated = array_map(function($word) {
                return mb_substr($word, 0, 1) . '.';
            }, $rest);
            $name = implode(' ', array_merge($first_two, $abbreviated));
        } else {
            $name = implode(' ', $words);
        }
    }

    // Layout kolom: label tetap rata kanan, nilai rata kiri
    $labelW = 10; // lebar kolom label
    $gap = 2;     // jarak setelah label
    $valW = $card_width - 6 - $labelW - $gap; // sisa untuk nilai (3mm margin kiri + 3mm kanan kira-kira)

    $lineH = 3;
    $startX = $x + 2;
    $curY = $y + 15;

    // Nama
    $pdf->SetXY($startX, $curY);
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->Cell($labelW, $lineH, 'Nama', 0, 0, 'L');
    $pdf->Cell($gap, $lineH, ':', 0, 0, 'C');
    $pdf->Cell($valW, $lineH, ' ' . $name, 0, 1, 'L');

    // Baris berikutnya (ukuran font lebih kecil)
    $pdf->SetFont('Arial', '', 6.5);

    $curY += $lineH;
    $pdf->SetXY($startX, $curY);
    $pdf->Cell($labelW, $lineH, 'NIS', 0, 0, 'L');
    $pdf->Cell($gap, $lineH, ':', 0, 0, 'C');
    $pdf->Cell($valW, $lineH, ' ' . ($data['nis'] ?: '-'), 0, 1, 'L');

    $curY += $lineH;
    $pdf->SetXY($startX, $curY);
    $pdf->Cell($labelW, $lineH, 'NISN', 0, 0, 'L');
    $pdf->Cell($gap, $lineH, ':', 0, 0, 'C');
    $pdf->Cell($valW, $lineH, ' ' . ($data['nisn'] ?: '-'), 0, 1, 'L');

    $curY += $lineH;
    $pdf->SetXY($startX, $curY);
    $pdf->Cell($labelW, $lineH, 'TTL', 0, 0, 'L');
    $pdf->Cell($gap, $lineH, ':', 0, 0, 'C');
    $ttl = trim(ucfirst(strtolower($data['tempat_lahir'])) . ', ' . (!empty($data['tanggal_lahir']) ? date('d/m/Y', strtotime($data['tanggal_lahir'])) : '-'));
    $pdf->Cell($valW, $lineH, ' ' . $ttl, 0, 1, 'L');

    $curY += $lineH;
    $pdf->SetXY($startX, $curY);
    $pdf->Cell($labelW, $lineH, 'Kelas', 0, 0, 'L');
    $pdf->Cell($gap, $lineH, ':', 0, 0, 'C');
    $pdf->Cell($valW, $lineH, ' ' . ($data['nama_kelas'] ?: '-'), 0, 1, 'L');

    // QR
    $qr_path = "../assets/qr/" . $data['nisn'] . ".png";
    if (file_exists($qr_path)) {
        $pdf->Image($qr_path, $x + $card_width - 31, $y + $card_height - 39, 23, 23);
    } else {
        $pdf->SetXY($x + $card_width - 25, $y + $card_height - 10);
        $pdf->Cell(18, 5, 'QR Missing', 0, 1, 'C');
    }

    $index++;
}

$pdf->Output('I', 'kartu_pelajar.pdf');
