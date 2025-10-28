

  <style>
    body {
      background: #f8f9fa;
    }
    .menu-btn {
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 10px;
      font-size: 1rem;
      font-weight: 600;
      padding: 14px;
      border-radius: 12px;
    }
    .menu-btn i {
      font-size: 1.2rem;
      width: 24px;
      text-align: center;
    }
  </style>


  <header class="bg-white shadow-sm sticky-top">
    <div class="container py-3 d-flex justify-content-between align-items-center">
      <h1 class="h5 m-0">Pengaturan</h1>
      <a href="dashboard.php" class="btn btn-sm btn-outline-secondary">
        <i class="fa-solid fa-house"></i>
      </a>
    </div>
  </header>

  <main class="container py-4">
    <div class="alert alert-info small" style="border-radius: 10px;">
      <i class="fa-solid fa-circle-info"></i> 
      Sebelum melakukan tindakan penting, sebaiknya lakukan <strong>Backup</strong> terlebih dahulu.
    </div>

    <div class="d-grid gap-3">
      <a href="?page=backup_restore" class="btn btn-light border menu-btn">
        <i class="fa-solid fa-database text-primary"></i> Backup & Restore
      </a>
      <a href="?page=server-info" class="btn btn-light border menu-btn">
        <i class="fa-solid fa-code text-success"></i> Cek Versi PHP
      </a>
      <a href="?page=update_db" class="btn btn-light border menu-btn">
        <i class="fa-solid fa-database text-warning"></i> Update Versi Database
      </a>
      <a href="?page=cek_update" class="btn btn-light border menu-btn">
        <i class="fa-solid fa-rotate text-danger"></i> Cek Update Versi Aplikasi 
      </a>
    </div>
  </main>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

