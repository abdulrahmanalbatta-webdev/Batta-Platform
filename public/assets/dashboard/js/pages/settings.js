document.addEventListener('app:ready', () => {
  const { $, $$, esc, icon, toast, confirmDialog, closeModal } = App;
  const team = [
    { name: DB.admin.name, email: DB.admin.email, role: 'مالك', initial: 'ع', color: '#0b0d12', you: true },
    { name: 'سارة النجار', email: 'sara@batta.dev', role: 'محرر محتوى', initial: 'س', color: '#7c3aed' },
    { name: 'يوسف عودة', email: 'yousef@batta.dev', role: 'دعم فني', initial: 'ي', color: '#0e9f6e' },
  ];
  const sessions = [
    { device: 'Windows · Edge', place: 'غزة، فلسطين', time: 'الآن', current: true, ico: 'monitor' },
    { device: 'iPhone · Safari', place: 'غزة، فلسطين', time: 'منذ 3 ساعات', ico: 'phone' },
    { device: 'MacBook · Chrome', place: 'عمّان، الأردن', time: '28 سبتمبر', ico: 'monitor' },
  ];

  function renderTeam() {
    $('#team').innerHTML = team
      .map(
        (m, i) => `
      <div class="list-item">
        <div class="person grow"><span class="avatar" style="background:${m.color}">${esc(m.initial)}</span><div><b>${esc(m.name)}${m.you ? ' <span class="badge info" style="height:20px;font-size:11px">أنت</span>' : ''}${m.pending ? ' <span class="badge warning" style="height:20px;font-size:11px">دعوة معلّقة</span>' : ''}</b><small>${esc(m.email)}</small></div></div>
        ${m.you ? `<span class="badge">${m.role}</span>` : `<select class="select" style="width:auto;height:36px" data-role="${i}" aria-label="الصلاحية">${['مدير', 'محرر محتوى', 'دعم فني', 'محاسب'].map((r) => `<option ${r === m.role ? 'selected' : ''}>${r}</option>`).join('')}</select>
        <button type="button" class="btn-icon danger" data-remove="${i}" aria-label="إزالة">${icon('trash', 'sm')}</button>`}
      </div>`,
      )
      .join('');
  }

  function renderSessions() {
    $('#sessions').innerHTML = sessions
      .map(
        (s, i) => `
      <div class="list-item">
        <span class="kpi-ico c-blue" style="width:38px;height:38px">${icon(s.ico, 'sm')}</span>
        <span class="grow"><b>${s.device}${s.current ? ' <span class="badge dot success" style="height:20px;font-size:11px">هذا الجهاز</span>' : ''}</b><small>${s.place} · ${s.time}</small></span>
        ${s.current ? '' : `<button type="button" class="btn btn-sm btn-ghost" data-revoke="${i}">إنهاء</button>`}
      </div>`,
      )
      .join('');
  }

  // dirty tracking: the save button lights up after a change
  const form = $('#settingsForm');
  const save = $('#saveAll');
  let dirty = false;
  // team roles apply immediately, so they don't count as unsaved settings
  const counts = (e) => !e.target.closest('#team, #sessions');
  form.addEventListener('input', (e) => {
    if (!counts(e)) return;
    dirty = true;
    save.classList.add('pulse');
  });
  form.addEventListener('change', (e) => {
    if (!counts(e)) return;
    dirty = true;
    save.classList.add('pulse');
    if (e.target.id === 'maintenance') toast(e.target.checked ? 'سيُفعّل وضع الصيانة بعد الحفظ' : 'سيُلغى وضع الصيانة بعد الحفظ', 'info');
  });
  form.addEventListener('submit', (e) => {
    e.preventDefault();
    if (!/^https?:\/\/.+\..+/.test($('#siteUrl').value)) {
      $('[data-tab="general"]').click();
      $('#siteUrl').classList.add('invalid');
      return toast('الرابط غير صحيح', 'error');
    }
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test($('#email').value)) {
      $('[data-tab="general"]').click();
      $('#email').classList.add('invalid');
      return toast('البريد غير صحيح', 'error');
    }
    $$('.invalid', form).forEach((i) => i.classList.remove('invalid'));
    dirty = false;
    save.classList.remove('pulse');
    toast('تم حفظ الإعدادات');
  });
  window.addEventListener('beforeunload', (e) => {
    if (dirty) e.preventDefault();
  });

  // open a tab from the URL hash (/dashboard/settings#security)
  const tab = location.hash.slice(1);
  if (tab) $(`[data-tab="${CSS.escape(tab)}"]`)?.click();
  $('[data-tabs="settings"]').addEventListener('tabchange', (e) => history.replaceState(null, '', `#${e.detail}`));

  document.addEventListener('click', async (e) => {
    const gw = e.target.closest('[data-gw]');
    if (gw) toast(`إعدادات ${gw.dataset.gw} تتطلب ربط الخادم`, 'info');
    const rm = e.target.closest('[data-remove]');
    if (rm) {
      const m = team[rm.dataset.remove];
      if (await confirmDialog({ title: `إزالة ${m.name}؟`, text: 'سيفقد صلاحية الدخول إلى لوحة التحكم.', ok: 'إزالة' })) {
        team.splice(rm.dataset.remove, 1);
        renderTeam();
        toast('تمت إزالة العضو');
      }
    }
    const rv = e.target.closest('[data-revoke]');
    if (rv) {
      sessions.splice(rv.dataset.revoke, 1);
      renderSessions();
      toast('تم إنهاء الجلسة');
    }
  });
  $('#team').addEventListener('change', (e) => {
    const s = e.target.closest('[data-role]');
    if (!s) return;
    e.stopPropagation();
    team[s.dataset.role].role = s.value;
    toast(`أصبحت صلاحية ${team[s.dataset.role].name}: ${s.value}`);
  });

  $('#inviteForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = $('#iName').value.trim();
    const email = $('#iEmail').value.trim();
    if (!name || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return toast('أدخل اسماً وبريداً صحيحاً', 'error');
    team.push({ name, email, role: $('#iRole').value, initial: name[0], color: '#0066ff', pending: true });
    renderTeam();
    closeModal('inviteModal');
    e.target.reset();
    toast(`تم إرسال الدعوة إلى ${email}`);
  });

  $('#exportData').addEventListener('click', () => {
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob([JSON.stringify(DB, null, 2)], { type: 'application/json' }));
    a.download = 'batta-backup.json';
    a.click();
    toast('تم تجهيز نسخة البيانات');
  });
  $('#wipe').addEventListener('click', async () => {
    if (await confirmDialog({ title: 'حذف كل البيانات؟', text: 'هذا إجراء نهائي ولا يمكن التراجع عنه. (في هذه النسخة التجريبية لن يُحذف شيء)', ok: 'نعم، احذف' })) toast('هذه نسخة تجريبية — لم يُحذف شيء', 'info');
  });

  renderTeam();
  renderSessions();
});
