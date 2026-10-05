document.addEventListener('app:ready', async () => {
  const { $, esc, num, date, badge, icon, toast, confirmDialog, DataTable, api } = App;
  const canEdit = App.can('manage_content');
  let rows;
  try {
    rows = (await api.get('articles')).data;
  } catch {
    return;
  }

  function stats() {
    const pub = rows.filter((a) => a.status === 'published');
    $('#artStats').innerHTML = [
      ['article', 'c-blue', 'مقالات منشورة', pub.length],
      ['eye', 'c-violet', 'إجمالي القراءات', num(pub.reduce((s, a) => s + a.views, 0))],
      ['chat', 'c-green', 'التعليقات', num(pub.reduce((s, a) => s + a.comments, 0))],
      ['clock', 'c-amber', 'مجدولة ومسودات', rows.length - pub.length],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const table = new DataTable({
    mount: $('#table'),
    rows,
    pageSize: 8,
    searchKeys: ['title', 'category_label', 'code'],
    columns: [
      { key: 'title', label: 'المقال', sortable: true, render: (a) => `<div class="person"><span class="kpi-ico c-blue" style="width:42px;height:42px;flex:none">${icon('article')}</span><div><b>${esc(a.title)}</b><small>${esc(a.code)} · ${a.reading_minutes} دقائق قراءة</small></div></div>` },
      { key: 'category_label', label: 'التصنيف', sortable: true, render: (a) => `<span class="badge">${esc(a.category_label)}</span>` },
      { key: 'date', label: 'التاريخ', sortable: true, render: (a) => `<span class="nowrap">${date(a.date)}</span>` },
      { key: 'views', label: 'القراءات', sortable: true, className: 'num', render: (a) => (a.views ? num(a.views) : '<span class="muted">—</span>') },
      { key: 'comments', label: 'التعليقات', sortable: true, className: 'num', render: (a) => (a.comments ? num(a.comments) : '<span class="muted">—</span>') },
      { key: 'status_label', label: 'الحالة', sortable: true, render: (a) => badge(a.status_label) },
      {
        key: '',
        label: '',
        className: 'actions',
        render: (a) => `
          ${canEdit ? `<a class="btn-icon" href="${App.url('article-edit', { id: a.id })}" title="تعديل" aria-label="تعديل">${icon('edit', 'sm')}</a>` : ''}
          <button class="btn-icon" data-act="preview" data-id="${a.id}" title="معاينة" aria-label="معاينة">${icon('eye', 'sm')}</button>
          ${canEdit ? `<button class="btn-icon danger" data-act="delete" data-id="${a.id}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>` : ''}`,
      },
    ],
  });

  const statuses = ['الكل', 'منشور', 'مجدول', 'مسودة'];
  let current = 'الكل';
  const renderSeg = () =>
    ($('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s}" class="${current === s ? 'on' : ''}">${s}<span class="n">${s === 'الكل' ? rows.length : rows.filter((r) => r.status_label === s).length}</span></button>`)
      .join(''));
  renderSeg();
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    table.setFilter('status', current === 'الكل' ? null : (r) => r.status_label === current);
    renderSeg();
  });

  [...new Set(rows.map((r) => r.category_label))].forEach((c) => $('#catFilter').insertAdjacentHTML('beforeend', `<option>${esc(c)}</option>`));
  $('#catFilter').addEventListener('change', (e) => table.setFilter('cat', e.target.value ? (r) => r.category_label === e.target.value : null));
  $('#q').addEventListener('input', App.debounce((e) => table.setQuery(e.target.value), 150));

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const a = rows.find((r) => r.id === Number(btn.dataset.id));
    if (!a) return;
    if (btn.dataset.act === 'delete') {
      if (await confirmDialog({ title: 'حذف المقال؟', text: `سيتم حذف "${a.title}" نهائياً.`, ok: 'حذف' })) {
        try {
          await api.delete(`articles/${a.id}`);
        } catch {
          return;
        }
        rows = rows.filter((r) => r !== a);
        table.setRows(rows);
        stats();
        renderSeg();
        toast('تم حذف المقال');
      }
    } else if (btn.dataset.act === 'preview') {
      toast(a.status === 'published' ? `يفتح: ${App.siteUrl}/articles/${a.slug}` : 'المقال غير منشور بعد');
    }
  });

  stats();
});
