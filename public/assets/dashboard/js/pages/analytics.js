document.addEventListener('app:ready', () => {
  const { $, esc, num } = App;
  const days = DB.traffic.labels.map((l) => l.split(' ')[0]);

  function draw(metric) {
    const isVisits = metric === 'visits';
    Charts.line($('#trafficChart'), {
      labels: days,
      xEvery: 3,
      height: 300,
      series: [{ name: isVisits ? 'الزيارات' : 'حسابات جديدة', color: isVisits ? '#0066ff' : '#7c3aed', data: DB.traffic[metric], area: true }],
    });
  }
  draw('visits');
  $('#metric').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $('#metric').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
    draw(b.dataset.m);
  });

  // funnel: each bar as wide as its share of the first step
  const first = DB.funnel[0].value;
  $('#funnel').innerHTML = DB.funnel
    .map((s, i) => {
      const pct = (s.value / first) * 100;
      const drop = i ? Math.round((s.value / DB.funnel[i - 1].value) * 100) : 100;
      return `
      <div style="margin-bottom:14px">
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:6px">
          <b style="color:var(--fg)">${esc(s.label)}</b>
          <span><b class="num" style="color:var(--fg)">${num(s.value)}</b> <span class="muted">${i ? `· ${drop}% من الخطوة السابقة` : ''}</span></span>
        </div>
        <div style="height:30px;border-radius:8px;background:var(--tint);overflow:hidden">
          <div style="height:100%;width:${Math.max(pct, 2)}%;background:linear-gradient(90deg,#0052cc,#0066ff);border-radius:8px;opacity:${1 - i * 0.12}"></div>
        </div>
      </div>`;
    })
    .join('');

  Charts.donut($('#devicesChart'), {
    items: [
      { label: 'جوال', value: 61, color: '#0066ff' },
      { label: 'كمبيوتر', value: 33, color: '#0b0d12' },
      { label: 'تابلت', value: 6, color: '#94a3b8' },
    ],
    centerValue: '61%',
    centerLabel: 'من الجوال',
  });

  const maxViews = DB.topPages[0].views;
  $('#topPages').innerHTML = DB.topPages
    .map(
      (p) => `<tr>
        <td><b style="color:var(--fg)">${esc(p.title)}</b><div class="mono muted" style="font-size:12px">${esc(p.path)}</div></td>
        <td class="num">${num(p.views)}</td>
        <td class="mono">${p.avg}</td>
        <td><div class="progress"><i style="width:${(p.views / maxViews) * 100}%"></i></div></td>
      </tr>`,
    )
    .join('');

  const countries = [
    ['السعودية', 31], ['الأردن', 18], ['فلسطين', 14], ['مصر', 12], ['الإمارات', 9], ['المغرب', 6], ['أخرى', 10],
  ];
  $('#countries').innerHTML = countries
    .map(
      ([c, v]) => `<div style="margin-bottom:12px">
        <div style="display:flex;justify-content:space-between;font-size:13.5px;margin-bottom:5px"><span style="color:var(--fg);font-weight:700">${c}</span><b class="num">${v}%</b></div>
        <div class="progress"><i style="width:${v * 3}%"></i></div>
      </div>`,
    )
    .join('');

  $('#exportTraffic').addEventListener('click', () =>
    App.downloadCSV(
      'traffic.csv',
      [{ key: 'day', label: 'اليوم' }, { key: 'visits', label: 'الزيارات' }, { key: 'signups', label: 'حسابات جديدة' }],
      DB.traffic.labels.map((d, i) => ({ day: d, visits: DB.traffic.visits[i], signups: DB.traffic.signups[i] })),
    ),
  );
});
