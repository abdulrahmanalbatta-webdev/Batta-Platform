document.addEventListener('app:ready', async () => {
  const { $, $$, esc, icon, toast, confirmDialog, api, showFieldErrors } = App;
  const canAnswer = App.can('answer_messages');
  const COLORS = ['#0066ff', '#0e9f6e', '#7c3aed', '#334155', '#c27803', '#0891b2'];
  const quick = ['شكراً لتواصلك، سأرد بالتفصيل خلال اليوم.', 'يمكنك حجز مكالمة من صفحة الخدمات.', 'تم حل المشكلة، جرّب الآن من فضلك.'];
  let threads;
  try {
    threads = (await api.get('conversations')).data;
  } catch {
    return;
  }
  let filter = 'all';
  let active = null; // the open conversation with its messages
  let file = null; // the attachment waiting to be sent

  const color = (t) => COLORS[t.id % COLORS.length];
  const time = (iso) => new Date(iso).toLocaleTimeString('en-GB', { hour: '2-digit', minute: '2-digit' });
  const dayKey = (iso) => new Date(iso).toDateString();
  function day(iso) {
    const d = new Date(iso);
    const today = new Date();
    const yesterday = new Date(today.getFullYear(), today.getMonth(), today.getDate() - 1);
    if (d.toDateString() === today.toDateString()) return 'اليوم';
    if (d.toDateString() === yesterday.toDateString()) return 'أمس';
    return App.date(`${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`);
  }
  // the list shows the time for today's conversations and the day for older ones
  const when = (iso) => (iso ? (day(iso) === 'اليوم' ? time(iso) : day(iso)) : '');
  const preview = (t) => {
    const m = t.last_message;
    if (!m) return 'لا توجد رسائل بعد';
    return `${m.from_contact ? '' : 'أنت: '}${m.body || `📎 ${m.attachment_name}`}`;
  };
  const replace = (t) => {
    const i = threads.findIndex((x) => x.id === t.id);
    if (i >= 0) threads.splice(i, 1, t);
  };
  const profileUrl = (t) => (t.student_id ? App.url('students', { q: t.name }) : t.lead_id ? App.url('leads') : null);

  function renderList() {
    App.setNavCount('messages', threads.filter((t) => t.unread).length);
    const q = $('#tq').value.trim().toLowerCase();
    const list = threads.filter((t) => (filter === 'all' || t.unread) && (!q || `${t.name} ${t.email} ${t.label} ${t.last_message?.body || ''}`.toLowerCase().includes(q)));
    $('#threads').innerHTML =
      list
        .map(
          (t) => `
        <div class="thread ${t.unread ? 'unread' : ''} ${t.id === active?.id ? 'on' : ''}" data-thread="${t.id}" role="button" tabindex="0">
          <span class="avatar" style="background:${color(t)}">${esc(t.initial)}</span>
          <div class="grow">
            <div class="row1"><b>${esc(t.name)}</b><time>${esc(when(t.last_message_at))}</time></div>
            <small class="muted" style="font-size:12px">${esc(t.label)}</small>
            <p>${esc(preview(t))}</p>
          </div>
        </div>`,
        )
        .join('') || '<div class="empty"><b>لا توجد محادثات</b>جرّب بحثاً آخر.</div>';
  }

  function bubbles(messages) {
    let lastDay = null;
    return messages
      .map((m) => {
        const sep = dayKey(m.at) !== lastDay ? `<span class="chat-day">${esc(day(m.at))}</span>` : '';
        lastDay = dayKey(m.at);
        const attachment = m.attachment ? `<a href="${esc(m.attachment.url)}" style="display:flex;align-items:center;gap:6px;font-weight:700;color:inherit;text-decoration:underline">${icon('paperclip', 'sm')}${esc(m.attachment.name)}</a>` : '';
        const by = !m.from_contact && m.sender ? ` · ${esc(m.sender)}` : '';
        return `${sep}<div class="bubble ${m.from_contact ? '' : 'me'}">${m.body ? esc(m.body) : ''}${attachment}<time>${esc(time(m.at))}${by}</time></div>`;
      })
      .join('');
  }

  function renderFile() {
    const chip = $('#fileChip');
    if (!chip) return;
    chip.hidden = !file;
    chip.innerHTML = file ? `${icon('paperclip', 'sm')}<span class="grow" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${esc(file.name)}</span><button type="button" class="btn-icon" id="dropFile" aria-label="إزالة المرفق" style="width:26px;height:26px">${icon('close', 'sm')}</button>` : '';
    $('#dropFile')?.addEventListener('click', () => {
      file = null;
      renderFile();
    });
  }

  function renderChat() {
    const t = active;
    $('#chat').classList.toggle('has-active', !!t);
    if (!t) {
      $('#chatMain').innerHTML = `<div class="empty" style="margin:auto"><div class="e-ico">${icon('chat')}</div><b>اختر محادثة</b>اختر محادثة من القائمة لعرضها.</div>`;
      return;
    }
    const profile = profileUrl(t);
    const menu = [
      canAnswer ? `<button id="markUnread">${icon('mail', 'sm')}تعليم كغير مقروءة</button>` : '',
      profile ? `<a href="${profile}">${icon('user', 'sm')}${t.student_id ? 'عرض الملف' : 'عرض الطلب'}</a>` : '',
      `<a href="mailto:${esc(t.email)}">${icon('mail', 'sm')}${esc(t.email)}</a>`,
      canAnswer ? `<button id="delThread" class="danger">${icon('trash', 'sm')}حذف المحادثة</button>` : '',
    ].join('');
    $('#chatMain').innerHTML = `
      <div class="chat-head">
        <button class="btn-icon chat-back" id="back" aria-label="رجوع">${icon('chevron-right')}</button>
        <div class="person" style="flex:1"><span class="avatar" style="background:${color(t)}">${esc(t.initial)}</span><div><b>${esc(t.name)}</b><small>${esc(t.label)}</small></div></div>
        <div class="dropdown">
          <button class="btn-icon" data-dropdown aria-label="خيارات">${icon('more')}</button>
          <div class="menu">${menu}</div>
        </div>
      </div>
      <div class="chat-body" id="chatBody">
        ${bubbles(t.messages) || '<div class="empty" style="margin:auto"><b>لا توجد رسائل بعد</b>اكتب أول رسالة، وستصل إلى بريده.</div>'}
      </div>
      ${
        canAnswer
          ? `<div class="chat-quick">${quick.map((q, i) => `<button type="button" data-quick="${i}">${esc(q)}</button>`).join('')}</div>
      <div id="fileChip" class="badge" style="display:flex;align-items:center;gap:6px;margin:0 14px 6px;max-width:320px" hidden></div>
      <form class="chat-input" id="sendForm">
        <input type="file" id="fileInput" hidden accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.zip,.doc,.docx,.xls,.xlsx,.txt">
        <button type="button" class="btn-icon" aria-label="إرفاق ملف" id="attach">${icon('paperclip')}</button>
        <textarea class="textarea" id="msg" maxlength="5000" placeholder="اكتب رداً… (Enter للإرسال، Shift+Enter لسطر جديد)" aria-label="الرسالة"></textarea>
        <button class="btn btn-primary" type="submit" aria-label="إرسال">${icon('send', 'sm')}<span class="hide-xs">إرسال</span></button>
      </form>`
          : ''
      }`;
    const body = $('#chatBody');
    body.scrollTop = body.scrollHeight;

    $('#back').addEventListener('click', () => {
      active = null;
      renderList();
      renderChat();
    });
    $('#markUnread')?.addEventListener('click', async () => {
      try {
        replace((await api.delete(`conversations/${t.id}/read`)).data);
      } catch {
        return;
      }
      active = null;
      renderList();
      renderChat();
    });
    $('#delThread')?.addEventListener('click', async () => {
      if (!(await confirmDialog({ title: `حذف محادثة ${t.name}؟`, text: 'ستُحذف الرسائل والمرفقات، ولا يمكن التراجع عن هذا الإجراء.', ok: 'حذف' }))) return;
      try {
        await api.delete(`conversations/${t.id}`);
      } catch {
        return;
      }
      threads = threads.filter((x) => x.id !== t.id);
      active = null;
      renderList();
      renderChat();
      toast('تم حذف المحادثة');
    });
    if (!canAnswer) return;

    renderFile();
    const msg = $('#msg');
    msg.addEventListener('input', () => {
      msg.style.height = 'auto';
      msg.style.height = `${Math.min(140, msg.scrollHeight)}px`;
    });
    msg.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        $('#sendForm').requestSubmit();
      }
    });
    $('#sendForm').addEventListener('submit', async (e) => {
      e.preventDefault();
      const text = msg.value.trim();
      if (!text && !file) return;
      const form = new FormData();
      if (text) form.append('body', text);
      if (file) form.append('attachment', file);
      const btn = $('#sendForm [type=submit]');
      btn.disabled = true;
      let sent;
      try {
        sent = (await api.post(`conversations/${t.id}/messages`, form)).data;
      } catch (err) {
        showFieldErrors(err, { body: '#msg' });
        btn.disabled = false;
        return;
      }
      file = null;
      t.messages.push(sent);
      t.unread = false;
      t.last_message = { body: sent.body, from_contact: false, attachment_name: sent.attachment?.name || null };
      t.last_message_at = sent.at;
      threads = [t, ...threads.filter((x) => x.id !== t.id)];
      renderList();
      renderChat();
      $('#msg').focus();
    });
    $$('[data-quick]').forEach((b) =>
      b.addEventListener('click', () => {
        msg.value = quick[b.dataset.quick];
        msg.focus();
      }),
    );
    $('#attach').addEventListener('click', () => $('#fileInput').click());
    $('#fileInput').addEventListener('change', (e) => {
      const picked = e.target.files[0];
      if (!picked) return;
      if (picked.size > 10 * 1024 * 1024) return toast('حجم المرفق أكبر من 10 ميغابايت', 'error');
      file = picked;
      renderFile();
      msg.focus();
    });
  }

  async function open(id) {
    file = null;
    let t;
    try {
      t = (await api.get(`conversations/${id}`)).data;
    } catch {
      return;
    }
    if (t.unread && canAnswer) {
      // opening a conversation reads it; a read-only member leaves it for the team
      api.post(`conversations/${id}/read`).catch(() => {});
      t.unread = false;
    }
    active = t;
    replace({ ...t, messages: undefined });
    renderList();
    renderChat();
    if (matchMedia('(min-width: 761px)').matches) $('#msg')?.focus();
  }

  $('#threads').addEventListener('click', (e) => {
    const el = e.target.closest('[data-thread]');
    if (el) open(Number(el.dataset.thread));
  });
  $('#threads').addEventListener('keydown', (e) => {
    const el = e.target.closest('[data-thread]');
    if (el && e.key === 'Enter') open(Number(el.dataset.thread));
  });
  $('#tq').addEventListener('input', App.debounce(renderList, 120));
  $('#threadSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    filter = b.dataset.f;
    $$('#threadSeg button').forEach((x) => x.classList.toggle('on', x === b));
    renderList();
  });

  renderList();
  // ?c=ID opens that conversation (e.g. "مراسلة" on a project request); otherwise desktop opens the first, phone starts from the list
  const requested = Number(new URLSearchParams(location.search).get('c'));
  if (requested && threads.some((t) => t.id === requested)) open(requested);
  else if (matchMedia('(min-width: 761px)').matches && threads.length) open(threads[0].id);
  else renderChat();
});
