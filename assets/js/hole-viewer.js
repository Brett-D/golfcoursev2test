// Shared hole viewer: a modal with the full layout picture and previous/next buttons.
// HoleViewer.open(index, { count, describe(index) -> text }) where index is 0-based.
window.HoleViewer = (function () {
  "use strict";

  var ASSET_VERSION = "20261002-2"; // set by tools/bump-version.ps1; changes the picture URLs so caches fetch new files
  var PATH = "assets/images/holes/vector/hole-";
  var dialog;
  var image;
  var title;
  var meta;
  var current = 0;
  var options = { count: 9, describe: function () { return ""; } };

  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function build() {
    dialog = el("dialog", "hole-dialog");
    dialog.setAttribute("aria-labelledby", "hole-dialog-title");

    var close = el("button", "hole-close", "\u00d7");
    close.type = "button";
    close.setAttribute("aria-label", "Close");
    close.addEventListener("click", function () { dialog.close(); });

    var prev = el("button", "hole-step hole-prev", "\u2039");
    prev.type = "button";
    prev.setAttribute("aria-label", "Previous hole");
    prev.addEventListener("click", function () { step(-1); });

    var next = el("button", "hole-step hole-next", "\u203a");
    next.type = "button";
    next.setAttribute("aria-label", "Next hole");
    next.addEventListener("click", function () { step(1); });

    var figure = el("figure", "hole-figure");
    image = el("img");
    figure.appendChild(image);

    var caption = el("figcaption", "hole-caption");
    title = el("h3");
    title.id = "hole-dialog-title";
    meta = el("p", "hole-meta");
    caption.appendChild(title);
    caption.appendChild(meta);
    caption.appendChild(el("p", "hole-key", "Tees at the bottom, green at the top."));

    var stage = el("div", "hole-stage");
    stage.appendChild(prev);
    stage.appendChild(figure);
    stage.appendChild(next);

    dialog.appendChild(close);
    dialog.appendChild(stage);
    dialog.appendChild(caption);
    document.body.appendChild(dialog);

    dialog.addEventListener("click", function (event) { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener("keydown", function (event) {
      if (event.key === "ArrowLeft") step(-1);
      if (event.key === "ArrowRight") step(1);
    });
    dialog.addEventListener("close", function () { document.documentElement.classList.remove("no-scroll"); });
  }

  function step(direction) {
    show((current + direction + options.count) % options.count);
  }

  function show(index) {
    current = index;
    image.src = PATH + ((index % 9) + 1) + ".svg?v=" + ASSET_VERSION;
    image.alt = "Layout of hole " + (index + 1) + ", with the tees at the bottom and the green at the top";
    title.textContent = "Hole " + (index + 1);
    meta.textContent = options.describe(index);
  }

  function open(index, settings) {
    if (!dialog) build();
    options = { count: (settings && settings.count) || 9, describe: (settings && settings.describe) || function () { return ""; } };
    show(index);
    if (typeof dialog.showModal === "function") {
      if (!dialog.open) dialog.showModal();
    } else {
      dialog.setAttribute("open", "");
    }
    document.documentElement.classList.add("no-scroll");
  }

  return { open: open };
})();