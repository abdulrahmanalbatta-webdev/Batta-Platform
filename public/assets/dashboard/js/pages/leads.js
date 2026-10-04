document.addEventListener('app:ready', async () => {
  const { $, $$, esc, money, date, icon, toast, openDrawer, closeDrawer, closeModal, confirmDialog, api, showFieldErrors } = App;
  const canManage = App.can('manage_leads');
  const canMessage = App.can('answer_messages');
  // the board's columns, in the order of App\Enums\LeadStage
  const stages = [
    { key: 'new', label: 'جديد', color: '#0066ff' },
    { key: 'contacted', label: 'تم التواصل', color: '#0891b2' },
    { key: 'proposal', label: 'عرض مُرسل', color: '#c27803' },
    { key: 'won', label: 'مقبول', color: '#0e9f6e' },
    { key: 'lost', label: 'مرفوض', color: '#e02424' },
  ];
  let leads;
  try {
    leads = (await api.get('leads')).data;
  } catch {
    return;
  }

  const replace = (lead) => leads.splice(leads.findIndex((l) => l.id === lead.id), 1, lead);
  const title = (l) => l.company || l.name;

  $('#leadForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));

  function stats() {
    App.setNavCount('leads', leads.filter((l) => l.stage === 'new').length);
    const open = leads.filter((l) => !['won', 'lost'].includes(l.stage));
    const won = leads.filter((l) => l.stage === 'won');
    const decided = leads.filter((l) => ['won', 'lost'].includes(l.stage)).length;
    $('#leadStats').innerHTML = [
      ['briefcase', 'c-blue', 'طلبات مفتوحة', open.length],
      ['dollar', 'c-amber', 'قيمة المفتوحة', money(open.reduce((s, l) => s + l.budget, 0))],
      ['check-circle', 'c-green', 'قيمة المقبولة', money(won.reduce((s, l) => s + l.budget, 0))],
      ['trend', 'c-violet', 'نسبة الإغلاق', `${Math.round((won.length / (decided || 1)) * 100)}%`],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  function card(l) {
    return `
      <article class="k-card" ${canManage ? 'draggable="true"' : ''} data-id="${l.id}" tabindex="0" aria-label="${esc(title(l))}">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
          <h4>${esc(title(l))}</h4>
          ${
            canManage
              ? `<div class="dropdown">
            <button class="btn-icon" data-dropdown aria-label="نقل إلى" style="width:28px;height:28px">${icon('more', 'sm')}</button>
            <div class="menu">${stages.filter((s) => s.key !== l.stage).map((s) => `<button data-move="${s.key}" data-id="${l.id}"><i style="width:9px;height:9px;border-radius:50%;background:${s.color};display:inline-block"></i>نقل إلى: ${s.label}</button>`).join('')}</div>
          </div>`
              : ''
          }
        </div>
        <span class="badge" style="width:fit-content">${esc(l.service_label)}</span>
        ${l.note ? `<p class="muted" style="font-size:13px">${esc(l.note)}</p>` : ''}
        <div class="k-meta"><span>${icon('user', 'sm')} ${esc(l.name)}</span><span class="k-budget">${money(l.budget)}</span></div>
        <div class="k-meta"><span>${icon('clock', 'sm')} ${date(l.date)}</span><span class="mono">${esc(l.number)}</span></div>
      </article>`;
  }

  function render() {
    $('#board').innerHTML = stages
      .map((s) => {
        const items = leads.filter((l) => l.stage === s.key);
        return `
        <section class="k-col" data-stage="${s.key}" style="--k:${s.color}">
          <div class="k-head"><b>${s.label}</b><span class="badge">${items.length} · ${money(items.reduce((a, l) => a + l.budget, 0))}</span></div>
          ${items.map(card).join('') || `<div class="muted" style="text-align:center;font-size:13px;padding:20px 0">${canManage ? 'اسحب بطاقة إلى هنا' : 'لا توجد طلبات'}</div>`}
        </section>`;
      })
      .join('');
    stats();
  }

  async function save(l, changes, message) {
    try {
      replace((await api.patch(`leads/${l.id}`, changes)).data);
    } catch (err) {
      showFieldErrors(err, { note: '#dNote' });
      return false;
    }
    render();
    if (message) toast(message);
    return true;
  }

  function move(id, stage) {
    const l = leads.find((x) => x.id === Number(id));
    if (!l || l.stage === stage) return;
    save(l, { stage }, `نُقل "${title(l)}" إلى: ${stages.find((s) => s.key === stage).label}`);
  }

  // drag & drop (desktop)
  const board = $('#board');
  board.addEventListener('dragstart', (e) => {
    const c = e.target.closest('.k-card');
    if (!c) return;
    c.classList.add('dragging');
    e.dataTransfer.setData('text/plain', c.dataset.id);
    e.dataTransfer.effectAllowed = 'move';
  });
  board.addEventListener('dragend', (e) => e.target.closest('.k-card')?.classList.remove('dragging'));
  board.addEventListener('dragover', (e) => {
    const col = e.target.closest('.k-col');
    if (!col || !canManage) return;
    e.preventDefault();
    $$('.k-col.over').forEach((c) => c !== col && c.classList.remove('over'));
    col.classList.add('over');
  });
  board.addEventListener('dragleave', (e) => {
    const col = e.target.closest('.k-col');
    if (col && !col.contains(e.relatedTarget)) col.classList.remove('over');
  });
  board.addEventListener('drop', (e) => {
    const col = e.target.closest('.k-col');
    if (!col) return;
    e.preventDefault();
    col.classList.remove('over');
    move(e.dataTransfer.getData('text/plain'), col.dataset.stage);
  });

  // menu "move to" (touch / keyboard) + open details
  const find = (id) => leads.find((l) => l.id === Number(id));
  board.addEventListener('click', (e) => {
    const m = e.target.closest('[data-move]');
    if (m) return move(m.dataset.id, m.dataset.move);
    if (e.target.closest('.dropdown')) return;
    const c = e.target.closest('.k-card');
    if (c) details(find(c.dataset.id));
  });
  board.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('k-card')) details(find(e.target.dataset.id));
  });

  // open (or start) the conversation with the client and go to it
  async function message(l) {
    try {
      const { data } = await api.post('conversations', { lead_id: l.id });
      location.href = App.url('messages', { c: data.id });
    } catch {
      // the toast already explains it
    }
  }

  function details(l) {
    const contact = [
      l.email ? `<div class="list-item"><span class="grow muted">البريد</span><a class="ltr" href="mailto:${esc(l.email)}">${esc(l.email)}</a></div>` : '',
      l.phone ? `<div class="list-item"><span class="grow muted">الهاتف</span><a class="ltr" href="tel:${esc(l.phone)}">${esc(l.phone)}</a></div>` : '',
    ].join('');
    const dr = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">${esc(title(l))}</h3><small class="muted mono">${esc(l.number)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div class="field"><label for="dStage">المرحلة</label><select class="select" id="dStage" ${canManage ? '' : 'disabled'}>${stages.map((s) => `<option value="${s.key}" ${s.key === l.stage ? 'selected' : ''}>${s.label}</option>`).join('')}</select></div>
        <div class="card">
          <div class="list-item"><span class="grow muted">العميل</span><b>${esc(l.name)}</b></div>
          ${contact}
          <div class="list-item"><span class="grow muted">الخدمة</span><b>${esc(l.service_label)}</b></div>
          <div class="list-item"><span class="grow muted">الميزانية</span><b class="num">${money(l.budget)}</b></div>
          <div class="list-item"><span class="grow muted">تاريخ الطلب</span><b>${date(l.date)}</b></div>
        </div>
        <div class="field"><label for="dNote">ملاحظات</label><textarea class="textarea" id="dNote" rows="4" ${canManage ? '' : 'readonly'}>${esc(l.note || '')}</textarea></div>
      </div>
      <div class="drawer-foot">
        ${canManage ? `<button class="btn btn-danger-soft" id="dDelete" aria-label="حذف">${icon('trash', 'sm')}</button>` : ''}
        ${canMessage && l.email ? `<button class="btn btn-ghost" style="flex:1" id="dMessage">${icon('chat', 'sm')}مراسلة</button>` : ''}
        ${canManage ? '<button class="btn btn-primary" style="flex:1" id="dSave">حفظ</button>' : ''}
      </div>`);
    $('#dMessage', dr)?.addEventListener('click', () => message(l));
    $('#dSave', dr)?.addEventListener('click', async () => {
      if (await save(l, { note: $('#dNote', dr).value.trim() || null, stage: $('#dStage', dr).value }, 'تم حفظ الطلب')) closeDrawer();
    });
    $('#dDelete', dr)?.addEventListener('click', async () => {
      if (!(await confirmDialog({ title: 'حذف الطلب؟', text: `سيتم حذف طلب "${title(l)}".`, ok: 'حذف' }))) return;
      try {
        await api.delete(`leads/${l.id}`);
      } catch {
        return;
      }
      leads.splice(leads.indexOf(l), 1);
      closeDrawer();
      render();
      toast('تم حذف الطلب');
    });
  }

  $('#leadForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = e.submitter;
    btn.disabled = true;
    try {
      const { data } = await api.post('leads', {
        name: $('#lName').value.trim(),
        company: $('#lCompany').value.trim() || null,
        email: $('#lEmail').value.trim() || null,
        phone: $('#lPhone').value.trim() || null,
        service: $('#lService').value,
        budget: $('#lBudget').value === '' ? 0 : Number($('#lBudget').value),
        note: $('#lNote').value.trim() || null,
      });
      leads.unshift(data);
    } catch (err) {
      showFieldErrors(err, { name: '#lName', email: '#lEmail', phone: '#lPhone', budget: '#lBudget', company: '#lCompany', note: '#lNote' });
      return;
    } finally {
      btn.disabled = false;
    }
    closeModal('leadModal');
    e.target.reset();
    render();
    toast('تمت إضافة الطلب إلى "جديد"');
  });

  render();
});
