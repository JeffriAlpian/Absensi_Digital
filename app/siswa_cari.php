<?php
// Sertakan config database (sesuaikan path jika perlu)
// Asumsi config.php ada di level yang sama dengan dashboard.php
require_once 'config.php'; 
session_start(); // Perlu session untuk cek role

// [DIUBAH] Metode Keamanan: Cek Session Login

// Cek apakah user sudah login (sesi username ada)
if (!isset($_SESSION['username'])) {
    // Jika tidak ada sesi, kirim respons error (bisa JSON atau HTML error)
    // dan hentikan skrip
    // die("Akses ditolak. Anda harus login."); 
    
    // Atau kirim row tabel error untuk AJAX
    $user_role = ''; // Default role jika tidak login
    $colspan = ($user_role === 'admin' ? 7 : 6); 
    echo "<tr><td colspan='$colspan' class='px-4 py-4 text-center text-red-500'>Akses ditolak. Sesi Anda mungkin sudah habis, silakan login kembali.</td></tr>";
    exit; // Hentikan eksekusi
}

// Jika sesi ada, lanjutkan eksekusi skrip...

// Ambil search term dari parameter GET
$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';

// Tentukan role user untuk menampilkan tombol aksi
$user_role = $_SESSION['role'] ?? '';

// Siapkan query dasar
$sql = "SELECT s.*, k.nama_kelas
        FROM siswa s
        LEFT JOIN kelas k ON s.id_kelas = k.id
        WHERE s.status='aktif'";

// Tambahkan kondisi pencarian jika search term tidak kosong
if (!empty($searchTerm)) {
    // Gunakan prepared statement untuk keamanan
    $searchTermWild = "%" . $searchTerm . "%";
    $sql .= " AND (s.nama LIKE ? OR s.nis LIKE ? OR s.nisn LIKE ?)";
}

$sql .= " ORDER BY s.nama ASC LIMIT 50"; // Batasi hasil

// Persiapan dan eksekusi query
$stmt = $conn->prepare($sql);

if (!$stmt) {
    // Error saat prepare query
    echo "<tr><td colspan='".($user_role === 'admin' ? 7 : 6)."' class='px-4 py-4 text-center text-red-500'>Error: Query database gagal disiapkan.</td></tr>";
    exit;
}

// Bind parameter jika ada pencarian
if (!empty($searchTerm)) {
    $stmt->bind_param("sss", $searchTermWild, $searchTermWild, $searchTermWild);
}

$stmt->execute();
$result = $stmt->get_result();

// Bangun output HTML
$output = '';
if ($result->num_rows == 0) {
    $output = "<tr><td colspan='".($user_role === 'admin' ? 7 : 6)."' class='px-4 py-4 text-center text-gray-500'>Data siswa tidak ditemukan.</td></tr>";
} else {
    // Helper class (salin dari siswa.php jika perlu)
    $btn_info = "bg-cyan-500 hover:bg-cyan-600 focus:ring-cyan-400";
    $btn_warning = "bg-yellow-500 hover:bg-yellow-600 focus:ring-yellow-400";
    
    while ($row = $result->fetch_assoc()) {
        $qr_file = "../assets/qr/{$row['nisn']}.png"; // Sesuaikan path relatif
        $qr_src = file_exists($qr_file) ? str_replace('../', '', $qr_file) : 'assets/qr/default.png'; // Path untuk src

        $output .= '<tr class="hover:bg-gray-50">';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">' . htmlspecialchars($row['nis']) . '</td>';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">' . htmlspecialchars($row['nisn']) . '</td>';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900">' . htmlspecialchars($row['nama']) . '</td>';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center">' . htmlspecialchars($row['nama_kelas'] ?? 'N/A') . '</td>';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700">' . htmlspecialchars($row['no_wa'] ?? '') . '</td>';
        $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-center">';
        $output .= '  <a href="' . $qr_src . '" target="_blank" title="Lihat QR Code">';
        $output .= '    <img src="' . $qr_src . '" width="40" class="mx-auto border rounded">';
        $output .= '  </a>';
        $output .= '</td>';

        // Kolom aksi hanya untuk admin
        if ($user_role === 'admin') {
            $output .= '<td class="px-4 py-2 whitespace-nowrap text-sm text-center space-x-2">';
            $output .= '  <a href="?page=siswa&edit=' . $row['id'] . '" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white ' . $btn_info . '">Edit</a>';
            $output .= '  <a href="?page=siswa&keluar=' . $row['id'] . '" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md shadow-sm text-white ' . $btn_warning . '" onclick="return confirm(\'Yakin siswa ini keluar/lulus?\')">Keluar</a>';
            $output .= '</td>';
        }
        $output .= '</tr>';
    }
}

$stmt->close();
$conn->close();

// Keluarkan hasil HTML
echo $output;
?>