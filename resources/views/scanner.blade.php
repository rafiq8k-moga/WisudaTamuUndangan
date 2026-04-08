<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Absensi Tamu - Scan QR Code</title>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }
        .header {
            text-align: center;
            color: white;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        .header p {
            opacity: 0.9;
            font-size: 0.9rem;
        }
        .scanner-container {
            background: white;
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
        }
        #reader {
            border-radius: 15px;
            overflow: hidden;
        }
        #reader video {
            border-radius: 15px;
        }
        .result-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.8);
            justify-content: center;
            align-items: center;
            z-index: 1000;
            padding: 20px;
        }
        .result-modal.active {
            display: flex;
        }
        .result-card {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            text-align: center;
            animation: slideUp 0.3s ease;
        }
        @keyframes slideUp {
            from { transform: translateY(50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .result-card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        .result-card .tamu-name {
            font-size: 1.5rem;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        .result-card .tamu-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 25px;
        }
        .btn {
            padding: 15px 40px;
            border: none;
            border-radius: 50px;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 600;
        }
        .btn-absen {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-absen:hover {
            transform: scale(1.05);
            box-shadow: 0 10px 30px rgba(102, 126, 234, 0.4);
        }
        .btn-absen:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-close {
            background: #f1f1f1;
            color: #333;
            margin-top: 10px;
        }
        .status {
            margin-top: 15px;
            padding: 10px;
            border-radius: 10px;
            font-size: 0.9rem;
        }
        .status.success {
            background: #d4edda;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            color: #721c24;
        }
        .status.info {
            background: #d1ecf1;
            color: #0c5460;
        }
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .already-absen {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Absensi Tamu Undangan</h1>
        <p>Arahkan kamera ke QR Code untuk scan</p>
    </div>

    <div class="scanner-container">
        <div id="reader"></div>
        <div id="scan-status" class="status info" style="display: none;"></div>
    </div>

    <div id="resultModal" class="result-modal">
        <div class="result-card">
            <h2>Data Tamu Terdeteksi</h2>
            <div id="tamuInfo"></div>
            <button id="btnAbsen" class="btn btn-absen" onclick="doAbsen()">Absen</button>
            <button class="btn btn-close" onclick="closeModal()">Batal / Scan Lagi</button>
        </div>
    </div>

    <script>
        let currentTamu = null;
        let html5QrcodeScanner = null;

        function onScanSuccess(decodedText, decodedResult) {
            try {
                const data = JSON.parse(decodedText);
                
                if (!data.id || !data.nama) {
                    showStatus('Format QR Code tidak valid', 'error');
                    return;
                }

                // Stop scanning while showing modal
                html5QrcodeScanner.pause();

                // Validate tamu data
                validateTamu(data);
            } catch (e) {
                showStatus('QR Code tidak valid: ' + e.message, 'error');
            }
        }

        function validateTamu(data) {
            const params = new URLSearchParams({ id: data.id, nama: data.nama });
            
            fetch('/api/validate-tamu?' + params)
                .then(response => response.json())
                .then(result => {
                    if (result.valid) {
                        currentTamu = result.data;
                        showModal(result.data);
                    } else {
                        showStatus('Data tamu tidak ditemukan', 'error');
                        html5QrcodeScanner.resume();
                    }
                })
                .catch(err => {
                    showStatus('Error validasi: ' + err.message, 'error');
                    html5QrcodeScanner.resume();
                });
        }

        function showModal(data) {
            const modal = document.getElementById('resultModal');
            const tamuInfo = document.getElementById('tamuInfo');
            const btnAbsen = document.getElementById('btnAbsen');

            if (data.absen) {
                tamuInfo.innerHTML = `
                    <div class="already-absen">
                        <strong>Tamu sudah absen!</strong><br>
                        Waktu: ${new Date(data.kapan_diabsen).toLocaleString('id-ID')}
                    </div>
                    <div class="tamu-name">${data.nama}</div>
                    <div class="tamu-label">Keluarga</div>
                `;
                btnAbsen.disabled = true;
                btnAbsen.textContent = 'Sudah Absen';
            } else {
                tamuInfo.innerHTML = `
                    <div class="tamu-name">${data.nama}</div>
                    <div class="tamu-label">Atas nama Keluarga</div>
                `;
                btnAbsen.disabled = false;
                btnAbsen.textContent = 'Absen';
            }

            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('resultModal').classList.remove('active');
            currentTamu = null;
            html5QrcodeScanner.resume();
        }

        function doAbsen() {
            if (!currentTamu || currentTamu.absen) return;

            const btn = document.getElementById('btnAbsen');
            btn.disabled = true;
            btn.innerHTML = '<span class="loading"></span> Memproses...';

            fetch('/api/absen', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({ tamu_id: currentTamu.id })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    btn.innerHTML = 'Berhasil!';
                    btn.style.background = '#28a745';
                    showStatus(`Absen berhasil untuk ${result.data.nama}`, 'success');
                    setTimeout(() => {
                        closeModal();
                    }, 1500);
                } else {
                    showStatus(result.message || 'Gagal absen', 'error');
                    btn.disabled = false;
                    btn.textContent = 'Coba Lagi';
                }
            })
            .catch(err => {
                showStatus('Error: ' + err.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Absen';
            });
        }

        function showStatus(message, type) {
            const status = document.getElementById('scan-status');
            status.textContent = message;
            status.className = 'status ' + type;
            status.style.display = 'block';
            
            if (type !== 'error') {
                setTimeout(() => {
                    status.style.display = 'none';
                }, 3000);
            }
        }

        // Initialize scanner
        html5QrcodeScanner = new Html5Qrcode("reader");
        
        const config = { fps: 10, qrbox: { width: 250, height: 250 } };
        
        html5QrcodeScanner.start(
            { facingMode: "environment" },
            config,
            onScanSuccess
        ).catch(err => {
            showStatus('Error kamera: ' + err.message, 'error');
        });
    </script>
</body>
</html>
