/* Site behaviour: nav, solid header on scroll, reveal animations, Facebook widget sizing, contact form. */
(function () {
  "use strict";

  document.documentElement.classList.add("js");

  var header = document.querySelector(".site-header");
  var toggle = document.querySelector(".nav-toggle");
  var menu = document.getElementById("nav-menu");

  function setMenu(open) {
    menu.classList.toggle("is-open", open);
    header.classList.toggle("menu-open", open);
    toggle.setAttribute("aria-expanded", String(open));
  }

  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      setMenu(!menu.classList.contains("is-open"));
    });
    menu.addEventListener("click", function (event) {
      if (event.target.tagName === "A") setMenu(false);
    });
    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") setMenu(false);
    });
  }

  function paintHeader() {
    header.classList.toggle("is-solid", window.scrollY > 40);
  }
  paintHeader();
  window.addEventListener("scroll", paintHeader, { passive: true });

  var here = window.location.pathname.split("/").pop() || "index.html";
  // The digital scorecard used to live on the course page; keep old links working.
  if (here === "course.html" && window.location.hash === "#my-scorecard") { window.location.replace("scorecard.html"); return; }
  Array.prototype.forEach.call(document.querySelectorAll(".nav-menu a"), function (link) {
    if (link.getAttribute("href") === here) link.setAttribute("aria-current", "page");
  });

  var reveals = document.querySelectorAll(".reveal");
  if ("IntersectionObserver" in window) {
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12 });
    Array.prototype.forEach.call(reveals, function (el) { observer.observe(el); });
  } else {
    Array.prototype.forEach.call(reveals, function (el) { el.classList.add("is-visible"); });
  }

  // Mouse parallax: pointer position becomes --mx/--my (-1..1) on the element; CSS turns that into movement.
  var fineHover = window.matchMedia("(hover: hover) and (pointer: fine)").matches;
  var calm = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  // Photos are pinned to the viewport so the page scrolls over them. The top banner on inner pages
  // stays centred and still; everything else (and the home banner) also follows the mouse.
  var isHome = here === "index.html";
  Array.prototype.forEach.call(document.querySelectorAll(".hero, .band"), function (section) {
    var image = section.style.backgroundImage;
    if (!image) return;
    var layer = document.createElement("div");
    var topBanner = section.classList.contains("hero") && !isHome;
    layer.className = "parallax-bg" + (calm ? " is-still" : topBanner ? " pin-top" : "");
    layer.setAttribute("aria-hidden", "true");
    layer.style.backgroundImage = image;
    layer.style.backgroundPosition = section.style.backgroundPosition || "center";
    section.insertBefore(layer, section.firstChild);
    section.style.backgroundImage = "none";
    if (section.dataset.video && !calm) addVideo(section, layer);
  });

  // Looping background video. The poster is the first frame, so the video fades in over it without a jump.
  function addVideo(section, layer) {
    var base = section.dataset.video;
    var video = document.createElement("video");
    video.className = "parallax-video";
    // iOS only autoplays when muted/playsinline exist as attributes, not just properties.
    video.defaultMuted = true;
    video.muted = true;
    video.setAttribute("muted", "");
    video.setAttribute("playsinline", "");
    video.setAttribute("webkit-playsinline", "");
    video.loop = true;
    video.autoplay = true;
    video.playsInline = true;
    video.preload = "auto";
    video.setAttribute("aria-hidden", "true");
    video.style.objectPosition = layer.style.backgroundPosition;
    ["webm", "mp4"].forEach(function (ext) {
      var source = document.createElement("source");
      source.src = base + "." + ext;
      source.type = "video/" + ext;
      video.appendChild(source);
    });
    video.addEventListener("playing", function () { video.classList.add("is-playing"); });
    layer.appendChild(video);
    var visible = true;
    function play() {
      if (!visible || !video.paused) return;
      var attempt = video.play();
      if (attempt && attempt.catch) attempt.catch(function () {});
    }
    if ("IntersectionObserver" in window) {
      new IntersectionObserver(function (entries) {
        visible = entries[0].isIntersecting;
        if (visible) play(); else video.pause();
      }).observe(section);
    } else {
      play();
    }
    // Phones in Low Power Mode refuse autoplay until the visitor touches the page, so retry on the first gesture.
    ["touchstart", "pointerdown", "scroll", "click", "keydown"].forEach(function (name) {
      window.addEventListener(name, play, { passive: true });
    });
    document.addEventListener("visibilitychange", function () { if (!document.hidden) play(); });
    video.addEventListener("canplay", play);
  }

  if (fineHover && !calm) {
    var selector = ".band, .frame, .photo-card, .gallery a" + (isHome ? ", .hero" : "");
    var targets = document.querySelectorAll(selector);
    var active = [];
    var running = false;

    // Eased every frame (not via CSS transitions) so movement follows the cursor smoothly without lag or snapping.
    function tick() {
      var moving = false;
      active = active.filter(function (s) {
        s.x += (s.tx - s.x) * 0.12;
        s.y += (s.ty - s.y) * 0.12;
        s.h += (s.th - s.h) * 0.12;
        var settled = Math.abs(s.tx - s.x) + Math.abs(s.ty - s.y) + Math.abs(s.th - s.h) < 0.002;
        if (settled) { s.x = s.tx; s.y = s.ty; s.h = s.th; }
        s.el.style.setProperty("--mx", s.x.toFixed(4));
        s.el.style.setProperty("--my", s.y.toFixed(4));
        s.el.style.setProperty("--hv", s.h.toFixed(4));
        if (settled) { s.queued = false; return false; }
        moving = true;
        return true;
      });
      if (moving) requestAnimationFrame(tick); else running = false;
    }

    function wake(s) {
      if (!s.queued) { s.queued = true; active.push(s); }
      if (!running) { running = true; requestAnimationFrame(tick); }
    }

    Array.prototype.forEach.call(targets, function (el) {
      var s = { el: el, x: 0, y: 0, h: 0, tx: 0, ty: 0, th: 0, queued: false };
      el.addEventListener("pointermove", function (event) {
        var box = el.getBoundingClientRect();
        s.tx = Math.max(-1, Math.min(1, ((event.clientX - box.left) / box.width) * 2 - 1));
        s.ty = Math.max(-1, Math.min(1, ((event.clientY - box.top) / box.height) * 2 - 1));
        s.th = 1;
        wake(s);
      });
      el.addEventListener("pointerleave", function () {
        s.tx = 0; s.ty = 0; s.th = 0;
        wake(s);
      });
    });
  }  // Rates come from data/rates.json so prices can be changed without touching the HTML.
  function el(tag, className, text) {
    var node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined) node.textContent = text;
    return node;
  }

  function renderRates(data) {
    Array.prototype.forEach.call(document.querySelectorAll("[data-rates]"), function (grid) {
      var homeOnly = grid.getAttribute("data-rates") === "home";
      grid.textContent = "";
      data.cards.forEach(function (card) {
        if (homeOnly && !card.home) return;
        var box = el("div", "rate-card");
        if (card.id) box.id = card.id;
        var head = el("header");
        head.appendChild(el("h3", "", card.title));
        if (card.subtitle) head.appendChild(el("p", "", card.subtitle));
        box.appendChild(head);
        (card.items || []).forEach(function (item) {
          var row = el("div", "rate-row");
          row.appendChild(el("span", "", item.label));
          row.appendChild(el("span", "price", item.price));
          box.appendChild(row);
        });
        grid.appendChild(box);
      });
    });
    Array.prototype.forEach.call(document.querySelectorAll("[data-rates-updated]"), function (node) {
      node.textContent = data.updated || "";
    });
    if (data.range_note) {
      Array.prototype.forEach.call(document.querySelectorAll("[data-rates-note]"), function (node) {
        node.textContent = data.range_note;
      });
    }
    if (window.location.hash) {
      var target = document.getElementById(window.location.hash.slice(1));
      if (target) target.scrollIntoView();
    }
  }

  if (document.querySelector("[data-rates]")) {
    fetch("data/rates.json", { cache: "no-cache" })
      .then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      })
      .then(renderRates)
      .catch(function () {
        Array.prototype.forEach.call(document.querySelectorAll("[data-rates]"), function (grid) {
          grid.textContent = "";
          grid.appendChild(el("p", "note", "Rates are unavailable right now. Please call the pro shop at (541) 265-7331."));
        });
        Array.prototype.forEach.call(document.querySelectorAll("[data-rates-updated]"), function (node) {
          node.parentElement.hidden = true;
        });
      });
  }

  // Course notices come from the ForeUp booking page. notices.php reads it on PHP hosting (Bluehost);
  // anywhere else the saved copy in data/notices.json is used.
  var noticeBox = document.querySelector("[data-notices]");
  if (noticeBox) {
    var loadJson = function (url) {
      return fetch(url, { cache: "no-cache" }).then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      });
    };
    loadJson("notices.php")
      .catch(function () { return loadJson("data/notices.json"); })
      .then(function (data) {
        var messages = (data && data.messages) || [];
        messages.forEach(function (message) {
          noticeBox.appendChild(el("p", "", message));
        });
        noticeBox.hidden = messages.length === 0;
      })
      .catch(function () { /* no notices to show */ });
  }

  // The Facebook Page plugin needs a pixel width between 180 and 500.
  function loadFacebook() {
    Array.prototype.forEach.call(document.querySelectorAll("[data-fb-page]"), function (frame) {
      var wrap = frame.parentElement;
      var width = Math.max(180, Math.min(500, Math.floor(wrap.clientWidth)));
      var src = "https://www.facebook.com/plugins/page.php?href=" + encodeURIComponent(frame.dataset.fbPage) +
        "&tabs=timeline&width=" + width + "&height=600&small_header=true&adapt_container_width=true" +
        "&hide_cover=false&show_facepile=true";
      if (frame.getAttribute("src") !== src) {
        frame.setAttribute("src", src);
        frame.setAttribute("width", String(width));
      }
    });
  }
  loadFacebook();
  var resizeTimer;
  window.addEventListener("resize", function () {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(loadFacebook, 300);
  });

  // Contact form: sends straight to the pro shop through contact.php. If that file is not there (a host without PHP,
  // such as GitHub Pages) or the request fails, it falls back to opening the visitor's email app with the message filled in.
  var form = document.getElementById("contact-form");
  if (form) {
    var started = document.getElementById("c-started");
    if (started) started.value = String(Date.now());
    var status = document.getElementById("form-status");
    var sendButton = form.querySelector('button[type="submit"]');

    function openEmailApp(data) {
      var body = data.get("message") + "\n\n— " + data.get("name") + " (" + data.get("email") + ")";
      window.location.href = "mailto:teeoff@agatebeachgolf.net?subject=" +
        encodeURIComponent(data.get("subject")) + "&body=" + encodeURIComponent(body);
      if (status) status.textContent = "Opening your email app… if nothing happens, email teeoff@agatebeachgolf.net directly.";
    }

    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var data = new FormData(form);
      if (status) { status.className = "form-status"; status.textContent = "Sending…"; }
      if (sendButton) sendButton.disabled = true;

      fetch("contact.php", { method: "POST", body: data })
        .then(function (response) {
          return response.json().then(
            function (result) { return { status: response.status, result: result }; },
            function () { return { status: response.status, result: null }; }
          );
        })
        .then(function (reply) {
          // No JSON back means contact.php is not on this host, so use the email app instead.
          if (!reply.result) { openEmailApp(data); return; }
          if (status) { status.className = "form-status " + (reply.result.ok ? "is-ok" : "is-error"); status.textContent = reply.result.message; }
          if (reply.result.ok) {
            form.reset();
            if (started) started.value = String(Date.now());
          }
        })
        .catch(function () { openEmailApp(data); })
        .then(function () { if (sendButton) sendButton.disabled = false; });
    });
  }
  var year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());
})();
