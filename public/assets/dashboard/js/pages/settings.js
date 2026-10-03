document.addEventListener('app:ready', () => {
  const { $, $$, esc, icon, toast, confirmDialog, closeModal, api, showFieldErrors } = App;
  /* ---------- team & devices (from the API) ---------- */
  const COLORS = ['#0b0d12', '#7c3aed', '#0e9f6e', '#0066ff', '#c27803', '#0891b2'];
  const BADGE = 'style="height:20px;font-size:11px"';
  let team = [];
  let roles = [];
  let sessions = [];

  function renderTeam() {
    $('#team').innerHTML = team
      .map((m) => {
        const role = m.can.update
          ? `<select class="select" style="width:auto;min-width:150px;height:36px" data-role="${m.id}" aria-label="صلاحية ${esc(m.name)}">${roles
              .map((r) => `<option value="${esc(r.value)}" ${r.value === m.role ? 'selected' : ''}>${esc(r.label)}</option>`)
              .join('')}</select>`
          : `<span class="badge">${esc(m.role_label)}</span>`;
        const remove = m.can.delete ? `<button type="button" class="btn-icon danger" data-remove="${m.id}" aria-label="إزالة ${esc(m.name)}">${icon('trash', 'sm')}</button>` : '';
        return `
      <div class="list-item">
        <div class="person grow"><span class="avatar" style="background:${COLORS[m.id % COLORS.length]}">${m.avatar_url ? `<img src="${esc(m.avatar_url)}" alt="">` : ''}${esc(m.initial)}</span><div><b>${esc(m.name)}${m.is_you ? ` <span class="badge info" ${BADGE}>أنت</span>` : ''}${m.pending ? ` <span class="badge warning" ${BADGE}>دعوة معلّقة</span>` : ''}</b><small>${esc(m.email)}</small></div></div>
        ${role}${remove}
      </div>`;
      })
      .join('');
  }

  function renderSessions(tracked = true) {
    $('#sessions').innerHTML = !tracked
      ? '<div class="list-item"><span class="muted">قائمة الأجهزة تحتاج SESSION_DRIVER=database.</span></div>'
      : sessions
          .map(
            (s) => `
      <div class="list-item">
        <span class="kpi-ico c-blue" style="width:38px;height:38px">${icon(s.is_mobile ? 'phone' : 'monitor', 'sm')}</span>
        <span class="grow"><b>${esc(s.device)}${s.is_current ? ` <span class="badge dot success" ${BADGE}>هذا الجهاز</span>` : ''}</b><small class="ltr" style="display:inline-block">${esc(s.ip_address || '')}</small><small> · ${esc(s.last_active)}</small></span>
        ${s.is_current ? '' : `<button type="button" class="btn btn-sm btn-ghost" data-revoke="${esc(s.id)}">إنهاء</button>`}
      </div>`,
          )
          .join('');
  }

  async function loadTeam() {
    const res = await api.get('team');
    team = res.data;
    roles = res.meta.roles;
    $('#inviteBtn').hidden = !res.meta.can_invite;
    $('#iRole').innerHTML = roles.map((r) => `<option value="${esc(r.value)}">${esc(r.label)}</option>`).join('');
    renderTeam();
  }
  async function loadSessions() {
    const res = await api.get('sessions');
    sessions = res.data;
    renderSessions(res.meta.tracked);
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
      const m = team.find((x) => x.id === Number(rm.dataset.remove));
      if (!(await confirmDialog({ title: `إزالة ${m.name}؟`, text: 'سيفقد صلاحية الدخول إلى لوحة التحكم ويُسجَّل خروجه من كل أجهزته.', ok: 'إزالة' }))) return;
      try {
        await api.delete(`team/${m.id}`);
      } catch {
        return;
      }
      team = team.filter((x) => x !== m);
      renderTeam();
      toast('تمت إزالة العضو');
    }
    const rv = e.target.closest('[data-revoke]');
    if (rv) {
      try {
        await api.delete(`sessions/${rv.dataset.revoke}`);
      } catch (err) {
        return showFieldErrors(err);
      }
      sessions = sessions.filter((x) => x.id !== rv.dataset.revoke);
      renderSessions();
      toast('تم إنهاء الجلسة');
    }
  });
  $('#team').addEventListener('change', async (e) => {
    const s = e.target.closest('[data-role]');
    if (!s) return;
    e.stopPropagation();
    const m = team.find((x) => x.id === Number(s.dataset.role));
    try {
      Object.assign(m, (await api.patch(`team/${m.id}`, { role: s.value })).data);
    } catch (err) {
      showFieldErrors(err);
      s.value = m.role;
      return;
    }
    toast(`أصبحت صلاحية ${m.name}: ${m.role_label}`);
  });

  $('#inviteForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  $('#inviteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const name = $('#iName').value.trim();
    const email = $('#iEmail').value.trim();
    if (!name || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) return toast('أدخل اسماً وبريداً صحيحاً', 'error');
    let member;
    try {
      member = (await api.post('team', { name, email, role: $('#iRole').value })).data;
    } catch (err) {
      return showFieldErrors(err, { name: '#iName', email: '#iEmail', role: '#iRole' });
    }
    team.push(member);
    renderTeam();
    closeModal('inviteModal');
    e.target.reset();
    toast(`تم إرسال الدعوة إلى ${member.email}`);
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

  loadTeam().catch(() => {});
  loadSessions().catch(() => {});
});
