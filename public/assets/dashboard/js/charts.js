/* ==========================================================================
   Charts — رسوم بيانية SVG مكتوبة يدوياً (بدون مكتبات)
   Charts.line(el, opts) · Charts.bars(el, opts) · Charts.donut(el, opts) · Charts.spark(el, data, color)
   ========================================================================== */
(function () {
  'use strict';
  // labels and names may come from the database: escape them before they go into the SVG/HTML
  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
  const NS = 'http://www.w3.org/2000/svg';
  const fmt = (n) => Number(n).toLocaleString('en-US');

  // "nice" axis maximum + ticks
  function niceScale(max, ticks = 4) {
    if (max <= 0) return { max: 1, step: 0.25 };
    const raw = max / ticks;
    const pow = Math.pow(10, Math.floor(Math.log10(raw)));
    const step = [1, 1.5, 2, 2.5, 3, 4, 5, 6, 8, 10].map((m) => m * pow).find((s) => s >= raw);
    return { max: step * ticks, step };
  }

  function tooltip(wrap) {
    let tip = wrap.querySelector('.chart-tip');
    if (!tip) {
      tip = document.createElement('div');
      tip.className = 'chart-tip';
      wrap.appendChild(tip);
    }
    return tip;
  }

  // draw at the real pixel width so text stays readable on phones
  const chartWidth = (el) => Math.max(300, Math.min(1200, Math.round(el.clientWidth || 720)));

  /* ---------- line / area chart ---------- */
  function line(el, { labels, series, height = 280, format = fmt, xEvery = 1 }) {
    el.classList.add('chart-wrap');
    const W = chartWidth(el);
    const H = Math.min(height, Math.round(W * 0.62));
    const pad = { t: 16, r: 12, b: 34, l: W < 500 ? 54 : 70 };
    const iw = W - pad.l - pad.r;
    const ih = H - pad.t - pad.b;
    const allMax = Math.max(...series.flatMap((s) => s.data));
    const { max, step } = niceScale(allMax * 1.06);
    // RTL: first label on the right
    const x = (i) => pad.l + iw - (i / (labels.length - 1)) * iw;
    const y = (v) => pad.t + ih - (v / max) * ih;

    let grid = '';
    for (let v = 0; v <= max + 0.0001; v += step) {
      grid += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(v)}" y2="${y(v)}" stroke="#e2e7ef" stroke-dasharray="${v === 0 ? '' : '4 5'}"/>`;
      grid += `<text x="${pad.l - 10}" y="${y(v) + 4}" text-anchor="end" font-size="11" fill="#8a94a3">${format(v)}</text>`;
    }
    const xLabels = labels
      .map((l, i) => (i % Math.max(xEvery, Math.ceil(labels.length / Math.max(2, Math.floor(iw / 44)))) === 0 ? `<text x="${x(i)}" y="${H - 10}" text-anchor="middle" font-size="11" fill="#8a94a3">${esc(l)}</text>` : ''))
      .join('');

    const paths = series
      .map((s, si) => {
        const pts = s.data.map((v, i) => [x(i), y(v)]);
        // smooth curve (monotone-ish cubic)
        let d = `M${pts[0][0]},${pts[0][1]}`;
        for (let i = 1; i < pts.length; i++) {
          const [x0, y0] = pts[i - 1];
          const [x1, y1] = pts[i];
          const cx = (x0 + x1) / 2;
          d += ` C${cx},${y0} ${cx},${y1} ${x1},${y1}`;
        }
        const gid = `g${Math.random().toString(36).slice(2, 8)}`;
        const area = s.area
          ? `<defs><linearGradient id="${gid}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${s.color}" stop-opacity="0.22"/><stop offset="1" stop-color="${s.color}" stop-opacity="0"/></linearGradient></defs>
             <path d="${d} L${pts.at(-1)[0]},${pad.t + ih} L${pts[0][0]},${pad.t + ih} Z" fill="url(#${gid})"/>`
          : '';
        return `${area}<path d="${d}" fill="none" stroke="${s.color}" stroke-width="${si === 0 ? 2.6 : 2}" stroke-linecap="round" ${s.dashed ? 'stroke-dasharray="6 6"' : ''}/>`;
      })
      .join('');

    el.innerHTML = `
      <svg class="chart" viewBox="0 0 ${W} ${H}" direction="ltr" role="img" aria-label="${series.map((s) => s.name).join('، ')}">
        ${grid}${xLabels}${paths}
        <line class="hover-line" x1="0" x2="0" y1="${pad.t}" y2="${pad.t + ih}" stroke="#0b0d12" stroke-opacity="0.15" stroke-width="1.5" visibility="hidden"/>
        ${series.map((s, si) => `<circle class="hover-dot" data-s="${si}" r="5" fill="#fff" stroke="${s.color}" stroke-width="2.5" visibility="hidden"/>`).join('')}
        <rect x="${pad.l}" y="${pad.t}" width="${iw}" height="${ih}" fill="transparent" class="hit"/>
      </svg>`;

    const svg = el.querySelector('svg');
    const tip = tooltip(el);
    const hline = svg.querySelector('.hover-line');
    const dots = [...svg.querySelectorAll('.hover-dot')];
    svg.querySelector('.hit').addEventListener('mousemove', (e) => {
      const r = svg.getBoundingClientRect();
      const px = ((e.clientX - r.left) / r.width) * W;
      const i = Math.max(0, Math.min(labels.length - 1, Math.round(((pad.l + iw - px) / iw) * (labels.length - 1))));
      hline.setAttribute('x1', x(i));
      hline.setAttribute('x2', x(i));
      hline.setAttribute('visibility', 'visible');
      dots.forEach((d, si) => {
        d.setAttribute('cx', x(i));
        d.setAttribute('cy', y(series[si].data[i]));
        d.setAttribute('visibility', 'visible');
      });
      tip.innerHTML = `<small>${esc(labels[i])}</small>${series.map((s) => `<div><span style="color:${s.color}">●</span> ${esc(s.name)}: <b>${esc(format(s.data[i]))}</b></div>`).join('')}`;
      tip.style.left = `${(x(i) / W) * r.width}px`;
      tip.style.top = `${(Math.min(...series.map((s) => y(s.data[i]))) / H) * r.height}px`;
      tip.classList.add('show');
    });
    svg.querySelector('.hit').addEventListener('mouseleave', () => {
      tip.classList.remove('show');
      hline.setAttribute('visibility', 'hidden');
      dots.forEach((d) => d.setAttribute('visibility', 'hidden'));
    });
  }

  /* ---------- bar chart (grouped or stacked) ---------- */
  function bars(el, { labels, series, height = 260, stacked = false, format = fmt }) {
    el.classList.add('chart-wrap');
    const W = chartWidth(el);
    const H = Math.min(height, Math.round(W * 0.62));
    const pad = { t: 16, r: 12, b: 34, l: W < 500 ? 54 : 70 };
    const iw = W - pad.l - pad.r;
    const ih = H - pad.t - pad.b;
    const totals = labels.map((_, i) => (stacked ? series.reduce((a, s) => a + s.data[i], 0) : Math.max(...series.map((s) => s.data[i]))));
    const { max, step } = niceScale(Math.max(...totals));
    const band = iw / labels.length;
    const bw = Math.min(38, band * (stacked ? 0.5 : 0.7));
    const y = (v) => pad.t + ih - (v / max) * ih;

    let grid = '';
    for (let v = 0; v <= max + 0.0001; v += step) {
      grid += `<line x1="${pad.l}" x2="${W - pad.r}" y1="${y(v)}" y2="${y(v)}" stroke="#e2e7ef" stroke-dasharray="${v === 0 ? '' : '4 5'}"/>`;
      grid += `<text x="${pad.l - 10}" y="${y(v) + 4}" text-anchor="end" font-size="11" fill="#8a94a3">${format(v)}</text>`;
    }
    let rects = '';
    labels.forEach((l, i) => {
      const cx = pad.l + iw - band * i - band / 2; // RTL
      if (stacked) {
        let acc = 0;
        series.forEach((s, si) => {
          const v = s.data[i];
          const top = y(acc + v);
          const h = y(acc) - top;
          const isTop = si === series.length - 1;
          rects += `<rect x="${cx - bw / 2}" y="${top}" width="${bw}" height="${Math.max(0, h)}" fill="${s.color}" ${isTop ? 'rx="5"' : ''} data-i="${i}"/>`;
          acc += v;
        });
      } else {
        const gw = bw / series.length;
        series.forEach((s, si) => {
          const v = s.data[i];
          rects += `<rect x="${cx - bw / 2 + gw * si + 1}" y="${y(v)}" width="${gw - 2}" height="${y(0) - y(v)}" rx="4" fill="${s.color}" data-i="${i}"/>`;
        });
      }
      if (i % Math.ceil(labels.length / Math.max(2, Math.floor(iw / 50))) === 0) rects += `<text x="${cx}" y="${H - 10}" text-anchor="middle" font-size="11" fill="#8a94a3">${esc(l)}</text>`;
      rects += `<rect class="col" x="${cx - band / 2}" y="${pad.t}" width="${band}" height="${ih}" fill="transparent" data-i="${i}"/>`;
    });

    el.innerHTML = `<svg class="chart" viewBox="0 0 ${W} ${H}" direction="ltr" role="img" aria-label="${series.map((s) => s.name).join('، ')}">${grid}${rects}</svg>`;
    const svg = el.querySelector('svg');
    const tip = tooltip(el);
    svg.querySelectorAll('.col').forEach((c) => {
      c.addEventListener('mouseenter', () => {
        const i = Number(c.dataset.i);
        const r = svg.getBoundingClientRect();
        const cx = pad.l + iw - band * i - band / 2;
        c.setAttribute('fill', 'rgba(11,13,18,0.04)');
        tip.innerHTML = `<small>${esc(labels[i])}</small>${series.map((s) => `<div><span style="color:${s.color}">●</span> ${esc(s.name)}: <b>${esc(format(s.data[i]))}</b></div>`).join('')}${stacked ? `<div>المجموع: <b>${esc(format(totals[i]))}</b></div>` : ''}`;
        tip.style.left = `${(cx / W) * r.width}px`;
        tip.style.top = `${(y(totals[i]) / H) * r.height}px`;
        tip.classList.add('show');
      });
      c.addEventListener('mouseleave', () => {
        c.setAttribute('fill', 'transparent');
        tip.classList.remove('show');
      });
    });
  }

  /* ---------- donut ---------- */
  function donut(el, { items, size = 200, thickness = 26, centerLabel = '', centerValue = '' }) {
    el.classList.add('chart-wrap');
    const total = items.reduce((a, b) => a + b.value, 0);
    const r = (size - thickness) / 2;
    const c = 2 * Math.PI * r;
    let offset = 0;
    const arcs = items
      .map((it) => {
        const len = (it.value / total) * c;
        const s = `<circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" stroke="${it.color}" stroke-width="${thickness}" stroke-dasharray="${len - 2} ${c - len + 2}" stroke-dashoffset="${-offset}" transform="rotate(-90 ${size / 2} ${size / 2})"><title>${esc(it.label)}: ${esc(it.value)}%</title></circle>`;
        offset += len;
        return s;
      })
      .join('');
    el.innerHTML = `
      <svg class="chart" viewBox="0 0 ${size} ${size}" style="max-width:${size}px;margin-inline:auto" role="img" aria-label="${esc(centerLabel)}">
        <circle cx="${size / 2}" cy="${size / 2}" r="${r}" fill="none" stroke="#edf1f8" stroke-width="${thickness}"/>
        ${arcs}
        <text x="${size / 2}" y="${size / 2 - 2}" text-anchor="middle" font-size="26" font-weight="800" fill="#0b0d12">${esc(centerValue)}</text>
        <text x="${size / 2}" y="${size / 2 + 20}" text-anchor="middle" font-size="12" fill="#8a94a3">${esc(centerLabel)}</text>
      </svg>`;
  }

  /* ---------- sparkline ---------- */
  function spark(el, data, color = '#0066ff') {
    const W = 110;
    const H = 36;
    const min = Math.min(...data);
    const max = Math.max(...data);
    const x = (i) => W - (i / (data.length - 1)) * W; // RTL
    const y = (v) => H - 3 - ((v - min) / (max - min || 1)) * (H - 6);
    const d = data.map((v, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(v).toFixed(1)}`).join(' ');
    el.innerHTML = `<svg class="spark" viewBox="0 0 ${W} ${H}" aria-hidden="true">
      <path d="${d} L0,${H} L${W},${H} Z" fill="${color}" fill-opacity="0.1"/>
      <path d="${d}" fill="none" stroke="${color}" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
      <circle cx="${x(data.length - 1)}" cy="${y(data.at(-1))}" r="3" fill="${color}"/>
    </svg>`;
  }

  // re-draw line/bar charts when their container width changes (sidebar toggle, rotation, resize)
  const responsive = (fn) => (el, opts) => {
    el.__opts = opts;
    fn(el, opts);
    if (el.__ro || !window.ResizeObserver) return;
    let w = el.clientWidth;
    el.__ro = new ResizeObserver(() => {
      if (Math.abs(el.clientWidth - w) < 24) return;
      w = el.clientWidth;
      fn(el, el.__opts);
    });
    el.__ro.observe(el);
  };

  window.Charts = { line: responsive(line), bars: responsive(bars), donut, spark };
})();
