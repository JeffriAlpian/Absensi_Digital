<?php
include 'config.php'; // Asumsi config.php ada di root (satu level di atas)

$username = "NISN"; // Username default
$password = "NISN"; // Password default

// ====== Ambil nama sekolah ======
$qProfil = mysqli_query($conn, "SELECT nama_sekolah FROM profil_sekolah LIMIT 1");
$profil = $qProfil ? mysqli_fetch_assoc($qProfil) : null;
$nama_sekolah = $profil['nama_sekolah'] ?? "Sekolah Anda";

// ====== Bangun URL (LOGIKA DIPERBAIKI) ======
$httpsOn = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? 80) == 443;
$protocol = $httpsOn ? 'https://' : 'http://';
$host     = $_SERVER['HTTP_HOST'] ?? 'localhost';

// Ambil path direktori dari request URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';

// [PERBAIKAN] Ambil direktori 1 tingkat sebelum file ini
// Contoh: Jika file ini di /absensi/app/share.php, ini akan mengambil /absensi/
$parentDir = dirname($requestUri); 
// Tambahkan trailing slash jika belum di root
$dirPath = rtrim($parentDir, '/\\') . '/';

// URL Lengkap ke root aplikasi
$baseUrl = $protocol . $host . $dirPath;

// ====== Susun pesan ======
$pesan = $baseUrl . "\n\n" .
    "Username: " . $username . "\n" .
    "Password: " . $password . "\n\n" .
    "Mohon izin menginformasikan bahwa kami dari " . htmlspecialchars($nama_sekolah) . " telah menggunakan teknologi absen digital " .
    "Yang dapat dipantau secara langsung oleh Bapak/Ibu Orang Tua/Wali Siswa. " .
    "Mohon simpan nomor ini agar kami bisa mengirim informasi dengan lancar.";

$waLink = "https://wa.me/?text=" . urlencode($pesan);
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-12 col-md-10 col-lg-8">
            <div class="card shadow-sm border-0">
                
                <div class="card-header bg-white text-center py-3 border-0">
                    <h1 class="h4 mb-0">Bagikan Informasi Login</h1>
                    <p class="text-muted mb-0 small">Bagikan link dan info login ke grup WhatsApp.</p>
                </div>
                
                <div class="card-body p-4 p-md-5">

                    <div class="mb-3">
                        <label for="namaSekolah" class="form-label fw-bold">Nama Sekolah</label>
                        <input type="text" id="namaSekolah" class="form-control" value="<?php echo htmlspecialchars($nama_sekolah, ENT_QUOTES); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="baseUrl" class="form-label fw-bold">URL Portal Absen (Otomatis)</label>
                        <input type="text" id="baseUrl" class="form-control" value="<?php echo htmlspecialchars($baseUrl, ENT_QUOTES); ?>" readonly>
                    </div>

                    <div class="mb-3">
                        <label for="previewPesan" class="form-label fw-bold">Preview Pesan WhatsApp</label>
                        <textarea id="previewPesan" class="form-control" rows="8" readonly><?php echo htmlspecialchars($pesan, ENT_QUOTES); ?></textarea>
                    </div>

                    <div class="alert alert-warning small" role="alert">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>Perhatian:</strong> Harap gunakan nomor WhatsApp Bisnis untuk membagikan pesan ini ke banyak kontak agar terhindar dari blokir/spam.
                    </div>

                    <div class="d-grid gap-2">
                        <a class="btn btn-success btn-lg" href="<?php echo htmlspecialchars($waLink, ENT_QUOTES); ?>" target="_blank" rel="noopener">
                            <i class="fa-brands fa-whatsapp me-2"></i>
                            Bagikan ke WhatsApp
                        </a>
                        <button class="btn btn-outline-secondary" type="button" onclick="copyPesan()">
                            <i class="fa-solid fa-copy me-2"></i>
                            Salin Teks Pesan
                        </button>
                    </div>

                    <hr class="my-4">
                    
                    <p class="text-muted small mb-0 fst-italic">
                        *URL Portal Absen otomatis diambil dari domain dan direktori utama (satu tingkat di atas file ini).
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function copyPesan() {
    // [PERBAIKAN] Menggunakan json_encode untuk escaping string PHP ke JS yang aman
    const txt = <?php echo json_encode($pesan); ?>;
    
    if (navigator.clipboard) {
        // Metode modern (aman di koneksi HTTPS)
        navigator.clipboard.writeText(txt).then(() => {
            alert('Pesan disalin ke clipboard.');
        }).catch((err) => {
            // Fallback jika gagal (misal di koneksi non-HTTPS)
            fallbackCopyText(txt);
        });
    } else {
        // Fallback untuk browser lama (IE, dll)
        fallbackCopyText(txt);
    }
}

function fallbackCopyText(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    // Styling agar tidak terlihat dan tidak mengganggu
    ta.style.position = 'fixed';
    ta.style.top = 0;
    ta.style.left = 0;
    ta.style.opacity = 0;
    document.body.appendChild(ta);
    ta.select();
    try {
        document.execCommand('copy');
        alert('Pesan disalin ke clipboard.');
    } catch (err) {
        alert('Gagal menyalin pesan.');
    }
    document.body.removeChild(ta);
}
</script>