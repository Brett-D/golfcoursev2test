// Copies the course notices from the ForeUp booking page into data/notices.json.
//
// The browser cannot read ForeUp directly (it blocks other sites), so this runs on a schedule in
// GitHub Actions (.github/workflows/update-notices.yml) and the website reads the saved file.
// You can also run it by hand:  node scripts/fetch-notices.mjs
import { readFile, writeFile, mkdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import path from "node:path";

const URL_ = "https://foreupsoftware.com/index.php/booking/21987/9520";
const OUT = path.join(path.dirname(fileURLToPath(import.meta.url)), "..", "data", "notices.json");

const ENTITIES = { amp: "&", lt: "<", gt: ">", quot: '"', apos: "'", nbsp: " " };

function decode(text) {
  return text.replace(/&(#x[0-9a-f]+|#\d+|[a-z]+);/gi, (whole, code) => {
    if (code[0] === "#") {
      const number = code[1].toLowerCase() === "x" ? parseInt(code.slice(2), 16) : parseInt(code.slice(1), 10);
      return String.fromCodePoint(number);
    }
    return ENTITIES[code.toLowerCase()] ?? whole;
  });
}

function toLines(markup) {
  const text = decode(
    markup
      .replace(/<img[^>]*>/gi, "")
      .replace(/<br\s*\/?>|<\/p>|<\/div>|<\/li>/gi, "\n")
      .replace(/<[^>]+>/g, "")
  ).replace(/\u00a0/g, " ");
  return text
    .split("\n")
    .map((line) => line.replace(/\s+/g, " ").trim())
    .filter((line) => line && !/^thank you!?$/i.test(line));
}

const response = await fetch(URL_, { headers: { "User-Agent": "AgateBeachWebsite/1.0 (notice sync)" } });
if (!response.ok) throw new Error(`ForeUp returned HTTP ${response.status}`);
const page = await response.text();

// The page repeats this field and the first copy is empty, so use the first one that has text.
const copies = [...page.matchAll(/"online_booking_welcome_message":("(?:[^"\\]|\\.)*")/g)];
if (!copies.length) throw new Error("Could not find the welcome message on the booking page; leaving notices.json unchanged.");
const messages = copies.map((match) => toLines(JSON.parse(match[1]))).find((lines) => lines.length) ?? [];

let previous = null;
try {
  previous = JSON.parse(await readFile(OUT, "utf8")).messages;
} catch {
  /* first run */
}
if (JSON.stringify(previous) === JSON.stringify(messages)) {
  console.log("No change.");
} else {
  await mkdir(path.dirname(OUT), { recursive: true });
  await writeFile(OUT, JSON.stringify({ source: URL_, messages }, null, 2) + "\n", "utf8");
  console.log(`Updated notices.json with ${messages.length} messages.`);
}
