document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, icon, toast, confirmDialog, openModal, closeModal, openDrawer, closeDrawer } = App;
  const tools = DB.tools;
  const cats = DB.toolCategories;
  const COLORS = ['#0066ff', '#0b0d12', '#334155', '#0e9f6e', '#0891b2', '#7c3aed', '#c27803', '#e02424'];
  let filterCat = 'all';
  let editing = null;

  const catOf = (id) => cats.find((c) => c.id === id);
  const catName = (id) => catOf(id)?.name || 'بدون تصنيف';
  const countIn = (id) => tools.filter((t) => t.category === id).length;
  const nameTaken = (name, except) => cats.some((c) => c !== except && c.name.trim().toLowerCase() === name.trim().toLowerCase());
  const newCatId = () => `cat-${Date.now().toString(36)}${Math.random().toString(36).slice(2, 5)}`;
  const suggestShort = (name) => {
    const words = name.trim().split(/\s+/).filter(Boolean);
    if (!words.length) return '';
    return words.length > 1 ? (words[0][0] + words[1][0]).toUpperCase() : words[0].slice(0, 2).replace(/^./, (c) => c.toUpperCase());
  };

  function addCategory(name, color = COLORS[cats.length % COLORS.length]) {
    name = name.trim();
    if (!name) return toast('اكتب اسم التصنيف', 'error'), null;
    if (nameTaken(name)) return toast('هذا التصنيف موجود مسبقاً', 'error'), null;
    const c = { id: newCatId(), name, color };
    cats.push(c);
    toast(`تمت إضافة تصنيف "${name}"`);
    return c;
  }

  /* ---------------- page ---------------- */
  function stats() {
    $('#toolStats').innerHTML = [
      ['code', 'c-blue', 'كل الأدوات', tools.length],
      ['eye', 'c-green', 'منشورة في الموقع', tools.filter((t) => t.status === 'منشور').length],
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
      cats.map((c) => `<button data-c="${c.id}" class="${filterCat === c.id ? 'on' : ''}"><i class="cat-dot" style="background:${c.color}"></i>${esc(c.name)}<span class="n">${countIn(c.id)}</span></button>`).join('');
  }

  function render() {
    const q = $('#q').value.trim().toLowerCase();
    const st = $('#statusFilter').value;
    const list = tools.filter((t) => (filterCat === 'all' || t.category === filterCat) && (!st || t.status === st) && (!q || `${t.name} ${t.why} ${catName(t.category)}`.toLowerCase().includes(q)));
    $('#grid').innerHTML =
      list
        .map((t) => {
          const i = tools.indexOf(t);
          const c = catOf(t.category);
          return `
        <article class="tool-card ${t.status !== 'منشور' ? 'is-draft' : ''}">
          <div class="tool-top">
            <span class="tool-logo" style="background:${t.color}">${esc(t.short)}</span>
            <div class="tool-title">
              <b>${esc(t.name)}</b>
              <small><i class="cat-dot" style="background:${c?.color || 'var(--line-2)'}"></i>${esc(catName(t.category))} · منذ ${t.since}</small>
            </div>
            <div class="dropdown">
              <button class="btn-icon" data-dropdown aria-label="خيارات ${esc(t.name)}">${icon('more', 'sm')}</button>
              <div class="menu">
                <button data-edit="${t.id}">${icon('edit', 'sm')}تعديل</button>
                ${t.url ? `<a href="${esc(t.url)}" target="_blank" rel="noopener">${icon('external', 'sm')}فتح موقع الأداة</a>` : ''}
                <button data-move="-1" data-id="${t.id}" ${i === 0 ? 'disabled' : ''}>${icon('arrow-up', 'sm')}تقديم في الترتيب</button>
                <button data-move="1" data-id="${t.id}" ${i === tools.length - 1 ? 'disabled' : ''}>${icon('arrow-down', 'sm')}تأخير في الترتيب</button>
                <hr>
                <button class="danger" data-del="${t.id}">${icon('trash', 'sm')}حذف</button>
              </div>
            </div>
          </div>
          <p>${esc(t.why)}</p>
          <div class="tool-foot">
            <label class="switch" title="إظهار في الموقع"><input type="checkbox" data-toggle="${t.id}" ${t.status === 'منشور' ? 'checked' : ''}><span class="track"></span><span>${t.status}</span></label>
            <span class="grow"></span>
            ${t.affiliate ? '<span class="badge warning">رابط شراكة</span>' : ''}
            <span class="muted nowrap" style="font-size:12.5px">${icon('trend', 'sm')} ${num(t.clicks)}</span>
          </div>
        </article>`;
        })
        .join('') ||
      `<div class="empty" style="grid-column:1/-1"><div class="e-ico">${icon('code')}</div><b>لا توجد أدوات</b>جرّب بحثاً آخر أو أضف أداة جديدة.</div>`;
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

  function preview() {
    const name = $('#tName').value.trim() || 'اسم الأداة';
    const short = $('#tShort').value.trim() || suggestShort($('#tName').value) || '?';
    const c = catOf($('#tCategory').value);
    $('#preview').innerHTML = `
      <span class="muted" style="font-size:12px">معاينة كما ستظهر في الموقع</span>
      <div class="tool-top">
        <span class="tool-logo" style="background:${color}">${esc(short)}</span>
        <div class="tool-title"><b>${esc(name)}</b><small>${c ? `<i class="cat-dot" style="background:${c.color}"></i>${esc(c.name)}` : 'التصنيف'} · منذ ${esc($('#tSince').value || '—')}</small></div>
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
    fillCatSelect(t?.category || (filterCat !== 'all' ? filterCat : ''));
    $('#tSince').value = t?.since || new Date().getFullYear();
    $('#tUrl').value = t?.url || '';
    $('#tWhy').value = t?.why || '';
    $('#tStatus').value = t?.status || 'منشور';
    $('#tAffiliate').checked = !!t?.affiliate;
    color = t?.color || COLORS[0];
    shortTouched = !!t;
    preview();
    openModal('toolModal');
    setTimeout(() => $('#tName').focus(), 50);
  }

  // "+ new category" straight from the tool form
  $('#tCategory').addEventListener('change', (e) => {
    e.target.classList.remove('invalid');
    if (e.target.value !== '__new') return (lastCat = e.target.value);
    e.target.hidden = true;
    $('#newCatRow').hidden = false;
    $('#newCatName').value = '';
    $('#newCatName').focus();
  });
  function saveInlineCat() {
    const c = addCategory($('#newCatName').value);
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

  $('#toolForm').addEventListener('submit', (e) => {
    e.preventDefault();
    if (!$('#newCatRow').hidden) return saveInlineCat();
    const name = $('#tName').value.trim();
    const category = $('#tCategory').value;
    const why = $('#tWhy').value.trim();
    const url = $('#tUrl').value.trim();
    const bad = [];
    if (!name) bad.push('#tName');
    if (!catOf(category)) bad.push('#tCategory');
    if (!why) bad.push('#tWhy');
    if (url && !/^https?:\/\/[^\s.]+\.[^\s]+$/.test(url)) bad.push('#tUrl');
    const duplicate = tools.some((t) => t.name.toLowerCase() === name.toLowerCase() && t !== editing);
    if (duplicate) bad.push('#tName');
    if (bad.length) {
      bad.forEach((s) => $(s).classList.add('invalid'));
      $(bad[0]).focus();
      return toast(duplicate ? 'توجد أداة بنفس الاسم' : 'أكمل الحقول المطلوبة بشكل صحيح', 'error');
    }
    const data = {
      name,
      short: $('#tShort').value.trim() || suggestShort(name),
      color,
      category,
      why,
      since: Number($('#tSince').value) || new Date().getFullYear(),
      url,
      affiliate: $('#tAffiliate').checked,
      status: $('#tStatus').value,
    };
    if (editing) {
      Object.assign(editing, data);
      toast(`تم حفظ ${name}`);
    } else {
      const next = Math.max(0, ...tools.map((t) => Number(t.id.slice(2)) || 0)) + 1;
      tools.unshift({ id: `T-${next}`, clicks: 0, ...data });
      toast(`تمت إضافة ${name}${data.status === 'منشور' ? ' وستظهر في الموقع' : ' كمسودة'}`);
    }
    closeModal('toolModal');
    refresh();
  });

  /* ---------------- categories manager (drawer) ---------------- */
  let deleting = null; // category id whose "move tools to…" row is open
  let catDrawer = null; // the open manager (an old drawer can linger while it animates out)

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

    const nextColor = (c) => COLORS[(COLORS.indexOf(c) + 1) % COLORS.length];
    $('#catAddColor', dr).addEventListener('click', (e) => {
      const c = nextColor(e.currentTarget.dataset.color);
      e.currentTarget.dataset.color = c;
      e.currentTarget.style.background = c;
    });
    $('#catAddForm', dr).addEventListener('submit', (e) => {
      e.preventDefault();
      if (!addCategory($('#catAddName', dr).value, $('#catAddColor', dr).dataset.color)) return $('#catAddName', dr).focus();
      $('#catAddName', dr).value = '';
      const c = COLORS[cats.length % COLORS.length];
      $('#catAddColor', dr).dataset.color = c;
      $('#catAddColor', dr).style.background = c;
      refresh();
    });

    const list = $('#catList', dr);
    list.addEventListener('click', async (e) => {
      const b = e.target.closest('[data-cact]');
      if (!b) return;
      const c = catOf(b.dataset.id);
      const i = cats.indexOf(c);
      switch (b.dataset.cact) {
        case 'color':
          c.color = nextColor(c.color);
          return refresh();
        case 'up':
        case 'down': {
          const j = i + (b.dataset.cact === 'up' ? -1 : 1);
          [cats[i], cats[j]] = [cats[j], cats[i]];
          return refresh();
        }
        case 'filter':
          filterCat = c.id;
          closeDrawer();
          return refresh();
        case 'delete':
          if (!countIn(c.id)) {
            if (await confirmDialog({ title: `حذف تصنيف "${c.name}"؟`, text: 'لا توجد أدوات في هذا التصنيف.', ok: 'حذف' })) {
              cats.splice(i, 1);
              refresh();
              toast('تم حذف التصنيف');
            }
            return;
          }
          deleting = deleting === c.id ? null : c.id;
          return renderCatList();
        case 'confirm-move': {
          const target = $(`#moveTo-${c.id}`, list).value;
          const n = countIn(c.id);
          tools.forEach((t) => t.category === c.id && (t.category = target));
          cats.splice(i, 1);
          deleting = null;
          refresh();
          return toast(`تم حذف "${c.name}" ونقل ${n} ${n === 1 ? 'أداة' : 'أدوات'} إلى "${catName(target)}"`);
        }
        case 'cancel-move':
          deleting = null;
          return renderCatList();
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
        e.target.value = catOf(e.target.dataset.rename).name;
        e.target.blur();
      }
    });
    list.addEventListener('focusout', (e) => {
      if (!e.target.matches('[data-rename]')) return;
      const c = catOf(e.target.dataset.rename);
      const name = e.target.value.trim();
      if (!c || name === c.name) return;
      if (!name || nameTaken(name, c)) {
        toast(!name ? 'اسم التصنيف لا يمكن أن يكون فارغاً' : 'هذا الاسم مستخدم لتصنيف آخر', 'error');
        e.target.value = c.name;
        return;
      }
      const old = c.name;
      c.name = name;
      refresh();
      toast(`تمت إعادة تسمية "${old}" إلى "${name}"`);
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
            <button type="button" class="cat-color" data-cact="color" data-id="${c.id}" style="background:${c.color}" aria-label="تغيير لون ${esc(c.name)}" title="تغيير اللون"></button>
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
    if (ed) return openForm(tools.find((t) => t.id === ed.dataset.edit));
    const mv = e.target.closest('[data-move]');
    if (mv && !mv.disabled) {
      const i = tools.findIndex((t) => t.id === mv.dataset.id);
      const j = i + Number(mv.dataset.move);
      [tools[i], tools[j]] = [tools[j], tools[i]];
      return render();
    }
    const del = e.target.closest('[data-del]');
    if (del) {
      const t = tools.find((x) => x.id === del.dataset.del);
      if (await confirmDialog({ title: `حذف ${t.name}؟`, text: 'ستختفي الأداة من صفحة "أدواتي" في الموقع.', ok: 'حذف' })) {
        tools.splice(tools.indexOf(t), 1);
        refresh();
        toast('تم حذف الأداة');
      }
    }
  });
  $('#grid').addEventListener('change', (e) => {
    const t = tools.find((x) => x.id === e.target.dataset.toggle);
    if (!t) return;
    t.status = e.target.checked ? 'منشور' : 'مسودة';
    refresh();
    toast(e.target.checked ? `${t.name} ظاهرة الآن في الموقع` : `تم إخفاء ${t.name} من الموقع`);
  });

  $('#catSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    filterCat = b.dataset.c;
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
      tools.map((t) => ({ ...t, categoryName: catName(t.category) })),
    ),
  );

  refresh();
  if (location.hash === '#new') openForm();
  if (location.hash === '#categories') openCatManager();
});
