document.addEventListener('app:ready', () => {
  const { $, $$, esc, icon, toast, confirmDialog } = App;
  const threads = DB.threads;
  const quick = ['شكراً لتواصلك، سأرد بالتفصيل خلال اليوم.', 'يمكنك حجز مكالمة من صفحة الخدمات.', 'تم حل المشكلة، جرّب الآن من فضلك.'];
  let filter = 'all';
  let activeId = null;

  const now = () => new Date().toTimeString().slice(0, 5);
  const last = (t) => t.messages[t.messages.length - 1];

  function renderList() {
    App.setNavCount('messages', threads.filter((t) => t.unread).length);
    const q = $('#tq').value.trim().toLowerCase();
    const list = threads.filter((t) => (filter === 'all' || t.unread) && (!q || `${t.name} ${t.role} ${t.messages.map((m) => m.text).join(' ')}`.toLowerCase().includes(q)));
    $('#threads').innerHTML =
      list
        .map(
          (t) => `
        <div class="thread ${t.unread ? 'unread' : ''} ${t.id === activeId ? 'on' : ''}" data-thread="${t.id}" role="button" tabindex="0">
          <span class="avatar" style="background:${t.color}">${esc(t.initial)}</span>
          <div class="grow">
            <div class="row1"><b>${esc(t.name)}</b><time>${esc(t.time)}</time></div>
            <small class="muted" style="font-size:12px">${esc(t.role)}</small>
            <p>${last(t)?.me ? 'أنت: ' : ''}${esc(last(t)?.text || '')}</p>
          </div>
        </div>`,
        )
        .join('') || '<div class="empty"><b>لا توجد محادثات</b>جرّب بحثاً آخر.</div>';
  }

  function renderChat() {
    const t = threads.find((x) => x.id === activeId);
    $('#chat').classList.toggle('has-active', !!t);
    if (!t) {
      $('#chatMain').innerHTML = `<div class="empty" style="margin:auto"><div class="e-ico">${icon('chat')}</div><b>اختر محادثة</b>اختر محادثة من القائمة لعرضها.</div>`;
      return;
    }
    $('#chatMain').innerHTML = `
      <div class="chat-head">
        <button class="btn-icon chat-back" id="back" aria-label="رجوع">${icon('chevron-right')}</button>
        <div class="person" style="flex:1"><span class="avatar" style="background:${t.color}">${esc(t.initial)}</span><div><b>${esc(t.name)}</b><small>${esc(t.role)}</small></div></div>
        <div class="dropdown">
          <button class="btn-icon" data-dropdown aria-label="خيارات">${icon('more')}</button>
          <div class="menu">
            <button id="markUnread">${icon('mail', 'sm')}تعليم كغير مقروءة</button>
            <a href="${App.url('students', { q: t.name })}">${icon('user', 'sm')}عرض الملف</a>
            <button id="delThread" class="danger">${icon('trash', 'sm')}حذف المحادثة</button>
          </div>
        </div>
      </div>
      <div class="chat-body" id="chatBody">
        <span class="chat-day">${esc(t.time.includes(':') ? 'اليوم' : t.time)}</span>
        ${t.messages.map((m) => `<div class="bubble ${m.me ? 'me' : ''}">${esc(m.text)}<time>${esc(m.time)}</time></div>`).join('')}
      </div>
      <div class="chat-quick">${quick.map((q, i) => `<button type="button" data-quick="${i}">${esc(q)}</button>`).join('')}</div>
      <form class="chat-input" id="sendForm">
        <button type="button" class="btn-icon" aria-label="إرفاق ملف" id="attach">${icon('paperclip')}</button>
        <textarea class="textarea" id="msg" placeholder="اكتب رداً… (Enter للإرسال، Shift+Enter لسطر جديد)" aria-label="الرسالة"></textarea>
        <button class="btn btn-primary" type="submit" aria-label="إرسال">${icon('send', 'sm')}<span class="hide-xs">إرسال</span></button>
      </form>`;
    const body = $('#chatBody');
    body.scrollTop = body.scrollHeight;

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
    $('#sendForm').addEventListener('submit', (e) => {
      e.preventDefault();
      const text = msg.value.trim();
      if (!text) return;
      t.messages.push({ me: true, text, time: now() });
      t.time = now();
      threads.splice(threads.indexOf(t), 1);
      threads.unshift(t);
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
    $('#attach').addEventListener('click', () => toast('رفع المرفقات يتطلب ربط الخادم', 'info'));
    $('#back').addEventListener('click', () => {
      activeId = null;
      renderList();
      renderChat();
    });
    $('#markUnread').addEventListener('click', () => {
      t.unread = true;
      activeId = null;
      renderList();
      renderChat();
    });
    $('#delThread').addEventListener('click', async () => {
      if (await confirmDialog({ title: `حذف محادثة ${t.name}؟`, text: 'لا يمكن التراجع عن هذا الإجراء.', ok: 'حذف' })) {
        threads.splice(threads.indexOf(t), 1);
        activeId = null;
        renderList();
        renderChat();
        toast('تم حذف المحادثة');
      }
    });
  }

  function open(id) {
    activeId = id;
    const t = threads.find((x) => x.id === id);
    t.unread = false;
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
  // desktop: open the first conversation; phone: start from the list
  if (matchMedia('(min-width: 761px)').matches && threads.length) open(threads[0].id);
  else renderChat();
});
