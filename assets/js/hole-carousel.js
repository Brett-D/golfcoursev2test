// Hole-by-hole carousel on the course page. Cards are plain HTML; this adds the centring, arrows, number chips and the viewer.
(function () {
  "use strict";

  var root = document.querySelector("[data-hole-carousel]");
  if (!root) return;
  var track = root.querySelector(".hc-track");
  var cards = Array.prototype.slice.call(track.querySelectorAll(".hc-card"));
  var printed = document.querySelector("table.scorecard");
  var calm = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Men's yardage, par and handicap come from the printed table on this page.
  function readRow(name) {
    var row = printed && printed.querySelector('tr[data-row="' + name + '"]');
    var cells = row ? row.querySelectorAll("td") : [];
    var values = [];
    for (var i = 0; i < 9; i++) values.push(cells[i] ? parseInt(cells[i].textContent.replace(/,/g, ""), 10) || 0 : 0);
    return values;
  }
  var yards = readRow("men-yards");
  var par = readRow("men-par");
  var hcp = readRow("men-hcp");

  function describe(i) {
    return "Par " + par[i] + " \u00b7 " + yards[i] + " yds \u00b7 Handicap " + hcp[i] + " \u00b7 Men\u2019s tees";
  }

  cards.forEach(function (card, i) {
    var info = card.querySelector(".hc-meta");
    if (info && par[i]) info.textContent = "Par " + par[i] + " \u00b7 " + yards[i] + " yds";
    card.addEventListener("click", function () {
      if (window.HoleViewer) window.HoleViewer.open(i, { count: 9, describe: describe });
    });
  });

  var active = 0;
  var chips = [];

  function go(index) {
    index = Math.max(0, Math.min(cards.length - 1, index));
    var card = cards[index];
    track.scrollTo({ left: card.offsetLeft - (track.clientWidth - card.offsetWidth) / 2, behavior: calm ? "auto" : "smooth" });
  }

  function update() {
    var middle = track.scrollLeft + track.clientWidth / 2;
    var best = 0;
    var distance = Infinity;
    cards.forEach(function (card, i) {
      var gap = Math.abs(card.offsetLeft + card.offsetWidth / 2 - middle);
      if (gap < distance) { distance = gap; best = i; }
    });
    active = best;
    cards.forEach(function (card, i) { card.classList.toggle("is-active", i === best); });
    chips.forEach(function (chip, i) {
      if (i === best) chip.setAttribute("aria-current", "true"); else chip.removeAttribute("aria-current");
    });
    prev.disabled = best === 0;
    next.disabled = best === cards.length - 1;
  }

  function control(className, label, text, handler) {
    var button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.setAttribute("aria-label", label);
    button.textContent = text;
    button.addEventListener("click", handler);
    return button;
  }

  var prev = control("hc-arrow hc-prev", "Previous hole", "\u2039", function () { go(active - 1); });
  var next = control("hc-arrow hc-next", "Next hole", "\u203a", function () { go(active + 1); });
  root.appendChild(prev);
  root.appendChild(next);

  var nav = document.createElement("div");
  nav.className = "hc-chips";
  cards.forEach(function (card, i) {
    var chip = control("hc-chip", "Go to hole " + (i + 1), String(i + 1), function () { go(i); });
    chips.push(chip);
    nav.appendChild(chip);
  });
  root.appendChild(nav);

  var ticking = false;
  track.addEventListener("scroll", function () {
    if (ticking) return;
    ticking = true;
    window.requestAnimationFrame(function () { ticking = false; update(); });
  }, { passive: true });
  window.addEventListener("resize", update);
  update();
})();