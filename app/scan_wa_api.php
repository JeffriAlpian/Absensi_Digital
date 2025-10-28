
<div class="container mt-4">

  <a href="dashboard.php" class="btn btn-secondary mb-3">← Kembali</a>

  <div id="reader" style="width: 100%"></div>
  <div id="result" class="mt-3" style="max-height: 300px; overflow-y: auto;"></div>

  <!-- Suara beep -->
  <audio id="beepSound" src="app/beep.mp3" preload="auto"></audio>

  <script>
    function onScanSuccess(qrMessage) {
      fetch("app/rekam_absen_wa_api.php?nisn=" + qrMessage)
        .then(res => res.json())
        .then(data => {
          let result = document.getElementById("result");
          let alertDiv = document.createElement("div");
          alertDiv.className = "alert alert-info mb-2";
          alertDiv.innerHTML = data.message;

          if (data.wa_link) {
            // Jika ada nomor WA → tampilkan tombol WhatsApp
            alertDiv.innerHTML += `<br><a id="waBtn" href="${data.wa_link}" target="_blank" class="btn btn-success mt-2">📲 Kirim WhatsApp</a>`;
            
            // Klik otomatis setelah 1 detik
            setTimeout(() => {
              document.getElementById("waBtn").click();
            }, 1000);
          } else {
            // Jika no_wa kosong → tampilkan tombol Tambahkan nomor WA ke siswa.php
            alertDiv.innerHTML += `<br><a href="?page=siswa" class="btn btn-warning mt-2">➕ Tambahkan Nomor WA</a>`;
          }

          result.appendChild(alertDiv);

          // Mainkan suara beep
          document.getElementById("beepSound").play();

          // Scroll otomatis ke bawah
          result.scrollTop = result.scrollHeight;
        });
    }

    let html5QrcodeScanner = new Html5QrcodeScanner(
      "reader",
      { fps: 10, qrbox: 250 },
      false
    );
    html5QrcodeScanner.render(onScanSuccess);
  </script>
</div>

