document.addEventListener('app:ready', () => {
  const { $, esc, num, money, badge, person, toast, icon } = App;

  // greeting by time of day + today's date
  const h = new Date().getHours();
  $('#greeting').textContent = `${h < 12 ? 'صباح الخير' : 'مساء الخير'}، ${DB.admin.name.split(' ')[0]}`;
  $('#today').textContent = `${new Date().toLocaleDateString('ar-u-nu-latn', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })} · إليك ملخص أداء المنصة.`;

  // sparklines
  const sparks = {
    revenue: DB.revenue.courses.map((v, i) => v + DB.revenue.services[i]),
    students: [62, 70, 68, 81, 77, 90, 96, 104, 99, 112, 109, 128],
    orders: [210, 230, 226, 251, 262, 270, 284, 291, 300, 318, 314, 342],
    completion: [58, 60, 61, 63, 62, 66, 67, 65, 66, 67, 66, 64],
  };
  document.querySelectorAll('[data-spark]').forEach((el) => Charts.spark(el, sparks[el.dataset.spark], el.dataset.color));

  // revenue chart with range switch
  function drawRevenue(n) {
    const labels = DB.revenue.labels.slice(-n);
    const courses = DB.revenue.courses.slice(-n);
    const services = DB.revenue.services.slice(-n);
    Charts.bars($('#revenueChart'), {
      labels,
      stacked: true,
      series: [
        { name: 'الدورات والورش', color: '#0066ff', data: courses },
        { name: 'خدمات التطوير', color: '#0b0d12', data: services },
      ],
      format: (v) => `${num(v)}$`,
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

  // traffic sources
  Charts.donut($('#sourcesChart'), { items: DB.sources, centerValue: '21.4K', centerLabel: 'زيارة' });
  $('#sourcesList').innerHTML = DB.sources
    .map((s) => `<div class="list-item" style="padding:8px 0;border:0"><i style="width:10px;height:10px;border-radius:3px;background:${s.color}"></i><span class="grow">${esc(s.label)}</span><b>${s.value}%</b></div>`)
    .join('');

  // recent orders
  $('#recentOrders').innerHTML = DB.orders
    .slice(0, 6)
    .map((o) => `<tr><td class="mono">${o.id}</td><td>${person({ name: o.customer, initial: o.initial, color: o.color })}</td><td>${esc(o.item)}</td><td class="num">${money(o.amount)}</td><td>${badge(o.status)}</td></tr>`)
    .join('');

  // what the team did lately, from the activity log
  App.api
    .get('activity', { limit: 6 })
    .then(({ data }) => {
      $('#activity').innerHTML =
        data.map((a) => `<li><span class="t-dot" style="background:${App.activityTone(a.action)}"></span><b>${esc(a.user)}</b><p>${esc(a.description)}</p><time>${esc(App.ago(a.at))}</time></li>`).join('') ||
        '<li class="muted" style="list-style:none">لا يوجد نشاط بعد</li>';
    })
    .catch(() => {});

  // top courses by revenue
  const top = [...DB.courses].sort((a, b) => b.revenue - a.revenue).slice(0, 4);
  const maxRev = top[0].revenue;
  $('#topCourses').innerHTML = top
    .map(
      (c) => `
      <div class="list-item">
        <span class="thumb">${esc(c.glyph)}</span>
        <div class="grow">
          <b>${esc(c.title)}</b>
          <div class="progress" style="margin-top:6px"><i style="width:${(c.revenue / maxRev) * 100}%"></i></div>
        </div>
        <b class="num">${money(c.revenue)}</b>
      </div>`,
    )
    .join('');

  // upcoming workshops
  $('#upcomingWorkshops').innerHTML = DB.workshops
    .filter((w) => w.status !== 'منتهية')
    .slice(0, 4)
    .map((w) => {
      const pct = Math.round((w.taken / w.seats) * 100);
      const [, m, d] = w.date.split('-').map(Number);
      return `
      <div class="list-item">
        <span class="kpi-ico c-ink" style="flex-direction:column;line-height:1.1;font-weight:800;width:46px;height:50px;display:flex;align-items:center;justify-content:center"><span style="font-size:17px">${d}</span><small style="font-size:10px;color:#c9d1dd">${['', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر', 'أكتوبر', 'نوفمبر', 'ديسمبر'][m]}</small></span>
        <div class="grow">
          <b>${esc(w.title)}</b>
          <small>${w.taken}/${w.seats} مقعداً · ${esc(w.format)}</small>
          <div class="progress ${pct >= 100 ? 'red' : pct > 75 ? 'amber' : ''}" style="margin-top:6px"><i style="width:${pct}%"></i></div>
        </div>
      </div>`;
    })
    .join('');

  // leads pipeline
  const total = DB.leads.filter((l) => l.stage !== 'lost').reduce((a, l) => a + l.budget, 0);
  $('#pipeline').innerHTML = `
    <div class="mini-stat" style="margin-bottom:16px"><b>${money(total)}</b><small>قيمة الفرص المفتوحة والمقبولة</small></div>
    ${DB.leadStages
      .map((s) => {
        const n = DB.leads.filter((l) => l.stage === s.key).length;
        return `<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
          <span style="width:110px;font-size:13px;font-weight:700;color:var(--fg)">${s.label}</span>
          <div class="progress" style="flex:1"><i style="width:${(n / DB.leads.length) * 100}%;background:${s.color}"></i></div>
          <b class="num" style="width:20px;text-align:center">${n}</b>
        </div>`;
      })
      .join('')}`;

  $('#exportReport').addEventListener('click', () =>
    App.downloadCSV(
      'revenue-report.csv',
      [
        { key: 'month', label: 'الشهر' },
        { key: 'courses', label: 'الدورات' },
        { key: 'services', label: 'الخدمات' },
      ],
      DB.revenue.labels.map((m, i) => ({ month: m, courses: DB.revenue.courses[i], services: DB.revenue.services[i] })),
    ),
  );
});
