document.addEventListener('app:ready', async () => {
  const { $, $$, esc, icon, toast, confirmDialog, api, showFieldErrors } = App;
  const canEdit = App.can('manage_content');
  if (App.siteUrl) $('#viewSite').href = App.siteUrl;
  else $('#viewSite').hidden = true;

  let res;
  try {
    res = await api.get('site-content');
  } catch {
    return;
  }
  const { definitions, groups, icons } = res.meta;
  const storageUrl = res.meta.storage_url;
  // what is being edited, per section; only "حفظ" sends it
  const state = Object.fromEntries(Object.keys(definitions).map((k) => [k, structuredClone(res.data[k])]));
  let photo = res.data.photo;

  /* ---------- paths: ["services", 2, "features", 0] ---------- */
  const enc = (path) => esc(JSON.stringify(path));
  const getAt = (path) => path.reduce((v, k) => v?.[k], state);
  const setAt = (path, value) => {
    const parent = getAt(path.slice(0, -1));
    parent[path.at(-1)] = value;
  };
  // an empty item for a list, from its fields
  const blank = (def) => {
    if (def.type === 'object') return Object.fromEntries(Object.entries(def.fields).map(([k, f]) => [k, blank(f)]));
    if (['list', 'strings', 'tags', 'images'].includes(def.type)) return [];
    if (def.type === 'image') return null;
    if (def.type === 'bool') return false;
    if (def.type === 'icon') return Object.keys(icons)[0];
    if (def.type === 'select') return Object.keys(def.options)[0];
    return '';
  };
  const blankItem = (def) => Object.fromEntries(Object.entries(def.item).map(([k, f]) => [k, blank(f)]));
  const dis = canEdit ? '' : 'disabled';
  const ltr = (name) => ['id', 'url', 'link'].includes(name);

  /* ---------- one field ---------- */
  function field(def, path, name) {
    const value = getAt(path);
    const label = def.label ? `<label>${esc(def.label)}${def.required ? ' *' : ''}</label>` : '';
    const hint = def.hint ? `<span class="hint">${esc(def.hint)}</span>` : '';
    const at = `data-path="${enc(path)}"`;

    switch (def.type) {
      case 'text':
      case 'url':
        return `<div class="field">${label}<input class="input ${ltr(name) || def.type === 'url' ? 'ltr' : ''}" ${at} value="${esc(value ?? '')}" maxlength="${def.max || 255}" ${dis}>${hint}</div>`;
      case 'textarea':
        return `<div class="field full">${label}<textarea class="textarea" rows="3" ${at} maxlength="${def.max || 1000}" ${dis}>${esc(value ?? '')}</textarea>${hint}</div>`;
      case 'bool':
        return `<div class="field"><label class="switch"><input type="checkbox" ${at} ${value ? 'checked' : ''} ${dis}><span class="track"></span>${esc(def.label)}</label></div>`;
      case 'icon':
      case 'select': {
        const options = def.type === 'icon' ? icons : def.options;
        return `<div class="field">${label}<select class="select" ${at} ${dis}>${Object.entries(options)
          .map(([k, l]) => `<option value="${esc(k)}" ${k === value ? 'selected' : ''}>${esc(l)}${def.type === 'icon' ? ` (${esc(k)})` : ''}</option>`)
          .join('')}</select></div>`;
      }
      case 'tags':
        return `<div class="field full">${label}<div class="sc-tags">${Object.entries(def.options)
          .map(([k, l]) => `<label><input type="checkbox" data-tag="${esc(k)}" ${at} ${value.includes(k) ? 'checked' : ''} ${dis}>${esc(l)}</label>`)
          .join('')}</div>${hint}</div>`;
      case 'strings':
        return `<div class="field full">${label}<div class="sc-list">${value
          .map((s, i) => {
            const p = [...path, i];
            const input = def.long
              ? `<textarea class="textarea" rows="3" data-path="${enc(p)}" maxlength="${def.max}" ${dis}>${esc(s)}</textarea>`
              : `<input class="input" data-path="${enc(p)}" value="${esc(s)}" maxlength="${def.max}" ${dis}>`;
            return `<div class="sc-row">${input}${canEdit ? moveButtons(path, i, value.length) : ''}</div>`;
          })
          .join('')}</div>${canEdit && value.length < def.max_items ? `<button type="button" class="btn btn-ghost btn-sm" data-add="${enc(path)}" style="align-self:flex-start">${icon('plus', 'sm')}إضافة</button>` : ''}${hint}</div>`;
      case 'image':
        return `<div class="field full">${label}<div class="sc-images">${value ? thumb(path, value) : canEdit ? uploader(path, false) : '<span class="muted">لا توجد صورة</span>'}</div>${hint}</div>`;
      case 'images': {
        const images = value ?? [];
        return `<div class="field full">${label}<div class="sc-images">${images.map((src, i) => thumb(path, src, i)).join('')}${canEdit && images.length < def.max_items ? uploader(path, true) : ''}</div>${hint}</div>`;
      }
      case 'list':
        return `<div class="field full">${label}${list(def, path)}</div>`;
      case 'object': {
        const grid = `<div class="form-grid">${Object.entries(def.fields).map(([k, f]) => field(f, [...path, k], k)).join('')}</div>`;
        // a group inside a section (the page texts): its own small heading
        return path.length > 1 ? `<fieldset class="field full sc-group"><legend>${esc(def.label)}</legend>${grid}</fieldset>` : grid;
      }
      default:
        return '';
    }
  }

  // uploaded content images: a thumbnail with a remove button, and an upload tile
  const thumb = (path, src, i) => `<figure class="sc-thumb"><img src="${esc(`${storageUrl}/${src}`)}" alt="" loading="lazy">${canEdit ? `<button type="button" class="btn-icon danger" data-image-remove="${enc(path)}" ${i === undefined ? '' : `data-index="${i}"`} aria-label="إزالة الصورة" title="إزالة">${icon('trash', 'sm')}</button>` : ''}</figure>`;
  const uploader = (path, many) => `<label class="sc-upload">${icon('upload')}<span>${many ? 'إضافة صور' : 'رفع صورة'}</span><input type="file" accept="image/jpeg,image/png,image/webp" ${many ? 'multiple' : ''} data-upload="${enc(path)}" data-many="${many ? 1 : ''}" hidden></label>`;

  const moveButtons = (path, i, count) => `
    <button type="button" class="btn-icon" data-move="${enc(path)}" data-from="${i}" data-to="${i - 1}" ${i === 0 ? 'disabled' : ''} aria-label="لأعلى" title="لأعلى">${icon('arrow-up', 'sm')}</button>
    <button type="button" class="btn-icon" data-move="${enc(path)}" data-from="${i}" data-to="${i + 1}" ${i === count - 1 ? 'disabled' : ''} aria-label="لأسفل" title="لأسفل">${icon('arrow-down', 'sm')}</button>
    <button type="button" class="btn-icon danger" data-remove="${enc(path)}" data-index="${i}" aria-label="حذف" title="حذف">${icon('trash', 'sm')}</button>`;

  function list(def, path) {
    const items = getAt(path);
    const title = (item, i) => {
      const t = item[def.title];
      const shown = def.item[def.title]?.type === 'select' ? def.item[def.title].options[t] : t;
      return shown ? esc(shown) : `عنصر ${i + 1}`;
    };
    return `<div class="sc-list">${items
      .map(
        (item, i) => `<details class="sc-item">
          <summary><span class="grow">${title(item, i)}</span>${canEdit ? moveButtons(path, i, items.length) : ''}</summary>
          <div class="sc-item-body"><div class="form-grid">${Object.entries(def.item).map(([k, f]) => field(f, [...path, i, k], k)).join('')}</div></div>
        </details>`,
      )
      .join('')}</div>${canEdit && items.length < def.max_items ? `<button type="button" class="btn btn-ghost btn-sm" data-add-item="${enc(path)}" style="align-self:flex-start;margin-top:10px">${icon('plus', 'sm')}إضافة عنصر</button>` : ''}`;
  }

  /* ---------- one section (= one saved key) ---------- */
  function section(key) {
    const def = definitions[key];
    const top = def.type === 'object' ? field(def, [key]) : field({ ...def, label: '' }, [key]);
    return `<section class="sc-section" data-section="${key}">
      <div class="sc-head">
        <div><h3>${esc(def.label)}</h3>${def.hint ? `<p>${esc(def.hint)}</p>` : ''}</div>
        ${canEdit ? `<div class="sc-actions"><button type="button" class="btn btn-ghost btn-sm" data-reset="${key}">استعادة الأصل</button><button type="button" class="btn btn-primary btn-sm" data-save="${key}">${icon('save', 'sm')}حفظ</button></div>` : ''}
      </div>
      <div class="sc-body">${top}</div>
    </section>`;
  }

  // re-draw one section after adding, removing or moving, keeping open items open
  function redraw(key) {
    const el = $(`[data-section="${key}"]`);
    const open = $$('details[open] > summary .grow', el).map((s) => s.textContent);
    el.outerHTML = section(key);
    const fresh = $(`[data-section="${key}"]`);
    $$('details > summary .grow', fresh).forEach((s) => open.includes(s.textContent) && (s.closest('details').open = true));
    App.hydrateIcons?.(fresh);
  }

  const photoCard = () => `<section class="sc-section" id="photoSection">
      <div class="sc-head"><div><h3>صورتك</h3><p>تظهر في الرئيسية وصفحة "من أنا". مربعة وواضحة، 300×300 على الأقل.</p></div></div>
      <div class="sc-photo">
        ${photo ? `<img src="${esc(photo)}" alt="">` : '<span class="ph">صورة الموقع الحالية</span>'}
        ${canEdit ? `<label class="btn btn-ghost btn-sm">${icon('upload', 'sm')}رفع صورة<input type="file" id="photoInput" accept="image/jpeg,image/png,image/webp" hidden></label>${photo ? '<button type="button" class="btn btn-ghost btn-sm" id="photoRemove">إزالة</button>' : ''}` : ''}
      </div>
    </section>`;

  /* ---------- tabs ---------- */
  $('#contentTabs').innerHTML = Object.entries(groups).map(([g, l], i) => `<button type="button" class="${i ? '' : 'on'}" data-tab="${g}" role="tab">${esc(l)}</button>`).join('');
  $('#contentPanels').innerHTML = Object.keys(groups)
    .map(
      (g, i) => `<div class="tab-panel ${i ? '' : 'on'}" data-panel-group="content" data-panel="${g}">
        ${g === 'about' ? photoCard() : ''}
        ${Object.keys(definitions).filter((k) => definitions[k].group === g).map(section).join('')}
      </div>`,
    )
    .join('');
  App.initTabs($('.card'));
  const tab = location.hash.slice(1);
  if (groups[tab]) $(`[data-tab="${tab}"]`).click();

  /* ---------- editing ---------- */
  const dirty = new Set();
  const panels = $('#contentPanels');
  panels.addEventListener('input', (e) => {
    const el = e.target.closest('[data-path]');
    if (!el) return;
    const path = JSON.parse(el.dataset.path);
    if (el.dataset.tag) {
      const tags = getAt(path);
      const i = tags.indexOf(el.dataset.tag);
      if (el.checked && i < 0) tags.push(el.dataset.tag);
      if (!el.checked && i >= 0) tags.splice(i, 1);
    } else {
      setAt(path, el.type === 'checkbox' ? el.checked : el.value);
    }
    el.classList.remove('invalid');
    dirty.add(path[0]);
  });

  // content images: uploaded right away, kept in the section until "حفظ"
  panels.addEventListener('change', async (e) => {
    const input = e.target.closest('[data-upload]');
    if (!input?.files.length) return;
    const path = JSON.parse(input.dataset.upload);
    const files = [...input.files];
    const label = input.closest('.sc-upload');
    label.classList.add('busy');
    for (const file of input.dataset.many ? files : files.slice(0, 1)) {
      if (!/^image\/(jpeg|png|webp)$/.test(file.type)) {
        toast('اختر صورة بصيغة JPG أو PNG أو WebP', 'error');
        continue;
      }
      const form = new FormData();
      form.append('image', await App.shrinkImage(file));
      let uploaded;
      try {
        uploaded = (await api.post('site-images', form)).data;
      } catch (err) {
        showFieldErrors(err);
        break;
      }
      if (input.dataset.many) getAt(path).push(uploaded.path);
      else setAt(path, uploaded.path);
    }
    dirty.add(path[0]);
    redraw(path[0]);
    toast('تم رفع الصورة، اضغط "حفظ" لتظهر في الموقع');
  });

  panels.addEventListener('click', async (e) => {
    const remove = e.target.closest('[data-image-remove]');
    if (remove) {
      const path = JSON.parse(remove.dataset.imageRemove);
      if (remove.dataset.index === undefined) setAt(path, null);
      else getAt(path).splice(Number(remove.dataset.index), 1);
      dirty.add(path[0]);
      return redraw(path[0]);
    }
    const b = e.target.closest('button');
    if (!b) return;
    if (b.closest('summary')) e.preventDefault(); // buttons in a summary don't toggle it

    if (b.dataset.add) {
      const path = JSON.parse(b.dataset.add);
      getAt(path).push('');
      dirty.add(path[0]);
      redraw(path[0]);
    } else if (b.dataset.addItem) {
      const path = JSON.parse(b.dataset.addItem);
      const def = defAt(path);
      getAt(path).push(blankItem(def));
      dirty.add(path[0]);
      redraw(path[0]);
      const items = $$(`[data-section="${path[0]}"] details`);
      if (items.length) items.at(-1).open = true;
    } else if (b.dataset.move) {
      const path = JSON.parse(b.dataset.move);
      const arr = getAt(path);
      const [from, to] = [Number(b.dataset.from), Number(b.dataset.to)];
      [arr[from], arr[to]] = [arr[to], arr[from]];
      dirty.add(path[0]);
      redraw(path[0]);
    } else if (b.dataset.remove) {
      const path = JSON.parse(b.dataset.remove);
      getAt(path).splice(Number(b.dataset.index), 1);
      dirty.add(path[0]);
      redraw(path[0]);
    } else if (b.dataset.save) {
      await save(b.dataset.save, b);
    } else if (b.dataset.reset) {
      const key = b.dataset.reset;
      if (!(await confirmDialog({ title: `استعادة "${definitions[key].label}"؟`, text: 'يعود هذا القسم في الموقع إلى نصه الأصلي، وتضيع تعديلاته.', ok: 'استعادة' }))) return;
      try {
        state[key] = (await api.delete(`site-content/${key}`)).data;
      } catch {
        return;
      }
      dirty.delete(key);
      redraw(key);
      toast('عاد القسم إلى نصه الأصلي');
    }
  });

  // the schema of the list at a path (skipping item indexes)
  function defAt(path) {
    let def = definitions[path[0]];
    for (const k of path.slice(1)) {
      if (typeof k === 'number') continue;
      def = def.type === 'object' ? def.fields[k] : def.item[k];
    }
    return def;
  }

  async function save(key, btn) {
    btn.disabled = true;
    try {
      state[key] = (await api.put(`site-content/${key}`, { value: state[key] })).data;
    } catch (err) {
      if (err.status === 422) {
        // "value.2.features.0" → the input with that path
        const fields = Object.fromEntries(
          Object.keys(err.errors).map((k) => {
            const path = [key, ...k.split('.').slice(1).map((p) => (/^\d+$/.test(p) ? Number(p) : p))];
            const el = $(`[data-path="${CSS.escape(JSON.stringify(path))}"]`) || $(`[data-section="${key}"]`);
            el.closest('details') && (el.closest('details').open = true);
            return [k, `[data-path="${CSS.escape(JSON.stringify(path))}"]`];
          }),
        );
        showFieldErrors(err, fields);
      }
      return;
    } finally {
      btn.disabled = false;
    }
    dirty.delete(key);
    redraw(key);
    toast(`تم حفظ "${definitions[key].label}"، وظهر في الموقع`);
  }

  /* ---------- photo ---------- */
  async function upload(file) {
    const form = new FormData();
    form.append('photo', await App.shrinkImage(file, 1200));
    try {
      photo = (await api.post('site-photo', form)).data.photo;
    } catch (err) {
      showFieldErrors(err);
      return;
    }
    $('#photoSection').outerHTML = photoCard();
    toast('تم رفع الصورة');
  }
  panels.addEventListener('change', (e) => {
    if (e.target.id === 'photoInput' && e.target.files[0]) upload(e.target.files[0]);
  });
  panels.addEventListener('click', async (e) => {
    if (e.target.closest('#photoRemove')) {
      try {
        await api.delete('site-photo');
      } catch {
        return;
      }
      photo = null;
      $('#photoSection').outerHTML = photoCard();
      toast('عادت صورة الموقع الأصلية');
    }
  });

  window.addEventListener('beforeunload', (e) => {
    if (dirty.size) e.preventDefault();
  });
});
