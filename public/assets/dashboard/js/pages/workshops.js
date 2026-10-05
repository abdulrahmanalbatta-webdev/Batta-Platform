document.addEventListener('app:ready', async () => {
  const { $, $$, esc, num, money, date, badge, icon, toast, confirmDialog, openModal, closeModal, openDrawer, person, api, showFieldErrors } = App;
  const COLORS = ['#0066ff', '#0b0d12', '#334155', '#5c9dff', '#0e9f6e', '#7c3aed', '#c27803'];
  const canEdit = App.can('manage_content');
  let list = [];
  let filter = 'upcoming';
  let editing = null;
  const ended = (w) => w.state === 'ended';

  function stats() {
    const open = list.filter((w) => !ended(w));
    const seats = open.reduce((a, w) => a + w.seats, 0);
    const taken = open.reduce((a, w) => a + w.taken, 0);
    const revenue = list.reduce((a, w) => a + w.price * w.taken, 0);
    $('#wsStats').innerHTML = [
      ['calendar', 'c-blue', 'ورش قادمة', open.length],
      ['users', 'c-violet', 'مقاعد محجوزة', `${num(taken)} / ${num(seats)}`],
      ['trend', 'c-green', 'نسبة الإشغال', `${Math.round((taken / (seats || 1)) * 100)}%`],
      ['dollar', 'c-amber', 'إيرادات الورش', money(revenue)],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  // local "today" (not UTC), so the date picker's minimum matches the organiser's calendar
  const now = new Date();
  const today = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`;

  function render() {
    const rows = list
      .filter((w) => (filter === 'all' ? true : filter === 'past' ? ended(w) : !ended(w)))
      // upcoming: soonest first; past/all: most recent first
      .sort((a, b) => (filter === 'upcoming' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date)));
    $('#wsList').innerHTML =
      rows
        .map((w) => {
          const pct = Math.round((w.taken / w.seats) * 100);
          const menu = canEdit
            ? `<button data-act="attendees" data-id="${w.id}">${icon('users', 'sm')}المسجلون</button>
                <button data-act="edit" data-id="${w.id}">${icon('edit', 'sm')}تعديل</button>
                <button data-act="remind" data-id="${w.id}">${icon('mail', 'sm')}إرسال تذكير</button>
                <hr><button class="danger" data-act="delete" data-id="${w.id}">${icon('trash', 'sm')}حذف</button>`
            : `<button data-act="attendees" data-id="${w.id}">${icon('users', 'sm')}المسجلون</button>`;
          const [y, m, d] = w.date.split('-').map(Number);
          return `
        <article class="card" style="padding:18px;display:flex;flex-direction:column;gap:14px">
          <div style="display:flex;gap:14px;align-items:flex-start">
            <span class="kpi-ico c-ink" style="width:58px;height:62px;flex-direction:column;display:flex;align-items:center;justify-content:center;line-height:1.15;flex:none">
              <b style="font-size:22px;color:#fff">${d}</b><small style="font-size:11px;color:#c9d1dd">${date(w.date).split(' ')[1]}</small>
            </span>
            <div style="flex:1;min-width:0">
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px">${badge(w.state_label)}<span class="badge">${icon(w.format === 'online' ? 'monitor' : 'pin', 'sm')}${esc(w.format_label)} · ${esc(w.place)}</span></div>
              <h3 style="font-size:16px">${esc(w.title)}</h3>
              <small class="muted">${icon('clock', 'sm')} ${esc(w.time)} · ${w.price ? money(w.price) : 'مجانية'}</small>
            </div>
            <div class="dropdown">
              <button class="btn-icon" data-dropdown aria-label="المزيد">${icon('more', 'sm')}</button>
              <div class="menu">${menu}</div>
            </div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:6px"><span class="muted">المقاعد</span><b class="num">${w.taken} / ${w.seats}</b></div>
            <div class="progress ${pct >= 100 ? 'red' : pct > 75 ? 'amber' : ''}"><i style="width:${Math.min(pct, 100)}%"></i></div>
          </div>
          <div style="display:flex;gap:8px">
            <button class="btn btn-ghost btn-sm" data-act="attendees" data-id="${w.id}">${icon('users', 'sm')}عرض المسجلين</button>
            <button class="btn btn-soft btn-sm" data-act="copy" data-id="${w.id}">${icon('link', 'sm')}نسخ رابط التسجيل</button>
          </div>
        </article>`;
        })
        .join('') || `<div class="empty" style="grid-column:1/-1"><b>لا توجد ورش هنا</b>${canEdit ? 'أضف ورشة جديدة لتظهر في القائمة' : ''}</div>`;
  }

  $('#wsSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    $('#wsSeg').querySelectorAll('button').forEach((x) => x.classList.toggle('on', x === b));
    filter = b.dataset.s;
    render();
  });

  function fillForm(w) {
    editing = w;
    $$('#wsForm .invalid').forEach((i) => i.classList.remove('invalid'));
    $('#wDate').min = w ? '' : today;
    $('#wsModalTitle').textContent = w ? 'تعديل الورشة' : 'ورشة جديدة';
    $('#wTitle').value = w?.title ?? '';
    $('#wDate').value = w?.date ?? '';
    $('#wTime').value = w?.time ?? '19:00';
    $('#wFormat').value = w?.format ?? 'online';
    $('#wPlace').value = w?.place ?? 'Zoom';
    $('#wPrice').value = w?.price ?? 0;
    $('#wSeats').value = w?.seats ?? 40;
    $('#wDesc').value = w?.description ?? '';
  }
  document.querySelector('[data-open="workshopModal"]').addEventListener('click', () => fillForm(null));
  // switching online ↔ in-person swaps the default place
  $('#wFormat').addEventListener('change', (e) => {
    const place = $('#wPlace');
    if (e.target.value === 'online' && !place.value.trim()) place.value = 'Zoom';
    if (e.target.value === 'in-person' && place.value.trim() === 'Zoom') place.value = '';
    place.placeholder = e.target.value === 'online' ? 'Zoom / Google Meet' : 'المدينة أو العنوان';
  });
  $('#wsForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));

  $('#wsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const required = ['#wTitle', '#wDate', '#wSeats'];
    const missing = required.filter((sel) => !$(sel).value.trim());
    required.forEach((sel) => $(sel).classList.toggle('invalid', missing.includes(sel)));
    if (missing.length) return toast('أكمل الحقول المطلوبة', 'error');
    const data = {
      title: $('#wTitle').value.trim(),
      description: $('#wDesc').value.trim() || null,
      date: $('#wDate').value,
      time: $('#wTime').value,
      format: $('#wFormat').value,
      place: $('#wPlace').value.trim() || null,
      price: Number($('#wPrice').value),
      seats: Number($('#wSeats').value),
    };
    const fields = { title: '#wTitle', description: '#wDesc', date: '#wDate', time: '#wTime', format: '#wFormat', place: '#wPlace', price: '#wPrice', seats: '#wSeats' };
    const wasEditing = !!editing;
    try {
      const saved = (await (editing ? api.put(`workshops/${editing.id}`, data) : api.post('workshops', data))).data;
      if (editing) Object.assign(editing, saved);
      else list.push(saved);
    } catch (err) {
      return showFieldErrors(err, fields);
    }
    closeModal('workshopModal');
    stats();
    render();
    toast(wasEditing ? 'تم تحديث الورشة' : 'تمت إضافة الورشة');
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const w = list.find((x) => x.id === Number(btn.dataset.id));
    if (!w) return;
    const act = btn.dataset.act;
    if (act === 'edit') {
      fillForm(w);
      openModal('workshopModal');
    } else if (act === 'delete') {
      if (await confirmDialog({ title: 'حذف الورشة؟', text: w.taken ? `سيتم إلغاء "${w.title}" وإشعار ${w.taken} مسجلاً.` : `سيتم حذف "${w.title}" نهائياً.`, ok: 'حذف' })) {
        try {
          await api.delete(`workshops/${w.id}`);
        } catch (err) {
          return showFieldErrors(err);
        }
        list = list.filter((x) => x !== w);
        stats();
        render();
        toast('تم حذف الورشة');
      }
    } else if (act === 'remind') {
      if (!w.taken) return toast('لا يوجد مسجلون في هذه الورشة بعد', 'info');
      btn.disabled = true;
      try {
        const res = await api.post(`workshops/${w.id}/reminders`);
        toast(`تم إرسال تذكير إلى ${res.sent} مسجلاً`);
      } catch (err) {
        showFieldErrors(err);
      } finally {
        btn.disabled = false;
      }
    } else if (act === 'copy') {
      App.copy(`${App.siteUrl}/workshops/${w.code.toLowerCase()}`, 'تم نسخ رابط التسجيل');
    } else if (act === 'attendees') {
      let people;
      try {
        people = (await api.get(`workshops/${w.id}/registrations`)).data;
      } catch {
        return;
      }
      openDrawer(`
        <div class="drawer-head"><div><h3 style="font-size:17px">المسجلون (${people.length})</h3><small class="muted">${esc(w.title)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
        <div class="drawer-body" style="gap:0;padding:0">${
          people.length
            ? people.map((p) => `<div class="list-item">${person({ name: p.name, initial: p.initial, color: COLORS[p.student_id % COLORS.length], sub: p.email })}<span class="grow"></span><span class="badge success">${esc(p.order_number)}</span></div>`).join('')
            : `<div class="empty"><div class="e-ico">${icon('users')}</div><b>لا يوجد مسجلون بعد</b>سيظهر هنا كل من يحجز مقعداً في الورشة.</div>`
        }</div>`);
    }
  });

  try {
    list = (await api.get('workshops')).data;
  } catch {
    return;
  }
  stats();
  render();
  if (canEdit && location.hash === '#new') {
    fillForm(null);
    openModal('workshopModal');
  }
});
