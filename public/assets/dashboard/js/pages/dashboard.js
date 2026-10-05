document.addEventListener('app:ready', async () => {
  const { $, esc, num, money, badge, person } = App;
  const COLORS = ['#0066ff', '#7c3aed', '#334155', '#0e9f6e', '#c27803', '#0891b2'];
  const MIX_COLORS = { courses: '#0066ff', workshops: '#0891b2', pro: '#7c3aed', services: '#0b0d12' };
  const STAGE_COLORS = { new: '#0066ff', contacted: '#0891b2', proposal: '#c27803', won: '#0e9f6e', lost: '#e02424' };
  const MONTHS = ['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'];

  // greeting by time of day + today's date
  const h = new Date().getHours();
  $('#greeting').textContent = `${h < 12 ? 'صباح الخير' : 'مساء الخير'}، ${(App.user?.name || '').split(' ')[0]}`;
  $('#today').textContent = `${new Date().toLocaleDateString('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · إليك ملخص أداء المنصة.`;

  // what the team did lately, from the activity log
  App.api
    .get('activity', { limit: 6 })
    .then(({ data }) => {
      $('#activity').innerHTML =
        data.map((a) => `<li><span class="t-dot" style="background:${App.activityTone(a.action)}"></span><b>${esc(a.user)}</b><p>${esc(a.description)}</p><time>${esc(App.ago(a.at))}</time></li>`).join('') ||
        '<li class="muted" style="list-style:none">لا يوجد نشاط بعد</li>';
    })
    .catch(() => {});

  let d;
  try {
    d = (await App.api.get('dashboard')).data;
  } catch {
    return;
  }

  /* ---------- KPIs: this month so far vs the same days of last month ---------- */
  const trend = (k) =>
    k.change === null ? '<span class="trend">—</span>' : `<span class="trend ${k.change >= 0 ? 'up' : 'down'}">${k.change >= 0 ? '+' : ''}${k.change}%</span>`;
  const vs = `عن نفس الفترة من ${d.previous_month}`;
  $('#kpiRevenueLabel').textContent = `إيرادات ${d.month}`;
  $('#kpiRevenue').textContent = money(d.kpis.revenue.value);
  $('#kpiRevenueNote').innerHTML = `${trend(d.kpis.revenue)} ${vs}`;
  $('#kpiStudents').textContent = num(d.kpis.students.value);
  $('#kpiStudentsNote').innerHTML = `${trend(d.kpis.students)} هذا الشهر`;
  $('#kpiOrders').textContent = num(d.kpis.orders.value);
  $('#kpiOrdersNote').innerHTML = `${trend(d.kpis.orders)} هذا الشهر`;
  $('#kpiCompletion').textContent = `${d.kpis.completion}%`;
  $('#kpiLessonsNote').innerHTML = `${num(d.kpis.lessons.value)} درساً أُنجز هذا الشهر`;
  document.querySelectorAll('[data-spark]').forEach((el) => Charts.spark(el, d.kpis[el.dataset.spark].spark, el.dataset.color));

  /* ---------- revenue: courses & workshops (paid orders) vs development services (won requests) ---------- */
  function drawRevenue(n) {
    const labels = d.revenue.labels.slice(-n);
    const courses = d.revenue.courses.slice(-n);
    const services = d.revenue.services.slice(-n);
    Charts.bars($('#revenueChart'), {
      labels,
      stacked: true,
      series: [
        { name: 'الدورات والورش', color: '#0066ff', data: courses },
        { name: 'خدمات التطوير', color: '#0b0d12', data: services },
      ],
      format: (v) => money(v),
    });
    const sum = (a) => a.reduce((x, y) => x + y, 0);
    $('#revTotal').textContent = money(sum(courses) + sum(services));
    $('#revCourses').textContent = money(sum(courses));
    $('#revServices').textContent = money(sum(services));
  }
  drawRevenue(12);
  $('#revRange').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $('#revRange').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
    drawRevenue(Number(b.dataset.range));
  });

  /* ---------- where the last 30 days' revenue came from ---------- */
  const mixTotal = d.sales_mix.reduce((a, m) => a + m.value, 0);
  const share = (v) => (mixTotal ? Math.round((v / mixTotal) * 100) : 0);
  Charts.donut($('#mixChart'), {
    items: mixTotal ? d.sales_mix.filter((m) => m.value).map((m) => ({ label: m.label, value: share(m.value), color: MIX_COLORS[m.key] })) : [],
    centerValue: money(mixTotal),
    centerLabel: 'آخر 30 يوماً',
  });
  $('#mixList').innerHTML = d.sales_mix
    .map((m) => `<div class="list-item" style="padding:8px 0;border:0"><i style="width:10px;height:10px;border-radius:3px;background:${MIX_COLORS[m.key]}"></i><span class="grow">${esc(m.label)}</span><span class="muted num" style="font-size:12.5px">${money(m.value)}</span><b style="min-width:42px;text-align:end">${share(m.value)}%</b></div>`)
    .join('');

  /* ---------- recent orders ---------- */
  $('#recentOrders').innerHTML =
    d.recent_orders
      .map(
        (o) =>
          `<tr><td class="mono"><a href="${App.url('orders', { q: o.number })}">${esc(o.number)}</a></td><td>${person({ name: o.student.name, initial: o.student.initial, color: COLORS[o.student.id % COLORS.length] })}</td><td>${esc(o.item_name)}</td><td class="num">${money(o.total)}</td><td>${badge(o.status_label)}</td></tr>`,
      )
      .join('') || '<tr><td colspan="5" class="muted" style="text-align:center">لا توجد طلبات بعد</td></tr>';

  /* ---------- top courses by revenue ---------- */
  const maxRev = d.top_courses[0]?.revenue || 1;
  $('#topCourses').innerHTML =
    d.top_courses
      .map(
        (c) => `
      <a class="list-item" href="${App.url('course-edit', { id: c.id })}">
        <span class="thumb">${esc(c.glyph)}</span>
        <div class="grow">
          <b>${esc(c.title)}</b>
          <div class="progress" style="margin-top:6px"><i style="width:${(c.revenue / maxRev) * 100}%"></i></div>
        </div>
        <b class="num">${money(c.revenue)}</b>
      </a>`,
      )
      .join('') || '<div class="list-item muted">لا توجد مبيعات بعد</div>';

  /* ---------- upcoming workshops ---------- */
  $('#upcomingWorkshops').innerHTML =
    d.upcoming_workshops
      .map((w) => {
        const pct = Math.min(100, Math.round((w.taken / w.seats) * 100));
        const [, m, day] = w.date.split('-').map(Number);
        return `
      <div class="list-item">
        <span class="kpi-ico c-ink" style="flex-direction:column;line-height:1.1;font-weight:800;width:46px;height:50px;display:flex;align-items:center;justify-content:center"><span style="font-size:17px">${day}</span><small style="font-size:10px;color:#c9d1dd">${MONTHS[m]}</small></span>
        <div class="grow">
          <b>${esc(w.title)}</b>
          <small>${w.taken}/${w.seats} مقعداً · ${esc(w.format_label)}</small>
          <div class="progress ${pct >= 100 ? 'red' : pct > 75 ? 'amber' : ''}" style="margin-top:6px"><i style="width:${pct}%"></i></div>
        </div>
      </div>`;
      })
      .join('') || '<div class="list-item muted">لا توجد ورش قادمة</div>';

  /* ---------- project requests by stage ---------- */
  const leadsTotal = d.pipeline.stages.reduce((a, s) => a + s.count, 0) || 1;
  $('#pipeline').innerHTML = `
    <div class="mini-stat" style="margin-bottom:16px"><b>${money(d.pipeline.value)}</b><small>قيمة الطلبات المفتوحة والمقبولة</small></div>
    ${d.pipeline.stages
      .map(
        (s) => `<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
          <span style="width:110px;font-size:13px;font-weight:700;color:var(--fg)">${esc(s.label)}</span>
          <div class="progress" style="flex:1"><i style="width:${(s.count / leadsTotal) * 100}%;background:${STAGE_COLORS[s.key]}"></i></div>
          <b class="num" style="width:20px;text-align:center">${s.count}</b>
        </div>`,
      )
      .join('')}`;

  $('#exportReport').addEventListener('click', () =>
    App.downloadCSV(
      'revenue-report.csv',
      [
        { key: 'month', label: 'الشهر' },
        { key: 'courses', label: 'الدورات والورش' },
        { key: 'services', label: 'خدمات التطوير' },
      ],
      d.revenue.labels.map((m, i) => ({ month: m, courses: d.revenue.courses[i], services: d.revenue.services[i] })),
    ),
  );
});
