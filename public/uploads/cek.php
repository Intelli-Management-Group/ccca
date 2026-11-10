<?php
/**
 * ================================================================
 * SUPER MAILER - VERSI LENGKAP (FIX FROM NAME + SMTP + BULK)
 * ================================================================
 * Fitur:
 * - Pilihan driver: SMTP (native) atau mail() bawaan PHP
 * - From Name tampil di email penerima
 * - Kirim massal ke banyak penerima
 * - Lampiran (attachment)
 * - Background process (bisa tutup browser)
 * - Form responsif
 * - Log file
 * ================================================================
 */

// === JIKA ADA SUBMIT (POST), PROSES KIRIM EMAIL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit'])) {
    
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ignore_user_abort(true);
    set_time_limit(0);
    
    header('Content-Type: text/plain; charset=utf-8');
    if (ob_get_level()) ob_end_clean();
    echo "🚀 PROSES PENGIRIMAN DIMULAI... (Browser boleh ditutup)\n\n";
    flush();

    // --- Ambil data dari form ---
    $driver = $_POST['driver'];
    $from_email = trim($_POST['from_email']);
    $from_name = trim($_POST['from_name']);
    $subject = trim($_POST['subject']);
    $html_body = $_POST['html_body'];
    $text_body = $_POST['text_body'];
    $delay = (int) $_POST['delay'];

    // --- Parsing penerima ---
    $recipients = array();
    $lines = explode("\n", $_POST['recipients']);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $line, $matches)) {
            $name = trim($matches[1]);
            $email = trim($matches[2]);
            $recipients[] = array('email' => $email, 'name' => $name);
        } else {
            $recipients[] = array('email' => $line, 'name' => '');
        }
    }

    // --- Konfigurasi ---
    $config = array(
        'driver' => $driver,
        'from_email' => $from_email,
        'from_name' => $from_name,
        'log_file' => 'mail_log.txt',
        'debug' => isset($_POST['debug']),
    );

    if ($driver === 'smtp') {
        $config['smtp_host'] = trim($_POST['smtp_host']);
        $config['smtp_port'] = (int) $_POST['smtp_port'];
        $config['smtp_user'] = trim($_POST['smtp_user']);
        $config['smtp_pass'] = trim($_POST['smtp_pass']);
        $config['smtp_encryption'] = $_POST['smtp_encryption'];
    }

    // --- Lampiran ---
    $attachments = array();
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $_FILES['attachment']['tmp_name'];
        $name = $_FILES['attachment']['name'];
        $attachments[] = array('path' => $tmp_name, 'name' => $name);
    }

    // ============================================================
    // 1. CLASS SMTP (DENGAN FROM NAME)
    // ============================================================
    class SimpleSMTP {
        private $socket, $host, $port, $username, $password, $encryption, $timeout = 30, $debug = false;
        public function __construct($host, $port, $username, $password, $encryption = 'tls', $debug = false) {
            $this->host = $host; $this->port = $port; $this->username = $username; $this->password = $password; $this->encryption = $encryption; $this->debug = $debug;
        }
        private function getResponse() {
            $data = '';
            while ($line = fgets($this->socket, 1024)) {
                $data .= $line;
                $this->debugLog("S: " . trim($line));
                if (isset($line[3]) && $line[3] === ' ') break;
            }
            return $data;
        }
        private function sendCommand($cmd) {
            $this->debugLog("C: " . trim($cmd));
            fputs($this->socket, $cmd . "\r\n");
            return $this->getResponse();
        }
        private function debugLog($msg) {
            if ($this->debug) file_put_contents('smtp_debug.log', date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
        }
        public function connect() {
            $host = $this->host;
            if ($this->encryption === 'ssl') $host = 'ssl://' . $host;
            $this->socket = fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
            if (!$this->socket) throw new Exception("Gagal koneksi: $errstr ($errno)");
            $resp = $this->getResponse();
            if (substr($resp, 0, 3) !== '220') throw new Exception("Tidak 220: $resp");
            $resp = $this->sendCommand('EHLO ' . gethostname());
            if (substr($resp, 0, 3) !== '250') throw new Exception("EHLO gagal: $resp");
            if ($this->encryption === 'tls') {
                $resp = $this->sendCommand('STARTTLS');
                if (substr($resp, 0, 3) !== '220') throw new Exception("STARTTLS gagal: $resp");
                stream_context_set_option($this->socket, 'ssl', 'verify_peer', false);
                stream_context_set_option($this->socket, 'ssl', 'verify_peer_name', false);
                if (!stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new Exception("Gagal TLS.");
                }
                $resp = $this->sendCommand('EHLO ' . gethostname());
                if (substr($resp, 0, 3) !== '250') throw new Exception("EHLO ulang gagal: $resp");
            }
            $resp = $this->sendCommand('AUTH LOGIN');
            if (substr($resp, 0, 3) !== '334') throw new Exception("AUTH LOGIN gagal: $resp");
            $resp = $this->sendCommand(base64_encode($this->username));
            if (substr($resp, 0, 3) !== '334') throw new Exception("User ditolak: $resp");
            $resp = $this->sendCommand(base64_encode($this->password));
            if (substr($resp, 0, 3) !== '235') throw new Exception("Pass ditolak: $resp");
            return true;
        }
        
        // ======================================================
        // PERBAIKAN: FUNGSI sendMail DENGAN PARAMETER $fromName
        // ======================================================
        public function sendMail($from, $fromName, $to, $subject, $htmlBody, $textBody = '', $attachments = array()) {
            $resp = $this->sendCommand("MAIL FROM: <$from>");
            if (substr($resp, 0, 3) !== '250') throw new Exception("MAIL FROM gagal: $resp");
            $resp = $this->sendCommand("RCPT TO: <$to>");
            if (substr($resp, 0, 3) !== '250' && substr($resp, 0, 3) !== '251') throw new Exception("RCPT TO gagal: $resp");
            $resp = $this->sendCommand('DATA');
            if (substr($resp, 0, 3) !== '354') throw new Exception("DATA gagal: $resp");
            
            $boundary = '----=_Part_' . md5(uniqid());
            // === INI PERBAIKAN UTAMA: GABUNGKAN FROM NAME ===
            $fromHeader = $fromName ? $this->encodeHeader($fromName) . " <$from>" : $from;
            $headers = array(
                "MIME-Version: 1.0",
                "From: $fromHeader",
                "To: $to",
                "Subject: " . $this->encodeHeader($subject),
                "Content-Type: multipart/mixed; boundary=\"$boundary\""
            );
            
            $body = '';
            $altBoundary = $boundary . '_alt';
            $body .= "--$boundary\r\n";
            $body .= "Content-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
            
            $plain = $textBody ?: strip_tags($htmlBody);
            $body .= "--$altBoundary\r\n";
            $body .= "Content-Type: text/plain; charset=utf-8\r\n";
            $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n$plain\r\n\r\n";
            
            if ($htmlBody) {
                $body .= "--$altBoundary\r\n";
                $body .= "Content-Type: text/html; charset=utf-8\r\n";
                $body .= "Content-Transfer-Encoding: 8bit\r\n\r\n$htmlBody\r\n\r\n";
            }
            $body .= "--$altBoundary--\r\n\r\n";
            
            foreach ($attachments as $att) {
                if (!is_file($att['path'])) continue;
                $data = file_get_contents($att['path']);
                $mime = function_exists('mime_content_type') ? mime_content_type($att['path']) : 'application/octet-stream';
                $fname = $att['name'] ?: basename($att['path']);
                $body .= "--$boundary\r\n";
                $body .= "Content-Type: $mime; name=\"{$fname}\"\r\n";
                $body .= "Content-Disposition: attachment; filename=\"{$fname}\"\r\n";
                $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
                $body .= chunk_split(base64_encode($data)) . "\r\n";
            }
            $body .= "--$boundary--\r\n";

            $payload = implode("\r\n", $headers) . "\r\n\r\n" . $body;
            fputs($this->socket, $payload . "\r\n.\r\n");

            $resp = $this->getResponse();
            if (substr($resp, 0, 3) !== '250') throw new Exception("DATA gagal dikirim: $resp");
            return true;
        }
        public function quit() { if ($this->socket) { $this->sendCommand('QUIT'); fclose($this->socket); } }
        private function encodeHeader($str) {
            if (preg_match('/[^\x20-\x7E]/', $str)) return '=?UTF-8?B?' . base64_encode($str) . '?=';
            return $str;
        }
    }

    // ============================================================
    // 2. CLASS BULK MAILER (DENGAN FROM NAME)
    // ============================================================
    class BulkMailer {
        private $config, $logFile, $sentCount = 0, $failCount = 0;
        public function __construct($config) { $this->config = $config; $this->logFile = $config['log_file'] ?: 'mail_log.txt'; }
        private function log($msg) {
            file_put_contents($this->logFile, date('Y-m-d H:i:s') . ' ' . $msg . "\n", FILE_APPEND);
            echo $msg . "\n"; flush();
        }
        public function sendBulk($recipients, $subject, $htmlBody, $textBody = '', $attachments = array(), $delayMicroseconds = 500000) {
            $driver = $this->config['driver'];
            $this->log("📨 Driver: " . strtoupper($driver) . " | Total: " . count($recipients));
            $smtp = null;
            if ($driver === 'smtp') {
                try {
                    $smtp = new SimpleSMTP($this->config['smtp_host'], $this->config['smtp_port'], $this->config['smtp_user'], $this->config['smtp_pass'], $this->config['smtp_encryption'], $this->config['debug']);
                    $smtp->connect();
                    $this->log("✅ Koneksi SMTP berhasil.");
                } catch (Exception $e) { $this->log("❌ Koneksi SMTP gagal: " . $e->getMessage()); return; }
            }
            foreach ($recipients as $index => $recip) {
                $email = trim($recip['email']); $name = trim($recip['name'] ?: '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $this->log("⚠️ Lewati: $email"); $this->failCount++; continue; }
                try {
                    if ($driver === 'smtp') { 
                        // === PERBAIKAN: KIRIMKAN FROM_NAME ===
                        $smtp->sendMail(
                            $this->config['from_email'],
                            $this->config['from_name'],
                            $email,
                            $subject,
                            $htmlBody,
                            $textBody,
                            $attachments
                        );
                    } else { 
                        $this->sendViaMail($email, $name, $subject, $htmlBody, $textBody, $attachments);
                    }
                    $this->sentCount++; $this->log("[" . ($index+1) . "] ✅ $email");
                } catch (Exception $e) { 
                    $this->failCount++; $this->log("[" . ($index+1) . "] ❌ $email: " . $e->getMessage()); 
                }
                if ($delayMicroseconds > 0) usleep($delayMicroseconds);
            }
            if ($smtp) $smtp->quit();
            $this->log("\n✨ Selesai! Terkirim: {$this->sentCount}, Gagal: {$this->failCount}");
        }
        private function sendViaMail($to, $name, $subject, $htmlBody, $textBody, $attachments) {
            $from = $this->config['from_email'];
            $fromName = $this->config['from_name'] ?: '';
            $toFormatted = $name ? "$name <$to>" : $to;
            $subjectEnc = $this->encodeHeader($subject);
            $boundary = '----=_Part_' . md5(uniqid());
            // === SUDAH PAKAI FROM NAME ===
            $fromHeader = $fromName ? $this->encodeHeader($fromName) . " <$from>" : $from;
            $headers = array(
                "MIME-Version: 1.0",
                "From: $fromHeader",
                "Content-Type: multipart/mixed; boundary=\"$boundary\""
            );
            $body = ''; $altBoundary = $boundary . '_alt';
            $body .= "--$boundary\r\nContent-Type: multipart/alternative; boundary=\"$altBoundary\"\r\n\r\n";
            $plain = $textBody ?: strip_tags($htmlBody);
            $body .= "--$altBoundary\r\nContent-Type: text/plain; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$plain\r\n\r\n";
            if ($htmlBody) $body .= "--$altBoundary\r\nContent-Type: text/html; charset=utf-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$htmlBody\r\n\r\n";
            $body .= "--$altBoundary--\r\n\r\n";
            foreach ($attachments as $att) {
                if (!is_file($att['path'])) continue;
                $data = file_get_contents($att['path']); $mime = function_exists('mime_content_type') ? mime_content_type($att['path']) : 'application/octet-stream';
                $fname = $att['name'] ?: basename($att['path']);
                $body .= "--$boundary\r\nContent-Type: $mime; name=\"{$fname}\"\r\nContent-Disposition: attachment; filename=\"{$fname}\"\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($data)) . "\r\n";
            }
            $body .= "--$boundary--\r\n";
            if (!mail($toFormatted, $subjectEnc, $body, implode("\r\n", $headers))) throw new Exception("mail() false.");
        }
        private function encodeHeader($str) {
            if (preg_match('/[^\x20-\x7E]/', $str)) return '=?UTF-8?B?' . base64_encode($str) . '?=';
            return $str;
        }
    }

    // --- EKSEKUSI ---
    try {
        $mailer = new BulkMailer($config);
        $mailer->sendBulk($recipients, $subject, $html_body, $text_body, $attachments, $delay);
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage();
        file_put_contents('mail_log.txt', date('Y-m-d H:i:s') . ' ERROR: ' . $e->getMessage() . "\n", FILE_APPEND);
    }
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Mailer - Form</title>
    <style>
        /* RESET & GLOBAL RESPONSIF */
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif; 
            background: #f0f2f5; 
            padding: 15px; 
        }
        .container {
            max-width: 860px; 
            margin: 0 auto; 
            background: #ffffff; 
            padding: 25px 20px; 
            border-radius: 12px; 
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        }
        h1 { 
            font-size: 1.8rem; 
            color: #1a1a2e; 
            border-bottom: 3px solid #007bff; 
            padding-bottom: 12px; 
            margin-bottom: 20px; 
            display: flex; 
            align-items: center; 
            gap: 10px; 
        }
        .warning {
            background: #fff8e1; 
            border-left: 4px solid #f5a623; 
            padding: 14px 18px; 
            border-radius: 6px; 
            font-size: 0.9rem; 
            color: #5d4b1a; 
            margin-bottom: 25px;
            line-height: 1.6;
        }
        .warning strong { color: #b7410e; }

        /* FORM ELEMEN */
        label {
            display: block; 
            font-weight: 600; 
            font-size: 0.95rem; 
            margin: 18px 0 6px 0; 
            color: #1e293b;
        }
        input[type="text"], input[type="number"], input[type="password"], select, textarea {
            width: 100%; 
            padding: 12px 14px; 
            border: 1px solid #d1d5db; 
            border-radius: 8px; 
            font-size: 1rem; 
            background: #fafafa; 
            transition: 0.2s;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; 
            border-color: #007bff; 
            box-shadow: 0 0 0 3px rgba(0,123,255,0.15);
            background: #fff;
        }
        textarea { 
            min-height: 90px; 
            font-family: monospace; 
            resize: vertical; 
        }
        .row {
            display: flex; 
            gap: 16px; 
            flex-wrap: wrap; 
        }
        .col {
            flex: 1; 
            min-width: 200px; 
        }
        .field-group {
            background: #f8fafc; 
            padding: 18px 16px; 
            border-radius: 10px; 
            margin: 12px 0 8px 0; 
            border-left: 5px solid #007bff;
        }
        .field-group.smtp {
            border-left-color: #28a745;
        }
        .field-group h4 {
            font-size: 1rem; 
            margin-bottom: 12px; 
            color: #0f172a;
        }
        small {
            display: block; 
            margin-top: 6px; 
            color: #6b7280; 
            font-size: 0.8rem;
        }
        button {
            background: #007bff; 
            color: #fff; 
            border: none; 
            padding: 16px 28px; 
            border-radius: 50px; 
            font-size: 1.1rem; 
            font-weight: 700; 
            cursor: pointer; 
            margin-top: 25px; 
            width: 100%; 
            transition: 0.2s;
            letter-spacing: 0.5px;
        }
        button:hover { background: #0056b3; transform: scale(1.01); }
        .debug-check {
            display: flex; 
            align-items: center; 
            gap: 10px; 
            margin-top: 18px;
            font-weight: 400;
        }
        .debug-check input[type="checkbox"] {
            width: 20px; 
            height: 20px; 
            margin: 0;
        }

        /* RESPONSIVE - HP */
        @media (max-width: 600px) {
            body { padding: 8px; }
            .container { padding: 16px 12px; }
            h1 { font-size: 1.4rem; }
            .row { flex-direction: column; gap: 8px; }
            .col { min-width: 100%; }
            button { padding: 14px 20px; font-size: 1rem; }
            .field-group { padding: 14px 12px; }
            input, select, textarea { font-size: 0.95rem; padding: 10px 12px; }
            .warning { font-size: 0.8rem; padding: 10px 12px; }
        }
        @media (max-width: 400px) {
            h1 { font-size: 1.2rem; flex-wrap: wrap; }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📧 Super Mailer - Form Kirim Massal</h1>
        
        <div class="warning">
            <strong>⚠️ Perhatian:</strong> 
            <br>• Untuk Gmail, pilih driver <strong>SMTP</strong> dan gunakan <strong>App Password</strong>.
            <br>• Proses tetap berjalan meskipun browser ditutup.
            <br>• <strong>From Name</strong> sekarang akan muncul di email penerima.
        </div>

        <form action="" method="post" enctype="multipart/form-data">
            <!-- DRIVER -->
            <label for="driver">Pilih Driver</label>
            <select name="driver" id="driver" onchange="toggleSmtp(this.value)">
                <option value="smtp">SMTP (Rekomendasi)</option>
                <option value="mail">mail() Bawaan PHP</option>
            </select>

            <!-- SMTP CONFIG -->
            <div id="smtp_config" class="field-group smtp">
                <h4>⚙️ Konfigurasi SMTP</h4>
                <div class="row">
                    <div class="col">
                        <label>Host</label>
                        <input type="text" name="smtp_host" value="smtp.gmail.com">
                    </div>
                    <div class="col">
                        <label>Port</label>
                        <input type="number" name="smtp_port" value="587">
                    </div>
                </div>
                <div class="row">
                    <div class="col">
                        <label>Username</label>
                        <input type="text" name="smtp_user" placeholder="email@gmail.com">
                    </div>
                    <div class="col">
                        <label>Password</label>
                        <input type="password" name="smtp_pass" placeholder="App Password">
                    </div>
                </div>
                <label>Security</label>
                <select name="smtp_encryption">
                    <option value="tls">TLS (587)</option>
                    <option value="ssl">SSL (465)</option>
                    <option value="none">None (25)</option>
                </select>
            </div>

            <!-- FROM (dengan FROM NAME) -->
            <div class="field-group">
                <h4>✉️ Pengirim (From)</h4>
                <div class="row">
                    <div class="col">
                        <label>From Email</label>
                        <input type="text" name="from_email" placeholder="sender@domain.com" required>
                    </div>
                    <div class="col">
                        <label>From Name</label>
                        <input type="text" name="from_name" placeholder="Nama Pengirim (akan muncul di email)">
                    </div>
                </div>
            </div>

            <!-- PENERIMA -->
            <label>📋 Daftar Penerima <small>(satu per baris, format: Nama &lt;email&gt;)</small></label>
            <textarea name="recipients" placeholder="Budi &lt;budi@mail.com&gt;&#10;Ani &lt;ani@mail.com&gt;" required></textarea>

            <!-- SUBJEK & ISI -->
            <label>📌 Subject</label>
            <input type="text" name="subject" placeholder="Judul Email" required>

            <label>📄 Isi HTML</label>
            <textarea name="html_body" rows="6" placeholder="&lt;h1&gt;Halo&lt;/h1&gt;"></textarea>

            <label>📄 Isi Teks (Alternatif)</label>
            <textarea name="text_body" rows="3" placeholder="Halo, ini teks biasa."></textarea>

            <!-- LAMPIRAN -->
            <label>📎 Lampiran (Opsional)</label>
            <input type="file" name="attachment" accept=".pdf,.doc,.docx,.png,.jpg,.zip">

            <!-- DELAY -->
            <label>⏱️ Jeda (mikrodetik)</label>
            <input type="number" name="delay" value="500000">
            <small>Rekomendasi: 500000 (0.5 detik) agar tidak kena blokir spam.</small>

            <!-- DEBUG -->
            <div class="debug-check">
                <input type="checkbox" name="debug" value="1" id="debug">
                <label for="debug" style="display:inline; font-weight:400; margin:0;">Aktifkan Debug (buat file log SMTP)</label>
            </div>

            <button type="submit" name="submit">🚀 Kirim Sekarang</button>
        </form>
    </div>

    <script>
        function toggleSmtp(val) {
            var el = document.getElementById('smtp_config');
            el.style.display = (val === 'smtp') ? 'block' : 'none';
        }
        // Jalankan saat halaman dimuat
        document.addEventListener('DOMContentLoaded', function() {
            toggleSmtp(document.getElementById('driver').value);
        });
    </script>
</body>
</html>