<?php

// ==========================
// Fungsi Backup
// ==========================
if (isset($_POST['backup'])) {
    $tables = [];
    $result = $conn->query("SHOW TABLES");
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    $sqlScript = "";
    foreach ($tables as $table) {
        // Struktur tabel
        $result = $conn->query("SHOW CREATE TABLE $table");
        $row = $result->fetch_row();

        // Tambahkan DROP TABLE IF EXISTS
        $sqlScript .= "\n\nDROP TABLE IF EXISTS `$table`;\n";
        $sqlScript .= $row[1] . ";\n\n";

        // Data tabel
        $result = $conn->query("SELECT * FROM $table");
        $columnCount = $result->field_count;

        while ($row = $result->fetch_row()) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            for ($j = 0; $j < $columnCount; $j++) {
                $row[$j] = addslashes($row[$j]);
                $row[$j] = str_replace("\n", "\\n", $row[$j]);
                $sqlScript .= isset($row[$j]) ? "'$row[$j]'" : "''";
                if ($j < ($columnCount - 1)) {
                    $sqlScript .= ',';
                }
            }
            $sqlScript .= ");\n";
        }
        $sqlScript .= "\n"; 
    }

    if (!empty($sqlScript)) {
        $backup_file_name = 'absensiqr ' . date('Ymd') . '.sql';
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename=' . $backup_file_name);
        echo $sqlScript;
        exit;
    }
}

// ==========================
// Fungsi Restore
// ==========================
if (isset($_POST['restore'])) {
    if (isset($_FILES['restore_file']['tmp_name']) && $_FILES['restore_file']['tmp_name'] != '') {
        $filename = $_FILES['restore_file']['tmp_name'];
        $sql = file_get_contents($filename);

        // Pisahkan query berdasarkan ;
        $queries = explode(";\n", $sql);
        $success = true;
        $errors = [];

        // Matikan foreign key check sementara
        $conn->query("SET FOREIGN_KEY_CHECKS=0");

        foreach ($queries as $query) {
            $query = trim($query);
            if ($query != '') {
                if (!$conn->query($query)) {
                    $success = false;
                    $errors[] = "Error: " . $conn->error . "\nQuery: " . $query;
                }
            }
        }

        // Aktifkan kembali
        $conn->query("SET FOREIGN_KEY_CHECKS=1");

        if ($success) {
            echo "<div class='alert alert-success text-center m-3'>Restore berhasil!</div>";
        } else {
            echo "<div class='alert alert-danger text-center m-3'>Restore gagal!</div>";
            echo "<pre style='background:#f8f9fa;padding:10px;border:1px solid #ddd;max-height:300px;overflow:auto'>";
            echo implode("\n\n", $errors);
            echo "</pre>";
        }
    } else {
        echo "<div class='alert alert-warning text-center m-3'>Silakan pilih file SQL untuk di-restore.</div>";
    }
}
?>

    
    <style>
        /* Kecilkan tombol di layar kecil */
        @media (max-width: 576px) {
            .btn-lg {
                font-size: 0.9rem;
                padding: 0.5rem 1rem;
            }
            h3 {
                font-size: 1.3rem;
            }
        }
    </style>

<div class="bg-light">

<div class="container py-3">
    <div class="card shadow-lg mx-auto" style="max-width: 500px;">
        <div class="card-header bg-primary text-white text-center">
            <h3>Backup & Restore Database</h3>
        </div>
        <div class="card-body">

            <!-- Form Backup -->
            <form method="post" class="d-grid gap-2 mb-4">
                <button type="submit" name="backup" class="btn btn-success btn-lg">
                    <i class="bi bi-download"></i> Backup Database
                </button>
            </form>

            <hr>

            <!-- Form Restore -->
            <form method="post" enctype="multipart/form-data" class="d-grid gap-2 mb-4">
                <input type="file" class="form-control mb-3" name="restore_file" accept=".sql" required>
                <button type="submit" name="restore" class="btn btn-warning btn-lg">
                    <i class="bi bi-upload"></i> Restore Database
                </button>
            </form>

            <hr>

            <!-- Tombol Kembali -->
            <div class="d-grid">
                <a href="dashboard.php" class="btn btn-secondary btn-lg">
                    <i class="bi bi-arrow-left-circle"></i> Kembali ke Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

