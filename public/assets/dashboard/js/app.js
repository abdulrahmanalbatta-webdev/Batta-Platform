/* ==========================================================================
   Batta Dashboard — shared engine
   - يبني الشريط الجانبي والشريط العلوي في كل صفحة (حتى لا يتكرر في 17 ملفاً)
   - أدوات مشتركة: الأيقونات، الإشعارات (toast)، النوافذ، القوائم، التبويبات، الجداول
   كل صفحة HTML مستقلة (ليست SPA)، وتحدد اسمها عبر <body data-page="...">
   ========================================================================== */
(function () {
  'use strict';

  /* ---------- tiny helpers ---------- */
  const $ = (sel, root = document) => root.querySelector(sel);
  const $$ = (sel, root = document) => [...root.querySelectorAll(sel)];
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const num = (n) => Number(n).toLocaleString('en-US');
  // amounts in the platform currency (settings → عام), for project budgets, e.g. "1,200$" or "49 ر.س"
  const money = (n) => `${num(n)}${CFG.currency_symbol ?? '$'}`;
  const months = ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];
  const date = (iso) => {
    const [y, m, d] = String(iso).split('-').map(Number);
    return `${d} ${months[m - 1]} ${y}`;
  };
  const debounce = (fn, ms = 200) => {
    let t;
    return (...a) => {
      clearTimeout(t);
      t = setTimeout(() => fn(...a), ms);
    };
  };

  /* ---------- icons ---------- */
  const ICONS = {
    grid: '<rect x="3" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="3" width="7.5" height="7.5" rx="1.8"/><rect x="3" y="13.5" width="7.5" height="7.5" rx="1.8"/><rect x="13.5" y="13.5" width="7.5" height="7.5" rx="1.8"/>',
    chart: '<path d="M3 20.5h18"/><path d="M6.5 16v-4M11 16V7.5M15.5 16v-6M20 16V4.5"/>',
    play: '<circle cx="12" cy="12" r="9.5"/><path d="M10 8.5l5.5 3.5-5.5 3.5z"/>',
    calendar: '<rect x="3.5" y="4.5" width="17" height="16" rx="2.5"/><path d="M16 2.5v4M8 2.5v4M3.5 10h17"/>',
    article: '<path d="M14 2.5H6.5a2 2 0 0 0-2 2v15a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2V8z"/><path d="M14 2.5V8h5.5M8.5 13h7M8.5 17h5"/>',
    cart: '<circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/><path d="M2.5 3.5h2.8l2.4 11.2a1.8 1.8 0 0 0 1.8 1.4h8.4a1.8 1.8 0 0 0 1.7-1.3l1.9-6.8H6.2"/>',
    tag: '<path d="M20.6 13.4l-7.2 7.2a1.8 1.8 0 0 1-2.6 0L3 12.8V3h9.8l7.8 7.8a1.8 1.8 0 0 1 0 2.6z"/><circle cx="7.5" cy="7.5" r="1.3"/>',
    briefcase: '<rect x="2.5" y="7" width="19" height="13.5" rx="2"/><path d="M16 20.5V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v15.5"/>',
    users: '<path d="M16.5 20v-1.5a4 4 0 0 0-4-4h-6a4 4 0 0 0-4 4V20"/><circle cx="9.5" cy="7.5" r="3.8"/><path d="M21.5 20v-1.5a4 4 0 0 0-3-3.9M15.5 3.7a3.8 3.8 0 0 1 0 7.4"/>',
    chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
    star: '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5L12 17.4l-5.9 3.1 1.2-6.5-4.8-4.6 6.6-.9z"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/>',
    user: '<path d="M19.5 20.5v-1.5a4 4 0 0 0-4-4h-7a4 4 0 0 0-4 4v1.5"/><circle cx="12" cy="7.5" r="4"/>',
    search: '<circle cx="11" cy="11" r="7.5"/><path d="M20.5 20.5l-4.3-4.3"/>',
    bell: '<path d="M18 8.5a6 6 0 0 0-12 0c0 7-3 8.5-3 8.5h18s-3-1.5-3-8.5M13.7 21a2 2 0 0 1-3.4 0"/>',
    plus: '<path d="M12 5v14M5 12h14"/>',
    menu: '<path d="M3.5 6.5h17M3.5 12h17M3.5 17.5h17"/>',
    close: '<path d="M18 6L6 18M6 6l12 12"/>',
    'chevron-down': '<path d="M6 9l6 6 6-6"/>',
    'chevron-left': '<path d="M15 18l-6-6 6-6"/>',
    'chevron-right': '<path d="M9 18l6-6-6-6"/>',
    'chevrons-up-down': '<path d="M7 15l5 5 5-5M7 9l5-5 5 5"/>',
    arrow: '<path d="M19 12H5M11 18l-6-6 6-6"/>',
    'arrow-up': '<path d="M12 19V5M5 12l7-7 7 7"/>',
    'arrow-down': '<path d="M12 5v14M19 12l-7 7-7-7"/>',
    more: '<circle cx="12" cy="5" r="1.4"/><circle cx="12" cy="12" r="1.4"/><circle cx="12" cy="19" r="1.4"/>',
    edit: '<path d="M12 20h9M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
    trash: '<path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6M10 11v6M14 11v6"/>',
    eye: '<path d="M1.5 12S5.5 4.5 12 4.5 22.5 12 22.5 12 18.5 19.5 12 19.5 1.5 12 1.5 12z"/><circle cx="12" cy="12" r="3"/>',
    'eye-off': '<path d="M17.9 17.9A10.4 10.4 0 0 1 12 19.5C5.5 19.5 1.5 12 1.5 12a18.6 18.6 0 0 1 4.6-5.9M9.9 4.7A9.6 9.6 0 0 1 12 4.5c6.5 0 10.5 7.5 10.5 7.5a18.7 18.7 0 0 1-2.2 3.3M14.1 14.1a3 3 0 1 1-4.2-4.2M1.5 1.5l21 21"/>',
    download: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>',
    upload: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>',
    filter: '<path d="M22 3H2l8 9.5V19l4 2v-8.5z"/>',
    check: '<path d="M20 6L9 17l-5-5"/>',
    'check-circle': '<circle cx="12" cy="12" r="9.5"/><path d="M8 12.5l2.5 2.5L16 9.5"/>',
    'x-circle': '<circle cx="12" cy="12" r="9.5"/><path d="M15 9l-6 6M9 9l6 6"/>',
    alert: '<path d="M10.3 3.9L1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
    clock: '<circle cx="12" cy="12" r="9.5"/><path d="M12 7v5l3.5 2"/>',
    logout: '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4M16 17l5-5-5-5M21 12H9"/>',
    mail: '<rect x="2.5" y="4.5" width="19" height="15" rx="2"/><path d="M21.5 6.5L12 13 2.5 6.5"/>',
    phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 1.9.7 2.8a2 2 0 0 1-.5 2.1L8 9.9a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.5c.9.3 1.8.6 2.8.7a2 2 0 0 1 1.7 2z"/>',
    globe: '<circle cx="12" cy="12" r="9.5"/><path d="M2.5 12h19M12 2.5a14.5 14.5 0 0 1 0 19 14.5 14.5 0 0 1 0-19z"/>',
    image: '<rect x="3" y="3" width="18" height="18" rx="2.5"/><circle cx="8.5" cy="8.5" r="1.8"/><path d="M21 15l-5-5L5 21"/>',
    link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
    copy: '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
    send: '<path d="M22 2L11 13M22 2l-7 20-4-9-9-4z"/>',
    paperclip: '<path d="M21.4 11l-9.2 9.2a6 6 0 0 1-8.5-8.5l9.2-9.2a4 4 0 0 1 5.7 5.7l-9.2 9.2a2 2 0 0 1-2.8-2.8l8.5-8.5"/>',
    refresh: '<path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.5 9a9 9 0 0 1 14.8-3.4L23 10M1 14l4.6 4.4A9 9 0 0 0 20.5 15"/>',
    external: '<path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6M15 3h6v6M10 14L21 3"/>',
    lock: '<rect x="4" y="10.5" width="16" height="11" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>',
    shield: '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
    card: '<rect x="2" y="5" width="20" height="14" rx="2.5"/><path d="M2 10h20"/>',
    dollar: '<path d="M12 1.5v21M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>',
    trend: '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
    layers: '<path d="M12 2.5L2.5 7.5 12 12.5l9.5-5z"/><path d="M2.5 16.5L12 21.5l9.5-5M2.5 12L12 17l9.5-5"/>',
    home: '<path d="M3 10.5L12 3l9 7.5V20a1.5 1.5 0 0 1-1.5 1.5H15v-6H9v6H4.5A1.5 1.5 0 0 1 3 20z"/>',
    award: '<circle cx="12" cy="8.5" r="6"/><path d="M8.5 13.5L7 22l5-3 5 3-1.5-8.5"/>',
    monitor: '<rect x="2.5" y="3.5" width="19" height="13" rx="2"/><path d="M8 20.5h8M12 16.5v4"/>',
    pin: '<path d="M20 10c0 6-8 11.5-8 11.5S4 16 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.8"/>',
    code: '<path d="M16 18l6-6-6-6M8 6l-6 6 6 6"/>',
    bold: '<path d="M6 4h8a4 4 0 0 1 0 8H6zM6 12h9a4 4 0 0 1 0 8H6z"/>',
    italic: '<path d="M19 4h-9M14 20H5M15 4L9 20"/>',
    list: '<path d="M8 6h13M8 12h13M8 18h13M3.5 6h.01M3.5 12h.01M3.5 18h.01"/>',
    quote: '<path d="M3 21c3 0 7-1 7-8V5H3v7h4c0 3-1 5-4 5zM14 21c3 0 7-1 7-8V5h-7v7h4c0 3-1 5-4 5z"/>',
    heading: '<path d="M6 4v16M18 4v16M6 12h12"/>',
    grip: '<circle cx="9" cy="6" r="1.2"/><circle cx="15" cy="6" r="1.2"/><circle cx="9" cy="12" r="1.2"/><circle cx="15" cy="12" r="1.2"/><circle cx="9" cy="18" r="1.2"/><circle cx="15" cy="18" r="1.2"/>',
    save: '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8M7 3v5h8"/>',
    info: '<circle cx="12" cy="12" r="9.5"/><path d="M12 11v5.5M12 7.6v.1"/>',
    help: '<circle cx="12" cy="12" r="9.5"/><path d="M9.1 9a3 3 0 0 1 5.8 1c0 2-3 3-3 3M12 17h.01"/>',
    reply: '<path d="M9 17l-5-5 5-5"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
    sidebar: '<rect x="3" y="3.5" width="18" height="17" rx="3"/><path d="M15 3.5v17"/><rect class="pane" x="15" y="3.5" width="6" height="17" rx="0"/><path class="chev" d="M9.5 9.5l2.5 2.5-2.5 2.5"/>',
    sparkle: '<path d="M12 3l1.9 5.6L19.5 10.5l-5.6 1.9L12 18l-1.9-5.6L4.5 10.5l5.6-1.9z"/>',
  };
  const icon = (name, cls = '') => `<svg class="icon ${cls}" viewBox="0 0 24 24" aria-hidden="true">${ICONS[name] || ''}</svg>`;
  // replace <i data-icon="name" class="sm"></i> placeholders
  function hydrateIcons(root = document) {
    $$('i[data-icon]', root).forEach((el) => {
      el.outerHTML = icon(el.dataset.icon, el.className);
    });
  }

  /* ---------- routes & assets (provided by Laravel in layouts/partials/app-config) ---------- */
  const CFG = window.APP || { routes: {}, assets: 'assets' };
  // url('course-edit', { id: 'C-101' }) → /dashboard/courses/C-101/edit ; extra params become ?query
  function url(name, params = {}, hash = '') {
    let u = CFG.routes[name];
    if (!u) {
      console.warn(`Unknown route: ${name}`);
      return '#';
    }
    const rest = { ...params };
    if (u.includes('__ID__')) {
      u = u.replace('__ID__', encodeURIComponent(rest.id ?? ''));
      delete rest.id;
    }
    const qs = new URLSearchParams(rest).toString();
    return `${u}${qs ? `?${qs}` : ''}${hash ? `#${encodeURIComponent(hash)}` : ''}`;
  }
  const asset = (p) => `${CFG.assets}/${p}`;

  /* ---------- navigation ---------- */
  // the signed-in member, from Laravel (null on the sign-in pages)
  const USER = CFG.user || null;
  const ME = { name: USER?.name || '', role: USER?.role_label || '', initial: USER?.initial || '', photo: USER?.avatar_url || null };
  // sidebar badges from the server; pages update them with setNavCount after a change
  const COUNTS = CFG.counts || {};
  const unreadMsgs = COUNTS.messages || 0;
  const newLeads = COUNTS.leads || 0;
  const pendingReviews = COUNTS.reviews || 0;
  const pendingComments = COUNTS.comments || 0;

  const NAV = [
    { label: 'الرئيسية', items: [
      { page: 'dashboard', href: url('dashboard'), icon: 'grid', label: 'لوحة المعلومات' },
      { page: 'analytics', href: url('analytics'), icon: 'chart', label: 'التحليلات' },
    ] },
    { label: 'المحتوى', items: [
      { page: 'courses', href: url('courses'), icon: 'play', label: 'الدورات', also: ['course-form'] },
      { page: 'workshops', href: url('workshops'), icon: 'calendar', label: 'الورش' },
      { page: 'articles', href: url('articles'), icon: 'article', label: 'المقالات', also: ['article-editor'] },
      { page: 'tools', href: url('tools'), icon: 'code', label: 'الأدوات' },
      { page: 'site-content', href: url('site-content'), icon: 'globe', label: 'محتوى الموقع' },
    ] },
    { label: 'العملاء', items: [
      { page: 'leads', href: url('leads'), icon: 'briefcase', label: 'طلبات المشاريع', count: newLeads },
    ] },
    { label: 'المجتمع', items: [
      { page: 'students', href: url('students'), icon: 'users', label: 'الطلاب' },
      { page: 'subscribers', href: url('subscribers'), icon: 'mail', label: 'النشرة البريدية' },
      { page: 'messages', href: url('messages'), icon: 'chat', label: 'الرسائل', count: unreadMsgs, hot: true },
      { page: 'reviews', href: url('reviews'), icon: 'star', label: 'التقييمات', count: pendingReviews },
      { page: 'comments', href: url('comments'), icon: 'chat', label: 'التعليقات', count: pendingComments },
    ] },
    { label: 'النظام', items: [
      { page: 'settings', href: url('settings'), icon: 'settings', label: 'الإعدادات' },
      { page: 'profile', href: url('profile'), icon: 'user', label: 'الملف الشخصي' },
    ] },
  ];

  const avatarHTML = (cls = '') => {
    return `<span class="avatar ${cls}">${ME.photo ? `<img src="${esc(ME.photo)}" alt="">` : ''}${esc(ME.initial)}</span>`;
  };

  /* ---------- sidebar toggle: collapse to an icon rail on desktop, slide-in panel on mobile ---------- */
  const SB_KEY = 'batta-sb-collapsed';
  const desktop = matchMedia('(min-width: 1025px)');
  const root = document.documentElement;
  const isCollapsed = () => root.classList.contains('sb-collapsed');

  function initSidebarToggle() {
    const btn = $('#sbToggle');

    function sync() {
      const label = desktop.matches ? (isCollapsed() ? 'توسيع القائمة' : 'طي القائمة') : 'فتح القائمة';
      btn.setAttribute('aria-label', label);
      btn.setAttribute('aria-expanded', String(desktop.matches ? !isCollapsed() : document.body.classList.contains('sb-open')));
      btn.dataset.tip = label;
      btn.dataset.kbd = desktop.matches ? '[' : '';
    }
    function setCollapsed(on) {
      root.classList.toggle('sb-collapsed', on);
      try { localStorage.setItem(SB_KEY, on ? '1' : '0'); } catch {}
      hideTip();
      sync();
    }
    const toggle = () => (desktop.matches ? setCollapsed(!isCollapsed()) : document.body.classList.toggle('sb-open'));

    btn.addEventListener('click', () => {
      toggle();
      sync();
      if (tip) showTip(btn); // keep the tooltip text in step with the new state
    });
    $('#sbBackdrop').addEventListener('click', () => document.body.classList.remove('sb-open'));
    desktop.addEventListener('change', () => {
      document.body.classList.remove('sb-open');
      hideTip();
      sync();
    });
    sync();

    // "[" toggles the sidebar (outside text fields)
    document.addEventListener('keydown', (e) => {
      if (e.key !== '[' || e.ctrlKey || e.metaKey || e.altKey) return;
      if (e.target.closest?.('input, textarea, select, [contenteditable]')) return;
      e.preventDefault();
      toggle();
      sync();
    });
  }

  /* ---------- tooltips: icon rail (side) + topbar buttons (below) ---------- */
  let tip = null;
  function hideTip() {
    tip?.remove();
    tip = null;
  }
  function showTip(el) {
    hideTip();
    const r = el.getBoundingClientRect();
    const below = el.dataset.tipPos === 'bottom';
    tip = document.createElement('div');
    tip.className = `ui-tip ${below ? 'below' : 'side'}`;
    tip.dataset.for = el.dataset.tip;
    tip.innerHTML = `${esc(el.dataset.tip)}${el.dataset.kbd ? `<kbd>${esc(el.dataset.kbd)}</kbd>` : ''}`;
    document.body.appendChild(tip);
    if (below) {
      // centre under the button, but keep it inside the content area (not over the sidebar or off-screen)
      const sb = desktop.matches ? $('#sidebar')?.getBoundingClientRect() : null;
      const maxRight = (sb ? sb.left : innerWidth) - 8;
      const w = tip.offsetWidth;
      const left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, maxRight - w));
      tip.style.top = `${r.bottom + 8}px`;
      tip.style.left = `${left}px`;
      tip.style.setProperty('--arrow-x', `${r.left + r.width / 2 - left}px`);
    } else {
      tip.style.top = `${r.top + r.height / 2}px`;
      tip.style.right = `${innerWidth - r.left + 10}px`; // RTL: the rail is on the right, tips open to its left
    }
  }
  function initTooltips() {
    if (!matchMedia('(hover: hover)').matches) return;
    document.addEventListener('mouseover', (e) => {
      const el = e.target.closest('[data-tip]');
      const inRail = el?.closest('#sidebar');
      if (!el || (inRail && (!desktop.matches || !isCollapsed())) || el.closest('.dropdown.open')) return hideTip();
      if (tip?.dataset.for !== el.dataset.tip) showTip(el);
    });
    document.addEventListener('mousedown', hideTip);
    addEventListener('scroll', hideTip, { passive: true, capture: true });
  }

  function buildLayout() {
    const page = document.body.dataset.page;
    const content = $('#content');
    if (!content) return; // standalone pages (login, 404)

    const navHTML = NAV.map(
      (g) => `
      <div class="sb-group">
        <div class="sb-label">${g.label}</div>
        ${g.items
          .map((it) => {
            const active = it.page === page || (it.also || []).includes(page);
            const count = it.count ? `<span class="count ${it.hot ? 'hot' : ''}">${it.count}</span>` : '';
            return `<a class="sb-link ${active ? 'active' : ''}" href="${it.href}" data-nav="${it.page}" data-tip="${it.label}${it.count ? ` (${it.count})` : ''}" ${active ? 'aria-current="page"' : ''}>${icon(it.icon)}<span class="sb-text">${it.label}</span>${count}</a>`;
          })
          .join('')}
      </div>`,
    ).join('');

    const sidebar = `
      <aside class="sidebar" id="sidebar" aria-label="القائمة الجانبية">
        <a class="sb-brand" href="${url('dashboard')}">
          <img src="${asset('img/logo-white.png')}" alt="">
          <span class="sb-text"><b>${esc(CFG.app_name)}</b><small>لوحة التحكم</small></span>
        </a>
        <nav class="sb-nav">${navHTML}</nav>
        <div class="sb-foot">
          <div class="sb-upgrade">
            <b>مساحة التخزين</b>
            <p>استخدمت 6.2 من 10 جيجابايت لفيديوهات الدورات.</p>
            <div class="bar"><i style="width:62%"></i></div>
          </div>
          <a class="sb-user" href="${url('profile')}" data-tip="${esc(ME.name)}">
            ${avatarHTML('sm')}
            <span class="sb-text"><b>${esc(ME.name)}</b><small>${esc(ME.role)}</small></span>
            ${icon('chevron-left', 'sm')}
          </a>
        </div>
      </aside>
      <div class="sb-backdrop" id="sbBackdrop"></div>`;

    const topbar = `
      <header class="topbar">
        <button class="tb-btn tb-menu" id="sbToggle" type="button" aria-controls="sidebar" data-tip-pos="bottom">${icon('sidebar')}</button>
        <span class="tb-sep" aria-hidden="true"></span>
        <label class="tb-search">
          ${icon('search', 'sm')}
          <input type="search" id="globalSearch" placeholder="ابحث عن طالب، دورة، مقال…" aria-label="بحث عام">
          <kbd>/</kbd>
        </label>
        <div class="tb-actions">
          <div class="dropdown hide-xs" data-requires="manage_content">
            <button class="btn btn-primary btn-sm" data-dropdown>${icon('plus', 'sm')}<span>إنشاء</span></button>
            <div class="menu">
              <a href="${url('course-create')}">${icon('play', 'sm')}دورة جديدة</a>
              <a href="${url('article-create')}">${icon('article', 'sm')}مقال جديد</a>
              <a href="${url('workshops', {}, 'new')}">${icon('calendar', 'sm')}ورشة جديدة</a>
              <a href="${url('tools', {}, 'new')}">${icon('code', 'sm')}أداة جديدة</a>
            </div>
          </div>
          <a class="tb-btn hide-sm" href="${esc(CFG.site_url || '#')}" target="_blank" rel="noopener" data-tip="عرض الموقع" data-tip-pos="bottom" aria-label="عرض الموقع">${icon('external')}</a>
          <div class="dropdown">
            <button class="tb-btn" id="bellBtn" data-dropdown aria-label="الإشعارات" data-tip="الإشعارات" data-tip-pos="bottom">${icon('bell')}</button>
            <div class="menu notif-menu">
              <div class="menu-head"><b>الإشعارات</b><button class="link" style="width:auto;padding:0" data-mark-read hidden>تعليم الكل كمقروء</button></div>
              <div id="notifList"></div>
            </div>
          </div>
          <div class="dropdown">
            <button class="tb-user" data-dropdown aria-label="حسابي">
              ${avatarHTML('sm')}
              <span class="who"><b>${esc(ME.name)}</b><small>${esc(ME.role)}</small></span>
              ${icon('chevron-down', 'sm')}
            </button>
            <div class="menu">
              <a href="${url('profile')}">${icon('user', 'sm')}الملف الشخصي</a>
              <a href="${url('settings')}">${icon('settings', 'sm')}الإعدادات</a>
              <a href="${url('messages')}">${icon('chat', 'sm')}الرسائل</a>
              <hr>
              <button type="button" class="danger" data-logout>${icon('logout', 'sm')}تسجيل الخروج</button>
            </div>
          </div>
        </div>
      </header>`;

    document.body.insertAdjacentHTML('afterbegin', sidebar);
    const main = document.createElement('div');
    main.className = 'main';
    content.parentNode.insertBefore(main, content);
    main.insertAdjacentHTML('afterbegin', topbar);
    main.appendChild(content);
    content.classList.add('content');

    initSidebarToggle();

    // "/" focuses the global search
    const gs = $('#globalSearch');
    document.addEventListener('keydown', (e) => {
      if (e.key === '/' && !/input|textarea|select/i.test(document.activeElement.tagName)) {
        e.preventDefault();
        gs.focus();
      }
    });
    initGlobalSearch(gs);

    document.addEventListener('click', async (e) => {
      if (e.target.closest('[data-logout]')) {
        try {
          await api.post('auth/logout');
        } catch {
          return;
        }
        location.href = url('login');
      }
      if (e.target.closest('[data-mark-read]')) {
        try {
          await api.post('notifications/read');
        } catch {
          return;
        }
        notifications.forEach((n) => (n.unread = false));
        renderNotifications();
        toast('تم تعليم كل الإشعارات كمقروءة');
      }
      const item = e.target.closest('[data-notif]');
      if (item) {
        e.preventDefault();
        const n = notifications.find((x) => x.id === item.dataset.notif);
        if (n?.unread) await api.post(`notifications/${n.id}/read`).catch(() => {});
        location.href = item.href;
      }
    });

    loadNotifications();
    // check for new ones every minute while the tab is visible
    setInterval(() => document.visibilityState === 'visible' && loadNotifications(), 60000);
  }

  /* ---------- topbar search: results from /search as you type; arrows + Enter to open one ---------- */
  function initGlobalSearch(input) {
    const pop = document.createElement('div');
    pop.className = 'search-pop';
    pop.id = 'searchPop';
    pop.setAttribute('role', 'listbox');
    pop.hidden = true;
    input.closest('.tb-search').appendChild(pop);
    input.setAttribute('aria-controls', 'searchPop');
    let seq = 0;
    let active = -1;
    const links = () => $$('a', pop);
    const close = () => {
      pop.hidden = true;
      active = -1;
    };
    const highlight = (i) => {
      const all = links();
      active = all.length ? (i + all.length) % all.length : -1;
      all.forEach((a, n) => a.classList.toggle('on', n === active));
      all[active]?.scrollIntoView({ block: 'nearest' });
    };
    const search = debounce(async () => {
      const q = input.value.trim();
      const mine = ++seq;
      if (q.length < 2) return close();
      let res;
      try {
        res = await api.get('search', { q });
      } catch {
        return;
      }
      if (mine !== seq) return; // a newer search is on its way
      pop.innerHTML =
        res.data
          .map(
            (g) =>
              `<div class="sp-group">${esc(g.label)}</div>${g.items
                .map((it) => `<a href="${url(it.page, it.params)}" role="option"><b>${esc(it.title)}</b>${it.subtitle ? `<small>${esc(it.subtitle)}</small>` : ''}</a>`)
                .join('')}`,
          )
          .join('') || `<div class="sp-empty">لا توجد نتائج لـ "${esc(q)}"</div>`;
      pop.hidden = false;
      highlight(0);
    }, 200);
    input.addEventListener('input', search);
    input.addEventListener('focus', () => input.value.trim().length >= 2 && pop.innerHTML && (pop.hidden = false));
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') return close();
      if (pop.hidden) return;
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        highlight(active + (e.key === 'ArrowDown' ? 1 : -1));
      } else if (e.key === 'Enter') {
        const target = links()[Math.max(active, 0)];
        if (target) {
          e.preventDefault();
          location.href = target.href;
        }
      }
    });
    document.addEventListener('click', (e) => !e.target.closest('.tb-search') && close());
  }

  // the dot colour of an activity-log entry by its action
  const activityTone = (action) => ({ created: '#0e9f6e', updated: '#0066ff', status: '#c27803', deleted: '#e02424', exported: '#0891b2', wiped: '#e02424' })[action] || '#94a3b8';

  /* ---------- bell: the member's notifications from /notifications ---------- */
  const ALERT_STYLE = {
    registrations: ['award', 'c-green'],
    students: ['users', 'c-blue'],
    leads: ['briefcase', 'c-blue'],
    reviews: ['star', 'c-amber'],
    comments: ['chat', 'c-amber'],
    messages: ['chat', 'c-violet'],
    export: ['download', 'c-blue'],
  };
  let notifications = [];
  // "قبل 5 دقائق", "أمس", or the date for older ones
  function ago(iso) {
    const minutes = Math.floor((Date.now() - new Date(iso)) / 60000);
    if (minutes < 1) return 'الآن';
    if (minutes < 60) return minutes === 1 ? 'قبل دقيقة' : minutes === 2 ? 'قبل دقيقتين' : `قبل ${minutes} ${minutes <= 10 ? 'دقائق' : 'دقيقة'}`;
    const hours = Math.floor(minutes / 60);
    if (hours < 24) return hours === 1 ? 'قبل ساعة' : hours === 2 ? 'قبل ساعتين' : `قبل ${hours} ${hours <= 10 ? 'ساعات' : 'ساعة'}`;
    if (hours < 48) return 'أمس';
    const d = new Date(iso);
    return date(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
  }
  function renderNotifications() {
    const list = $('#notifList');
    if (!list) return;
    const unread = notifications.filter((n) => n.unread).length;
    list.innerHTML =
      notifications
        .map((n) => {
          const [ic, tone] = ALERT_STYLE[n.type] || ['bell', 'c-blue'];
          return `
        <a class="notif ${n.unread ? 'unread' : ''}" href="${url(n.page, n.params || {}, n.hash || '')}" data-notif="${esc(n.id)}">
          <span class="n-ico ${tone}">${icon(ic, 'sm')}</span>
          <span><b>${esc(n.title)}</b><small>${esc(n.meta)} · ${esc(ago(n.at))}</small></span>
        </a>`;
        })
        .join('') || '<div class="muted" style="padding:22px 16px;text-align:center;font-size:13px">لا توجد إشعارات</div>';
    $('[data-mark-read]').hidden = !unread;
    const bell = $('#bellBtn');
    bell.querySelector('.dot')?.remove();
    if (unread) bell.insertAdjacentHTML('beforeend', '<span class="dot"></span>');
    bell.setAttribute('aria-label', unread ? `الإشعارات (${unread} غير مقروءة)` : 'الإشعارات');
  }
  async function loadNotifications() {
    if (!USER) return;
    try {
      notifications = (await api.get('notifications')).data;
    } catch {
      return;
    }
    renderNotifications();
  }

  /* ---------- toast ---------- */
  function toast(message, type = 'success') {
    let host = $('.toasts');
    if (!host) {
      host = document.createElement('div');
      host.className = 'toasts';
      host.setAttribute('role', 'status');
      host.setAttribute('aria-live', 'polite');
      document.body.appendChild(host);
    }
    const el = document.createElement('div');
    el.className = `toast ${type}`;
    el.innerHTML = `${icon(type === 'error' ? 'x-circle' : type === 'info' ? 'info' : 'check-circle')}<span>${esc(message)}</span>`;
    host.appendChild(el);
    setTimeout(() => {
      el.classList.add('out');
      el.addEventListener('animationend', () => el.remove());
    }, 2800);
  }

  /* ---------- dropdowns ---------- */
  function initDropdowns() {
    document.addEventListener('click', (e) => {
      const trigger = e.target.closest('[data-dropdown]');
      const openOnes = $$('.dropdown.open');
      if (trigger) {
        const dd = trigger.closest('.dropdown');
        const willOpen = !dd.classList.contains('open');
        openOnes.forEach((d) => d.classList.remove('open'));
        if (willOpen) {
          dd.classList.add('open');
          // menus inside scrolling tables would be clipped: float them above the page
          const menu = dd.querySelector('.menu');
          if (dd.closest('.table-wrap, .kanban') && menu) {
            const r = trigger.getBoundingClientRect();
            menu.style.position = 'fixed';
            menu.style.insetInlineEnd = 'auto';
            const w = menu.offsetWidth;
            const below = window.innerHeight - r.bottom > menu.offsetHeight + 16;
            menu.style.top = `${below ? r.bottom + 6 : r.top - menu.offsetHeight - 6}px`;
            menu.style.left = `${Math.max(8, r.right - w)}px`;
          }
        }
        return;
      }
      if (!e.target.closest('.menu')) openOnes.forEach((d) => d.classList.remove('open'));
      else if (e.target.closest('.menu a, .menu button:not([data-keep])')) openOnes.forEach((d) => d.classList.remove('open'));
    });
    // floating (table) menus would drift when the page scrolls
    window.addEventListener('scroll', () => $$('.table-wrap .dropdown.open').forEach((d) => d.classList.remove('open')), { passive: true });
    document.addEventListener('scroll', (e) => e.target.classList?.contains('table-wrap') && $$('.dropdown.open', e.target).forEach((d) => d.classList.remove('open')), true);
    // Escape closes the top-most layer only: menu → modal → drawer → mobile sidebar
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Escape') return;
      const menus = $$('.dropdown.open');
      if (menus.length) return menus.forEach((d) => d.classList.remove('open'));
      const modal = $$('.modal.open').at(-1);
      if (modal) return closeModal(modal);
      if ($('.drawer.open')) return closeDrawer();
      document.body.classList.remove('sb-open');
    });
  }

  // keep Tab inside an open modal / drawer so keyboard users don't land behind it
  function initFocusTrap() {
    document.addEventListener('keydown', (e) => {
      if (e.key !== 'Tab') return;
      const layer = $$('.modal.open').at(-1) || $('.drawer.open');
      if (!layer) return;
      const items = $$('a[href], button:not([disabled]), input:not([disabled]):not([type="hidden"]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])', layer).filter(
        (el) => el.offsetParent !== null,
      );
      if (!items.length) return;
      const first = items[0];
      const last = items.at(-1);
      if (!layer.contains(document.activeElement)) {
        e.preventDefault();
        first.focus();
      } else if (e.shiftKey && document.activeElement === first) {
        e.preventDefault();
        last.focus();
      } else if (!e.shiftKey && document.activeElement === last) {
        e.preventDefault();
        first.focus();
      }
    });
  }

  /* ---------- tabs: <div class="tabs" data-tabs="group"><button data-tab="id"> + <div class="tab-panel" data-panel="id"> ---------- */
  function initTabs(root = document) {
    $$('[data-tabs]', root).forEach((bar) => {
      const group = bar.dataset.tabs;
      bar.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-tab]');
        if (!btn) return;
        $$('[data-tab]', bar).forEach((b) => b.classList.toggle('on', b === btn));
        $$(`[data-panel-group="${group}"]`).forEach((p) => p.classList.toggle('on', p.dataset.panel === btn.dataset.tab));
        bar.dispatchEvent(new CustomEvent('tabchange', { detail: btn.dataset.tab }));
      });
    });
  }

  /* ---------- modals ---------- */
  let lastFocus;
  function openModal(idOrEl) {
    const m = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
    if (!m) return;
    lastFocus = document.activeElement;
    m.classList.add('open');
    m.setAttribute('aria-hidden', 'false');
    setTimeout(() => $('input, select, textarea, button', m.querySelector('.modal-body') || m)?.focus(), 60);
  }
  function closeModal(idOrEl) {
    const m = typeof idOrEl === 'string' ? document.getElementById(idOrEl) : idOrEl;
    if (!m) return;
    m.classList.remove('open');
    m.setAttribute('aria-hidden', 'true');
    lastFocus?.focus?.();
  }
  function initModals() {
    document.addEventListener('click', (e) => {
      const opener = e.target.closest('[data-open]');
      if (opener) {
        e.preventDefault();
        openModal(opener.dataset.open);
      }
      if (e.target.closest('[data-close]')) closeModal(e.target.closest('.modal'));
      if (e.target.classList.contains('modal')) closeModal(e.target);
    });
  }

  // confirm dialog → Promise<boolean>
  function confirmDialog({ title = 'هل أنت متأكد؟', text = '', ok = 'تأكيد', danger = true } = {}) {
    return new Promise((resolve) => {
      const m = document.createElement('div');
      m.className = 'modal';
      m.innerHTML = `
        <div class="modal-box sm" role="alertdialog" aria-modal="true">
          <div class="modal-body" style="text-align:center">
            <div class="confirm-ico ${danger ? 'c-red' : 'c-blue'}" style="margin-inline:auto">${icon(danger ? 'alert' : 'help', 'lg')}</div>
            <h3 style="font-size:18px">${esc(title)}</h3>
            <p class="muted" style="margin-top:6px">${esc(text)}</p>
          </div>
          <div class="modal-foot" style="justify-content:center">
            <button class="btn btn-ghost" data-act="no">إلغاء</button>
            <button class="btn ${danger ? 'btn-danger' : 'btn-primary'}" data-act="yes">${esc(ok)}</button>
          </div>
        </div>`;
      const returnFocus = document.activeElement;
      document.body.appendChild(m);
      requestAnimationFrame(() => m.classList.add('open'));
      $('[data-act="yes"]', m).focus();
      let settled = false;
      const done = (val) => {
        if (settled) return;
        settled = true;
        m.classList.remove('open');
        setTimeout(() => m.remove(), 200);
        returnFocus?.focus?.();
        resolve(val);
      };
      m.addEventListener('click', (e) => {
        const act = e.target.closest('[data-act]')?.dataset.act;
        if (act) done(act === 'yes');
        else if (e.target === m) done(false);
      });
      // Escape cancels this dialog only (not the modal or drawer underneath)
      m.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape') return;
        e.stopPropagation();
        done(false);
      });
    });
  }

  /* ---------- drawer ---------- */
  function openDrawer(html) {
    closeDrawer();
    const bd = document.createElement('div');
    bd.className = 'drawer-backdrop';
    const dr = document.createElement('aside');
    dr.className = 'drawer';
    dr.setAttribute('role', 'dialog');
    dr.setAttribute('aria-modal', 'true');
    dr.innerHTML = html;
    document.body.append(bd, dr);
    hydrateIcons(dr);
    requestAnimationFrame(() => {
      bd.classList.add('open');
      dr.classList.add('open');
    });
    bd.addEventListener('click', closeDrawer);
    dr.addEventListener('click', (e) => e.target.closest('[data-close-drawer]') && closeDrawer());
    return dr;
  }
  function closeDrawer() {
    $$('.drawer, .drawer-backdrop').forEach((el) => {
      el.classList.remove('open');
      setTimeout(() => el.remove(), 250);
    });
  }

  /* ---------- DataTable: search, filters, sort, pagination, selection ---------- */
  const collator = new Intl.Collator('ar', { numeric: true, sensitivity: 'base' });
  class DataTable {
    constructor({ mount, columns, rows, pageSize = 10, searchKeys = [], selectable = false, rowKey = 'id', onSelect, empty = 'لا توجد نتائج مطابقة' }) {
      Object.assign(this, { mount, columns, rows, pageSize, searchKeys, selectable, rowKey, onSelect, empty });
      this.page = 1;
      this.query = '';
      this.filters = {};
      this.sort = null;
      this.selected = new Set();
      this.mount.addEventListener('click', (e) => this.onClick(e));
      this.mount.addEventListener('change', (e) => this.onChange(e));
      this.render();
    }
    setRows(rows) { this.rows = rows; this.selected.clear(); this.render(); this.emit(); }
    setQuery(q) { this.query = q.trim().toLowerCase(); this.page = 1; this.render(); }
    setFilter(key, fn) { if (fn) this.filters[key] = fn; else delete this.filters[key]; this.page = 1; this.render(); }
    get view() {
      let r = this.rows.filter((row) => Object.values(this.filters).every((fn) => fn(row)));
      if (this.query) r = r.filter((row) => this.searchKeys.some((k) => String(row[k] ?? '').toLowerCase().includes(this.query)));
      if (this.sort) {
        const { key, dir } = this.sort;
        r = [...r].sort((a, b) => {
          const x = a[key];
          const y = b[key];
          const c = typeof x === 'number' && typeof y === 'number' ? x - y : collator.compare(String(x ?? ''), String(y ?? ''));
          return c * dir;
        });
      }
      return r;
    }
    emit() { this.onSelect?.([...this.selected]); }
    onClick(e) {
      const th = e.target.closest('th.sortable');
      if (th) {
        const key = th.dataset.key;
        this.sort = this.sort?.key === key ? { key, dir: -this.sort.dir } : { key, dir: 1 };
        this.render();
        return;
      }
      const pg = e.target.closest('[data-page]');
      if (pg && this.mount.contains(pg) && !pg.disabled) {
        this.page = Number(pg.dataset.page);
        this.render();
        this.mount.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
      }
    }
    onChange(e) {
      if (e.target.matches('[data-check-all]')) {
        const ids = this.pageRows.map((r) => String(r[this.rowKey]));
        ids.forEach((id) => (e.target.checked ? this.selected.add(id) : this.selected.delete(id)));
        this.render();
        this.emit();
      } else if (e.target.matches('[data-check]')) {
        const id = e.target.dataset.check;
        e.target.checked ? this.selected.add(id) : this.selected.delete(id);
        e.target.closest('tr').classList.toggle('selected', e.target.checked);
        const all = $('[data-check-all]', this.mount);
        const ids = this.pageRows.map((r) => String(r[this.rowKey]));
        if (all) {
          all.checked = ids.every((i) => this.selected.has(i));
          all.indeterminate = !all.checked && ids.some((i) => this.selected.has(i));
        }
        this.emit();
      }
    }
    render() {
      const view = this.view;
      const pages = Math.max(1, Math.ceil(view.length / this.pageSize));
      this.page = Math.min(this.page, pages);
      const start = (this.page - 1) * this.pageSize;
      this.pageRows = view.slice(start, start + this.pageSize);
      const allChecked = this.pageRows.length && this.pageRows.every((r) => this.selected.has(String(r[this.rowKey])));

      const head = this.columns
        .map((c) => {
          const sorted = this.sort?.key === c.key;
          const arrow = c.sortable ? `<span class="arrow">${sorted ? (this.sort.dir > 0 ? '▲' : '▼') : '▲▼'}</span>` : '';
          return `<th class="${c.sortable ? 'sortable' : ''} ${sorted ? 'sorted' : ''} ${c.className || ''}" data-key="${c.key || ''}" ${c.width ? `style="width:${c.width}"` : ''}>${c.label}${arrow}</th>`;
        })
        .join('');
      const body = this.pageRows.length
        ? this.pageRows
            .map((row) => {
              const id = String(row[this.rowKey]);
              const sel = this.selected.has(id);
              const check = this.selectable ? `<td class="w-check"><input type="checkbox" class="check" data-check="${esc(id)}" ${sel ? 'checked' : ''} aria-label="تحديد"></td>` : '';
              return `<tr class="${sel ? 'selected' : ''}" data-id="${esc(id)}">${check}${this.columns.map((c) => `<td class="${c.className || ''}">${c.render ? c.render(row) : esc(row[c.key])}</td>`).join('')}</tr>`;
            })
            .join('')
        : `<tr><td colspan="${this.columns.length + (this.selectable ? 1 : 0)}"><div class="table-empty">${icon('search')}${this.empty}</div></td></tr>`;

      // compact page list: 1 … 4 5 6 … 10
      const nums = [];
      for (let p = 1; p <= pages; p++) {
        if (p === 1 || p === pages || Math.abs(p - this.page) <= 1) nums.push(p);
        else if (nums.at(-1) !== '…') nums.push('…');
      }
      const pager = nums
        .map((p) => (p === '…' ? '<button disabled>…</button>' : `<button data-page="${p}" class="${p === this.page ? 'on' : ''}">${p}</button>`))
        .join('');

      this.mount.innerHTML = `
        <div class="table-wrap">
          <table class="table">
            <thead><tr>${this.selectable ? `<th class="w-check"><input type="checkbox" class="check" data-check-all ${allChecked ? 'checked' : ''} aria-label="تحديد الكل"></th>` : ''}${head}</tr></thead>
            <tbody>${body}</tbody>
          </table>
        </div>
        <div class="pagination">
          <span>عرض ${view.length ? start + 1 : 0}–${Math.min(start + this.pageSize, view.length)} من ${num(view.length)}</span>
          <div class="pager">
            <button data-page="${this.page - 1}" ${this.page === 1 ? 'disabled' : ''} aria-label="السابق">${icon('chevron-right', 'sm')}</button>
            ${pager}
            <button data-page="${this.page + 1}" ${this.page === pages ? 'disabled' : ''} aria-label="التالي">${icon('chevron-left', 'sm')}</button>
          </div>
        </div>`;
    }
  }

  /* ---------- misc ---------- */
  const STATUS_TONE = {
    'منشورة': 'success', 'منشور': 'success', 'مكتمل': 'success', 'نشط': 'success', 'مفتوحة': 'success', 'مقبول': 'success',
    'مسودة': '', 'غير نشط': '', 'منتهية': '', 'منتهي': '',
    'قيد المراجعة': 'warning', 'بانتظار المراجعة': 'warning', 'معلّق': 'warning', 'مجدول': 'info', 'مكتملة': 'violet',
    'مسترد': 'violet', 'فشل': 'danger', 'موقوف': 'danger', 'مخفي': 'danger', 'مرفوض': 'danger',
  };
  const badge = (status) => `<span class="badge dot ${STATUS_TONE[status] ?? ''}">${esc(status)}</span>`;
  const person = (p) =>
    `<div class="person"><span class="avatar sm" style="background:${p.color || '#0b0d12'}">${esc(p.initial || p.name?.[0] || '?')}</span><div><b>${esc(p.name)}</b>${p.sub ? `<small>${esc(p.sub)}</small>` : ''}</div></div>`;

  // export a CSV file from rows (works when the dashboard is opened locally)
  function downloadCSV(filename, columns, rows) {
    // text starting with = + - @ (or a tab/CR) would run as a formula in Excel: prefix it with ' (numbers stay numbers)
    const cell = (v) => (typeof v === 'string' && /^[=+\-@\t\r]/.test(v) ? `'${v}` : String(v ?? ''));
    const line = (arr) => arr.map((v) => `"${cell(v).replace(/"/g, '""')}"`).join(',');
    const csv = '﻿' + [line(columns.map((c) => c.label)), ...rows.map((r) => line(columns.map((c) => r[c.key])))].join('\n');
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([csv], { type: 'text/csv;charset=utf-8' }));
    a.download = filename;
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    toast(`تم تصدير ${num(rows.length)} صف`);
  }

  // update a sidebar counter after the page changes its data (e.g. a message was read)
  function setNavCount(page, n) {
    const link = $(`.sb-link[data-nav="${page}"]`);
    if (!link) return;
    let c = $('.count', link);
    if (!n) return c?.remove();
    if (!c) {
      c = document.createElement('span');
      c.className = `count${page === 'messages' ? ' hot' : ''}`;
      link.appendChild(c);
    }
    c.textContent = n;
    const label = $('.sb-text', link)?.textContent || '';
    link.dataset.tip = `${label} (${n})`;
  }

  // copy text; falls back to a hidden textarea where the Clipboard API is unavailable
  function copy(text, okMessage = 'تم النسخ') {
    const fallback = () => {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.setAttribute('readonly', '');
      ta.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
      document.body.appendChild(ta);
      ta.select();
      let ok = false;
      try { ok = document.execCommand('copy'); } catch {}
      ta.remove();
      toast(ok ? okMessage : `انسخ يدوياً: ${text}`, ok ? 'success' : 'info');
    };
    if (navigator.clipboard?.writeText) navigator.clipboard.writeText(text).then(() => toast(okMessage), fallback);
    else fallback();
  }

  /* ---------- API: JSON calls to /dashboard/api/v1 with the session cookie + CSRF token ---------- */
  // const { data } = await App.api.get('courses', { page: 2 });
  // await App.api.post('courses', { title }) → on 422 it rejects with err.errors = { title: ['…'] } for the page to show;
  // other failures show a toast and reject too. Files: send FormData with POST (PHP does not parse multipart PUT).
  class ApiError extends Error {
    constructor(status, body) {
      super(body?.message || (status ? `حدث خطأ غير متوقع (${status})، حاول مرة أخرى` : 'تعذّر الاتصال بالخادم'));
      this.name = 'ApiError';
      this.status = status;
      this.errors = body?.errors || {};
    }
  }
  const API_MESSAGES = {
    403: 'ليست لديك صلاحية لهذا الإجراء',
    404: 'العنصر المطلوب غير موجود',
    413: 'الملف أكبر من الحد الذي يقبله الخادم',
    419: 'انتهت صلاحية الجلسة، حدّث الصفحة وحاول مرة أخرى',
    429: 'طلبات كثيرة خلال وقت قصير، انتظر قليلاً ثم حاول',
  };
  // a write that is already on its way (same method, path and data) isn't sent twice: a double click on
  // "send reminder" or "add" rejects the second call quietly (err.duplicate) instead of emailing or creating twice
  const inFlight = new Set();
  async function request(method, path, data) {
    const key = method !== 'GET' && !(data instanceof FormData) ? `${method} ${path} ${JSON.stringify(data ?? null)}` : null;
    if (key && inFlight.has(key)) {
      const dup = new ApiError(0, { message: 'duplicate request' });
      dup.duplicate = true;
      throw dup;
    }
    if (key) inFlight.add(key);
    try {
      return await send(method, path, data);
    } finally {
      if (key) inFlight.delete(key);
    }
  }
  // PHP prints its own warnings (an upload it couldn't store, a misconfigured php.ini) before Laravel's JSON when
  // display_errors is on; the reply is still read, and the warning goes to the console to show what PHP said
  function parseReply(text) {
    if (!text) return null;
    try {
      return JSON.parse(text);
    } catch {
      const start = text.indexOf('{"');
      if (start < 0) return null;
      console.warn('The server printed this before its reply:', text.slice(0, start).replace(/<[^>]+>/g, '').trim());
      try {
        return JSON.parse(text.slice(start));
      } catch {
        return null;
      }
    }
  }
  async function send(method, path, data) {
    const headers = {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest',
      'X-CSRF-TOKEN': $('meta[name="csrf-token"]')?.content || '',
    };
    let body;
    if (data instanceof FormData) body = data;
    else if (data !== undefined) {
      headers['Content-Type'] = 'application/json';
      body = JSON.stringify(data);
    }
    let res;
    try {
      res = await fetch(`${CFG.api}/${String(path).replace(/^\//, '')}`, { method, headers, body, credentials: 'same-origin' });
    } catch {
      toast('تعذّر الاتصال بالخادم، تحقق من اتصالك بالإنترنت', 'error');
      throw new ApiError(0);
    }
    const json = res.status === 204 ? null : parseReply(await res.text().catch(() => ''));
    if (res.ok) return json;
    const err = new ApiError(res.status, json);
    if (res.status === 401) location.href = url('login');
    else if (res.status !== 422) toast(API_MESSAGES[res.status] || 'حدث خطأ غير متوقع، حاول مرة أخرى', 'error');
    throw err;
  }
  // after a 422: mark the inputs named in the errors ({ email: '#iEmail' }), focus the first and toast its message.
  // Returns false for any other failure (App.api already showed a toast for those).
  function showFieldErrors(err, fields = {}) {
    if (!(err instanceof ApiError) || err.status !== 422) return false;
    const keys = Object.keys(err.errors);
    keys.forEach((k) => fields[k] && $(fields[k])?.classList.add('invalid'));
    const first = keys.find((k) => fields[k] && $(fields[k]));
    if (first) $(fields[first]).focus();
    toast(err.errors[first ?? keys[0]]?.[0] || err.message, 'error');
    return true;
  }
  // big photos are scaled down in the browser before they're uploaded (longest side 1920px): faster, and well
  // under PHP's upload limit (2MB by default), which would otherwise reject them with "فشل رفع الصورة"
  async function shrinkImage(file, maxSide = 1920) {
    const LIMIT = 1.8 * 1024 * 1024;
    if (!/^image\/(jpeg|png|webp)$/.test(file?.type)) return file;
    let bitmap;
    try {
      bitmap = await createImageBitmap(file);
    } catch {
      return file;
    }
    const longest = Math.max(bitmap.width, bitmap.height);
    if (longest <= maxSide && file.size <= 1.5 * 1024 * 1024) {
      bitmap.close();
      return file;
    }
    // PNG becomes WebP to keep any transparency
    const type = file.type === 'image/jpeg' ? 'image/jpeg' : 'image/webp';
    const canvas = document.createElement('canvas');
    let best = null;
    // smaller sides and lower qualities until it fits; an already compressed photo may need more than one step
    for (const side of [maxSide, Math.round(maxSide * 0.75), Math.round(maxSide * 0.55)]) {
      const scale = Math.min(1, side / longest);
      canvas.width = Math.round(bitmap.width * scale);
      canvas.height = Math.round(bitmap.height * scale);
      canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
      for (const quality of [0.85, 0.72, 0.6]) {
        const blob = await new Promise((resolve) => canvas.toBlob(resolve, type, quality));
        if (blob && (!best || blob.size < best.size)) best = blob;
        if (best && best.size <= LIMIT) break;
      }
      if (best && best.size <= LIMIT) break;
    }
    bitmap.close();
    if (!best || best.size >= file.size) return file;
    return new File([best], `${file.name.replace(/\.\w+$/, '')}.${type === 'image/jpeg' ? 'jpg' : 'webp'}`, { type });
  }
  // what the signed-in member may do, e.g. App.can('manage_content') — the server enforces it either way
  const can = (permission) => !!USER?.permissions?.[permission];
  const api = {
    get: (path, query) => request('GET', query ? `${path}?${new URLSearchParams(query)}` : path),
    post: (path, data) => request('POST', path, data),
    put: (path, data) => request('PUT', path, data),
    patch: (path, data) => request('PATCH', path, data),
    delete: (path) => request('DELETE', path),
  };

  /* ---------- boot ---------- */
  window.App = { $, $$, esc, num, money, date, debounce, icon, hydrateIcons, toast, openModal, closeModal, confirmDialog, openDrawer, closeDrawer, DataTable, badge, person, downloadCSV, initTabs, setNavCount, copy, url, asset, api, ApiError, showFieldErrors, shrinkImage, user: USER, can, ago, activityTone, siteUrl: String(CFG.site_url || '').replace(/\/$/, '') };

  // no inline handlers (the Content-Security-Policy blocks them): a broken avatar image falls back to the initial,
  // and [data-back] goes to the previous page
  document.addEventListener('error', (e) => e.target.matches?.('.avatar img') && e.target.remove(), true);
  document.addEventListener('click', (e) => e.target.closest('[data-back]') && history.back());

  document.addEventListener('DOMContentLoaded', () => {
    buildLayout();
    // <button data-requires="manage_content"> disappears for members without that permission
    if (USER) $$('[data-requires]').forEach((el) => (el.hidden = !can(el.dataset.requires)));
    hydrateIcons();
    initDropdowns();
    initTabs();
    initModals();
    initFocusTrap();
    initTooltips();
    initNavProgress();
    document.dispatchEvent(new Event('app:ready'));
    reveal();
  });

  // show the page once the layout is built and the fonts are in (capped, so a slow font never blocks)
  function reveal() {
    const fonts = document.fonts?.ready ?? Promise.resolve();
    Promise.race([fonts, new Promise((r) => setTimeout(r, 700))]).then(() => {
      root.classList.remove('app-loading');
      root.classList.add('app-ready');
    });
  }

  // thin progress bar at the top while leaving for another dashboard page
  function initNavProgress() {
    const bar = document.createElement('div');
    bar.className = 'nav-progress';
    document.body.appendChild(bar);
    document.addEventListener('click', (e) => {
      const a = e.target.closest('a[href]');
      if (!a || e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || a.target === '_blank' || a.hasAttribute('download')) return;
      const url = new URL(a.href, location.href);
      if (url.origin !== location.origin || (url.pathname === location.pathname && url.search === location.search)) return;
      bar.classList.add('go');
    });
    // coming back through the browser cache must not leave the bar running
    addEventListener('pageshow', (e) => e.persisted && bar.classList.remove('go'));
  }
})();
