/* Shared site behaviour: mobile navigation + cart badge. */
(function () {
  "use strict";

  var toggle = document.querySelector(".nav-toggle");
  var menu = document.getElementById("nav-menu");

  if (toggle && menu) {
    toggle.addEventListener("click", function () {
      var open = menu.classList.toggle("is-open");
      toggle.setAttribute("aria-expanded", String(open));
    });

    menu.addEventListener("click", function (event) {
      if (event.target.tagName === "A") {
        menu.classList.remove("is-open");
        toggle.setAttribute("aria-expanded", "false");
      }
    });
  }

  // Mark the current page in the navigation.
  var here = window.location.pathname.split("/").pop() || "index.html";
  Array.prototype.forEach.call(document.querySelectorAll(".nav-menu a"), function (link) {
    if (link.getAttribute("href") === here) {
      link.setAttribute("aria-current", "page");
    }
  });

  // Keep the header cart badge in sync (cart.js dispatches this event).
  function paintBadge(count) {
    Array.prototype.forEach.call(document.querySelectorAll(".cart-count"), function (el) {
      el.textContent = String(count);
    });
  }

  window.addEventListener("cart:updated", function (event) {
    paintBadge(event.detail.count);
  });

  if (window.AgateCart) {
    paintBadge(window.AgateCart.count());
  }

  var year = document.getElementById("year");
  if (year) {
    year.textContent = String(new Date().getFullYear());
  }
})();
