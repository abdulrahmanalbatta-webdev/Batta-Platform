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

  /* ---------- the member's email switches for bell alerts ---------- */
  const ALERTS = {
    orders: ['طلب شراء مكتمل', 'بريد عند كل عملية شراء مكتملة.'],
    leads: ['طلب مشروع جديد', 'بريد عند وصول طلب من صفحة الخدمات.'],
    reviews: ['تقييم بانتظار المراجعة', 'بريد عند وصول تقييم جديد.'],
    messages: ['رسائل الطلاب والعملاء', 'بريد عند وصول رسالة جديدة.'],
    students: ['طالب جديد سجّل في الموقع', 'بريد فيه اسمه ورقم واتساب للتواصل معه وترتيب الدفع.'],
  };
  const prefs = App.user?.email_preferences || {};
  $('#notifPrefs').innerHTML = Object.entries(prefs)
    .filter(([key]) => ALERTS[key])
    .map(([key, on]) => `<div class="setting-row"><div><b>${ALERTS[key][0]}</b><p>${ALERTS[key][1]}</p></div><label class="switch"><input type="checkbox" data-pref="${key}" ${on ? 'checked' : ''} aria-label="${ALERTS[key][0]}"><span class="track"></span></label></div>`)
    .join('');
  $('#notifPrefs').addEventListener('change', async (e) => {
    const input = e.target.closest('[data-pref]');
    if (!input) return;
    try {
      await api.put('notification-preferences', { [input.dataset.pref]: input.checked });
    } catch {
      input.checked = !input.checked;
      return;
    }
    toast(input.checked ? 'سيصلك بريد بهذا التنبيه' : 'لن يصلك بريد بهذا التنبيه، وسيبقى في الجرس');
  });

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

  /* ---------- platform settings (GET/PUT settings): every [data-setting] field, secrets in [data-secret] ---------- */
  const form = $('#settingsForm');
  const save = $('#saveAll');
  const canEdit = App.can('manage_settings');
  const changed = new Set(); // keys edited since the last save

  const readField = (el) => {
    if (el.type === 'checkbox') return el.checked;
    if (el.type === 'number' || ['session_lifetime', 'mail_port'].includes(el.dataset.setting)) return el.value === '' ? null : Number(el.value);
    return el.value.trim() === '' ? null : el.value.trim();
  };
  function fill(res) {
    const data = res.data;
    // where payments and mail go is the owner's (meta.owner_only)
    const ownerOnly = new Set(res.meta.owner_only);
    const editable = (key) => canEdit && (!ownerOnly.has(key) || App.can('manage_platform_data'));
    $$('[data-setting]', form).forEach((el) => {
      const v = data[el.dataset.setting];
      if (el.type === 'checkbox') el.checked = !!v;
      else el.value = v ?? '';
      el.disabled = !editable(el.dataset.setting);
    });
    $$('[data-secret]', form).forEach((el) => {
      const secret = data[el.dataset.secret];
      el.value = '';
      el.placeholder = secret.set ? `محفوظ${secret.hint ? ` ${secret.hint}` : ''} — اتركه فارغاً للإبقاء عليه` : 'غير محفوظ';
      el.disabled = !editable(el.dataset.secret);
    });
    changed.clear();
    save?.classList.remove('pulse');
  }
  async function loadSettings() {
    try {
      fill(await api.get('settings'));
    } catch {
      // the toast already explains it
    }
  }

  // team roles and email switches apply immediately; only [data-setting]/[data-secret] fields wait for "حفظ"
  const track = (e) => {
    const el = e.target.closest('[data-setting], [data-secret]');
    if (!el) return;
    changed.add(el.dataset.setting || el.dataset.secret);
    el.classList.remove('invalid');
    save?.classList.add('pulse');
    if (e.type === 'change' && el.id === 'maintenance') toast(el.checked ? 'سيُفعّل وضع الصيانة بعد الحفظ' : 'سيُلغى وضع الصيانة بعد الحفظ', 'info');
  };
  form.addEventListener('input', track);
  form.addEventListener('change', track);
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!changed.size) return toast('لا توجد تغييرات للحفظ', 'info');
    const payload = {};
    changed.forEach((key) => {
      const el = $(`[data-setting="${key}"], [data-secret="${key}"]`, form);
      payload[key] = el.dataset.secret ? el.value.trim() : readField(el);
    });
    save.disabled = true;
    try {
      fill(await api.put('settings', payload));
    } catch (err) {
      if (err.status === 422) {
        const fields = Object.fromEntries(Object.keys(err.errors).map((k) => [k, `[data-setting="${k}"], [data-secret="${k}"]`]));
        // show the tab of the first wrong field
        const first = $(fields[Object.keys(err.errors)[0]] || '', form);
        const panel = first?.closest('[data-panel]')?.dataset.panel;
        if (panel) $(`[data-tab="${panel}"]`).click();
        showFieldErrors(err, fields);
      }
      return;
    } finally {
      save.disabled = false;
    }
    toast('تم حفظ الإعدادات');
  });
  window.addEventListener('beforeunload', (e) => {
    if (changed.size) e.preventDefault();
  });

  $('#testEmail')?.addEventListener('click', async (e) => {
    if (changed.size) return toast('احفظ التغييرات أولاً، ثم أرسل الرسالة التجريبية', 'info');
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      const res = await api.post('settings/test-email');
      toast(`أُرسلت رسالة تجريبية إلى ${res.sent_to}`);
    } catch (err) {
      showFieldErrors(err, { mail_host: '#mailHost' });
    } finally {
      btn.disabled = false;
    }
  });

  $('#testAnalytics')?.addEventListener('click', async (e) => {
    if (changed.size) return toast('احفظ التغييرات أولاً، ثم اختبر الاتصال', 'info');
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      toast((await api.post('settings/analytics-test')).message);
    } catch (err) {
      showFieldErrors(err, { ga_property_id: '#gaProperty' });
    } finally {
      btn.disabled = false;
    }
  });

  // open a tab from the URL hash (/dashboard/settings#security)
  const tab = location.hash.slice(1);
  if (tab) $(`[data-tab="${CSS.escape(tab)}"]`)?.click();
  $('[data-tabs="settings"]').addEventListener('tabchange', (e) => history.replaceState(null, '', `#${e.detail}`));

  document.addEventListener('click', async (e) => {
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

  /* ---------- the owner's data: exports (prepared in the background) and the wipe ---------- */
  const size = (bytes) => (bytes > 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);
  async function loadExports() {
    if (!App.can('manage_platform_data')) return;
    let res;
    try {
      res = await api.get('data-exports');
    } catch {
      return;
    }
    $('#exports').innerHTML = res.data
      .map(
        (x) => `<div class="list-item"><span class="grow"><b class="mono ltr" style="display:inline-block">${esc(x.name)}</b><small>${size(x.size)} · ${esc(App.ago(x.created_at))}</small></span>
          <a class="btn btn-sm btn-ghost" href="${esc(x.url)}">${icon('download', 'sm')}تنزيل</a>
          <button type="button" class="btn-icon danger" data-del-export="${esc(x.name)}" aria-label="حذف">${icon('trash', 'sm')}</button></div>`,
      )
      .join('');
  }
  $('#exportData').addEventListener('click', async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      toast((await api.post('data-exports')).message);
    } catch {
      // the toast already explains it
    } finally {
      btn.disabled = false;
    }
  });
  $('#exports').addEventListener('click', async (e) => {
    const del = e.target.closest('[data-del-export]');
    if (!del) return;
    try {
      await api.delete(`data-exports/${encodeURIComponent(del.dataset.delExport)}`);
    } catch {
      return;
    }
    loadExports();
    toast('تم حذف النسخة');
  });
  const WIPE_SENTENCE = 'احذف كل البيانات';
  $('#wipeForm').addEventListener('input', () => {
    $('#wipeSubmit').disabled = !$('#wipePassword').value || $('#wipeConfirm').value.trim() !== WIPE_SENTENCE;
  });
  $('#wipeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    $('#wipeSubmit').disabled = true;
    try {
      const res = await api.post('data-wipe', { password: $('#wipePassword').value, confirmation: $('#wipeConfirm').value.trim() });
      closeModal('wipeModal');
      e.target.reset();
      toast(res.message);
    } catch (err) {
      showFieldErrors(err, { password: '#wipePassword', confirmation: '#wipeConfirm' });
    } finally {
      $('#wipeSubmit').disabled = !$('#wipePassword').value || $('#wipeConfirm').value.trim() !== WIPE_SENTENCE;
    }
  });

  /* ---------- activity log, 20 entries at a time ---------- */
  let nextCursor = null;
  async function loadActivity() {
    let res;
    try {
      res = await api.get('activity', nextCursor ? { cursor: nextCursor } : undefined);
    } catch {
      return;
    }
    nextCursor = res.meta.next_cursor;
    $('#activityLog').insertAdjacentHTML(
      'beforeend',
      res.data.map((a) => `<li><span class="t-dot" style="background:${App.activityTone(a.action)}"></span><b>${esc(a.user)}</b><p>${esc(a.description)}</p><time title="${esc(new Date(a.at).toLocaleString('ar'))}">${esc(App.ago(a.at))}</time></li>`).join(''),
    );
    if (!$('#activityLog').children.length) $('#activityLog').innerHTML = '<li class="muted" style="list-style:none">لا يوجد نشاط بعد</li>';
    $('#moreActivity').hidden = !nextCursor;
  }
  $('#moreActivity').addEventListener('click', loadActivity);

  loadSettings();
  loadExports();
  loadActivity();
  loadTeam().catch(() => {});
  loadSessions().catch(() => {});
});
