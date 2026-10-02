<?php
/**
 * Lead submission proxy.
 *
 * The browser posts the registration form here; this script validates it,
 * adds server-side fields (IP, country, funnel) and forwards it to the
 * Supabase "submit-lead-nullypto" function using the affiliate API key from
 * .env. The API key never leaves the server.
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

const MAX_BODY_BYTES     = 4096;
const RATE_LIMIT_MAX     = 5;   // submissions...
const RATE_LIMIT_WINDOW  = 600; // ...per IP per 10 minutes
const UPSTREAM_TIMEOUT   = 15;

function respond(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function fail(int $status, string $message, array $errors = []): never
{
    respond($status, ['success' => false, 'message' => $message, 'errors' => (object) $errors]);
}

/** Minimal .env loader: KEY=VALUE lines, # comments, optional quotes. */
function load_env(string $path): array
{
    if (!is_readable($path)) {
        return [];
    }
    $env = [];
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#' || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[-1] === $value[0]) {
            $value = substr($value, 1, -1);
        }
        $env[$key] = $value;
    }
    return $env;
}

function client_ip(bool $trustCloudflare): string
{
    if ($trustCloudflare && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])
        && filter_var($_SERVER['HTTP_CF_CONNECTING_IP'], FILTER_VALIDATE_IP)) {
        return $_SERVER['HTTP_CF_CONNECTING_IP'];
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/** Simple file-based fixed-window rate limit, stored outside the web root. */
function rate_limited(string $ip): bool
{
    $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'profitsolution-ratelimit';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        return false; // fail open rather than block real users
    }
    $file = $dir . DIRECTORY_SEPARATOR . hash('sha256', $ip) . '.json';
    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        return false;
    }
    flock($fh, LOCK_EX);
    $now  = time();
    $data = json_decode((string) stream_get_contents($fh), true);
    if (!is_array($data) || ($now - (int) ($data['start'] ?? 0)) > RATE_LIMIT_WINDOW) {
        $data = ['start' => $now, 'count' => 0];
    }
    $data['count']++;
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    flock($fh, LOCK_UN);
    fclose($fh);
    return $data['count'] > RATE_LIMIT_MAX;
}

// --- Request checks -------------------------------------------------------

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    fail(405, 'Metodo non consentito.');
}

// Same-origin only: reject cross-site posts when the browser sends Origin.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    $originHost = parse_url($origin, PHP_URL_HOST);
    $serverHost = strtolower(explode(':', (string) ($_SERVER['HTTP_HOST'] ?? ''))[0]);
    if (!is_string($originHost) || strtolower($originHost) !== $serverHost) {
        fail(403, 'Richiesta non consentita.');
    }
}

if (stripos((string) ($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json') !== 0) {
    fail(415, 'Formato della richiesta non valido.');
}

$raw = file_get_contents('php://input', false, null, 0, MAX_BODY_BYTES + 1);
if ($raw === false || strlen($raw) > MAX_BODY_BYTES) {
    fail(413, 'Richiesta troppo grande.');
}

$input = json_decode($raw, true);
if (!is_array($input)) {
    fail(400, 'Formato della richiesta non valido.');
}

$env = load_env(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');
$supabaseUrl = rtrim($env['SUPABASE_URL'] ?? '', '/');
$apiKey      = $env['AFFILIATE_API_KEY'] ?? '';
$funnel      = $env['LEAD_FUNNEL'] ?? 'profitsolutions';
$country     = $env['LEAD_COUNTRY'] ?? 'IT';
$supabaseHost = parse_url($supabaseUrl, PHP_URL_HOST);

if ($apiKey === '' || !is_string($supabaseHost) || parse_url($supabaseUrl, PHP_URL_SCHEME) !== 'https') {
    error_log('[submit-lead] Missing or invalid SUPABASE_URL / AFFILIATE_API_KEY in .env');
    fail(500, 'Servizio temporaneamente non disponibile. Riprova più tardi.');
}

// Honeypot: real users never fill this hidden field.
if (!empty($input['website'])) {
    fail(400, 'Impossibile completare la registrazione.');
}

$ip = client_ip(($env['TRUST_CLOUDFLARE'] ?? '0') === '1');
if (rate_limited($ip)) {
    fail(429, 'Troppi tentativi. Riprova tra qualche minuto.');
}

// --- Validation -----------------------------------------------------------

$str = static fn(string $k): string => is_string($input[$k] ?? null) ? trim($input[$k]) : '';

$firstname = $str('firstname');
$lastname  = $str('lastname');
$email     = $str('email');
$mobile    = preg_replace('/[\s\-().]/', '', $str('mobile')) ?? '';
$clickId   = $str('click_id');

$namePattern = "/^\p{L}[\p{L}\p{M}' .\-]{0,29}$/u";
$errors = [];

if (!preg_match($namePattern, $firstname)) {
    $errors['first_name'] = 'Inserisci un nome valido.';
}
if (!preg_match($namePattern, $lastname)) {
    $errors['last_name'] = 'Inserisci un cognome valido.';
}
if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
    $errors['email'] = 'Inserisci un indirizzo email valido.';
}
if (!preg_match('/^\+[1-9]\d{7,14}$/', $mobile)) {
    $errors['phone_number'] = 'Inserisci un numero di telefono valido.';
}
if ($clickId !== '' && !preg_match('/^[A-Za-z0-9._\-]{1,128}$/', $clickId)) {
    $clickId = ''; // drop malformed tracking values instead of rejecting the lead
}

if ($errors) {
    fail(422, 'Controlla i dati inseriti.', $errors);
}

// --- Forward to Supabase --------------------------------------------------

$payload = [
    'firstname'    => $firstname,
    'lastname'     => $lastname,
    'email'        => $email,
    'mobile'       => $mobile,
    'country_code' => $country,
    'ip_address'   => $ip,
    'click_id'     => $clickId,
    'funnel'       => $funnel,
];

$ch = curl_init($supabaseUrl . '/functions/v1/submit-lead-nullypto');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER     => [
        'Api-Key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => UPSTREAM_TIMEOUT,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_PROTOCOLS      => CURLPROTO_HTTPS,
]);
$body   = curl_exec($ch);
$status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($body === false) {
    error_log('[submit-lead] Upstream request failed: ' . $curlErr);
    fail(502, 'Servizio temporaneamente non disponibile. Riprova più tardi.');
}

$result = json_decode((string) $body, true);
$requestId = is_array($result) && is_string($result['request_id'] ?? null) ? $result['request_id'] : '-';

if ($status < 200 || $status >= 300 || !is_array($result) || ($result['success'] ?? false) !== true) {
    // Log for troubleshooting (no API key, no lead PII), show a generic message.
    $upstreamMsg = is_array($result) && is_string($result['message'] ?? null) ? $result['message'] : 'non-JSON response';
    error_log(sprintf('[submit-lead] Upstream rejected lead: status=%d request_id=%s message=%s',
        $status, $requestId, substr($upstreamMsg, 0, 200)));
    fail(502, 'Impossibile completare la registrazione. Verifica i dati o riprova più tardi.');
}

// Only redirect to an https URL on the Supabase host (or an explicitly allowed host).
$autologin = is_string($result['autologin_url'] ?? null) ? $result['autologin_url'] : '';
$allowedHosts = array_filter(array_map('trim', explode(',', $env['AUTOLOGIN_ALLOWED_HOSTS'] ?? '')));
$allowedHosts[] = $supabaseHost;
$autologinHost = parse_url($autologin, PHP_URL_HOST);

if (parse_url($autologin, PHP_URL_SCHEME) !== 'https'
    || !is_string($autologinHost)
    || !in_array(strtolower($autologinHost), array_map('strtolower', $allowedHosts), true)) {
    error_log(sprintf('[submit-lead] Rejected autologin_url host for request_id=%s', $requestId));
    fail(502, 'Registrazione ricevuta, ma il reindirizzamento non è disponibile. Ti contatteremo a breve.');
}

respond(200, ['success' => true, 'autologin_url' => $autologin]);
