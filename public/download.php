<?php
// public/download.php
session_start();

// Konfigurasi rate limit sederhana
$max_attempts = 5;
$window_seconds = 300; // 5 menit
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$attempt_log = __DIR__ . '/../logs/download_attempts.log';
$success_log = __DIR__ . '/../logs/download.log';

// Param wajib
if (!isset($_POST['pendamping_key'], $_POST['pdf_file'], $_POST['code_input'])) {
    http_response_code(400);
    die("Parameter tidak lengkap.");
}

$pend_key = $_POST['pendamping_key'];
$pdf_file = basename($_POST['pdf_file']);
$code_input = trim($_POST['code_input']);

if (!preg_match('/^\d{4}$/', $code_input)) {
    die("Kode verifikasi harus 4 digit angka.");
}

// Hitung percobaan dalam window
$lines = [];
if (file_exists($attempt_log)) {
    $lines = file($attempt_log, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
}
$now = time();
$recent_count = 0;
foreach ($lines as $ln) {
    list($t, $lip) = explode("\t", $ln) + [0, ''];
    if (($now - (int)$t) <= $window_seconds && $lip === $ip) $recent_count++;
}
if ($recent_count >= $max_attempts) {
    die("Terlalu banyak percobaan. Coba lagi nanti.");
}

// Load JSON server-side
$json_path = __DIR__ . '/../private/data_sekolah.json';
if (!file_exists($json_path)) {
    http_response_code(500);
    die("Konfigurasi server bermasalah.");
}
$data = json_decode(file_get_contents($json_path), true);
if (!is_array($data)) {
    http_response_code(500);
    die("Format data bermasalah.");
}

// Temukan pendamping berdasarkan pendamping_key (hash)
$found = false;
$expected_code = null;
$allowed_pdf = null;
foreach ($data as $entry) {
    if (!isset($entry['sekolah'], $entry['pendamping'], $entry['verification_codes'], $entry['pdf_file'])) continue;
    $school = $entry['sekolah'];
    foreach ($entry['pendamping'] as $i => $pname) {
        $key = hash('sha256', $school . '::' . $i);
        if (hash_equals($key, $pend_key)) {
            $found = true;
            $expected_code = (string)$entry['verification_codes'][$i];
            $allowed_pdf = basename($entry['pdf_file']);
            break 2;
        }
    }
}

if (!$found) {
    // log attempt
    file_put_contents($attempt_log, $now . "\t" . $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
    die("Pendamping tidak ditemukan.");
}

// Pastikan file yang diminta sesuai yang terdaftar untuk pendamping tersebut
if ($pdf_file !== $allowed_pdf) {
    file_put_contents($attempt_log, $now . "\t" . $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
    die("Permintaan file tidak valid.");
}

// Verifikasi kode (konstan time)
if (!hash_equals($expected_code, $code_input)) {
    file_put_contents($attempt_log, $now . "\t" . $ip . PHP_EOL, FILE_APPEND | LOCK_EX);
    die("Kode verifikasi salah.");
}

// Path file aman di private/pdf_files
$file_path = realpath(__DIR__ . '/../private/pdf_files/' . $pdf_file);
$private_dir = realpath(__DIR__ . '/../private/pdf_files/');
if ($file_path === false || strpos($file_path, $private_dir) !== 0) {
    http_response_code(500);
    die("File tidak dapat diakses.");
}
if (!file_exists($file_path) || !is_readable($file_path)) {
    http_response_code(404);
    die("File tidak ditemukan.");
}

// Log successful download
file_put_contents($success_log, date('c') . "\t" . $ip . "\t" . $pdf_file . PHP_EOL, FILE_APPEND | LOCK_EX);

// Kirim file
header('Content-Description: File Transfer');
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="'.basename($file_path).'"');
header('Content-Length: ' . filesize($file_path));
readfile($file_path);
exit;
