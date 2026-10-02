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
  var pos = 0; // fractional while a finger or mouse is dragging the stack
  var active = 0;
  var peek = -1; // card under the mouse, lifted to the front without moving the stack
  var step = 60;
  var chips = [];
  var dragged = false;

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

  // How far each card is shifted so that the whole fan fits the width of the screen.
  function measure() {
    var half = track.clientWidth / 2 - cards[0].offsetWidth / 2 - 6;
    step = Math.max(12, Math.min(cards[0].offsetWidth * 0.62, half / VISIBLE));
  }

  function layout() {
    active = clamp(Math.round(pos), 0, cards.length - 1);
    items.forEach(function (item, i) {
      var offset = i - pos;
      var distance = Math.abs(offset);
      var slot = Math.min(distance, VISIBLE);
      var shown = clamp(VISIBLE + 1 - distance, 0, 1);
      var lifted = i === peek && shown > 0;
      item.style.transform = "translateX(" + ((offset < 0 ? -1 : 1) * slot * step).toFixed(1) + "px) " + (lifted ? "translateY(-8px) scale(1)" : "scale(" + (1 - 0.075 * slot).toFixed(3) + ")");
      item.style.opacity = shown.toFixed(2);
      item.style.zIndex = String(lifted ? 300 : 200 - Math.round(distance * 20));
      cards[i].classList.toggle("is-peek", lifted);
      item.style.pointerEvents = shown > 0 ? "auto" : "none";
      cards[i].classList.toggle("is-active", i === active);
      cards[i].tabIndex = i === active ? 0 : -1;
      cards[i].setAttribute("aria-hidden", shown > 0 ? "false" : "true");
      var chip = chips[i];
      if (chip) { if (i === active) chip.setAttribute("aria-current", "true"); else chip.removeAttribute("aria-current"); }
    });
    prev.disabled = active === 0;
    next.disabled = active === cards.length - 1;
  }

  function go(index) {
    pos = clamp(index, 0, cards.length - 1);
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

  // Touch: the stack follows your finger and settles on the nearest hole when you let go.
  var drag = null;
  var lastX = 0;
  var lastTime = 0;
  var velocity = 0;

  track.addEventListener("pointerdown", function (event) {
    if (event.pointerType === "mouse") return;
    drag = { x: event.clientX, from: pos, moving: false };
    lastX = event.clientX;
    lastTime = event.timeStamp;
    velocity = 0;
    dragged = false;
  });

  window.addEventListener("pointermove", function (event) {
    if (drag) {
      var dx = event.clientX - drag.x;
      if (!drag.moving && Math.abs(dx) > 8) {
        drag.moving = true;
        dragged = true;
        track.classList.add("is-dragging");
      }
      if (drag.moving) {
        var dt = Math.max(1, event.timeStamp - lastTime);
        velocity = (event.clientX - lastX) / dt;
        lastX = event.clientX;
        lastTime = event.timeStamp;
        pos = clamp(drag.from - dx / (step * 1.6), 0, cards.length - 1);
        layout();
      }
      return;
    }
  });

  // Mouse: the card under the pointer lifts to the front where it is. Click it to bring it to the centre.
  items.forEach(function (item, i) {
    item.addEventListener("pointerenter", function (event) {
      if (event.pointerType !== "mouse" || drag) return;
      peek = i;
      layout();
    });
  });
  track.addEventListener("pointerleave", function (event) {
    if (event.pointerType !== "mouse" || peek < 0) return;
    peek = -1;
    layout();
  });

  function release() {
    if (!drag) return;
    var moved = drag.moving;
    drag = null;
    track.classList.remove("is-dragging");
    if (moved) go(Math.round(pos - velocity * 180 / (step * 1.6)));
    window.setTimeout(function () { dragged = false; }, 0);
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