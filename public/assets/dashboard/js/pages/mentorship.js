document.addEventListener('app:ready', async () => {
  const { $, esc, num, badge, icon, toast, confirmDialog, api, showFieldErrors, openDrawer, closeDrawer } = App;
  const statuses = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'جديد' },
    { key: 'accepted', label: 'بانتظار الدفع' },
    { key: 'proof_submitted', label: 'مراجعة الدفع' },
    { key: 'paid', label: 'مدفوع' },
    { key: 'scheduled', label: 'تم تحديد الموعد' },
    { key: 'completed', label: 'مكتمل' },
    { key: 'closed', label: 'مرفوض أو منتهي', match: ['rejected', 'expired'] },
  ];
  let requests;
  let meta;
  try {
    ({ data: requests, meta } = await api.get('mentorship-requests'));
  } catch {
    return;
  }
  let current = 'all';

  // times are shown and entered in Palestine's time, where the students are
  const when = (iso) => (iso ? new Intl.DateTimeFormat('ar', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Asia/Gaza', numberingSystem: 'latn' }).format(new Date(iso)) : '—');
  const shekel = (n) => `${num(n)} ₪`;
  const inStatus = (r, s) => s.key === 'all' || (s.match || [s.key]).includes(r.status);
  const replace = (request) => requests.splice(requests.findIndex((r) => r.id === request.id), 1, request);

  async function reloadMeta() {
    try {
      meta = (await api.get('mentorship-requests')).meta;
    } catch {
      return;
    }
    renderSeats();
  }

  function renderSeats() {
    $('#seats').hidden = false;
    $('#seats').textContent = `المقاعد المتاحة: ${num(meta.seats_left)} من ${num(meta.capacity)}`;
  }

  function renderSeg() {
    $('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s.key}" class="${current === s.key ? 'on' : ''}">${s.label}<span class="n">${requests.filter((r) => inStatus(r, s)).length}</span></button>`)
      .join('');
  }

  function render() {
    const status = statuses.find((s) => s.key === current);
    const list = requests.filter((r) => inStatus(r, status));
    $('#requestRows').innerHTML =
      list
        .map(
          (r) => `
      <tr data-open="${r.id}" style="cursor:pointer">
        <td class="mono">${esc(r.number)}</td>
        <td><b>${esc(r.student.name)}</b><small class="muted" style="display:block">${esc(r.student.email)}</small></td>
        <td>${esc(r.package_name)}</td>
        <td class="num">${shekel(r.price)}</td>
        <td>${badge(r.status_label)}</td>
        <td class="nowrap">${when(r.created_at)}</td>
      </tr>`,
        )
        .join('') || `<tr><td colspan="6"><div class="empty"><div class="e-ico">${icon('award')}</div><b>لا توجد طلبات</b>تظهر هنا طلبات الإرشاد التي تصل من الموقع.</div></td></tr>`;
  }

  const refresh = () => {
    App.setNavCount('mentorship', requests.filter((r) => ['pending', 'proof_submitted'].includes(r.status)).length);
    renderSeg();
    render();
  };

  /* ---------- one request: details and the next step ---------- */
  const row = (label, value) => (value ? `<div class="list-item"><span class="muted" style="min-width:110px">${label}</span><span class="grow" style="white-space:pre-line">${value}</span></div>` : '');
  // the session time box is in Palestine's time whatever the browser's timezone: "2026-11-03T19:00" ⇄ ISO
  const gazaParts = (date) => Object.fromEntries(new Intl.DateTimeFormat('en-US', { timeZone: 'Asia/Gaza', hourCycle: 'h23', year: 'numeric', month: '2-digit', day: '2-digit', hour: '2-digit', minute: '2-digit' }).formatToParts(date).map((p) => [p.type, p.value]));
  const localInput = (iso) => {
    if (!iso) return '';
    const p = gazaParts(new Date(iso));
    return `${p.year}-${p.month}-${p.day}T${p.hour}:${p.minute}`;
  };
  const fromGaza = (value) => {
    const [d, t] = value.split('T');
    const [y, m, day] = d.split('-').map(Number);
    const [h, min] = t.split(':').map(Number);
    const asUtc = Date.UTC(y, m - 1, day, h, min);
    const p = gazaParts(new Date(asUtc));
    const offset = Date.UTC(+p.year, +p.month - 1, +p.day, +p.hour, +p.minute) - asUtc;
    return new Date(asUtc - offset).toISOString();
  };

  function steps(r) {
    const reject = `<button type="button" class="btn btn-ghost" data-step="reject">${icon('x-circle', 'sm')}رفض الطلب</button>`;
    switch (r.status) {
      case 'pending':
        return `
          <div class="field"><label for="mNote">رسالة للطالب عند الرفض (اختياري)</label><textarea class="textarea" id="mNote" rows="2" maxlength="1000"></textarea></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn btn-primary" data-step="accept">${icon('check', 'sm')}قبول الطلب</button>${reject}</div>`;
      case 'accepted':
        return `
          <p class="muted">بانتظار تحويل ${shekel(r.price)} ورفع الإشعار قبل ${when(r.payment_due_at)}.</p>
          <div class="field"><label for="mNote">رسالة للطالب عند الرفض (اختياري)</label><textarea class="textarea" id="mNote" rows="2" maxlength="1000"></textarea></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn btn-soft" data-step="confirm_payment">${icon('check-circle', 'sm')}وصلني المبلغ</button>${reject}</div>`;
      case 'proof_submitted':
        return `
          <a class="btn btn-ghost" href="${esc(r.proof_url)}" target="_blank" rel="noopener">${icon('external', 'sm')}فتح إشعار التحويل</a>
          <p class="muted">تأكّد أن ${shekel(r.price)} وصلت فعلاً إلى محفظتك قبل التأكيد.</p>
          <div class="field"><label for="mNote">سبب رفض الإشعار (يصل للطالب)</label><textarea class="textarea" id="mNote" rows="2" maxlength="1000" placeholder="مثلاً: المبلغ ناقص، أو الصورة غير واضحة"></textarea></div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn btn-primary" data-step="confirm_payment">${icon('check-circle', 'sm')}تأكيد الدفع</button><button type="button" class="btn btn-ghost" data-step="reject_proof">${icon('refresh', 'sm')}رفض الإشعار</button>${reject}</div>`;
      case 'paid':
      case 'scheduled':
        return `
          <div class="form-grid">
            <div class="field"><label for="mSession">موعد الجلسة (بتوقيت فلسطين)</label><input class="input ltr" id="mSession" type="datetime-local" value="${localInput(r.session_at)}"></div>
            <div class="field"><label for="mMeeting">رابط اللقاء</label><input class="input ltr" id="mMeeting" type="url" placeholder="https://meet.google.com/…" value="${esc(r.meeting_url || '')}"></div>
          </div>
          <div style="display:flex;gap:8px;flex-wrap:wrap"><button type="button" class="btn btn-primary" data-step="schedule">${icon('calendar', 'sm')}${r.status === 'scheduled' ? 'تعديل الموعد' : 'حفظ الموعد'}</button><button type="button" class="btn btn-ghost" data-step="complete">${icon('check', 'sm')}اكتمل الإرشاد</button></div>`;
      default:
        return '';
    }
  }

  function open(r) {
    const s = r.student;
    const dr = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">${esc(s.name)}</h3><small class="muted mono">${esc(r.number)}</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body">
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">${badge(r.status_label)}<b>${esc(r.package_name)}</b><span class="muted">· ${shekel(r.price)}</span></div>
        <div class="card">
          ${row('البريد', `<span class="ltr">${esc(s.email)}</span>`)}
          ${row('الهاتف', s.phone ? `<a class="ltr" href="${esc(s.whatsapp_url || '#')}" target="_blank" rel="noopener">${esc(s.phone)}</a>` : '')}
          ${row('هدفه', esc(r.goal))}
          ${row('مستواه الحالي', esc(r.background))}
          ${row('روابط', esc(r.links || ''))}
          ${row('الأوقات المناسبة', esc(r.availability))}
          ${row('رقم العملية', r.transaction_ref ? `<span class="ltr">${esc(r.transaction_ref)}</span>` : '')}
          ${row('رسالة للطالب', esc(r.decision_note || ''))}
          ${row('تاريخ الطلب', when(r.created_at))}
          ${row('الدفع', r.paid_at ? when(r.paid_at) : '')}
          ${row('الموعد', r.session_at ? when(r.session_at) : '')}
        </div>
        ${steps(r) ? `<div style="display:flex;flex-direction:column;gap:12px">${steps(r)}</div>` : ''}
        <div class="field"><label for="mNotes">ملاحظاتك (لا تظهر للطالب)</label><textarea class="textarea" id="mNotes" rows="3" maxlength="5000">${esc(r.notes || '')}</textarea></div>
        <div style="display:flex;gap:8px"><button type="button" class="btn btn-ghost btn-sm" data-save-notes>${icon('save', 'sm')}حفظ الملاحظات</button><span class="grow"></span><button type="button" class="btn btn-sm btn-danger-soft" data-delete>${icon('trash', 'sm')}حذف الطلب</button></div>
      </div>`);

    dr.addEventListener('click', async (e) => {
      const step = e.target.closest('[data-step]')?.dataset.step;
      if (step) return move(r, step, dr);
      if (e.target.closest('[data-save-notes]')) {
        try {
          replace((await api.put(`mentorship-requests/${r.id}`, { notes: $('#mNotes', dr).value })).data);
        } catch (err) {
          return showFieldErrors(err);
        }
        return toast('تم حفظ الملاحظات');
      }
      if (e.target.closest('[data-delete]')) {
        if (!(await confirmDialog({ title: 'حذف الطلب؟', text: `طلب ${s.name} وإشعار التحويل المرفق به سيُحذفان نهائياً.`, ok: 'حذف' }))) return;
        try {
          await api.delete(`mentorship-requests/${r.id}`);
        } catch {
          return;
        }
        requests.splice(requests.indexOf(r), 1);
        closeDrawer();
        toast('تم حذف الطلب');
        refresh();
        reloadMeta();
      }
    });
  }

  const DONE = {
    accept: 'تم قبول الطلب، ووصل الطالب بريد بتفاصيل الدفع',
    reject: 'تم رفض الطلب وإبلاغ الطالب',
    confirm_payment: 'تم تأكيد الدفع وإبلاغ الطالب',
    reject_proof: 'تم رفض الإشعار، وطُلب من الطالب إشعار جديد',
    schedule: 'تم حفظ الموعد وإرساله للطالب',
    complete: 'تم إكمال الإرشاد',
  };

  async function move(r, action, dr) {
    const body = { action };
    const note = $('#mNote', dr)?.value.trim();
    if (note) body.note = note;
    if (action === 'reject_proof' && !note) return toast('اكتب سبب رفض الإشعار', 'error');
    if (action === 'schedule') {
      const value = $('#mSession', dr).value;
      if (!value) return toast('اختر موعد الجلسة', 'error');
      body.session_at = fromGaza(value);
      body.meeting_url = $('#mMeeting', dr).value.trim() || null;
    }
    if (action === 'reject' && !(await confirmDialog({ title: 'رفض الطلب؟', text: 'سيصل الطالب بريد بالرفض.', ok: 'رفض' }))) return;
    try {
      replace((await api.put(`mentorship-requests/${r.id}/status`, body)).data);
    } catch (err) {
      return showFieldErrors(err);
    }
    toast(DONE[action]);
    closeDrawer();
    refresh();
    reloadMeta();
  }

  $('#requestRows').addEventListener('click', (e) => {
    const tr = e.target.closest('[data-open]');
    if (tr) open(requests.find((r) => r.id === Number(tr.dataset.open)));
  });
  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    renderSeg();
    render();
  });

  renderSeats();
  refresh();
  // opened from a bell notification: ?request=12
  const linked = requests.find((r) => r.id === Number(new URLSearchParams(location.search).get('request')));
  if (linked) open(linked);

  /* ---------- settings ---------- */
  const WALLETS = { palpay: 'بال باي', jawwalpay: 'جوال باي' };
  let settings;

  function packageCard(p, i) {
    return `
      <div class="card card-body" data-package="${i}" data-id="${esc(p.id || '')}">
        <div class="form-grid">
          <div class="field"><label>اسم الباقة</label><input class="input" data-k="name" maxlength="80" value="${esc(p.name)}"></div>
          <div class="field"><label>السعر (₪)</label><input class="input ltr" data-k="price" type="number" min="0" value="${esc(p.price)}"></div>
          <div class="field"><label>المدة</label><input class="input" data-k="period" maxlength="30" placeholder="مثلاً: شهرياً، أو 3 أشهر" value="${esc(p.period || '')}"></div>
          <div class="field"><label class="check" style="margin-top:30px"><input type="checkbox" data-k="featured" ${p.featured ? 'checked' : ''}> الأكثر طلباً</label></div>
          <div class="field full"><label>الوصف</label><input class="input" data-k="description" maxlength="300" value="${esc(p.description)}"></div>
          <div class="field full"><label>المميزات (ميزة في كل سطر)</label><textarea class="textarea" data-k="features" rows="4">${esc((p.features || []).join('\n'))}</textarea></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:8px"><button type="button" class="btn btn-sm btn-danger-soft" data-remove-package>${icon('trash', 'sm')}حذف الباقة</button></div>
      </div>`;
  }

  function renderSettings() {
    $('#enabled').checked = settings.enabled;
    $('#capacity').value = settings.capacity;
    $('#paymentHours').value = settings.payment_hours;
    $('#policy').value = settings.policy;
    $('#wallets').innerHTML = Object.entries(WALLETS)
      .map(
        ([id, name]) => `
        <div class="field"><label for="w-${id}-number">رقم ${name}</label><input class="input ltr" id="w-${id}-number" data-wallet="${id}" data-k="number" placeholder="059 000 0000" value="${esc(settings.wallets[id]?.number || '')}"></div>
        <div class="field"><label for="w-${id}-holder">الاسم المسجّل على ${name}</label><input class="input" id="w-${id}-holder" data-wallet="${id}" data-k="holder" maxlength="80" value="${esc(settings.wallets[id]?.holder || '')}"></div>`,
      )
      .join('');
    $('#packages').innerHTML = settings.packages.map(packageCard).join('');
  }

  function collect() {
    const wallets = {};
    Object.keys(WALLETS).forEach((id) => {
      wallets[id] = { number: $(`[data-wallet="${id}"][data-k="number"]`).value.trim() || null, holder: $(`[data-wallet="${id}"][data-k="holder"]`).value.trim() || null };
    });
    const packages = [...document.querySelectorAll('[data-package]')].map((card) => {
      const v = (k) => card.querySelector(`[data-k="${k}"]`);
      return {
        ...(card.dataset.id ? { id: card.dataset.id } : {}),
        name: v('name').value.trim(),
        price: Number(v('price').value),
        period: v('period').value.trim(),
        description: v('description').value.trim(),
        features: v('features').value.split('\n').map((f) => f.trim()).filter(Boolean),
        featured: v('featured').checked,
      };
    });
    return { enabled: $('#enabled').checked, capacity: Number($('#capacity').value), payment_hours: Number($('#paymentHours').value), wallets, packages, policy: $('#policy').value.trim() };
  }

  try {
    settings = (await api.get('mentorship-settings')).data;
  } catch {
    return;
  }
  renderSettings();

  $('#addPackage').addEventListener('click', () => {
    if (document.querySelectorAll('[data-package]').length >= 6) return toast('6 باقات كحد أقصى', 'error');
    $('#packages').insertAdjacentHTML('beforeend', packageCard({ name: '', price: 0, period: '', description: '', features: [], featured: false }, document.querySelectorAll('[data-package]').length));
    $('#packages [data-package]:last-child [data-k="name"]').focus();
  });
  $('#packages').addEventListener('click', (e) => {
    if (!e.target.closest('[data-remove-package]')) return;
    if (document.querySelectorAll('[data-package]').length === 1) return toast('أبقِ باقة واحدة على الأقل', 'error');
    e.target.closest('[data-package]').remove();
  });
  $('#settingsForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const values = collect();
    if (values.enabled && !values.wallets.palpay.number && !values.wallets.jawwalpay.number) return toast('أضف رقم محفظة واحدة على الأقل قبل فتح الطلبات', 'error');
    try {
      settings = (await api.put('mentorship-settings', values)).data;
    } catch (err) {
      return showFieldErrors(err);
    }
    renderSettings();
    toast('تم حفظ إعدادات الإرشاد');
    reloadMeta();
  });
});
