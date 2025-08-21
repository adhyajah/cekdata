<?php
require_once __DIR__ . "/app_inc.php";

$uploadMsg = "";

/* Composer autoload (untuk XLSX). CSV tidak butuh. */
$autoload_ok = false;
foreach ([__DIR__ . '/vendor/autoload.php', __DIR__ . '/../vendor/autoload.php', __DIR__ . '/../../vendor/autoload.php'] as $p) {
    if (file_exists($p)) { require $p; $autoload_ok = true; break; }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "upload") {
    if (!isset($_FILES["file"]) || $_FILES["file"]["error"] !== UPLOAD_ERR_OK) {
        $uploadMsg = "Gagal mengunggah file.";
    } else {
        $tmp  = $_FILES["file"]["tmp_name"];
        $name = $_FILES["file"]["name"];
        $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if ($ext === "csv") {
            if (!move_uploaded_file($tmp, $dataCsv)) {
                if (!@copy($tmp, $dataCsv)) $uploadMsg = "Gagal menyimpan CSV.";
                else $uploadMsg = "CSV berhasil diunggah.";
            } else {
                $uploadMsg = "CSV berhasil diunggah.";
            }
        } elseif (in_array($ext, ["xlsx", "xls"])) {
            if ($autoload_ok && class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
                try {
                    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp);
                    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Csv');
                    $writer->setDelimiter(';');
                    $writer->setEnclosure('"');
                    $writer->setSheetIndex(0);
                    $writer->save($dataCsv);
                    $uploadMsg = "XLSX berhasil dikonversi ke CSV.";
                } catch (Throwable $e) {
                    $uploadMsg = "Gagal mengonversi XLSX. Pesan: " . $e->getMessage();
                }
            } else {
                $uploadMsg = "PhpSpreadsheet belum tersedia. Ekspor Excel ke CSV (delimiter ;) lalu unggah. "
                           . "Atau instal via Composer: composer require phpoffice/phpspreadsheet";
            }
        } else {
            $uploadMsg = "Format tidak didukung. Unggah file .csv atau .xlsx/.xls.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <title>Unggah Data Guru</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
<div class="container">
  <h1>Unggah / Perbarui Data Guru</h1>
  <div class="card">
    <h2>Unggah File Data</h2>
    <form method="post" enctype="multipart/form-data">
      <input type="hidden" name="action" value="upload" />
      <input type="file" name="file" accept=".csv,.xlsx,.xls" required />
      <button type="submit">Unggah</button>
    </form>

    <?php if ($uploadMsg): ?>
      <p class="msg"><?= h($uploadMsg) ?></p>
    <?php endif; ?>

    <p class="hint">Minimal kolom: <code>nuptk, nama_guru, mata_pelajaran, kelas, jumlah_jam</code> — opsional: <code>tugas_tambahan</code>, <code>jam_tugas</code>. Nama kolom fleksibel.</p>
    <p class="hint">File data saat ini: <?= file_exists($dataCsv) ? ("<strong>data/dataguru.csv</strong> (".number_format(filesize($dataCsv))." bytes)") : "<em>Belum ada</em>"; ?></p>

    <?php if (!$autoload_ok): ?>
      <p class="hint" style="color:#b45309">Catatan: unggah Excel langsung butuh PhpSpreadsheet.</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
