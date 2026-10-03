document.addEventListener('app:ready', async () => {
  const { $, $$, esc, num, icon, toast, confirmDialog, openModal, closeModal, openDrawer, closeDrawer, api, showFieldErrors } = App;
  const canEdit = App.can('manage_content');
  const COLORS = ['#0066ff', '#0b0d12', '#334155', '#0e9f6e', '#0891b2', '#7c3aed', '#c27803', '#e02424'];
  let tools = [];
  let cats = [];
  let filterCat = 'all';
  let editing = null;

  const catOf = (id) => cats.find((c) => c.id === id);
  const catName = (id) => catOf(id)?.name || 'بدون تصنيف';
  const countIn = (id) => tools.filter((t) => t.category_id === id).length;
  const statusLabel = (t) => (t.is_published ? 'منشور' : 'مسودة');
  const suggestShort = (name) => {
    const words = name.trim().split(/\s+/).filter(Boolean);
    if (!words.length) return '';
    return words.length > 1 ? (words[0][0] + words[1][0]).toUpperCase() : words[0].slice(0, 2).replace(/^./, (c) => c.toUpperCase());
  };

  async function loadCats() {
    cats = (await api.get('tool-categories')).data;
  }
  async function addCategory(name, color = COLORS[cats.length % COLORS.length]) {
    name = name.trim();
    if (!name) return toast('اكتب اسم التصنيف', 'error'), null;
    try {
      const c = (await api.post('tool-categories', { name, color })).data;
      cats.push(c);
      toast(`تمت إضافة تصنيف "${c.name}"`);
      return c;
    } catch (err) {
      showFieldErrors(err);
      return null;
    }
  }

  /* ---------------- page ---------------- */
  function stats() {
    $('#toolStats').innerHTML = [
      ['code', 'c-blue', 'كل الأدوات', tools.length],
      ['eye', 'c-green', 'منشورة في الموقع', tools.filter((t) => t.is_published).length],
      ['layers', 'c-amber', 'التصنيفات', cats.length],
      ['trend', 'c-violet', 'نقرات هذا الشهر', num(tools.reduce((s, t) => s + t.clicks, 0))],
    ]
      .map(([ic, tone, l, v]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${l}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${v}</div></div>`)
      .join('');
  }

  function renderSeg() {
    if (filterCat !== 'all' && !catOf(filterCat)) filterCat = 'all';
    $('#catSeg').innerHTML =
      `<button data-c="all" class="${filterCat === 'all' ? 'on' : ''}">الكل<span class="n">${tools.length}</span></button>` +
      cats.map((c) => `<button data-c="${c.id}" class="${filterCat === c.id ? 'on' : ''}"><i class="cat-dot" style="background:${esc(c.color)}"></i>${esc(c.name)}<span class="n">${countIn(c.id)}</span></button>`).join('');
  }

  function render() {
    const q = $('#q').value.trim().toLowerCase();
    const st = $('#statusFilter').value;
    const list = tools.filter((t) => (filterCat === 'all' || t.category_id === filterCat) && (!st || statusLabel(t) === st) && (!q || `${t.name} ${t.why} ${catName(t.category_id)}`.toLowerCase().includes(q)));
    $('#grid').innerHTML =
      list
        .map((t) => {
          const i = tools.indexOf(t);
          const c = catOf(t.category_id);
          const menu = canEdit
            ? `<button data-edit="${t.id}">${icon('edit', 'sm')}تعديل</button>
                ${t.url ? `<a href="${esc(t.url)}" target="_blank" rel="noopener">${icon('external', 'sm')}فتح موقع الأداة</a>` : ''}
                <button data-move="up" data-id="${t.id}" ${i === 0 ? 'disabled' : ''}>${icon('arrow-up', 'sm')}تقديم في الترتيب</button>
                <button data-move="down" data-id="${t.id}" ${i === tools.length - 1 ? 'disabled' : ''}>${icon('arrow-down', 'sm')}تأخير في الترتيب</button>
                <hr>
                <button class="danger" data-del="${t.id}">${icon('trash', 'sm')}حذف</button>`
            : t.url
              ? `<a href="${esc(t.url)}" target="_blank" rel="noopener">${icon('external', 'sm')}فتح موقع الأداة</a>`
              : '';
          return `
        <article class="tool-card ${t.is_published ? '' : 'is-draft'}">
          <div class="tool-top">
            <span class="tool-logo" style="background:${esc(t.color)}">${esc(t.short)}</span>
            <div class="tool-title">
              <b>${esc(t.name)}</b>
              <small><i class="cat-dot" style="background:${esc(c?.color || 'var(--line-2)')}"></i>${esc(catName(t.category_id))} · منذ ${esc(t.since)}</small>
            </div>
            ${menu ? `<div class="dropdown">
              <button class="btn-icon" data-dropdown aria-label="خيارات ${esc(t.name)}">${icon('more', 'sm')}</button>
              <div class="menu">${menu}</div>
            </div>` : ''}
          </div>
          <p>${esc(t.why)}</p>
          <div class="tool-foot">
            <label class="switch" title="إظهار في الموقع"><input type="checkbox" data-toggle="${t.id}" ${t.is_published ? 'checked' : ''} ${canEdit ? '' : 'disabled'}><span class="track"></span><span>${statusLabel(t)}</span></label>
            <span class="grow"></span>
            ${t.is_affiliate ? '<span class="badge warning">رابط شراكة</span>' : ''}
            <span class="muted nowrap" style="font-size:12.5px">${icon('trend', 'sm')} ${num(t.clicks)}</span>
          </div>
        </article>`;
        })
        .join('') ||
      `<div class="empty" style="grid-column:1/-1"><div class="e-ico">${icon('code')}</div><b>لا توجد أدوات</b>${canEdit ? 'جرّب بحثاً آخر أو أضف أداة جديدة.' : 'جرّب بحثاً آخر.'}</div>`;
  }

  function refresh() {
    stats();
    renderSeg();
    render();
    if (catDrawer?.isConnected) renderCatList();
  }

  /* ---------------- tool form ---------------- */
  $('#swatches').innerHTML = COLORS.map((c) => `<button type="button" class="swatch" data-color="${c}" style="background:${c}" aria-label="لون ${c}"></button>`).join('');
  let color = COLORS[0];
  let shortTouched = false;
  let lastCat = '';

  function fillCatSelect(selected) {
    $('#tCategory').innerHTML =
      `<option value="" disabled ${selected ? '' : 'selected'}>اختر تصنيفاً</option>` +
      cats.map((c) => `<option value="${c.id}" ${c.id === selected ? 'selected' : ''}>${esc(c.name)}</option>`).join('') +
      '<option value="__new">＋ تصنيف جديد…</option>';
    lastCat = selected || '';
  }
  const hideNewCat = () => {
    $('#newCatRow').hidden = true;
    $('#tCategory').hidden = false;
  };
  const selectedCat = () => Number($('#tCategory').value) || null;

  function preview() {
    const name = $('#tName').value.trim() || 'اسم الأداة';
    const short = $('#tShort').value.trim() || suggestShort($('#tName').value) || '?';
    const c = catOf(selectedCat());
    $('#preview').innerHTML = `
      <span class="muted" style="font-size:12px">معاينة كما ستظهر في الموقع</span>
      <div class="tool-top">
        <span class="tool-logo" style="background:${color}">${esc(short)}</span>
        <div class="tool-title"><b>${esc(name)}</b><small>${c ? `<i class="cat-dot" style="background:${esc(c.color)}"></i>${esc(c.name)}` : 'التصنيف'} · منذ ${esc($('#tSince').value || '—')}</small></div>
        ${$('#tAffiliate').checked ? '<span class="badge warning">رابط شراكة</span>' : ''}
      </div>
      <p>${esc($('#tWhy').value || 'لماذا تستخدم هذه الأداة؟')}</p>`;
    $$('.swatch').forEach((s) => s.classList.toggle('on', s.dataset.color === color));
    $('#whyCount').textContent = $('#tWhy').value.length;
  }

  function openForm(t = null) {
    editing = t;
    const f = $('#toolForm');
    f.reset();
    hideNewCat();
    $$('.invalid', f).forEach((i) => i.classList.remove('invalid'));
    $('#toolModalTitle').textContent = t ? `تعديل ${t.name}` : 'أداة جديدة';
    $('#toolSubmit').textContent = t ? 'حفظ التعديلات' : 'إضافة الأداة';
    $('#tName').value = t?.name || '';
    $('#tShort').value = t?.short || '';
    fillCatSelect(t?.category_id || (filterCat !== 'all' ? filterCat : ''));
    $('#tSince').value = t?.since || new Date().getFullYear();
    $('#tUrl').value = t?.url || '';
    $('#tWhy').value = t?.why || '';
    $('#tStatus').value = t ? statusLabel(t) : 'منشور';
    $('#tAffiliate').checked = !!t?.is_affiliate;
    color = t?.color || COLORS[0];
    shortTouched = !!t;
    preview();
    openModal('toolModal');
    setTimeout(() => $('#tName').focus(), 50);
  }

  // "+ new category" straight from the tool form
  $('#tCategory').addEventListener('change', (e) => {
    e.target.classList.remove('invalid');
    if (e.target.value !== '__new') return (lastCat = selectedCat());
    e.target.hidden = true;
    $('#newCatRow').hidden = false;
    $('#newCatName').value = '';
    $('#newCatName').focus();
  });
  async function saveInlineCat() {
    const c = await addCategory($('#newCatName').value);
    if (!c) return $('#newCatName').focus();
    fillCatSelect(c.id);
    hideNewCat();
    renderSeg();
    stats();
    preview();
  }
  $('#newCatSave').addEventListener('click', saveInlineCat);
  $('#newCatName').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      saveInlineCat();
    }
    if (e.key === 'Escape') {
      e.stopPropagation();
      $('#newCatCancel').click();
    }
  });
  $('#newCatCancel').addEventListener('click', () => {
    fillCatSelect(lastCat);
    hideNewCat();
    preview();
  });

  $('#toolForm').addEventListener('input', (e) => {
    if (e.target.id === 'newCatName') return;
    if (e.target.id === 'tShort') shortTouched = !!e.target.value;
    if (e.target.id === 'tName' && !shortTouched) $('#tShort').value = suggestShort(e.target.value);
    e.target.classList.remove('invalid');
    preview();
  });
  $('#toolForm').addEventListener('change', preview);
  $('#swatches').addEventListener('click', (e) => {
    const s = e.target.closest('.swatch');
    if (!s) return;
    color = s.dataset.color;
    preview();
  });

  $('#toolForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!$('#newCatRow').hidden) return saveInlineCat();
    const name = $('#tName').value.trim();
    const data = {
      name,
      short: $('#tShort').value.trim() || suggestShort(name),
      color,
      tool_category_id: selectedCat(),
      why: $('#tWhy').value.trim(),
      since: Number($('#tSince').value) || new Date().getFullYear(),
      url: $('#tUrl').value.trim() || null,
      is_affiliate: $('#tAffiliate').checked,
      is_published: $('#tStatus').value === 'منشور',
    };
    const fields = { name: '#tName', short: '#tShort', tool_category_id: '#tCategory', why: '#tWhy', since: '#tSince', url: '#tUrl' };
    let saved;
    try {
      saved = editing ? (await api.patch(`tools/${editing.id}`, data)).data : (await api.post('tools', data)).data;
    } catch (err) {
      return showFieldErrors(err, fields);
    }
    if (editing) {
      Object.assign(editing, saved);
      toast(`تم حفظ ${saved.name}`);
    } else {
      tools.push(saved);
      toast(`تمت إضافة ${saved.name}${saved.is_published ? ' وستظهر في الموقع' : ' كمسودة'}`);
    }
    closeModal('toolModal');
    await loadCats();
    refresh();
  });

  /* ---------------- categories manager (drawer) ---------------- */
  let deleting = null; // category id whose "move tools to…" row is open
  let catDrawer = null; // the open manager (an old drawer can linger while it animates out)
  const nextColor = (c) => COLORS[(COLORS.indexOf(c) + 1) % COLORS.length];

  function openCatManager() {
    deleting = null;
    const dr = (catDrawer = openDrawer(`
      <div class="drawer-head"><div><h3 style="font-size:17px">تصنيفات الأدوات</h3><small class="muted">أضف، أعد التسمية، رتّب، أو احذف</small></div><button class="btn-icon" data-close-drawer aria-label="إغلاق"><i data-icon="close"></i></button></div>
      <div class="drawer-body" id="catManager">
        <form class="cat-add" id="catAddForm" novalidate>
          <div class="cat-add-row">
            <button type="button" class="cat-color" id="catAddColor" style="background:${COLORS[cats.length % COLORS.length]}" data-color="${COLORS[cats.length % COLORS.length]}" aria-label="تغيير اللون" title="اضغط لتغيير اللون"></button>
            <input class="input" id="catAddName" placeholder="اسم تصنيف جديد، مثلاً: الذكاء الاصطناعي" maxlength="30" aria-label="اسم التصنيف">
            <button class="btn btn-primary" type="submit">${icon('plus', 'sm')}إضافة</button>
          </div>
        </form>
        <div class="cat-list" id="catList"></div>
        <p class="muted" style="font-size:12.5px">اضغط على النقطة الملونة لتغيير لون التصنيف، وعلى الاسم لإعادة تسميته. الترتيب هنا هو ترتيب الفلاتر في الموقع.</p>
      </div>`));
    renderCatList();

    $('#catAddColor', dr).addEventListener('click', (e) => {
      const c = nextColor(e.currentTarget.dataset.color);
      e.currentTarget.dataset.color = c;
      e.currentTarget.style.background = c;
    });
    $('#catAddForm', dr).addEventListener('submit', async (e) => {
      e.preventDefault();
      if (!(await addCategory($('#catAddName', dr).value, $('#catAddColor', dr).dataset.color))) return $('#catAddName', dr).focus();
      $('#catAddName', dr).value = '';
      const c = COLORS[cats.length % COLORS.length];
      $('#catAddColor', dr).dataset.color = c;
      $('#catAddColor', dr).style.background = c;
      refresh();
    });

    const list = $('#catList', dr);
    list.addEventListener('click', async (e) => {
      const b = e.target.closest('[data-cact]');
      if (!b || b.disabled) return;
      const c = catOf(Number(b.dataset.id));
      try {
        switch (b.dataset.cact) {
          case 'color':
            Object.assign(c, (await api.patch(`tool-categories/${c.id}`, { color: nextColor(c.color) })).data);
            return refresh();
          case 'up':
          case 'down':
            cats = (await api.post(`tool-categories/${c.id}/move`, { direction: b.dataset.cact })).data;
            return refresh();
          case 'filter':
            filterCat = c.id;
            closeDrawer();
            return refresh();
          case 'delete':
            if (!countIn(c.id)) {
              if (await confirmDialog({ title: `حذف تصنيف "${c.name}"؟`, text: 'لا توجد أدوات في هذا التصنيف.', ok: 'حذف' })) {
                await api.delete(`tool-categories/${c.id}`);
                cats = cats.filter((x) => x !== c);
                refresh();
                toast('تم حذف التصنيف');
              }
              return;
            }
            deleting = deleting === c.id ? null : c.id;
            return renderCatList();
          case 'confirm-move': {
            const target = Number($(`#moveTo-${c.id}`, list).value);
            const n = countIn(c.id);
            await api.delete(`tool-categories/${c.id}?move_to=${target}`);
            tools.forEach((t) => t.category_id === c.id && (t.category_id = target));
            deleting = null;
            await loadCats();
            refresh();
            return toast(`تم حذف "${c.name}" ونقل ${n} ${n === 1 ? 'أداة' : 'أدوات'} إلى "${catName(target)}"`);
          }
          case 'cancel-move':
            deleting = null;
            return renderCatList();
        }
      } catch (err) {
        showFieldErrors(err);
      }
    });
    // rename inline: Enter or leaving the field saves, Escape reverts
    list.addEventListener('keydown', (e) => {
      if (!e.target.matches('[data-rename]')) return;
      if (e.key === 'Enter') {
        e.preventDefault();
        e.target.blur();
      }
      if (e.key === 'Escape') {
        e.stopPropagation();
        e.target.value = catOf(Number(e.target.dataset.rename)).name;
        e.target.blur();
      }
    });
    list.addEventListener('focusout', async (e) => {
      if (!e.target.matches('[data-rename]')) return;
      const c = catOf(Number(e.target.dataset.rename));
      const name = e.target.value.trim();
      if (!c || name === c.name) return;
      if (!name) {
        toast('اسم التصنيف لا يمكن أن يكون فارغاً', 'error');
        e.target.value = c.name;
        return;
      }
      const old = c.name;
      try {
        Object.assign(c, (await api.patch(`tool-categories/${c.id}`, { name })).data);
      } catch (err) {
        showFieldErrors(err);
        e.target.value = c.name;
        return;
      }
      refresh();
      toast(`تمت إعادة تسمية "${old}" إلى "${c.name}"`);
    });
  }

  function renderCatList() {
    const list = catDrawer?.isConnected && $('#catList', catDrawer);
    if (!list) return;
    list.innerHTML =
      cats
        .map((c, i) => {
          const n = countIn(c.id);
          const others = cats.filter((x) => x !== c);
          return `
        <div class="cat-row ${deleting === c.id ? 'is-deleting' : ''}">
          <div class="cat-main">
            <button type="button" class="cat-color" data-cact="color" data-id="${c.id}" style="background:${esc(c.color)}" aria-label="تغيير لون ${esc(c.name)}" title="تغيير اللون"></button>
            <input class="cat-name" data-rename="${c.id}" value="${esc(c.name)}" maxlength="30" aria-label="اسم التصنيف">
            <button type="button" class="cat-count" data-cact="filter" data-id="${c.id}" title="عرض أدوات هذا التصنيف">${n} ${n === 1 ? 'أداة' : 'أدوات'}</button>
            <div class="cat-actions">
              <button type="button" class="btn-icon" data-cact="up" data-id="${c.id}" ${i === 0 ? 'disabled' : ''} aria-label="تقديم">${icon('arrow-up', 'sm')}</button>
              <button type="button" class="btn-icon" data-cact="down" data-id="${c.id}" ${i === cats.length - 1 ? 'disabled' : ''} aria-label="تأخير">${icon('arrow-down', 'sm')}</button>
              <button type="button" class="btn-icon danger" data-cact="delete" data-id="${c.id}" aria-label="حذف ${esc(c.name)}">${icon('trash', 'sm')}</button>
            </div>
          </div>
          ${
            deleting === c.id
              ? others.length
                ? `<div class="cat-move">
                    <span>في هذا التصنيف ${n} ${n === 1 ? 'أداة' : 'أدوات'}. انقلها إلى:</span>
                    <select class="select" id="moveTo-${c.id}" aria-label="التصنيف البديل">${others.map((o) => `<option value="${o.id}">${esc(o.name)}</option>`).join('')}</select>
                    <div style="display:flex;gap:8px;justify-content:flex-end">
                      <button type="button" class="btn btn-sm btn-ghost" data-cact="cancel-move" data-id="${c.id}">إلغاء</button>
                      <button type="button" class="btn btn-sm btn-danger" data-cact="confirm-move" data-id="${c.id}">حذف ونقل الأدوات</button>
                    </div>
                  </div>`
                : `<div class="cat-move"><span>هذا آخر تصنيف وفيه أدوات. أضف تصنيفاً آخر أولاً لتنقل أدواته إليه.</span><button type="button" class="btn btn-sm btn-ghost" data-cact="cancel-move" data-id="${c.id}">حسناً</button></div>`
              : ''
          }
        </div>`;
        })
        .join('') || `<div class="empty"><div class="e-ico">${icon('layers')}</div><b>لا توجد تصنيفات</b>أضف أول تصنيف من الأعلى.</div>`;
  }

  /* ---------------- card actions & filters ---------------- */
  $('#grid').addEventListener('click', async (e) => {
    const ed = e.target.closest('[data-edit]');
    if (ed) return openForm(tools.find((t) => t.id === Number(ed.dataset.edit)));
    const mv = e.target.closest('[data-move]');
    if (mv && !mv.disabled) {
      try {
        tools = (await api.post(`tools/${mv.dataset.id}/move`, { direction: mv.dataset.move })).data;
      } catch (err) {
        return showFieldErrors(err);
      }
      return render();
    }
    const del = e.target.closest('[data-del]');
    if (del) {
      const t = tools.find((x) => x.id === Number(del.dataset.del));
      if (!(await confirmDialog({ title: `حذف ${t.name}؟`, text: 'ستختفي الأداة من صفحة "أدواتي" في الموقع.', ok: 'حذف' }))) return;
      try {
        await api.delete(`tools/${t.id}`);
      } catch {
        return;
      }
      tools = tools.filter((x) => x !== t);
      await loadCats();
      refresh();
      toast('تم حذف الأداة');
    }
  });
  $('#grid').addEventListener('change', async (e) => {
    const t = tools.find((x) => x.id === Number(e.target.dataset.toggle));
    if (!t) return;
    try {
      Object.assign(t, (await api.patch(`tools/${t.id}`, { is_published: e.target.checked })).data);
    } catch {
      e.target.checked = !e.target.checked;
      return;
    }
    refresh();
    toast(t.is_published ? `${t.name} ظاهرة الآن في الموقع` : `تم إخفاء ${t.name} من الموقع`);
  });

  $('#catSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    filterCat = b.dataset.c === 'all' ? 'all' : Number(b.dataset.c);
    renderSeg();
    render();
  });
  $('#q').addEventListener('input', App.debounce(render, 120));
  $('#statusFilter').addEventListener('change', render);
  $('#addTool').addEventListener('click', () => openForm());
  $('#manageCats').addEventListener('click', openCatManager);

  $('#export').addEventListener('click', () =>
    App.downloadCSV(
      'tools.csv',
      [
        { key: 'name', label: 'الأداة' }, { key: 'categoryName', label: 'التصنيف' }, { key: 'why', label: 'لماذا' }, { key: 'since', label: 'منذ' },
        { key: 'url', label: 'الرابط' }, { key: 'affiliate', label: 'شراكة' }, { key: 'status', label: 'الحالة' }, { key: 'clicks', label: 'النقرات' },
      ],
      tools.map((t) => ({ ...t, categoryName: catName(t.category_id), affiliate: t.is_affiliate ? 'نعم' : 'لا', status: statusLabel(t) })),
    ),
  );

  try {
    [tools] = await Promise.all([api.get('tools').then((r) => r.data), loadCats()]);
  } catch {
    return;
  }
  refresh();
  if (canEdit && location.hash === '#new') openForm();
  if (canEdit && location.hash === '#categories') openCatManager();
});
