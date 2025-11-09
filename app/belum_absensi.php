<?php

date_default_timezone_set("Asia/Jakarta");

$tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$kelas   = $_GET['kelas'] ?? '';

$secretKey = "";
$qKey = mysqli_query($conn, "SELECT key_wa_sidobe FROM profil_sekolah LIMIT 1");
if ($qKey && mysqli_num_rows($qKey) > 0) {
    $rowKey = mysqli_fetch_assoc($qKey);
    $secretKey = $rowKey['key_wa_sidobe'] ?? "";
}
$isSidobeConfigured = !empty($secretKey); // Flag untuk cek konfigurasi

// [BARU] Helper class Tailwind (sesuaikan jika sudah ada di file utama)
$btn_class = "inline-flex items-center justify-center px-3 py-1.5 border border-transparent text-xs font-medium rounded-md shadow-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed";

?>

<div class="container">

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <form method="get" class="mb-3 row g-2 items-center flex">
    <input type="hidden" name="page" value="belum_absensi">
    <div class="col-auto">
      <label for="tanggal" class="form-label mb-0">Tanggal:</label>
      <input type="date" name="tanggal" value="<?= $tanggal ?>" class="form-control">
    </div>

    <div class="col-auto">
      <label for="kelas" class="form-label mb-0">Kelas:</label>
      <select name="kelas" class="form-select">
        <option value="">Semua Kelas</option>
        <?php
        $qkelas = mysqli_query($conn, "SELECT DISTINCT id, nama_kelas FROM kelas ORDER BY nama_kelas");
        while ($k = mysqli_fetch_assoc($qkelas)) {
          $selected = $k['id'] == $kelas ? 'selected' : '';
          echo "<option $selected value='{$k['id']}'>{$k['nama_kelas']}</option>";
        }
        ?>
      </select>
    </div>
    
    <div class="col-auto">
      <button type="submit" class="inline-flex justify-center items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
        <i class="fa-solid fa-filter mr-2"></i> Tampilkan
      </button>
    </div>
  </form>

  <table class="table table-bordered table-sm">
    <thead class="table-light">
      <tr>
        <th>NIS</th>
        <th>Nama</th>
        <th>Kelas</th>
        <th>Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $filterKelas = intval($kelas) ? "AND s.id_kelas = '$kelas'" : '';

      $q = mysqli_query($conn, "SELECT s.id, s.nis, s.nama, s.no_wa, k.nama_kelas AS kelas FROM siswa s JOIN kelas k ON s.id_kelas = k.id WHERE s.status = 'aktif' $filterKelas
        AND NOT EXISTS (
        SELECT 1 
        FROM absensi a
        WHERE a.siswa_id = s.id
          AND a.tanggal = '$tanggal') ORDER BY s.nama; ");

      if (mysqli_num_rows($q) > 0) {
        mysqli_data_seek($q, 0); // Pastikan pointer di awal
        while ($d = mysqli_fetch_assoc($q)) {
            // [DIUBAH] Pesan tetap dibuat, tapi tidak perlu urlencode
            $namaSiswa = htmlspecialchars($d['nama']);
            $nisSiswa = htmlspecialchars($d['nis']);
            $kelasSiswa = htmlspecialchars($d['kelas']); // Asumsi JOIN sudah benar
            $noWaOrtu = htmlspecialchars($d['no_wa'] ?? ''); // Ambil No WA Ortu
            $siswaId = intval($d['id']); // Ambil ID siswa

            // Buat pesan dasar (akan dikirim ke PHP via AJAX)
             $pesanDasar = "Assalamualaikum Wr. Wb.,\n\n"
              . "Kami informasikan bahwa ananda *{$namaSiswa}* "
              . "(NIS: {$nisSiswa}, Kelas: {$kelasSiswa}) "
              . "belum tercatat hadir pada tanggal *$tanggal*.\n\n" // Pastikan var $tanggal ada
              . "Terimakasih Atas Perhatian-nya Bapak/Ibu 🙏";

            echo "<tr id='row-siswa-{$siswaId}'>"; // [BARU] ID unik untuk baris
            echo "  <td class='px-4 py-2 whitespace-nowrap text-sm text-gray-700'>" . $nisSiswa . "</td>";
            echo "  <td class='px-4 py-2 whitespace-nowrap text-sm font-medium text-gray-900'>" . $namaSiswa . "</td>";
            echo "  <td class='px-4 py-2 whitespace-nowrap text-sm text-gray-700 text-center'>" . $kelasSiswa . "</td>";
            echo "  <td class='px-4 py-2 whitespace-nowrap text-sm text-center'>";

            // [DIUBAH] Tombol Kirim WA via Sidobe API (AJAX)
            if ($isSidobeConfigured && !empty($noWaOrtu)) {
                echo "<button type='button'
                                class='{$btn_class} bg-green-600 hover:bg-green-700 focus:ring-green-500 btn-kirim-wa'
                                data-siswa-id='{$siswaId}'
                                data-no-wa='{$noWaOrtu}'
                                data-nama='{$namaSiswa}'
                                data-nis='{$nisSiswa}'
                                data-kelas='{$kelasSiswa}'
                                data-pesan='" . htmlspecialchars($pesanDasar) . "'>
                          <i class='fa-brands fa-whatsapp mr-1'></i>
                          <span class='btn-text'>Kirim WA</span>
                          <i class='fa-solid fa-spinner fa-spin ml-1 hidden loading-icon'></i>
                      </button>";
                echo "<span class='ml-2 text-xs text-green-600 hidden status-ok'><i class='fa-solid fa-check-circle'></i> Terkirim</span>";
                echo "<span class='ml-2 text-xs text-red-600 hidden status-fail'><i class='fa-solid fa-times-circle'></i> Gagal</span>";
            } elseif (!$isSidobeConfigured) {
                 echo "<span class='text-xs text-gray-500 italic'>API WA belum di-setup</span>";
            } else {
                 echo "<span class='text-xs text-gray-500 italic'>No. WA Ortu kosong</span>";
            }
            echo "  </td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='4' class='px-4 py-4 text-center text-gray-500'>Semua siswa sudah ada record absensi pada tanggal ini</td></tr>";
    }
      ?>
    </tbody>
  </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const buttons = document.querySelectorAll('.btn-kirim-wa');

    buttons.forEach(button => {
        button.addEventListener('click', () => {
            const siswaId = button.dataset.siswaId;
            const noWa = button.dataset.noWa;
            const pesan = button.dataset.pesan;
            const row = document.getElementById(`row-siswa-${siswaId}`);
            const btnText = button.querySelector('.btn-text');
            const loadingIcon = button.querySelector('.loading-icon');
            const statusOk = row.querySelector('.status-ok');
            const statusFail = row.querySelector('.status-fail');

            // Tampilkan loading & disable tombol
            btnText.textContent = 'Mengirim...';
            loadingIcon.classList.remove('hidden');
            button.disabled = true;
            statusOk.classList.add('hidden');
            statusFail.classList.add('hidden');

            // Siapkan data untuk dikirim
            const formData = new FormData();
            formData.append('no_wa', noWa);
            formData.append('pesan', pesan);
            // Anda bisa mengirim siswa_id jika perlu info lain di backend
            // formData.append('siswa_id', siswaId); 

            // Kirim request AJAX ke endpoint PHP
            fetch('app/kirim_wa_sidobe.php', { // Pastikan path ini benar
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    statusOk.classList.remove('hidden');
                    // Optional: Ubah tampilan tombol setelah sukses
                    button.classList.remove('bg-green-600', 'hover:bg-green-700');
                    button.classList.add('bg-gray-400', 'cursor-not-allowed'); 
                    btnText.textContent = 'Terkirim';
                } else {
                    statusFail.textContent = 'Gagal: ' + (data.message || 'Error tidak diketahui');
                    statusFail.classList.remove('hidden');
                    // Kembalikan tombol ke state awal agar bisa dicoba lagi
                    btnText.textContent = 'Kirim Ulang';
                    button.disabled = false; 
                }
            })
            .catch(error => {
                console.error('Error:', error);
                statusFail.textContent = 'Gagal: Error koneksi';
                statusFail.classList.remove('hidden');
                btnText.textContent = 'Kirim Ulang';
                button.disabled = false;
            })
            .finally(() => {
                loadingIcon.classList.add('hidden'); // Selalu sembunyikan loading di akhir
            });
        });
    });
});
</script>