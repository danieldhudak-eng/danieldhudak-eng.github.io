<?php
/**
 * MEDIfogg web · interný zoznam dopytov z formulára (/dopyty/), 12. 10. 2026.
 * Prihlásenie: meno a hash hesla v medifogg-form.php mimo webrootu (admin_user, admin_password_hash). Bez hashu je stránka vypnutá.
 * Funkcie: zoznam (najnovšie hore), označiť ako vybavený, zmazať (GDPR), export CSV pre Excel.
 */
declare(strict_types=1);

require dirname(__DIR__) . '/api/_form.php';

header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

$cfg = form_config();
$hash = (string) $cfg['admin_password_hash'];

// HTTPS aj za proxy Websupportu (openresty posiela X-Forwarded-Proto)
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
session_name('mf_dopyty');
session_set_cookie_params(['lifetime' => 0, 'path' => '/dopyty/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
ini_set('session.use_strict_mode', '1');
session_start();
// Odhlásenie po 30 minútach nečinnosti
$IDLE = 1800;
if (!empty($_SESSION['ok']) && time() - (int) ($_SESSION['last'] ?? 0) > $IDLE) {
    $_SESSION = [];
    session_regenerate_id(true);
}
$_SESSION['last'] = time();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$csrf = $_SESSION['csrf'];

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function page(string $title, string $body): never
{
    echo '<!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<meta name="robots" content="noindex, nofollow"><title>' . e($title) . ' · MEDIfogg</title><style>'
        . ':root{--ink:#14313d;--teal:#1b9ba4;--line:#d9e6ea;--bg:#f4f9fa;--muted:#5b717a}'
        . '*{box-sizing:border-box}body{margin:0;font:15px/1.5 system-ui,-apple-system,"Segoe UI",sans-serif;color:var(--ink);background:var(--bg)}'
        . '.wrap{max-width:1400px;margin:0 auto;padding:24px 16px}h1{font-size:22px;font-weight:600;margin:0 0 4px}'
        . '.bar{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:16px}'
        . '.muted{color:var(--muted);font-size:13px}a{color:var(--teal)}'
        . '.btn{display:inline-block;border:1px solid var(--teal);background:var(--teal);color:#fff;border-radius:8px;padding:7px 12px;font:inherit;font-size:13px;cursor:pointer;text-decoration:none}'
        . '.btn--ghost{background:#fff;color:var(--ink);border-color:var(--line)}.btn--danger{background:#fff;color:#a12d2d;border-color:#e6c2c2}'
        . '.tablewrap{overflow-x:auto;background:#fff;border:1px solid var(--line);border-radius:12px}'
        . 'table{border-collapse:collapse;width:100%;min-width:980px}th,td{text-align:left;vertical-align:top;padding:10px 12px;border-bottom:1px solid var(--line);font-size:14px}'
        . 'th{font-size:12px;text-transform:uppercase;letter-spacing:.04em;color:var(--muted);background:#fafcfd}tr.done td{opacity:.55}'
        . 'td.msg{max-width:420px;white-space:pre-wrap}form.inline{display:inline}'
        . '.pill{display:inline-block;border-radius:999px;padding:2px 8px;font-size:12px;background:#e3f3f4}.pill--warn{background:#fdecc8}'
        . '.login{max-width:360px;margin:12vh auto;background:#fff;border:1px solid var(--line);border-radius:12px;padding:24px}'
        . '.login label{display:block;font-size:13px;margin:12px 0 4px}.login input{width:100%;padding:9px 10px;border:1px solid var(--line);border-radius:8px;font:inherit}'
        . '.err{color:#a12d2d;font-size:13px;margin-top:10px}'
        . '</style></head><body>' . $body . '</body></html>';
    exit;
}

if ($hash === '') {
    http_response_code(503);
    $why = $cfg['_config_error'] !== '' ? 'Súbor medifogg-form.php sa nedá načítať: musí začínať <?php a obsahovať return [ ... ]; (vzor docs/forms/medifogg-form.example.php).' : 'Doplňte admin_password_hash do medifogg-form.php mimo webrootu (docs/forms/medifogg-form.example.php).';
    page('Dopyty', '<div class="login"><h1>Zoznam dopytov nie je zapnutý</h1><p class="muted">' . e($why) . '</p></div>');
}

// --- Prihlásenie / odhlásenie ---
/** Obmedzenie pokusov: po 5 neúspešných prihláseniach z jednej IP adresy 15 minút zákaz (súbor mimo webrootu). */
function login_attempts(string $ip, ?bool $failed = null): int
{
    $dir = form_storage_dir();
    if ($dir === null) {
        return 0;
    }
    $file = $dir . '/login-attempts.json';
    $fh = @fopen($file, 'c+');
    if (!$fh) {
        return 0;
    }
    flock($fh, LOCK_EX);
    $data = json_decode((string) stream_get_contents($fh), true) ?: [];
    $now = time();
    $data = array_filter($data, static fn ($v) => $now - (int) ($v['t'] ?? 0) < 900);
    $key = hash('sha256', $ip);
    if ($failed === true) {
        $data[$key] = ['n' => (int) ($data[$key]['n'] ?? 0) + 1, 't' => $now];
    } elseif ($failed === false) {
        unset($data[$key]);
    }
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($file, 0600);
    return (int) ($data[$key]['n'] ?? 0);
}

if (($_POST['akcia'] ?? '') === 'login') {
    // Klientská IP: REMOTE_ADDR; ak je to interná adresa proxy, posledná adresa z X-Forwarded-For (pridáva ju proxy hostingu)
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) && !empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $chain = array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']));
        $ip = (string) end($chain);
    }
    if (login_attempts($ip) >= 5) {
        http_response_code(429);
        $loginError = 'Príliš veľa neúspešných pokusov. Skúste to o 15 minút.';
    } else {
        $ok = hash_equals((string) $cfg['admin_user'], (string) ($_POST['meno'] ?? '')) && password_verify((string) ($_POST['heslo'] ?? ''), $hash);
        if ($ok) {
            login_attempts($ip, false);
            session_regenerate_id(true);
            $_SESSION['ok'] = true;
            $_SESSION['last'] = time();
            header('Location: ./', true, 303);
            exit;
        }
        login_attempts($ip, true);
        sleep(2);
        $loginError = 'Nesprávne meno alebo heslo.';
    }
}
if (($_GET['odhlasit'] ?? '') === '1') {
    $_SESSION = [];
    session_destroy();
    header('Location: ./', true, 303);
    exit;
}
if (empty($_SESSION['ok'])) {
    page('Prihlásenie', '<form class="login" method="post"><h1>Dopyty z webu</h1><p class="muted">MEDIfogg · interné</p>'
        . '<label for="meno">Meno</label><input id="meno" name="meno" autocomplete="username" required>'
        . '<label for="heslo">Heslo</label><input id="heslo" name="heslo" type="password" autocomplete="current-password" required>'
        . '<input type="hidden" name="akcia" value="login"><p style="margin-top:16px"><button class="btn" type="submit">Prihlásiť</button></p>'
        . (isset($loginError) ? '<p class="err">' . e($loginError) . '</p>' : '') . '</form>');
}

$pdo = form_db();

// --- Akcie (POST + CSRF) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    if (!hash_equals($csrf, (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        page('Chyba', '<div class="login"><p class="err">Neplatný formulár, obnovte stránku.</p></div>');
    }
    $id = (int) ($_POST['id'] ?? 0);
    $akcia = (string) ($_POST['akcia'] ?? '');
    if ($akcia === 'stav' && $id) {
        $pdo->prepare("UPDATE dopyty SET stav = CASE stav WHEN 'vybavený' THEN 'nový' ELSE 'vybavený' END WHERE id = ?")->execute([$id]);
    } elseif ($akcia === 'zmazat' && $id) {
        $pdo->prepare('DELETE FROM dopyty WHERE id = ?')->execute([$id]);
    }
    header('Location: ./', true, 303);
    exit;
}

$cols = ['id' => 'ID', 'created_at' => 'Čas', 'meno' => 'Meno', 'email' => 'E-mail', 'telefon' => 'Telefón', 'zaujem_label' => 'Záujem', 'sprava' => 'Správa', 'zdroj' => 'Stránka', 'url' => 'URL', 'mail_sent' => 'E-mail odoslaný', 'stav' => 'Stav'];

// --- Export CSV (Excel: bodkočiarka, UTF-8 BOM) ---
if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="medifogg-dopyty-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'wb');
    fwrite($out, "\xEF\xBB\xBF");
    if ($pdo) {
        fputcsv($out, array_values($cols), ';');
        foreach ($pdo->query('SELECT ' . implode(',', array_keys($cols)) . ' FROM dopyty ORDER BY id DESC') as $r) {
            $r['mail_sent'] = $r['mail_sent'] ? 'áno' : 'nie';
            fputcsv($out, array_map(static fn ($c) => (string) $r[$c], array_keys($cols)), ';');
        }
    } elseif (($dir = form_storage_dir()) && is_file($dir . '/dopyty.csv')) {
        readfile($dir . '/dopyty.csv');
    }
    exit;
}

// --- Zoznam ---
$rows = $pdo ? $pdo->query('SELECT * FROM dopyty ORDER BY id DESC LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC) : [];
$total = $pdo ? (int) $pdo->query('SELECT COUNT(*) FROM dopyty')->fetchColumn() : 0;
$new = $pdo ? (int) $pdo->query("SELECT COUNT(*) FROM dopyty WHERE stav <> 'vybavený'")->fetchColumn() : 0;

$html = '<div class="wrap"><div class="bar"><div><h1>Dopyty z webu</h1><p class="muted">Spolu ' . $total . ', nevybavených ' . $new . '. Údaje sú osobné, nezdieľajte export mimo firmy.</p></div>'
    . '<div><a class="btn" href="?export=csv">Export CSV</a> <a class="btn btn--ghost" href="?odhlasit=1">Odhlásiť</a></div></div>';
if (!$pdo) {
    $html .= '<p class="err">SQLite nie je na serveri dostupné, dopyty sa ukladajú do CSV (stiahnete cez Export CSV).</p>';
}
$mailErr = form_mail_error();
if ($mailErr !== '') {
    $html .= '<p class="err">Posledná chyba odosielania e-mailu: ' . e($mailErr) . '</p>';
}
$html .= '<p class="muted">Odosielanie: ' . ((string) $cfg['smtp_user'] !== '' ? 'SMTP ' . e((string) $cfg['smtp_host']) . ' ako ' . e((string) $cfg['smtp_user']) : 'PHP mail()') . ' · príjemca ' . e((string) $cfg['to']) . ' · odosielateľ ' . e((string) $cfg['from']) . '</p>';
$html .= '<div class="tablewrap"><table><thead><tr><th>Čas</th><th>Meno a kontakt</th><th>Záujem</th><th>Správa</th><th>Stránka</th><th>Stav</th><th></th></tr></thead><tbody>';
foreach ($rows as $r) {
    $done = $r['stav'] === 'vybavený';
    $html .= '<tr class="' . ($done ? 'done' : '') . '">'
        . '<td>' . e($r['created_at']) . '<br><span class="muted">#' . (int) $r['id'] . '</span></td>'
        . '<td><strong>' . e($r['meno']) . '</strong><br><a href="mailto:' . e($r['email']) . '">' . e($r['email']) . '</a>'
        . ($r['telefon'] !== '' ? '<br><a href="tel:' . e(preg_replace('/\s+/', '', (string) $r['telefon'])) . '">' . e($r['telefon']) . '</a>' : '') . '</td>'
        . '<td>' . e($r['zaujem_label']) . '</td>'
        . '<td class="msg">' . e($r['sprava']) . '</td>'
        . '<td>' . e($r['zdroj']) . ($r['url'] !== '' ? '<br><a class="muted" href="' . e($r['url']) . '" rel="noopener" target="_blank">otvoriť</a>' : '') . '</td>'
        . '<td><span class="pill">' . e($r['stav']) . '</span>' . ((int) $r['mail_sent'] ? '' : '<br><span class="pill pill--warn">e-mail neodišiel</span>') . '</td>'
        . '<td><form class="inline" method="post"><input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
        . '<button class="btn btn--ghost" name="akcia" value="stav">' . ($done ? 'Vrátiť' : 'Vybavené') . '</button></form> '
        . '<form class="inline" method="post" onsubmit="return confirm(\'Natrvalo zmazať dopyt #' . (int) $r['id'] . '?\')"><input type="hidden" name="csrf" value="' . e($csrf) . '"><input type="hidden" name="id" value="' . (int) $r['id'] . '">'
        . '<button class="btn btn--danger" name="akcia" value="zmazat">Zmazať</button></form></td>'
        . '</tr>';
}
if (!$rows) {
    $html .= '<tr><td colspan="7" class="muted">Zatiaľ žiadne dopyty.</td></tr>';
}
$html .= '</tbody></table></div></div>';
page('Dopyty', $html);
