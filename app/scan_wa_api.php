<div class="container mt-4">

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <div id="reader" style="width: 100%"></div>
  <div id="result" class="mt-3 mb-10" style="max-height: 300px; overflow-y: auto;"></div>

  <!-- Suara beep -->
  <audio id="beepSound" src="app/beep.mp3" preload="auto"></audio>

  <script>
    let lastScannedCode = null;  // Menyimpan kode terakhir yang discan
    let scanCooldown = false;    // Menandakan jeda antar-scan

    function onScanSuccess(qrMessage) {
      // Cegah pemrosesan ulang jika kode sama atau masih cooldown
      if (scanCooldown || qrMessage === lastScannedCode) return;

      lastScannedCode = qrMessage;
      scanCooldown = true;

      let result = document.getElementById("result");

      // Tampilkan spinner loading 1 detik
      let spinner = document.createElement("div");
      spinner.className = "d-flex align-items-center mb-2";
      spinner.innerHTML = `
        <div class="spinner-border text-primary me-2" role="status"></div>
        <strong>Memproses data...</strong>
      `;
      result.appendChild(spinner);
      result.scrollTop = result.scrollHeight;

      // Mainkan suara beep
      document.getElementById("beepSound").play();

      // Setelah 1 detik baru ambil data dari server
      setTimeout(() => {
        fetch("app/rekam_absen_wa_api.php?id=" + encodeURIComponent(qrMessage))
          .then(res => res.json())
          .then(data => {
            // Hapus spinner
            spinner.remove();

            let alertDiv = document.createElement("div");
            alertDiv.className = "alert alert-info mb-2";
            alertDiv.innerHTML = data.message;

            if (data.wa_link) {
              alertDiv.innerHTML += `
                <br><a id="waBtn" href="${data.wa_link}" target="_blank" class="btn btn-success mt-2">
                📲 Kirim WhatsApp</a>
              `;
              // Klik otomatis setelah 1 detik
              setTimeout(() => document.getElementById("waBtn").click(), 1000);
            } else {
              alertDiv.innerHTML += `
                <br><a href="?page=siswa" class="btn btn-warning mt-2">
                ➕ Tambahkan Nomor WA</a>
              `;
            }

            result.appendChild(alertDiv);
            result.scrollTop = result.scrollHeight;

            // Setelah 5 detik baru bisa scan lagi
            setTimeout(() => {
              scanCooldown = false;
            }, 5000);
          })
          .catch(err => {
            spinner.remove();
            console.error("Error:", err);
            result.innerHTML += `<div class="alert alert-danger">Terjadi kesalahan koneksi.</div>`;
  
          });
      }, 1000);
    }

    // Pengaturan scanner agar tidak terlalu sensitif
    let html5QrcodeScanner = new Html5QrcodeScanner(
      "reader",
      { fps: 5,
        qrbox: 250,
        disableFlip: false },
      false
    );
    html5QrcodeScanner.render(onScanSuccess);
  </script>
</div>
