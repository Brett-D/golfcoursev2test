/* Pro shop storefront: catalogue, filtering and cart rendering. */
(function () {
  "use strict";

  var CATALOG = [
    {
      id: "polo-performance",
      name: "Agate Beach Performance Polo",
      price: 54,
      category: "apparel",
      image: "assets/img/product-polo.svg",
      description: "Moisture-wicking stretch knit with the course crest on the chest. Men's and women's cuts, XS–3XL."
    },
    {
      id: "jacket-quarter-zip",
      name: "Coast Wind Quarter-Zip",
      price: 78,
      category: "apparel",
      image: "assets/img/product-jacket.svg",
      description: "Wind-blocking shell built for breezy Oregon mornings on the front nine."
    },
    {
      id: "cap-crest",
      name: "Embroidered Course Cap",
      price: 26,
      category: "apparel",
      image: "assets/img/product-cap.svg",
      description: "Unstructured cotton twill cap with an adjustable strap and raised crest embroidery."
    },
    {
      id: "glove-cabretta",
      name: "Cabretta Leather Glove",
      price: 22,
      category: "equipment",
      image: "assets/img/product-glove.svg",
      description: "Premium tour-grade leather. Available in right- and left-hand fits."
    },
    {
      id: "balls-sleeve",
      name: "Logo Golf Balls (Sleeve of 3)",
      price: 14,
      category: "equipment",
      image: "assets/img/product-balls.svg",
      description: "Soft-feel three-piece balls stamped with the Agate Beach logo."
    },
    {
      id: "bag-carry",
      name: "Lightweight Carry Stand Bag",
      price: 189,
      category: "equipment",
      image: "assets/img/product-bag.svg",
      description: "Four-way top, dual straps and a rain hood — ideal for walking our nine."
    },
    {
      id: "headcover-knit",
      name: "Knit Driver Headcover",
      price: 32,
      category: "equipment",
      image: "assets/img/product-headcover.svg",
      description: "Retro knit cover in course green with a contrast pom."
    },
    {
      id: "towel-trifold",
      name: "Tri-Fold Caddie Towel",
      price: 19,
      category: "accessories",
      image: "assets/img/product-towel.svg",
      description: "Plush cotton towel with a carabiner clip for the bag."
    },
    {
      id: "umbrella-62",
      name: "62\" Windproof Umbrella",
      price: 45,
      category: "accessories",
      image: "assets/img/product-umbrella.svg",
      description: "Double-canopy vented frame that stands up to coastal gusts."
    },
    {
      id: "divot-tool",
      name: "Brass Divot Tool & Ball Marker",
      price: 16,
      category: "accessories",
      image: "assets/img/product-divot.svg",
      description: "Solid brass switchblade tool with a magnetic enamel marker."
    },
    {
      id: "tees-bamboo",
      name: "Bamboo Tee Pack (50)",
      price: 9,
      category: "accessories",
      image: "assets/img/product-tees.svg",
      description: "Durable 2¾\" bamboo tees in a resealable course-branded pouch."
    },
    {
      id: "gift-card-50",
      name: "$50 Gift Card",
      price: 50,
      category: "gifts",
      image: "assets/img/product-giftcard.svg",
      description: "Good for green fees, range balls, café orders and pro shop merchandise."
    }
  ];

  var byId = {};
  CATALOG.forEach(function (item) {
    byId[item.id] = item;
  });

  var grid = document.getElementById("product-grid");
  var cartLines = document.getElementById("cart-lines");
  var cartTotal = document.getElementById("cart-total");
  var checkoutBtn = document.getElementById("checkout-btn");
  var clearBtn = document.getElementById("clear-cart");
  var cartStatus = document.getElementById("cart-status");

  if (!grid) return;

  function money(value) {
    return "$" + value.toFixed(2);
  }

  function productCard(item) {
    var card = document.createElement("article");
    card.className = "card product";
    card.dataset.category = item.category;

    var media = document.createElement("div");
    media.className = "product-media";
    var img = document.createElement("img");
    img.src = item.image;
    img.alt = item.name;
    img.loading = "lazy";
    img.width = 480;
    img.height = 360;
    media.appendChild(img);

    var body = document.createElement("div");
    body.className = "card-body";

    var cat = document.createElement("span");
    cat.className = "product-cat";
    cat.textContent = item.category;

    var title = document.createElement("h3");
    title.textContent = item.name;

    var desc = document.createElement("p");
    desc.className = "product-desc";
    desc.textContent = item.description;

    var foot = document.createElement("div");
    foot.className = "product-foot";

    var price = document.createElement("span");
    price.className = "price";
    price.textContent = money(item.price);

    var add = document.createElement("button");
    add.type = "button";
    add.className = "btn btn-primary";
    add.textContent = "Add to bag";
    add.addEventListener("click", function () {
      window.AgateCart.add(item.id, 1);
      announce(item.name + " added to your bag.");
    });

    foot.appendChild(price);
    foot.appendChild(add);
    body.appendChild(cat);
    body.appendChild(title);
    body.appendChild(desc);
    body.appendChild(foot);
    card.appendChild(media);
    card.appendChild(body);
    return card;
  }

  function announce(message) {
    if (cartStatus) cartStatus.textContent = message;
  }

  function renderProducts(filter) {
    grid.textContent = "";
    CATALOG.filter(function (item) {
      return filter === "all" || item.category === filter;
    }).forEach(function (item) {
      grid.appendChild(productCard(item));
    });
  }

  function renderCart() {
    if (!cartLines) return;
    var lines = window.AgateCart.lines().filter(function (line) {
      return byId[line.id];
    });

    cartLines.textContent = "";
    var sum = 0;

    if (!lines.length) {
      var empty = document.createElement("li");
      empty.className = "cart-empty";
      empty.textContent = "Your bag is empty. Add a few essentials before your round.";
      cartLines.appendChild(empty);
    }

    lines.forEach(function (line) {
      var item = byId[line.id];
      sum += item.price * line.qty;

      var li = document.createElement("li");
      li.className = "cart-line";

      var name = document.createElement("span");
      name.className = "cart-line-name";
      name.textContent = item.name;

      var lineTotal = document.createElement("span");
      lineTotal.textContent = money(item.price * line.qty);

      var controls = document.createElement("div");
      controls.className = "cart-line-controls";

      controls.appendChild(qtyButton("−", "Decrease quantity of " + item.name, function () {
        window.AgateCart.setQty(item.id, line.qty - 1);
      }));

      var qty = document.createElement("span");
      qty.textContent = String(line.qty);
      controls.appendChild(qty);

      controls.appendChild(qtyButton("+", "Increase quantity of " + item.name, function () {
        window.AgateCart.setQty(item.id, line.qty + 1);
      }));

      var remove = document.createElement("button");
      remove.type = "button";
      remove.className = "qty-btn";
      remove.textContent = "×";
      remove.setAttribute("aria-label", "Remove " + item.name);
      remove.addEventListener("click", function () {
        window.AgateCart.remove(item.id);
      });
      controls.appendChild(remove);

      li.appendChild(name);
      li.appendChild(lineTotal);
      li.appendChild(controls);
      cartLines.appendChild(li);
    });

    if (cartTotal) cartTotal.textContent = money(sum);
    if (checkoutBtn) checkoutBtn.disabled = lines.length === 0;
  }

  function qtyButton(label, ariaLabel, handler) {
    var btn = document.createElement("button");
    btn.type = "button";
    btn.className = "qty-btn";
    btn.textContent = label;
    btn.setAttribute("aria-label", ariaLabel);
    btn.addEventListener("click", handler);
    return btn;
  }

  Array.prototype.forEach.call(document.querySelectorAll(".filter-btn"), function (btn) {
    btn.addEventListener("click", function () {
      Array.prototype.forEach.call(document.querySelectorAll(".filter-btn"), function (other) {
        other.setAttribute("aria-pressed", String(other === btn));
      });
      renderProducts(btn.dataset.filter);
    });
  });

  if (clearBtn) {
    clearBtn.addEventListener("click", function () {
      window.AgateCart.clear();
      announce("Your bag has been emptied.");
    });
  }

  if (checkoutBtn) {
    checkoutBtn.addEventListener("click", function () {
      announce(
        "Thanks! Online payment is coming soon — call the pro shop at (541) 265-7331 to complete your order and we'll have it ready at the counter."
      );
    });
  }

  window.addEventListener("cart:updated", renderCart);

  renderProducts("all");
  renderCart();
})();
