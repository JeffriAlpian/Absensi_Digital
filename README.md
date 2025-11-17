# Aplikasi Absensi Sekolah v4.0 (QR Code, RFID & WhatsApp) 🏫

Aplikasi manajemen absensi sekolah modern berbasis web (PHP Native) yang mengintegrasikan dua metode pencatatan kehadiran: **Digital (QR Code)** dan **Fisik (IoT RFID)**.

Sistem ini dilengkapi dengan notifikasi WhatsApp otomatis ke orang tua/wali secara *real-time*, pelaporan PDF/Excel, serta dukungan perangkat IoT yang mampu bekerja dalam mode **Online** maupun **Offline**.

![Dashboard Preview](https://via.placeholder.com/800x400?text=Preview+Dashboard+Absensi) *(Ganti dengan screenshot aplikasi Anda)*

## 🌟 Fitur Utama

### 1. Sistem Absensi Hybrid
* **Scan QR Code:** Siswa/Guru memindai kartu pelajar ber-QR Code menggunakan kamera laptop/HP melalui halaman web (`html5-qrcode`).
* **Scan RFID (IoT):** Siswa/Guru menempelkan kartu (Mifare/EM4100) pada perangkat reader mandiri.

### 2. Perangkat IoT Cerdas (ESP8266/Wemos)
* **Dual Mode:**
    * **Online:** Kirim data langsung ke server via Wi-Fi.
    * **Offline:** Simpan data ke MicroSD Card saat Wi-Fi putus.
* **Auto-Sync:** Data offline otomatis dikirim ke server saat koneksi pulih.
* **Captive Portal:** Konfigurasi Wi-Fi & API Key tanpa *coding* ulang (konek ke Wi-Fi `Konfigurasi_Absen`).
* **Feedback:** LCD menampilkan Nama Siswa/Guru dan status (Masuk/Pulang/Gagal).

### 3. Notifikasi WhatsApp (API Sidobe) 📲
* Pesan otomatis terkirim ke Orang Tua saat anak **Absen Masuk** (termasuk status Terlambat) dan **Absen Pulang**.
* Fitur kirim pesan massal/manual dari dashboard.

### 4. Manajemen Data & Logika
* **Logika Masuk/Pulang:** Sistem otomatis mendeteksi scan sebagai "Masuk" atau "Pulang" berdasarkan riwayat hari ini.
* **Deteksi Keterlambatan:** Berdasarkan jam masuk yang diatur per kelas atau global.
* **Auto-Alpha:** Script otomatis mengubah status "Hadir" menjadi "Alpha" jika siswa lupa absen pulang pada hari sebelumnya.
* **Manajemen User:** Multi-role (Admin, Guru, Siswa).
* **Import/Export:** Import data siswa via Excel (pembuatan kelas otomatis), Export rekap absensi ke Excel & PDF.

---

## 🛠️ Teknologi & Spesifikasi

### Software Stack
| Komponen | Teknologi |
| :--- | :--- |
| **Bahasa** | PHP 8.x (Native) |
| **Database** | MySQL / MariaDB |
| **Frontend** | HTML5, Tailwind CSS, JavaScript (Vanilla) |
| **Scanner Web** | Library `html5-qrcode` |
| **PDF Engine** | `FPDF` |
| **Excel Engine** | `phpoffice/phpspreadsheet` |
| **QR Generator** | `phpqrcode` |

### Hardware IoT (Estimasi Biaya) 💰
Berikut adalah komponen yang dibutuhkan untuk membuat 1 unit alat absensi RFID:

| Komponen | Spesifikasi | Estimasi Harga |
| :--- | :--- | :--- |
| **Mikrokontroler** | Wemos D1 Mini (ESP8266) | Rp 35.000 - 45.000 |
| **RFID Reader** | MFRC522 (13.56 MHz) | Rp 15.000 - 20.000 |
| **Display** | LCD 16x2 + Modul I2C | Rp 25.000 |
| **Storage** | Modul MicroSD Card SPI | Rp 10.000 |
| **Indikator** | Buzzer 5V & LED | Rp 5.000 |
| **Kartu** | Kartu Polos Mifare 1k | @ Rp 3.000 |
| **Lainnya** | Kabel Jumper, PCB Lubang, Timah | Rp 10.000 |
| **TOTAL** | **(Per Unit)** | **± Rp 100.000 - 120.000** |

---

## 🚀 Panduan Instalasi (Server & Web)

Ikuti langkah ini untuk menjalankan aplikasi di komputer lokal (Laragon/XAMPP).

### 1. Persiapan File
1.  *Clone* atau *download* repositori ini.
2.  Simpan folder proyek di dalam `www` (Laragon) atau `htdocs` (XAMPP). Contoh: `C:/laragon/www/absensi-qr-v4`.
3.  Buka terminal di folder tersebut, jalankan `composer install` untuk mengunduh dependensi PHP (FPDF, Spreadsheet).

### 2. Database
1.  Buka **phpMyAdmin**.
2.  Buat database baru bernama `absen_new`.
3.  Import file `database.sql` (sertakan struktur tabel `siswa`, `guru`, `absensi`, `kartu_rfid`, `rfid_model`, dll) ke database tersebut.

### 3. Konfigurasi `config.php`
Buat/Edit file `config.php` di *root* folder:
```php
<?php
$conn = mysqli_connect("localhost", "root", "", "absen_new");
if (!$conn) { die("Koneksi gagal: " . mysqli_connect_error()); }
date_default_timezone_set('Asia/Jakarta');
?>
````

### 4\. Setup Awal Aplikasi

1.  Buka browser: `http://localhost/absensi-qr-v4`.
2.  Login sebagai Admin (Default: `admin` / `admin` - *segera ganti password*).
3.  Masuk ke menu **Pengaturan WA** -\> Masukkan API Key Sidobe.
4.  Masuk ke menu **Perangkat RFID** (`?page=rfid_model`):
      * Klik "Tambah Perangkat".
      * Beri nama (misal: "Gerbang Depan").
      * **Salin API Key** yang muncul (Contoh: `DEV-12345`). Ini diperlukan untuk setting alat.

-----

## 🔧 Panduan Konfigurasi IoT (Wemos D1 Mini)

### 1\. Persiapan Arduino IDE

  * Instal Board ESP8266 di Board Manager.
  * Instal Library via Library Manager:
      * `MFRC522`
      * `LiquidCrystal_I2C`
      * `ArduinoJson` (versi 6)
  * Pastikan driver CH340 terinstal agar Wemos terbaca di komputer.

### 2\. Flash Program

1.  Buka file `.ino` (Sketch Utama).
2.  Hubungkan Wemos ke USB.
3.  Pilih Board: "LOLIN(WEMOS) D1 R2 & mini".
4.  Upload sketch.

### 3\. Konfigurasi via Captive Portal

Setelah upload selesai, Wemos akan masuk mode konfigurasi.

1.  Cari Wi-Fi bernama **"Konfigurasi\_Absen"** di HP/Laptop Anda. Sambungkan.
2.  Buka browser, akses: `http://192.168.4.1`.
3.  Isi form konfigurasi:
      * **SSID WiFi:** Nama Wi-Fi sekolah/kantor (yang satu jaringan dengan server).
      * **Password WiFi:** Password Wi-Fi tersebut.
      * **Server Path:** IP Komputer Server + Folder Proyek.
          * *Contoh:* `192.168.1.10/absensi-qr-v4` (Tanpa `http://` dan tanpa `/api/...`).
          * *Catatan:* Jangan gunakan `localhost`. Gunakan IP LAN komputer Anda (cek via `ipconfig`).
      * **API Key:** Masukkan API Key perangkat yang didapat dari langkah setup Admin diatas.
4.  Klik **Simpan & Restart**.

### 4\. Troubleshooting Koneksi Lokal

Jika alat menampilkan "Gagal Kirim" di server lokal:

  * Pastikan **Windows Firewall** mengizinkan Apache/Laragon (Private Network dicentang).
  * Pastikan IP Address komputer server statis (tidak berubah-ubah).

-----

## 📖 Cara Penggunaan

### A. Mendaftarkan Kartu RFID

1.  Login Admin -\> Menu **Tambah RFID**.
2.  Pilih opsi "Siswa" atau "Guru".
3.  Pilih Nama orang yang akan didaftarkan.
4.  Pilih Perangkat Reader mana yang digunakan untuk *tapping*.
5.  Tempelkan kartu kosong ke reader. UID akan otomatis tersimpan dan terhubung ke siswa/guru tersebut.

### B. Proses Absensi

1.  Siswa menempelkan kartu ke alat IoT atau scan QR di Web.
2.  Alat/Web mengirim data ke server.
3.  Server mencatat waktu, status (Hadir/Terlambat), dan mengirim WA ke orang tua.
4.  Alat IoT menampilkan nama siswa di layar LCD.

### C. Laporan

1.  Buka menu **Rekap Bulanan**.
2.  Pilih Kelas, Bulan, Tahun.
3.  Klik "Tampilkan" untuk view web atau "Cetak PDF" untuk laporan fisik.

-----

## 📄 Lisensi

Project ini bersifat *Open Source*. Silakan dikembangkan lebih lanjut.
