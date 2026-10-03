document.addEventListener('app:ready', async () => {
  const { $, esc, num, money, date, badge, icon, person, toast, confirmDialog, openDrawer, closeDrawer, DataTable, api, showFieldErrors } = App;
  const canManage = App.can('manage_sales');
  const COLORS = ['#0066ff', '#0b0d12', '#334155', '#5c9dff', '#0e9f6e', '#7c3aed', '#c27803'];
  // flatten what the list, search and CSV need
  const shape = (o) => ({ ...o, customer: o.student.name, email: o.student.email, initial: o.student.initial, color: COLORS[o.student.id % COLORS.length], amount: o.total });
  let rows;
  try {
    rows = (await api.get('orders')).data.map(shape);
  } catch {
    return;
  }

  function stats() {
    const done = rows.filter((o) => o.status === 'completed');
    const revenue = done.reduce((s, o) => s + o.amount, 0);
    const refunds = rows.filter((o) => o.status === 'refunded');
    $('#orderStats').innerHTML = [
      ['dollar', 'c-green', 'الإيرادات المكتملة', money(revenue), 'kpi dark'],
      ['cart', 'c-blue', 'عدد الطلبات', num(rows.length), 'kpi'],
      ['trend', 'c-violet', 'متوسط قيمة الطلب', money(Math.round(revenue / (done.length || 1))), 'kpi'],
      ['refresh', 'c-red', `${refunds.length} طلبات مستردة`, money(refunds.reduce((s, o) => s + o.amount, 0)), 'kpi'],
    ]
      .map(([ic, tone, l, v, cls]) => `<div class="card ${cls}"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${cls.includes('dark') ? 'c-glass' : tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const table = new DataTable({
    mount: $('#table'),
    rows,
    pageSize: 10,
    searchKeys: ['number', 'customer', 'email', 'item_name', 'coupon_code'],
    columns: [
      { key: 'id', label: 'الطلب', sortable: true, render: (o) => `<button class="link mono" style="border:0;background:none;padding:0" data-open-order="${o.id}">${esc(o.number)}</button>` },
      { key: 'customer', label: 'العميل', sortable: true, render: (o) => person({ name: o.customer, initial: o.initial, color: o.color, sub: o.email }) },
      { key: 'item_name', label: 'المنتج', render: (o) => `<b style="color:var(--fg);font-weight:600">${esc(o.item_name)}</b><div><span class="badge" style="height:22px;font-size:11.5px">${esc(o.item_type_label)}</span>${o.coupon_code ? ` <span class="badge" style="height:22px;font-size:11.5px">${icon('tag', 'sm')}${esc(o.coupon_code)}</span>` : ''}</div>` },
      { key: 'payment_method_label', label: 'الدفع', render: (o) => `<span class="nowrap">${icon('card', 'sm')} ${esc(o.payment_method_label)}</span>` },
      { key: 'date', label: 'التاريخ', sortable: true, render: (o) => `<span class="nowrap">${date(o.date)}</span>` },
      { key: 'amount', label: 'المبلغ', sortable: true, className: 'num', render: (o) => money(o.amount) },
      { key: 'status_label', label: 'الحالة', sortable: true, render: (o) => badge(o.status_label) },
      { key: '', label: '', className: 'actions', render: (o) => `<button class="btn-icon" data-open-order="${o.id}" aria-label="التفاصيل">${icon('chevron-left', 'sm')}</button>` },
    ],
  });

  const statuses = ['الكل', 'مكتمل', 'معلّق', 'مسترد', 'فشل'];
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
  $('#q').addEventListener('input', App.debounce((e) => table.setQuery(e.target.value), 150));
  $('#typeFilter').addEventListener('change', (e) => table.setFilter('type', e.target.value ? (r) => r.item_type_label === e.target.value : null));
  $('#methodFilter').addEventListener('change', (e) => table.setFilter('method', e.target.value ? (r) => r.payment_method_label === e.target.value : null));

  // replace a row with the server's copy after an action, and refresh everything that counts orders
  function update(o, fresh) {
    Object.assign(o, shape(fresh));
    table.render();
    stats();
    renderSeg();
  }
  async function act(o, path, done) {
    try {
      update(o, (await api.post(`orders/${o.id}/${path}`)).data);
    } catch (err) {
      showFieldErrors(err);
      return false;
    }
    closeDrawer();
    toast(done);
    return true;
  }

  // order details drawer (invoice)
  function showOrder(o) {
    const at = (iso) => (iso ? date(iso.slice(0, 10)) : '');
    const steps = [
      o.refunded_at && `<li><span class="t-dot" style="background:var(--violet, #7c3aed)"></span><b>تم الاسترداد</b><p>أُعيد المبلغ وأُلغي الوصول</p><time>${at(o.refunded_at)}</time></li>`,
      o.paid_at && `<li><span class="t-dot" style="background:var(--success)"></span><b>تم الدفع</b><p>${esc(o.payment_method_label)}</p><time>${at(o.paid_at)}</time></li>`,
      o.status === 'failed' && `<li><span class="t-dot" style="background:var(--danger)"></span><b>فشل الدفع</b><p>${esc(o.payment_method_label)}</p><time></time></li>`,
      `<li><span class="t-dot"></span><b>أُنشئ الطلب</b><p>${esc(o.item_type_label)}</p><time>${at(o.created_at)}</time></li>`,
    ].filter(Boolean);
    const actions = canManage
      ? [
          o.status !== 'failed' && `<button class="btn btn-ghost" style="flex:1" id="sendInvoice">${icon('mail', 'sm')}إرسال الفاتورة</button>`,
          o.status === 'pending' && `<button class="btn btn-primary" style="flex:1" id="markPaid">${icon('check', 'sm')}تأكيد الدفع</button>`,
          o.status === 'pending' && `<button class="btn btn-danger-soft" style="flex:1" id="markFailed">${icon('close', 'sm')}فشل الدفع</button>`,
          o.status === 'completed' && `<button class="btn btn-danger-soft" style="flex:1" id="refund">${icon('refresh', 'sm')}استرداد المبلغ</button>`,
        ].filter(Boolean)
      : [];
    const dr = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">الطلب <span class="mono">${esc(o.number)}</span></h3><small class="muted">${date(o.date)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div style="display:flex;justify-content:space-between;align-items:center">${badge(o.status_label)}<b style="font-size:24px;color:var(--fg)">${money(o.total)}</b></div>
        <div class="card" style="padding:14px">${person({ name: o.customer, initial: o.initial, color: o.color, sub: o.email })}</div>
        <div>
          <div class="label" style="margin-bottom:8px">تفاصيل الفاتورة</div>
          <div class="card">
            <div class="list-item"><span class="grow">${esc(o.item_name)}<small style="display:block">${esc(o.item_type_label)}</small></span><b class="num">${money(o.subtotal)}</b></div>
            ${o.discount ? `<div class="list-item"><span class="grow muted">خصم ${esc(o.coupon_code || '')}</span><span class="num">-${money(o.discount)}</span></div>` : ''}
            ${o.fee ? `<div class="list-item"><span class="grow muted">رسوم بوابة الدفع</span><span class="num">-${money(o.fee)}</span></div>` : ''}
            <div class="list-item"><span class="grow"><b>صافي الدخل</b></span><b class="num" style="color:var(--success)">${money(+(o.total - o.fee).toFixed(2))}</b></div>
          </div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">الدفع</div>
          <div class="card" style="padding:14px;display:flex;align-items:center;gap:10px">${icon('card')}<span>${esc(o.payment_method_label)}</span></div>
        </div>
        <ul class="timeline" style="padding:0">${steps.join('')}</ul>
      </div>
      ${actions.length ? `<div class="drawer-foot">${actions.join('')}</div>` : ''}`);
    $('#sendInvoice', dr)?.addEventListener('click', async () => {
      try {
        toast((await api.post(`orders/${o.id}/invoice`)).message);
      } catch (err) {
        showFieldErrors(err);
      }
    });
    $('#markPaid', dr)?.addEventListener('click', async () => {
      if (await confirmDialog({ title: `تأكيد دفع ${money(o.total)}؟`, text: `سيحصل ${o.customer} على "${o.item_name}" فوراً.`, ok: 'تأكيد الدفع', danger: false })) act(o, 'payment', 'تم تأكيد الدفع');
    });
    $('#markFailed', dr)?.addEventListener('click', async () => {
      if (await confirmDialog({ title: 'تحديد الطلب كفاشل؟', text: 'سيُغلق الطلب ويعود كود الخصم (إن وُجد) للاستخدام.', ok: 'فشل الدفع' })) act(o, 'failure', 'تم إغلاق الطلب كفاشل');
    });
    $('#refund', dr)?.addEventListener('click', async () => {
      if (await confirmDialog({ title: `استرداد ${money(o.total)}؟`, text: `سيُلغى وصول ${o.customer} إلى "${o.item_name}". أعِد المبلغ من بوابة الدفع أيضاً.`, ok: 'استرداد' })) act(o, 'refund', 'تم استرداد الطلب');
    });
  }
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-open-order]');
    if (b) showOrder(rows.find((o) => o.id === Number(b.dataset.openOrder)));
  });

  $('#export').addEventListener('click', () =>
    App.downloadCSV('orders.csv', [
      { key: 'number', label: 'الطلب' }, { key: 'customer', label: 'العميل' }, { key: 'email', label: 'البريد' }, { key: 'item_name', label: 'المنتج' },
      { key: 'item_type_label', label: 'النوع' }, { key: 'payment_method_label', label: 'الدفع' }, { key: 'date', label: 'التاريخ' }, { key: 'subtotal', label: 'السعر' },
      { key: 'discount', label: 'الخصم' }, { key: 'coupon_code', label: 'الكوبون' }, { key: 'amount', label: 'المبلغ' }, { key: 'status_label', label: 'الحالة' },
    ], table.view),
  );

  stats();
});
