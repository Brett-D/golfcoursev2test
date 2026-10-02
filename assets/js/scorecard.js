(function () {
  "use strict";

  var root = document.getElementById("digital-scorecard");
  var printed = document.querySelector("table.scorecard");
  if (!root || !printed) return;

  // Yardage and par come from the printed scorecard table, so there is one place to edit them.
  function readRow(name) {
    var row = printed.querySelector('tr[data-row="' + name + '"]');
    var cells = row ? row.querySelectorAll("td") : [];
    var values = [];
    for (var i = 0; i < 9; i++) values.push(parseInt(String(cells[i] ? cells[i].textContent : "0").replace(/,/g, ""), 10) || 0);
    return values;
  }

  var tees = {
    men: { label: "Men\u2019s tees", yards: readRow("men-yards"), par: readRow("men-par"), hcp: readRow("men-hcp") },
    ladies: { label: "Ladies\u2019 tees", yards: readRow("ladies-yards"), par: readRow("ladies-par"), hcp: readRow("ladies-hcp") }
  };

  var STORAGE_KEY = "agate-beach-scorecard";
  var MAX_PLAYERS = 4;

  function blankScores() {
    var scores = [];
    for (var i = 0; i < 18; i++) scores.push("");
    return scores;
  }

  function freshState() {
    var players = [];
    for (var i = 0; i < MAX_PLAYERS; i++) players.push({ name: "", tee: "men", scores: blankScores() });
    return { holes: 9, count: 1, players: players };
  }

  function loadState() {
    var state = freshState();
    try {
      var saved = JSON.parse(window.localStorage.getItem(STORAGE_KEY));
      if (!saved) return state;
      if (saved.holes === 18) state.holes = 18;
      state.count = Math.min(MAX_PLAYERS, Math.max(1, parseInt(saved.count, 10) || 1));
      state.players.forEach(function (player, index) {
        var source = saved.players && saved.players[index];
        if (!source) return;
        player.name = String(source.name || "").slice(0, 24);
        // Older saved rounds had one set of tees for everybody.
        var savedTee = source.tee || saved.tee;
        if (tees[savedTee]) player.tee = savedTee;
        for (var i = 0; i < 18; i++) {
          var value = source.scores && source.scores[i];
          player.scores[i] = /^\d{1,2}$/.test(String(value)) ? String(value) : "";
        }
      });
    } catch (error) {
      return state;
    }
    return state;
  }

  var state = loadState();

  function save() {
    try {
      window.localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
    } catch (error) { /* storage can be unavailable (private mode); the card still works */ }
  }

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function sum(list) {
    return list.reduce(function (total, value) { return total + value; }, 0);
  }

  function signed(value) {
    return value === 0 ? "E" : (value > 0 ? "+" : "\u2212") + Math.abs(value);
  }

  // ---------- Hole layout pictures ----------
  // Small picture beside each hole. Mouse hover shows a larger preview; click or tap opens a full viewer.
  var ASSET_VERSION = "20261002-24"; // set by tools/bump-version.ps1; changes the picture URLs so caches fetch new files
  var HOLE_PATH = "assets/images/holes/vector/hole-";
  var peek;
  var peekImage;

  function holeNumber(index) { return (index % 9) + 1; }

  function holeButton(index) {
    var button = el("button", "hole-icon");
    button.type = "button";
    button.setAttribute("aria-label", "Hole " + (index + 1) + " layout");
    var img = el("img");
    img.src = HOLE_PATH + holeNumber(index) + ".svg?v=" + ASSET_VERSION;
    img.alt = "";
    img.width = 38;
    img.height = 64;
    img.decoding = "async";
    button.appendChild(img);
    button.appendChild(el("span", "hole-num", String(index + 1)));

    button.addEventListener("pointerenter", function (event) {
      if (event.pointerType === "mouse") showPeek(button, index);
    });
    button.addEventListener("pointerleave", hidePeek);
    button.addEventListener("click", function () {
      hidePeek();
      openHole(index);
    });
    return button;
  }

  function ensurePeek() {
    if (peek) return;
    peek = el("div", "hole-peek");
    peek.setAttribute("aria-hidden", "true");
    peekImage = el("img");
    peekImage.alt = "";
    peek.appendChild(peekImage);
    document.body.appendChild(peek);
    window.addEventListener("scroll", hidePeek, { passive: true });
    window.addEventListener("resize", hidePeek);
  }

  function showPeek(button, index) {
    ensurePeek();
    var height = Math.min(window.innerHeight * 0.6, 440);
    peekImage.style.height = height + "px";
    peekImage.src = HOLE_PATH + holeNumber(index) + ".svg?v=" + ASSET_VERSION;
    var rect = button.getBoundingClientRect();
    var top = Math.max(8, Math.min(rect.top + rect.height / 2 - height / 2, window.innerHeight - height - 8));
    var left = Math.min(rect.right + 14, window.innerWidth - 360);
    peek.style.top = top + "px";
    peek.style.left = Math.max(8, left) + "px";
    peek.classList.add("is-on");
  }

  function hidePeek() {
    if (peek) peek.classList.remove("is-on");
  }

  function openHole(index) {
    HoleViewer.open(index, {
      count: state.holes,
      describe: function (i) {
        var slot = i % 9;
        return usedTees().map(function (key) {
          var tee = tees[key];
          return tee.label + ": Par " + tee.par[slot] + " \u00b7 " + tee.yards[slot] + " yds \u00b7 Handicap " + tee.hcp[slot];
        });
      }
    });
  }
  var table;
  var totalCells = [];
  var inputs = [];

  // The sets of tees the players on this card are using (men's first), and a player's own tee set.
  function usedTees() {
    var keys = [];
    for (var p = 0; p < state.count; p++) {
      if (keys.indexOf(state.players[p].tee) < 0) keys.push(state.players[p].tee);
    }
    keys.sort(function (a, b) { return a === b ? 0 : (a === "men" ? -1 : 1); });
    return keys;
  }

  function teeOf(playerIndex) { return tees[state.players[playerIndex].tee]; }

  // A yardage/par cell. With one set of tees it is a plain number; with men and ladies in the group it shows both,
  // in the printed card's colours (blue men, red ladies). Par is only doubled up where the two differ.
  function teeCell(keys, valueFor, className, collapseEqual) {
    var cell = el("td", className || "");
    var values = keys.map(valueFor);
    if (keys.length === 1 || (collapseEqual && values.every(function (v) { return v === values[0]; }))) {
      cell.textContent = values[0];
    } else {
      keys.forEach(function (key, index) {
        cell.appendChild(el("span", "tee-line tee-" + key, values[index]));
      });
    }
    return cell;
  }

  function selectField(id, label, options, current, onChange) {
    var wrap = el("div", "sc-field");
    var lab = el("label", "", label);
    lab.setAttribute("for", id);
    var select = el("select");
    select.id = id;
    options.forEach(function (option) {
      var node = el("option", "", option.label);
      node.value = option.value;
      if (String(option.value) === String(current)) node.selected = true;
      select.appendChild(node);
    });
    select.addEventListener("change", function () { onChange(select.value); });
    wrap.appendChild(lab);
    wrap.appendChild(select);
    return wrap;
  }

  function build() {
    root.textContent = "";
    totalCells = [];
    inputs = [];

    var keys = usedTees();
    var controls = el("div", "sc-controls");
    controls.appendChild(selectField("sc-holes", "Round", [
      { value: 9, label: "9 holes" },
      { value: 18, label: "18 holes" }
    ], state.holes, function (value) { state.holes = parseInt(value, 10); save(); build(); }));
    controls.appendChild(selectField("sc-players", "Players", [
      { value: 1, label: "1 player" },
      { value: 2, label: "2 players" },
      { value: 3, label: "3 players" },
      { value: 4, label: "4 players" }
    ], state.count, function (value) { state.count = parseInt(value, 10); save(); build(); }));
    root.appendChild(controls);

    var wrap = el("div", "table-wrap");
    table = el("table", "sc-entry");

    var head = el("tr");
    ["Hole", "Yds", "Par"].forEach(function (text) {
      var th = el("th", "", text);
      th.scope = "col";
      head.appendChild(th);
    });
    for (var p = 0; p < state.count; p++) {
      (function (index) {
        var th = el("th", "sc-name");
        th.scope = "col";
        var input = el("input");
        input.type = "text";
        input.maxLength = 24;
        input.placeholder = "Player " + (index + 1);
        input.value = state.players[index].name;
        input.setAttribute("aria-label", "Player " + (index + 1) + " name");
        input.addEventListener("input", function () {
          state.players[index].name = input.value;
          save();
        });
        th.appendChild(input);
        var teeButton = el("button", "sc-tee-btn is-" + state.players[index].tee, state.players[index].tee === "men" ? "Men\u2019s" : "Ladies\u2019");
        teeButton.type = "button";
        teeButton.setAttribute("aria-label", "Player " + (index + 1) + " plays from the " + tees[state.players[index].tee].label.toLowerCase() + ". Press to switch.");
        teeButton.addEventListener("click", function () {
          state.players[index].tee = state.players[index].tee === "men" ? "ladies" : "men";
          save();
          build();
        });
        th.appendChild(teeButton);
        head.appendChild(th);
      })(p);
    }
    var thead = el("thead");
    thead.appendChild(head);
    table.appendChild(thead);

    var body = el("tbody");

    function summaryRow(label, from, to, className) {
      var row = el("tr", "sc-sum " + (className || ""));
      row.appendChild(el("th", "", label));
      row.appendChild(teeCell(keys, function (key) { return sum(tees[key].yards.concat(tees[key].yards).slice(from, to)).toLocaleString("en-US"); }));
      row.appendChild(teeCell(keys, function (key) { return String(sum(tees[key].par.concat(tees[key].par).slice(from, to))); }, "", true));
      for (var p = 0; p < state.count; p++) {
        var cell = el("td", "", "\u2013");
        totalCells.push({ cell: cell, player: p, from: from, to: to, kind: "sum" });
        row.appendChild(cell);
      }
      return row;
    }

    for (var hole = 0; hole < state.holes; hole++) {
      var row = el("tr");
      var holeHead = el("th", "sc-hole");
      holeHead.appendChild(holeButton(hole));
      row.appendChild(holeHead);
      row.appendChild(teeCell(keys, function (key) { return String(tees[key].yards[hole % 9]); }));
      row.appendChild(teeCell(keys, function (key) { return String(tees[key].par[hole % 9]); }, "sc-par", true));
      for (var q = 0; q < state.count; q++) {
        (function (holeIndex, playerIndex) {
          var cell = el("td", "sc-score");
          var input = el("input");
          input.type = "text";
          input.inputMode = "numeric";
          input.maxLength = 2;
          input.autocomplete = "off";
          input.value = state.players[playerIndex].scores[holeIndex];
          input.setAttribute("aria-label", "Hole " + (holeIndex + 1) + ", player " + (playerIndex + 1) + " strokes");
          input.addEventListener("input", function () {
            input.value = input.value.replace(/\D/g, "").slice(0, 2);
            state.players[playerIndex].scores[holeIndex] = input.value;
            save();
            refresh();
          });
          input.addEventListener("focus", function () { input.select(); });
          cell.appendChild(input);
          inputs.push({ input: input, hole: holeIndex, player: playerIndex });
          row.appendChild(cell);
        })(hole, q);
      }
      body.appendChild(row);
      if (state.holes === 18 && hole === 8) body.appendChild(summaryRow("Out", 0, 9));
    }

    if (state.holes === 18) body.appendChild(summaryRow("In", 9, 18));
    body.appendChild(summaryRow("Total", 0, state.holes, "sc-grand"));

    var relative = el("tr", "sc-sum sc-relative");
    relative.appendChild(el("th", "", "vs par"));
    relative.appendChild(el("td", ""));
    relative.appendChild(el("td", ""));
    for (var r = 0; r < state.count; r++) {
      var rel = el("td", "", "\u2013");
      totalCells.push({ cell: rel, player: r, from: 0, to: state.holes, kind: "relative" });
      relative.appendChild(rel);
    }
    body.appendChild(relative);

    table.appendChild(body);
    wrap.appendChild(table);
    root.appendChild(wrap);

    var actions = el("div", "btn-row");
    var reset = el("button", "btn btn-outline-dark", "Start a new round");
    reset.type = "button";
    reset.addEventListener("click", function () {
      if (!window.confirm("Clear all scores and start a new round?")) return;
      state.players.forEach(function (player) { player.scores = blankScores(); });
      save();
      build();
    });
    actions.appendChild(reset);
    root.appendChild(actions);
    root.appendChild(el("p", "note", "Tap the tee button under each name to choose men\u2019s or ladies\u2019 tees, so a mixed group gets the right yardage and par. " +
      (keys.length > 1 ? "Blue is the men\u2019s tees and red is the ladies\u2019 tees. " : "") +
      "Scores are saved on this device only, so you can close the page and pick up where you left off."));

    refresh();
  }

  function scoreClass(strokes, par) {
    var diff = strokes - par;
    if (diff <= -2) return "is-eagle";
    if (diff === -1) return "is-birdie";
    if (diff === 1) return "is-bogey";
    if (diff >= 2) return "is-double";
    return "";
  }

  function refresh() {
    inputs.forEach(function (item) {
      var value = state.players[item.player].scores[item.hole];
      var cls = value ? scoreClass(parseInt(value, 10), teeOf(item.player).par[item.hole % 9]) : "";
      item.input.className = cls;
    });

    totalCells.forEach(function (item) {
      var scores = state.players[item.player].scores;
      var par = teeOf(item.player).par;
      var total = 0;
      var parPlayed = 0;
      var played = 0;
      for (var i = item.from; i < item.to; i++) {
        if (!scores[i]) continue;
        total += parseInt(scores[i], 10);
        parPlayed += par[i % 9];
        played++;
      }
      if (!played) {
        item.cell.textContent = "\u2013";
      } else if (item.kind === "sum") {
        item.cell.textContent = String(total);
      } else {
        item.cell.textContent = signed(total - parPlayed);
      }
    });
  }

  build();
})();
