<?php
/**
 * MEDIfogg web · spoločná knižnica formulára: konfigurácia a ukladanie dopytov (12. 10. 2026).
 *
 * Konfigurácia je v súbore MIMO webrootu (nikdy v repe): premenná prostredia MEDIFOGG_FORM_CONFIG alebo
 * <adresár nad DOCUMENT_ROOT>/medifogg-form.php. Vzor: docs/forms/medifogg-form.example.php. Bez súboru platia
 * predvolené hodnoty nižšie a zoznam dopytov (/dopyty/) je vypnutý.
 *
 * Dopyty sa ukladajú do SQLite (PDO) v adresári mimo webrootu (predvolene <nad DOCUMENT_ROOT>/medifogg-data/).
 * Ak PDO SQLite chýba, zapisujú sa do CSV v tom istom adresári.
 */
declare(strict_types=1);

date_default_timezone_set('Europe/Bratislava');

// Knižnica sa nesmie volať priamo (Apache to blokuje aj cez .htaccess, php -S nie).
if (basename((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === '_form.php') {
    http_response_code(404);
    exit;
}

function form_config(): array
{
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }
    $above = dirname($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
    $path = getenv('MEDIFOGG_FORM_CONFIG') ?: $above . '/medifogg-form.php';
    $file = [];
    $error = '';
    if (is_file($path)) {
        // Poistka: súbor bez <?php alebo s textom mimo PHP by sa inak vypísal do stránky (aj s hashom hesla)
        ob_start();
        try {
            $ret = require $path;
        } catch (Throwable $e) {
            $ret = null;
            $error = 'Chyba v medifogg-form.php: ' . $e->getMessage();
        } finally {
            $stray = (string) ob_get_clean();
        }
        if ($stray !== '') {
            $error = 'medifogg-form.php obsahuje text mimo PHP: súbor musí začínať <?php a obsahovať return [ ... ];';
        } elseif (!is_array($ret)) {
            $error = $error ?: 'medifogg-form.php nevracia pole: chýba return [ ... ];';
        }
        if ($error !== '') {
            error_log('[medifogg-web] ' . $error);
        } else {
            $file = $ret;
        }
    }
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    $isProd = (bool) preg_match('/(^|\.)medifogg\.com$/', $host);
    $cfg = $file + [
        // Príjemca a odosielateľ. Odosielateľ musí byť na doméne hostovanej na tom istom hostingu (SPF).
        'to' => 'daniel@opusmagnus.co',
        'cc' => '',
        'from' => $isProd ? 'web@medifogg.com' : 'web@opusmagnus.co',
        'subject_prefix' => $isProd ? '[MEDIfogg web] ' : '[MEDIfogg web · dev] ',
        'storage_dir' => $above . '/medifogg-data',
        // Odosielanie cez SMTP schránky (spoľahlivejšie ako mail()). Prázdne smtp_user = PHP mail().
        // Websupport: smtp.m1.websupport.sk, 465 (ssl) alebo 587 (tls). Heslo len tu, mimo webrootu.
        'smtp_host' => 'smtp.m1.websupport.sk',
        'smtp_port' => 465,
        'smtp_secure' => 'ssl',
        'smtp_user' => '',
        'smtp_pass' => '',
        // Zoznam dopytov /dopyty/: prihlásenie HTTP Basic, heslo len ako hash (password_hash). Bez hashu je vypnutý.
        'admin_user' => 'medifogg',
        'admin_password_hash' => '',
    ];
    $cfg['admin_password_hash'] = trim((string) $cfg['admin_password_hash']);
    $cfg['_config_found'] = $file !== [];
    $cfg['_config_error'] = $error;
    return $cfg;
}

/** PDO na SQLite alebo null, ak SQLite nie je dostupné alebo adresár nie je zapisovateľný. */
function form_db(): ?PDO
{
    static $pdo = false;
    if ($pdo !== false) {
        return $pdo;
    }
    $pdo = null;
    $dir = form_storage_dir();
    if ($dir === null || !in_array('sqlite', PDO::getAvailableDrivers(), true)) {
        return null;
    }
    try {
        $dbFile = $dir . '/dopyty.sqlite';
        $pdo = new PDO('sqlite:' . $dbFile, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        @chmod($dbFile, 0600);
        $pdo->exec('CREATE TABLE IF NOT EXISTS dopyty (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            created_at TEXT NOT NULL,
            meno TEXT NOT NULL,
            email TEXT NOT NULL,
            telefon TEXT,
            zaujem TEXT,
            zaujem_label TEXT,
            sprava TEXT,
            zdroj TEXT,
            url TEXT,
            host TEXT,
            mail_sent INTEGER NOT NULL DEFAULT 0,
            stav TEXT NOT NULL DEFAULT \'nový\'
        )');
    } catch (Throwable $e) {
        error_log('[medifogg-web] SQLite: ' . $e->getMessage());
        $pdo = null;
    }
    return $pdo;
}

function form_storage_dir(): ?string
{
    $dir = (string) form_config()['storage_dir'];
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        error_log('[medifogg-web] nedá sa vytvoriť ' . $dir);
        return null;
    }
    if (!is_file($dir . '/.htaccess')) {
        // Poistka, ak by adresár omylom skončil vo webroote
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }
    return is_writable($dir) ? $dir : null;
}

/** Uloží dopyt. Vráti ID (SQLite), 0 (CSV) alebo null pri chybe. */
function form_save(array $row): ?int
{
    $row += ['created_at' => date('Y-m-d H:i:s'), 'host' => (string) ($_SERVER['HTTP_HOST'] ?? ''), 'mail_sent' => 0];
    $cols = ['created_at', 'meno', 'email', 'telefon', 'zaujem', 'zaujem_label', 'sprava', 'zdroj', 'url', 'host', 'mail_sent'];
    $pdo = form_db();
    if ($pdo) {
        try {
            $st = $pdo->prepare('INSERT INTO dopyty (' . implode(',', $cols) . ') VALUES (' . implode(',', array_fill(0, count($cols), '?')) . ')');
            $st->execute(array_map(static fn ($c) => $row[$c] ?? '', $cols));
            return (int) $pdo->lastInsertId();
        } catch (Throwable $e) {
            error_log('[medifogg-web] uloženie dopytu: ' . $e->getMessage());
        }
    }
    $dir = form_storage_dir();
    if ($dir === null) {
        return null;
    }
    $fh = @fopen($dir . '/dopyty.csv', 'ab');
    if (!$fh) {
        return null;
    }
    fputcsv($fh, array_map(static fn ($c) => (string) ($row[$c] ?? ''), $cols), ';');
    fclose($fh);
    return 0;
}

function form_mark_sent(?int $id): void
{
    $pdo = form_db();
    if ($pdo && $id) {
        $pdo->prepare('UPDATE dopyty SET mail_sent = 1 WHERE id = ?')->execute([$id]);
    }
}

/** Hlavička: ASCII ostane čitateľné (meno v úvodzovkách), inak RFC 2047 base64. */
function form_mime_word(string $s, bool $isName = false): string
{
    if (!preg_match('/[^\x20-\x7E]/', $s)) {
        return $isName ? '"' . addcslashes($s, '"\\') . '"' : $s;
    }
    return '=?UTF-8?B?' . base64_encode($s) . '?=';
}

/** Zapíše/prečíta poslednú chybu odosielania (zobrazí sa v /dopyty/). */
function form_mail_error(?string $msg = null): string
{
    $dir = form_storage_dir();
    if ($dir === null) {
        return '';
    }
    $file = $dir . '/posledna-chyba-emailu.txt';
    if ($msg !== null) {
        @file_put_contents($file, date('Y-m-d H:i:s') . ' · ' . $msg);
        @chmod($file, 0600);
        return $msg;
    }
    return is_file($file) ? (string) file_get_contents($file) : '';
}

/**
 * Odošle e-mail: SMTP (ak je smtp_user) alebo PHP mail(). $to a $cc sú adresy oddelené čiarkou.
 * Vráti true pri úspechu; dôvod chyby uloží cez form_mail_error().
 */
function form_send_mail(string $to, string $cc, string $from, string $fromName, string $replyName, string $replyEmail, string $subject, string $body): bool
{
    $cfg = form_config();
    $encSubject = form_mime_word($subject);
    // Mená v hlavičkách kódovať len pri diakritike (zbytočné base64 zvyšuje spamové skóre), Reply-To len adresa
    $headers = [
        'From: ' . form_mime_word($fromName, true) . ' <' . $from . '>',
        'Reply-To: <' . $replyEmail . '>',
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: quoted-printable',
    ];
    if ($cc !== '') {
        $headers[] = 'Cc: ' . $cc;
    }
    // quoted-printable namiesto base64: čitateľnejšie pre spamové filtre (base64 pri obyčajnom texte zvyšuje skóre)
    $encBody = quoted_printable_encode(str_replace(["\r\n", "\n"], ["\n", "\r\n"], $body));

    if ((string) $cfg['smtp_user'] !== '') {
        return form_smtp_send($cfg, $to, $cc, $from, $encSubject, $headers, $encBody);
    }
    $ok = @mail($to, $encSubject, $encBody, implode("\r\n", $headers), '-f' . $from);
    if (!$ok) {
        // Niektoré hostingy odmietajú parameter -f
        $ok = @mail($to, $encSubject, $encBody, implode("\r\n", $headers));
    }
    if (!$ok) {
        $last = error_get_last();
        form_mail_error('PHP mail() zlyhal' . ($last ? ': ' . $last['message'] : '') . '. Odporúčanie: SMTP (smtp_user, smtp_pass v medifogg-form.php).');
    }
    return $ok;
}

function form_smtp_send(array $cfg, string $to, string $cc, string $from, string $encSubject, array $headers, string $encBody): bool
{
    $host = (string) $cfg['smtp_host'];
    $port = (int) $cfg['smtp_port'];
    $secure = (string) $cfg['smtp_secure'];
    $fail = static function (string $why) use (&$fp): bool {
        form_mail_error('SMTP: ' . $why);
        if (is_resource($fp)) {
            @fwrite($fp, "QUIT\r\n");
            fclose($fp);
        }
        return false;
    };
    $fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15);
    if (!$fp) {
        return $fail("pripojenie na $host:$port zlyhalo ($errstr)");
    }
    stream_set_timeout($fp, 15);
    $read = static function () use ($fp): string {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $data;
    };
    $cmd = static function (string $c, array $okCodes) use ($fp, $read): array {
        if ($c !== '') {
            fwrite($fp, $c . "\r\n");
        }
        $r = $read();
        return [in_array((int) substr($r, 0, 3), $okCodes, true), trim($r)];
    };
    $ehloHost = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    [$ok, $r] = $cmd('', [220]);
    if (!$ok) return $fail("úvod servera: $r");
    [$ok, $r] = $cmd('EHLO ' . $ehloHost, [250]);
    if (!$ok) return $fail("EHLO: $r");
    if ($secure === 'tls') {
        [$ok, $r] = $cmd('STARTTLS', [220]);
        if (!$ok || !stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) return $fail("STARTTLS: $r");
        [$ok, $r] = $cmd('EHLO ' . $ehloHost, [250]);
        if (!$ok) return $fail("EHLO po TLS: $r");
    }
    [$ok, $r] = $cmd('AUTH LOGIN', [334]);
    if (!$ok) return $fail("AUTH: $r");
    [$ok, $r] = $cmd(base64_encode((string) $cfg['smtp_user']), [334]);
    if (!$ok) return $fail("meno: $r");
    [$ok, $r] = $cmd(base64_encode((string) $cfg['smtp_pass']), [235]);
    if (!$ok) return $fail('prihlásenie odmietnuté (skontrolujte smtp_user a smtp_pass)');
    [$ok, $r] = $cmd('MAIL FROM:<' . $from . '>', [250]);
    if (!$ok) return $fail("MAIL FROM: $r");
    $rcpts = array_filter(array_map('trim', explode(',', $to . ($cc !== '' ? ',' . $cc : ''))));
    foreach ($rcpts as $rc) {
        [$ok, $r] = $cmd('RCPT TO:<' . $rc . '>', [250, 251]);
        if (!$ok) return $fail("RCPT TO $rc: $r");
    }
    [$ok, $r] = $cmd('DATA', [354]);
    if (!$ok) return $fail("DATA: $r");
    $domain = substr(strrchr($from, '@') ?: '@localhost', 1);
    $msg = array_merge(
        ['Date: ' . date('r'), 'To: ' . $to, 'Subject: ' . $encSubject, 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $domain . '>'],
        $headers
    );
    $data = implode("\r\n", $msg) . "\r\n\r\n" . $encBody;
    $data = preg_replace('/^\./m', '..', $data);
    [$ok, $r] = $cmd($data . "\r\n.", [250]);
    if (!$ok) return $fail("odoslanie: $r");
    $cmd('QUIT', [221]);
    fclose($fp);
    return true;
}
