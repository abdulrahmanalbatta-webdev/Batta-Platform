document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, money, date, badge, icon, toast, confirmDialog, openModal, closeModal, openDrawer, person } = App;
  let list = [...DB.workshops];
  let filter = 'upcoming';
  let editing = null;

  function stats() {
    const open = list.filter((w) => w.status !== 'منتهية');
    const seats = open.reduce((a, w) => a + w.seats, 0);
    const taken = open.reduce((a, w) => a + w.taken, 0);
    const revenue = list.reduce((a, w) => a + (w.price > 100 ? w.price : w.price * w.taken), 0);
    $('#wsStats').innerHTML = [
      ['calendar', 'c-blue', 'ورش قادمة', open.length],
      ['users', 'c-violet', 'مقاعد محجوزة', `${num(taken)} / ${num(seats)}`],
      ['trend', 'c-green', 'نسبة الإشغال', `${Math.round((taken / (seats || 1)) * 100)}%`],
      ['dollar', 'c-amber', 'إيرادات الورش', money(revenue)],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  const today = new Date().toISOString().slice(0, 10);
  // status follows the data: past date → ended, no seats left → full, otherwise open
  const statusOf = (w) => (w.date < today ? 'منتهية' : w.taken >= w.seats ? 'مكتملة' : 'مفتوحة');

  function render() {
    const rows = list
      .filter((w) => (filter === 'all' ? true : filter === 'past' ? w.status === 'منتهية' : w.status !== 'منتهية'))
      // upcoming: soonest first; past/all: most recent first
      .sort((a, b) => (filter === 'upcoming' ? a.date.localeCompare(b.date) : b.date.localeCompare(a.date)));
    $('#wsList').innerHTML =
      rows
        .map((w) => {
          const pct = Math.round((w.taken / w.seats) * 100);
          const [y, m, d] = w.date.split('-').map(Number);
          return `
        <article class="card" style="padding:18px;display:flex;flex-direction:column;gap:14px">
          <div style="display:flex;gap:14px;align-items:flex-start">
            <span class="kpi-ico c-ink" style="width:58px;height:62px;flex-direction:column;display:flex;align-items:center;justify-content:center;line-height:1.15;flex:none">
              <b style="font-size:22px;color:#fff">${d}</b><small style="font-size:11px;color:#c9d1dd">${date(w.date).split(' ')[1]}</small>
            </span>
            <div style="flex:1;min-width:0">
              <div style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:4px">${badge(w.status)}<span class="badge">${icon(w.format === 'أونلاين' ? 'monitor' : 'pin', 'sm')}${esc(w.format)} · ${esc(w.place)}</span></div>
              <h3 style="font-size:16px">${esc(w.title)}</h3>
              <small class="muted">${icon('clock', 'sm')} ${w.time} · ${w.price ? money(w.price) : 'مجانية'}</small>
            </div>
            <div class="dropdown">
              <button class="btn-icon" data-dropdown aria-label="المزيد">${icon('more', 'sm')}</button>
              <div class="menu">
                <button data-act="attendees" data-id="${w.id}">${icon('users', 'sm')}المسجلون</button>
                <button data-act="edit" data-id="${w.id}">${icon('edit', 'sm')}تعديل</button>
                <button data-act="remind" data-id="${w.id}">${icon('mail', 'sm')}إرسال تذكير</button>
                <hr><button class="danger" data-act="delete" data-id="${w.id}">${icon('trash', 'sm')}حذف</button>
              </div>
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
        .join('') || '<div class="empty" style="grid-column:1/-1"><b>لا توجد ورش هنا</b>أضف ورشة جديدة لتظهر في القائمة</div>';
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
    $('#wFormat').value = w?.format ?? 'أونلاين';
    $('#wPlace').value = w?.place ?? 'Zoom';
    $('#wPrice').value = w?.price ?? 0;
    $('#wSeats').value = w?.seats ?? 40;
  }
  document.querySelector('[data-open="workshopModal"]').addEventListener('click', () => fillForm(null));
  // switching online ↔ in-person swaps the default place
  $('#wFormat').addEventListener('change', (e) => {
    const place = $('#wPlace');
    if (e.target.value === 'أونلاين' && !place.value.trim()) place.value = 'Zoom';
    if (e.target.value === 'حضوري' && place.value.trim() === 'Zoom') place.value = '';
    place.placeholder = e.target.value === 'أونلاين' ? 'Zoom / Google Meet' : 'المدينة أو العنوان';
  });
  $('#wsForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));

  $('#wsForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const required = ['#wTitle', '#wDate', '#wSeats'];
    const missing = required.filter((s) => !$(s).value.trim());
    required.forEach((s) => $(s).classList.toggle('invalid', missing.includes(s)));
    if (missing.length) return toast('أكمل الحقول المطلوبة', 'error');
    const seats = Number($('#wSeats').value);
    const price = Number($('#wPrice').value);
    const taken = editing?.taken ?? 0;
    const fail = (sel, msg) => {
      $(sel).classList.add('invalid');
      $(sel).focus();
      toast(msg, 'error');
    };
    if (!editing && $('#wDate').value < today) return fail('#wDate', 'تاريخ الورشة الجديدة لا يمكن أن يكون في الماضي');
    if (!Number.isInteger(seats) || seats < 1) return fail('#wSeats', 'عدد المقاعد يجب أن يكون رقماً صحيحاً أكبر من صفر');
    if (seats < taken) return fail('#wSeats', `لا يمكن أن تقل المقاعد عن عدد المسجلين (${taken})`);
    if (!(price >= 0)) return fail('#wPrice', 'السعر لا يمكن أن يكون سالباً');
    const data = {
      title: $('#wTitle').value.trim(),
      date: $('#wDate').value,
      time: $('#wTime').value,
      format: $('#wFormat').value,
      place: $('#wPlace').value.trim() || ($('#wFormat').value === 'أونلاين' ? 'Zoom' : '—'),
      price,
      seats,
    };
    const wasEditing = !!editing;
    if (editing) Object.assign(editing, data, { status: statusOf({ ...editing, ...data }) });
    else {
      const next = Math.max(0, ...list.map((w) => Number(w.id.slice(2)) || 0)) + 1;
      list.unshift({ id: `W-${next}`, taken: 0, ...data, status: statusOf({ ...data, taken: 0 }) });
    }
    closeModal('workshopModal');
    stats();
    render();
    toast(wasEditing ? 'تم تحديث الورشة' : 'تمت إضافة الورشة');
  });

  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-act]');
    if (!btn) return;
    const w = list.find((x) => x.id === btn.dataset.id);
    if (!w) return;
    const act = btn.dataset.act;
    if (act === 'edit') {
      fillForm(w);
      openModal('workshopModal');
    } else if (act === 'delete') {
      if (await confirmDialog({ title: 'حذف الورشة؟', text: `سيتم إلغاء "${w.title}" وإشعار ${w.taken} مسجلاً.`, ok: 'حذف' })) {
        list = list.filter((x) => x !== w);
        stats();
        render();
        toast('تم حذف الورشة');
      }
    } else if (act === 'remind') {
      toast(`تم إرسال تذكير إلى ${w.taken} مسجلاً`);
    } else if (act === 'copy') {
      App.copy(`https://batta.dev/workshops/${w.id.toLowerCase()}`, 'تم نسخ رابط التسجيل');
    } else if (act === 'attendees') {
      const people = DB.students.slice(0, Math.min(w.taken, 12));
      openDrawer(`
        <div class="drawer-head"><div><h3 style="font-size:17px">المسجلون</h3><small class="muted">${esc(w.title)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
        <div class="drawer-body" style="gap:0;padding:0">
          ${people.map((p) => `<div class="list-item">${person({ name: p.name, initial: p.initial, color: p.color, sub: p.email })}<span class="grow"></span><span class="badge success">مؤكد</span></div>`).join('')}
          ${w.taken > people.length ? `<div class="list-item muted">و ${w.taken - people.length} آخرون…</div>` : ''}
        </div>
        <div class="drawer-foot"><button class="btn btn-primary btn-block" data-close-drawer onclick="App.toast('تم إرسال رسالة لكل المسجلين')"><i data-icon="mail" class="sm"></i>مراسلة الكل</button></div>`);
    }
  });

  stats();
  render();
  if (location.hash === '#new') {
    fillForm(null);
    openModal('workshopModal');
  }
});
