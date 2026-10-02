<?php
/*
 * Sends the Contact page form to the pro shop's email, so visitors do not need an email app.
 *
 * Works on PHP hosting such as Bluehost (it uses PHP's built-in mail()). The page posts here with
 * JavaScript; if this file is not available (for example on GitHub Pages) the page falls back to
 * opening the visitor's email app.
 *
 * Protection: POST only, same-site requests only, a hidden "website" field that people never fill
 * in, a minimum time on the page, length limits, newline stripping in anything that goes into a
 * mail header, and at most 5 messages per visitor per hour.
 */

const TO_ADDRESS = 'teeoff@agatebeachgolf.net';   // where messages go
const FROM_ADDRESS = 'teeoff@agatebeachgolf.net'; // must be a real mailbox on this domain so the host accepts the mail
const SITE_HOSTS = ['agatebeachgolf.net', 'www.agatebeachgolf.net'];
const MAX_PER_HOUR = 5;
const MIN_SECONDS_ON_PAGE = 3;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['ok' => $status === 200, 'message' => $message]);
    exit;
}

function clean_line(string $value, int $max): string
{
    $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'Please use the contact form.');
}

// Only accept the form when it was sent from this website.
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '') {
    $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $ownHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    $allowed = array_merge(SITE_HOSTS, [$ownHost]);
    if (!in_array($host, $allowed, true)) {
        respond(403, 'This form can only be sent from the Agate Beach Golf website.');
    }
}

// A hidden field that real visitors never fill in, and a minimum time on the page, catch most bots.
// They get an "ok" answer so they do not keep retrying.
$started = (int) ($_POST['started'] ?? 0);
$elapsed = time() - intdiv($started, 1000);
if (($_POST['website'] ?? '') !== '' || $started <= 0 || $elapsed < MIN_SECONDS_ON_PAGE || $elapsed > 86400) {
    respond(200, 'Thank you! Your message has been sent.');
}

$name = clean_line((string) ($_POST['name'] ?? ''), 80);
$email = clean_line((string) ($_POST['email'] ?? ''), 120);
$subject = clean_line((string) ($_POST['subject'] ?? ''), 120);
$message = trim(str_replace("\r", '', (string) ($_POST['message'] ?? '')));
$message = mb_substr($message, 0, 4000);

if ($name === '' || $subject === '' || $message === '') {
    respond(422, 'Please fill in your name, a subject and your message.');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(422, 'Please enter a valid email address so we can reply.');
}

// At most MAX_PER_HOUR messages per visitor per hour.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$rateFile = sys_get_temp_dir() . '/agate-contact-' . hash('sha256', $ip) . '.json';
$now = time();
$recent = [];
if (is_file($rateFile)) {
    $saved = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($saved)) {
        $recent = array_values(array_filter($saved, static fn($t) => is_int($t) && $t > $now - 3600));
    }
}
if (count($recent) >= MAX_PER_HOUR) {
    respond(429, 'You have sent a few messages already. Please call the pro shop at (541) 265-7331.');
}

$body = $message . "\n\n--\nFrom: " . $name . ' <' . $email . ">\nSent from the Contact page of agatebeachgolf.net\n";
$encodedSubject = mb_encode_mimeheader('Website message: ' . $subject, 'UTF-8', 'B', "\r\n");
// Names can contain characters that are unsafe in a header, so keep them simple there.
$headerName = trim(preg_replace('/[^\p{L}\p{N} .\'-]/u', '', $name) ?? '');
$headers = [
    'From: Agate Beach Golf Website <' . FROM_ADDRESS . '>',
    'Reply-To: ' . ($headerName !== '' ? $headerName . ' ' : '') . '<' . $email . '>',
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'Content-Transfer-Encoding: 8bit',
    'X-Mailer: agatebeachgolf.net contact form',
];

// Used only when testing on a computer without a mail server.
$testFile = getenv('CONTACT_TEST_MODE');
if ($testFile) {
    file_put_contents($testFile, "TO: " . TO_ADDRESS . "\nSUBJECT: $subject\n" . implode("\n", $headers) . "\n\n$body\n---\n", FILE_APPEND);
    $sent = true;
} else {
    $sent = mail(TO_ADDRESS, $encodedSubject, $body, implode("\r\n", $headers), '-f' . FROM_ADDRESS);
}

if (!$sent) {
    respond(500, 'Sorry, your message could not be sent. Please email teeoff@agatebeachgolf.net or call (541) 265-7331.');
}

$recent[] = $now;
@file_put_contents($rateFile, json_encode($recent), LOCK_EX);
respond(200, 'Thank you! Your message has been sent.');
