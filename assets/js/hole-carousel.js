// Hole-by-hole card stack on the course page. Cards are plain HTML; this fans them out, adds arrows, number chips,
// hover/swipe to bring a hole to the top, and the full-size viewer.
(function () {
  "use strict";

  var root = document.querySelector("[data-hole-carousel]");
  if (!root) return;
  var track = root.querySelector(".hc-track");
  var items = Array.prototype.slice.call(track.querySelectorAll("li"));
  var cards = items.map(function (item) { return item.querySelector(".hc-card"); });
  var printed = document.querySelector("table.scorecard");

  // Men's yardage, par and handicap come from the printed table on this page.
  function readRow(name) {
    var row = printed && printed.querySelector('tr[data-row="' + name + '"]');
    var cells = row ? row.querySelectorAll("td") : [];
    var values = [];
    for (var i = 0; i < 9; i++) values.push(cells[i] ? parseInt(cells[i].textContent.replace(/,/g, ""), 10) || 0 : 0);
    return values;
  }
  var men = { label: "Men\u2019s tees", yards: readRow("men-yards"), par: readRow("men-par"), hcp: readRow("men-hcp") };
  var ladies = { label: "Ladies\u2019 tees", yards: readRow("ladies-yards"), par: readRow("ladies-par"), hcp: readRow("ladies-hcp") };

  // The viewer shows both sets of tees.
  function describe(i) {
    return [men, ladies].map(function (tee) {
      return tee.label + ": Par " + tee.par[i] + " \u00b7 " + tee.yards[i] + " yds \u00b7 Handicap " + tee.hcp[i];
    });
  }

  var active = 0;
  var gap = 60; // how far apart the cards sit; they never move sideways, only their stacking order changes
  var chips = [];
  var suppressClick = false;
  var SWIPE_STEP = 28; // pixels of swipe per card

  function clamp(value, low, high) { return Math.max(low, Math.min(high, value)); }

  function control(className, label, text, handler) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.textContent = text;
    button.addEventListener("click", handler);
    return button;
  }

  function openViewer(i) {
    if (window.HoleViewer) window.HoleViewer.open(i, { count: 9, describe: describe });
  }

  // Spread the cards so the whole fan fits the width of the screen.
  function measure() {
    var width = cards[0].offsetWidth;
    gap = clamp((track.clientWidth - width - 8) / (cards.length - 1), 12, width * 0.4);
    root.classList.toggle("hc-tight", gap < 32);
  }

  // Cards left of the chosen one stack up towards it (2 over 1); cards to its right stack down away from it (4 over 5 over 6 ...).
  function layout() {
    var width = cards[0].offsetWidth;
    var total = gap * (cards.length - 1) + width;
    items.forEach(function (item, i) {
      var distance = Math.abs(i - active);
      // The further down the deck, the smaller the card. Each card shrinks towards the edge hidden under its neighbour,
      // so the strip you can see (and point at) stays put and the stack does not wobble under the pointer.
      var scale = 1 - 0.05 * distance;
      item.style.transformOrigin = i < active ? "0% 50%" : i > active ? "100% 50%" : "50% 50%";
      item.style.transform = "translateX(" + (i * gap - total / 2).toFixed(1) + "px) scale(" + scale.toFixed(3) + ")";
      item.style.zIndex = String(i === active ? 100 : i < active ? i : 90 - i);
      cards[i].classList.toggle("is-active", i === active);
      cards[i].classList.toggle("is-right", i > active);
      cards[i].tabIndex = i === active ? 0 : -1;
      var chip = chips[i];
      if (chip) { if (i === active) chip.setAttribute("aria-current", "true"); else chip.removeAttribute("aria-current"); }
    });
    prev.disabled = active === 0;
    next.disabled = active === cards.length - 1;
  }

  function go(index) {
    active = clamp(index, 0, cards.length - 1);
    layout();
  }

  cards.forEach(function (card, i) {
    card.querySelector("img").draggable = false;
    // A number tab shows on the visible edge of cards that are tucked under the top one.
    var badge = document.createElement("span");
    badge.className = "hc-badge";
    badge.setAttribute("aria-hidden", "true");
    badge.textContent = String(i + 1);
    card.appendChild(badge);
    var info = card.querySelector(".hc-meta");
    if (info && men.par[i]) {
      info.textContent = "";
      [["Men", men], ["Ladies", ladies]].forEach(function (pair) {
        var line = document.createElement("span");
        line.className = "hc-meta-line";
        line.textContent = pair[0] + " \u00b7 Par " + pair[1].par[i] + " \u00b7 " + pair[1].yards[i] + " yds";
        info.appendChild(line);
      });
    }
    // The top card opens the viewer; tapping any other card brings it to the top.
    card.addEventListener("click", function () {
      if (suppressClick) return;
      if (i === active) openViewer(i); else go(i);
    });
  });

  var prev = control("hc-arrow hc-prev", "Previous hole", "\u2039", function () { go(active - 1); });
  var next = control("hc-arrow hc-next", "Next hole", "\u203a", function () { go(active + 1); });
  root.appendChild(prev);
  root.appendChild(next);

  var nav = document.createElement("div");
  nav.className = "hc-chips";
  cards.forEach(function (card, i) {
    var chip = control("hc-chip", "Show hole " + (i + 1), String(i + 1), function () { go(i); });
    chips.push(chip);
    nav.appendChild(chip);
  });
  root.appendChild(nav);

  // Mouse: whichever card is on top under the pointer comes to the top of the stack. Because the cards never slide sideways,
  // the card you point at is always the one you end up with. Touch: swiping moves the stack by how far the finger travels.
  function cardAt(x, y) {
    var node = document.elementFromPoint(x, y);
    var item = node && node.closest ? node.closest(".hc-track li") : null;
    return item ? items.indexOf(item) : -1;
  }

  var touch = null;
  track.addEventListener("pointerdown", function (event) {
    if (event.pointerType === "mouse") return;
    touch = { x: event.clientX, from: active, moved: false };
    suppressClick = false;
  });
  track.addEventListener("pointermove", function (event) {
    if (event.pointerType !== "mouse") {
      if (!touch) return;
      if (!touch.moved && Math.abs(event.clientX - touch.x) > 3) { touch.moved = true; suppressClick = true; }
      if (!touch.moved) return;
      // A swipe moves the stack by how far the finger travels, wherever on the stack it started.
      go(touch.from - Math.round((event.clientX - touch.x) / SWIPE_STEP));
      return;
    }
    var i = cardAt(event.clientX, event.clientY);
    if (i >= 0 && i !== active) go(i);
  });
  function release() {
    if (!touch) return;
    touch = null;
    window.setTimeout(function () { suppressClick = false; }, 0);
  }
  window.addEventListener("pointerup", release);
  window.addEventListener("pointercancel", release);

  track.addEventListener("keydown", function (event) {
    if (event.key === "ArrowRight") { event.preventDefault(); go(active + 1); }
    if (event.key === "ArrowLeft") { event.preventDefault(); go(active - 1); }
  });

  window.addEventListener("resize", function () { measure(); layout(); });
  root.classList.add("is-stack");
  measure();
  layout();
})();