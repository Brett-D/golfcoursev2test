<?php
/*
 * Private settings page for the owner: golf rates, the cafe menu and specials, and the website colors.
 * It lives at agatebeachgolf.net/ownerpage and nothing on the site links to it. The password protects it, not the address.
 *
 * Password: ownerpage/password.php (copy password.example.php and type the password between the quotes). That file
 * stays on the server and is never put in GitHub.
 *
 * Saving writes ../data/rates.json, ../data/cafe.json, ../data/theme.css and ../data/theme.json. The copy each save
 * replaces goes to ../data/backups/ (the newest KEEP_BACKUPS of each file are kept).
 *
 * Needs PHP 7.4 or newer (same as contact.php).
 */
declare(strict_types=1);

const DATA_DIR = __DIR__ . '/../data';
const PASSWORD_FILE = __DIR__ . '/password.php';
const MIN_PASSWORD_LENGTH = 8;
const IDLE_MINUTES = 240;           // signed out after this long without using the page
const MAX_FAILS_PER_VISITOR = 5;    // wrong passwords from one address ...
const MAX_FAILS_EVERYONE = 30;      // ... or from everybody together ...
const FAIL_WINDOW_SECONDS = 900;    // ... within 15 minutes pause sign-in for the rest of that time
const KEEP_BACKUPS = 30;
const THEME_COLORS = ['--accent', '--accent-soft', '--navy', '--navy-deep', '--green', '--cream'];

date_default_timezone_set('America/Los_Angeles');

$nonce = base64_encode(random_bytes(16));
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'nonce-$nonce'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; font-src 'self'; connect-src 'self'; frame-src 'self'; frame-ancestors 'self'; base-uri 'none'; form-action 'self'; object-src 'none'");

// Sessions are kept in their own folder so other software on the host cannot clear them early.
$sessionDir = sys_get_temp_dir() . '/agate-owner-sessions';
if (!is_dir($sessionDir)) {
    @mkdir($sessionDir, 0700, true);
}
if (is_dir($sessionDir) && is_writable($sessionDir)) {
    session_save_path($sessionDir);
}
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$cookiePath = rtrim(str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/ownerpage/index.php'))), '/') . '/';
ini_set('session.use_strict_mode', '1');
ini_set('session.gc_maxlifetime', (string) (IDLE_MINUTES * 60));
session_name('agate_owner');
session_set_cookie_params(['lifetime' => 0, 'path' => $cookiePath, 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
session_start();
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function reply(int $status, string $message, array $extra = []): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => $status < 300, 'message' => $message] + $extra, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function owner_password(): ?string
{
    if (!is_file(PASSWORD_FILE)) {
        return null;
    }
    $value = include PASSWORD_FILE;
    return is_string($value) ? trim($value) : null;
}

// Why the page cannot be used yet, or null when the password is set up.
function setup_problem(?string $password): ?string
{
    if ($password === null) {
        return 'missing';
    }
    if ($password === '' || $password === 'CHANGE-ME') {
        return 'placeholder';
    }
    return mb_strlen($password) < MIN_PASSWORD_LENGTH ? 'short' : null;
}

function signed_in(): bool
{
    if (empty($_SESSION['owner'])) {
        return false;
    }
    if (time() - (int) ($_SESSION['seen'] ?? 0) > IDLE_MINUTES * 60) {
        unset($_SESSION['owner'], $_SESSION['seen']);
        return false;
    }
    $_SESSION['seen'] = time();
    return true;
}

function fail_file(string $who): string
{
    return sys_get_temp_dir() . '/agate-owner-fails-' . $who . '.json';
}

function recent_fails(string $who): array
{
    $file = fail_file($who);
    $saved = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
    $since = time() - FAIL_WINDOW_SECONDS;
    return is_array($saved) ? array_values(array_filter($saved, static fn($t) => is_int($t) && $t > $since)) : [];
}

function add_fail(string $who): void
{
    $fails = recent_fails($who);
    $fails[] = time();
    @file_put_contents(fail_file($who), json_encode($fails), LOCK_EX);
}

// One line of text: no control characters or runs of spaces, at most $max characters.
function clean($value, int $max): string
{
    if (!is_string($value) && !is_numeric($value)) {
        return '';
    }
    $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value) ?? '';
    $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

function list_of($value, int $max): array
{
    return is_array($value) ? array_slice(array_values(array_filter($value, 'is_array')), 0, $max) : [];
}

function valid_day($value): bool
{
    return is_string($value) && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1 && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
}

function valid_time($value): bool
{
    return is_string($value) && preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $value) === 1;
}

// Replaces a file in data/ in one step, after copying the old version to data/backups/.
function save_file(string $name, string $content): void
{
    $path = DATA_DIR . '/' . $name;
    if (is_file($path)) {
        $backups = DATA_DIR . '/backups';
        if (!is_dir($backups)) {
            @mkdir($backups, 0755, true);
        }
        $dot = strrpos($name, '.');
        $base = substr($name, 0, $dot);
        $ext = substr($name, $dot);
        if (@copy($path, $backups . '/' . $base . '-' . date('Ymd-His') . $ext)) {
            $old = glob($backups . '/' . $base . '-*' . $ext) ?: [];
            sort($old);
            foreach (array_slice($old, 0, max(0, count($old) - KEEP_BACKUPS)) as $file) {
                @unlink($file);
            }
        }
    }
    $temp = $path . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($temp, $content, LOCK_EX) === false || !@rename($temp, $path)) {
        @unlink($temp);
        reply(500, 'Could not save. The data folder on the server needs to be writable (it normally is on Bluehost).');
    }
}

function to_json(array $data): string
{
    return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
}

function save_rates(array $in): void
{
    $cards = [];
    foreach (list_of($in['cards'] ?? null, 20) as $card) {
        $title = clean($card['title'] ?? '', 60);
        $items = [];
        foreach (list_of($card['items'] ?? null, 12) as $item) {
            $label = clean($item['label'] ?? '', 60);
            $price = clean($item['price'] ?? '', 20);
            if ($label !== '' || $price !== '') {
                $items[] = ['label' => $label, 'price' => $price];
            }
        }
        if ($title === '' && !$items) {
            continue;
        }
        $out = [];
        $id = clean($card['id'] ?? '', 30);
        if ($id !== '' && preg_match('/^[a-z0-9-]+$/', $id)) {
            $out['id'] = $id;
        }
        $out['title'] = $title;
        $out['subtitle'] = clean($card['subtitle'] ?? '', 80);
        if (!empty($card['home'])) {
            $out['home'] = true;
        }
        $out['items'] = $items;
        $cards[] = $out;
    }
    if (!$cards) {
        reply(422, 'Add at least one rate card before saving.');
    }
    $rates = ['updated' => date('n/j/Y'), 'range_note' => clean($in['range_note'] ?? '', 300), 'cards' => $cards];
    save_file('rates.json', to_json($rates));
    reply(200, 'Prices saved. They are on the website now.', ['data' => $rates]);
}

function save_cafe(array $in): void
{
    // Hours: Monday to Sunday. Times are kept for closed days too, so switching a day back on keeps them.
    $h = is_array($in['hours'] ?? null) ? $in['hours'] : [];
    $hours = ['show' => !empty($h['show']), 'title' => clean($h['title'] ?? '', 40), 'days' => [], 'note' => clean($h['note'] ?? '', 200)];
    $givenDays = is_array($h['days'] ?? null) ? array_values($h['days']) : [];
    $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    foreach ($dayNames as $i => $dayName) {
        $day = is_array($givenDays[$i] ?? null) ? $givenDays[$i] : [];
        $from = valid_time($day['from'] ?? null) ? $day['from'] : '';
        $to = valid_time($day['to'] ?? null) ? $day['to'] : '';
        $open = !empty($day['open']);
        if ($open && ($from === '' || $to === '' || $to <= $from)) {
            reply(422, "Check the hours for $dayName: pick an opening time and a later closing time.");
        }
        $hours['days'][] = ['open' => $open, 'from' => $from, 'to' => $to];
    }
    $s = is_array($in['specials'] ?? null) ? $in['specials'] : [];
    $when = in_array($s['when'] ?? '', ['always', 'week', 'range'], true) ? $s['when'] : 'week';
    $specials = [
        'show' => !empty($s['show']),
        'heading' => clean($s['heading'] ?? '', 60),
        'when' => $when,
        'week' => valid_day($s['week'] ?? '') ? $s['week'] : '',
        'from' => valid_day($s['from'] ?? '') ? $s['from'] : '',
        'to' => valid_day($s['to'] ?? '') ? $s['to'] : '',
        'note' => clean($s['note'] ?? '', 200),
        'home' => !empty($s['home']),
        'items' => [],
    ];
    foreach (list_of($s['items'] ?? null, 12) as $item) {
        $name = clean($item['name'] ?? '', 80);
        if ($name !== '') {
            $specials['items'][] = ['name' => $name, 'desc' => clean($item['desc'] ?? '', 300), 'price' => clean($item['price'] ?? '', 20)];
        }
    }
    if ($specials['show']) {
        if ($when === 'week' && $specials['week'] === '') {
            reply(422, 'Pick which week the specials are for.');
        }
        if ($when === 'range' && ($specials['from'] === '' || $specials['to'] === '' || $specials['to'] < $specials['from'])) {
            reply(422, 'Pick a "From" date and a "To" date on or after it.');
        }
    }

    $m = is_array($in['menu'] ?? null) ? $in['menu'] : [];
    $menu = ['lead' => clean($m['lead'] ?? '', 200), 'foot' => clean($m['foot'] ?? '', 300), 'sections' => []];
    foreach (list_of($m['sections'] ?? null, 12) as $section) {
        $items = [];
        foreach (list_of($section['items'] ?? null, 40) as $item) {
            $name = clean($item['name'] ?? '', 100);
            if ($name !== '') {
                $items[] = [
                    'name' => $name,
                    'option' => clean($item['option'] ?? '', 40),
                    'price' => clean($item['price'] ?? '', 20),
                    'desc' => clean($item['desc'] ?? '', 400),
                ];
            }
        }
        $title = clean($section['title'] ?? '', 60);
        if ($title !== '' || $items) {
            $menu['sections'][] = ['title' => $title, 'column' => ((int) ($section['column'] ?? 0)) === 1 ? 1 : 0, 'note' => clean($section['note'] ?? '', 300), 'items' => $items];
        }
    }

    $d = is_array($in['dogs'] ?? null) ? $in['dogs'] : [];
    $dogs = ['items' => [], 'byo' => clean($d['byo'] ?? '', 400)];
    foreach (list_of($d['items'] ?? null, 40) as $item) {
        $name = clean($item['name'] ?? '', 60);
        if ($name !== '') {
            $dogs['items'][] = ['name' => $name, 'price' => clean($item['price'] ?? '', 20), 'desc' => clean($item['desc'] ?? '', 200)];
        }
    }

    $cafe = ['hours' => $hours, 'specials' => $specials, 'menu' => $menu, 'dogs' => $dogs];
    save_file('cafe.json', to_json($cafe));
    reply(200, 'Cafe changes saved. They are on the website now.', ['data' => $cafe]);
}

function save_theme(array $in): void
{
    $colors = [];
    $given = is_array($in['colors'] ?? null) ? $in['colors'] : [];
    foreach (THEME_COLORS as $name) {
        $value = $given[$name] ?? null;
        if (is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value)) {
            $colors[$name] = strtolower($value);
        }
    }
    $css = "/* Website colors chosen on the owner page (agatebeachgolf.net/ownerpage).\n"
        . "   Empty means the standard colors in styles.css are used. The owner page rewrites this file. */\n";
    if ($colors) {
        $css .= ":root {\n";
        foreach ($colors as $name => $value) {
            $css .= "  $name: $value;\n";
        }
        $css .= "}\n";
    }
    $theme = ['preset' => clean($in['preset'] ?? '', 40), 'colors' => (object) $colors];
    save_file('theme.css', $css);
    save_file('theme.json', to_json($theme));
    reply(200, $colors ? 'Colors saved. Every page of the website uses them now.' : 'Back to the original colors on every page.', ['data' => $theme]);
}

$password = owner_password();
$problem = setup_problem($password);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $request = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($request)) {
        reply(400, 'That request could not be read.');
    }
    // Requests must carry the token from this page; a stale page gets the current token and tries once more.
    if (!hash_equals((string) $_SESSION['csrf'], (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
        reply(403, 'This page was out of date. Please try again.', ['retry' => true, 'csrf' => $_SESSION['csrf']]);
    }
    $action = (string) ($request['action'] ?? '');

    if ($action === 'login') {
        if ($problem !== null) {
            reply(503, 'The password has not been set up yet. Reload this page for the steps.');
        }
        $visitor = hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if (count(recent_fails($visitor)) >= MAX_FAILS_PER_VISITOR || count(recent_fails('all')) >= MAX_FAILS_EVERYONE) {
            reply(429, 'Too many wrong passwords. Please wait 15 minutes and try again.');
        }
        $given = trim((string) ($request['password'] ?? ''));
        if (!hash_equals(hash('sha256', (string) $password), hash('sha256', $given))) {
            add_fail($visitor);
            add_fail('all');
            sleep(1);
            reply(422, 'That password is not right.');
        }
        @unlink(fail_file($visitor));
        session_regenerate_id(true);
        $_SESSION['owner'] = true;
        $_SESSION['seen'] = time();
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        reply(200, 'Signed in.', ['csrf' => $_SESSION['csrf']]);
    }

    if ($action === 'logout') {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        reply(200, 'Signed out.', ['csrf' => $_SESSION['csrf']]);
    }

    if (!signed_in()) {
        reply(401, 'You were signed out. Enter the password to carry on.');
    }
    $data = is_array($request['data'] ?? null) ? $request['data'] : [];
    if ($action === 'save_rates') {
        save_rates($data);
    }
    if ($action === 'save_cafe') {
        save_cafe($data);
    }
    if ($action === 'save_theme') {
        save_theme($data);
    }
    reply(400, 'Unknown request.');
}

$state = $problem !== null ? 'setup' : (signed_in() ? 'in' : 'out');
$dataWritable = is_dir(DATA_DIR) && is_writable(DATA_DIR);
$h = static fn(string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Website settings | Agate Beach Golf Course</title>
<link rel="icon" href="../assets/images/logo.png" type="image/png">
<link rel="stylesheet" href="../assets/css/styles.css?v=20261010-3">
<style>
  body { background: #eef1f5; min-height: 100vh; }
  [hidden] { display: none !important; }
  input[type="radio"], input[type="checkbox"] { width: auto; padding: 0; margin: 0; flex: none; box-shadow: none; }
  input[type="radio"] { width: 17px; height: 17px; accent-color: var(--navy); }

  .owner-bar { display: flex; align-items: center; gap: 0.8rem; padding: 0.8rem 1.3rem; background: var(--navy-deep); color: #fff; }
  .owner-bar img { width: 38px; height: 38px; background: #fff; border-radius: 50%; padding: 2px; }
  .owner-bar strong { font-family: var(--font-head); font-size: 1.2rem; line-height: 1.1; }
  .owner-bar strong small { display: block; font: 500 0.75rem var(--font-body); letter-spacing: 0.12em; text-transform: uppercase; color: rgba(255,255,255,0.7); }
  .owner-bar .actions { margin-left: auto; display: flex; gap: 0.6rem; align-items: center; }
  .owner-bar .actions a { color: rgba(255,255,255,0.85); font-size: 0.9rem; }
  .owner-bar button { background: transparent; }

  .wrap { width: min(1280px, 100% - 2rem); margin: 2rem auto 4rem; }
  .panel { background: var(--white); border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem 1.5rem 2rem; }

  .login { max-width: 380px; margin: 12vh auto 0; padding: 0 1rem; text-align: center; }
  .login img { display: block; width: 84px; height: 84px; margin: 0 auto; }
  .login h1 { font-size: 1.6rem; margin: 0.6rem 0 1.2rem; }
  .login label { display: block; text-align: left; font-weight: 600; color: var(--navy); margin-bottom: 0.3rem; }
  .login .btn { width: 100%; margin-top: 0.9rem; }
  .login p { font-size: 0.82rem; color: var(--muted); margin: 0.9rem 0 0; }
  input[type="text"], input[type="password"] { width: 100%; padding: 0.6rem 0.75rem; border: 1px solid #c9d1db; border-radius: 8px; font: inherit; background: #fff; }
  input[type="text"]:focus, input[type="password"]:focus { outline: 2px solid var(--blue); outline-offset: 1px; }

  .tabs { display: flex; gap: 0.4rem; border-bottom: 2px solid var(--line); margin-bottom: 1.4rem; }
  .tabs button { background: none; border: 0; padding: 0.7rem 1.1rem; font: 600 1.05rem var(--font-body); color: var(--muted); cursor: pointer; border-bottom: 3px solid transparent; margin-bottom: -2px; }
  .tabs button[aria-selected="true"] { color: var(--navy); border-bottom-color: var(--accent); }
  .label { margin: 0 0 0.6rem; font-size: 0.74rem; font-weight: 700; letter-spacing: 0.18em; text-transform: uppercase; color: var(--muted); }

  .prices { display: grid; gap: 2rem; grid-template-columns: 1fr; }
  @media (min-width: 1000px) { .prices { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
  .prices .preview { position: sticky; top: 1rem; align-self: start; background: var(--sand); border-radius: 12px; padding: 1.2rem; }
  .prices .preview .rate-grid { grid-template-columns: repeat(auto-fill, minmax(230px, 1fr)); gap: 1rem; }
  .prices .preview .rate-row { padding: 0.75rem 1.1rem; font-size: 1rem; }
  .prices .preview .rate-note { margin-top: 1rem; font-size: 0.9rem; }
  .ed-card { border: 1px solid var(--line); border-radius: 12px; padding: 0.9rem 1rem; margin-bottom: 0.9rem; }
  .ed-card-top { display: flex; justify-content: space-between; align-items: center; gap: 1rem; margin-bottom: 0.6rem; }
  .ed-card .home-check { font: 500 0.88rem var(--font-body); color: var(--muted); display: flex; align-items: center; gap: 0.4rem; margin: 0; cursor: pointer; }
  .ed-row { display: grid; grid-template-columns: minmax(0, 1fr) 110px auto; gap: 0.5rem; margin-bottom: 0.45rem; align-items: center; }
  .ed-row input[type="text"], .ed-two input[type="text"] { padding: 0.5rem 0.65rem; }
  .ed-row.head { grid-template-columns: minmax(0, 1fr) 110px 98px; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--muted); margin-bottom: 0.2rem; }
  .ed-two { display: grid; grid-template-columns: 1fr 1fr; gap: 0.5rem; margin-bottom: 0.7rem; }
  .ed-field { margin-bottom: 1rem; }
  .ed-field label { display: block; font-weight: 600; color: var(--navy); margin-bottom: 0.3rem; }

  .save-row { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; margin-top: 1.2rem; padding-top: 1.2rem; border-top: 1px solid var(--line); }
  .save-msg { color: var(--green-dark); font-weight: 600; }

  .colors { display: grid; gap: 2rem; grid-template-columns: 1fr; }
  @media (min-width: 1000px) { .colors { grid-template-columns: minmax(0, 0.9fr) minmax(0, 1.1fr); } }
  .presets { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 0.7rem; margin-bottom: 1.5rem; }
  .preset { border: 2px solid var(--line); border-radius: 12px; background: #fff; padding: 0.55rem; cursor: pointer; text-align: left; font: 600 0.85rem var(--font-body); color: var(--navy); }
  .preset[aria-pressed="true"] { border-color: var(--navy); box-shadow: 0 0 0 3px #c9d1db; }
  .preset .chips { display: flex; height: 32px; border-radius: 7px; overflow: hidden; margin-bottom: 0.45rem; }
  .preset .chips span { flex: 1; }
  .customs { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 0.7rem; }
  .customs label { display: flex; align-items: center; gap: 0.6rem; border: 1px solid var(--line); border-radius: 10px; padding: 0.45rem 0.6rem; font-weight: 600; color: var(--navy); font-size: 0.9rem; }
  .customs input[type="color"] { width: 42px; height: 32px; border: 0; padding: 0; background: none; cursor: pointer; flex: none; }
  .customs small { display: block; font-weight: 400; color: var(--muted); font-size: 0.75rem; }
  .site-preview { position: sticky; top: 1rem; align-self: start; }
  .site-preview .frame-wrap { border: 1px solid #c9d1db; border-radius: 12px; overflow: hidden; box-shadow: var(--shadow-sm); background: #fff; }
  .site-preview .chrome { display: flex; gap: 0.35rem; align-items: center; padding: 0.45rem 0.7rem; background: #e3e7ec; font-size: 0.78rem; color: var(--muted); }
  .site-preview .chrome i { width: 10px; height: 10px; border-radius: 50%; background: #c3c9d1; }
  .site-preview .chrome span { margin-left: 0.5rem; }
  .site-preview iframe { display: block; width: 100%; height: 70vh; min-height: 480px; border: 0; }

  /* ---------- Cafe tab ---------- */
  .subtabs { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1.4rem; }
  .subtabs button { border: 1.5px solid #c9d1db; background: #fff; border-radius: 999px; padding: 0.45rem 1rem; font: 600 0.92rem var(--font-body); color: var(--navy); cursor: pointer; }
  .subtabs button[aria-pressed="true"] { background: var(--navy); border-color: var(--navy); color: #fff; }
  .cafe-grid { display: grid; gap: 2rem; grid-template-columns: 1fr; }
  @media (min-width: 1000px) { .cafe-grid { grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); } }
  .cafe-preview { position: sticky; top: 1rem; align-self: start; }
  .cafe-preview-scroll { max-height: 80vh; overflow: auto; border: 1px solid #c9d1db; border-radius: 12px; background: #fff; scroll-behavior: smooth; }
  .cafe-preview .section { padding: 1.8rem 1.3rem; }
  .cafe-preview .section-head { margin-bottom: 1.4rem; }
  .cafe-preview .section-head h2 { font-size: 1.6rem; }
  .cafe-preview .menu-grid { grid-template-columns: 1fr; gap: 1.5rem; }
  .cafe-preview .menu-group > h3 { font-size: 1.4rem; }
  .cafe-preview .dog-grid { grid-template-columns: 1fr 1fr; grid-template-rows: none; grid-auto-flow: row; }
  .dog h4 { display: flex; justify-content: space-between; gap: 0.8rem; align-items: baseline; }
  .dog h4 span { color: var(--accent-soft); letter-spacing: 0.02em; white-space: nowrap; }
  .switch { display: flex; align-items: center; gap: 0.55rem; font-weight: 600; color: var(--navy); margin-bottom: 0.9rem; cursor: pointer; }
  .switch input { width: 18px; height: 18px; accent-color: var(--green); }
  .sp-status { border-radius: 10px; padding: 0.6rem 0.85rem; font-weight: 600; font-size: 0.92rem; margin-bottom: 1.2rem; }
  .sp-status.on { background: #e5f2ea; color: var(--green-dark); }
  .sp-status.later { background: #fff4d6; color: #7a5b00; }
  .sp-status.off { background: #eef1f5; color: var(--muted); }
  .sp-status.bad { background: #fbeceb; color: #a3352f; }
  .chips-row { display: flex; flex-wrap: wrap; gap: 0.4rem; margin-top: 0.5rem; }
  .chips-row button { border: 1px solid var(--line); background: var(--cream); border-radius: 999px; padding: 0.25rem 0.75rem; font: 500 0.82rem var(--font-body); color: var(--navy); cursor: pointer; }
  .chips-row button:hover { border-color: var(--navy); }
  .when { border: 1px solid var(--line); border-radius: 12px; padding: 0.8rem 1rem 0.6rem; }
  .when legend { font-weight: 600; color: var(--navy); padding: 0 0.3rem; }
  .when > label { display: flex; align-items: center; flex-wrap: wrap; gap: 0.55rem; margin-bottom: 0.65rem; cursor: pointer; font-size: 0.95rem; }
  .when select, .when input[type="date"] { width: auto; padding: 0.35rem 0.5rem; border: 1px solid #c9d1db; border-radius: 8px; font: inherit; font-size: 0.92rem; }
  .when .range { display: flex; flex-wrap: wrap; gap: 0.8rem; margin: -0.2rem 0 0.7rem 1.75rem; }
  .when .range label { display: flex; align-items: center; gap: 0.4rem; margin: 0; font-size: 0.92rem; color: var(--muted); }
  .when .range[aria-disabled="true"] { opacity: 0.45; }
  .hint { font-size: 0.82rem; color: var(--muted); margin: 0.2rem 0 0; }
  .item-ed { border: 1px solid var(--line); border-radius: 10px; padding: 0.6rem; margin-bottom: 0.6rem; background: #fff; }
  .item-ed .top { display: grid; grid-template-columns: minmax(0, 2fr) minmax(0, 1.2fr) 86px auto; gap: 0.45rem; align-items: center; }
  .item-ed.dog .top { grid-template-columns: minmax(0, 1fr) 96px auto; }
  .item-ed.sp .top { grid-template-columns: minmax(0, 1fr) 96px auto; }
  .item-ed textarea { width: 100%; margin-top: 0.45rem; padding: 0.5rem 0.7rem; border: 1px solid #c9d1db; border-radius: 8px; font: inherit; font-size: 0.92rem; resize: vertical; min-height: 2.6rem; }
  .item-ed input[type="text"] { padding: 0.5rem 0.6rem; font-size: 0.92rem; }
  @media (max-width: 560px) { .item-ed .top { grid-template-columns: 1fr 1fr; } .item-ed .top .tools { grid-column: 1 / -1; justify-self: end; } }
  .tools { display: flex; gap: 0.25rem; }
  .tools button { width: 30px; height: 30px; border: 1px solid #c9d1db; border-radius: 7px; background: #fff; cursor: pointer; color: var(--navy); font-size: 0.85rem; line-height: 1; }
  .tools button:hover { border-color: var(--navy); }
  .tools button.del:hover { border-color: #a3352f; color: #a3352f; }
  .tools button:disabled { opacity: 0.3; cursor: default; }
  .sec-ed { border: 1px solid #c9d1db; border-radius: 12px; padding: 0.9rem; margin-bottom: 1rem; background: #f8f9fb; }
  .sec-ed .sec-top { display: grid; grid-template-columns: minmax(0, 1fr) auto auto; gap: 0.5rem; align-items: center; margin-bottom: 0.5rem; }
  .sec-ed .sec-top input { font-weight: 700; color: var(--navy); }
  .sec-ed select { padding: 0.5rem; border: 1px solid #c9d1db; border-radius: 8px; font: inherit; font-size: 0.88rem; }
  .sec-ed .note-in { margin-bottom: 0.7rem; font-size: 0.88rem; }

  /* What the specials would look like on the Cafe page */
  .specials-wrap { padding: 1.8rem 1.3rem; background: var(--cream); }
  .specials-card { max-width: 40rem; margin: 0 auto; background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); overflow: hidden; border: 1px solid var(--line); }
  .specials-head { background: var(--navy); color: #fff; text-align: center; padding: 1.3rem 1rem 1.1rem; position: relative; }
  .specials-head .eyebrow { color: var(--accent-soft); margin-bottom: 0.2rem; }
  .specials-head h2 { color: #fff; margin: 0; font-size: 1.9rem; }
  .specials-dates { display: inline-block; margin-top: 0.6rem; background: var(--accent); color: var(--navy-deep); border-radius: 999px; padding: 0.2rem 0.9rem; font-weight: 700; font-size: 0.85rem; letter-spacing: 0.04em; }
  .special { display: flex; justify-content: space-between; gap: 1rem; padding: 1rem 1.4rem; border-top: 1px solid var(--line); }
  .special:first-child { border-top: 0; }
  .special h4 { margin: 0 0 0.15rem; font-family: var(--font-body); font-size: 0.98rem; letter-spacing: 0.12em; text-transform: uppercase; color: var(--navy); }
  .special p { margin: 0; color: var(--muted); font-size: 0.95rem; line-height: 1.5; }
  .special .price { font: 700 1.25rem var(--font-head); color: var(--green); white-space: nowrap; }
  .specials-note { margin: 0; padding: 0.7rem 1.4rem 1rem; text-align: center; font-size: 0.85rem; font-style: italic; color: var(--muted); border-top: 1px solid var(--line); }
  .specials-hidden { margin: 1rem; padding: 1rem; border: 2px dashed #c9d1db; border-radius: 12px; text-align: center; color: var(--muted); font-size: 0.92rem; }

  /* ---------- Sign-in, setup and messages ---------- */
  .login-msg { min-height: 1.3em; color: #a3352f; font-weight: 600; font-size: 0.9rem; }
  .setup { max-width: 560px; margin: 8vh auto 0; background: #fff; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.8rem 2rem; }
  .setup h1 { font-size: 1.7rem; margin-top: 0; }
  .setup ol { padding-left: 1.2rem; line-height: 1.7; }
  .setup code, .warn code { background: #eef1f5; padding: 0.05rem 0.35rem; border-radius: 5px; font-size: 0.9em; }
  .setup-problem, .warn { background: #fbeceb; color: #a3352f; border-radius: 10px; padding: 0.7rem 0.9rem; font-weight: 600; }
  .warn { margin: 0 0 1rem; }
  .relogin { border: 0; border-radius: var(--radius); box-shadow: var(--shadow); padding: 1.5rem; max-width: 380px; width: calc(100% - 2rem); }
  .relogin::backdrop { background: rgba(9, 30, 53, 0.55); }
  .relogin h2 { margin: 0 0 0.4rem; font-size: 1.4rem; }
  .relogin p { color: var(--muted); font-size: 0.92rem; }
  .relogin input[type="password"] { margin-bottom: 0.3rem; }
  .save-msg.pending { color: #7a5b00; }
  .save-msg.ok { color: var(--green-dark); }
  .save-msg.bad { color: #a3352f; }
  .link-btn { background: none; border: 0; padding: 0.3rem 0; font: 600 0.9rem var(--font-body); color: var(--blue); cursor: pointer; }
  .link-btn:hover { text-decoration: underline; }
  .hours-ed { display: grid; gap: 0.45rem; margin-bottom: 0.4rem; }
  .hours-row { display: grid; grid-template-columns: 6.5rem auto minmax(0, 1fr); align-items: center; gap: 0.6rem; padding: 0.45rem 0.6rem; border: 1px solid var(--line); border-radius: 10px; background: #fff; }
  .hours-row.is-closed { background: #f6f7f9; }
  .hours-row .day { font-weight: 700; color: var(--navy); }
  .hours-row .open-check { display: flex; align-items: center; gap: 0.4rem; margin: 0; font-weight: 500; font-size: 0.9rem; color: var(--muted); cursor: pointer; }
  .hours-row .times { display: flex; align-items: center; gap: 0.45rem; flex-wrap: wrap; font-size: 0.9rem; color: var(--muted); }
  .hours-row input[type="time"] { width: auto; padding: 0.35rem 0.5rem; border: 1px solid #c9d1db; border-radius: 8px; font: inherit; font-size: 0.92rem; }
  .hours-row.is-closed .times { opacity: 0.4; }
  .hours-row.is-bad { border-color: #a3352f; }
  @media (max-width: 560px) { .hours-row { grid-template-columns: 1fr auto; } .hours-row .times { grid-column: 1 / -1; } }
  .pv-hours { padding: 1.4rem 1.3rem 1.6rem; background: var(--cream); border-bottom: 1px solid var(--line); }
  .pv-hours .hours-strip { margin-top: 1.2rem; }
  .pv-hours .hours-strip[hidden] { display: none; }
  .pv-where { margin: 0 0 0.7rem; font-size: 0.72rem; font-weight: 700; letter-spacing: 0.16em; text-transform: uppercase; color: var(--muted); }
  .pv-hidden { margin-top: 1rem; padding: 0.8rem; border: 2px dashed #c9d1db; border-radius: 12px; text-align: center; color: var(--muted); font-size: 0.9rem; }
  [data-add-card] { margin-top: 0.2rem; }
</style>
</head>
<body data-state="<?= $h($state) ?>" data-csrf="<?= $h((string) $_SESSION['csrf']) ?>">

<header class="owner-bar">
  <img src="../assets/images/logo.png" alt="">
  <strong>Agate Beach Golf<small>Website settings</small></strong>
  <div class="actions" data-signed-in hidden>
    <a href="../index.html" target="_blank" rel="noopener">View website &#8599;</a>
    <button class="btn btn-sm btn-outline" type="button" data-logout>Sign out</button>
  </div>
</header>

<?php if ($state === 'setup'): ?>
<div class="setup">
  <h1>Set the password first</h1>
  <?php if ($problem === 'short'): ?>
  <p class="setup-problem">The password in <code>ownerpage/password.php</code> is shorter than <?= MIN_PASSWORD_LENGTH ?> characters. Please make it longer.</p>
  <?php endif; ?>
  <ol>
    <li>In Bluehost, open <strong>File Manager</strong> and go to <code>public_html/ownerpage</code>.</li>
    <li><?= $problem === 'missing' ? 'Copy <code>password.example.php</code> and name the copy <code>password.php</code>.' : 'Open <code>password.php</code>.' ?></li>
    <li>Edit it: put your password between the quotes, replacing <code>CHANGE-ME</code>. Use at least <?= MIN_PASSWORD_LENGTH ?> characters.</li>
    <li>Save the file, then reload this page.</li>
  </ol>
  <p class="hint">Visitors can&rsquo;t read <code>password.php</code>: the server runs it instead of showing it.</p>
</div>
<?php else: ?>
<form class="login" data-login<?= $state === 'in' ? ' hidden' : '' ?>>
  <img src="../assets/images/logo.png" alt="">
  <h1>Website settings</h1>
  <label for="pw">Password</label>
  <input id="pw" type="password" autocomplete="current-password" required autofocus>
  <button class="btn btn-accent" type="submit">Sign in</button>
  <p class="login-msg" data-login-msg role="alert"></p>
</form>

<dialog class="relogin" data-relogin>
  <form method="dialog" data-relogin-form>
    <h2>Sign in again</h2>
    <p>You were signed out after a while. Enter the password and your changes will be saved.</p>
    <label for="pw2">Password</label>
    <input id="pw2" type="password" autocomplete="current-password" required>
    <p class="login-msg" data-relogin-msg role="alert"></p>
    <div class="btn-row">
      <button class="btn btn-green" type="submit">Sign in and save</button>
      <button class="btn btn-sm btn-outline-dark" type="button" data-relogin-cancel>Cancel</button>
    </div>
  </form>
</dialog>

<main class="wrap" data-panel hidden>
  <?php if (!$dataWritable): ?>
  <p class="warn">The <code>data</code> folder on the server can&rsquo;t be written to, so saving will fail. In File Manager, set the <code>data</code> folder&rsquo;s permissions to 755.</p>
  <?php endif; ?>
  <div class="panel">
    <div class="tabs" role="tablist">
      <button type="button" role="tab" aria-selected="true" data-tab="prices">Prices</button>
      <button type="button" role="tab" aria-selected="false" data-tab="cafe">Cafe</button>
      <button type="button" role="tab" aria-selected="false" data-tab="colors">Colors</button>
    </div>

    <section class="prices" data-pane="prices">
      <div>
        <div class="ed-field">
          <label for="ed-note">Note under the rate cards</label>
          <input id="ed-note" type="text" data-ed-note maxlength="300">
        </div>
        <div data-ed-cards></div>
        <button class="btn btn-sm btn-outline-dark" type="button" data-add-card>+ Add a rate card</button>
        <div class="save-row">
          <button class="btn btn-green" type="button" data-save="prices">Save prices</button>
          <span class="save-msg" data-save-msg role="status"></span>
        </div>
      </div>
      <div class="preview">
        <p class="label">How it will look on the Golf Rates page</p>
        <p style="margin:0 0 0.8rem;color:var(--muted);font-size:0.9rem">Updated <span data-rates-updated>&hellip;</span></p>
        <div class="rate-grid" data-preview-rates></div>
        <p class="rate-note" data-preview-note></p>
      </div>
    </section>

    <section class="cafe" data-pane="cafe" hidden>
      <div class="subtabs" role="group" aria-label="Cafe sections">
        <button type="button" aria-pressed="true" data-sub="specials">Specials</button>
        <button type="button" aria-pressed="false" data-sub="hours">Hours</button>
        <button type="button" aria-pressed="false" data-sub="menu">Breakfast &amp; Lunch menu</button>
        <button type="button" aria-pressed="false" data-sub="dogs">Good Dog menu</button>
      </div>
      <div class="cafe-grid">
        <div>
          <div data-subpane="specials">
            <label class="switch"><input type="checkbox" data-sp-show> <span>Show specials on the website</span></label>
            <div class="sp-status" data-sp-status></div>

            <div class="ed-field">
              <label for="sp-heading">Heading</label>
              <input id="sp-heading" type="text" data-sp-heading maxlength="60">
              <div class="chips-row" data-sp-heading-picks></div>
            </div>

            <fieldset class="ed-field when">
              <legend>When should they show?</legend>
              <label><input type="radio" name="sp-when" value="always"> Until I turn them off</label>
              <label><input type="radio" name="sp-when" value="week"> For one week
                <select data-sp-week aria-label="Which week"></select></label>
              <label><input type="radio" name="sp-when" value="range"> From one day to another</label>
              <div class="range" data-sp-range>
                <label>From <input type="date" data-sp-from></label>
                <label>To <input type="date" data-sp-to></label>
              </div>
              <p class="hint">Weekly and dated specials appear and disappear on their own (Oregon time), so you can set them up ahead of time.</p>
            </fieldset>

            <p class="label">Specials</p>
            <div data-sp-items></div>
            <button class="btn btn-sm btn-outline-dark" type="button" data-sp-add>+ Add a special</button>

            <div class="ed-field" style="margin-top:1.2rem">
              <label for="sp-note">Small print (optional)</label>
              <input id="sp-note" type="text" data-sp-note maxlength="200" placeholder="e.g. While they last. Dine-in only.">
            </div>
            <label class="switch"><input type="checkbox" data-sp-home> <span>Also show them in the Cafe section of the home page</span></label>
          </div>

          <div data-subpane="hours" hidden>
            <label class="switch"><input type="checkbox" data-hr-show> <span>Show the hours on the Cafe page</span></label>
            <div class="ed-field">
              <label for="hr-title">Heading</label>
              <input id="hr-title" type="text" data-hr-title maxlength="40">
            </div>
            <p class="label">Each day</p>
            <div class="hours-ed" data-hr-days></div>
            <button class="link-btn" type="button" data-hr-copy>Give every open day the same hours as the first open day</button>
            <div class="ed-field" style="margin-top:1rem">
              <label for="hr-note">Note under the hours (optional)</label>
              <input id="hr-note" type="text" data-hr-note maxlength="200" placeholder="e.g. Hours can change with the seasons.">
            </div>
          </div>

          <div data-subpane="menu" hidden>
            <div class="ed-field">
              <label for="mn-lead">Line under the menu heading</label>
              <input id="mn-lead" type="text" data-mn-lead maxlength="200">
            </div>
            <div data-mn-sections></div>
            <button class="btn btn-sm btn-outline-dark" type="button" data-mn-add-section>+ Add a menu section</button>
            <div class="ed-field" style="margin-top:1.2rem">
              <label for="mn-foot">Small print under the menu</label>
              <input id="mn-foot" type="text" data-mn-foot maxlength="300">
            </div>
          </div>

          <div data-subpane="dogs" hidden>
            <div data-dg-items></div>
            <button class="btn btn-sm btn-outline-dark" type="button" data-dg-add>+ Add a hot dog</button>
            <div class="ed-field" style="margin-top:1.2rem">
              <label for="dg-byo">&ldquo;Build your own&rdquo; toppings</label>
              <input id="dg-byo" type="text" data-dg-byo maxlength="400">
            </div>
          </div>

          <div class="save-row">
            <button class="btn btn-green" type="button" data-save="cafe">Save cafe changes</button>
            <span class="save-msg" data-save-msg role="status"></span>
          </div>
        </div>

        <div class="cafe-preview">
          <p class="label">How it will look on the Cafe page</p>
          <div class="cafe-preview-scroll" data-cafe-preview>
            <div class="pv-hours" data-pv-hours-wrap>
              <p class="pv-where">Under the &ldquo;View the Menu&rdquo; and &ldquo;Call&rdquo; buttons</p>
              <div class="btn-row"><span class="btn btn-sm btn-green">View the Menu</span><span class="btn btn-sm btn-outline-dark">Call (541) 265-7331</span></div>
              <div class="hours-strip" data-pv-hours></div>
            </div>
            <div data-pv-specials></div>
            <div class="section section-sand" data-pv-menu-wrap>
              <div class="section-head center"><p class="eyebrow">Menu</p><h2>Breakfast &amp; Lunch</h2><p class="lead" data-pv-menu-lead></p></div>
              <div class="menu-grid" data-pv-menu></div>
              <p class="note" style="margin-top:1.2rem" data-pv-menu-foot></p>
            </div>
            <div class="section section-navy" data-pv-dogs-wrap>
              <div class="section-head center"><p class="eyebrow">The Good Dog Menu</p><h2>Hot Dogs Named for Golf Course Dogs</h2></div>
              <div class="dog-grid" data-pv-dogs></div>
              <p style="text-align:center;margin:1.6rem auto 0;color:rgba(255,255,255,.8)"><strong style="color:var(--accent-soft)">Build your own:</strong> <span data-pv-byo></span></p>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="colors" data-pane="colors" hidden>
      <div>
        <p class="label">Ready-made themes</p>
        <div class="presets" data-presets></div>
        <p class="label">Or pick each color</p>
        <div class="customs" data-customs></div>
        <div class="save-row">
          <button class="btn btn-green" type="button" data-save="colors">Save colors</button>
          <button class="btn btn-sm btn-outline-dark" type="button" data-reset-colors>Undo color changes</button>
          <span class="save-msg" data-save-msg role="status"></span>
        </div>
      </div>
      <div class="site-preview">
        <p class="label">Live preview of the home page</p>
        <div class="frame-wrap">
          <div class="chrome"><i></i><i></i><i></i><span>agatebeachgolf.net</span></div>
          <iframe title="Home page preview" data-site-frame></iframe>
        </div>
      </div>
    </section>
  </div>
</main>
<?php endif; ?>

<script nonce="<?= $h($nonce) ?>">
(function () {
  "use strict";

  var body = document.body;
  var state = body.getAttribute("data-state");
  if (state === "setup") return;
  var csrf = body.getAttribute("data-csrf");

  function make(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text !== undefined) n.textContent = text;
    return n;
  }
  function each(selector, fn) { Array.prototype.forEach.call(document.querySelectorAll(selector), fn); }

  // ---------- Talking to the server ----------
  function api(action, extra, retried) {
    var payload = { action: action };
    Object.keys(extra || {}).forEach(function (k) { payload[k] = extra[k]; });
    return fetch("./", {
      method: "POST",
      credentials: "same-origin",
      headers: { "Content-Type": "application/json", "X-CSRF-Token": csrf },
      body: JSON.stringify(payload)
    }).then(function (response) {
      return response.json().catch(function () {
        return { ok: false, message: "The server sent an unexpected reply (" + response.status + "). Please try again." };
      }).then(function (reply) { reply.status = response.status; return reply; });
    }, function () {
      return { ok: false, status: 0, message: "Could not reach the website. Check the internet connection and try again." };
    }).then(function (reply) {
      if (reply.csrf) csrf = reply.csrf;
      if (reply.retry && !retried) return api(action, extra, true);
      return reply;
    });
  }

  // ---------- Signing in and out ----------
  var login = document.querySelector("[data-login]");
  var panel = document.querySelector("[data-panel]");
  var signedInBar = document.querySelector("[data-signed-in]");
  var loaded = false;

  function showPanel() {
    login.hidden = true; panel.hidden = false; signedInBar.hidden = false;
    if (!loaded) { loaded = true; loadEverything(); }
  }

  login.addEventListener("submit", function (event) {
    event.preventDefault();
    var msg = document.querySelector("[data-login-msg]");
    var button = login.querySelector("button");
    msg.textContent = ""; button.disabled = true;
    api("login", { password: document.getElementById("pw").value }).then(function (reply) {
      button.disabled = false;
      if (reply.ok) { document.getElementById("pw").value = ""; showPanel(); }
      else { msg.textContent = reply.message; document.getElementById("pw").select(); }
    });
  });

  document.querySelector("[data-logout]").addEventListener("click", function () {
    if (anyUnsaved() && !window.confirm("You have changes that are not saved. Sign out anyway?")) return;
    unsaved = {};
    api("logout").then(function () { window.location.reload(); });
  });

  // When the session has run out, ask for the password in a dialog and then carry on with the save.
  var relogin = document.querySelector("[data-relogin]");
  var reloginForm = document.querySelector("[data-relogin-form]");
  var reloginDone = null;
  function askToSignIn() {
    return new Promise(function (resolve) {
      reloginDone = resolve;
      document.querySelector("[data-relogin-msg]").textContent = "";
      document.getElementById("pw2").value = "";
      relogin.showModal();
      document.getElementById("pw2").focus();
    });
  }
  reloginForm.addEventListener("submit", function (event) {
    event.preventDefault();
    api("login", { password: document.getElementById("pw2").value }).then(function (reply) {
      if (!reply.ok) { document.querySelector("[data-relogin-msg]").textContent = reply.message; return; }
      relogin.close();
      reloginDone(true);
    });
  });
  document.querySelector("[data-relogin-cancel]").addEventListener("click", function () {
    relogin.close();
    if (reloginDone) reloginDone(false);
  });

  // ---------- Tabs ----------
  each("[data-tab]", function (tab) {
    tab.addEventListener("click", function () {
      each("[data-tab]", function (t) { t.setAttribute("aria-selected", String(t === tab)); });
      each("[data-pane]", function (pane) { pane.hidden = pane.getAttribute("data-pane") !== tab.getAttribute("data-tab"); });
      if (tab.getAttribute("data-tab") === "colors") loadFrame();
      fitAll();
    });
  });

  // ---------- Saving ----------
  var unsaved = {};
  function anyUnsaved() { return Object.keys(unsaved).some(function (k) { return unsaved[k]; }); }
  function changed(part) {
    unsaved[part] = true;
    var msg = document.querySelector('[data-save="' + part + '"]').parentElement.querySelector("[data-save-msg]");
    msg.className = "save-msg pending"; msg.textContent = "Not saved yet";
  }
  window.addEventListener("beforeunload", function (event) {
    if (anyUnsaved()) { event.preventDefault(); event.returnValue = ""; }
  });

  function save(part, action, data, done) {
    var button = document.querySelector('[data-save="' + part + '"]');
    var msg = button.parentElement.querySelector("[data-save-msg]");
    button.disabled = true;
    msg.className = "save-msg"; msg.textContent = "Saving\u2026";
    var attempt = function () {
      return api(action, { data: data }).then(function (reply) {
        if (reply.status === 401) {
          return askToSignIn().then(function (ok) {
            return ok ? attempt() : { ok: false, message: "Not saved. Sign in, then press Save again." };
          });
        }
        return reply;
      });
    };
    attempt().then(function (reply) {
      button.disabled = false;
      msg.className = "save-msg " + (reply.ok ? "ok" : "bad");
      msg.textContent = reply.message;
      if (reply.ok) { unsaved[part] = false; done(reply.data); }
    });
  }

  function getJson(url) {
    return fetch(url + (url.indexOf("?") < 0 ? "?" : "&") + "v=" + Date.now(), { cache: "no-store" }).then(function (r) {
      if (!r.ok) throw new Error("HTTP " + r.status);
      return r.json();
    });
  }

  // ---------- Shared editor pieces ----------
  function iconButton(label, text, onClick, cls) {
    var b = make("button", cls || "", text); b.type = "button";
    b.setAttribute("aria-label", label); b.title = label;
    b.addEventListener("click", onClick);
    return b;
  }
  // Up / down / remove buttons for one entry of a list; redraw() rebuilds that editor afterwards.
  function listTools(list, index, redraw, what) {
    var tools = make("div", "tools");
    var up = iconButton("Move " + what + " up", "\u2191", function () { list.splice(index - 1, 0, list.splice(index, 1)[0]); redraw(); });
    var down = iconButton("Move " + what + " down", "\u2193", function () { list.splice(index + 1, 0, list.splice(index, 1)[0]); redraw(); });
    var del = iconButton("Remove " + what, "\u2715", function () { list.splice(index, 1); redraw(); }, "del");
    up.disabled = index === 0; down.disabled = index === list.length - 1;
    tools.appendChild(up); tools.appendChild(down); tools.appendChild(del);
    return tools;
  }
  function field(obj, key, placeholder, onChange, tag, max) {
    var input = make(tag || "input");
    if (!tag) input.type = "text";
    input.placeholder = placeholder; input.setAttribute("aria-label", placeholder);
    if (max) input.maxLength = max;
    input.value = obj[key] || "";
    if (tag === "textarea") input.rows = 2;
    input.addEventListener("input", function () { obj[key] = input.value; onChange(); if (tag === "textarea") fit(input); });
    return input;
  }
  // Description boxes grow to fit their text (they can only be measured once visible).
  function fit(t) { t.style.height = "auto"; t.style.height = (t.scrollHeight + 2) + "px"; }
  function fitAll() {
    requestAnimationFrame(function () {
      each(".item-ed textarea", function (t) { if (t.offsetParent) fit(t); });
    });
  }

  // ---------- Prices ----------
  var rates = null;
  function ratesChanged() { renderRatesPreview(); changed("prices"); }
  function renderRatesPreview() {
    var grid = document.querySelector("[data-preview-rates]");
    grid.textContent = "";
    rates.cards.forEach(function (card) {
      var box = make("div", "rate-card");
      var head = make("header");
      head.appendChild(make("h3", "", card.title));
      if (card.subtitle) head.appendChild(make("p", "", card.subtitle));
      box.appendChild(head);
      card.items.forEach(function (item) {
        var row = make("div", "rate-row");
        row.appendChild(make("span", "", item.label));
        row.appendChild(make("span", "price", item.price));
        box.appendChild(row);
      });
      grid.appendChild(box);
    });
    document.querySelector("[data-preview-note]").textContent = rates.range_note || "";
    document.querySelector("[data-rates-updated]").textContent = rates.updated || "";
  }
  function drawRates() {
    var wrap = document.querySelector("[data-ed-cards]");
    wrap.textContent = "";
    var redraw = function () { drawRates(); ratesChanged(); };
    rates.cards.forEach(function (card, ci) {
      card.items = card.items || [];
      var box = make("div", "ed-card");
      var h = make("div", "ed-card-top");
      var homeLabel = make("label", "home-check");
      var cb = make("input"); cb.type = "checkbox"; cb.checked = !!card.home;
      cb.addEventListener("change", function () { card.home = cb.checked; changed("prices"); });
      homeLabel.appendChild(cb); homeLabel.appendChild(document.createTextNode("Also show on home page"));
      h.appendChild(homeLabel);
      h.appendChild(listTools(rates.cards, ci, redraw, "rate card"));
      box.appendChild(h);
      var two = make("div", "ed-two");
      two.appendChild(field(card, "title", "Card title", ratesChanged, null, 60));
      two.appendChild(field(card, "subtitle", "Subtitle", ratesChanged, null, 80));
      box.appendChild(two);
      var head = make("div", "ed-row head"); head.appendChild(make("span", "", "Item")); head.appendChild(make("span", "", "Price"));
      box.appendChild(head);
      card.items.forEach(function (item, ii) {
        var row = make("div", "ed-row");
        row.appendChild(field(item, "label", "Item", ratesChanged, null, 60));
        row.appendChild(field(item, "price", "Price", ratesChanged, null, 20));
        row.appendChild(listTools(card.items, ii, redraw, "line"));
        box.appendChild(row);
      });
      var add = make("button", "link-btn", "+ Add a line"); add.type = "button";
      add.addEventListener("click", function () {
        card.items.push({ label: "", price: "" }); redraw();
        var rows = wrap.querySelectorAll(".ed-card")[ci].querySelectorAll(".ed-row:not(.head)");
        rows[rows.length - 1].querySelector("input").focus();
      });
      box.appendChild(add);
      wrap.appendChild(box);
    });
  }
  function setUpPrices(data) {
    rates = data;
    rates.cards = rates.cards || [];
    var note = document.querySelector("[data-ed-note]");
    note.value = rates.range_note || "";
    note.addEventListener("input", function () { rates.range_note = note.value; ratesChanged(); });
    document.querySelector("[data-add-card]").addEventListener("click", function () {
      rates.cards.push({ title: "", subtitle: "", items: [{ label: "", price: "" }] });
      drawRates(); ratesChanged();
      var cards = document.querySelectorAll("[data-ed-cards] .ed-card");
      cards[cards.length - 1].querySelector(".ed-two input").focus();
    });
    document.querySelector('[data-save="prices"]').addEventListener("click", function () {
      save("prices", "save_rates", rates, function (saved) {
        rates.updated = saved.updated;
        renderRatesPreview();
      });
    });
    drawRates();
    renderRatesPreview();
  }

  // ---------- Cafe ----------
  // Dates are "YYYY-MM-DD"; specials follow the calendar at the course (Oregon time).
  function courseToday() {
    var parts = new Intl.DateTimeFormat("en-US", { timeZone: "America/Los_Angeles", year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(new Date());
    var get = function (type) { return parts.filter(function (p) { return p.type === type; })[0].value; };
    return get("year") + "-" + get("month") + "-" + get("day");
  }
  function dayOf(iso) { var p = iso.split("-"); return new Date(Date.UTC(+p[0], p[1] - 1, +p[2])); }
  function plusDays(iso, n) { var d = dayOf(iso); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); }
  function mondayOf(iso) { return plusDays(iso, -((dayOf(iso).getUTCDay() + 6) % 7)); }
  function validDay(s) { return /^\d{4}-\d{2}-\d{2}$/.test(s || ""); }
  function shortDay(iso) { return dayOf(iso).toLocaleDateString("en-US", { timeZone: "UTC", month: "short", day: "numeric" }); }
  function longDay(iso) { return dayOf(iso).toLocaleDateString("en-US", { timeZone: "UTC", weekday: "long", month: "short", day: "numeric" }); }
  function rangeLabel(a, b) {
    if (a === b) return longDay(a);
    return shortDay(a) + " \u2013 " + (dayOf(a).getUTCMonth() === dayOf(b).getUTCMonth() ? String(dayOf(b).getUTCDate()) : shortDay(b));
  }

  var cafe = null, specials = null, menu = null, dogs = null;

  function specialsWindow() {
    if (specials.when === "always") return { start: "", end: "" };
    if (specials.when === "week") return validDay(specials.week) ? { start: specials.week, end: plusDays(specials.week, 6) } : null;
    if (validDay(specials.from) && validDay(specials.to) && specials.to >= specials.from) return { start: specials.from, end: specials.to };
    return null;
  }
  function specialsStatus() {
    var w = specialsWindow(), t = courseToday();
    if (!specials.show) return { cls: "off", live: false, text: "Turned off. Specials are hidden on the website." };
    if (!w) return { cls: "bad", live: false, text: "Pick a \u201cTo\u201d date on or after the \u201cFrom\u201d date." };
    if (!specials.items.some(function (i) { return (i.name || "").trim(); })) return { cls: "off", live: false, text: "Add at least one special to show this section." };
    if (!w.start) return { cls: "on", live: true, text: "Showing on the website now, until you turn it off." };
    if (t < w.start) return { cls: "later", live: false, text: "Scheduled: appears on " + longDay(w.start) + " and comes down after " + longDay(w.end) + "." };
    if (t > w.end) return { cls: "off", live: false, text: "Ended " + longDay(w.end) + ". Hidden on the website." };
    return { cls: "on", live: true, text: "Showing on the website now, through " + longDay(w.end) + ". It comes down by itself after that." };
  }

  function renderSpecialsPreview() {
    var box = document.querySelector("[data-pv-specials]");
    box.textContent = "";
    var status = specialsStatus();
    var st = document.querySelector("[data-sp-status]");
    st.className = "sp-status " + status.cls; st.textContent = status.text;
    var wrap = make("div", "specials-wrap");
    if (status.cls === "bad" || !specials.show) {
      wrap.appendChild(make("div", "specials-hidden", "Specials are hidden, so the Cafe page shows no specials section."));
      box.appendChild(wrap); return;
    }
    var w = specialsWindow();
    var card = make("div", "specials-card");
    var head = make("div", "specials-head");
    head.appendChild(make("p", "eyebrow", "The Clubhouse"));
    head.appendChild(make("h2", "", specials.heading || "Specials"));
    if (w && w.start) head.appendChild(make("span", "specials-dates", specials.when === "week" ? "Week of " + shortDay(w.start) : rangeLabel(w.start, w.end)));
    card.appendChild(head);
    var list = make("div");
    specials.items.forEach(function (item) {
      if (!(item.name || "").trim()) return;
      var row = make("div", "special");
      var text = make("div");
      text.appendChild(make("h3", "", item.name));
      if (item.desc) text.appendChild(make("p", "", item.desc));
      row.appendChild(text);
      if (item.price) row.appendChild(make("span", "price", item.price));
      list.appendChild(row);
    });
    card.appendChild(list);
    if (specials.note) card.appendChild(make("p", "specials-note", specials.note));
    if (!status.live) {
      wrap.appendChild(make("div", "specials-hidden", status.cls === "later" ? "Preview only. Visitors will see this from " + longDay(w.start) + "." : "Preview only. Visitors don\u2019t see this right now."));
    }
    wrap.appendChild(card);
    box.appendChild(wrap);
  }
  function specialsChanged() { renderSpecialsPreview(); changed("cafe"); }

  function drawSpecialItems() {
    var wrap = document.querySelector("[data-sp-items]");
    wrap.textContent = "";
    specials.items.forEach(function (item, i) {
      var box = make("div", "item-ed sp");
      var top = make("div", "top");
      top.appendChild(field(item, "name", "Name of the special", specialsChanged, null, 80));
      top.appendChild(field(item, "price", "Price", specialsChanged, null, 20));
      top.appendChild(listTools(specials.items, i, function () { drawSpecialItems(); specialsChanged(); }, "special"));
      box.appendChild(top);
      box.appendChild(field(item, "desc", "Description (optional)", specialsChanged, "textarea", 300));
      wrap.appendChild(box);
    });
    fitAll();
  }

  function setUpSpecials() {
    var today = courseToday();
    if (!validDay(specials.week)) specials.week = mondayOf(today);
    if (!validDay(specials.from)) specials.from = today;
    if (!validDay(specials.to)) specials.to = plusDays(specials.from, 6);
    specials.items = specials.items || [];

    var show = document.querySelector("[data-sp-show]");
    show.checked = !!specials.show;
    show.addEventListener("change", function () { specials.show = show.checked; specialsChanged(); });
    var home = document.querySelector("[data-sp-home]");
    home.checked = !!specials.home;
    home.addEventListener("change", function () { specials.home = home.checked; changed("cafe"); });

    var heading = document.querySelector("[data-sp-heading]");
    heading.value = specials.heading || "";
    heading.addEventListener("input", function () { specials.heading = heading.value; specialsChanged(); });
    var picks = document.querySelector("[data-sp-heading-picks]");
    ["Weekly Specials", "Today\u2019s Specials", "Weekend Specials", "Specials", "Holiday Specials", "Soup of the Week"].forEach(function (text) {
      var b = make("button", "", text); b.type = "button";
      b.addEventListener("click", function () { heading.value = specials.heading = text; specialsChanged(); });
      picks.appendChild(b);
    });

    var weekSel = document.querySelector("[data-sp-week]");
    var thisMonday = mondayOf(today);
    var weeks = ["This week", "Next week", "In 2 weeks", "In 3 weeks"].map(function (name, i) {
      var m = plusDays(thisMonday, 7 * i);
      return { value: m, text: name + " (" + rangeLabel(m, plusDays(m, 6)) + ")" };
    });
    if (!weeks.some(function (w) { return w.value === specials.week; })) {
      weeks.unshift({ value: specials.week, text: "Week of " + rangeLabel(specials.week, plusDays(specials.week, 6)) });
    }
    weeks.forEach(function (w) { var o = make("option", "", w.text); o.value = w.value; weekSel.appendChild(o); });
    weekSel.value = specials.week;

    var from = document.querySelector("[data-sp-from]"), to = document.querySelector("[data-sp-to]");
    from.value = specials.from; to.value = specials.to;
    var range = document.querySelector("[data-sp-range]");
    var radios = document.querySelectorAll('input[name="sp-when"]');
    var syncWhen = function () {
      range.setAttribute("aria-disabled", String(specials.when !== "range"));
      weekSel.disabled = specials.when !== "week";
      renderSpecialsPreview();
    };
    var choose = function (value) {
      specials.when = value;
      Array.prototype.forEach.call(radios, function (r) { r.checked = r.value === value; });
      syncWhen(); changed("cafe");
    };
    Array.prototype.forEach.call(radios, function (r) {
      r.checked = r.value === specials.when;
      r.addEventListener("change", function () { choose(r.value); });
    });
    weekSel.addEventListener("change", function () { specials.week = weekSel.value; choose("week"); });
    from.addEventListener("input", function () {
      specials.from = from.value;
      if (to.value && from.value && to.value < from.value) to.value = specials.to = from.value;
      choose("range");
    });
    to.addEventListener("input", function () { specials.to = to.value; choose("range"); });

    var note = document.querySelector("[data-sp-note]");
    note.value = specials.note || "";
    note.addEventListener("input", function () { specials.note = note.value; specialsChanged(); });
    document.querySelector("[data-sp-add]").addEventListener("click", function () {
      specials.items.push({ name: "", desc: "", price: "" });
      drawSpecialItems(); specialsChanged();
      var boxes = document.querySelectorAll("[data-sp-items] .item-ed");
      boxes[boxes.length - 1].querySelector("input").focus();
    });
    drawSpecialItems();
    syncWhen();
  }

  function renderMenuPreview() {
    document.querySelector("[data-pv-menu-lead]").textContent = menu.lead || "";
    document.querySelector("[data-pv-menu-foot]").textContent = menu.foot || "";
    var grid = document.querySelector("[data-pv-menu]");
    grid.textContent = "";
    [0, 1].forEach(function (col) {
      var group = make("div", "menu-group");
      menu.sections.filter(function (s) { return (s.column || 0) === col; }).forEach(function (s, i) {
        var h = make("h3", "", s.title);
        if (i > 0) h.style.marginTop = "2.2rem";
        group.appendChild(h);
        if (s.note) group.appendChild(make("p", "menu-note", s.note));
        s.items.forEach(function (item) {
          if (!(item.name || "").trim()) return;
          var box = make("div", "menu-item");
          var h4 = make("h4", "", item.name);
          var extra = [item.option, item.price].filter(Boolean).join(" \u00b7 ");
          if (extra) h4.appendChild(make("span", "", extra));
          box.appendChild(h4);
          box.appendChild(make("p", "", item.desc || ""));
          group.appendChild(box);
        });
      });
      grid.appendChild(group);
    });
  }
  function menuChanged() { renderMenuPreview(); changed("cafe"); }

  function drawMenu() {
    var wrap = document.querySelector("[data-mn-sections]");
    wrap.textContent = "";
    var redraw = function () { drawMenu(); menuChanged(); };
    menu.sections.forEach(function (s, si) {
      s.items = s.items || [];
      var sec = make("div", "sec-ed");
      var top = make("div", "sec-top");
      top.appendChild(field(s, "title", "Section name", menuChanged, null, 60));
      var col = make("select"); col.setAttribute("aria-label", "Column");
      [["Left column", 0], ["Right column", 1]].forEach(function (c) { var o = make("option", "", c[0]); o.value = c[1]; col.appendChild(o); });
      col.value = String(s.column || 0);
      col.addEventListener("change", function () { s.column = +col.value; menuChanged(); });
      top.appendChild(col);
      top.appendChild(listTools(menu.sections, si, redraw, "section"));
      sec.appendChild(top);
      var note = field(s, "note", "Note under the section name (optional)", menuChanged, null, 300);
      note.className = "note-in";
      sec.appendChild(note);
      s.items.forEach(function (item, ii) {
        var box = make("div", "item-ed");
        var row = make("div", "top");
        row.appendChild(field(item, "name", "Item name", menuChanged, null, 100));
        row.appendChild(field(item, "option", "Sizes (optional)", menuChanged, null, 40));
        row.appendChild(field(item, "price", "Price", menuChanged, null, 20));
        row.appendChild(listTools(s.items, ii, redraw, "item"));
        box.appendChild(row);
        box.appendChild(field(item, "desc", "Description (optional)", menuChanged, "textarea", 400));
        sec.appendChild(box);
      });
      var add = make("button", "link-btn", "+ Add item to " + (s.title || "this section")); add.type = "button";
      add.addEventListener("click", function () {
        s.items.push({ name: "", option: "", price: "", desc: "" }); redraw();
        var items = wrap.querySelectorAll(".sec-ed")[si].querySelectorAll(".item-ed");
        items[items.length - 1].querySelector("input").focus();
      });
      sec.appendChild(add);
      wrap.appendChild(sec);
    });
    fitAll();
  }

  function renderDogsPreview() {
    var grid = document.querySelector("[data-pv-dogs]");
    grid.textContent = "";
    dogs.items.forEach(function (d) {
      if (!(d.name || "").trim()) return;
      var box = make("div", "dog");
      var h4 = make("h4", "", d.name);
      if (d.price) h4.appendChild(make("span", "", d.price));
      box.appendChild(h4);
      box.appendChild(make("p", "", d.desc || ""));
      grid.appendChild(box);
    });
    document.querySelector("[data-pv-byo]").textContent = dogs.byo || "";
  }
  function dogsChanged() { renderDogsPreview(); changed("cafe"); }

  function drawDogs() {
    var wrap = document.querySelector("[data-dg-items]");
    wrap.textContent = "";
    dogs.items.forEach(function (d, i) {
      var box = make("div", "item-ed dog");
      var row = make("div", "top");
      row.appendChild(field(d, "name", "Hot dog name", dogsChanged, null, 60));
      row.appendChild(field(d, "price", "Price", dogsChanged, null, 20));
      row.appendChild(listTools(dogs.items, i, function () { drawDogs(); dogsChanged(); }, "hot dog"));
      box.appendChild(row);
      box.appendChild(field(d, "desc", "Toppings", dogsChanged, "textarea", 200));
      wrap.appendChild(box);
    });
    fitAll();
  }

  // ---------- Cafe hours (week strip on the Cafe page) ----------
  var HOUR_DAYS = ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday", "Sunday"];
  var hours = null;
  function validTime(s) { return /^([01]\d|2[0-3]):[0-5]\d$/.test(s || ""); }
  function dayIsOpen(d) { return d.open && validTime(d.from) && validTime(d.to) && d.to > d.from; }
  function minutesOf(s) { var p = s.split(":"); return +p[0] * 60 + +p[1]; }
  function timeText(s, withAmPm) {
    var p = s.split(":"), h = +p[0];
    return (h % 12 || 12) + (p[1] === "00" ? "" : ":" + p[1]) + (withAmPm ? (h < 12 ? " am" : " pm") : "");
  }
  // Same layout and wording as main.js draws on the Cafe page.
  function renderHoursPreview() {
    var box = document.querySelector("[data-pv-hours]");
    var wrap = document.querySelector("[data-pv-hours-wrap]");
    var old = wrap.querySelector(".pv-hidden");
    if (old) old.remove();
    box.textContent = "";
    if (!hours.show) {
      box.hidden = true;
      wrap.appendChild(make("div", "pv-hidden", "Hours are hidden, so the Cafe page doesn\u2019t show them."));
      return;
    }
    box.hidden = false;
    var parts = new Intl.DateTimeFormat("en-US", { timeZone: "America/Los_Angeles", weekday: "short", hour: "2-digit", minute: "2-digit", hourCycle: "h23" }).formatToParts(new Date());
    var get = function (type) { return parts.filter(function (p) { return p.type === type; })[0].value; };
    var today = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"].indexOf(get("weekday"));
    var now = (+get("hour") % 24) * 60 + +get("minute");
    var t = hours.days[today], status;
    if (dayIsOpen(t) && now >= minutesOf(t.from) && now < minutesOf(t.to)) {
      status = { cls: "open", text: "Open now \u00b7 until " + timeText(t.to, true) };
    } else {
      var next = "";
      for (var k = 0; k < 8 && !next; k++) {
        var i = (today + k) % 7, d = hours.days[i];
        if (dayIsOpen(d) && (k > 0 || now < minutesOf(d.from))) next = (k === 0 ? "" : k === 1 ? "tomorrow " : HOUR_DAYS[i] + " ") + timeText(d.from, true);
      }
      status = { cls: "closed", text: "Closed now" + (next ? " \u00b7 opens " + next : "") };
    }
    var top = make("div", "strip-top");
    var title = make("h3", "hours-title");
    title.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
    title.appendChild(document.createTextNode(hours.title || "Cafe Hours"));
    top.appendChild(title);
    top.appendChild(make("span", "hours-status " + status.cls, status.text));
    box.appendChild(top);
    var list = make("ul", "strip-days");
    hours.days.forEach(function (d, i) {
      var item = make("li", "strip-day" + (dayIsOpen(d) ? "" : " closed") + (i === today ? " today" : ""));
      item.appendChild(make("b", "", HOUR_DAYS[i].slice(0, 3)));
      item.appendChild(make("span", "", dayIsOpen(d) ? timeText(d.from) + "\u2013" + timeText(d.to) : "Closed"));
      list.appendChild(item);
    });
    box.appendChild(list);
    box.appendChild(make("p", "strip-note", hours.note || ""));
  }
  function hoursChanged() { renderHoursPreview(); changed("cafe"); }

  function drawHours() {
    var wrap = document.querySelector("[data-hr-days]");
    wrap.textContent = "";
    hours.days.forEach(function (d, i) {
      var row = make("div", "hours-row");
      var paint = function () {
        row.classList.toggle("is-closed", !d.open);
        row.classList.toggle("is-bad", !!d.open && !dayIsOpen(d));
      };
      row.appendChild(make("span", "day", HOUR_DAYS[i]));
      var check = make("label", "open-check");
      var cb = make("input"); cb.type = "checkbox"; cb.checked = !!d.open;
      check.appendChild(cb); check.appendChild(document.createTextNode("Open"));
      row.appendChild(check);
      var times = make("div", "times");
      var from = make("input"); from.type = "time"; from.step = 300; from.value = d.from || "09:00"; from.setAttribute("aria-label", HOUR_DAYS[i] + " opening time");
      var to = make("input"); to.type = "time"; to.step = 300; to.value = d.to || "15:30"; to.setAttribute("aria-label", HOUR_DAYS[i] + " closing time");
      d.from = from.value; d.to = to.value;
      times.appendChild(from); times.appendChild(document.createTextNode("to")); times.appendChild(to);
      row.appendChild(times);
      var sync = function () { from.disabled = to.disabled = !d.open; paint(); };
      cb.addEventListener("change", function () { d.open = cb.checked; sync(); hoursChanged(); });
      from.addEventListener("input", function () { d.from = from.value; paint(); hoursChanged(); });
      to.addEventListener("input", function () { d.to = to.value; paint(); hoursChanged(); });
      sync();
      wrap.appendChild(row);
    });
  }

  function setUpHours() {
    hours = cafe.hours = cafe.hours || { show: true, title: "Cafe Hours", days: [], note: "" };
    for (var i = 0; i < 7; i++) {
      var d = hours.days[i] || {};
      hours.days[i] = { open: !!d.open, from: validTime(d.from) ? d.from : "09:00", to: validTime(d.to) ? d.to : "15:30" };
    }
    hours.days.length = 7;
    var show = document.querySelector("[data-hr-show]");
    show.checked = !!hours.show;
    show.addEventListener("change", function () { hours.show = show.checked; hoursChanged(); });
    var title = document.querySelector("[data-hr-title]");
    title.value = hours.title || "";
    title.addEventListener("input", function () { hours.title = title.value; hoursChanged(); });
    var note = document.querySelector("[data-hr-note]");
    note.value = hours.note || "";
    note.addEventListener("input", function () { hours.note = note.value; hoursChanged(); });
    document.querySelector("[data-hr-copy]").addEventListener("click", function () {
      var first = hours.days.filter(function (d) { return d.open; })[0];
      if (!first) return;
      hours.days.forEach(function (d) { if (d.open) { d.from = first.from; d.to = first.to; } });
      drawHours(); hoursChanged();
    });
    drawHours();
    renderHoursPreview();
    setInterval(renderHoursPreview, 60000);
  }

  function setUpCafe(data) {
    cafe = data;
    setUpHours();
    specials = cafe.specials = cafe.specials || { show: false, heading: "Weekly Specials", when: "week", items: [], home: true };
    menu = cafe.menu = cafe.menu || { lead: "", foot: "", sections: [] };
    menu.sections = menu.sections || [];
    dogs = cafe.dogs = cafe.dogs || { items: [], byo: "" };
    dogs.items = dogs.items || [];
    setUpSpecials();

    var leadIn = document.querySelector("[data-mn-lead]"), footIn = document.querySelector("[data-mn-foot]"), byoIn = document.querySelector("[data-dg-byo]");
    leadIn.value = menu.lead || ""; footIn.value = menu.foot || ""; byoIn.value = dogs.byo || "";
    leadIn.addEventListener("input", function () { menu.lead = leadIn.value; menuChanged(); });
    footIn.addEventListener("input", function () { menu.foot = footIn.value; menuChanged(); });
    byoIn.addEventListener("input", function () { dogs.byo = byoIn.value; dogsChanged(); });
    document.querySelector("[data-mn-add-section]").addEventListener("click", function () {
      menu.sections.push({ title: "", column: 1, note: "", items: [{ name: "", option: "", price: "", desc: "" }] });
      drawMenu(); menuChanged();
      var secs = document.querySelectorAll("[data-mn-sections] .sec-ed");
      secs[secs.length - 1].querySelector("input").focus();
    });
    document.querySelector("[data-dg-add]").addEventListener("click", function () {
      dogs.items.push({ name: "The ", price: "", desc: "" });
      drawDogs(); dogsChanged();
      var boxes = document.querySelectorAll("[data-dg-items] .item-ed");
      var input = boxes[boxes.length - 1].querySelector("input");
      input.focus(); input.setSelectionRange(input.value.length, input.value.length);
    });
    document.querySelector('[data-save="cafe"]').addEventListener("click", function () {
      save("cafe", "save_cafe", cafe, function () { renderSpecialsPreview(); });
    });
    drawMenu(); renderMenuPreview();
    drawDogs(); renderDogsPreview();
  }

  // Sub-tabs: show one editor and scroll the preview to the matching part of the Cafe page.
  var previewTargets = { specials: "[data-pv-specials]", hours: "[data-pv-hours-wrap]", menu: "[data-pv-menu-wrap]", dogs: "[data-pv-dogs-wrap]" };
  each("[data-sub]", function (btn) {
    btn.addEventListener("click", function () {
      var name = btn.getAttribute("data-sub");
      each("[data-sub]", function (b) { b.setAttribute("aria-pressed", String(b === btn)); });
      each("[data-subpane]", function (p) { p.hidden = p.getAttribute("data-subpane") !== name; });
      var scroller = document.querySelector("[data-cafe-preview]");
      scroller.scrollTop = document.querySelector(previewTargets[name]).offsetTop - scroller.offsetTop;
      fitAll();
    });
  });

  // ---------- Colors ----------
  var colorVars = [
    ["--accent", "Accent", "Buttons, Announcements strip"],
    ["--accent-soft", "Accent light", "Notices, numbers on navy"],
    ["--navy", "Navy", "Headings, stats band"],
    ["--navy-deep", "Dark navy", "Header, footer"],
    ["--green", "Green", "Green buttons"],
    ["--cream", "Cream", "Light backgrounds"]
  ];
  // This page loads styles.css but not the saved theme, so these are the site's original colors.
  var original = {};
  var rootStyles = getComputedStyle(document.documentElement);
  colorVars.forEach(function (v) { original[v[0]] = rootStyles.getPropertyValue(v[0]).trim().toLowerCase(); });
  var presets = [
    ["Original (champagne sand)", {}],
    ["Old gold", { "--accent": "#c8a14b", "--accent-soft": "#e6cf94" }],
    ["Seafoam teal", { "--accent": "#3fb8a5", "--accent-soft": "#c4efe7" }],
    ["Sky aqua", { "--accent": "#4cc3ee", "--accent-soft": "#d3f1fd" }],
    ["Sunset amber", { "--accent": "#f2a03d", "--accent-soft": "#fde3b8" }],
    ["Fairway green", { "--accent": "#8fc93a", "--accent-soft": "#e2f1c3" }],
    ["Coral", { "--accent": "#f4845f", "--accent-soft": "#ffd3c0" }],
    ["Forest & cream", { "--accent": "#e9dcc0", "--accent-soft": "#f6efe0", "--navy": "#1f4d39", "--navy-deep": "#143326", "--green": "#2d6a4f" }],
    ["Ocean storm", { "--accent": "#9fc3d6", "--accent-soft": "#e1eef5", "--navy": "#263849", "--navy-deep": "#18232f", "--green": "#3d6f6a" }]
  ];
  var current = {}, savedColors = {}, presetName = "";
  var pickers = {}, presetButtons = [];
  var frame = document.querySelector("[data-site-frame]");

  function fullColors(values) {
    var out = {};
    colorVars.forEach(function (v) { out[v[0]] = (values[v[0]] || original[v[0]]).toLowerCase(); });
    return out;
  }
  function loadFrame() { if (!frame.getAttribute("src")) frame.setAttribute("src", "../index.html"); }
  // Only the preview of the site changes color; this settings page keeps its own look.
  function paintFrame() {
    var doc = frame.contentDocument;
    if (!doc || !doc.documentElement) return;
    colorVars.forEach(function (v) { doc.documentElement.style.setProperty(v[0], current[v[0]]); });
  }
  frame.addEventListener("load", paintFrame);
  function markPreset() {
    presetName = "";
    presetButtons.forEach(function (b, i) {
      var match = colorVars.every(function (v) { return fullColors(presets[i][1])[v[0]] === current[v[0]]; });
      if (match && !presetName) presetName = presets[i][0];
      b.setAttribute("aria-pressed", String(match && presetName === presets[i][0]));
    });
  }
  function applyColors(values) {
    current = fullColors(values);
    colorVars.forEach(function (v) { pickers[v[0]].value = current[v[0]]; });
    paintFrame(); markPreset();
  }
  function setUpColors(theme) {
    savedColors = (theme && theme.colors) || {};
    var customs = document.querySelector("[data-customs]");
    colorVars.forEach(function (v) {
      var label = make("label");
      var input = make("input"); input.type = "color";
      input.addEventListener("input", function () {
        current[v[0]] = input.value.toLowerCase();
        paintFrame(); markPreset(); changed("colors");
      });
      pickers[v[0]] = input;
      var text = make("span", "", v[1]); text.appendChild(make("small", "", v[2]));
      label.appendChild(input); label.appendChild(text);
      customs.appendChild(label);
    });
    var box = document.querySelector("[data-presets]");
    presets.forEach(function (p) {
      var b = make("button", "preset"); b.type = "button";
      var chips = make("span", "chips");
      ["--navy-deep", "--navy", "--accent", "--accent-soft"].forEach(function (key) {
        var c = make("span"); c.style.background = p[1][key] || original[key]; chips.appendChild(c);
      });
      b.appendChild(chips); b.appendChild(document.createTextNode(p[0]));
      b.addEventListener("click", function () { applyColors(p[1]); changed("colors"); });
      presetButtons.push(b);
      box.appendChild(b);
    });
    document.querySelector("[data-reset-colors]").addEventListener("click", function () {
      applyColors(savedColors);
      unsaved.colors = false;
      var msg = document.querySelector('[data-save="colors"]').parentElement.querySelector("[data-save-msg]");
      msg.className = "save-msg"; msg.textContent = "Back to the colors the website is using now.";
    });
    document.querySelector('[data-save="colors"]').addEventListener("click", function () {
      // Only colors that differ from the originals are saved, so "Original" leaves the theme file empty.
      var colors = {};
      colorVars.forEach(function (v) { if (current[v[0]] !== original[v[0]]) colors[v[0]] = current[v[0]]; });
      save("colors", "save_theme", { colors: colors, preset: presetName }, function (saved) { savedColors = saved.colors || {}; });
    });
    applyColors(savedColors);
  }

  function loadEverything() {
    getJson("../data/rates.json").then(setUpPrices).catch(function () {
      document.querySelector("[data-ed-cards]").textContent = "Could not load the current prices. Reload the page to try again.";
    });
    getJson("../data/cafe.json").then(setUpCafe).catch(function () {
      document.querySelector("[data-mn-sections]").textContent = "Could not load the cafe menu. Reload the page to try again.";
    });
    getJson("../data/theme.json").catch(function () { return {}; }).then(setUpColors);
  }

  if (state === "in") showPanel();
})();
</script>
</body>
</html>
