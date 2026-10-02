// Hole-by-hole card stack on the course page. Cards are plain HTML; this stacks them, adds arrows, number chips, swipe/drag and the viewer.
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
  var yards = readRow("men-yards");
  var par = readRow("men-par");
  var hcp = readRow("men-hcp");

  function describe(i) {
    return "Par " + par[i] + " \u00b7 " + yards[i] + " yds \u00b7 Handicap " + hcp[i] + " \u00b7 Men\u2019s tees";
  }

  var VISIBLE = 4; // cards fanned out on each side before the rest tuck underneath
  var active = 0;
  var step = 60;
  var chips = [];
  var dragged = false;

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

  // How far each card is shifted so that the whole fan fits the width of the screen.
  function measure() {
    var half = track.clientWidth / 2 - cards[0].offsetWidth / 2 - 6;
    step = Math.max(12, Math.min(cards[0].offsetWidth * 0.62, half / VISIBLE));
  }

  function layout() {
    items.forEach(function (item, i) {
      var offset = i - active;
      var distance = Math.abs(offset);
      var slot = Math.min(distance, VISIBLE);
      var side = offset < 0 ? -1 : 1;
      var scale = 1 - 0.075 * slot;
      var visible = distance <= VISIBLE;
      item.style.transform = "translateX(" + (side * slot * step).toFixed(1) + "px) scale(" + scale.toFixed(3) + ")";
      item.style.opacity = visible ? "1" : "0";
      item.style.zIndex = String(50 - distance);
      item.style.pointerEvents = visible ? "auto" : "none";
      cards[i].classList.toggle("is-active", i === active);
      cards[i].tabIndex = i === active ? 0 : -1;
      cards[i].setAttribute("aria-hidden", visible ? "false" : "true");
      var chip = chips[i];
      if (chip) { if (i === active) chip.setAttribute("aria-current", "true"); else chip.removeAttribute("aria-current"); }
    });
    prev.disabled = active === 0;
    next.disabled = active === cards.length - 1;
  }

  function go(index) {
    active = Math.max(0, Math.min(cards.length - 1, index));
    layout();
  }

  cards.forEach(function (card, i) {
    card.querySelector("img").draggable = false;
    var info = card.querySelector(".hc-meta");
    if (info && par[i]) info.textContent = "Par " + par[i] + " \u00b7 " + yards[i] + " yds";
    // The top card opens the viewer; any other card is brought to the front first.
    card.addEventListener("click", function () {
      if (dragged) return;
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

  // Swipe or drag sideways to change hole.
  var start = null;
  track.addEventListener("pointerdown", function (event) {
    if (event.pointerType === "mouse" && event.button !== 0) return;
    start = { x: event.clientX, y: event.clientY };
    dragged = false;
  });
  track.addEventListener("pointermove", function (event) {
    if (!start) return;
    var dx = event.clientX - start.x;
    if (Math.abs(dx) > 8) { dragged = true; track.classList.add("is-dragging"); }
  });
  function finish(event) {
    if (!start) return;
    var dx = event.clientX - start.x;
    start = null;
    track.classList.remove("is-dragging");
    if (dragged && Math.abs(dx) > 40) go(active + (dx < 0 ? 1 : -1));
    window.setTimeout(function () { dragged = false; }, 0);
  }
  window.addEventListener("pointerup", finish);
  window.addEventListener("pointercancel", function () { start = null; track.classList.remove("is-dragging"); });

  track.addEventListener("keydown", function (event) {
    if (event.key === "ArrowRight") { event.preventDefault(); go(active + 1); }
    if (event.key === "ArrowLeft") { event.preventDefault(); go(active - 1); }
  });

  window.addEventListener("resize", function () { measure(); layout(); });
  root.classList.add("is-stack");
  measure();
  layout();
})();