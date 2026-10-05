document.addEventListener('app:ready', async () => {
  const { $, esc, date, badge, icon, toast, confirmDialog, api, showFieldErrors } = App;
  const canModerate = App.can('moderate_reviews');
  const COLORS = ['#0066ff', '#7c3aed', '#334155', '#0e9f6e', '#c27803', '#0891b2'];
  const statuses = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'بانتظار' },
    { key: 'published', label: 'منشور' },
    { key: 'hidden', label: 'مخفي' },
  ];
  let comments;
  try {
    comments = (await api.get('comments')).data;
  } catch {
    return;
  }
  // a link from a new-comment alert opens the pending ones
  let current = new URLSearchParams(location.search).get('status') || 'all';
  let replying = null;

  const color = (c) => COLORS[c.student_id % COLORS.length];
  const replace = (comment) => comments.splice(comments.findIndex((c) => c.id === comment.id), 1, comment);
  const articleLink = (c) => (App.siteUrl ? `<a href="${esc(`${App.siteUrl}/articles/${c.article_slug}`)}" target="_blank" rel="noopener">${esc(c.article)}</a>` : esc(c.article));

  function renderSeg() {
    $('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s.key}" class="${current === s.key ? 'on' : ''}">${s.label}<span class="n">${s.key === 'all' ? comments.length : comments.filter((c) => c.status === s.key).length}</span></button>`)
      .join('');
  }

  function actions(c) {
    if (!canModerate) return '';
    return `
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${c.status !== 'published' ? `<button class="btn btn-sm btn-soft" data-act="publish" data-id="${c.id}">${icon('check', 'sm')}${c.status === 'hidden' ? 'إظهار' : 'اعتماد ونشر'}</button>` : ''}
          ${c.status !== 'hidden' ? `<button class="btn btn-sm btn-ghost" data-act="hide" data-id="${c.id}">${icon('eye-off', 'sm')}${c.status === 'pending' ? 'رفض' : 'إخفاء'}</button>` : ''}
          <button class="btn btn-sm btn-ghost" data-act="reply" data-id="${c.id}">${icon('reply', 'sm')}${c.reply ? 'تعديل الرد' : 'رد'}</button>
          <button class="btn btn-sm btn-danger-soft" data-act="delete" data-id="${c.id}" aria-label="حذف">${icon('trash', 'sm')}</button>
        </div>`;
  }

  function render() {
    const article = Number($('#articleFilter').value);
    const list = comments.filter((c) => (current === 'all' || c.status === current) && (!article || c.article_id === article));
    $('#commentList').innerHTML =
      list
        .map(
          (c) => `
      <div class="review" style="${c.status === 'hidden' ? 'opacity:.6' : ''}">
        <div class="review-top">
          <div class="person"><span class="avatar" style="background:${color(c)}">${esc(c.initial)}</span><div><b>${esc(c.name)}</b><small>${articleLink(c)} · ${date(c.date)}</small></div></div>
          ${badge(c.status_label)}
        </div>
        <p style="white-space:pre-line">${esc(c.body)}</p>
        ${c.reply && replying !== c.id ? `<div class="reply"><b>${c.replied_by ? `ردّ ${esc(c.replied_by)}` : 'الرد'}</b>${esc(c.reply)}</div>` : ''}
        ${
          replying === c.id
            ? `<form class="reply-form" data-reply-form="${c.id}" style="display:flex;flex-direction:column;gap:8px;margin-inline-start:48px">
                <textarea class="textarea" rows="3" maxlength="2000" placeholder="اكتب ردّاً…" aria-label="الرد">${esc(c.reply || '')}</textarea>
                <div style="display:flex;gap:8px;justify-content:flex-end">
                  ${c.reply ? '<button type="button" class="btn btn-sm btn-danger-soft" data-remove-reply style="margin-inline-end:auto">حذف الرد</button>' : ''}
                  <button type="button" class="btn btn-sm btn-ghost" data-cancel>إلغاء</button><button class="btn btn-sm btn-primary">نشر الرد</button>
                </div>
              </form>`
            : ''
        }
        ${actions(c)}
      </div>`,
        )
        .join('') || `<div class="empty"><div class="e-ico">${icon('chat')}</div><b>لا توجد تعليقات</b>${comments.length ? 'لا يوجد ما يطابق الفلاتر الحالية.' : 'تظهر هنا تعليقات الطلاب على المقالات عند وصولها.'}</div>`;
    $('#commentList [data-reply-form] textarea')?.focus();
  }

  const refresh = () => {
    App.setNavCount('comments', comments.filter((c) => c.status === 'pending').length);
    renderSeg();
    render();
  };

  async function setStatus(c, status, message) {
    try {
      replace((await api.put(`comments/${c.id}/status`, { status })).data);
    } catch {
      return;
    }
    toast(message);
    refresh();
  }

  $('#commentList').addEventListener('click', async (e) => {
    if (e.target.closest('[data-cancel]')) {
      replying = null;
      return render();
    }
    const remove = e.target.closest('[data-remove-reply]');
    if (remove) {
      const id = Number(remove.closest('[data-reply-form]').dataset.replyForm);
      try {
        replace((await api.delete(`comments/${id}/reply`)).data);
      } catch {
        return;
      }
      replying = null;
      render();
      return toast('تم حذف الرد');
    }
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const c = comments.find((x) => x.id === Number(b.dataset.id));
    const act = b.dataset.act;
    if (act === 'publish') return setStatus(c, 'published', 'تم نشر التعليق تحت المقال');
    if (act === 'hide') return setStatus(c, 'hidden', 'تم إخفاء التعليق');
    if (act === 'reply') {
      replying = c.id;
      return render();
    }
    if (act === 'delete') {
      if (!(await confirmDialog({ title: 'حذف التعليق؟', text: `تعليق ${c.name} سيُحذف نهائياً.`, ok: 'حذف' }))) return;
      try {
        await api.delete(`comments/${c.id}`);
      } catch {
        return;
      }
      comments.splice(comments.indexOf(c), 1);
      toast('تم حذف التعليق');
      refresh();
    }
  });
  $('#commentList').addEventListener('submit', async (e) => {
    const f = e.target.closest('[data-reply-form]');
    if (!f) return;
    e.preventDefault();
    const text = f.querySelector('textarea').value.trim();
    if (!text) return toast('اكتب نص الرد', 'error');
    try {
      replace((await api.put(`comments/${f.dataset.replyForm}/reply`, { reply: text })).data);
    } catch (err) {
      showFieldErrors(err);
      return;
    }
    replying = null;
    render();
    toast('تم نشر ردك');
  });

  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    current = b.dataset.s;
    renderSeg();
    render();
  });
  const articles = new Map(comments.map((c) => [c.article_id, c.article]));
  articles.forEach((title, id) => $('#articleFilter').insertAdjacentHTML('beforeend', `<option value="${id}">${esc(title)}</option>`));
  $('#articleFilter').addEventListener('change', render);

  refresh();
});
