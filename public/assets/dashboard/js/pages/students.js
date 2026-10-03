document.addEventListener('app:ready', () => {
  const { $, esc, num, money, date, badge, icon, person, toast, confirmDialog, openDrawer, closeDrawer, openModal, closeModal, DataTable } = App;
  const rows = DB.students;

  function stats() {
    const active = rows.filter((s) => s.status === 'نشط').length;
    const pro = rows.filter((s) => s.pro).length;
    const avg = Math.round(rows.reduce((a, s) => a + s.progress, 0) / rows.length);
    $('#studentStats').innerHTML = [
      ['users', 'c-blue', 'إجمالي الأعضاء', num(rows.length)],
      ['check-circle', 'c-green', 'نشطون', num(active)],
      ['award', 'c-violet', 'أعضاء Pro', num(pro)],
      ['trend', 'c-amber', 'متوسط الإنجاز', `${avg}%`],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const table = new DataTable({
    mount: $('#table'),
    rows,
    pageSize: 10,
    selectable: true,
    searchKeys: ['name', 'email', 'id'],
    onSelect: (ids) => {
      $('#bulk').hidden = !ids.length;
      $('#bulkCount').textContent = `تم تحديد ${ids.length}`;
    },
    columns: [
      { key: 'name', label: 'الطالب', sortable: true, render: (s) => `<button class="link-reset" data-student="${s.id}" style="border:0;background:none;padding:0;text-align:start;cursor:pointer">${person({ ...s, sub: s.email })}</button>` },
      { key: 'pro', label: 'العضوية', render: (s) => (s.pro ? '<span class="badge info">Pro</span>' : '<span class="badge">مجانية</span>') },
      { key: 'country', label: 'الدولة', sortable: true, render: (s) => `<span class="nowrap">${esc(s.country)}</span>` },
      { key: 'courses', label: 'الدورات', sortable: true, className: 'num' },
      { key: 'progress', label: 'الإنجاز', sortable: true, render: (s) => `<div style="min-width:120px;display:flex;align-items:center;gap:8px"><div class="progress ${s.progress === 100 ? 'green' : ''}" style="flex:1"><i style="width:${s.progress}%"></i></div><small class="num">${s.progress}%</small></div>` },
      { key: 'spent', label: 'المدفوع', sortable: true, className: 'num', render: (s) => money(s.spent) },
      { key: 'joined', label: 'انضم', sortable: true, render: (s) => `<span class="nowrap">${date(s.joined)}</span>` },
      { key: 'status', label: 'الحالة', sortable: true, render: (s) => badge(s.status) },
      {
        key: '',
        label: '',
        className: 'actions',
        render: (s) => `
          <div class="dropdown">
            <button class="btn-icon" data-dropdown aria-label="إجراءات">${icon('more', 'sm')}</button>
            <div class="menu">
              <button data-student="${s.id}">${icon('eye', 'sm')}عرض الملف</button>
              <button data-mail="${s.id}">${icon('mail', 'sm')}مراسلة</button>
              <button data-toggle="${s.id}">${icon(s.status === 'موقوف' ? 'check-circle' : 'lock', 'sm')}${s.status === 'موقوف' ? 'إعادة التفعيل' : 'إيقاف الحساب'}</button>
            </div>
          </div>`,
      },
    ],
  });

  // filters
  const statuses = ['الكل', 'نشط', 'غير نشط', 'موقوف'];
  let current = 'الكل';
  const renderSeg = () =>
    ($('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s}" class="${current === s ? 'on' : ''}">${s}<span class="n">${s === 'الكل' ? rows.length : rows.filter((r) => r.status === s).length}</span></button>`)
      .join(''));
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    table.setFilter('status', current === 'الكل' ? null : (r) => r.status === current);
    renderSeg();
  });
  [...new Set(rows.map((s) => s.country))].sort().forEach((c) => $('#countryFilter').insertAdjacentHTML('beforeend', `<option>${esc(c)}</option>`));
  $('#countryFilter').addEventListener('change', (e) => table.setFilter('country', e.target.value ? (r) => r.country === e.target.value : null));
  $('#planFilter').addEventListener('change', (e) => table.setFilter('plan', e.target.value ? (r) => (e.target.value === 'pro' ? r.pro : !r.pro) : null));
  $('#q').addEventListener('input', App.debounce((e) => table.setQuery(e.target.value), 150));

  // global search from the topbar lands here as ?q=
  const q = new URLSearchParams(location.search).get('q');
  if (q) {
    $('#q').value = q;
    table.setQuery(q);
  }

  function refresh() {
    table.selected.clear();
    table.render();
    $('#bulk').hidden = true;
    renderSeg();
    stats();
  }

  // bulk
  const picked = () => rows.filter((r) => table.selected.has(r.id));
  $('#bulkActivate').addEventListener('click', () => {
    picked().forEach((s) => (s.status = 'نشط'));
    refresh();
    toast('تم تفعيل الحسابات المحددة');
  });
  $('#bulkSuspend').addEventListener('click', async () => {
    const n = table.selected.size;
    if (await confirmDialog({ title: `إيقاف ${n} حساب؟`, text: 'لن يتمكن الطلاب من الدخول إلى دوراتهم حتى إعادة التفعيل.', ok: 'إيقاف' })) {
      picked().forEach((s) => (s.status = 'موقوف'));
      refresh();
      toast('تم إيقاف الحسابات');
    }
  });

  // mail modal
  let mailTargets = [];
  const setMailTargets = (list) => {
    mailTargets = list;
    $('#mailTo').textContent = list.length === 1 ? `${list[0].name} · ${list[0].email}` : `${num(list.length)} مستلم`;
  };
  $('#mailAll').addEventListener('click', () => setMailTargets(table.view));
  $('#bulk [data-open="mailModal"]').addEventListener('click', () => setMailTargets(picked()));
  $('#mailForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!$('#mSubject').value.trim() || !$('#mBody').value.trim()) return toast('أكمل الموضوع والرسالة', 'error');
    closeModal('mailModal');
    e.target.reset();
    toast(`تم إرسال الرسالة إلى ${num(mailTargets.length)} مستلم`);
  });

  // row actions
  document.addEventListener('click', async (e) => {
    const v = e.target.closest('[data-student]');
    if (v) return profile(rows.find((s) => s.id === v.dataset.student));
    const m = e.target.closest('[data-mail]');
    if (m) {
      setMailTargets([rows.find((s) => s.id === m.dataset.mail)]);
      return openModal('mailModal');
    }
    const t = e.target.closest('[data-toggle]');
    if (t) {
      const s = rows.find((x) => x.id === t.dataset.toggle);
      if (s.status !== 'موقوف' && !(await confirmDialog({ title: `إيقاف حساب ${s.name}؟`, ok: 'إيقاف' }))) return;
      s.status = s.status === 'موقوف' ? 'نشط' : 'موقوف';
      refresh();
      closeDrawer();
      toast(s.status === 'موقوف' ? 'تم إيقاف الحساب' : 'تم تفعيل الحساب');
    }
  });

  function profile(s) {
    const enrolled = DB.courses.filter((c) => c.status === 'منشورة').slice(0, s.courses);
    const orders = DB.orders.filter((o) => o.customer === s.name).slice(0, 4);
    openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">ملف الطالب</h3><small class="muted mono">${s.id}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div style="display:flex;align-items:center;gap:14px">
          <span class="avatar lg" style="background:${s.color}">${esc(s.initial)}</span>
          <div style="flex:1;min-width:0"><b style="font-size:17px;color:var(--fg);display:block">${esc(s.name)}</b><small class="muted">${esc(s.email)}</small><div style="display:flex;gap:6px;margin-top:6px">${badge(s.status)}${s.pro ? '<span class="badge info">Pro</span>' : ''}</div></div>
        </div>
        <div class="card stat-row" style="border-top:1px solid var(--line)">
          <div class="mini-stat"><b>${s.courses}</b><small>دورات</small></div>
          <div class="mini-stat"><b>${s.progress}%</b><small>إنجاز</small></div>
          <div class="mini-stat"><b>${money(s.spent)}</b><small>المدفوع</small></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">الدورات المسجّل بها</div>
          <div class="card">${enrolled
            .map((c, i) => {
              const p = Math.max(5, Math.min(100, s.progress - i * 17));
              return `<div class="list-item"><span class="grow"><b>${esc(c.title)}</b><div class="progress ${p === 100 ? 'green' : ''}" style="margin-top:8px"><i style="width:${p}%"></i></div></span><small class="num">${p}%</small></div>`;
            })
            .join('')}</div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">آخر المدفوعات</div>
          <div class="card">${orders.length ? orders.map((o) => `<div class="list-item"><span class="grow"><b>${esc(o.item)}</b><small>${date(o.date)}</small></span><b class="num">${money(o.amount)}</b></div>`).join('') : '<div class="list-item muted">لا توجد مدفوعات.</div>'}</div>
        </div>
        <div class="card" style="padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13.5px">
          <span>${icon('globe', 'sm')} ${esc(s.country)}</span>
          <span>${icon('calendar', 'sm')} انضم في ${date(s.joined)}</span>
        </div>
      </div>
      <div class="drawer-foot">
        <button class="btn btn-ghost" style="flex:1" data-mail="${s.id}">${icon('mail', 'sm')}مراسلة</button>
        <button class="btn ${s.status === 'موقوف' ? 'btn-soft' : 'btn-danger-soft'}" style="flex:1" data-toggle="${s.id}">${s.status === 'موقوف' ? 'إعادة التفعيل' : 'إيقاف الحساب'}</button>
      </div>`);
  }

  $('#export').addEventListener('click', () =>
    App.downloadCSV('students.csv', [
      { key: 'id', label: 'المعرف' }, { key: 'name', label: 'الاسم' }, { key: 'email', label: 'البريد' }, { key: 'country', label: 'الدولة' },
      { key: 'courses', label: 'الدورات' }, { key: 'progress', label: 'الإنجاز %' }, { key: 'spent', label: 'المدفوع' }, { key: 'joined', label: 'تاريخ الانضمام' }, { key: 'status', label: 'الحالة' },
    ], table.view),
  );

  renderSeg();
  stats();
});
