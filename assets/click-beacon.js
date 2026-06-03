/**
 * Affiliate click beacon (MeinTechBlog Affiliate Cards).
 *
 * Fires a fire-and-forget navigator.sendBeacon() when a reader clicks an outbound
 * Amazon /dp/<ASIN> link, then lets the navigation proceed unchanged. The link in the
 * DOM stays a direct amazon.de URL — no redirect, no cloaking, no single point of failure.
 * Counts middle-click / cmd-click (auxclick) too. No PII is sent, only {asin, postId}.
 */
(function () {
  "use strict";

  var cfg = window.mtbAffiliateClick;
  if (!cfg || !cfg.endpoint || !cfg.postId || !navigator.sendBeacon) {
    return;
  }

  // Matches .../dp/ASIN and .../gp/product/ASIN on any amazon TLD.
  var ASIN_RE = /amazon\.[a-z.]+\/(?:[^\s"']*?\/)?(?:dp|gp\/product|gp\/aw\/d)\/([A-Z0-9]{10})/i;

  function extractAsin(href) {
    if (!href) return null;
    var m = ASIN_RE.exec(href);
    return m ? m[1].toUpperCase() : null;
  }

  function send(asin) {
    try {
      var payload = JSON.stringify({ asin: asin, postId: cfg.postId });
      var blob = new Blob([payload], { type: "application/json" });
      navigator.sendBeacon(cfg.endpoint, blob);
    } catch (e) {
      /* tracking must never break the click */
    }
  }

  function onClick(event) {
    var a = event.target && event.target.closest ? event.target.closest("a[href]") : null;
    if (!a) return;
    var asin = extractAsin(a.getAttribute("href") || a.href);
    if (asin) {
      send(asin);
    }
  }

  document.addEventListener("click", onClick, true);
  document.addEventListener("auxclick", onClick, true);
})();
