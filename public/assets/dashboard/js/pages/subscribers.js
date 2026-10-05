document.addEventListener('app:ready', async () => {
  const { $, $$, esc, num, date, badge, icon, toast, confirmDialog, DataTable, api } = App;
  const canManage = App.can('manage_students');
  let rows;
  try {
    rows = (await api.get('subscribers')).data;
  } catch {
    return;
  }

  function stats() {
    const active = rows.filter((s) => s.is_active);
    const monthAgo = new Date(Date.now() - 30 * 864e5).toISOString().slice(0, 10);
    $('#subStats').innerHTML = [
      ['mail', 'c-blue', 'مشتركون حالياً', num(active.length)],
      ['plus', 'c-green', 'اشتركوا آخر 30 يوماً', num(active.filter((s) => s.subscribed_at >= monthAgo).length)],
      ['x-circle', 'c-amber', 'ألغوا الاشتراك', num(rows.length - active.length)],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const table = new DataTable({
    mount: $('#table'),
    rows,
    pageSize: 15,
    searchKeys: ['email'],
    empty: 'لا يوجد مشتركون بعد. نموذج النشرة في الموقع يضيفهم هنا.',
    columns: [
      { key: 'email', label: 'البريد', sortable: true, render: (s) => `<bdi class="mono" style="color:var(--fg)">${esc(s.email)}</bdi>${s.is_student ? ' <span class="badge info">طالب</span>' : ''}` },
      { key: 'status_label', label: 'الحالة', render: (s) => (s.is_active ? `<span class="badge success dot">${esc(s.status_label)}</span>` : `<span class="badge dot">${esc(s.status_label)}</span>`) },
      { key: 'source_label', label: 'المصدر', render: (s) => esc(s.source_label) },
      { key: 'subscribed_at', label: 'التاريخ', sortable: true, render: (s) => `<span class="nowrap">${date(s.is_active ? s.subscribed_at : s.unsubscribed_at)}</span>` },
      { key: '', label: '', className: 'actions', render: (s) => (canManage ? `<button class="btn-icon danger" data-del="${s.id}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>` : '') },
    ],
  });

  // الكل / مشترك / ألغى الاشتراك
  let current = 'الكل';
  const renderSeg = () => {
    const counts = { الكل: rows.length, مشترك: rows.filter((s) => s.is_active).length, 'ألغى الاشتراك': rows.filter((s) => !s.is_active).length };
    $('#statusSeg').innerHTML = Object.entries(counts).map(([l, n]) => `<button class="${l === current ? 'on' : ''}" data-s="${l}">${l} <span class="n">${n}</span></button>`).join('');
  };
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    table.setFilter('status', current === 'الكل' ? null : (r) => r.status_label === current);
    renderSeg();
  });
  $('#q').addEventListener('input', App.debounce((e) => table.setQuery(e.target.value), 150));

  $('#table').addEventListener('click', async (e) => {
    const del = e.target.closest('[data-del]');
    if (!del) return;
    const s = rows.find((r) => r.id === Number(del.dataset.del));
    if (!(await confirmDialog({ title: 'حذف المشترك؟', text: `سيُحذف ${s.email} من قائمة النشرة.`, ok: 'حذف' }))) return;
    try {
      await api.delete(`subscribers/${s.id}`);
    } catch {
      return;
    }
    rows.splice(rows.indexOf(s), 1);
    table.render();
    stats();
    renderSeg();
    toast('تم الحذف');
  });

  $('#export').addEventListener('click', () =>
    App.downloadCSV(
      'subscribers.csv',
      [
        { key: 'email', label: 'البريد' },
        { key: 'status_label', label: 'الحالة' },
        { key: 'source_label', label: 'المصدر' },
        { key: 'subscribed_at', label: 'تاريخ الاشتراك' },
        { key: 'unsubscribed_at', label: 'تاريخ الإلغاء' },
      ],
      table.view,
    ),
  );

  stats();
  renderSeg();
});
