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
    fetch("data/rates.json?v=" + Date.now(), { cache: "no-store" })
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
        var announce = noticeBox.closest("[data-announce]");
        if (announce) announce.hidden = messages.length === 0;
      })
      .catch(function () { /* no notices to show */ });
  }

  // ---------- Weather at the course (home page) ----------
  // Current conditions from the Newport airport station, the forecast for the course's grid square, both from the
  // National Weather Service (free, no key). Sunrise and sunset are worked out here, so they show even if NWS is down.
  var weather = document.querySelector("[data-weather]");
  if (weather) {
    var LAT = 44.6668, LON = -124.053, TZ = "America/Los_Angeles";
    var NWS = "https://api.weather.gov/";
    var FORECAST = NWS + "gridpoints/PQR/60,74/forecast";
    var HOURLY = FORECAST + "/hourly";
    var OBSERVED = NWS + "stations/KONP/observations/latest";

    var getWeather = function (url, retried) {
      return fetch(url, { headers: { Accept: "application/geo+json" } }).then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      }).catch(function (error) {
        // api.weather.gov sometimes fails once and works on the next try.
        if (!retried) return getWeather(url, true);
        throw error;
      });
    };
    var clock = function (date) {
      return date.toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", timeZone: TZ }).toLowerCase();
    };
    var compass = function (deg) {
      if (deg === null || deg === undefined) return "";
      return ["N", "NNE", "NE", "ENE", "E", "ESE", "SE", "SSE", "S", "SSW", "SW", "WSW", "W", "WNW", "NW", "NNW"][Math.round(deg / 22.5) % 16];
    };
    var sky = function (text) {
      text = (text || "").toLowerCase();
      if (/thunder/.test(text)) return "storm";
      if (/snow|sleet|ice|flurr/.test(text)) return "snow";
      if (/rain|shower|drizzle/.test(text)) return "rain";
      if (/fog|mist|haze|smoke/.test(text)) return "fog";
      if (/partly|mostly sunny|mostly clear|few|scattered/.test(text)) return "partly";
      if (/cloud|overcast/.test(text)) return "cloud";
      if (/sunny|clear|fair/.test(text)) return "clear";
      return "cloud";
    };
    var weatherIcon = function (type, night) {
      var sun = night
        ? '<path class="moon" d="M20 14.5A8 8 0 1 1 11.5 6a6.3 6.3 0 0 0 8.5 8.5z"/>'
        : '<circle class="sun" cx="12" cy="12" r="4.2"/><g class="sun"><path d="M12 3v2M12 19v2M3 12h2M19 12h2M5.6 5.6l1.4 1.4M17 17l1.4 1.4M5.6 18.4 7 17M17 7l1.4-1.4"/></g>';
      var smallSun = night
        ? '<path class="moon" d="M14 9.5A5 5 0 1 1 8.6 4a4 4 0 0 0 5.4 5.5z"/>'
        : '<circle class="sun" cx="9" cy="8" r="3.4"/><g class="sun"><path d="M9 2.5v1.2M3.5 8h1.2M5 4l.9.9M13 4l-.9.9"/></g>';
      var cloud = '<path class="cloud" d="M7 19h10.5a4 4 0 0 0 .4-8 5.5 5.5 0 0 0-10.6 1.2A3.4 3.4 0 0 0 7 19z"/>';
      var highCloud = '<path class="cloud" d="M7 15h10.5a3.6 3.6 0 0 0 .4-7.2 5.2 5.2 0 0 0-10.1 1.1A3 3 0 0 0 7 15z"/>';
      var shapes = {
        clear: sun,
        partly: smallSun + cloud,
        cloud: cloud,
        rain: highCloud + '<path class="drop" d="M8.5 17.5l-1 2.5M12.5 17.5l-1 2.5M16.5 17.5l-1 2.5"/>',
        snow: highCloud + '<path class="drop" d="M8 18.5h.01M12 20h.01M16 18.5h.01"/>',
        storm: highCloud + '<path class="bolt" d="M12.5 14 10 18.5h3L11.5 22"/>',
        fog: '<path class="fogline" d="M4 9h16M3 13h18M5 17h14"/>'
      };
      return '<svg class="wx-icon" viewBox="0 0 24 24" aria-hidden="true">' + shapes[type] + "</svg>";
    };
    // Standard solar position formulas; accurate to about a minute.
    var sunTimes = function (date) {
      var rad = Math.PI / 180, day = 86400000, J1970 = 2440588, J2000 = 2451545, J0 = 0.0009, tilt = rad * 23.4397;
      var lw = rad * -LON, phi = rad * LAT;
      var d = date.valueOf() / day - 0.5 + J1970 - J2000;
      var n = Math.round(d - J0 - lw / (2 * Math.PI));
      var ds = J0 + lw / (2 * Math.PI) + n;
      var M = rad * (357.5291 + 0.98560028 * ds);
      var L = M + rad * (1.9148 * Math.sin(M) + 0.02 * Math.sin(2 * M) + 0.0003 * Math.sin(3 * M)) + rad * 102.9372 + Math.PI;
      var dec = Math.asin(Math.sin(tilt) * Math.sin(L));
      var transit = function (x) { return J2000 + x + 0.0053 * Math.sin(M) - 0.0069 * Math.sin(2 * L); };
      var noon = transit(ds);
      var w = Math.acos((Math.sin(rad * -0.833) - Math.sin(phi) * Math.sin(dec)) / (Math.cos(phi) * Math.cos(dec)));
      var set = transit(J0 + (w + lw) / (2 * Math.PI) + n);
      var toDate = function (j) { return new Date((j + 0.5 - J1970) * day); };
      return { rise: toDate(noon - (set - noon)), set: toDate(set) };
    };
    var courseNoon = function (offsetDays) {
      var parts = new Intl.DateTimeFormat("en-US", { timeZone: TZ, year: "numeric", month: "numeric", day: "numeric" }).formatToParts(new Date());
      var get = function (type) { return Number(parts.filter(function (p) { return p.type === type; })[0].value); };
      return new Date(Date.UTC(get("year"), get("month") - 1, get("day") + (offsetDays || 0), 20));
    };
    var span = function (ms) {
      var mins = Math.max(0, Math.round(ms / 60000));
      var h = Math.floor(mins / 60);
      return (h ? h + " hr " : "") + (mins % 60) + " min";
    };
    var showSun = function () {
      var now = new Date();
      var today = sunTimes(courseNoon(0));
      var tomorrow = sunTimes(courseNoon(1));
      var part = (now - today.rise) / (today.set - today.rise);
      var up = part >= 0 && part <= 1;
      var clamped = Math.min(1, Math.max(0, part));
      var status;
      if (now < today.rise) status = "Sunrise in " + span(today.rise - now);
      else if (up) status = span(today.set - now) + " of daylight left";
      else status = "Sun has set &middot; rises " + clock(tomorrow.rise) + " tomorrow";
      weather.querySelector("[data-wx-sun]").innerHTML =
        '<p class="wx-label">Sunrise &amp; sunset</p>' +
        '<svg class="arc" viewBox="0 0 200 66" aria-hidden="true">' +
          '<line x1="0" y1="58" x2="200" y2="58" stroke="#c9d1db" stroke-width="1.5"/>' +
          '<path d="M10 58 A90 48 0 0 1 190 58" fill="none" stroke="#e0a10b" stroke-width="2" stroke-dasharray="4 4"/>' +
          (up ? '<circle cx="' + (10 + 180 * clamped).toFixed(1) + '" cy="' + (58 - 48 * Math.sin(Math.PI * clamped)).toFixed(1) + '" r="7" fill="#fbd46a" stroke="#e0a10b" stroke-width="2"/>' : "") +
        "</svg>" +
        '<span class="k">Sunrise</span><span class="v">' + clock(today.rise) + "</span>" +
        '<span class="k">Sunset</span><span class="v">' + clock(today.set) + "</span>" +
        '<span class="k">Daylight</span><span class="v">' + span(today.set - today.rise) + "</span>" +
        '<span class="left">' + status + "</span>";
      return today;
    };
    var escapeText = function (text) {
      return String(text).replace(/[&<>"]/g, function (c) { return { "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;" }[c]; });
    };
    var windText = function (mph, dir, gust) {
      if (!mph) return "Calm";
      return "Wind " + (dir ? dir + " " : "") + mph + " mph" + (gust ? ", gusts " + gust : "");
    };
    var showNow = function (temp, text, wind, night) {
      weather.querySelector("[data-wx-now]").innerHTML = weatherIcon(sky(text), night) +
        '<div><div class="wx-temp">' + temp + "&deg;</div>" +
        '<div class="wx-desc">' + escapeText(text) + '</div><div class="wx-wind">' + escapeText(wind) + "</div></div>";
    };
    var loadNow = function (sun) {
      var now = new Date();
      var night = now < sun.rise || now > sun.set;
      return getWeather(OBSERVED).then(function (data) {
        var p = data.properties;
        // Skip a missing or more than two hours old reading and use the forecast for this hour instead.
        if (p.temperature.value === null || now - new Date(p.timestamp) > 2 * 3600000) throw new Error("stale");
        var mph = function (kmh) { return Math.round((kmh || 0) * 0.621371); };
        showNow(Math.round(p.temperature.value * 9 / 5 + 32), p.textDescription || "",
          windText(mph(p.windSpeed.value), compass(p.windDirection.value), p.windGust && p.windGust.value ? mph(p.windGust.value) : 0), night);
      }).catch(function () {
        return getWeather(HOURLY).then(function (data) {
          var hour = data.properties.periods[0];
          showNow(hour.temperature, hour.shortForecast, windText(parseInt(hour.windSpeed, 10) || 0, hour.windDirection), !hour.isDaytime);
        });
      });
    };
    var loadForecast = function () {
      return getWeather(FORECAST).then(function (data) {
        weather.querySelector("[data-wx-days]").innerHTML = data.properties.periods.slice(0, 4).map(function (p) {
          var rain = p.probabilityOfPrecipitation && p.probabilityOfPrecipitation.value;
          var name = p.name.replace(/\b(Mon|Tues|Wednes|Thurs|Fri|Satur|Sun)day\b/, function (m) { return m.slice(0, 3); }).replace(" Night", " night");
          return '<div class="wx-day" title="' + escapeText(p.detailedForecast) + '"><b>' + escapeText(name) + "</b>" +
            weatherIcon(sky(p.shortForecast), !p.isDaytime) +
            '<span class="t' + (p.isDaytime ? "" : " low") + '">' + p.temperature + "&deg;</span>" +
            '<span class="pop">' + (rain ? rain + "% rain" : "") + "</span></div>";
        }).join("");
      });
    };
    var loadWeather = function () {
      var sun = showSun();
      Promise.all([
        loadNow(sun).catch(function () {
          weather.querySelector("[data-wx-now]").innerHTML = '<span class="wx-desc">Current conditions are unavailable right now.</span>';
        }),
        loadForecast().catch(function () {
          weather.querySelector("[data-wx-days]").innerHTML = '<span class="wx-desc" style="grid-column:1/-1">The forecast is unavailable right now.</span>';
        })
      ]).then(function () {
        weather.querySelector("[data-wx-updated]").textContent = "Updated " + clock(new Date());
      });
    };
    loadWeather();
    setInterval(showSun, 60000);
    setInterval(loadWeather, 20 * 60000);
  }

  // ---------- Cafe menu and specials (data/cafe.json, edited on the owner page) ----------
  var cafeTargets = document.querySelectorAll("[data-cafe-menu], [data-cafe-dogs], [data-specials], [data-specials-home]");
  if (cafeTargets.length) {
    // Specials dates are calendar days at the course, whatever time zone the visitor is in.
    var courseToday = function () {
      var parts = new Intl.DateTimeFormat("en-US", { timeZone: "America/Los_Angeles", year: "numeric", month: "2-digit", day: "2-digit" }).formatToParts(new Date());
      var get = function (type) { return parts.filter(function (p) { return p.type === type; })[0].value; };
      return get("year") + "-" + get("month") + "-" + get("day");
    };
    var dayOf = function (iso) { var p = iso.split("-"); return new Date(Date.UTC(+p[0], p[1] - 1, +p[2])); };
    var plusDays = function (iso, n) { var d = dayOf(iso); d.setUTCDate(d.getUTCDate() + n); return d.toISOString().slice(0, 10); };
    var dayText = function (iso, options) { return dayOf(iso).toLocaleDateString("en-US", Object.assign({ timeZone: "UTC" }, options)); };
    var validDay = function (s) { return /^\d{4}-\d{2}-\d{2}$/.test(s || ""); };

    // { start, end } as YYYY-MM-DD (empty for "until turned off"), or null if the dates are unusable.
    var specialsDates = function (s) {
      if (s.when === "always") return { start: "", end: "" };
      if (s.when === "week") return validDay(s.week) ? { start: s.week, end: plusDays(s.week, 6) } : null;
      if (validDay(s.from) && validDay(s.to) && s.to >= s.from) return { start: s.from, end: s.to };
      return null;
    };
    var specialsLabel = function (s, dates) {
      if (!dates.start) return "";
      if (s.when === "week") return "Week of " + dayText(dates.start, { month: "short", day: "numeric" });
      if (dates.start === dates.end) return dayText(dates.start, { weekday: "long", month: "short", day: "numeric" });
      var a = dayOf(dates.start), b = dayOf(dates.end);
      return dayText(dates.start, { month: "short", day: "numeric" }) + " \u2013 " +
        (a.getUTCMonth() === b.getUTCMonth() ? String(b.getUTCDate()) : dayText(dates.end, { month: "short", day: "numeric" }));
    };
    var liveSpecials = function (s) {
      if (!s || !s.show) return null;
      var items = (s.items || []).filter(function (i) { return i.name && i.name.trim(); });
      var dates = specialsDates(s);
      if (!items.length || !dates) return null;
      var today = courseToday();
      if (dates.start && (today < dates.start || today > dates.end)) return null;
      return { heading: s.heading || "Specials", label: specialsLabel(s, dates), note: s.note || "", items: items, home: !!s.home };
    };

    var renderSpecials = function (s) {
      var live = liveSpecials(s);
      var section = document.querySelector("[data-specials]");
      if (section) {
        section.hidden = !live;
        if (live) {
          section.querySelector("[data-specials-heading]").textContent = live.heading;
          section.querySelector("[data-specials-dates]").textContent = live.label;
          section.querySelector("[data-specials-note]").textContent = live.note;
          var list = section.querySelector("[data-specials-list]");
          list.textContent = "";
          live.items.forEach(function (item) {
            var row = el("div", "special");
            var text = el("div");
            text.appendChild(el("h3", "", item.name));
            if (item.desc) text.appendChild(el("p", "", item.desc));
            row.appendChild(text);
            if (item.price) row.appendChild(el("span", "price", item.price));
            list.appendChild(row);
          });
        }
      }
      var mini = document.querySelector("[data-specials-home]");
      if (mini) {
        mini.hidden = !(live && live.home);
        if (live && live.home) {
          mini.querySelector("[data-specials-heading]").textContent = live.heading;
          mini.querySelector("[data-specials-dates]").textContent = live.label;
          var ul = mini.querySelector("ul");
          ul.textContent = "";
          live.items.forEach(function (item) {
            var li = el("li");
            li.appendChild(el("span", "", item.name));
            if (item.price) li.appendChild(el("span", "", item.price));
            ul.appendChild(li);
          });
        }
      }
    };

    var renderMenu = function (menuData) {
      var grid = document.querySelector("[data-cafe-menu]");
      if (!grid) return;
      var lead = document.querySelector("[data-cafe-menu-lead]");
      if (lead) lead.textContent = menuData.lead || "";
      var foot = document.querySelector("[data-cafe-menu-foot]");
      if (foot) foot.textContent = menuData.foot || "";
      grid.textContent = "";
      [0, 1].forEach(function (column) {
        var group = el("div", "menu-group");
        (menuData.sections || []).filter(function (s) { return (s.column || 0) === column; }).forEach(function (s, i) {
          var title = el("h3", "", s.title);
          if (i > 0) title.style.marginTop = "2.2rem";
          group.appendChild(title);
          if (s.note) group.appendChild(el("p", "menu-note", s.note));
          (s.items || []).forEach(function (item) {
            var box = el("div", "menu-item");
            var name = el("h4", "", item.name);
            var extra = [item.option, item.price].filter(Boolean).join(" \u00b7 ");
            if (extra) name.appendChild(el("span", "", extra));
            box.appendChild(name);
            box.appendChild(el("p", "", item.desc || ""));
            group.appendChild(box);
          });
        });
        grid.appendChild(group);
      });
    };

    var renderDogs = function (dogData) {
      var grid = document.querySelector("[data-cafe-dogs]");
      if (!grid) return;
      var items = dogData.items || [];
      grid.textContent = "";
      grid.style.setProperty("--dog-rows", String(Math.max(1, Math.ceil(items.length / 2))));
      items.forEach(function (dog) {
        var box = el("div", "dog");
        var name = el("h4", "", dog.name);
        if (dog.price) name.appendChild(el("span", "", dog.price));
        box.appendChild(name);
        box.appendChild(el("p", "", dog.desc || ""));
        grid.appendChild(box);
      });
      var byo = document.querySelector("[data-cafe-byo]");
      if (byo) {
        byo.querySelector("[data-cafe-byo-text]").textContent = dogData.byo || "";
        byo.hidden = !dogData.byo;
      }
    };

    fetch("data/cafe.json?v=" + Date.now(), { cache: "no-store" })
      .then(function (response) {
        if (!response.ok) throw new Error("HTTP " + response.status);
        return response.json();
      })
      .then(function (data) {
        renderSpecials(data.specials);
        renderMenu(data.menu || {});
        renderDogs(data.dogs || {});
      })
      .catch(function () {
        Array.prototype.forEach.call(document.querySelectorAll("[data-cafe-menu], [data-cafe-dogs]"), function (grid) {
          grid.textContent = "";
          grid.appendChild(el("p", "menu-status", "The menu is unavailable right now. Please call the cafe at (541) 265-7331."));
        });
      });
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

    // After a few messages from the same visitor, contact.php asks a simple question first.
    function showChallenge(question, token) {
      var box = document.getElementById("human-check");
      if (!box) {
        box = document.createElement("div");
        box.id = "human-check";
        box.className = "span-2";
        box.innerHTML = '<label for="c-answer"></label><input id="c-answer" name="answer" type="text" inputmode="numeric" autocomplete="off" required><input type="hidden" name="token" id="c-token">';
        var last = form.lastElementChild;
        form.insertBefore(box, last);
      }
      box.querySelector("label").textContent = question;
      document.getElementById("c-token").value = token;
      var answerField = document.getElementById("c-answer");
      answerField.value = "";
      answerField.focus();
    }

    function removeChallenge() {
      var box = document.getElementById("human-check");
      if (box) box.remove();
    }

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

      // Some hosts (Bluehost) first want a small "humans" cookie, which they normally set by running a script in the page they
      // return. A fetch does not run that script, so read the cookie from it, set it here and try once more.
      function post(retried) {
        return fetch("contact.php", { method: "POST", body: data, credentials: "same-origin" }).then(function (response) {
          if (response.status !== 409 || retried) return response;
          return response.text().then(function (text) {
            var match = /document\.cookie\s*=\s*"([A-Za-z0-9_]+=[A-Za-z0-9_]+)/.exec(text);
            if (!match) return response;
            document.cookie = match[1] + "; path=/; max-age=86400; SameSite=Lax";
            return post(true);
          });
        });
      }

      post(false)
        .then(function (response) {
          return response.json().then(
            function (result) { return { status: response.status, result: result }; },
            function () { return { status: response.status, result: null }; }
          );
        })
        .then(function (reply) {
          // No JSON back means contact.php is not on this host, so use the email app instead.
          if (!reply.result) { openEmailApp(data); return; }
          if (reply.result.challenge) showChallenge(reply.result.question, reply.result.token);
          if (status) { status.className = "form-status " + (reply.result.ok ? "is-ok" : "is-error"); status.textContent = reply.result.message; }
          if (reply.result.ok) {
            removeChallenge();
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
