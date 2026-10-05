document.addEventListener('app:ready', async () => {
  const { $, esc, num, date, badge, icon, toast, confirmDialog, api, showFieldErrors } = App;
  const canModerate = App.can('moderate_reviews');
  const COLORS = ['#0066ff', '#7c3aed', '#334155', '#0e9f6e', '#c27803', '#0891b2'];
  const statuses = [
    { key: 'all', label: 'الكل' },
    { key: 'pending', label: 'بانتظار' },
    { key: 'published', label: 'منشور' },
    { key: 'hidden', label: 'مخفي' },
  ];
  let reviews;
  try {
    reviews = (await api.get('reviews')).data;
  } catch {
    return;
  }
  let current = 'all';
  let replying = null;

  const color = (r) => COLORS[r.student_id % COLORS.length];
  const replace = (review) => reviews.splice(reviews.findIndex((r) => r.id === review.id), 1, review);
  const stars = (n) => `<span class="stars" aria-label="${n} من 5">${[1, 2, 3, 4, 5].map((i) => `<span class="${i <= n ? '' : 'off'}">★</span>`).join('')}</span>`;

  // the site shows published reviews only, so the summary counts those
  function summary() {
    const pub = reviews.filter((r) => r.status === 'published');
    const avg = pub.reduce((s, r) => s + r.rating, 0) / (pub.length || 1);
    $('#summary').innerHTML = `
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
        <b style="font-size:44px;line-height:1;color:var(--fg)">${avg.toFixed(1)}</b>
        <div>${stars(Math.round(avg))}<small class="muted" style="display:block">من ${num(pub.length)} تقييم منشور</small></div>
      </div>
      <div class="rating-bars">${[5, 4, 3, 2, 1]
        .map((n) => {
          const c = pub.filter((r) => r.rating === n).length;
          return `<div class="rb"><span>${n} ★</span><div class="progress ${n <= 2 ? 'red' : n === 3 ? 'amber' : ''}"><i style="width:${(c / (pub.length || 1)) * 100}%"></i></div><span class="num">${c}</span></div>`;
        })
        .join('')}</div>`;

    const byCourse = new Map();
    pub.forEach((r) => {
      const c = byCourse.get(r.course_id) || { title: r.course, sum: 0, count: 0 };
      c.sum += r.rating;
      c.count += 1;
      byCourse.set(r.course_id, c);
    });
    $('#byCourse').innerHTML =
      [...byCourse.values()]
        .sort((a, b) => b.count - a.count)
        .map((c) => `<div class="list-item"><span class="grow"><b>${esc(c.title)}</b><small>${num(c.count)} تقييم</small></span><span class="nowrap" style="color:var(--fg);font-weight:800">${(c.sum / c.count).toFixed(1)} <span class="stars">★</span></span></div>`)
        .join('') || '<div class="list-item muted">لا توجد تقييمات منشورة بعد</div>';
  }

  function renderSeg() {
    $('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s.key}" class="${current === s.key ? 'on' : ''}">${s.label}<span class="n">${s.key === 'all' ? reviews.length : reviews.filter((r) => r.status === s.key).length}</span></button>`)
      .join('');
  }

  function actions(r) {
    if (!canModerate) return '';
    return `
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${r.status !== 'published' ? `<button class="btn btn-sm btn-soft" data-act="publish" data-id="${r.id}">${icon('check', 'sm')}${r.status === 'hidden' ? 'إظهار' : 'اعتماد ونشر'}</button>` : ''}
          ${r.status !== 'hidden' ? `<button class="btn btn-sm btn-ghost" data-act="hide" data-id="${r.id}">${icon('eye-off', 'sm')}${r.status === 'pending' ? 'رفض' : 'إخفاء'}</button>` : ''}
          <button class="btn btn-sm btn-ghost" data-act="reply" data-id="${r.id}">${icon('reply', 'sm')}${r.reply ? 'تعديل الرد' : 'رد'}</button>
          <button class="btn btn-sm btn-danger-soft" data-act="delete" data-id="${r.id}" aria-label="حذف">${icon('trash', 'sm')}</button>
        </div>`;
  }

  function render() {
    const course = Number($('#courseFilter').value);
    const star = Number($('#starFilter').value);
    const list = reviews.filter((r) => (current === 'all' || r.status === current) && (!course || r.course_id === course) && (!star || r.rating === star));
    $('#reviewList').innerHTML =
      list
        .map(
          (r) => `
      <div class="review" style="${r.status === 'hidden' ? 'opacity:.6' : ''}">
        <div class="review-top">
          <div class="person"><span class="avatar" style="background:${color(r)}">${esc(r.initial)}</span><div><b>${esc(r.name)}</b><small>${esc(r.course)} · ${date(r.date)}</small></div></div>
          <div style="display:flex;align-items:center;gap:10px">${stars(r.rating)}${badge(r.status_label)}</div>
        </div>
        <p>${esc(r.body)}</p>
        ${r.reply && replying !== r.id ? `<div class="reply"><b>${r.replied_by ? `ردّ ${esc(r.replied_by)}` : 'الرد'}</b>${esc(r.reply)}</div>` : ''}
        ${
          replying === r.id
            ? `<form class="reply-form" data-reply-form="${r.id}" style="display:flex;flex-direction:column;gap:8px;margin-inline-start:48px">
                <textarea class="textarea" rows="3" maxlength="2000" placeholder="اكتب ردّاً لطيفاً…" aria-label="الرد">${esc(r.reply || '')}</textarea>
                <div style="display:flex;gap:8px;justify-content:flex-end">
                  ${r.reply ? '<button type="button" class="btn btn-sm btn-danger-soft" data-remove-reply style="margin-inline-end:auto">حذف الرد</button>' : ''}
                  <button type="button" class="btn btn-sm btn-ghost" data-cancel>إلغاء</button><button class="btn btn-sm btn-primary">نشر الرد</button>
                </div>
              </form>`
            : ''
        }
        ${actions(r)}
      </div>`,
        )
        .join('') || `<div class="empty"><div class="e-ico">${icon('star')}</div><b>لا توجد تقييمات</b>لا يوجد ما يطابق الفلاتر الحالية.</div>`;
    $('#reviewList [data-reply-form] textarea')?.focus();
  }

  const refresh = () => {
    App.setNavCount('reviews', reviews.filter((r) => r.status === 'pending').length);
    renderSeg();
    render();
    summary();
  };

  async function setStatus(r, status, message) {
    try {
      replace((await api.put(`reviews/${r.id}/status`, { status })).data);
    } catch {
      return;
    }
    toast(message);
    refresh();
  }

  $('#reviewList').addEventListener('click', async (e) => {
    if (e.target.closest('[data-cancel]')) {
      replying = null;
      return render();
    }
    const remove = e.target.closest('[data-remove-reply]');
    if (remove) {
      const id = Number(remove.closest('[data-reply-form]').dataset.replyForm);
      try {
        replace((await api.delete(`reviews/${id}/reply`)).data);
      } catch {
        return;
      }
      replying = null;
      render();
      return toast('تم حذف الرد');
    }
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const r = reviews.find((x) => x.id === Number(b.dataset.id));
    const act = b.dataset.act;
    if (act === 'publish') return setStatus(r, 'published', 'تم نشر التقييم في الموقع');
    if (act === 'hide') return setStatus(r, 'hidden', 'تم إخفاء التقييم');
    if (act === 'reply') {
      replying = r.id;
      return render();
    }
    if (act === 'delete') {
      if (!(await confirmDialog({ title: 'حذف التقييم؟', text: `تقييم ${r.name} سيُحذف نهائياً.`, ok: 'حذف' }))) return;
      try {
        await api.delete(`reviews/${r.id}`);
      } catch {
        return;
      }
      reviews.splice(reviews.indexOf(r), 1);
      toast('تم حذف التقييم');
      refresh();
    }
  });
  $('#reviewList').addEventListener('submit', async (e) => {
    const f = e.target.closest('[data-reply-form]');
    if (!f) return;
    e.preventDefault();
    const text = f.querySelector('textarea').value.trim();
    if (!text) return toast('اكتب نص الرد', 'error');
    try {
      replace((await api.put(`reviews/${f.dataset.replyForm}/reply`, { reply: text })).data);
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
  const courses = new Map(reviews.map((r) => [r.course_id, r.course]));
  courses.forEach((title, id) => $('#courseFilter').insertAdjacentHTML('beforeend', `<option value="${id}">${esc(title)}</option>`));
  $('#courseFilter').addEventListener('change', render);
  $('#starFilter').addEventListener('change', render);

  refresh();
});
