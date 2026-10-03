/*
 * Runs in <head>, before the first paint.
 * - marks the page as loading: CSS draws the app shell (sidebar + topbar) and keeps
 *   the content hidden until app.js has built the layout and the fonts are ready,
 *   so a reload never shows a half-built page or text jumping between fonts
 * - restores the collapsed sidebar immediately (no width animation on load)
 * - preloads the Arabic Cairo weights used above the fold
 */
(function () {
  var html = document.documentElement;
  html.classList.add('app-loading');

  try {
    var saved = localStorage.getItem('batta-sb-collapsed');
    if (saved === '1' || (saved === null && window.innerWidth <= 1280)) html.classList.add('sb-collapsed');
  } catch (e) {}

  // fonts live next to this script: <base>/js/boot.js → <base>/fonts
  var base = (document.currentScript && document.currentScript.src.replace(/js\/boot\.js(\?.*)?$/, '')) || '';
  ['400', '600', '700', '800'].forEach(function (w) {
    var l = document.createElement('link');
    l.rel = 'preload';
    l.as = 'font';
    l.type = 'font/woff2';
    l.crossOrigin = 'anonymous';
    l.href = base + 'fonts/cairo-arabic-' + w + '-normal.woff2';
    document.head.appendChild(l);
  });

  // safety net: never leave the page hidden if something fails to load
  setTimeout(function () {
    html.classList.remove('app-loading');
  }, 3000);
})();
