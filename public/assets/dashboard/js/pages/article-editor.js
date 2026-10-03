document.addEventListener('app:ready', async () => {
  const { $, esc, toast, openDrawer, api, showFieldErrors } = App;
  const body = $('#body');
  const id = Number(document.body.dataset.id) || null; // set by the edit route: /dashboard/articles/{id}/edit
  let existing = null;
  if (id) {
    try {
      existing = (await api.get(`articles/${id}`)).data;
    } catch {
      return;
    }
  }
  // one draft per article, so editing an old article never leaks into "new article"
  const DRAFT_KEY = `batta-article-draft:${existing ? existing.id : 'new'}`;
  // <input type="datetime-local"> holds local wall time; the API takes and returns ISO instants
  const toLocalInput = (iso) => {
    if (!iso) return '';
    const d = new Date(iso);
    return new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
  };

  /* ---------- markdown toolbar ---------- */
  const wrap = (before, after = before, placeholder = 'نص') => {
    const { selectionStart: s, selectionEnd: e, value } = body;
    const sel = value.slice(s, e) || placeholder;
    body.setRangeText(before + sel + after, s, e, 'select');
    body.focus();
    body.dispatchEvent(new Event('input'));
  };
  const linePrefix = (prefix) => {
    const { selectionStart: s, value } = body;
    const lineStart = value.lastIndexOf('\n', s - 1) + 1;
    body.setRangeText(prefix, lineStart, lineStart, 'end');
    body.focus();
    body.dispatchEvent(new Event('input'));
  };
  const actions = {
    heading: () => linePrefix('## '),
    bold: () => wrap('**'),
    italic: () => wrap('_'),
    list: () => linePrefix('- '),
    quote: () => linePrefix('> '),
    code: () => wrap('\n```js\n', '\n```\n', 'const hello = "world"'),
    link: () => wrap('[', '](https://)', 'نص الرابط'),
    image: () => wrap('![', '](https://)', 'وصف الصورة'),
  };
  document.querySelector('.editor-toolbar').addEventListener('click', (e) => {
    const b = e.target.closest('[data-md]');
    if (b) actions[b.dataset.md]();
  });
  body.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'b') { e.preventDefault(); actions.bold(); }
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'i') { e.preventDefault(); actions.italic(); }
  });

  /* ---------- stats ---------- */
  const wordCount = () => (body.value.trim() ? body.value.trim().split(/\s+/).length : 0);
  function updateStats() {
    const words = wordCount();
    $('#words').textContent = words.toLocaleString('en-US');
    $('#readTime').textContent = Math.max(1, Math.round(words / 200));
    $('#headings').textContent = (body.value.match(/^#{2,3}\s/gm) || []).length;
  }

  /* ---------- SEO preview ---------- */
  function updateSeo() {
    const t = $('#metaTitle').value || $('#title').value || 'عنوان المقال سيظهر هنا';
    const d = $('#metaDesc').value || $('#excerpt').value || 'وصف المقال في نتائج البحث سيظهر هنا.';
    $('#pvTitle').textContent = t;
    $('#pvDesc').textContent = d;
    $('#mtCount').textContent = $('#metaTitle').value.length;
    $('#mdCount').textContent = $('#metaDesc').value.length;
    // the slug is fixed once the article exists, so its links keep working
    const slug = existing ? existing.slug : $('#title').value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
    $('#pvSlug').textContent = slug || 'new-article';
  }

  /* ---------- autosave draft in this browser ---------- */
  const fields = ['title', 'excerpt', 'body', 'metaTitle', 'metaDesc'];
  let saveTimer;
  function autosave() {
    $('#saveState').textContent = 'جارٍ الحفظ…';
    clearTimeout(saveTimer);
    saveTimer = setTimeout(() => {
      try {
        localStorage.setItem(DRAFT_KEY, JSON.stringify(Object.fromEntries(fields.map((f) => [f, $('#' + f).value]))));
      } catch {
        /* storage unavailable */
      }
      $('#saveState').textContent = `حُفظت المسودة تلقائياً · ${new Date().toLocaleTimeString('ar-u-nu-latn', { hour: '2-digit', minute: '2-digit' })}`;
    }, 700);
  }
  fields.forEach((f) =>
    $('#' + f).addEventListener('input', (e) => {
      e.target.classList.remove('invalid');
      updateStats();
      updateSeo();
      autosave();
    }),
  );

  $('#status').addEventListener('change', (e) => ($('#scheduleField').hidden = e.target.value !== 'scheduled'));
  $('#publishAt').addEventListener('input', (e) => e.target.classList.remove('invalid'));

  /* ---------- cover preview ---------- */
  const cover = $('#cover');
  let coverFile = null; // uploaded right after the article is saved
  const showCover = (src) => {
    cover.classList.add('has-image');
    cover.querySelectorAll('img').forEach((i) => {
      if (i.src.startsWith('blob:')) URL.revokeObjectURL(i.src);
      i.remove();
    });
    cover.insertAdjacentHTML('afterbegin', `<img src="${esc(src)}" alt="">`);
  };
  $('input', cover).addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return toast('اختر صورة بصيغة JPG أو PNG أو WebP', 'error');
    if (f.size > 5 * 1024 * 1024) return toast('الحد الأقصى للصورة 5MB', 'error');
    coverFile = f;
    showCover(URL.createObjectURL(f));
  });

  /* ---------- load: existing article (?id), then any unsaved draft on top ---------- */
  let draft = null;
  try {
    draft = JSON.parse(localStorage.getItem(DRAFT_KEY) || 'null');
  } catch {
    /* storage unavailable */
  }
  function fillFrom(a) {
    document.title = `تعديل: ${a.title} | لوحة التحكم`;
    $('#pageTitle').textContent = 'تعديل المقال';
    $('#crumb').textContent = a.title;
    $('#title').value = a.title;
    $('#category').value = a.category;
    $('#status').value = a.status;
    $('#scheduleField').hidden = a.status !== 'scheduled';
    $('#publishAt').value = toLocalInput(a.publish_at);
    $('#excerpt').value = a.excerpt || '';
    body.value = a.body || '';
    $('#metaTitle').value = a.meta_title || '';
    $('#metaDesc').value = a.meta_description || '';
    $('#featured').checked = a.is_featured;
    $('#newsletter').checked = a.send_newsletter;
    if (a.cover_url) showCover(a.cover_url);
    $('#saveState').textContent = `آخر تعديل: ${new Date(a.updated_at).toLocaleString('ar-u-nu-latn', { dateStyle: 'medium', timeStyle: 'short' })}`;
  }
  if (existing) {
    fillFrom(existing);
  }
  if (draft) {
    fields.forEach((f) => ($('#' + f).value = draft[f] || ''));
    $('#saveState').textContent = 'تمت استعادة تعديلاتك غير المحفوظة';
  }
  updateStats();
  updateSeo();

  /* ---------- preview: small, safe markdown renderer ---------- */
  // the source is escaped first, so only the tags produced here can appear; links accept http(s) only
  function inline(s) {
    return s
      .replace(/`([^`]+)`/g, '<code style="direction:ltr;background:var(--tint);padding:1px 6px;border-radius:5px">$1</code>')
      .replace(/!\[([^\]]*)\]\((https?:\/\/[^\s)]+)\)/g, '<img src="$2" alt="$1" style="max-width:100%;border-radius:10px">')
      .replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a class="link" href="$2" target="_blank" rel="noopener">$1</a>')
      .replace(/\*\*(.+?)\*\*/g, '<b>$1</b>')
      .replace(/(^|[\s(])_(\S(?:.*?\S)?)_(?=[\s.,،؛:!?)]|$)/g, '$1<i>$2</i>');
  }
  function md(src) {
    // pull fenced code out first so blank lines inside code don't split it
    const codes = [];
    const text = esc(src).replace(/```\w*\n?([\s\S]*?)```/g, (_, code) => `\u0000${codes.push(code.replace(/\n$/, '')) - 1}\u0000`);
    return text
      .split(/\n{2,}/)
      .map((b) => b.trim())
      .filter(Boolean)
      .map((b) => {
        const code = b.match(/^\u0000(\d+)\u0000$/);
        if (code) return `<pre style="direction:ltr;text-align:left;background:#0b0d12;color:#c9d4e3;padding:14px;border-radius:10px;overflow:auto"><code>${codes[code[1]]}</code></pre>`;
        if (b.startsWith('### ')) return `<h4 style="font-size:16px;margin-top:6px">${inline(b.slice(4))}</h4>`;
        if (b.startsWith('## ')) return `<h3 style="font-size:19px;margin-top:8px">${inline(b.slice(3))}</h3>`;
        if (b.startsWith('&gt; ')) return `<p style="background:var(--primary-soft);border-inline-start:4px solid var(--primary);padding:10px 14px;border-radius:8px">${inline(b.replace(/^&gt; ?/gm, ''))}</p>`;
        if (b.split('\n').every((l) => /^- /.test(l))) return `<ul style="padding-inline-start:22px">${b.split('\n').map((l) => `<li>${inline(l.slice(2))}</li>`).join('')}</ul>`;
        if (b.split('\n').every((l) => /^\d+\. /.test(l))) return `<ol style="padding-inline-start:22px">${b.split('\n').map((l) => `<li>${inline(l.replace(/^\d+\. /, ''))}</li>`).join('')}</ol>`;
        return `<p>${inline(b).replace(/\n/g, '<br>').replace(/\u0000(\d+)\u0000/g, (_, i) => `<code>${codes[i]}</code>`)}</p>`;
      })
      .join('');
  }
  $('#previewBtn').addEventListener('click', () =>
    openDrawer(`
      <div class="drawer-head"><h3 style="font-size:17px">معاينة المقال</h3><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body" style="gap:12px;line-height:2">
        <span class="badge primary" style="width:fit-content">${esc($('#category').value)}</span>
        <h2 style="font-size:22px">${esc($('#title').value || 'بدون عنوان')}</h2>
        <p class="muted">${esc($('#excerpt').value)}</p>
        <hr style="border:0;border-top:1px solid var(--line);width:100%">
        ${md(body.value) || '<p class="muted">لا يوجد محتوى بعد.</p>'}
      </div>`),
  );

  /* ---------- save / publish ---------- */
  // "publish" puts the article live now; "save" keeps the status chosen in the side panel
  let saving = false;
  document.querySelectorAll('[data-save]').forEach((btn) =>
    btn.addEventListener('click', async () => {
      if (saving) return;
      const publish = btn.dataset.save === 'publish';
      if (!$('#title').value.trim()) {
        $('#title').classList.add('invalid');
        $('#title').focus();
        return toast('اكتب عنوان المقال أولاً', 'error');
      }
      const status = publish ? 'published' : $('#status').value;
      const at = $('#publishAt').value;
      const data = {
        title: $('#title').value.trim(),
        excerpt: $('#excerpt').value.trim() || null,
        body: body.value,
        category: $('#category').value,
        status,
        publish_at: status === 'scheduled' && at ? new Date(at).toISOString() : null,
        is_featured: $('#featured').checked,
        send_newsletter: $('#newsletter').checked,
        meta_title: $('#metaTitle').value.trim() || null,
        meta_description: $('#metaDesc').value.trim() || null,
      };
      const fields = { title: '#title', excerpt: '#excerpt', body: '#body', publish_at: '#publishAt', meta_title: '#metaTitle', meta_description: '#metaDesc' };
      saving = true;
      $('#saveState').textContent = 'جارٍ الحفظ…';
      try {
        existing = (await (existing ? api.put(`articles/${existing.id}`, data) : api.post('articles', data))).data;
        if (coverFile) {
          const form = new FormData();
          form.append('cover', coverFile);
          existing = (await api.post(`articles/${existing.id}/cover`, form)).data;
          coverFile = null;
        }
      } catch (err) {
        $('#saveState').textContent = 'لم يُحفظ';
        if (err.status === 422 && err.errors.publish_at) $('#scheduleField').hidden = false;
        return showFieldErrors(err, fields);
      } finally {
        saving = false;
      }
      clearTimeout(saveTimer);
      try { localStorage.removeItem(DRAFT_KEY); } catch { /* ignore */ }
      fillFrom(existing);
      toast(status === 'published' ? 'تم نشر المقال' : status === 'scheduled' ? 'تمت جدولة المقال' : 'تم حفظ المسودة');
      if (publish) setTimeout(() => (location.href = App.url('articles')), 900);
      // a new article now has an address of its own: keep editing it there
      else if (!id) history.replaceState(null, '', App.url('article-edit', { id: existing.id }));
    }),
  );
});
