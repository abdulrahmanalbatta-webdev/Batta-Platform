document.addEventListener('app:ready', () => {
  const { $, esc, num, money, date, badge, icon, person, toast, confirmDialog, openDrawer, closeDrawer, DataTable } = App;
  const rows = DB.orders;

  function stats() {
    const done = rows.filter((o) => o.status === 'مكتمل');
    const revenue = done.reduce((s, o) => s + o.amount, 0);
    const refunds = rows.filter((o) => o.status === 'مسترد');
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
    searchKeys: ['id', 'customer', 'email', 'item'],
    columns: [
      { key: 'id', label: 'الطلب', sortable: true, render: (o) => `<button class="link mono" style="border:0;background:none;padding:0" data-open-order="${o.id}">${o.id}</button>` },
      { key: 'customer', label: 'العميل', sortable: true, render: (o) => person({ name: o.customer, initial: o.initial, color: o.color, sub: o.email }) },
      { key: 'item', label: 'المنتج', render: (o) => `<b style="color:var(--fg);font-weight:600">${esc(o.item)}</b><div><span class="badge" style="height:22px;font-size:11.5px">${o.type}</span></div>` },
      { key: 'method', label: 'الدفع', render: (o) => `<span class="nowrap">${icon('card', 'sm')} ${o.method}</span>` },
      { key: 'date', label: 'التاريخ', sortable: true, render: (o) => `<span class="nowrap">${date(o.date)}</span>` },
      { key: 'amount', label: 'المبلغ', sortable: true, className: 'num', render: (o) => money(o.amount) },
      { key: 'status', label: 'الحالة', sortable: true, render: (o) => badge(o.status) },
      { key: '', label: '', className: 'actions', render: (o) => `<button class="btn-icon" data-open-order="${o.id}" aria-label="التفاصيل">${icon('chevron-left', 'sm')}</button>` },
    ],
  });

  const statuses = ['الكل', 'مكتمل', 'معلّق', 'مسترد', 'فشل'];
  let current = 'الكل';
  const renderSeg = () =>
    ($('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s}" class="${current === s ? 'on' : ''}">${s}<span class="n">${s === 'الكل' ? rows.length : rows.filter((r) => r.status === s).length}</span></button>`)
      .join(''));
  renderSeg();
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    table.setFilter('status', current === 'الكل' ? null : (r) => r.status === current);
    renderSeg();
  });
  $('#q').addEventListener('input', App.debounce((e) => table.setQuery(e.target.value), 150));
  $('#typeFilter').addEventListener('change', (e) => table.setFilter('type', e.target.value ? (r) => r.type === e.target.value : null));
  $('#methodFilter').addEventListener('change', (e) => table.setFilter('method', e.target.value ? (r) => r.method === e.target.value : null));

  // order details drawer (invoice)
  function showOrder(o) {
    const fee = +(o.amount * 0.05).toFixed(2);
    const dr = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">الطلب <span class="mono">${o.id}</span></h3><small class="muted">${date(o.date)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div style="display:flex;justify-content:space-between;align-items:center">${badge(o.status)}<b style="font-size:24px;color:var(--fg)">${money(o.amount)}</b></div>
        <div class="card" style="padding:14px">${person({ name: o.customer, initial: o.initial, color: o.color, sub: o.email })}</div>
        <div>
          <div class="label" style="margin-bottom:8px">تفاصيل الفاتورة</div>
          <div class="card">
            <div class="list-item"><span class="grow">${esc(o.item)}<small style="display:block">${o.type}</small></span><b class="num">${money(o.amount)}</b></div>
            <div class="list-item"><span class="grow muted">رسوم بوابة الدفع (5%)</span><span class="num">-${fee}$</span></div>
            <div class="list-item"><span class="grow"><b>صافي الدخل</b></span><b class="num" style="color:var(--success)">${(o.amount - fee).toFixed(2)}$</b></div>
          </div>
        </div>
        <div>
          <div class="label" style="margin-bottom:8px">الدفع</div>
          <div class="card" style="padding:14px;display:flex;align-items:center;gap:10px">${icon('card')}<span>${o.method}</span><span class="grow"></span><span class="mono muted">•••• 4242</span></div>
        </div>
        <ul class="timeline" style="padding:0">
          <li><span class="t-dot" style="background:var(--success)"></span><b>تم الدفع</b><p>${o.method}</p><time>${date(o.date)}</time></li>
          <li><span class="t-dot"></span><b>أُنشئ الطلب</b><p>من صفحة الدورة</p><time>${date(o.date)}</time></li>
        </ul>
      </div>
      <div class="drawer-foot">
        <button class="btn btn-ghost" style="flex:1" id="sendInvoice">${icon('mail', 'sm')}إرسال الفاتورة</button>
        ${o.status === 'مكتمل' ? `<button class="btn btn-danger-soft" style="flex:1" id="refund">${icon('refresh', 'sm')}استرداد المبلغ</button>` : ''}
      </div>`);
    $('#sendInvoice', dr).addEventListener('click', () => toast(`تم إرسال الفاتورة إلى ${o.email}`));
    $('#refund', dr)?.addEventListener('click', async () => {
      if (await confirmDialog({ title: `استرداد ${money(o.amount)}؟`, text: `سيُعاد المبلغ إلى ${o.customer} ويُلغى وصوله للدورة.`, ok: 'استرداد' })) {
        o.status = 'مسترد';
        closeDrawer();
        table.render();
        stats();
        renderSeg();
        toast('تم استرداد المبلغ');
      }
    });
  }
  document.addEventListener('click', (e) => {
    const b = e.target.closest('[data-open-order]');
    if (b) showOrder(rows.find((o) => o.id === b.dataset.openOrder));
  });

  $('#export').addEventListener('click', () =>
    App.downloadCSV('orders.csv', [
      { key: 'id', label: 'الطلب' }, { key: 'customer', label: 'العميل' }, { key: 'email', label: 'البريد' }, { key: 'item', label: 'المنتج' },
      { key: 'type', label: 'النوع' }, { key: 'method', label: 'الدفع' }, { key: 'date', label: 'التاريخ' }, { key: 'amount', label: 'المبلغ' }, { key: 'status', label: 'الحالة' },
    ], table.view),
  );

  stats();
});
