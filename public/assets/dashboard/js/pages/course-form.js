document.addEventListener('app:ready', async () => {
  const { $, esc, icon, toast, api, showFieldErrors } = App;
  const id = Number(document.body.dataset.id) || null; // set by the edit route: /dashboard/courses/{id}/edit
  let existing = null;
  if (id) {
    try {
      existing = (await api.get(`courses/${id}`)).data;
    } catch {
      return;
    }
  }

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

  /* ---------- cover image preview (drag & drop or pick) ---------- */
  const cover = $('#cover');
  let coverFile = null; // uploaded right after the course is saved
  const coverImage = (src) => {
    cover.classList.add('has-image');
    cover.querySelectorAll('img').forEach((i) => {
      if (i.src.startsWith('blob:')) URL.revokeObjectURL(i.src);
      i.remove();
    });
    cover.insertAdjacentHTML('afterbegin', `<img src="${esc(src)}" alt="معاينة الغلاف">`);
  };
  const showCover = (file) => {
    if (!file || !/^image\/(jpeg|png|webp)$/.test(file.type)) return toast('اختر صورة بصيغة JPG أو PNG أو WebP', 'error');
    if (file.size > 20 * 1024 * 1024) return toast('الحد الأقصى للصورة 20MB', 'error');
    coverFile = file;
    coverImage(URL.createObjectURL(file));
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

  /* ---------- edit mode: fill the form from the saved course ---------- */
  let outcomes;
  let tags;
  function fillFrom(c) {
    document.title = `تعديل: ${c.title} | لوحة التحكم`;
    $('#pageTitle').textContent = 'تعديل الدورة';
    $('#crumb').textContent = c.title;
    $('#title').value = c.title;
    $('#slug').value = c.slug;
    slugTouched = true;
    $('#short').value = c.short_description || '';
    $('#shortCount').textContent = $('#short').value.length;
    $('#desc').value = c.description || '';
    $('#price').value = c.price;
    $('#oldPrice').value = c.old_price ?? '';
    $('#ppp').checked = c.has_regional_pricing;
    $('#pro').checked = c.is_included_in_pro;
    $('#status').value = c.status;
    $('#publishAt').value = c.publish_at || '';
    $('#certificate').checked = c.has_certificate;
    $('#comments').checked = c.allows_questions;
    $('#level').value = c.level;
    $('#category').value = c.category;
    if (c.cover_url) coverImage(c.cover_url);
  }
  if (existing) {
    fillFrom(existing);
    outcomes = tagsInput($('#outcomes'), existing.outcomes);
    tags = tagsInput($('#tags'), existing.tags);
  } else {
    outcomes = tagsInput($('#outcomes'));
    tags = tagsInput($('#tags'));
  }
  // warn before leaving with unsaved edits
  let dirty = false;
  $('#courseForm').addEventListener('input', () => (dirty = true));
  window.addEventListener('beforeunload', (e) => dirty && e.preventDefault());

  /* ---------- save ---------- */
  // "publish" puts the course live; "draft" keeps the status chosen in the side panel unless it says published
  let saving = false;
  document.querySelectorAll('[data-save]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      if (saving) return;
      if (!$('#title').value.trim()) {
        $('#title').classList.add('invalid');
        $('#titleError').hidden = false;
        $('#title').focus();
        toast('أكمل الحقول المطلوبة', 'error');
        return;
      }
      const chosen = $('#status').value;
      const status = btn.dataset.save === 'publish' ? 'published' : chosen === 'published' ? 'draft' : chosen;
      const data = {
        title: $('#title').value.trim(),
        slug: $('#slug').value.trim() || null,
        short_description: $('#short').value.trim() || null,
        description: $('#desc').value.trim() || null,
        outcomes: [...outcomes],
        tags: [...tags],
        level: $('#level').value,
        category: $('#category').value,
        status,
        publish_at: $('#publishAt').value || null,
        price: Number($('#price').value) || 0,
        old_price: $('#oldPrice').value === '' ? null : Number($('#oldPrice').value),
        has_regional_pricing: $('#ppp').checked,
        is_included_in_pro: $('#pro').checked,
        has_certificate: $('#certificate').checked,
        allows_questions: $('#comments').checked,
      };
      const fields = { title: '#title', slug: '#slug', short_description: '#short', description: '#desc', price: '#price', old_price: '#oldPrice', publish_at: '#publishAt' };
      saving = true;
      try {
        existing = (await (existing ? api.put(`courses/${existing.id}`, data) : api.post('courses', data))).data;
        if (coverFile) {
          const form = new FormData();
          form.append('cover', await App.shrinkImage(coverFile));
          existing = (await api.post(`courses/${existing.id}/cover`, form)).data;
          coverFile = null;
        }
      } catch (err) {
        return showFieldErrors(err, fields);
      } finally {
        saving = false;
      }
      dirty = false;
      toast(status === 'published' ? 'تم نشر الدورة' : 'تم حفظ الدورة');
      setTimeout(() => (location.href = App.url('courses')), 900);
    }),
  );
});
