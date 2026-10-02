<?php
/*
 * Course notices from the ForeUp booking page, as JSON for the home page.
 *
 * ForeUp does not let other websites read its page from the browser, so this file reads it on the
 * server instead. The result is cached in data/notices.json and refreshed at most every
 * CACHE_SECONDS, so ForeUp is not contacted on every page view. If ForeUp is down, the last
 * saved notices are served.
 *
 * Only needed on PHP hosting such as Bluehost. On other hosts the site falls back to the
 * data/notices.json file as it is.
 */

const SOURCE_URL = 'https://foreupsoftware.com/index.php/booking/21987/9520';
const CACHE_FILE = __DIR__ . '/data/notices.json';
const CACHE_SECONDS = 1200;  // 20 minutes
const RETRY_SECONDS = 300;   // after a failed fetch, wait 5 minutes before trying again
const MAX_LINES = 10;
const MAX_LINE_LENGTH = 300;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
header('X-Content-Type-Options: nosniff');

function fetch_page(): ?string
{
    $agent = 'AgateBeachWebsite/1.0 (notice sync)';
    if (function_exists('curl_init')) {
        $curl = curl_init(SOURCE_URL);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_CONNECTTIMEOUT => 4,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_USERAGENT => $agent,
        ]);
        $body = curl_exec($curl);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        return ($body !== false && $status === 200) ? $body : null;
    }
    $context = stream_context_create(['http' => ['timeout' => 8, 'header' => "User-Agent: $agent\r\n"]]);
    $body = @file_get_contents(SOURCE_URL, false, $context);
    return $body === false ? null : $body;
}

function to_lines(string $markup): array
{
    $markup = preg_replace('/<img[^>]*>/i', '', $markup);
    $markup = preg_replace('/<br\s*\/?>|<\/p>|<\/div>|<\/li>/i', "\n", $markup);
    $text = html_entity_decode(strip_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = preg_replace('/\x{00A0}/u', ' ', $text);
    $lines = [];
    foreach (explode("\n", $text) as $line) {
        $line = trim(preg_replace('/\s+/u', ' ', $line));
        if ($line === '' || preg_match('/^thank you!?$/i', $line)) {
            continue;
        }
        $lines[] = mb_substr($line, 0, MAX_LINE_LENGTH);
    }
    return array_slice($lines, 0, MAX_LINES);
}

// Returns the notice lines, or null when the page could not be read or understood.
function read_notices(): ?array
{
    $page = fetch_page();
    if ($page === null) {
        return null;
    }
    // The page repeats this field and the first copy is empty, so use the first one that has text.
    if (!preg_match_all('/"online_booking_welcome_message":("(?:[^"\\\\]|\\\\.)*")/', $page, $found)) {
        return null;
    }
    foreach ($found[1] as $encoded) {
        $markup = json_decode($encoded);
        if (is_string($markup)) {
            $lines = to_lines($markup);
            if ($lines) {
                return $lines;
            }
        }
    }
    return [];
}

function save_notices(array $messages): void
{
    $json = json_encode(['source' => SOURCE_URL, 'messages' => $messages], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    $temp = CACHE_FILE . '.' . getmypid() . '.tmp';
    if (@file_put_contents($temp, $json, LOCK_EX) !== false) {
        @rename($temp, CACHE_FILE);
    }
    @unlink($temp);
}

$cached = is_file(CACHE_FILE) ? file_get_contents(CACHE_FILE) : false;
$age = is_file(CACHE_FILE) ? time() - filemtime(CACHE_FILE) : PHP_INT_MAX;

if ($cached === false || $age >= CACHE_SECONDS) {
    $messages = read_notices();
    if ($messages !== null) {
        save_notices($messages);
        echo json_encode(['source' => SOURCE_URL, 'messages' => $messages], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    if ($cached !== false) {
        // Keep serving the last good copy and try again in a few minutes.
        @touch(CACHE_FILE, time() - CACHE_SECONDS + RETRY_SECONDS);
    }
}

echo $cached !== false ? $cached : json_encode(['messages' => []]);
