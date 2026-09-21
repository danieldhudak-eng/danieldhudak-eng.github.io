<?php
/**
 * MEDIfogg web · odoslanie kontaktného formulára e-mailom (Websupport, PHP mail()).
 * Používa sa, keď je PUBLIC_FORM_PROVIDER=custom a PUBLIC_FORM_ENDPOINT=/api/kontakt.php.
 *
 * Nastavenie: upraviť $TO (príjemca) a $FROM (musí byť adresa na doméne hostovanej na Websupporte,
 * inak Websupport e-mail zahodí). Formulár posiela polia: meno, email, telefon, zaujem, sprava, suhlas,
 * zdroj, url + honeypot "website".
 */
declare(strict_types=1);

// --- Nastavenie -------------------------------------------------------------
$TO = 'daniel@opusmagnus.co'; // TODO(klient): sales@detectair.sk (+ kópia do CRM), keď bude rozhodnuté
$CC = '';                     // voliteľná kópia, napr. 'adam@opusmagnus.co'
$FROM = 'web@opusmagnus.co';  // odosielateľ na doméne hostingu (Websupport)
$SUBJECT_PREFIX = '[MEDIfogg web · dev] ';
$THANKS_URL = '/dakujeme';
$ALLOWED_HOSTS = ['medifogg.opusmagnus.co', 'development.opusmagnus.co', 'www.medifogg.sk', 'medifogg.sk', 'localhost', '127.0.0.1'];
// -----------------------------------------------------------------------------

header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$wantsJson = str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch');

function respond(int $status, array $payload, string $redirect, bool $json): void
{
    http_response_code($status);
    if ($json) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    } elseif ($status < 400) {
        header('Location: ' . $redirect, true, 303);
    } else {
        header('Content-Type: text/plain; charset=utf-8');
        echo $payload['error'] ?? 'Chyba';
    }
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    respond(405, ['ok' => false, 'error' => 'Method not allowed'], $THANKS_URL, $wantsJson);
}

// Rovnaký pôvod (Origin / Referer) – jednoduchá ochrana proti cudzím POSTom
$originHost = '';
foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $h) {
    if (!empty($_SERVER[$h])) {
        $originHost = (string) (parse_url($_SERVER[$h], PHP_URL_HOST) ?? '');
        break;
    }
}
if ($originHost !== '' && !in_array($originHost, $ALLOWED_HOSTS, true) && $originHost !== ($_SERVER['HTTP_HOST'] ?? '')) {
    respond(403, ['ok' => false, 'error' => 'Forbidden'], $THANKS_URL, $wantsJson);
}

// Honeypot: boti vyplnia skryté pole → tvárime sa, že prešlo
if (!empty($_POST['website'])) {
    respond(200, ['ok' => true], $THANKS_URL, $wantsJson);
}

$field = static function (string $key, int $max = 2000): string {
    $v = (string) ($_POST[$key] ?? '');
    $v = trim(str_replace(["\r", "\0"], '', $v));
    return mb_substr($v, 0, $max);
};

$meno = $field('meno', 200);
$email = $field('email', 200);
$telefon = $field('telefon', 60);
$zaujem = $field('zaujem', 60);
$sprava = $field('sprava', 4000);
$suhlas = $field('suhlas', 10);
$zdroj = $field('zdroj', 200);
$url = $field('url', 500);

$errors = [];
if ($meno === '') {
    $errors['meno'] = 'Meno je povinné.';
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Neplatný e-mail.';
}
if ($suhlas !== 'ano') {
    $errors['suhlas'] = 'Chýba súhlas so spracovaním údajov.';
}
if ($errors) {
    respond(422, ['ok' => false, 'error' => 'Skontrolujte povinné polia.', 'fields' => $errors], $THANKS_URL, $wantsJson);
}

$labels = [
    'stretnutie' => 'Stretnutie a predstavenie systému',
    'obhliadka' => 'Obhliadku priestorov a návrh riešenia',
    'dokumentacia' => 'Dokumentáciu a certifikáty',
    'vyber-urovne' => 'Pomoc s výberom úrovne systému',
    'reporting' => 'Ukážku reportingu',
    'pilotne-meranie' => 'Pilotné meranie na našom pracovisku',
    'referencna-navsteva' => 'Referenčnú návštevu',
    'kalkulacia' => 'Presnú kalkuláciu pre naše pracovisko',
    'studia-nda' => 'Plnú klinickú štúdiu a merania (NDA)',
    'partnerstvo' => 'Partnerstvo / distribúciu',
    'znizenie-nakaz' => 'Zníženie nozokomiálnych nákaz',
    'vytazenost' => 'Vyťaženosť operačných sál',
    'bezpecnost-personalu' => 'Bezpečnosť personálu',
    'ine' => 'Iné',
];
$zaujemLabel = $labels[$zaujem] ?? ($zaujem !== '' ? $zaujem : '—');

$subject = $SUBJECT_PREFIX . 'Nový dopyt: ' . $zaujemLabel . ' – ' . $meno;
$lines = [
    'Nový dopyt z webu MEDIfogg',
    '',
    'Meno:        ' . $meno,
    'E-mail:      ' . $email,
    'Telefón:     ' . ($telefon !== '' ? $telefon : '—'),
    'Mám záujem o: ' . $zaujemLabel,
    '',
    'Správa:',
    $sprava !== '' ? $sprava : '—',
    '',
    '---',
    'Zdroj (stránka): ' . ($zdroj !== '' ? $zdroj : '—'),
    'URL:             ' . ($url !== '' ? $url : '—'),
    'Čas:             ' . date('Y-m-d H:i:s'),
    'IP:              ' . ($_SERVER['REMOTE_ADDR'] ?? '—'),
    'Súhlas GDPR:     áno',
];
$body = implode("\n", $lines);

$encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
$fromName = '=?UTF-8?B?' . base64_encode('MEDIfogg web') . '?=';
$headers = [
    'From: ' . $fromName . ' <' . $FROM . '>',
    'Reply-To: ' . $meno . ' <' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: medifogg-web',
];
if ($CC !== '') {
    $headers[] = 'Cc: ' . $CC;
}
// Reply-To s menom: odstrániť znaky, ktoré by rozbili hlavičku
$headers[1] = 'Reply-To: ' . preg_replace('/[^\p{L}\p{N} .\-]/u', '', $meno) . ' <' . $email . '>';

$sent = @mail($TO, $encodedSubject, $body, implode("\r\n", $headers), '-f' . $FROM);

if (!$sent) {
    error_log('[medifogg-web] mail() failed for ' . $email);
    respond(500, ['ok' => false, 'error' => 'E-mail sa nepodarilo odoslať.'], $THANKS_URL, $wantsJson);
}

respond(200, ['ok' => true], $THANKS_URL, $wantsJson);
