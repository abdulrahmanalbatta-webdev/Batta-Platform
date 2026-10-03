document.addEventListener('app:ready', () => {
  const { $, esc, num, date, badge, icon, toast, confirmDialog } = App;
  const reviews = DB.reviews;
  const statuses = ['الكل', 'بانتظار المراجعة', 'منشور', 'مخفي'];
  let current = 'الكل';
  let replying = null;

  const stars = (n) => `<span class="stars" aria-label="${n} من 5">${[1, 2, 3, 4, 5].map((i) => `<span class="${i <= n ? '' : 'off'}">★</span>`).join('')}</span>`;

  function summary() {
    const pub = reviews.filter((r) => r.status !== 'مخفي');
    const avg = pub.reduce((s, r) => s + r.rating, 0) / (pub.length || 1);
    $('#summary').innerHTML = `
      <div style="display:flex;align-items:center;gap:16px;margin-bottom:18px">
        <b style="font-size:44px;line-height:1;color:var(--fg)">${avg.toFixed(1)}</b>
        <div>${stars(Math.round(avg))}<small class="muted" style="display:block">من ${num(pub.length)} تقييم ظاهر</small></div>
      </div>
      <div class="rating-bars">${[5, 4, 3, 2, 1]
        .map((n) => {
          const c = pub.filter((r) => r.rating === n).length;
          return `<div class="rb"><span>${n} ★</span><div class="progress ${n <= 2 ? 'red' : n === 3 ? 'amber' : ''}"><i style="width:${(c / (pub.length || 1)) * 100}%"></i></div><span class="num">${c}</span></div>`;
        })
        .join('')}</div>`;

    $('#byCourse').innerHTML = DB.courses
      .filter((c) => c.rating)
      .map((c) => `<div class="list-item"><span class="grow"><b>${esc(c.title)}</b><small>${num(c.students)} طالب</small></span><span class="nowrap" style="color:var(--fg);font-weight:800">${c.rating.toFixed(1)} <span class="stars">★</span></span></div>`)
      .join('');
  }

  function renderSeg() {
    $('#statusSeg').innerHTML = statuses
      .map((s) => `<button data-s="${s}" class="${current === s ? 'on' : ''}">${s === 'بانتظار المراجعة' ? 'بانتظار' : s}<span class="n">${s === 'الكل' ? reviews.length : reviews.filter((r) => r.status === s).length}</span></button>`)
      .join('');
  }

  function render() {
    const course = $('#courseFilter').value;
    const star = Number($('#starFilter').value);
    const list = reviews.filter((r) => (current === 'الكل' || r.status === current) && (!course || r.course === course) && (!star || r.rating === star));
    $('#reviewList').innerHTML =
      list
        .map(
          (r) => `
      <div class="review" style="${r.status === 'مخفي' ? 'opacity:.6' : ''}">
        <div class="review-top">
          <div class="person"><span class="avatar" style="background:${r.color}">${esc(r.initial)}</span><div><b>${esc(r.name)}</b><small>${esc(r.course)} · ${date(r.date)}</small></div></div>
          <div style="display:flex;align-items:center;gap:10px">${stars(r.rating)}${badge(r.status)}</div>
        </div>
        <p>${esc(r.text)}</p>
        ${r.reply ? `<div class="reply"><b>ردّك</b>${esc(r.reply)}</div>` : ''}
        ${
          replying === r.id
            ? `<form class="reply-form" data-reply-form="${r.id}" style="display:flex;flex-direction:column;gap:8px;margin-inline-start:48px">
                <textarea class="textarea" rows="3" placeholder="اكتب ردّاً لطيفاً…" aria-label="الرد">${esc(r.reply)}</textarea>
                <div style="display:flex;gap:8px;justify-content:flex-end"><button type="button" class="btn btn-sm btn-ghost" data-cancel>إلغاء</button><button class="btn btn-sm btn-primary">نشر الرد</button></div>
              </form>`
            : ''
        }
        <div style="display:flex;gap:8px;flex-wrap:wrap">
          ${r.status !== 'منشور' ? `<button class="btn btn-sm btn-soft" data-act="approve" data-id="${r.id}">${icon('check', 'sm')}${r.status === 'مخفي' ? 'إظهار' : 'اعتماد ونشر'}</button>` : ''}
          ${r.status !== 'مخفي' ? `<button class="btn btn-sm btn-ghost" data-act="hide" data-id="${r.id}">${icon('eye-off', 'sm')}إخفاء</button>` : ''}
          <button class="btn btn-sm btn-ghost" data-act="reply" data-id="${r.id}">${icon('reply', 'sm')}${r.reply ? 'تعديل الرد' : 'رد'}</button>
          <button class="btn btn-sm btn-danger-soft" data-act="delete" data-id="${r.id}" aria-label="حذف">${icon('trash', 'sm')}</button>
        </div>
      </div>`,
        )
        .join('') || `<div class="empty"><div class="e-ico">${icon('star')}</div><b>لا توجد تقييمات</b>لا يوجد ما يطابق الفلاتر الحالية.</div>`;
    $('#reviewList [data-reply-form] textarea')?.focus();
  }

  const refresh = () => {
    App.setNavCount('reviews', reviews.filter((r) => r.status === 'بانتظار المراجعة').length);
    renderSeg();
    render();
    summary();
  };

  $('#reviewList').addEventListener('click', async (e) => {
    if (e.target.closest('[data-cancel]')) {
      replying = null;
      return render();
    }
    const b = e.target.closest('[data-act]');
    if (!b) return;
    const r = reviews.find((x) => x.id === Number(b.dataset.id));
    const act = b.dataset.act;
    if (act === 'approve') {
      r.status = 'منشور';
      toast('تم نشر التقييم في الموقع');
    } else if (act === 'hide') {
      r.status = 'مخفي';
      toast('تم إخفاء التقييم');
    } else if (act === 'reply') {
      replying = r.id;
      return render();
    } else if (act === 'delete') {
      if (!(await confirmDialog({ title: 'حذف التقييم؟', text: `تقييم ${r.name} سيُحذف نهائياً.`, ok: 'حذف' }))) return;
      reviews.splice(reviews.indexOf(r), 1);
      toast('تم حذف التقييم');
    }
    refresh();
  });
  $('#reviewList').addEventListener('submit', (e) => {
    const f = e.target.closest('[data-reply-form]');
    if (!f) return;
    e.preventDefault();
    const text = f.querySelector('textarea').value.trim();
    if (!text) return toast('اكتب نص الرد', 'error');
    reviews.find((x) => x.id === Number(f.dataset.replyForm)).reply = text;
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
  [...new Set(reviews.map((r) => r.course))].forEach((c) => $('#courseFilter').insertAdjacentHTML('beforeend', `<option>${esc(c)}</option>`));
  $('#courseFilter').addEventListener('change', render);
  $('#starFilter').addEventListener('change', render);

  refresh();
});
