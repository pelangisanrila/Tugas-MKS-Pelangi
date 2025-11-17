<?php
// public/index.php
// Membaca data dari private/data_sekolah.json (server-side)
$json_path = __DIR__ . '/../private/data_sekolah.json';
if (!file_exists($json_path)) {
    die("Data tidak ditemukan. Hubungi admin.");
}
$json = json_decode(file_get_contents($json_path), true);
if (!is_array($json)) {
    die("Format data tidak valid.");
}

// Build listing tanpa mengekspos kode verifikasi
$grouped = [];
foreach ($json as $entry) {
    if (!isset($entry['sekolah'], $entry['pendamping'], $entry['verification_codes'], $entry['pdf_file'])) continue;
    $school = $entry['sekolah'];
    if (!isset($grouped[$school])) {
        $grouped[$school] = [
            'pdf_file' => basename($entry['pdf_file']),
            'pendamping' => []
        ];
    }
    foreach ($entry['pendamping'] as $i => $pname) {
        // pendamping_key adalah hash (identifier) — TIDAK mengandung kode verifikasi
        $pend_key = hash('sha256', $school . '::' . $i);
        $grouped[$school]['pendamping'][] = [
            'name' => $pname,
            'key'  => $pend_key,
            'index'=> $i
        ];
    }
}
ksort($grouped);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Daftar Peserta Bebras Challenge 2025</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Daftar Peserta Bebras Challenge 2025</h1>

<div class="school-list">
<?php foreach ($grouped as $school => $info): ?>
  <div class="school-item">
    <h3><?= htmlspecialchars($school) ?></h3>
    <ul class="pendamping-list">
      <?php foreach ($info['pendamping'] as $p): ?>
        <li>
          <strong>Pendamping:</strong> <?= htmlspecialchars($p['name']) ?>
          <button class="download-btn" onclick="openModal('<?= htmlspecialchars($info['pdf_file']) ?>','<?= $p['key'] ?>','<?= htmlspecialchars($p['name']) ?>')">Download PDF</button>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>
<?php endforeach; ?>
</div>

<!-- Modal (UI serupa aslinya) -->
<div id="myModal" class="modal" style="display:none;">
  <div class="modal-content">
    <span class="close" onclick="closeModal()" style="cursor:pointer">&times;</span>
    <h3>Verifikasi Unduhan</h3>
    <p id="modal-pendamping-name"></p>
    <p>Masukkan 4 digit terakhir nomor telepon pendamping untuk mengunduh PDF.</p>

    <form id="verificationForm" method="POST" action="download.php" autocomplete="off">
      <input type="hidden" name="pendamping_key" id="pendamping_key">
      <input type="hidden" name="pdf_file" id="pdf_file">
      <label for="code_input">Kode Verifikasi (4 digit):</label>
      <input type="text" id="code_input" name="code_input" maxlength="4" pattern="\d{4}" required>
      <div id="errorMessage" class="error-message" style="color:red"></div>
      <button type="submit">Verifikasi dan Unduh</button>
    </form>
  </div>
</div>

<script>
function openModal(pdfFile, pendKey, pendName) {
    document.getElementById('pdf_file').value = pdfFile;
    document.getElementById('pendamping_key').value = pendKey;
    document.getElementById('modal-pendamping-name').textContent = 'Pendamping: ' + pendName;
    document.getElementById('code_input').value = '';
    document.getElementById('errorMessage').textContent = '';
    document.getElementById('myModal').style.display = 'block';
}
function closeModal() {
    document.getElementById('myModal').style.display = 'none';
}
window.onclick = function(e) {
    if (e.target === document.getElementById('myModal')) closeModal();
};
</script>
</body>
</html>
