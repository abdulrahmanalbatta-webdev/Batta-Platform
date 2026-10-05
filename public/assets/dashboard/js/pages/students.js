document.addEventListener('app:ready', async () => {
  const { $, esc, num, money, date, badge, icon, person, toast, confirmDialog, openDrawer, closeDrawer, openModal, closeModal, DataTable, api, showFieldErrors } = App;
  const canManage = App.can('manage_students');
  const COLORS = ['#0066ff', '#0b0d12', '#334155', '#5c9dff', '#0e9f6e', '#7c3aed', '#c27803'];
  const load = async () => (await api.get('students')).data.map((s) => ({ ...s, color: COLORS[s.id % COLORS.length] }));
  let rows;
  try {
    rows = await load();
  } catch {
    return;
  }
  const byId = (id) => rows.find((s) => s.id === Number(id));

  function stats() {
    const active = rows.filter((s) => s.state === 'active').length;
    const pro = rows.filter((s) => s.is_pro).length;
    const avg = rows.length ? Math.round(rows.reduce((a, s) => a + s.progress, 0) / rows.length) : 0;
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
    selectable: canManage,
    searchKeys: ['name', 'email', 'code'],
    onSelect: (ids) => {
      $('#bulk').hidden = !ids.length;
      $('#bulkCount').textContent = `تم تحديد ${ids.length}`;
    },
    columns: [
      { key: 'name', label: 'الطالب', sortable: true, render: (s) => `<button class="link-reset" data-student="${s.id}" style="border:0;background:none;padding:0;text-align:start;cursor:pointer">${person({ ...s, sub: s.email })}</button>` },
      { key: 'is_pro', label: 'العضوية', render: (s) => (s.is_pro ? '<span class="badge info">Pro</span>' : '<span class="badge">مجانية</span>') },
      { key: 'country', label: 'الدولة', sortable: true, render: (s) => `<span class="nowrap">${esc(s.country || '—')}</span>` },
      { key: 'courses', label: 'الدورات', sortable: true, className: 'num' },
      { key: 'progress', label: 'الإنجاز', sortable: true, render: (s) => `<div style="min-width:120px;display:flex;align-items:center;gap:8px"><div class="progress ${s.progress === 100 ? 'green' : ''}" style="flex:1"><i style="width:${s.progress}%"></i></div><small class="num">${s.progress}%</small></div>` },
      { key: 'spent', label: 'المدفوع', sortable: true, className: 'num', render: (s) => money(s.spent) },
      { key: 'joined', label: 'انضم', sortable: true, render: (s) => `<span class="nowrap">${date(s.joined)}</span>` },
      { key: 'state_label', label: 'الحالة', sortable: true, render: (s) => badge(s.state_label) },
      {
        key: '',
        label: '',
        className: 'actions',
        render: (s) => `
          <div class="dropdown">
            <button class="btn-icon" data-dropdown aria-label="إجراءات">${icon('more', 'sm')}</button>
            <div class="menu">
              <button data-student="${s.id}">${icon('eye', 'sm')}عرض الملف</button>
              ${canManage ? `<button data-mail="${s.id}">${icon('mail', 'sm')}مراسلة</button>
              <button data-toggle="${s.id}">${icon(s.state === 'suspended' ? 'check-circle' : 'lock', 'sm')}${s.state === 'suspended' ? 'إعادة التفعيل' : 'إيقاف الحساب'}</button>` : ''}
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
      .map((s) => `<button data-s="${s}" class="${current === s ? 'on' : ''}">${s}<span class="n">${s === 'الكل' ? rows.length : rows.filter((r) => r.state_label === s).length}</span></button>`)
      .join(''));
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    table.setFilter('status', current === 'الكل' ? null : (r) => r.state_label === current);
    renderSeg();
  });
  [...new Set(rows.map((s) => s.country).filter(Boolean))].sort().forEach((c) => $('#countryFilter').insertAdjacentHTML('beforeend', `<option>${esc(c)}</option>`));
  $('#countryFilter').addEventListener('change', (e) => table.setFilter('country', e.target.value ? (r) => r.country === e.target.value : null));
  $('#planFilter').addEventListener('change', (e) => table.setFilter('plan', e.target.value ? (r) => (e.target.value === 'pro' ? r.is_pro : !r.is_pro) : null));
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

  // suspend / reactivate through the API, then reflect it locally
  async function setStatus(list, status) {
    try {
      await api.put('students/status', { ids: list.map((s) => s.id), status });
    } catch {
      return false;
    }
    // reload rather than guess: a reactivated student who hasn't been around for a while is "inactive", not "active"
    try {
      rows = await load();
    } catch {
      return true;
    }
    table.setRows(rows);
    refresh();
    return true;
  }

  // bulk
  const picked = () => rows.filter((r) => table.selected.has(String(r.id)));
  $('#bulkActivate').addEventListener('click', async () => {
    if (await setStatus(picked(), 'active')) toast('تم تفعيل الحسابات المحددة');
  });
  $('#bulkSuspend').addEventListener('click', async () => {
    const n = table.selected.size;
    if (await confirmDialog({ title: `إيقاف ${n} حساب؟`, text: 'لن يتمكن الطلاب من الدخول إلى دوراتهم حتى إعادة التفعيل.', ok: 'إيقاف' })) {
      if (await setStatus(picked(), 'suspended')) toast('تم إيقاف الحسابات');
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
  $('#mailForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  $('#mailForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!$('#mSubject').value.trim() || !$('#mBody').value.trim()) return toast('أكمل الموضوع والرسالة', 'error');
    if (!mailTargets.length) return toast('لا يوجد مستلمون', 'error');
    const btn = e.submitter;
    btn.disabled = true;
    let res;
    try {
      res = await api.post('students/messages', { ids: mailTargets.map((s) => s.id), subject: $('#mSubject').value.trim(), body: $('#mBody').value.trim() });
    } catch (err) {
      return showFieldErrors(err, { subject: '#mSubject', body: '#mBody' });
    } finally {
      btn.disabled = false;
    }
    closeModal('mailModal');
    e.target.reset();
    const skipped = mailTargets.length - res.sent;
    toast(`تم إرسال الرسالة إلى ${num(res.sent)} مستلم${skipped ? ` (تم تخطي ${num(skipped)} حساب موقوف)` : ''}`);
  });

  // row actions
  document.addEventListener('click', async (e) => {
    const v = e.target.closest('[data-student]');
    if (v) return profile(byId(v.dataset.student));
    const m = e.target.closest('[data-mail]');
    if (m) {
      setMailTargets([byId(m.dataset.mail)]);
      return openModal('mailModal');
    }
    const t = e.target.closest('[data-toggle]');
    if (t) {
      const s = byId(t.dataset.toggle);
      const suspend = s.state !== 'suspended';
      if (suspend && !(await confirmDialog({ title: `إيقاف حساب ${s.name}؟`, ok: 'إيقاف' }))) return;
      if (!(await setStatus([s], suspend ? 'suspended' : 'active'))) return;
      closeDrawer();
      toast(suspend ? 'تم إيقاف الحساب' : 'تم تفعيل الحساب');
    }
  });

  async function profile(s) {
    let full;
    try {
      full = (await api.get(`students/${s.id}`)).data;
    } catch {
      return;
    }
    openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">ملف الطالب</h3><small class="muted mono">${esc(s.code)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div style="display:flex;align-items:center;gap:14px">
          <span class="avatar lg" style="background:${s.color}">${esc(s.initial)}</span>
          <div style="flex:1;min-width:0"><b style="font-size:17px;color:var(--fg);display:block">${esc(s.name)}</b><small class="muted">${esc(s.email)}</small><div style="display:flex;gap:6px;margin-top:6px">${badge(s.state_label)}${s.is_pro ? `<span class="badge info">Pro حتى ${date(full.pro_until)}</span>` : ''}</div></div>
        </div>
        <div class="card stat-row" style="border-top:1px solid var(--line)">
          <div class="mini-stat"><b>${full.courses}</b><small>دورات</small></div>
          <div class="mini-stat"><b>${full.progress}%</b><small>إنجاز</small></div>
          <div class="mini-stat"><b>${money(full.spent)}</b><small>المدفوع</small></div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">الدورات المسجّل بها</div>
          <div class="card">${
            full.enrollments
              .map((c) => `<div class="list-item"><span class="grow"><b>${esc(c.title)}</b><div class="progress ${c.progress === 100 ? 'green' : ''}" style="margin-top:8px"><i style="width:${c.progress}%"></i></div></span><small class="num">${c.progress}%</small></div>`)
              .join('') || '<div class="list-item muted">غير مسجّل في أي دورة.</div>'
          }</div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">آخر الطلبات</div>
          <div class="card">${full.orders.length ? full.orders.map((o) => `<div class="list-item"><span class="grow"><b>${esc(o.item_name)}</b><small>${esc(o.number)} · ${date(o.date)} · ${esc(o.status_label)}</small></span><b class="num">${money(o.total)}</b></div>`).join('') : '<div class="list-item muted">لا توجد طلبات.</div>'}</div>
        </div>
        <div class="card" style="padding:14px;display:flex;flex-direction:column;gap:8px;font-size:13.5px">
          <span>${icon('globe', 'sm')} ${esc(s.country || '—')}</span>
          <span>${icon('calendar', 'sm')} انضم في ${date(s.joined)}</span>
        </div>
      </div>
      ${canManage ? `<div class="drawer-foot">
        <button class="btn btn-ghost" style="flex:1" data-mail="${s.id}">${icon('mail', 'sm')}مراسلة</button>
        <button class="btn ${s.state === 'suspended' ? 'btn-soft' : 'btn-danger-soft'}" style="flex:1" data-toggle="${s.id}">${s.state === 'suspended' ? 'إعادة التفعيل' : 'إيقاف الحساب'}</button>
      </div>` : ''}`);
  }

  $('#export').addEventListener('click', () =>
    App.downloadCSV('students.csv', [
      { key: 'code', label: 'المعرف' }, { key: 'name', label: 'الاسم' }, { key: 'email', label: 'البريد' }, { key: 'country', label: 'الدولة' },
      { key: 'courses', label: 'الدورات' }, { key: 'progress', label: 'الإنجاز %' }, { key: 'spent', label: 'المدفوع' }, { key: 'joined', label: 'تاريخ الانضمام' }, { key: 'state_label', label: 'الحالة' },
    ], table.view),
  );

  renderSeg();
  stats();
});
