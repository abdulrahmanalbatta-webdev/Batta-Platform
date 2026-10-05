document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, money, icon, api } = App;
  const METHOD_COLORS = ['#0066ff', '#0b0d12', '#0e9f6e', '#c27803', '#7c3aed'];
  const PERIODS = { 7: 'آخر 7 أيام', 30: 'آخر 30 يوماً', 90: 'آخر 90 يوماً', 365: 'آخر 12 شهراً' };
  let report;
  let metric = 'revenue';

  const trend = (k) =>
    k.change === null ? '<span class="trend">—</span> لا توجد فترة سابقة للمقارنة' : `<span class="trend ${k.change >= 0 ? 'up' : 'down'}">${k.change >= 0 ? '+' : ''}${k.change}%</span> عن الفترة السابقة`;

  function kpis() {
    const k = report.kpis;
    $('#kpis').innerHTML = [
      ['dollar', 'c-blue', 'الإيرادات', money(k.revenue.value), k.revenue],
      ['cart', 'c-green', 'الطلبات المكتملة', num(k.orders.value), k.orders],
      ['users', 'c-violet', 'طلاب جدد', num(k.students.value), k.students],
      ['tag', 'c-amber', 'متوسط قيمة الطلب', money(k.average_order.value), k.average_order],
    ]
      .map(([ic, tone, label, value, kpi]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${label}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${value}</div><div class="kpi-note">${trend(kpi)}</div></div>`)
      .join('');
  }

  function series() {
    const isRevenue = metric === 'revenue';
    const s = report.series;
    $('#seriesTitle').textContent = isRevenue ? 'الإيرادات' : 'الطلاب الجدد';
    $('#seriesNote').textContent = `${PERIODS[report.days]} · ${report.days > 90 ? 'شهرياً' : 'يومياً'}`;
    Charts.line($('#seriesChart'), {
      labels: s.labels,
      xEvery: Math.max(1, Math.ceil(s.labels.length / 10)),
      height: 300,
      series: [{ name: isRevenue ? 'الإيرادات' : 'طلاب جدد', color: isRevenue ? '#0066ff' : '#7c3aed', data: s[metric], area: true }],
      ...(isRevenue ? { format: (v) => money(v) } : {}),
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

  function methods() {
    const total = report.payment_methods.reduce((a, m) => a + m.value, 0);
    const items = report.payment_methods.map((m, i) => ({ label: m.label, value: Math.round((m.value / total) * 100), color: METHOD_COLORS[i % METHOD_COLORS.length] }));
    Charts.donut($('#methodsChart'), { items, centerValue: num(total), centerLabel: 'طلب مكتمل' });
    $('#methodsLegend').innerHTML = items.map((m) => `<span><i style="background:${m.color}"></i>${esc(m.label)} <bdi>${m.value}%</bdi></span>`).join('') || '<span class="muted">لا توجد طلبات في هذه الفترة</span>';
  }

  function products() {
    const max = report.top_products[0]?.revenue || 1;
    $('#topProducts').innerHTML =
      report.top_products
        .map(
          (p) => `<tr>
        <td><b style="color:var(--fg)">${esc(p.name)}</b><div class="muted" style="font-size:12px">${esc(p.type_label)}</div></td>
        <td class="num">${num(p.orders)}</td>
        <td class="num">${money(p.revenue)}</td>
        <td><div class="progress"><i style="width:${(p.revenue / max) * 100}%"></i></div></td>
      </tr>`,
        )
        .join('') || '<tr><td colspan="4" class="muted" style="text-align:center">لا توجد مبيعات في هذه الفترة</td></tr>';
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
        .join('') || '<p class="muted">لا يوجد مشترون في هذه الفترة</p>';
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
    $('#periodNote').textContent = `المبيعات والطلاب خلال ${PERIODS[days]}، مقارنة بالفترة التي قبلها.`;
    kpis();
    series();
    funnel();
    methods();
    products();
    countries();
  }

  $('#period').addEventListener('change', (e) => load(Number(e.target.value)));
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
      `sales-${report.days}d.csv`,
      [
        { key: 'label', label: report.days > 90 ? 'الشهر' : 'اليوم' },
        { key: 'revenue', label: 'الإيرادات' },
        { key: 'students', label: 'طلاب جدد' },
      ],
      report.series.labels.map((label, i) => ({ label, revenue: report.series.revenue[i], students: report.series.students[i] })),
    );
  });

  load(30);
});
