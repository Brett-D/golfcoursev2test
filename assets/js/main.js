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

  // Static site: the contact form opens the visitor's email app with the message filled in.
  var form = document.getElementById("contact-form");
  if (form) {
    form.addEventListener("submit", function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var body = data.get("message") + "\n\n— " + data.get("name") + " (" + data.get("email") + ")";
      window.location.href = "mailto:teeoff@agatebeachgolf.net?subject=" +
        encodeURIComponent(data.get("subject")) + "&body=" + encodeURIComponent(body);
      var status = document.getElementById("form-status");
      if (status) status.textContent = "Opening your email app… if nothing happens, email teeoff@agatebeachgolf.net directly.";
    });
  }

  var year = document.getElementById("year");
  if (year) year.textContent = String(new Date().getFullYear());
})();
