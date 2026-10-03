document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, money, date, icon, toast, openDrawer, closeDrawer, closeModal, confirmDialog } = App;
  const leads = DB.leads;
  const stages = DB.leadStages;

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
      <article class="k-card" draggable="true" data-id="${l.id}" tabindex="0" aria-label="${esc(l.company)}">
        <div style="display:flex;justify-content:space-between;gap:8px;align-items:flex-start">
          <h4>${esc(l.company)}</h4>
          <div class="dropdown">
            <button class="btn-icon" data-dropdown aria-label="نقل إلى" style="width:28px;height:28px">${icon('more', 'sm')}</button>
            <div class="menu">${stages.filter((s) => s.key !== l.stage).map((s) => `<button data-move="${s.key}" data-id="${l.id}"><i style="width:9px;height:9px;border-radius:50%;background:${s.color};display:inline-block"></i>نقل إلى: ${s.label}</button>`).join('')}</div>
          </div>
        </div>
        <span class="badge" style="width:fit-content">${esc(l.service)}</span>
        <p class="muted" style="font-size:13px">${esc(l.note)}</p>
        <div class="k-meta"><span>${icon('user', 'sm')} ${esc(l.name)}</span><span class="k-budget">${money(l.budget)}</span></div>
        <div class="k-meta"><span>${icon('clock', 'sm')} ${date(l.date)}</span><span class="mono">${l.id}</span></div>
      </article>`;
  }

  function render() {
    $('#board').innerHTML = stages
      .map((s) => {
        const items = leads.filter((l) => l.stage === s.key);
        return `
        <section class="k-col" data-stage="${s.key}" style="--k:${s.color}">
          <div class="k-head"><b>${s.label}</b><span class="badge">${items.length} · ${money(items.reduce((a, l) => a + l.budget, 0))}</span></div>
          ${items.map(card).join('') || '<div class="muted" style="text-align:center;font-size:13px;padding:20px 0">اسحب بطاقة إلى هنا</div>'}
        </section>`;
      })
      .join('');
    stats();
  }

  function move(id, stage) {
    const l = leads.find((x) => x.id === id);
    if (!l || l.stage === stage) return;
    l.stage = stage;
    render();
    toast(`نُقل "${l.company}" إلى: ${stages.find((s) => s.key === stage).label}`);
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
    if (!col) return;
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
  board.addEventListener('click', (e) => {
    const m = e.target.closest('[data-move]');
    if (m) return move(m.dataset.id, m.dataset.move);
    if (e.target.closest('.dropdown')) return;
    const c = e.target.closest('.k-card');
    if (c) details(leads.find((l) => l.id === c.dataset.id));
  });
  board.addEventListener('keydown', (e) => {
    if (e.key === 'Enter' && e.target.classList.contains('k-card')) details(leads.find((l) => l.id === e.target.dataset.id));
  });

  function details(l) {
    const dr = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">${esc(l.company)}</h3><small class="muted mono">${l.id}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div class="field"><label for="dStage">المرحلة</label><select class="select" id="dStage">${stages.map((s) => `<option value="${s.key}" ${s.key === l.stage ? 'selected' : ''}>${s.label}</option>`).join('')}</select></div>
        <div class="card">
          <div class="list-item"><span class="grow muted">العميل</span><b>${esc(l.name)}</b></div>
          <div class="list-item"><span class="grow muted">الخدمة</span><b>${esc(l.service)}</b></div>
          <div class="list-item"><span class="grow muted">الميزانية</span><b class="num">${money(l.budget)}</b></div>
          <div class="list-item"><span class="grow muted">تاريخ الطلب</span><b>${date(l.date)}</b></div>
        </div>
        <div class="field"><label>ملاحظات</label><textarea class="textarea" id="dNote" rows="4">${esc(l.note)}</textarea></div>
      </div>
      <div class="drawer-foot">
        <button class="btn btn-danger-soft" id="dDelete">${icon('trash', 'sm')}</button>
        <a class="btn btn-ghost" style="flex:1" href="${App.url('messages')}">${icon('chat', 'sm')}مراسلة</a>
        <button class="btn btn-primary" style="flex:1" id="dSave">حفظ</button>
      </div>`);
    $('#dSave', dr).addEventListener('click', () => {
      l.note = $('#dNote', dr).value;
      l.stage = $('#dStage', dr).value;
      closeDrawer();
      render();
      toast('تم حفظ الطلب');
    });
    $('#dDelete', dr).addEventListener('click', async () => {
      if (await confirmDialog({ title: 'حذف الطلب؟', text: `سيتم حذف طلب "${l.company}".`, ok: 'حذف' })) {
        leads.splice(leads.indexOf(l), 1);
        closeDrawer();
        render();
        toast('تم حذف الطلب');
      }
    });
  }

  $('#leadForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!$('#lName').value.trim()) {
      $('#lName').classList.add('invalid');
      $('#lName').focus();
      return toast('اسم العميل مطلوب', 'error');
    }
    const budget = Number($('#lBudget').value);
    if (!(budget >= 0)) {
      $('#lBudget').classList.add('invalid');
      $('#lBudget').focus();
      return toast('الميزانية لا يمكن أن تكون سالبة', 'error');
    }
    leads.unshift({
      id: `L-${Math.max(0, ...leads.map((l) => Number(l.id.slice(2)) || 0)) + 1}`,
      name: $('#lName').value.trim(),
      company: $('#lCompany').value.trim() || $('#lName').value.trim(),
      service: $('#lService').value,
      budget,
      stage: 'new',
      date: new Date().toISOString().slice(0, 10),
      note: $('#lNote').value.trim() || '—',
    });
    closeModal('leadModal');
    e.target.reset();
    render();
    toast('تمت إضافة الطلب إلى "جديد"');
  });

  render();
});
