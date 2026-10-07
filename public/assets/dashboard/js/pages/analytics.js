document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, icon, api } = App;
  const SPLIT_COLORS = ['#0066ff', '#0891b2'];
  const PERIODS = { 7: 'آخر 7 أيام', 30: 'آخر 30 يوماً', 90: 'آخر 90 يوماً', 365: 'آخر 12 شهراً' };
  let report;
  let metric = 'registrations';

  const trend = (k) =>
    k.change === null ? '<span class="trend">—</span> لا توجد فترة سابقة للمقارنة' : `<span class="trend ${k.change >= 0 ? 'up' : 'down'}">${k.change >= 0 ? '+' : ''}${k.change}%</span> عن الفترة السابقة`;

  function kpis() {
    const k = report.kpis;
    $('#kpis').innerHTML = [
      ['award', 'c-blue', 'كل التسجيلات', num(k.registrations.value), k.registrations],
      ['play', 'c-green', 'في الدورات', num(k.enrollments.value), k.enrollments],
      ['calendar', 'c-amber', 'في الورش', num(k.workshop_registrations.value), k.workshop_registrations],
      ['users', 'c-violet', 'طلاب جدد', num(k.students.value), k.students],
    ]
      .map(([ic, tone, label, value, kpi]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${label}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${value}</div><div class="kpi-note">${trend(kpi)}</div></div>`)
      .join('');
  }

  function series() {
    const isRegistrations = metric === 'registrations';
    const s = report.series;
    $('#seriesTitle').textContent = isRegistrations ? 'التسجيلات' : 'الطلاب الجدد';
    $('#seriesNote').textContent = `${PERIODS[report.days]} · ${report.days > 90 ? 'شهرياً' : 'يومياً'}`;
    Charts.line($('#seriesChart'), {
      labels: s.labels,
      xEvery: Math.max(1, Math.ceil(s.labels.length / 10)),
      height: 300,
      series: [{ name: isRegistrations ? 'تسجيلات' : 'طلاب جدد', color: isRegistrations ? '#0066ff' : '#7c3aed', data: s[metric], area: true }],
    });
  }

  // each step as wide as its share of the first one
  function funnel() {
    const first = report.funnel[0].value || 1;
    $('#funnel').innerHTML = report.funnel
      .map((step, i) => {
        const prev = report.funnel[i - 1]?.value;
        const kept = i && prev ? `· ${Math.round((step.value / prev) * 100)}% من الخطوة السابقة` : '';
        return `
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
          <b style="color:var(--fg)">${esc(step.label)}</b>
          <span><b class="num" style="color:var(--fg)">${num(step.value)}</b> <span class="muted">${kept}</span></span>
        </div>
        <div style="height:30px;border-radius:8px;background:var(--tint);overflow:hidden">
          <div style="height:100%;width:${Math.max((step.value / first) * 100, step.value ? 2 : 0)}%;background:linear-gradient(90deg,#0052cc,#0066ff);border-radius:8px;opacity:${1 - i * 0.12}"></div>
        </div>
      </div>`;
      })
      .join('');
  }

  function split() {
    const total = report.split.reduce((a, m) => a + m.value, 0);
    const items = total ? report.split.filter((m) => m.value).map((m, i) => ({ label: m.label, value: Math.round((m.value / total) * 100), color: SPLIT_COLORS[i % SPLIT_COLORS.length] })) : [];
    Charts.donut($('#splitChart'), { items, centerValue: num(total), centerLabel: 'تسجيل' });
    $('#splitLegend').innerHTML = items.map((m) => `<span><i style="background:${m.color}"></i>${esc(m.label)} <bdi>${m.value}%</bdi></span>`).join('') || '<span class="muted">لا توجد تسجيلات في هذه الفترة</span>';
  }

  function items() {
    const max = report.top_items[0]?.registrations || 1;
    $('#topItems').innerHTML =
      report.top_items
        .map(
          (p) => `<tr>
        <td><b style="color:var(--fg)">${esc(p.name)}</b><div class="muted" style="font-size:12px">${esc(p.type_label)}</div></td>
        <td class="num">${num(p.registrations)}</td>
        <td><div class="progress"><i style="width:${(p.registrations / max) * 100}%"></i></div></td>
      </tr>`,
        )
        .join('') || '<tr><td colspan="3" class="muted" style="text-align:center">لا توجد تسجيلات في هذه الفترة</td></tr>';
  }

  function countries() {
    const max = Math.max(...report.countries.map((c) => c.value), 1);
    $('#countries').innerHTML =
      report.countries
        .map(
          (c) => `<div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:5px"><span style="color:var(--fg);font-weight:700">${esc(c.label)}</span><b class="num">${c.value}%</b></div>
        <div class="progress"><i style="width:${(c.value / max) * 100}%"></i></div>
      </div>`,
        )
        .join('') || '<p class="muted">لا يوجد مسجّلون في هذه الفترة</p>';
  }

  /* ---------- site traffic (Google Analytics): loaded on its own so a slow or failing Google never holds up the registrations ---------- */
  const DEVICE_COLORS = ['#0066ff', '#7c3aed', '#0e9f6e', '#c27803'];
  let trafficRequest = 0;

  function trafficEmpty(title, text, tone) {
    $('#traffic').hidden = true;
    $('#trafficEmpty').hidden = false;
    $('#trafficEmptyIcon').className = `kpi-ico ${tone}`;
    $('#trafficEmptyIcon').innerHTML = icon(tone === 'c-red' ? 'alert' : 'chart');
    $('#trafficEmptyTitle').textContent = title;
    $('#trafficEmptyText').innerHTML = text;
  }

  function renderTraffic(t) {
    $('#trafficEmpty').hidden = true;
    $('#traffic').hidden = false;
    const k = t.kpis;
    $('#trafficKpis').innerHTML = [
      ['users', 'c-blue', 'الزوار', num(k.users.value), k.users],
      ['monitor', 'c-violet', 'الجلسات', num(k.sessions.value), k.sessions],
      ['eye', 'c-green', 'مشاهدات الصفحات', num(k.views.value), k.views],
      ['trend', 'c-amber', 'معدل التفاعل', `<bdi>${k.engagement.value}%</bdi>`, k.engagement],
    ]
      .map(([ic, tone, label, value, kpi]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${label}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${value}</div><div class="kpi-note">${trend(kpi)}</div></div>`)
      .join('');

    $('#trafficSeriesNote').textContent = `${PERIODS[t.days]} · ${t.days > 90 ? 'شهرياً' : 'يومياً'}`;
    Charts.line($('#trafficChart'), {
      labels: t.series.labels,
      xEvery: Math.max(1, Math.ceil(t.series.labels.length / 10)),
      height: 280,
      series: [
        { name: 'الزوار', color: '#0066ff', data: t.series.users, area: true },
        { name: 'الجلسات', color: '#7c3aed', data: t.series.sessions },
      ],
    });

    $('#trafficSources').innerHTML =
      t.sources
        .map(
          (s) => `<div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:5px"><span style="color:var(--fg);font-weight:700">${esc(s.label)}</span><span><b class="num">${s.value}%</b> <span class="muted num">· ${num(s.sessions)}</span></span></div>
        <div class="progress"><i style="width:${s.value}%"></i></div>
      </div>`,
        )
        .join('') || '<p class="muted">لا توجد زيارات في هذه الفترة</p>';

    const devices = t.devices.map((d, i) => ({ label: d.label, value: d.value, color: DEVICE_COLORS[i % DEVICE_COLORS.length] }));
    Charts.donut($('#devicesChart'), { items: devices, centerValue: num(k.sessions.value), centerLabel: 'جلسة' });
    $('#devicesLegend').innerHTML = devices.map((d) => `<span><i style="background:${d.color}"></i>${esc(d.label)} <bdi>${d.value}%</bdi></span>`).join('') || '<span class="muted">لا توجد زيارات في هذه الفترة</span>';

    $('#trafficPages').innerHTML =
      t.pages
        .map(
          (p) => `<div style="display:flex;justify-content:space-between;gap:10px;padding:8px 0;border-bottom:1px solid var(--line)">
        <div style="min-width:0"><b style="color:var(--fg);display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(p.title || p.path)}</b>
        <a class="muted mono ltr" style="font-size:12px" href="${esc(App.siteUrl + p.path)}" target="_blank" rel="noopener">${esc(p.path)}</a></div>
        <b class="num" style="color:var(--fg)">${num(p.views)}</b>
      </div>`,
        )
        .join('') || '<p class="muted">لا توجد زيارات في هذه الفترة</p>';
  }

  async function loadTraffic(days) {
    const request = ++trafficRequest;
    $('#trafficNote').textContent = 'جارٍ التحميل من Google Analytics…';
    let res;
    try {
      res = await api.get('analytics/traffic', { days });
    } catch {
      return;
    }
    if (request !== trafficRequest) return; // the period changed meanwhile
    $('#trafficNote').textContent = 'من Google Analytics، لنفس الفترة. تُحدَّث كل ساعة.';
    if (!res.meta.configured) {
      const how = App.can('manage_settings') ? `اربطه من <a href="${App.url('settings', {}, 'analytics')}">الإعدادات ← الإحصاءات</a>.` : 'اطلب من مالك المنصة أو المدير ربطه من الإعدادات.';
      return trafficEmpty('Google Analytics غير مربوط', `لعرض الزوار ومصادرهم وأجهزتهم والصفحات الأكثر زيارة. ${how}`, 'c-amber');
    }
    if (res.meta.error) return trafficEmpty('تعذّر جلب الزيارات', esc(res.meta.error), 'c-red');
    renderTraffic(res.data);
  }

  async function load(days) {
    $('#period').disabled = true;
    try {
      report = (await api.get('analytics', { days })).data;
    } catch {
      return;
    } finally {
      $('#period').disabled = false;
    }
    $('#periodNote').textContent = `التسجيلات والطلاب خلال ${PERIODS[days]}، مقارنة بالفترة التي قبلها.`;
    kpis();
    series();
    funnel();
    split();
    items();
    countries();
  }

  $('#period').addEventListener('change', (e) => {
    load(Number(e.target.value));
    loadTraffic(Number(e.target.value));
  });
  $('#metric').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b || !report) return;
    metric = b.dataset.m;
    $$('#metric button').forEach((x) => x.classList.toggle('on', x === b));
    series();
  });
  $('#exportReport').addEventListener('click', () => {
    if (!report) return;
    App.downloadCSV(
      `registrations-${report.days}d.csv`,
      [
        { key: 'label', label: report.days > 90 ? 'الشهر' : 'اليوم' },
        { key: 'registrations', label: 'التسجيلات' },
        { key: 'students', label: 'طلاب جدد' },
      ],
      report.series.labels.map((label, i) => ({ label, registrations: report.series.registrations[i], students: report.series.students[i] })),
    );
  });

  load(30);
  loadTraffic(30);
});
