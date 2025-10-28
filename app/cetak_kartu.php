<?php
// Memulai session PHP untuk mengakses variabel session seperti role dan username.
session_start();
// Memeriksa apakah pengguna sudah login dan memiliki role 'admin' atau 'guru'.
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
    // Jika tidak, arahkan pengguna ke halaman login (diasumsikan index.php di root).
    header("Location: ../index.php"); // Sesuaikan path jika perlu
    // Menghentikan eksekusi skrip lebih lanjut setelah redirect.
    exit;
}

// Menyertakan file konfigurasi database (berisi variabel $conn).
include 'config.php';
// Menyertakan library FPDF untuk pembuatan file PDF.
require '../fpdf/fpdf.php'; // Pastikan path ini benar

// --- Mengambil Data Sekolah ---
// Mengambil data profil sekolah (logo, nama, alamat) dari database, hanya 1 baris.
$profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT logo, nama_sekolah, alamat FROM profil_sekolah LIMIT 1"));
// Inisialisasi path logo sebagai null.
$logo_path = null;
// Memeriksa apakah data profil ada, logo tidak kosong, dan file logo benar-benar ada di server.
if ($profil && !empty($profil['logo']) && file_exists('../uploads/' . $profil['logo'])) {
    // Jika ya, set path lengkap ke file logo. Path '../uploads/' berarti folder uploads ada satu level di atas folder saat ini (app).
    $logo_path = '../uploads/' . $profil['logo'];
}
// Mengambil nama sekolah dari data profil, atau gunakan string default jika tidak ada.
$nama_sekolah = $profil['nama_sekolah'] ?? 'Nama Sekolah Anda';
// Mengambil alamat sekolah dari data profil, atau gunakan string default jika tidak ada.
$alamat_sekolah = $profil['alamat'] ?? 'Alamat Sekolah Anda';

// --- Mengambil Data Siswa ---
// Mengambil semua data siswa (*) yang berstatus 'aktif', menggabungkannya dengan tabel 'kelas' untuk mendapatkan nama kelas,
// dan mengurutkannya berdasarkan ID kelas lalu nama siswa.
$result = mysqli_query($conn, "SELECT s.*, k.nama_kelas
                               FROM siswa s
                               LEFT JOIN kelas k ON s.id_kelas = k.id
                               WHERE s.status='aktif'
                               ORDER BY k.nama_kelas ASC, s.nama ASC"); // Urut berdasarkan nama kelas dulu, baru nama siswa

// --- Setup FPDF ---
// Mendefinisikan class PDF kustom yang mewarisi (extends) dari FPDF.
class PDF extends FPDF
{
    // Properti publik untuk menyimpan path logo (tidak digunakan di sini, tapi ada di kode asli Anda).
    public $logo_path;

    // // Fungsi kustom Footer (dihapus dari kode Anda, tapi bisa ditambahkan kembali jika perlu).
    // function Footer()
    // {
    //     $this->SetY(-15); // Posisi 1.5 cm dari bawah.
    //     $this->SetFont('Arial', 'I', 9); // Font italic ukuran 9.
    //     $this->Cell(0, 10, 'Teks Footer Anda', 0, 0, 'C'); // Tampilkan teks di tengah.
    // }
}

// Membuat objek PDF baru dengan orientasi Portrait ('P'), satuan milimeter ('mm'), ukuran kertas A4.
$pdf = new PDF('P', 'mm', 'A4');
// Menyimpan path logo ke properti objek PDF (meskipun tidak digunakan di implementasi background).
$pdf->logo_path = $logo_path;
// Menonaktifkan penambahan halaman otomatis saat konten mencapai batas bawah.
$pdf->SetAutoPageBreak(false);
// Menghapus margin halaman default agar bisa menggambar dari tepi.
$pdf->SetMargins(0, 0, 0);
// Menambahkan halaman pertama ke dokumen PDF SEBELUM loop dimulai.
$pdf->AddPage();

// --- Pengaturan Layout Kartu ---
// Menentukan lebar kartu dalam mm.
$card_width = 95;
// Menentukan tinggi kartu dalam mm.
$card_height = 50;
// Menentukan margin kiri dan kanan halaman dalam mm.
$margin_x = 7;
// Menentukan margin atas dan bawah halaman dalam mm.
$margin_y = 10;
// Menentukan jarak horizontal antar kartu dalam mm.
$spacing_x = 5;
// Menentukan jarak vertikal antar kartu dalam mm.
$spacing_y = 5;

// --- Persiapan Gambar Latar Belakang ---
// Menentukan path ke file gambar latar belakang kartu (HARUS DIGANTI SESUAI LOKASI FILE ANDA).
$background_image_path = 'desainkartu.png'; // Pastikan path ini benar relatif terhadap file cetak_kartu.php

// --- Inisialisasi Posisi dan Penghitung ---
// Menentukan posisi X awal (koordinat horizontal) untuk kartu pertama.
$x = $margin_x;
// Menentukan posisi Y awal (koordinat vertikal) untuk kartu pertama.
$y = $margin_y;
// Menghitung jumlah kartu yang sudah dibuat, dimulai dari 0.
$count = 0;

// --- Loop untuk Membuat Kartu per Siswa ---
// Mengambil data siswa baris per baris dari hasil query.
while ($data = mysqli_fetch_assoc($result)) {
    // Memeriksa apakah ini BUKAN kartu pertama DAN merupakan kelipatan 10 (kartu ke-11, 21, dst.).
    if ($count > 0 && $count % 10 == 0) {
        // Jika ya, tambahkan halaman baru ke PDF.
        $pdf->AddPage();
        // Reset posisi X ke margin kiri.
        $x = $margin_x;
        // Reset posisi Y ke margin atas.
        $y = $margin_y;
    }

    // --- Menggambar Kartu ---

    // 1. Gambar Latar Belakang Kartu
    // Memeriksa apakah file gambar latar belakang ada.
    if (file_exists($background_image_path)) {
        // Jika ada, gambar latar belakang di posisi (x,y) dengan ukuran kartu.
        $pdf->Image($background_image_path, $x, $y, $card_width, $card_height);
    } else {
        // Jika tidak ada (fallback), gambar kotak border abu-abu sebagai pengganti.
        $pdf->SetDrawColor(200); // Set warna garis abu-abu.
        $pdf->Rect($x, $y, $card_width, $card_height); // Gambar kotak border.
        $pdf->SetDrawColor(0); // Kembalikan warna garis ke hitam.
    }

    // 2. Gambar Border Kartu (Opsional, jika background sudah ada border, ini bisa dihapus)
    // Menggambar garis kotak (border) di sekeliling kartu.
    $pdf->Rect($x, $y, $card_width, $card_height);

    // Kode untuk logo sekolah, judul, nama sekolah, alamat DIKOMENTARI karena diasumsikan sudah ada di background image.
    
    // Logo sekolah
    // if ($logo_path && file_exists($logo_path)) {
    //     $pdf->Image($logo_path, $x + 5, $y + 2, 12, 12);
    // }
    // // Judul + Nama sekolah + Alamat
    // $pdf->SetXY($x + 18, $y + 2); // Set posisi kursor
    // $pdf->SetFont('Arial', 'B', 10); // Set font tebal ukuran 10
    // $pdf->Cell(0, 5, 'Kartu Pelajar Absensi Digital', 0, 1); // Tulis judul, 0=border, 1=pindah baris
    // $pdf->SetX($x + 18); // Kembalikan X ke posisi awal teks
    // $pdf->SetFont('Arial', '', 8); // Set font biasa ukuran 8
    // $pdf->Cell(0, 4, $nama_sekolah, 0, 1); // Tulis nama sekolah
    // $pdf->SetX($x + 18); // Kembalikan X
    // $pdf->SetFont('Arial', '', 7); // Set font biasa ukuran 7
    // $pdf->MultiCell(0, 3, $alamat_sekolah); // Tulis alamat (bisa beberapa baris)

    // --- Menulis Data Siswa di Atas Background ---
    // Set posisi Y awal untuk data siswa (misal: 14mm dari atas kartu). Sesuaikan nilai ini!
    $pdf->SetXY($x + 5, $y + 14); // Set posisi X=5mm dari kiri kartu, Y=14mm dari atas kartu

    // Menulis Nama Siswa
    $pdf->SetFont('Arial', 'B', 9); // Set font tebal ukuran 9.
    // Batasi panjang nama jika perlu (kode ini di-komen karena mungkin tidak perlu jika posisi sudah pas)
    // $nama_display = $data['nama'];
    // ... (kode potong nama) ...
    // Tulis nama siswa dalam cell selebar 50mm, tanpa border (0), pindah baris (1).
    $pdf->Cell(50, 5, 'Nama: ' . $data['nama'], 0, 1);

    // Menulis Data Lainnya (NIS, NISN, Kelas)
    $pdf->SetFont('Arial', '', 8); // Set font biasa ukuran 8.
    $pdf->SetX($x + 5); // Set posisi X kembali ke 5mm dari kiri.
    $pdf->Cell(50, 4, 'NIS : ' . $data['nis'], 0, 1); // Tulis NIS.
    $pdf->SetX($x + 5); // Set X lagi.
    $pdf->Cell(50, 4, 'NISN: ' . $data['nisn'], 0, 1); // Tulis NISN.
    $pdf->SetX($x + 5); // Set X lagi.
    $pdf->Cell(50, 4, 'Kelas: ' . $data['nama_kelas'], 0, 1); // Tulis Nama Kelas.

    // --- Menempatkan QR Code di Atas Background ---
    // Menentukan path file gambar QR code siswa. Path '../assets/qr/' berarti folder assets satu level di atas folder saat ini (app).
    $qr_path = "../assets/qr/" . $data['nisn'] . ".png";
    // Memeriksa apakah file QR code ada.
    if (file_exists($qr_path)) {
        // Jika ada, gambar QR code di posisi X, Y dengan ukuran W, H yang ditentukan. Sesuaikan nilai ini!
        // X = $x + $card_width - 33.3  (33.3mm dari kanan kartu?)
        // Y = $y + $card_height - 36    (36mm dari bawah kartu?)
        // W = 23.3 mm (lebar QR)
        // H = 21 mm   (tinggi QR)
        $pdf->Image($qr_path, $x + $card_width - 33.3, $y + $card_height - 36, 23.3, 21);
    } else {
        // Jika file QR tidak ada (fallback), tampilkan teks 'QR Missing'.
        $pdf->SetXY($x + $card_width - 25, $y + $card_height - 10); // Set posisi untuk teks error.
        $pdf->Cell(18, 5, 'QR Missing', 0, 1, 'C'); // Tulis teks error di tengah cell.
    }

    // --- Menentukan Posisi Kartu Berikutnya ---
    // Memeriksa apakah penambahan kartu berikutnya akan melebihi batas kanan halaman (210mm - margin kanan).
    if ($x + $card_width + $spacing_x > 210 - $margin_x) {
        // Jika ya, reset X ke margin kiri.
        $x = $margin_x;
        // Pindahkan Y ke bawah sejauh tinggi kartu + jarak vertikal.
        $y += $card_height + $spacing_y;
    } else {
        // Jika tidak, pindahkan X ke kanan sejauh lebar kartu + jarak horizontal.
        $x += $card_width + $spacing_x;
    }

     // Cek batas bawah halaman (setelah update Y atau X)
     // Ini mungkin perlu disesuaikan lagi logikanya agar lebih akurat
    if ($y + $card_height > 297 - $margin_y - 15) { // Cek jika posisi Y + tinggi kartu melebihi batas bawah (297mm - margin - footer)
        // Jika melewati batas, paksa $count ke kelipatan 10 agar AddPage terpanggil di iterasi berikutnya.
        // Ini mungkin menyebabkan halaman terakhir kosong jika jumlah kartu tidak pas kelipatan 10.
        $count = floor($count / 10) * 10 + 9; // Set ke kelipatan 10 - 1
    }

    // Menambah penghitung kartu.
    $count++;

} // Akhir loop while

// --- Mengirim PDF ke Browser ---
// Mengirimkan file PDF ke browser untuk ditampilkan ('I' = inline) dengan nama file 'kartu_pelajar.pdf'.
$pdf->Output('I', 'kartu_pelajar.pdf');
?>