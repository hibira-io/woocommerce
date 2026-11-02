/*! Hibira Live Visitors for WooCommerce · https://hibira.io · GPL-2.0-or-later */
(function () {
  "use strict";

  var VERSION = "0.1.0";
  var HIDE_AFTER_MS = 30000; // close the socket when the tab stays hidden
  var TOAST_MS = 6500;
  var LIVE_TOAST_GAP_MS = 15000;
  var SEEN_KEY = "hibira-lv-seen";

  var el = document.getElementById("hibira-lv-config");
  if (!el || window.HibiraLiveVisitors) return;
  var cfg;
  try {
    cfg = JSON.parse(el.textContent);
  } catch (e) {
    return;
  }
  if (!cfg || !cfg.key || !cfg.store) return;

  var pro = !!(cfg.storeCount || cfg.popups);
  var storeCh = cfg.store;
  var productCh = cfg.product ? storeCh + "-p-" + cfg.product : null;
  var lang = document.documentElement.lang || "en";
  var counts = {};
  var api = { counts: counts, version: VERSION };
  window.HibiraLiveVisitors = api;

  /* ---------- counters ---------- */

  function fill(text, n) {
    return String(text).replace(/\{count\}/g, String(n));
  }

  function badgeText(scope, n) {
    var t = cfg.texts || {};
    if (scope === "store") return fill(n === 1 ? t.shopping_one : t.shopping_other, n);
    return fill(n === 1 ? t.viewing_one : t.viewing_other, n);
  }

  function badges() {
    return document.querySelectorAll("[data-hibira-viewers]");
  }

  function render() {
    var all = badges();
    for (var i = 0; i < all.length; i++) {
      var b = all[i];
      var scope = b.getAttribute("data-scope") || "product";
      var n = scope === "store" ? counts.store : counts.product;
      var min = Number(b.getAttribute("data-min") || cfg.min || 2);
      if (scope === "store" && !cfg.storeCount) n = undefined;
      if (typeof n !== "number" || n < min) {
        b.hidden = true;
        continue;
      }
      var label = b.querySelector("[data-hibira-label]") || b;
      var text = badgeText(scope, n);
      if (label.textContent !== text) label.textContent = text;
      b.hidden = false;
    }
  }

  /* ---------- "just bought" popups ---------- */

  var toastBox = null;
  var lastLiveToast = 0;
  var showing = false;

  function seen() {
    try {
      return JSON.parse(sessionStorage.getItem(SEEN_KEY) || "[]");
    } catch (e) {
      return [];
    }
  }

  function markSeen(p) {
    try {
      var s = seen();
      s.push(p.id + "-" + p.at);
      sessionStorage.setItem(SEEN_KEY, JSON.stringify(s.slice(-50)));
    } catch (e) {
      /* storage blocked: popups may repeat, which is fine */
    }
  }

  function ago(at) {
    var s = Math.max(0, Math.round((Date.now() - at) / 1000));
    var rtf;
    try {
      rtf = new Intl.RelativeTimeFormat(lang, { numeric: "auto" });
    } catch (e) {
      rtf = new Intl.RelativeTimeFormat("en", { numeric: "auto" });
    }
    if (s < 60) return rtf.format(0, "second");
    if (s < 3600) return rtf.format(-Math.round(s / 60), "minute");
    return rtf.format(-Math.round(s / 3600), "hour");
  }

  function toast(p) {
    if (showing || !cfg.popups) return false;
    if (!toastBox) {
      toastBox = document.createElement("div");
      toastBox.className = "hibira-lv-toasts hibira-lv-" + (cfg.position || "bottom-left");
      toastBox.setAttribute("role", "status");
      toastBox.setAttribute("aria-live", "polite");
      document.body.appendChild(toastBox);
    }
    showing = true;
    markSeen(p);
    var a = document.createElement("a");
    a.className = "hibira-lv-toast";
    a.href = p.url;
    if (p.image) {
      var img = document.createElement("img");
      img.src = p.image;
      img.alt = "";
      img.width = 56;
      img.height = 56;
      img.loading = "lazy";
      a.appendChild(img);
    }
    var body = document.createElement("span");
    body.className = "hibira-lv-toast-body";
    var line = document.createElement("span");
    line.className = "hibira-lv-toast-line";
    line.textContent = (cfg.texts && cfg.texts.bought) || "Someone just bought";
    var title = document.createElement("strong");
    title.textContent = p.title;
    var time = document.createElement("span");
    time.className = "hibira-lv-toast-time";
    time.textContent = ago(p.at);
    body.appendChild(line);
    body.appendChild(title);
    body.appendChild(time);
    a.appendChild(body);
    var close = document.createElement("button");
    close.type = "button";
    close.className = "hibira-lv-toast-close";
    close.setAttribute("aria-label", (cfg.texts && cfg.texts.close) || "Close");
    close.innerHTML = '<svg viewBox="0 0 16 16" width="12" height="12" aria-hidden="true"><path d="M3 3l10 10M13 3L3 13" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    var wrap = document.createElement("div");
    wrap.className = "hibira-lv-toast-wrap";
    wrap.appendChild(a);
    wrap.appendChild(close);
    toastBox.appendChild(wrap);
    requestAnimationFrame(function () {
      wrap.classList.add("hibira-lv-in");
    });
    var gone = false;
    function hide() {
      if (gone) return;
      gone = true;
      wrap.classList.remove("hibira-lv-in");
      setTimeout(function () {
        if (wrap.parentNode) wrap.parentNode.removeChild(wrap);
        showing = false;
      }, 300);
    }
    close.addEventListener("click", hide);
    var timer = setTimeout(hide, TOAST_MS);
    wrap.addEventListener("mouseenter", function () {
      clearTimeout(timer);
    });
    wrap.addEventListener("mouseleave", function () {
      clearTimeout(timer);
      timer = setTimeout(hide, 2500);
    });
    return true;
  }

  function showRecent() {
    var maxAge = (cfg.maxAgeHours || 24) * 3600 * 1000;
    var s = seen();
    var list = cfg.recent || [];
    for (var i = 0; i < list.length; i++) {
      var p = list[i];
      if (!p || Date.now() - p.at > maxAge) continue;
      if (String(p.id) === String(cfg.product)) continue;
      if (s.indexOf(p.id + "-" + p.at) !== -1) continue;
      toast(p);
      return;
    }
  }

  function onPurchase(p) {
    if (!p || !p.url || !p.title) return;
    var now = Date.now();
    if (now - lastLiveToast < LIVE_TOAST_GAP_MS) return;
    if (toast(p)) lastLiveToast = now;
  }

  /* ---------- live stock ---------- */

  var LISTS = "ul.products, .wc-block-product-template, .wp-block-woocommerce-product-collection, .related, .upsells";

  function mainOnly(selector) {
    var out = [];
    var all = document.querySelectorAll(selector);
    for (var i = 0; i < all.length; i++) if (!all[i].closest(LISTS)) out.push(all[i]);
    return out;
  }

  function onStock(d) {
    if (!d || typeof d.text !== "string") return;
    var nodes = mainOnly("p.stock, .wc-block-components-product-stock-indicator");
    for (var i = 0; i < nodes.length; i++) {
      var n = nodes[i];
      n.textContent = d.text;
      n.classList.remove("in-stock", "out-of-stock", "available-on-backorder");
      if (d.class) n.classList.add(d.class);
    }
    var buttons = mainOnly("form.cart button[type=submit], form.cart .single_add_to_cart_button");
    for (var j = 0; j < buttons.length; j++) buttons[j].disabled = d.status === "outofstock";
    if (api.onStock) api.onStock(d);
  }

  /* ---------- Pusher protocol 7 client (public channels only) ---------- */

  var ws = null;
  var backoff = 1000;
  var retryTimer = null;
  var pingTimer = null;
  var pongTimer = null;
  var activity = 120000;
  var hiddenTimer = null;
  var stopped = false;

  function channels() {
    var list = [];
    if (productCh && (hasProductBadge() || cfg.stock)) list.push(productCh);
    if (pro) list.push(storeCh);
    return list;
  }

  function hasProductBadge() {
    var all = badges();
    for (var i = 0; i < all.length; i++) {
      if ((all[i].getAttribute("data-scope") || "product") === "product") return true;
    }
    return false;
  }

  function send(event, data) {
    if (ws && ws.readyState === 1) ws.send(JSON.stringify({ event: event, data: data }));
  }

  function armPing() {
    clearTimeout(pingTimer);
    clearTimeout(pongTimer);
    pingTimer = setTimeout(function () {
      send("pusher:ping", {});
      pongTimer = setTimeout(function () {
        if (ws) ws.close();
      }, 30000);
    }, activity);
  }

  function connect() {
    if (ws || stopped) return;
    var list = channels();
    if (!list.length) return;
    var url =
      (cfg.tls === false ? "ws://" : "wss://") +
      cfg.host +
      "/app/" +
      encodeURIComponent(cfg.key) +
      "?protocol=7&client=hibira-woocommerce&version=" +
      VERSION +
      "&flash=false";
    var sock;
    try {
      sock = new WebSocket(url);
    } catch (e) {
      return retry();
    }
    ws = sock;
    sock.onmessage = function (m) {
      armPing();
      var msg;
      try {
        msg = JSON.parse(m.data);
      } catch (e) {
        return;
      }
      var data = msg.data;
      if (typeof data === "string") {
        try {
          data = JSON.parse(data);
        } catch (e) {
          /* plain string payload */
        }
      }
      switch (msg.event) {
        case "pusher:connection_established":
          backoff = 1000;
          if (data && data.activity_timeout) activity = Math.min(data.activity_timeout * 1000, 120000);
          for (var i = 0; i < list.length; i++) send("pusher:subscribe", { channel: list[i] });
          break;
        case "pusher:ping":
          send("pusher:pong", {});
          break;
        case "pusher:error":
          if (data && data.code >= 4000 && data.code < 4100) stopped = true;
          break;
        case "pusher_internal:subscription_count":
          if (data && typeof data.subscription_count === "number") {
            if (msg.channel === productCh) counts.product = data.subscription_count;
            if (msg.channel === storeCh) counts.store = data.subscription_count;
            render();
          }
          break;
        case "purchase":
          if (msg.channel === storeCh && cfg.popups) onPurchase(data);
          break;
        case "stock":
          if (msg.channel === productCh && cfg.stock) onStock(data);
          break;
      }
    };
    sock.onclose = function (ev) {
      if (ws !== sock) return;
      ws = null;
      clearTimeout(pingTimer);
      clearTimeout(pongTimer);
      if (ev && ev.code >= 4000 && ev.code < 4100) stopped = true;
      if (document.visibilityState !== "hidden") retry();
    };
  }

  function retry() {
    if (stopped || retryTimer) return;
    var wait = backoff + Math.random() * 1000;
    backoff = Math.min(backoff * 2, 30000);
    retryTimer = setTimeout(function () {
      retryTimer = null;
      connect();
    }, wait);
  }

  function disconnect() {
    clearTimeout(retryTimer);
    retryTimer = null;
    clearTimeout(pingTimer);
    clearTimeout(pongTimer);
    if (ws) {
      var s = ws;
      ws = null;
      s.close();
    }
    counts.product = counts.store = undefined;
    render();
  }

  document.addEventListener("visibilitychange", function () {
    clearTimeout(hiddenTimer);
    if (document.visibilityState === "hidden") {
      hiddenTimer = setTimeout(disconnect, HIDE_AFTER_MS);
    } else {
      connect();
    }
  });

  /* ---------- start ---------- */

  function start() {
    connect();
    if (cfg.popups) setTimeout(showRecent, (cfg.delay || 8) * 1000);
  }

  api.render = render;
  if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", start);
  else start();
})();
