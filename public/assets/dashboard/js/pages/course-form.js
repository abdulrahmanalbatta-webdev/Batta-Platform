document.addEventListener('app:ready', () => {
  const { $, $$, esc, icon, toast, confirmDialog } = App;

  /* ---------- tags inputs (outcomes + tags) ---------- */
  function tagsInput(root, initial = []) {
    const input = $('input', root);
    const values = [];
    const add = (v) => {
      v = v.trim();
      if (!v || values.includes(v)) return;
      values.push(v);
      const chip = document.createElement('span');
      chip.className = 'tag-chip';
      chip.innerHTML = `${esc(v)}<button type="button" aria-label="إزالة">${icon('close', 'sm')}</button>`;
      chip.querySelector('button').onclick = () => {
        values.splice(values.indexOf(v), 1);
        chip.remove();
      };
      root.insertBefore(chip, input);
    };
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        add(input.value);
        input.value = '';
      } else if (e.key === 'Backspace' && !input.value && values.length) {
        values.pop();
        root.querySelectorAll('.tag-chip').forEach((c, i, all) => i === all.length - 1 && c.remove());
      }
    });
    root.addEventListener('click', () => input.focus());
    initial.forEach(add);
    return values;
  }

  /* ---------- curriculum builder ---------- */
  let modules = [
    { title: 'البداية والتجهيز', lessons: [{ title: 'مقدمة الدورة', dur: '05:20' }, { title: 'تجهيز بيئة العمل', dur: '12:40' }] },
    { title: 'المشروع الأول', lessons: [{ title: 'هيكلة المشروع', dur: '18:05' }] },
  ];

  function renderModules() {
    const host = $('#modules');
    host.innerHTML = modules.length
      ? modules
          .map(
            (m, mi) => `
        <div class="module" data-m="${mi}">
          <div class="module-head">
            <span class="badge ink">${mi + 1}</span>
            <input class="input" data-field="mtitle" value="${esc(m.title)}" placeholder="عنوان الوحدة" aria-label="عنوان الوحدة">
            <button type="button" class="btn-icon" data-move="up" title="لأعلى" ${mi === 0 ? 'disabled' : ''}>${icon('arrow-up', 'sm')}</button>
            <button type="button" class="btn-icon" data-move="down" title="لأسفل" ${mi === modules.length - 1 ? 'disabled' : ''}>${icon('arrow-down', 'sm')}</button>
            <button type="button" class="btn-icon danger" data-del-module title="حذف الوحدة">${icon('trash', 'sm')}</button>
          </div>
          ${m.lessons
            .map(
              (l, li) => `
            <div class="lesson" data-l="${li}">
              <span class="muted">${icon('play', 'sm')}</span>
              <input class="input" data-field="ltitle" value="${esc(l.title)}" placeholder="عنوان الدرس" aria-label="عنوان الدرس">
              <input class="input dur ltr" data-field="ldur" value="${esc(l.dur)}" placeholder="00:00" aria-label="المدة" style="text-align:center">
              <button type="button" class="btn-icon danger" data-del-lesson title="حذف الدرس">${icon('close', 'sm')}</button>
            </div>`,
            )
            .join('')}
          <div class="module-add"><button type="button" class="btn btn-ghost btn-sm" data-add-lesson>${icon('plus', 'sm')}إضافة درس</button></div>
        </div>`,
          )
          .join('')
      : `<div class="empty"><div class="e-ico">${icon('layers', 'lg')}</div><b>لا توجد وحدات بعد</b>ابدأ بإضافة أول وحدة للمنهج</div>`;
    const lessons = modules.reduce((a, m) => a + m.lessons.length, 0);
    $('#curriculumSummary').textContent = `${modules.length} وحدات · ${lessons} دروس`;
  }

  $('#addModule').addEventListener('click', () => {
    modules.push({ title: '', lessons: [{ title: '', dur: '' }] });
    renderModules();
    $$('#modules [data-field="mtitle"]').at(-1).focus();
  });
  $('#modules').addEventListener('input', (e) => {
    const mEl = e.target.closest('[data-m]');
    if (!mEl) return;
    const m = modules[mEl.dataset.m];
    const f = e.target.dataset.field;
    if (f === 'mtitle') m.title = e.target.value;
    const lEl = e.target.closest('[data-l]');
    if (lEl) {
      const l = m.lessons[lEl.dataset.l];
      if (f === 'ltitle') l.title = e.target.value;
      if (f === 'ldur') l.dur = e.target.value;
    }
  });
  $('#modules').addEventListener('click', async (e) => {
    const mEl = e.target.closest('[data-m]');
    if (!mEl) return;
    const mi = Number(mEl.dataset.m);
    if (e.target.closest('[data-add-lesson]')) {
      modules[mi].lessons.push({ title: '', dur: '' });
      renderModules();
      $$(`[data-m="${mi}"] [data-field="ltitle"]`).at(-1).focus();
    } else if (e.target.closest('[data-del-lesson]')) {
      modules[mi].lessons.splice(Number(e.target.closest('[data-l]').dataset.l), 1);
      renderModules();
    } else if (e.target.closest('[data-del-module]')) {
      if (await confirmDialog({ title: 'حذف الوحدة؟', text: 'سيتم حذف الوحدة وكل دروسها.', ok: 'حذف' })) {
        modules.splice(mi, 1);
        renderModules();
      }
    } else if (e.target.closest('[data-move]')) {
      const to = e.target.closest('[data-move]').dataset.move === 'up' ? mi - 1 : mi + 1;
      [modules[mi], modules[to]] = [modules[to], modules[mi]];
      renderModules();
    }
  });

  /* ---------- cover image preview (drag & drop or pick) ---------- */
  const cover = $('#cover');
  const showCover = (file) => {
    if (!file || !file.type.startsWith('image/')) return toast('اختر ملف صورة', 'error');
    const url = URL.createObjectURL(file);
    cover.classList.add('has-image');
    cover.querySelectorAll('img').forEach((i) => i.remove());
    cover.insertAdjacentHTML('afterbegin', `<img src="${url}" alt="معاينة الغلاف">`);
  };
  $('input', cover).addEventListener('change', (e) => showCover(e.target.files[0]));
  ['dragover', 'dragenter'].forEach((ev) => cover.addEventListener(ev, (e) => { e.preventDefault(); cover.classList.add('over'); }));
  ['dragleave', 'drop'].forEach((ev) => cover.addEventListener(ev, () => cover.classList.remove('over')));
  cover.addEventListener('drop', (e) => { e.preventDefault(); showCover(e.dataTransfer.files[0]); });

  /* ---------- slug + counters ---------- */
  let slugTouched = false;
  $('#slug').addEventListener('input', () => (slugTouched = true));
  $('#title').addEventListener('input', (e) => {
    $('#title').classList.remove('invalid');
    $('#titleError').hidden = true;
    if (!slugTouched) {
      // keep Latin letters/numbers from the title, e.g. "Next.js من الصفر" → "nextjs"
      $('#slug').value = e.target.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
    }
  });
  $('#short').addEventListener('input', (e) => ($('#shortCount').textContent = e.target.value.length));

  /* ---------- edit mode: ?id=C-101 ---------- */
  const id = document.body.dataset.id; // set by the edit route: /dashboard/courses/{id}/edit
  const existing = DB.courses.find((c) => c.id === id);
  let outcomes;
  let tags;
  if (existing) {
    document.title = `تعديل: ${existing.title} | لوحة التحكم`;
    $('#pageTitle').textContent = 'تعديل الدورة';
    $('#crumb').textContent = existing.title;
    $('#title').value = existing.title;
    $('#slug').value = existing.id.toLowerCase();
    slugTouched = true;
    $('#short').value = 'دورة عملية تنتهي بمشروع حقيقي منشور.';
    $('#shortCount').textContent = $('#short').value.length;
    $('#price').value = existing.price;
    $('#level').value = existing.level;
    $('#status').value = existing.status;
    outcomes = tagsInput($('#outcomes'), ['بناء مشروع كامل', 'نشر المشروع على الإنترنت']);
    tags = tagsInput($('#tags'), [existing.level, 'مشروع عملي']);
  } else {
    outcomes = tagsInput($('#outcomes'));
    tags = tagsInput($('#tags'));
  }
  renderModules();

  // warn before leaving with unsaved edits
  let dirty = false;
  $('#courseForm').addEventListener('input', () => (dirty = true));
  $('#modules').addEventListener('click', (e) => e.target.closest('button') && (dirty = true));
  window.addEventListener('beforeunload', (e) => dirty && e.preventDefault());

  /* ---------- save ---------- */
  document.querySelectorAll('[data-save]').forEach((btn) =>
    btn.addEventListener('click', () => {
      if (!$('#title').value.trim()) {
        $('#title').classList.add('invalid');
        $('#titleError').hidden = false;
        $('#title').focus();
        toast('أكمل الحقول المطلوبة', 'error');
        return;
      }
      // lesson durations must look like 12:40 (minutes:seconds)
      const durInputs = $$('#modules [data-field="ldur"]');
      const badDur = durInputs.filter((i) => i.value.trim() && !/^\d{1,3}:[0-5]\d$/.test(i.value.trim()));
      durInputs.forEach((i) => i.classList.toggle('invalid', badDur.includes(i)));
      if (badDur.length) {
        badDur[0].focus();
        return toast('مدة الدرس بصيغة دقائق:ثوانٍ، مثل 12:40', 'error');
      }
      if (btn.dataset.save === 'publish' && !modules.some((m) => m.lessons.some((l) => l.title.trim()))) {
        toast('أضف درساً واحداً على الأقل قبل النشر', 'error');
        return;
      }
      dirty = false;
      // TODO: أرسل البيانات إلى الخادم (POST /api/courses)
      toast(btn.dataset.save === 'publish' ? 'تم نشر الدورة' : 'تم حفظ المسودة');
      setTimeout(() => (location.href = App.url('courses')), 900);
    }),
  );
});
