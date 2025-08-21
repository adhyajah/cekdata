<?php
require_once __DIR__ . "/app_inc.php";

$hasil = [];
$nuptkInput = isset($_POST["nuptk"]) ? normalize_id($_POST["nuptk"]) : "";
$totalJamMengajar = 0.0;
$totalJamTugas = 0.0;
$recapKelas = [];   // jika tampilanmu "Kelas & Jumlah Jam"
$recapPairs = [];   // jika kamu pakai "Kelas, Mapel & Jumlah Jam" (pilih salah satu rendernya)
$recapTugas = [];
$nama = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && $nuptkInput !== "") {
    if (file_exists($dataCsv)) {
        list($headers, $rows) = load_csv($dataCsv);
        $col = map_columns($headers);

        $filtered = array_filter($rows, function($r) use ($col, $nuptkInput) {
            $key = $col["nuptk"];
            $val = $key ? normalize_id($r[$key] ?? "") : "";
            return $val !== "" && $val === $nuptkInput;
        });

        foreach ($filtered as $r) {
            $kelas = $col["kelas"] ? ($r[$col["kelas"]] ?? "") : "";
            $mapel = $col["mapel"] ? ($r[$col["mapel"]] ?? "") : "";
            $jMengajar = $col["jam"] ? parse_number($r[$col["jam"]] ?? "") : 0.0;

            // === FIX: hanya agregasi jika ada jam (>0), agar tidak ada baris kosong 0
            if ($jMengajar > 0) {
                // Mode 1: Rekap per Kelas
                $k = trim($kelas) === "" ? "(Tidak tercantum)" : $kelas;
                if (!isset($recapKelas[$k])) $recapKelas[$k] = 0.0;
                $recapKelas[$k] += $jMengajar;

                // Mode 2: Rekap per Kelas+Mapel (aktifkan rendernya di bawah)
                $km = (trim($kelas) === "" ? "(Tidak tercantum)" : $kelas)
                    . "||" .
                      (trim($mapel) === "" ? "(Tidak tercantum)" : $mapel);
                if (!isset($recapPairs[$km])) $recapPairs[$km] = 0.0;
                $recapPairs[$km] += $jMengajar;

                $totalJamMengajar += $jMengajar;
            }

            // Tugas tambahan (boleh 0 jam; tidak mengganggu tabel mengajar)
            $tugas  = $col["tugas"]     ? ($r[$col["tugas"]] ?? "") : "";
            $jTugas = $col["jam_tugas"] ? parse_number($r[$col["jam_tugas"]] ?? "") : 0.0;
            if ($tugas !== "" || $jTugas > 0) {
                $keyT = $tugas !== "" ? $tugas : "(Tugas Tambahan)";
                if (!isset($recapTugas[$keyT])) $recapTugas[$keyT] = 0.0;
                $recapTugas[$keyT] += $jTugas;
                $totalJamTugas     += $jTugas;
            }

            if ($col["nama"] && $nama === "") $nama = $r[$col["nama"]] ?? "";
        }

        $hasil = [
            "nama"        => $nama,
            "recapKelas"  => $recapKelas,
            "recapPairs"  => $recapPairs,
            "recapTugas"  => $recapTugas,
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Cek Rekap Jam Mengajar Guru — Pencarian</title>
  <link rel="stylesheet" href="style.css" />
</head>
<body>
<div class="container">
  <h1>Cek Rekap Jam Mengajar Guru</h1>
 <div class="card">
    <h2>Cari Berdasarkan NUPTK</h2>
    <form method="post">
      <label for="nuptk">NUPTK</label>
      <input id="nuptk" type="text" name="nuptk" value="<?=h($nuptkInput)?>" placeholder="Masukkan nomor NUPTK" required />
      <button type="submit">Cari</button>
    </form>

    <?php if ($nuptkInput): ?>
      <?php if (empty($hasil)): ?>
        <p class="msg">Data untuk NUPTK <strong><?=h($nuptkInput)?></strong> tidak ditemukan.</p>
      <?php else: ?>
        <p><strong>NUPTK :</strong> <?=h($nuptkInput)?></p>
        <p><strong>Nama :</strong> <?=h($hasil["nama"])?></p>

        <!-- ====== PILIH SALAH SATU TABEL REKAP MENGAJAR ====== -->

        <!-- A) Rekap per KELAS saja -->
            <h3>Rekapan Kelas, Mapel &amp; Jam Mengajar</h3>
        <div class="table-wrap">
          <table class="rtable">
            <thead><tr><th>Kelas</th><th>Mata Pelajaran</th><th>Jumlah Jam</th></tr></thead>
            <tbody>
              <?php ksort($hasil["recapPairs"]);
              foreach ($hasil["recapPairs"] as $key=>$jam):
                list($kelas,$mapel)=explode("||",$key,2); ?>
                <tr>
                  <td data-label="Kelas"><?=h($kelas)?></td>
                  <td data-label="Mata Pelajaran"><?=h($mapel)?></td>
                  <td data-label="Jumlah Jam"><?=fmt($jam)?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th colspan="2">Total Jam Mengajar</th><th><?=fmt($totalJamMengajar)?></th></tr></tfoot>
          </table>
        </div>    

        <!-- B) (OPSIONAL) Rekap per KELAS + MAPEL
             Jika ingin ini yang ditampilkan, pindahkan blok ini ke atas dan sembunyikan blok A -->
        <!--

        
        <h3>Rekapan Kelas &amp; Jam Mengajar</h3>
        <div class="table-wrap">
          <table class="rtable">
            <thead><tr><th>Kelas</th><th>Jumlah Jam</th></tr></thead>
            <tbody>
              <?php ksort($hasil["recapKelas"]);
              foreach ($hasil["recapKelas"] as $kelas=>$jam): ?>
                <tr>
                  <td data-label="Kelas"><?=h($kelas)?></td>
                  <td data-label="Jumlah Jam"><?=fmt($jam)?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
            <tfoot><tr><th>Total Jam Mengajar</th><th><?=fmt($totalJamMengajar)?></th></tr></tfoot>
          </table>
        </div>
        -->

        <h3>Rekapan Tugas Tambahan</h3>
        <div class="table-wrap">
          <table class="rtable">
            <thead><tr><th>Tugas</th><th>Jam</th></tr></thead>
            <tbody>
              <?php if (!empty($hasil["recapTugas"])): ?>
                <?php foreach ($hasil["recapTugas"] as $tugas=>$jam): ?>
                  <tr>
                    <td data-label="Tugas"><?=h($tugas)?></td>
                    <td data-label="Jam"><?=fmt($jam)?></td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr><td data-label="Tugas" colspan="2"><em>Tidak ada</em></td></tr>
              <?php endif; ?>
            </tbody>
            <tfoot><tr><th>Total Jam Tugas Tambahan</th><th><?=fmt($totalJamTugas)?></th></tr></tfoot>
          </table>
        </div>

        <h3>Total Keseluruhan</h3>
        <p><strong><?=fmt($totalJamMengajar + $totalJamTugas)?> JP</strong> (Jam Mengajar + Tugas Tambahan)</p>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <footer>
    <small>&copy; <?=date("Y")?> - Valid Ditinggal Invalid Dikejar</small>
  </footer>
</div>
</body>
</html>
