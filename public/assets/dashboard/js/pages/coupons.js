document.addEventListener('app:ready', async () => {
  const { $, esc, num, date, badge, icon, toast, confirmDialog, closeModal, openModal, DataTable, api, showFieldErrors, money } = App;
  const canManage = App.can('manage_sales');
  let rows;
  let discounts = 0;
  try {
    const [coupons, orders, courses] = await Promise.all([api.get('coupons'), api.get('orders'), api.get('courses')]);
    rows = coupons.data.map((c) => ({ ...c, uses: c.times_used, limit: c.usage_limit ?? 0 }));
    // what coupons actually took off paid orders
    discounts = orders.data.filter((o) => o.status === 'completed').reduce((s, o) => s + o.discount, 0);
    $('#cscope').insertAdjacentHTML('beforeend', courses.data.map((c) => `<option value="course:${c.id}">${esc(c.title)}</option>`).join(''));
  } catch {
    return;
  }

  function stats() {
    const active = rows.filter((c) => c.state === 'active');
    const uses = rows.reduce((s, c) => s + c.uses, 0);
    $('#couponStats').innerHTML = [
      ['tag', 'c-blue', 'كوبونات نشطة', active.length],
      ['cart', 'c-green', 'مرات الاستخدام', num(uses)],
      ['dollar', 'c-amber', 'خصومات ممنوحة', money(+discounts.toFixed(2))],
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
      { key: 'scope_label', label: 'ينطبق على', render: (c) => esc(c.scope_label) },
      {
        key: 'uses',
        label: 'الاستخدام',
        sortable: true,
        render: (c) =>
          c.limit
            ? `<div style="min-width:120px"><div style="font-size:12.5px;margin-bottom:4px"><b class="num">${c.uses}</b> / ${c.limit}</div><div class="progress ${c.uses >= c.limit ? 'red' : ''}"><i style="width:${Math.min(100, (c.uses / c.limit) * 100)}%"></i></div></div>`
            : `<b class="num">${num(c.uses)}</b> <span class="muted">· بلا حد</span>`,
      },
      { key: 'expires_on', label: 'ينتهي', sortable: true, render: (c) => `<span class="nowrap">${date(c.expires_on)}</span>` },
      {
        key: 'state_label',
        label: 'الحالة',
        render: (c) =>
          c.state === 'expired' || !canManage
            ? badge(c.state_label)
            : `<label class="switch"><input type="checkbox" data-toggle="${c.id}" ${c.is_active ? 'checked' : ''}><span class="track"></span><span>${esc(c.state_label)}</span></label>`,
      },
      {
        key: '',
        label: '',
        className: 'actions',
        render: (c) => `<button class="btn-icon" data-copy="${esc(c.code)}" title="نسخ" aria-label="نسخ">${icon('copy', 'sm')}</button>${canManage ? `<button class="btn-icon danger" data-del="${c.id}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>` : ''}`,
      },
    ],
  });

  const shape = (c) => ({ ...c, uses: c.times_used, limit: c.usage_limit ?? 0 });
  document.addEventListener('change', async (e) => {
    const t = e.target.closest('[data-toggle]');
    if (!t) return;
    const c = rows.find((r) => r.id === Number(t.dataset.toggle));
    try {
      Object.assign(c, shape((await api.patch(`coupons/${c.id}`, { is_active: t.checked })).data));
    } catch {
      t.checked = !t.checked;
      return;
    }
    table.render();
    stats();
    toast(c.is_active ? `تم تفعيل ${c.code}` : `تم إيقاف ${c.code}`);
  });
  document.addEventListener('click', async (e) => {
    const cp = e.target.closest('[data-copy]');
    if (cp) App.copy(cp.dataset.copy, `تم نسخ ${cp.dataset.copy}`);
    const del = e.target.closest('[data-del]');
    if (!del) return;
    const c = rows.find((r) => r.id === Number(del.dataset.del));
    if (!(await confirmDialog({ title: `حذف ${c.code}؟`, text: 'لن يتمكن أحد من استخدام هذا الكود بعد حذفه. الطلبات السابقة تحتفظ به.', ok: 'حذف' }))) return;
    try {
      await api.delete(`coupons/${c.id}`);
    } catch {
      return;
    }
    rows = rows.filter((r) => r !== c);
    table.setRows(rows);
    stats();
    toast('تم حذف الكوبون');
  });

  // form
  const genCode = () => {
    const words = ['BATTA', 'CODE', 'LEARN', 'BUILD', 'DEV'];
    $('#code').value = `${words[Math.floor(Math.random() * words.length)]}${Math.floor(Math.random() * 40 + 10)}`;
  };
  $('#genCode').addEventListener('click', genCode);
  // local dates (not UTC), so "today" matches the calendar of whoever is creating the coupon
  const localDate = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
  const today = localDate(new Date());
  const nextMonth = localDate(new Date(Date.now() + 30 * 864e5));
  $('#cexp').value = nextMonth;

  $('#couponForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const code = $('#code').value.trim().toUpperCase();
    if (!/^[A-Z0-9_-]{3,20}$/.test(code)) {
      $('#code').classList.add('invalid');
      return toast('الكود: 3–20 حرفاً إنجليزياً أو رقماً', 'error');
    }
    const [scope, courseId] = $('#cscope').value.split(':');
    const data = {
      code,
      type: $('#ctype').value,
      value: Number($('#cvalue').value),
      usage_limit: Number($('#climit').value),
      expires_on: $('#cexp').value,
      scope,
      course_id: courseId ? Number(courseId) : null,
    };
    let coupon;
    try {
      coupon = shape((await api.post('coupons', data)).data);
    } catch (err) {
      return showFieldErrors(err, { code: '#code', value: '#cvalue', usage_limit: '#climit', expires_on: '#cexp', scope: '#cscope', course_id: '#cscope' });
    }
    rows.unshift(coupon);
    table.setRows(rows);
    stats();
    closeModal('couponModal');
    e.target.reset();
    $('#cexp').value = nextMonth;
    toast(`تم إنشاء الكوبون ${coupon.code}`);
  });
  $('#couponForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  $('#cexp').min = today;

  stats();
  if (canManage && location.hash === '#new') {
    genCode();
    openModal('couponModal');
  }
});
