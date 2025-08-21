<?php
// app_inc.php - helper & konfigurasi bersama
session_start();
date_default_timezone_set('Asia/Jakarta');

$dataDir = __DIR__ . "/data";
if (!is_dir($dataDir)) mkdir($dataDir, 0777, true);
$dataCsv = $dataDir . "/dataguru.csv";

function h($s){ return htmlspecialchars($s ?? "", ENT_QUOTES, "UTF-8"); }
function fmt($n){ return rtrim(rtrim(number_format((float)$n, 2, ",", "."), "0"), ","); }

function detect_delimiter($line) {
    $candidates = [",", ";", "\t", "|"];
    $best = ";"; $max = -1;
    foreach ($candidates as $d) {
        $cnt = substr_count($line, $d);
        if ($cnt > $max) { $max = $cnt; $best = $d; }
    }
    return $best;
}

function norm_key($s){
    $s = strtolower(trim($s));
    if (substr($s, 0, 3) === "\xEF\xBB\xBF") $s = substr($s, 3); // hapus BOM
    $s = preg_replace('/[^a-z0-9]+/', '_', $s);
    return trim($s, '_');
}

function load_csv($file) {
    if (!file_exists($file)) return [[], []];
    $fh = fopen($file, "r");
    if ($fh === false) return [[], []];
    $first = fgets($fh);
    if ($first === false) { fclose($fh); return [[], []]; }
    $delim = detect_delimiter($first);
    rewind($fh);
    $header = fgetcsv($fh, 0, $delim);
    if (!$header) { fclose($fh); return [[], []]; }

    $keys = array_map('norm_key', $header);
    $rows = [];
    while (($row = fgetcsv($fh, 0, $delim)) !== false) {
        if (count($row) < count($keys)) $row = array_pad($row, count($keys), "");
        $assoc = [];
        foreach ($keys as $i => $k) {
            $val = isset($row[$i]) ? trim($row[$i]) : "";
            $val = preg_replace('/\s+/u', ' ', $val);
            $assoc[$k] = $val;
        }
        $rows[] = $assoc;
    }
    fclose($fh);
    return [$keys, $rows];
}

function parse_number($raw) {
    if ($raw === "" || $raw === null) return 0.0;
    if (preg_match('/-?\d+(?:[.,]\d+)?/', (string)$raw, $m)) {
        $num = str_replace(',', '.', $m[0]); // koma -> titik
        return (float)$num;
    }
    return 0.0;
}

function normalize_id($s) { // untuk NUPTK: hapus spasi, jaga leading zero
    $s = trim((string)$s);
    return str_replace(' ', '', $s);
}

/** daftar alias kolom -> canonical */
function column_map() {
    return [
        "nuptk"     => ["nuptk", "nutpk", "no_nuptk", "nomor_nuptk", "no nuptk", "nomor nuptk"],
        "nama"      => ["nama", "nama_guru", "namaguru", "nama guru"],
        "mapel"     => ["mapel", "mata_pelajaran", "mata pelajaran", "subject"],
        "kelas"     => ["kelas", "rombel", "kelas_ajar", "kelas ajar"],
        "jam"       => ["jam", "jumlah_jam", "jml_jam", "jp", "jumlah jam", "total_jam", "total jam"],
        "tugas"     => ["tugas_tambahan", "tugas", "jabatan", "penugasan", "tugas tambahan"],
        "jam_tugas" => ["jam_tugas", "jp_tugas", "jumlah_jam_tugas", "jml_jam_tugas", "jam tugas", "jam_tambahan", "jam tambahan"],
    ];
}

/** cari kolom sebenarnya di header hasil normalisasi */
function map_columns($headers) {
    $map = column_map();
    $col = [];
    foreach ($map as $canon => $alts) {
        $found = "";
        foreach ($alts as $a) {
            $norm = norm_key($a);
            if (in_array($norm, $headers, true)) { $found = $norm; break; }
        }
        $col[$canon] = $found;
    }
    return $col;
}
