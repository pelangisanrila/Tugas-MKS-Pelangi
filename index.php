<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Peserta Bebras Challenge 2025</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Daftar Peserta Bebras Challenge 2025</h1>

<div class="school-list">
    <?php
    // Baca file JSON
    $json_file = 'data_sekolah.json';
    $json_data = file_get_contents($json_file);
    if ($json_data === false) {
        die("Error: Tidak dapat membaca file $json_file.");
    }
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        die("Error: Format JSON tidak valid di $json_file. " . json_last_error_msg());
    }

    // Kelompokkan data berdasarkan sekolah (untuk menampilkan daftar pendamping)
    $grouped_data = [];
    foreach ($data as $item) {
        $school_name = $item['sekolah'];
        if (!isset($grouped_data[$school_name])) {
            $grouped_data[$school_name] = [
                'pdf_file' => $item['pdf_file'], // Ambil PDF file dari entri pertama
                'pendamping_list' => []
            ];
        }
        // Gabungkan semua pendamping dan kode verifikasi untuk sekolah ini
        foreach ($item['pendamping'] as $idx => $pendamping_nama) {
             $grouped_data[$school_name]['pendamping_list'][] = [
                 'name' => $pendamping_nama,
                 'code' => $item['verification_codes'][$idx] // Ambil kode verifikasi yang sesuai
             ];
        }
    }

    // Urutkan daftar sekolah
    ksort($grouped_data);

    foreach ($grouped_data as $school_name => $school_info) {
        $sekolah_nama = htmlspecialchars($school_name);
        $pdf_file = $school_info['pdf_file'];
        $pendamping_list = $school_info['pendamping_list'];

        echo "<div class='school-item'>";
        echo "<h3>$sekolah_nama</h3>";
        echo "<ul class='pendamping-list'>";

        foreach ($pendamping_list as $pendamping) {
            $pendamping_nama = htmlspecialchars($pendamping['name']);
            $verification_code = $pendamping['code']; // Kode untuk verifikasi

            // Gunakan kode verifikasi sebagai identifier untuk tombol
            echo "<li>";
            echo "<strong>Pendamping:</strong> $pendamping_nama ";
            echo "<button class='download-btn' onclick='openModal(\"$pdf_file\", \"$verification_code\", \"$pendamping_nama\")'>Download PDF</button>";
            echo "</li>";
        }

        echo "</ul>";
        echo "</div>";
    }
    ?>
</div>

<!-- The Modal -->
<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Verifikasi Unduhan</h3>
        <p id="modal-pendamping-name"></p>
        <p>Masukkan 4 digit terakhir nomor telepon pendamping untuk mengunduh PDF.</p>
        <form id="verificationForm">
            <input type="hidden" id="pdfFileInput" name="pdf_file">
            <input type="hidden" id="expectedCodeInput" name="expected_code"> <!-- Simpan kode yang benar di sini -->
            <label for="verificationCode">Kode Verifikasi (4 Digit):</label>
            <input type="text" id="verificationCode" name="code_input" placeholder="XXXX" maxlength="4" required>
            <div id="errorMessage" class="error-message"></div>
            <button type="submit">Verifikasi dan Unduh</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById("myModal");

    function openModal(pdfFile, expectedCode, pendampingName) {
        document.getElementById("pdfFileInput").value = pdfFile;
        document.getElementById("expectedCodeInput").value = expectedCode; // Simpan kode yang benar
        document.getElementById("modal-pendamping-name").textContent = "Pendamping: " + pendampingName;
        document.getElementById("verificationCode").value = ""; // Kosongkan input user
        document.getElementById("errorMessage").textContent = ""; // Kosongkan error
        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }

    // Handle form submission (Client-Side Verification) - Updated for forced download
    document.getElementById("verificationForm").addEventListener("submit", function(event) {
        event.preventDefault(); // Prevent default form submission

        const input_code = document.getElementById("verificationCode").value.trim();
        const expected_code = document.getElementById("expectedCodeInput").value; // Ambil kode yang benar
        const errorMessageDiv = document.getElementById("errorMessage");
        const pdfFileToDownload = document.getElementById("pdfFileInput").value;

        // Clear previous error
        errorMessageDiv.textContent = "";

        if (input_code.length !== 4 || isNaN(input_code)) {
            errorMessageDiv.textContent = "Kode harus berupa 4 digit angka.";
            return;
        }

        if (input_code !== expected_code) {
            errorMessageDiv.textContent = "Kode verifikasi salah.";
            return;
        }

        // Jika kode benar, coba unduh file
        // Membuat URL absolut untuk file PDF
        const pdfUrl = `pdf_files/${encodeURIComponent(pdfFileToDownload)}`; // encodeURIComponent untuk keamanan nama file

        // Coba trigger download menggunakan fetch dan Blob
        // Ini lebih andal untuk memaksa download dibanding window.location.href
        fetch(pdfUrl)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`Gagal mengambil file: ${response.status} ${response.statusText}`);
                }
                return response.blob(); // Ambil file sebagai Blob
            })
            .then(blob => {
                // Buat URL objek untuk Blob
                const blobUrl = window.URL.createObjectURL(blob);

                // Buat link sementara untuk Blob
                const downloadLink = document.createElement('a');
                downloadLink.href = blobUrl;
                downloadLink.download = pdfFileToDownload; // Nama file saat diunduh

                // Simulasikan klik pada link download
                document.body.appendChild(downloadLink);
                downloadLink.click();

                // Hapus link sementara dan bebaskan URL objek
                document.body.removeChild(downloadLink);
                window.URL.revokeObjectURL(blobUrl);

                // Tutup modal setelah download dimulai
                closeModal();
            })
            .catch(error => {
                console.error('Error saat mengunduh file:', error);
                errorMessageDiv.textContent = 'Gagal mengunduh file. Silakan coba lagi nanti.';
            });

    });
</script>

</body>
</html>