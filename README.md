# Aplikasi Absensi Sekolah Berbasis QR Code v4.00

Aplikasi web sederhana untuk mengelola absensi siswa di sekolah menggunakan pemindaian QR Code, dengan notifikasi WhatsApp otomatis ke orang tua dan berbagai fitur pelaporan.

## Deskripsi 📝

Aplikasi ini dirancang untuk memodernisasi proses absensi di sekolah. Siswa melakukan absensi masuk dan pulang dengan memindai QR Code unik mereka. Data absensi tersimpan secara *real-time* dan dapat dipantau oleh admin, guru, serta orang tua/wali siswa. Aplikasi ini juga dilengkapi fitur notifikasi WhatsApp otomatis saat siswa melakukan absensi.

## Fitur Utama ✨

* **Manajemen Data:**
    * Kelola Data Siswa (CRUD, Import Excel, Status Aktif/Keluar).
    * Kelola Data Kelas.
    * Kelola Data Wali Kelas (dengan relasi ke kelas).
    * Kelola Data User (Admin & Guru).
    * Kelola Hari Libur Nasional/Sekolah.
    * Profil Sekolah (Nama, Alamat, Logo, Kepala Sekolah).
* **Absensi QR Code:**
    * Generate QR Code unik per siswa (berdasarkan NISN).
    * Halaman pemindaian QR Code (menggunakan `html5-qrcode`).
    * Logika absensi **Masuk** dan **Pulang** otomatis berdasarkan waktu scan dan jam pulang yang ditentukan.
    * Validasi "Gerbang Waktu" untuk absensi pulang.
* **Notifikasi WhatsApp:** 📲
    * Notifikasi otomatis ke nomor WA orang tua/wali saat siswa absen masuk atau pulang (menggunakan API Sidobe).
    * Fitur kirim pesan WhatsApp massal ke orang tua/wali.
    * Manajemen Kunci API Sidobe.
* **Pelaporan:** 📊
    * Rekap absensi bulanan per siswa (tampilan web & cetak PDF).
    * Grafik absensi bulanan.
    * Daftar siswa yang belum hadir hari ini.
    * Prosentase kehadiran.
    * Export data absensi ke format Excel (XLS).
* **Manajemen Akun:**
    * Login terpisah untuk Admin, Guru, dan Siswa (Orang Tua).
    * Manajemen user Admin & Guru oleh Super Admin.
    * Akun siswa dibuat otomatis berdasarkan data siswa (login dengan NISN).
    * Fitur ganti password untuk admin.
* **Lain-lain:**
    * Backup & Restore database.
    * Fitur pengosongan data (dengan konfirmasi password admin).
    * Desain responsif menggunakan Tailwind CSS.
    * Pengecekan *maintenance mode*.

## Teknologi yang Digunakan 💻

* **Backend:** PHP (Native)
* **Database:** MySQL / MariaDB
* **Frontend:** HTML, Tailwind CSS, JavaScript (Vanilla JS)
* **Library PHP:**
    * `phpqrcode` (untuk generate QR Code)
    * `FPDF` (untuk generate PDF laporan)
* **API Eksternal:** Sidobe WhatsApp API (untuk notifikasi WA)
* **Web Server:** Apache / Nginx (atau web server lain yang mendukung PHP & MySQL)

## Instalasi & Setup 🚀

1.  **Prasyarat:**
    * Web Server (Contoh: XAMPP, Laragon, MAMP, atau server hosting).
    * PHP versi 7.4 atau lebih baru (direkomendasikan 8.x).
    * Database MySQL atau MariaDB.
    * Composer (jika library `phpqrcode` atau `FPDF` dikelola via Composer).

2.  **Download/Clone Proyek:**
    * Unduh *source code* proyek atau *clone* repositori (jika ada).
    * Letakkan file proyek di direktori web server Anda (misal: `htdocs`, `www`).

3.  **Database:**
    * Buat database baru di phpMyAdmin atau *tool* database lainnya (misal: `absen_new`).
    * Import file `absen_new.sql` (jika tersedia) ke dalam database yang baru dibuat. File ini berisi struktur tabel yang diperlukan.

4.  **Konfigurasi:**
    * Salin atau ubah nama file `app/config-example.php` menjadi `app/config.php` (jika ada file contoh).
    * Buka file `app/config.php` dan sesuaikan detail koneksi database:
        ```php
        <?php
        $db_host = 'localhost'; // atau host database Anda
        $db_user = 'root';      // username database
        $db_pass = '';          // password database
        $db_name = 'absen_new'; // nama database

        $conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

        if (!$conn) {
            die("Koneksi database gagal: " . mysqli_connect_error());
        }

        // Atur timezone (opsional tapi disarankan)
        date_default_timezone_set('Asia/Jakarta');

        ?>
        ```

5.  **Dependensi (jika menggunakan Composer):**
    * Jika proyek menggunakan `composer.json`, buka terminal/CMD di direktori proyek dan jalankan:
        ```bash
        composer install
        ```
    * Jika tidak, pastikan folder `vendor/` (berisi `phpqrcode` dan `FPDF`) sudah ada dan disertakan dalam proyek.

6.  **Folder `assets/qr/` dan `uploads/`:**
    * Pastikan folder `assets/qr/` ada dan *writable* oleh web server (untuk menyimpan gambar QR code).
    * Pastikan folder `uploads/` ada dan *writable* (untuk menyimpan logo sekolah).

7.  **Web Server:**
    * Pastikan web server Anda berjalan dan arahkan *browser* ke URL proyek Anda (misal: `http://localhost/nama_folder_proyek/`).

## Penggunaan 🧑‍🏫

1.  **Login:**
    * Buka halaman utama aplikasi di *browser*.
    * **Admin:** Gunakan akun admin default (misal: username `admin`, password `admin` - *cek atau buat manual di tabel `users` jika belum ada*) atau akun admin lain yang sudah dibuat. *Password admin default sangat disarankan untuk segera diubah melalui menu Profil