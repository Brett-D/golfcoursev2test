/* Minimal cart store for the pro shop.
   Only product ids and quantities are persisted; names and prices are always
   resolved from the catalogue at render time. */
(function (window) {
  "use strict";

  var KEY = "abgc-cart-v1";

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      var parsed = raw ? JSON.parse(raw) : [];
      if (!Array.isArray(parsed)) return [];
      return parsed
        .filter(function (line) {
          return line && typeof line.id === "string" && isFinite(line.qty);
        })
        .map(function (line) {
          return { id: line.id, qty: Math.min(99, Math.max(1, Math.floor(line.qty))) };
        });
    } catch (err) {
      return [];
    }
  }

  function write(lines) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify(lines));
    } catch (err) {
      /* storage unavailable (private mode) — cart stays in memory for this page */
    }
    window.dispatchEvent(
      new CustomEvent("cart:updated", { detail: { lines: lines, count: total(lines) } })
    );
  }

  function total(lines) {
    return lines.reduce(function (sum, line) {
      return sum + line.qty;
    }, 0);
  }

  var Cart = {
    lines: function () {
      return read();
    },
    count: function () {
      return total(read());
    },
    add: function (id, qty) {
      var lines = read();
      var amount = Math.max(1, Math.floor(qty || 1));
      var existing = lines.filter(function (line) {
        return line.id === id;
      })[0];
      if (existing) {
        existing.qty = Math.min(99, existing.qty + amount);
      } else {
        lines.push({ id: id, qty: Math.min(99, amount) });
      }
      write(lines);
    },
    setQty: function (id, qty) {
      var next = Math.floor(qty);
      var lines = read().filter(function (line) {
        return line.id !== id || next > 0;
      });
      lines.forEach(function (line) {
        if (line.id === id) line.qty = Math.min(99, next);
      });
      write(lines);
    },
    remove: function (id) {
      write(
        read().filter(function (line) {
          return line.id !== id;
        })
      );
    },
    clear: function () {
      write([]);
    }
  };

  window.AgateCart = Cart;
})(window);
