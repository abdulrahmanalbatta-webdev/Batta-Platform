document.addEventListener('app:ready', async () => {
  const { $, esc, num, badge, icon, toast, confirmDialog, DataTable, api, showFieldErrors } = App;
  const canEdit = App.can('manage_content');
  let rows;
  try {
    rows = (await api.get('courses')).data;
  } catch {
    return;
  }

  function stats() {
    const pub = rows.filter((c) => c.status === 'published');
    const students = rows.reduce((a, c) => a + c.students, 0);
    const rated = pub.filter((c) => c.rating);
    const avg = rated.reduce((a, c) => a + c.rating, 0) / (rated.length || 1);
    $('#courseStats').innerHTML = [
      ['play', 'c-blue', 'الدورات المنشورة', `${pub.length} / ${rows.length}`],
      ['users', 'c-violet', 'إجمالي المسجلين', num(students)],
      ['award', 'c-green', 'متوسط الطلاب لكل دورة', num(Math.round(students / (rows.length || 1)))],
      ['star', 'c-amber', 'متوسط التقييم', avg.toFixed(1)],
    ]
      .map(([ic, tone, label, value]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${label}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${value}</div></div>`)
      .join('');
  }

  const columns = [
    { key: 'title', label: 'الدورة', sortable: true, render: (c) => `<div class="person"><span class="thumb">${esc(c.glyph)}</span><div><b>${esc(c.title)}</b><small>${esc(c.code)} · ${esc(c.level_label)}</small></div></div>` },
    { key: 'level_label', label: 'المستوى', sortable: true, render: (c) => `<span class="badge">${esc(c.level_label)}</span>` },
    { key: 'students', label: 'الطلاب', sortable: true, className: 'num', render: (c) => num(c.students) },
    { key: 'rating', label: 'التقييم', sortable: true, render: (c) => (c.rating ? `<span class="stars">${icon('star', 'sm fill')}</span> <b class="num">${c.rating}</b>` : '<span class="muted">—</span>') },
    { key: 'status_label', label: 'الحالة', sortable: true, render: (c) => badge(c.status_label) },
    {
      key: '',
      label: '',
      className: 'actions',
      render: (c) => !canEdit ? '' : `
        <a class="btn-icon" href="${App.url('course-edit', { id: c.id })}" title="تعديل" aria-label="تعديل">${icon('edit', 'sm')}</a>
        <div class="dropdown" style="display:inline-block">
          <button class="btn-icon" data-dropdown aria-label="المزيد">${icon('more', 'sm')}</button>
          <div class="menu">
            <a href="${App.url('course-edit', { id: c.id })}">${icon('edit', 'sm')}تعديل</a>
            <button data-act="duplicate" data-id="${c.id}">${icon('copy', 'sm')}نسخ الدورة</button>
            <button data-act="toggle" data-id="${c.id}">${icon(c.status === 'published' ? 'eye-off' : 'eye', 'sm')}${c.status === 'published' ? 'إخفاء' : 'نشر'}</button>
            <hr>
            <button class="danger" data-act="delete" data-id="${c.id}">${icon('trash', 'sm')}حذف</button>
          </div>
        </div>`,
    },
  ];

  const table = new DataTable({
    mount: $('#tableView'),
    columns,
    rows,
    pageSize: 8,
    searchKeys: ['title', 'code'],
    selectable: canEdit,
    onSelect: (ids) => {
      $('#bulkbar').hidden = !ids.length;
      $('#bulkCount').textContent = `تم تحديد ${ids.length}`;
    },
  });

  // status segment with counts
  const statuses = ['الكل', 'منشورة', 'مسودة', 'قيد المراجعة'];
  const renderSeg = () =>
    ($('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s}" class="${(table.status || 'الكل') === s ? 'on' : ''}">${s}<span class="n">${s === 'الكل' ? rows.length : rows.filter((r) => r.status_label === s).length}</span></button>`)
      .join(''));
  renderSeg();
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    table.status = b.dataset.s;
    table.setFilter('status', b.dataset.s === 'الكل' ? null : (r) => r.status_label === b.dataset.s);
    renderSeg();
    renderGrid();
  });
  $('#q').addEventListener('input', App.debounce((e) => { table.setQuery(e.target.value); renderGrid(); }, 150));
  $('#levelFilter').addEventListener('change', (e) => {
    table.setFilter('level', e.target.value ? (r) => r.level_label === e.target.value : null);
    renderGrid();
  });

  // grid view uses the same filters as the table
  function renderGrid() {
    $('#gridView').innerHTML =
      table.view
        .map(
          (c) => `
        <div class="card c-card">
          <div class="c-cover">${c.cover_url ? `<img src="${esc(c.cover_url)}" alt="" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover">` : esc(c.glyph)}${badge(c.status_label)}</div>
          <div class="c-body">
            <h4>${esc(c.title)}</h4>
            <div class="c-meta"><span>${icon('award', 'sm')}${esc(c.level_label)}</span><span>${icon('users', 'sm')}${num(c.students)}</span><span>${icon('star', 'sm')}${c.rating || '—'}</span></div>
          </div>
          <div class="c-foot"><b class="num">${num(c.students)} طالب</b>${canEdit ? `<a class="btn btn-ghost btn-sm" href="${App.url('course-edit', { id: c.id })}">${icon('edit', 'sm')}تعديل</a>` : ''}</div>
        </div>`,
        )
        .join('') || '<div class="empty" style="grid-column:1/-1">لا توجد دورات مطابقة</div>';
  }
  renderGrid();
  $('#viewSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $('#viewSeg').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
    const grid = b.dataset.view === 'grid';
    $('#tableView').hidden = grid;
    $('#gridView').hidden = !grid;
    if (grid) renderGrid();
  });

  function refresh() {
    table.setRows(rows);
    stats();
    renderSeg();
    renderGrid();
  }

  // one course's status through the API; returns false (after a toast) if the server refused
  async function setStatus(c, status) {
    try {
      Object.assign(c, (await api.put(`courses/${c.id}/status`, { status })).data);
      return true;
    } catch (err) {
      showFieldErrors(err);
      return false;
    }
  }

  // row actions
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const c = rows.find((r) => r.id === Number(btn.dataset.id));
    if (!c) return;
    if (btn.dataset.act === 'delete') {
      if (await confirmDialog({ title: 'حذف الدورة؟', text: `سيتم حذف "${c.title}" نهائياً مع دروسها.`, ok: 'حذف' })) {
        try {
          await api.delete(`courses/${c.id}`);
        } catch (err) {
          return showFieldErrors(err);
        }
        rows = rows.filter((r) => r !== c);
        refresh();
        toast('تم حذف الدورة');
      }
    } else if (btn.dataset.act === 'duplicate') {
      try {
        rows.unshift((await api.post(`courses/${c.id}/copies`)).data);
      } catch {
        return;
      }
      refresh();
      toast('تم نسخ الدورة كمسودة');
    } else if (btn.dataset.act === 'toggle') {
      if (!(await setStatus(c, c.status === 'published' ? 'draft' : 'published'))) return;
      refresh();
      toast(c.status === 'published' ? 'تم نشر الدورة' : 'تم إخفاء الدورة');
    }
  });

  // bulk actions: one request per course, then a single summary
  const selectedRows = () => rows.filter((r) => table.selected.has(String(r.id)));
  async function bulkStatus(status, done) {
    const results = await Promise.all(selectedRows().map((r) => setStatus(r, status)));
    refresh();
    const ok = results.filter(Boolean).length;
    if (ok) toast(ok === results.length ? done : `${done} (${ok} من ${results.length})`);
  }
  $('#bulkPublish').addEventListener('click', () => bulkStatus('published', 'تم نشر الدورات المحددة'));
  $('#bulkDraft').addEventListener('click', () => bulkStatus('draft', 'تم تحويل الدورات إلى مسودة'));
  $('#bulkDelete').addEventListener('click', async () => {
    const chosen = selectedRows();
    const n = chosen.length;
    if (!(await confirmDialog({ title: n === 1 ? 'حذف الدورة المحددة؟' : `حذف ${n} دورات؟`, text: 'لا يمكن التراجع عن هذه العملية.', ok: 'حذف' }))) return;
    const deleted = await Promise.all(chosen.map((r) => api.delete(`courses/${r.id}`).then(() => r, () => null)));
    rows = rows.filter((r) => !deleted.includes(r));
    refresh();
    const count = deleted.filter(Boolean).length;
    const kept = n - count;
    // courses with students refuse deletion (they can be hidden instead)
    if (kept) toast(`${count ? `تم حذف ${count} وبقيت ${kept}` : 'لم تُحذف الدورات المحددة'}: الدورات التي فيها طلاب لا تُحذف، أخفِها بدلاً من ذلك.`, 'info');
    else if (count) toast(count === 1 ? 'تم حذف الدورة' : `تم حذف ${count} دورات`);
  });

  $('#exportCourses').addEventListener('click', () =>
    App.downloadCSV('courses.csv', [
      { key: 'code', label: 'الرقم' }, { key: 'title', label: 'الدورة' }, { key: 'level_label', label: 'المستوى' },
      { key: 'students', label: 'الطلاب' }, { key: 'status_label', label: 'الحالة' },
    ], table.view),
  );

  stats();
});
