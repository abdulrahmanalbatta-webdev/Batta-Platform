document.addEventListener('app:ready', async () => {
  const { $, $$, esc, num, icon, toast, confirmDialog, api, ApiError } = App;
  const OPTIONS = JSON.parse($('#pathOptions').textContent);
  const ITEM_ICONS = { course: 'play', workshop: 'calendar', article: 'article' };
  const MAX = { stages: 15, resources: 12, items: 10, topics: 15 };
  let id = Number(document.body.dataset.id) || null; // set by the edit route: /dashboard/paths/{id}/edit

  // the path, and the platform's own content a stage can link to: { course: Map(id → {title, note}), … }
  let path;
  const content = { course: new Map(), workshop: new Map(), article: new Map() };
  try {
    const today = new Date().toISOString().slice(0, 10);
    const [saved, courses, workshops, articles] = await Promise.all([
      id ? api.get(`learning-paths/${id}`) : null,
      api.get('courses'),
      api.get('workshops'),
      api.get('articles'),
    ]);
    path = saved?.data ?? { title: '', slug: '', icon: 'code', summary: '', audience: '', duration: '', outcomes: [], is_published: false, stages: [] };
    // a note when the site won't show a linked item (the link stays, and shows again once it's published)
    courses.data.forEach((c) => content.course.set(c.id, { title: c.title, off: c.status !== 'published', note: c.status === 'published' ? '' : `${c.status_label}، لن تظهر في الموقع` }));
    workshops.data.forEach((w) => content.workshop.set(w.id, { title: w.title, off: w.date < today, note: w.date < today ? 'انتهت، لن تظهر في الموقع' : App.date(w.date) }));
    articles.data.forEach((a) => content.article.set(a.id, { title: a.title, off: a.status !== 'published', note: a.status === 'published' ? '' : `${a.status_label}، لن يظهر في الموقع` }));
  } catch {
    return;
  }

  /* ---------- the path's own fields ---------- */
  const fields = { title: '#title', slug: '#slug', summary: '#summary', audience: '#audience', duration: '#duration', icon: '#icon' };
  Object.entries(fields).forEach(([key, sel]) => ($(sel).value = path[key] ?? ''));
  $('#published').checked = path.is_published;
  $('#summaryCount').textContent = $('#summary').value.length;
  $('#summary').addEventListener('input', (e) => ($('#summaryCount').textContent = e.target.value.length));

  let slugTouched = !!path.slug;
  $('#slug').addEventListener('input', () => (slugTouched = true));
  $('#title').addEventListener('input', (e) => {
    // keep Latin letters/numbers from the title, e.g. "مطوّر واجهات Frontend" → "frontend"
    if (!slugTouched) $('#slug').value = e.target.value.toLowerCase().replace(/[^a-z0-9\s-]/g, '').trim().replace(/\s+/g, '-');
  });

  function heading() {
    if (!id) return;
    document.title = `تعديل: ${path.title} | لوحة التحكم`;
    $('#pageTitle').textContent = 'تعديل المسار';
    $('#crumb').textContent = path.title;
    const link = $('#viewOnSite');
    link.hidden = !(path.is_published && App.siteUrl);
    link.href = `${App.siteUrl}/paths/${path.slug}`;
  }
  heading();

  // outcomes: chips typed with Enter
  const outcomes = [...(path.outcomes ?? [])];
  (function outcomesInput(root) {
    const input = $('input', root);
    const draw = () => {
      $$('.tag-chip', root).forEach((c) => c.remove());
      outcomes.forEach((v, i) => input.insertAdjacentHTML('beforebegin', `<span class="tag-chip">${esc(v)}<button type="button" data-outcome="${i}" aria-label="إزالة">${icon('close', 'sm')}</button></span>`));
    };
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter' || e.key === ',') {
        e.preventDefault();
        const v = input.value.trim();
        if (v && !outcomes.includes(v) && outcomes.length < 8) outcomes.push(v);
        input.value = '';
        draw();
        dirty = true;
      } else if (e.key === 'Backspace' && !input.value && outcomes.length) {
        outcomes.pop();
        draw();
      }
    });
    root.addEventListener('click', (e) => {
      const remove = e.target.closest('[data-outcome]');
      if (remove) {
        outcomes.splice(Number(remove.dataset.outcome), 1);
        draw();
        dirty = true;
      } else input.focus();
    });
    draw();
  })($('#outcomes'));

  /* ---------- stages ---------- */
  const stages = (path.stages ?? []).map((s) => ({ title: s.title, text: s.text ?? '', topics: [...(s.topics ?? [])], resources: (s.resources ?? []).map((r) => ({ ...r })), items: (s.items ?? []).map((i) => ({ ...i })) }));
  const collapsed = new WeakSet(stages.length > 2 ? stages : []); // long maps open folded
  const blankStage = () => ({ title: '', text: '', topics: [], resources: [], items: [] });
  const blankResource = () => ({ title: '', url: '', type: Object.keys(OPTIONS.resource_types)[0], lang: 'ar' });

  const options = (map, value) => Object.entries(map).map(([k, l]) => `<option value="${esc(k)}" ${k === value ? 'selected' : ''}>${esc(l)}</option>`).join('');
  const moveButtons = (kind, s, i, count) => `
    <button type="button" class="btn-icon" data-move="${kind}" data-s="${s}" data-i="${i}" data-to="${i - 1}" ${i === 0 ? 'disabled' : ''} title="لأعلى" aria-label="لأعلى">${icon('arrow-up', 'sm')}</button>
    <button type="button" class="btn-icon" data-move="${kind}" data-s="${s}" data-i="${i}" data-to="${i + 1}" ${i === count - 1 ? 'disabled' : ''} title="لأسفل" aria-label="لأسفل">${icon('arrow-down', 'sm')}</button>
    <button type="button" class="btn-icon danger" data-remove="${kind}" data-s="${s}" data-i="${i}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>`;

  const resourceRow = (r, s, i, count) => `
    <div class="res-row">
      <input class="input" data-s="${s}" data-r="${i}" data-f="title" data-key="stages.${s}.resources.${i}.title" value="${esc(r.title)}" maxlength="100" placeholder="اسم المصدر" aria-label="اسم المصدر">
      <input class="input ltr" type="url" data-s="${s}" data-r="${i}" data-f="url" data-key="stages.${s}.resources.${i}.url" value="${esc(r.url)}" maxlength="255" placeholder="https://" aria-label="الرابط">
      <select class="select" data-s="${s}" data-r="${i}" data-f="type" data-key="stages.${s}.resources.${i}.type" aria-label="النوع">${options(OPTIONS.resource_types, r.type)}</select>
      <select class="select" data-s="${s}" data-r="${i}" data-f="lang" data-key="stages.${s}.resources.${i}.lang" aria-label="اللغة">${options(OPTIONS.languages, r.lang)}</select>
      <div class="res-actions">${moveButtons('resource', s, i, count)}</div>
    </div>`;

  const itemChip = (item, s, i, count) => {
    const found = content[item.type].get(item.id);
    return `<div class="link-row ${found?.off ? 'is-off' : ''} ${found ? '' : 'is-missing'}" data-key="stages.${s}.items.${i}.id">
      <span class="kpi-ico c-blue">${icon(ITEM_ICONS[item.type], 'sm')}</span>
      <div class="grow"><b>${found ? esc(found.title) : 'محتوى محذوف'}</b><small>${esc(OPTIONS.item_types[item.type])}${found?.note ? ` · ${esc(found.note)}` : ''}${found ? '' : ' · احذفه من المرحلة'}</small></div>
      ${moveButtons('item', s, i, count)}
    </div>`;
  };

  // the picker lists what isn't linked to this stage yet
  const itemOptions = (stage, type) => {
    const taken = new Set(stage.items.filter((i) => i.type === type).map((i) => i.id));
    const free = [...content[type]].filter(([key]) => !taken.has(key));
    return free.length ? `<option value="">اختر…</option>${free.map(([key, c]) => `<option value="${key}">${esc(c.title)}${c.note ? ` (${esc(c.note)})` : ''}</option>`).join('')}` : '<option value="">لا يوجد ما يُضاف</option>';
  };

  const stageCard = (stage, s) => `
    <li class="stage-card ${collapsed.has(stage) ? 'collapsed' : ''}" data-stage="${s}">
      <div class="stage-head">
        <span class="stage-num">${s + 1}</span>
        <input class="input" data-s="${s}" data-f="title" data-key="stages.${s}.title" value="${esc(stage.title)}" maxlength="80" placeholder="عنوان المرحلة، مثلاً: أساسيات الويب" aria-label="عنوان المرحلة">
        <span class="stage-count muted">${num(stage.resources.length)} مصادر · ${num(stage.items.length)} مرتبط</span>
        <button type="button" class="btn-icon" data-fold="${s}" title="${collapsed.has(stage) ? 'فتح' : 'طيّ'}" aria-label="${collapsed.has(stage) ? 'فتح المرحلة' : 'طيّ المرحلة'}" aria-expanded="${!collapsed.has(stage)}">${icon('chevron-down', 'sm')}</button>
        ${moveButtons('stage', s, s, stages.length)}
      </div>
      <div class="stage-body">
        <div class="field">
          <label>ماذا يتعلّم في هذه المرحلة ولماذا</label>
          <textarea class="textarea" rows="2" data-s="${s}" data-f="text" data-key="stages.${s}.text" maxlength="600" placeholder="جملتان تشرحان المرحلة للطالب">${esc(stage.text)}</textarea>
        </div>
        <div class="field">
          <label>المواضيع</label>
          <div class="tags-input" data-topics="${s}">${stage.topics.map((t, i) => `<span class="tag-chip">${esc(t)}<button type="button" data-topic="${i}" data-s="${s}" aria-label="إزالة">${icon('close', 'sm')}</button></span>`).join('')}<input placeholder="اكتب موضوعاً واضغط Enter" aria-label="المواضيع" data-topic-input="${s}"></div>
        </div>

        <div class="stage-part">
          <div class="part-head"><b>${icon('link', 'sm')}مصادر مجانية</b><span class="muted">تفتح للطالب في تبويب جديد</span></div>
          ${stage.resources.map((r, i) => resourceRow(r, s, i, stage.resources.length)).join('') || '<p class="muted part-empty">لا توجد مصادر بعد.</p>'}
          ${stage.resources.length < MAX.resources ? `<button type="button" class="btn btn-ghost btn-sm" data-add-resource="${s}">${icon('plus', 'sm')}إضافة مصدر</button>` : ''}
        </div>

        <div class="stage-part">
          <div class="part-head"><b>${icon('play', 'sm')}من محتواك</b><span class="muted">دوراتك وورشك ومقالاتك تظهر كبطاقات داخل المرحلة</span></div>
          ${stage.items.map((item, i) => itemChip(item, s, i, stage.items.length)).join('') || '<p class="muted part-empty">لم تربط شيئاً بعد.</p>'}
          ${stage.items.length < MAX.items ? `<div class="link-add">
            <select class="select" data-pick-type="${s}" aria-label="النوع">${options(OPTIONS.item_types, 'course')}</select>
            <select class="select" data-pick-item="${s}" aria-label="المحتوى">${itemOptions(stage, 'course')}</select>
            <button type="button" class="btn btn-ghost btn-sm" data-add-item="${s}">${icon('plus', 'sm')}ربط</button>
          </div>` : ''}
        </div>
      </div>
    </li>`;

  function renderStages() {
    $('#stages').innerHTML = stages.map(stageCard).join('') || `<li class="empty"><span class="e-ico">${icon('layers')}</span><b>لا توجد مراحل بعد</b>أضف المرحلة الأولى من الطريق.</li>`;
    $('#addStage').hidden = stages.length >= MAX.stages;
    $('#stagesNote').textContent = stages.length ? `${num(stages.length)} من ${MAX.stages}` : '';
    summary();
  }

  function summary() {
    const count = (key) => stages.reduce((a, s) => a + s[key].length, 0);
    $('#summaryBox').innerHTML = [
      ['layers', 'المراحل', stages.length],
      ['code', 'المواضيع', count('topics')],
      ['link', 'المصادر المجانية', count('resources')],
      ['play', 'المحتوى المرتبط', count('items')],
    ]
      .map(([ic, label, n]) => `<div class="sum-row">${icon(ic, 'sm')}<span class="grow">${label}</span><b class="num">${num(n)}</b></div>`)
      .join('');
  }

  /* ---------- editing ---------- */
  let dirty = false;
  $('#pathForm').addEventListener('input', (e) => {
    dirty = true;
    e.target.classList.remove('invalid');
    const el = e.target.closest('[data-f]');
    if (!el || el.dataset.s === undefined) return;
    const stage = stages[Number(el.dataset.s)];
    if (el.dataset.r !== undefined) stage.resources[Number(el.dataset.r)][el.dataset.f] = el.value;
    else stage[el.dataset.f] = el.value;
  });
  window.addEventListener('beforeunload', (e) => dirty && e.preventDefault());

  // the item picker follows the chosen type
  $('#stages').addEventListener('change', (e) => {
    const type = e.target.closest('[data-pick-type]');
    if (type) $(`[data-pick-item="${type.dataset.pickType}"]`).innerHTML = itemOptions(stages[Number(type.dataset.pickType)], type.value);
  });

  // topics: Enter or comma adds one, Backspace on an empty box removes the last
  $('#stages').addEventListener('keydown', (e) => {
    const input = e.target.closest('[data-topic-input]');
    if (!input) return;
    const s = Number(input.dataset.topicInput);
    const topics = stages[s].topics;
    if (e.key === 'Enter' || e.key === ',') {
      e.preventDefault();
      const v = input.value.trim().slice(0, 40);
      if (v && !topics.includes(v) && topics.length < MAX.topics) topics.push(v);
    } else if (e.key === 'Backspace' && !input.value && topics.length) {
      topics.pop();
    } else return;
    dirty = true;
    redrawStage(s, `[data-topic-input="${s}"]`);
  });

  // re-draw one stage, keeping the focus where the editor was typing
  function redrawStage(s, focus) {
    const card = $(`[data-stage="${s}"]`);
    card.outerHTML = stageCard(stages[s], s);
    if (focus) $(focus)?.focus();
    summary();
  }

  const moveIn = (list, from, to) => list.splice(to, 0, list.splice(from, 1)[0]);

  $('#stages').addEventListener('click', async (e) => {
    const b = e.target.closest('button');
    if (!b) {
      if (e.target.closest('.tags-input')) $('input', e.target.closest('.tags-input')).focus();
      return;
    }
    const s = Number(b.dataset.s ?? b.dataset.fold ?? b.dataset.addResource ?? b.dataset.addItem);
    const stage = stages[s];

    if (b.dataset.fold !== undefined) {
      collapsed.has(stage) ? collapsed.delete(stage) : collapsed.add(stage);
      return redrawStage(s);
    }
    if (b.dataset.topic !== undefined) {
      stage.topics.splice(Number(b.dataset.topic), 1);
      dirty = true;
      return redrawStage(s);
    }
    if (b.dataset.addResource !== undefined) {
      stage.resources.push(blankResource());
      dirty = true;
      return redrawStage(s, `[data-s="${s}"][data-r="${stage.resources.length - 1}"][data-f="title"]`);
    }
    if (b.dataset.addItem !== undefined) {
      const type = $(`[data-pick-type="${s}"]`).value;
      const picked = Number($(`[data-pick-item="${s}"]`).value);
      if (!picked) return toast('اختر ما تريد ربطه أولاً', 'error');
      stage.items.push({ type, id: picked });
      dirty = true;
      redrawStage(s);
      $(`[data-pick-type="${s}"]`).value = type;
      $(`[data-pick-item="${s}"]`).innerHTML = itemOptions(stage, type);
      return;
    }

    const kind = b.dataset.move ?? b.dataset.remove;
    if (!kind) return;
    const i = Number(b.dataset.i);
    const list = kind === 'stage' ? stages : kind === 'resource' ? stage.resources : stage.items;
    if (b.dataset.move) {
      moveIn(list, i, Number(b.dataset.to));
    } else {
      if (kind === 'stage' && (stage.title || stage.resources.length || stage.items.length) && !(await confirmDialog({ title: 'حذف المرحلة؟', text: `ستُحذف "${stage.title || `المرحلة ${s + 1}`}" بمصادرها وروابطها من المسار.`, ok: 'حذف' }))) return;
      list.splice(i, 1);
    }
    dirty = true;
    kind === 'stage' ? renderStages() : redrawStage(s);
  });

  $('#addStage').addEventListener('click', () => {
    stages.push(blankStage());
    dirty = true;
    renderStages();
    $(`[data-stage="${stages.length - 1}"] [data-f="title"]`).focus();
  });

  /* ---------- save ---------- */
  // a 422 marks every field it names, opens the stages they're in and focuses the first
  function showErrors(err) {
    if (!(err instanceof ApiError) || err.status !== 422) return;
    const keys = Object.keys(err.errors);
    keys.forEach((key) => {
      const stage = key.match(/^stages\.(\d+)\./);
      if (stage && collapsed.has(stages[Number(stage[1])])) {
        collapsed.delete(stages[Number(stage[1])]);
        redrawStage(Number(stage[1]));
      }
    });
    const marked = keys.map((key) => $(fields[key] ?? `[data-key="${key}"]`)).filter(Boolean);
    marked.forEach((el) => el.classList.add('invalid'));
    marked[0]?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    marked[0]?.focus?.();
    toast(err.errors[keys[0]]?.[0] || err.message, 'error');
  }

  let saving = false;
  $('#save').addEventListener('click', async () => {
    if (saving) return;
    $$('.invalid', $('#pathForm')).forEach((el) => el.classList.remove('invalid'));
    if (!$('#title').value.trim()) {
      $('#title').classList.add('invalid');
      $('#title').focus();
      return toast('اكتب اسم المسار', 'error');
    }
    const data = {
      title: $('#title').value.trim(),
      slug: $('#slug').value.trim() || null,
      icon: $('#icon').value,
      summary: $('#summary').value.trim() || null,
      audience: $('#audience').value.trim() || null,
      duration: $('#duration').value.trim() || null,
      outcomes: [...outcomes],
      is_published: $('#published').checked,
      stages: stages.map((s) => ({ ...s, title: s.title.trim(), text: s.text.trim() || null, resources: s.resources.map((r) => ({ ...r, title: r.title.trim(), url: r.url.trim() })) })),
    };
    saving = true;
    try {
      path = (await (id ? api.put(`learning-paths/${id}`, data) : api.post('learning-paths', data))).data;
    } catch (err) {
      return showErrors(err);
    } finally {
      saving = false;
    }
    dirty = false;
    const created = !id;
    id = path.id;
    $('#slug').value = path.slug;
    slugTouched = true;
    if (created) history.replaceState(null, '', App.url('path-edit', { id }));
    heading();
    toast(created ? 'تم إنشاء المسار' : path.is_published ? 'تم حفظ المسار، والتعديلات ظاهرة في الموقع' : 'تم حفظ المسار');
  });

  renderStages();
});
