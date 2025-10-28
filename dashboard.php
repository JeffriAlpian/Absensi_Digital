<?php
session_start();
// [DIUBAH] Cek apakah user login dan rolenya admin atau guru
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'guru'])) {
  // Jika tidak, redirect ke halaman login
  header("Location: index.php"); // Asumsi login di index.php
  exit;
}

// Simpan role user untuk digunakan nanti
$user_role = $_SESSION['role'];

include "app/config.php";



// Ambil parameter halaman, default 'home' jika tidak ada
$halaman = isset($_GET['page']) ? $_GET['page'] : 'home';
// Tentukan path aplikasi
$app_path = 'app/';

// Tentukan Judul Halaman berdasarkan var $halaman
// Anda bisa melengkapi ini untuk setiap 'case'
$page_title = 'Dashboard Admin'; // Judul default
switch ($halaman) {
  case 'siswa':
    $page_title = 'Data Siswa';
    break;
  case 'kelas':
    $page_title = 'Data Kelas';
    break;
  case 'scan':
    $page_title = 'Scan QR Code';
    break;
  case 'scan_wa':
    $page_title = 'Scan QR + WA Manual';
    break;
  case 'scan_wa_api':
    $page_title = 'Scan QR + WA API';
    break;
  case 'key_wa_sidobe':
    $page_title = 'Key API WA';
    break;
  case 'belum_absensi':
    $page_title = 'Siswa Belum Hadir';
    break;
  case 'absensi':
    $page_title = 'Input S/I/A';
    break;
  case 'jam_absensi':
    $page_title = 'Jam Waktu Absensi';
    break;
  case 'rekap_bulanan':
    $page_title = 'Rekap Bulanan';
    break;
  case 'hadir':
    $page_title = 'Prosentase Kehadiran';
    break;
  case 'libur':
    $page_title = 'Hari Libur';
    break;
  case 'export':
    $page_title = 'Export Excel';
    break;
  case 'wa-wali-siswa':
    $page_title = 'Kirim WA Orang Tua';
    break;
  case 'profil':
    $page_title = 'Profil Sekolah';
    break;
  case 'users':
    $page_title = 'Manajemen User';
    break;
  case 'wali_kelas':
    $page_title = 'Data Wali Kelas';
    break;
  case 'kosongkan_data':
    $page_title = 'Hapus Data';
    break;
  case 'restore':
    $page_title = 'Restore Data Contoh';
    break;
  case 'backup_restore':
    $page_title = 'Backup & Restore';
    break;
  case 'pengaturan':
    $page_title = 'Pengaturan';
    break;
  case 'cetak_kartu':
    $page_title = 'Cetak Kartu Siswa';
    break;
  case 'siswa_keluar':
    $page_title = 'Siswa Keluar/Lulus';
    break;
  case 'import_siswa':
    $page_title = 'Import Data Siswa';
    break;
  case 'editprofil':
    $page_title = 'Edit Profil';
    break;
  case 'server-info':
    $page_title = 'Info Server';
    break;
  case 'update_db':
    $page_title = 'Update Database';
    break;
  case 'cek_update':
    $page_title = 'Cek Pembaruan';
    break;
  case 'riwayat':
    $page_title = 'Riwayat';
    break;
}

?>
<!DOCTYPE html>
<html lang="id">

<head>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard Admin Absensi QR</title>

  <script src="https://unpkg.com/html5-qrcode"></script>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css" rel="stylesheet">

  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">

  <script src="https://cdn.tailwindcss.com"></script>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    @keyframes marquee {
      0% {
        transform: translateX(0);
      }

      100% {
        transform: translateX(-100%);
      }
    }

    .animate-marquee {
      animation: marquee 15s linear infinite;
    }
  </style>
</head>

<body class="flex flex-col h-screen font-sans bg-gray-100 overflow-hidden">

  <div class="bg-orange-500 overflow-hidden whitespace-nowrap box-border py-2.5">
    <span class="inline-block pl-full animate-marquee text-white font-bold text-base">
      Kehadiran Bapak/Ibu Guru membersamai siswa belajar tidak akan pernah dapat digantikan oleh Robot AI
    </span>
  </div>

  <div class="flex flex-1 relative overflow-hidden">

    <div id="overlay" class="fixed inset-0 bg-black/60 z-20 lg:hidden hidden"></div>

    <aside id="sidebar"
      class="fixed top-0 inset-y-0 left-0 z-30
                  flex flex-col w-64 h-full
                  bg-white shadow-xl
                  transform -translate-x-full 
                  transition-transform duration-300 ease-in-out
                  lg:static lg:translate-x-0">

      <div class="p-4 border-b flex items-center justify-center gap-3">
        <?php 
        $profil = mysqli_fetch_assoc(mysqli_query($conn, "SELECT nama_sekolah, logo FROM profil_sekolah LIMIT 1"));
        $nama_sekolah = $profil['nama_sekolah'] ?? 'Nama Sekolah';
        $logo = $profil['logo'] ?? 'default.png'; 
        ?>

        <img src="uploads/<?= htmlspecialchars($logo); ?>" alt="Logo Sekolah" class="mx-auto h-16 mb-2">
        <h2 class="text-l font-bold text-gray-800"><?= htmlspecialchars($nama_sekolah); ?></h2>
      </div>

      <nav class="flex-1 overflow-y-auto p-4">
        <ul class="flex flex-col gap-2">
          <?php
          // Definisikan semua item menu dan role yang boleh akses
          $menuItems = [
            // Menu Umum (Admin & Guru)
            ['page' => 'home',          'icon' => 'fa-home',                'text' => 'Dashboard',          'roles' => ['admin', 'guru']],
            ['page' => 'siswa',         'icon' => 'fa-user-graduate',       'text' => 'Data Siswa',         'roles' => ['admin', 'guru']],
            // ['page' => 'scan',          'icon' => 'fa-qrcode',              'text' => 'SCAN QR',            'roles' => ['admin', 'guru']],
            // ['page' => 'scan_wa',       'icon' => 'fa-qrcode',              'text' => 'SCAN QR + WA Manual', 'roles' => ['admin', 'guru']],
            ['page' => 'scan_wa_api',   'icon' => 'fa-qrcode',              'text' => 'SCAN QR',   'roles' => ['admin', 'guru']], // Guru mungkin perlu ini?
            ['page' => 'belum_absensi', 'icon' => 'fa-user-clock',          'text' => 'Siswa Belum Hadir',  'roles' => ['admin', 'guru']], // Ganti ikon
            ['page' => 'absensi',       'icon' => 'fa-clipboard-check',     'text' => 'Isi S/I/A',          'roles' => ['admin', 'guru']],
            ['page' => 'rekap_bulanan', 'icon' => 'fa-calendar-days',       'text' => 'Rekap Bulanan',      'roles' => ['admin', 'guru']],
            ['page' => 'hadir',         'icon' => 'fa-chart-pie',           'text' => 'Prosentase Kehadiran', 'roles' => ['admin', 'guru']],
            ['page' => 'wa-wali-siswa', 'icon' => 'fa-brands fa-whatsapp',            'text' => 'Kirim WA Ortu',      'roles' => ['admin', 'guru']], // Ganti ikon

            // Menu Khusus Admin
            ['page' => 'key_wa_sidobe', 'icon' => 'fa-key',                 'text' => 'Key API WA',         'roles' => ['admin']], // Ganti ikon
            ['page' => 'jam_absensi',   'icon' => 'fa-clock',               'text' => 'Jam Absensi',        'roles' => ['admin']],
            ['page' => 'libur',         'icon' => 'fa-plane',               'text' => 'Hari Libur',         'roles' => ['admin']],
            ['page' => 'export',        'icon' => 'fa-file-excel',          'text' => 'Export Excel',       'roles' => ['admin']],
            ['page' => 'profil',        'icon' => 'fa-school',              'text' => 'Profil Sekolah',     'roles' => ['admin']],
            ['page' => 'wali_kelas',    'icon' => 'fa-chalkboard-teacher',  'text' => 'Wali Kelas',         'roles' => ['admin']],
            ['page' => 'users',         'icon' => 'fa-users-cog',           'text' => 'Manajemen User',     'roles' => ['admin']], // [BARU] Link ke user.php
            ['page' => 'kelas',         'icon' => 'fa-door-closed',         'text' => 'Manajemen Kelas',    'roles' => ['admin']], // [BARU] Link ke kelas.php
            ['page' => 'kosongkan_data', 'icon' => 'fa-trash',               'text' => 'Hapus Data',         'roles' => ['admin']],
            ['page' => 'restore',       'icon' => 'fa-database',            'text' => 'Restore Contoh',     'roles' => ['admin']],
            ['page' => 'backup_restore', 'icon' => 'fa-rotate',              'text' => 'Backup Restore',     'roles' => ['admin']],
            ['page' => 'pengaturan',    'icon' => 'fa-gear',                'text' => 'Pengaturan',         'roles' => ['admin']],
            // Tambahkan link eksternal jika perlu
            ['url'  => '', 'icon' => 'fa-chain', 'text' => 'External Link', 'roles' => ['admin'], 'external' => true],
          ];

          // Loop dan tampilkan menu sesuai role
          foreach ($menuItems as $item) {
            // Cek apakah role user saat ini ada dalam array 'roles' item menu
            if (in_array($user_role, $item['roles'])) {
              $link_class = "flex items-center gap-3 p-3 rounded-lg font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-100 hover:text-green-600";
              $icon_class = "fa-solid " . $item['icon'] . " fa-fw text-orange-500"; // Default icon color

              // [FIX] Cek 'page' exists before using it for color check
              $page_key = $item['page'] ?? null; // Get 'page' or null if it doesn't exist

              // Sesuaikan warna ikon jika perlu
              if ($page_key !== null) { // Only check colors if 'page' key exists
                if (in_array($page_key, ['scan', 'scan_wa', 'scan_wa_api', 'key_wa_sidobe', 'wa-wali-siswa', 'export'])) $icon_class = str_replace('text-orange-500', 'text-green-600', $icon_class);
                if (in_array($page_key, ['belum_absensi', 'libur', 'kosongkan_data'])) $icon_class = str_replace('text-orange-500', 'text-red-600', $icon_class);
                if (in_array($page_key, ['absensi', 'backup_restore', 'pengaturan'])) $icon_class = str_replace('text-orange-500', 'text-blue-600', $icon_class);
                if (in_array($page_key, ['jam_absensi', 'restore'])) $icon_class = str_replace('text-orange-500', 'text-purple-600', $icon_class);
                if (in_array($page_key, ['rekap_bulanan', 'grafik', 'hadir', 'kelas', 'users'])) $icon_class = str_replace('text-orange-500', 'text-gray-700', $icon_class); // Added 'users' here too
              }

              // Cek apakah link eksternal atau internal
              if (isset($item['external']) && $item['external']) {
                $href = $item['url'];
                $target = 'target="_blank"';
              } else {
                $href = '?page=' . $item['page'];
                $target = '';
              }

              echo "<li>";
              echo "<a href='{$href}' class='{$link_class}' {$target}>";
              echo "<i class='{$icon_class}'></i> {$item['text']}";
              echo "</a>";
              echo "</li>";
            }
          }

          // Tampilkan link Logout (selalu ada)
          echo "<li>";
          echo "<a href='app/logout.php' class='flex items-center gap-3 p-3 rounded-lg font-medium text-gray-700 transition-colors duration-200 hover:bg-gray-100 hover:text-red-600'>"; // Hover merah
          echo "<i class='fa-solid fa-right-from-bracket fa-fw text-red-600'></i> Logout";
          echo "</a>";
          echo "</li>";

          ?>
        </ul>
      </nav>
    </aside>

    <main class="flex-1 flex flex-col h-full overflow-y-auto">


      <header class="bg-green-600 text-white p-4 shadow-md relative text-center lg:text-center">
        <button id="menu-toggle" class="lg:hidden absolute left-4 top-1/2 -translate-y-1/2 p-2 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-white">
          <i class="fa-solid fa-bars fa-fw text-xl"></i>
        </button>
        <h1 class="text-2xl lg:text-3xl font-bold"><?php echo $page_title; ?></h1>
      </header>

      <div class="flex-1 p-6">
        <?php
        // [PERUBAHAN 4] Switch case sekarang HANYA memuat konten
        switch ($halaman) {
          case 'siswa':
            include $app_path . 'siswa.php';
            break;
          case 'kelas':
            include $app_path . 'kelas.php';
            break;
          case 'scan':
            include $app_path . 'scan.php';
            break;
          case 'scan_wa':
            include $app_path . 'scan_wa.php';
            break;
          // ... (case lain sama persis) ...
          case 'scan_wa_api':
            include $app_path . 'scan_wa_api.php';
            break;
          case 'key_wa_sidobe':
            include $app_path . 'key_wa_sidobe.php';
            break;
          case 'belum_absensi':
            include $app_path . 'belum_absensi.php';
            break;
          case 'absensi':
            include $app_path . 'absensi.php';
            break;
          case 'jam_absensi':
            include $app_path . 'jam_absensi.php';
            break;
          case 'rekap_bulanan':
            include $app_path . 'rekap_bulanan.php';
            break;
          case 'hadir':
            include $app_path . 'hadir.php';
            break;
          case 'libur':
            include $app_path . 'libur.php';
            break;
          case 'export':
            include $app_path . 'export.php';
            break;
          case 'wa-wali-siswa':
            include $app_path . 'wa-wali-siswa.php';
            break;
          case 'profil':
            include $app_path . 'profil.php';
            break;
          case 'users':
            include $app_path . 'users.php';
            break;
          case 'wali_kelas':
            include $app_path . 'wali_kelas.php';
            break;
          case 'kosongkan_data':
            include $app_path . 'kosongkan_data.php';
            break;
          case 'restore':
            include $app_path . 'restore.php';
            break;
          case 'backup_restore':
            include $app_path . 'backup_restore.php';
            break;
          case 'pengaturan':
            include $app_path . 'pengaturan.php';
            break;
          case 'cetak_kartu':
            include $app_path . 'cetak_kartu.php';
            break;
          case 'siswa_keluar':
            include $app_path . 'siswa_keluar.php';
            break;
          case 'import_siswa':
            include $app_path . 'import_siswa.php';
            break;
          case 'editprofil':
            include $app_path . 'editprofil.php';
            break;
          case 'server-info':
            include $app_path . 'server-info.php';
            break;
          case 'update_db':
            include $app_path . 'update_db.php';
            break;
          case 'cek_update':
            include $app_path . 'cek_update.php';
            break;
          case 'riwayat':
            include $app_path . 'riwayat.php';
            break;

          // [PERUBAHAN 5] 'default' case disederhanakan
          case 'home':
          default:
            // Header dan wrapper 'p-6' sudah dihapus dari sini
        ?>
            <h2 class="text-3xl font-semibold text-gray-800 mb-4">Selamat Datang, Admin!</h2>
            <p class="text-gray-600 text-lg">
              Anda berada di halaman Dashboard Admin. Silakan gunakan menu di sebelah kiri untuk mengelola data absensi.
            </p>

            <?php
            // --- (Logika query stat box Anda) ---
            $query_total_siswa = "SELECT COUNT(id) as total_siswa FROM siswa";
            $sql_total_siswa = mysqli_query($conn, $query_total_siswa);
            $data_total_siswa = mysqli_fetch_assoc($sql_total_siswa);
            $total_siswa = $data_total_siswa['total_siswa'] ?? 0;

            $query_hadir_ini = "SELECT COUNT(id) as total_hadir 
                                FROM absensi 
                                WHERE status = 'H' AND tanggal = CURDATE()";
            $sql_hadir_ini = mysqli_query($conn, $query_hadir_ini);
            $data_hadir_ini = mysqli_fetch_assoc($sql_hadir_ini);
            $total_hadir = $data_hadir_ini['total_hadir'] ?? 0;

            $total_belum_hadir = $total_siswa - $total_hadir;
            ?>

            <div class="mt-8 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
              <div class="bg-white p-4 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-blue-600">Siswa Terdaftar</h3>
                <p class="text-3xl font-bold"><?php echo $total_siswa; ?></p>
              </div>
              <div class="bg-white p-4 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-green-600">Hadir Hari Ini</h3>
                <p class="text-3xl font-bold"><?php echo $total_hadir; ?></p>
              </div>
              <div class="bg-white p-4 rounded-lg shadow-md">
                <h3 class="text-lg font-semibold text-red-600">Belum Hadir</h3>
                <p class="text-3xl font-bold"><?php echo $total_belum_hadir; ?></p>
              </div>
            </div>

            <div class="mt-8 bg-white p-6 rounded-lg shadow-md">
              <?php
              // --- (Logika query chart Anda) ---
              $kelas = $_GET['kelas'] ?? '';
              $bulan = $_GET['bulan'] ?? date('m');
              $tahun = $_GET['tahun'] ?? date('Y');
              // $kelasList = mysqli_query($conn, "SELECT DISTINCT kelas FROM siswa ORDER BY kelas");
              $kelasList = mysqli_query($conn, "SELECT id, nama_kelas FROM kelas ORDER BY nama_kelas");
              $query = "SELECT a.tanggal, k.nama_kelas, a.status
                        FROM absensi a
                        JOIN siswa s ON a.siswa_id = s.id
                        JOIN kelas k ON s.id_kelas = k.id
                        WHERE MONTH(a.tanggal) = '$bulan' AND YEAR(a.tanggal) = '$tahun'";
              if ($kelas != '') {
                $query .= " AND k.id = '$kelas'";
              }
              $result = mysqli_query($conn, $query);
              $rekapGrafik = [];
              $total = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
              while ($row = mysqli_fetch_assoc($result)) {
                $tgl = date('d', strtotime($row['tanggal']));
                if (!isset($rekapGrafik[$tgl])) {
                  $rekapGrafik[$tgl] = ['H' => 0, 'S' => 0, 'I' => 0, 'A' => 0];
                }
                if (isset($rekapGrafik[$tgl][$row['status']])) {
                  $rekapGrafik[$tgl][$row['status']]++;
                  $total[$row['status']]++;
                }
              }
              $tanggalList = [];
              $dataH = [];
              $dataS = [];
              $dataI = [];
              $dataA = [];
              $jumlahHari = cal_days_in_month(CAL_GREGORIAN, $bulan, $tahun);
              for ($i = 1; $i <= $jumlahHari; $i++) {
                $tglStr = str_pad($i, 2, '0', STR_PAD_LEFT);
                $tanggalList[] = $tglStr;
                $dataH[] = $rekapGrafik[$tglStr]['H'] ?? 0;
                $dataS[] = $rekapGrafik[$tglStr]['S'] ?? 0;
                $dataI[] = $rekapGrafik[$tglStr]['I'] ?? 0;
                $dataA[] = $rekapGrafik[$tglStr]['A'] ?? 0;
              }
              ?>

              <style>
                select,
                button {
                  padding: 5px;
                }

                .total-box {
                  margin-top: 20px;
                  padding: 10px;
                  border: 1px solid #ccc;
                  display: inline-block;
                }

                .total-box span {
                  display: inline-block;
                  margin-right: 20px;
                  font-weight: bold;
                }
              </style>

              <h2>Grafik Absensi Bulanan</h2>

              <form method="get">
                <label>Kelas:
                  <select name="kelas">
                    <option value="">Semua</option>
                    <?php mysqli_data_seek($kelasList, 0);
                    while ($k = mysqli_fetch_assoc($kelasList)) {
                      $sel = ($k['nama_kelas'] == $kelas) ? 'selected' : '';
                      echo "<option $sel value='{$k['id']}'>{$k['nama_kelas']}</option>";
                    } ?>
                  </select>
                </label>
                <label>Bulan:
                  <select name="bulan">
                    <?php for ($b = 1; $b <= 12; $b++) {
                      $sel = ($b == $bulan) ? 'selected' : '';
                      echo "<option $sel value='$b'>" . date('F', mktime(0, 0, 0, $b, 10)) . "</option>";
                    } ?>
                  </select>
                </label>
                <label>Tahun:
                  <input type="number" name="tahun" value="<?= $tahun ?>" style="width:80px;">
                </label>
                <button type="submit">Tampilkan</button>
              </form>

              <canvas id="grafikAbsensi" height="100"></canvas>
              <script>
                const ctx = document.getElementById('grafikAbsensi').getContext('2d');
                new Chart(ctx, {
                  type: 'line',
                  data: {
                    labels: <?= json_encode($tanggalList) ?>,
                    datasets: [{
                        label: 'Hadir (H)',
                        data: <?= json_encode($dataH) ?>,
                        borderColor: 'green',
                        backgroundColor: 'rgba(0, 128, 0, 0.2)',
                        fill: true
                      },
                      {
                        label: 'Sakit (S)',
                        data: <?= json_encode($dataS) ?>,
                        borderColor: 'orange',
                        backgroundColor: 'rgba(255, 165, 0, 0.2)',
                        fill: true
                      },
                      {
                        label: 'Izin (I)',
                        data: <?= json_encode($dataI) ?>,
                        borderColor: 'blue',
                        backgroundColor: 'rgba(0, 0, 255, 0.2)',
                        fill: true
                      },
                      {
                        label: 'Alpa (A)',
                        data: <?= json_encode($dataA) ?>,
                        borderColor: 'red',
                        backgroundColor: 'rgba(255, 0, 0, 0.2)',
                        fill: true
                      }
                    ]
                  },
                  options: {
                    responsive: true,
                    scales: {
                      y: {
                        beginAtZero: true,
                        precision: 0
                      }
                    }
                  }
                });
              </script>

              <div class="total-box">
                <span style="color:green;">Hadir (H): <?= $total['H'] ?></span>
                <span style="color:orange;">Sakit (S): <?= $total['S'] ?></span>
                <span style="color:blue;">Izin (I): <?= $total['I'] ?></span>
                <span style="color:red;">Alpa (A): <?= $total['A'] ?></span>
              </div>
            </div>
        <?php
            break; // Akhir dari case 'home'/default
        } // Akhir dari switch
        ?>
      </div>
    </main>

  </div>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      const sidebar = document.getElementById('sidebar');
      const toggleButton = document.getElementById('menu-toggle');
      const overlay = document.getElementById('overlay');

      function toggleMenu() {
        sidebar.classList.toggle('-translate-x-full');
        overlay.classList.toggle('hidden');
      }

      // Overlay tetap berfungsi di semua halaman
      overlay.addEventListener('click', toggleMenu);

      // Event listener global ini sekarang akan SELALU menemukan #menu-toggle
      // karena header sudah dipindah ke template utama
      document.body.addEventListener('click', function(e) {
        if (e.target && (e.target.id === 'menu-toggle' || e.target.closest('#menu-toggle'))) {
          if (sidebar && overlay) {
            toggleMenu();
          }
        }
      });
    });
  </script>

</body>

</html>