document.addEventListener('app:ready', () => {
  const { $, esc, num, date, badge, icon, toast, confirmDialog, closeModal, openModal, DataTable } = App;
  let rows = DB.coupons.map((c) => ({ ...c, id: c.code }));

  function stats() {
    const active = rows.filter((c) => c.status === 'نشط');
    const uses = rows.reduce((s, c) => s + c.uses, 0);
    $('#couponStats').innerHTML = [
      ['tag', 'c-blue', 'كوبونات نشطة', active.length],
      ['cart', 'c-green', 'مرات الاستخدام', num(uses)],
      ['dollar', 'c-amber', 'خصومات ممنوحة (تقديري)', `${num(uses * 14)}$`],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const table = new DataTable({
    mount: $('#table'),
    rows,
    pageSize: 10,
    columns: [
      { key: 'code', label: 'الكود', render: (c) => `<span class="mono" style="font-weight:800;color:var(--fg);background:var(--tint);padding:4px 10px;border-radius:8px;border:1px dashed var(--line-2)">${esc(c.code)}</span>` },
      { key: 'value', label: 'الخصم', sortable: true, render: (c) => `<b style="color:var(--fg)">${c.type === 'percent' ? `${c.value}%` : `${c.value}$`}</b>` },
      { key: 'scope', label: 'ينطبق على', render: (c) => esc(c.scope) },
      {
        key: 'uses',
        label: 'الاستخدام',
        sortable: true,
        render: (c) =>
          c.limit
            ? `<div style="min-width:120px"><div style="font-size:12.5px;margin-bottom:4px"><b class="num">${c.uses}</b> / ${c.limit}</div><div class="progress ${c.uses >= c.limit ? 'red' : ''}"><i style="width:${Math.min(100, (c.uses / c.limit) * 100)}%"></i></div></div>`
            : `<b class="num">${num(c.uses)}</b> <span class="muted">· بلا حد</span>`,
      },
      { key: 'expires', label: 'ينتهي', sortable: true, render: (c) => `<span class="nowrap">${date(c.expires)}</span>` },
      { key: 'status', label: 'الحالة', render: (c) => (c.status === 'منتهي' ? badge('منتهي') : `<label class="switch"><input type="checkbox" data-toggle="${c.id}" ${c.status === 'نشط' ? 'checked' : ''}><span class="track"></span><span>${c.status}</span></label>`) },
      {
        key: '',
        label: '',
        className: 'actions',
        render: (c) => `<button class="btn-icon" data-copy="${c.id}" title="نسخ" aria-label="نسخ">${icon('copy', 'sm')}</button><button class="btn-icon danger" data-del="${c.id}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>`,
      },
    ],
  });

  document.addEventListener('change', (e) => {
    const t = e.target.closest('[data-toggle]');
    if (!t) return;
    const c = rows.find((r) => r.id === t.dataset.toggle);
    c.status = t.checked ? 'نشط' : 'موقوف';
    table.render();
    stats();
    toast(t.checked ? `تم تفعيل ${c.code}` : `تم إيقاف ${c.code}`);
  });
  document.addEventListener('click', async (e) => {
    const cp = e.target.closest('[data-copy]');
    if (cp) App.copy(cp.dataset.copy, `تم نسخ ${cp.dataset.copy}`);
    const del = e.target.closest('[data-del]');
    if (del && (await confirmDialog({ title: `حذف ${del.dataset.del}؟`, text: 'لن يتمكن أحد من استخدام هذا الكود بعد حذفه.', ok: 'حذف' }))) {
      rows = rows.filter((r) => r.id !== del.dataset.del);
      table.setRows(rows);
      stats();
      toast('تم حذف الكوبون');
    }
  });

  // form
  const genCode = () => {
    const words = ['BATTA', 'CODE', 'LEARN', 'BUILD', 'DEV'];
    $('#code').value = `${words[Math.floor(Math.random() * words.length)]}${Math.floor(Math.random() * 40 + 10)}`;
  };
  $('#genCode').addEventListener('click', genCode);
  const nextMonth = new Date(Date.now() + 30 * 864e5).toISOString().slice(0, 10);
  $('#cexp').value = nextMonth;

  $('#couponForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const code = $('#code').value.trim().toUpperCase();
    const value = Number($('#cvalue').value);
    if (!/^[A-Z0-9_-]{3,20}$/.test(code)) {
      $('#code').classList.add('invalid');
      return toast('الكود: 3–20 حرفاً إنجليزياً أو رقماً', 'error');
    }
    if (rows.some((r) => r.code === code)) return toast('هذا الكود موجود مسبقاً', 'error');
    if (!(value > 0) || ($('#ctype').value === 'percent' && value > 100)) {
      $('#cvalue').classList.add('invalid');
      return toast('قيمة الخصم غير صحيحة (النسبة بين 1 و 100)', 'error');
    }
    const limit = Number($('#climit').value);
    if (!Number.isInteger(limit) || limit < 0) {
      $('#climit').classList.add('invalid');
      return toast('حد الاستخدام رقم صحيح (0 = بلا حد)', 'error');
    }
    if (!$('#cexp').value || $('#cexp').value < new Date().toISOString().slice(0, 10)) {
      $('#cexp').classList.add('invalid');
      return toast('تاريخ الانتهاء يجب أن يكون اليوم أو بعده', 'error');
    }
    rows.unshift({ id: code, code, type: $('#ctype').value, value, uses: 0, limit, expires: $('#cexp').value, status: 'نشط', scope: $('#cscope').value });
    table.setRows(rows);
    stats();
    closeModal('couponModal');
    e.target.reset();
    $('#cexp').value = nextMonth;
    toast(`تم إنشاء الكوبون ${code}`);
  });
  $('#couponForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  $('#cexp').min = new Date().toISOString().slice(0, 10);

  stats();
  if (location.hash === '#new') {
    genCode();
    openModal('couponModal');
  }
});
