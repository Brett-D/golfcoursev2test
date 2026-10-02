<?php
/*
 * Sends the Contact page form to the pro shop's email, so visitors do not need an email app.
 *
 * Works on PHP hosting such as Bluehost (it uses PHP's built-in mail()). The page posts here with
 * JavaScript; if this file is not available (for example on GitHub Pages) the page falls back to
 * opening the visitor's email app.
 *
 * Spam protection, in the order it is applied:
 *   1. POST only, and only from this website.
 *   2. A hidden "website" field that people never fill in, and a minimum time on the page.
 *   3. A content filter: too many links, or typical spam phrases, are dropped quietly.
 *   4. A daily limit across all visitors (DAILY_CAP).
 *   5. A limit per visitor per hour (MAX_PER_HOUR), and a quick "are you a person" question once a
 *      visitor has already sent CHALLENGE_AFTER messages in the last hour.
 * Newlines are also stripped from anything that goes into a mail header, and lengths are limited.
 */

const TO_ADDRESS = 'teeoff@agatebeachgolf.net';   // where messages go
const FROM_ADDRESS = 'teeoff@agatebeachgolf.net'; // must be a real mailbox on this domain so the host accepts the mail
const SITE_HOSTS = ['agatebeachgolf.net', 'www.agatebeachgolf.net'];
const PHONE = '(541) 265-7331';
const MIN_SECONDS_ON_PAGE = 3;
const MAX_LINKS = 2;           // more links than this and the message is dropped
const DAILY_CAP = 50;          // messages per day from everybody together
const MAX_PER_HOUR = 5;        // messages per visitor per hour
const CHALLENGE_AFTER = 2;     // a visitor who has already sent this many messages in the last hour gets a question for the next one
const CHALLENGE_MINUTES = 10;  // how long a question stays valid

// Phrases that nearly always mean a sales pitch or scam. Two of them, or one plus a link, drops the message.
const SPAM_PHRASES = [
    'seo', 'backlink', 'backlinks', 'search engine', 'web design', 'website design', 'web development', 'guest post',
    'casino', 'betting', 'poker', 'crypto', 'bitcoin', 'forex', 'binary option', 'viagra', 'cialis',
    'pharmacy', 'weight loss', 'make money', 'earn money', 'work from home', 'increase your traffic', 'more traffic',
    'telegram', 'whatsapp', 'click here', 'buy now', 'limited offer', 'free trial', 'escort', 'porn',
    'domain expires', 'domain name', 'rank your website', 'first page of google',
];

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function respond(int $status, string $message, array $extra = []): void
{
    http_response_code($status);
    echo json_encode(array_merge(['ok' => $status === 200, 'message' => $message], $extra));
    exit;
}

// Spammers and bots get the same "sent" answer a real visitor does, so they do not keep trying.
function pretend_sent(): void
{
    respond(200, 'Thank you! Your message has been sent.');
}

function clean_line(string $value, int $max): string
{
    $value = preg_replace('/[\r\n\t]+/', ' ', $value) ?? '';
    $value = trim(preg_replace('/\s+/', ' ', $value) ?? '');
    return mb_substr($value, 0, $max);
}

function temp_file(string $name): string
{
    return sys_get_temp_dir() . '/agate-contact-' . $name;
}

function count_links(string $text): int
{
    return (int) preg_match_all('~(?:https?://|ftp://|www\.)\S+|\b[a-z0-9-]+\.(?:com|net|org|info|biz|xyz|top|club|site|online|shop|store|ru|cn|tk|ml|click|link)\b~i', $text);
}

function count_spam_phrases(string $text): int
{
    $hits = 0;
    foreach (SPAM_PHRASES as $phrase) {
        if (preg_match('/\b' . preg_quote($phrase, '/') . '\b/i', $text)) {
            $hits++;
        }
    }
    return $hits;
}

// A simple question, kept for CHALLENGE_MINUTES and good for one answer only.
function issue_challenge(string $ipHash): array
{
    $words = ['one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine'];
    $a = random_int(1, 9);
    $b = random_int(1, 9);
    $token = bin2hex(random_bytes(16));
    file_put_contents(temp_file('challenge-' . $token . '.json'), json_encode([
        'answer' => $a + $b, 'ip' => $ipHash, 'expires' => time() + CHALLENGE_MINUTES * 60,
    ]), LOCK_EX);
    return ['token' => $token, 'question' => 'What is ' . $words[$a - 1] . ' plus ' . $words[$b - 1] . '? (answer with a number)'];
}

function challenge_passed(string $token, string $answer, string $ipHash): bool
{
    if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
        return false;
    }
    $file = temp_file('challenge-' . $token . '.json');
    if (!is_file($file)) {
        return false;
    }
    $data = json_decode((string) file_get_contents($file), true);
    @unlink($file); // one try per question
    return is_array($data)
        && ($data['ip'] ?? '') === $ipHash
        && ($data['expires'] ?? 0) >= time()
        && trim($answer) !== ''
        && (string) ($data['answer'] ?? '') === trim($answer);
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, 'Please use the contact form.');
}

// 1. Only accept the form when it was sent from this website.
$origin = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? '');
if ($origin !== '') {
    $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
    $ownHost = strtolower(preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) ?? '');
    $allowed = array_merge(SITE_HOSTS, [$ownHost]);
    if (!in_array($host, $allowed, true)) {
        respond(403, 'This form can only be sent from the Agate Beach Golf website.');
    }
}

// 2. A hidden field that real visitors never fill in, and a minimum time on the page, catch most bots.
$started = (int) ($_POST['started'] ?? 0);
$elapsed = time() - intdiv($started, 1000);
if (($_POST['website'] ?? '') !== '' || $started <= 0 || $elapsed < MIN_SECONDS_ON_PAGE || $elapsed > 86400) {
    pretend_sent();
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

// 3. Content filter: sales pitches are nearly always links and a few stock phrases.
$allText = $name . ' ' . $subject . ' ' . $message;
$links = count_links($allText);
$phrases = count_spam_phrases($allText);
if ($links > MAX_LINKS || count_links($name . ' ' . $subject) > 0 || $phrases >= 2 || ($phrases >= 1 && $links >= 1)) {
    pretend_sent();
}

// 4. A daily limit across all visitors keeps the inbox safe even if a flood gets through.
$dayFile = temp_file('day-' . date('Ymd') . '.txt');
$today = is_file($dayFile) ? (int) file_get_contents($dayFile) : 0;
if ($today >= DAILY_CAP) {
    respond(429, 'We have had a lot of messages today. Please call the pro shop at ' . PHONE . '.');
}

// 5. Limits per visitor, with a quick question once they have already sent a couple of messages.
$ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$ipHash = hash('sha256', $ip);
$rateFile = temp_file($ipHash . '.json');
$now = time();
$recent = [];
if (is_file($rateFile)) {
    $saved = json_decode((string) file_get_contents($rateFile), true);
    if (is_array($saved)) {
        $recent = array_values(array_filter($saved, static fn($t) => is_int($t) && $t > $now - 3600));
    }
}
if (count($recent) >= MAX_PER_HOUR) {
    respond(429, 'You have sent a few messages already. Please call the pro shop at ' . PHONE . '.');
}
if (count($recent) >= CHALLENGE_AFTER) {
    $token = (string) ($_POST['token'] ?? '');
    $answer = (string) ($_POST['answer'] ?? '');
    $answered = $token !== '' || $answer !== '';
    if (!challenge_passed($token, $answer, $ipHash)) {
        $challenge = issue_challenge($ipHash);
        respond(403, $answered ? 'That was not quite right. Here is another question.' : 'Quick check to make sure you are a person, then press Send again.', [
            'challenge' => true, 'question' => $challenge['question'], 'token' => $challenge['token'],
        ]);
    }
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
    respond(500, 'Sorry, your message could not be sent. Please email ' . TO_ADDRESS . ' or call ' . PHONE . '.');
}

$recent[] = $now;
@file_put_contents($rateFile, json_encode($recent), LOCK_EX);
@file_put_contents($dayFile, (string) ($today + 1), LOCK_EX);
pretend_sent();
