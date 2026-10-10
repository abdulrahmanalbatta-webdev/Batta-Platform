document.addEventListener('app:ready', async () => {
  const { $, esc, num, icon, toast, confirmDialog, api, showFieldErrors } = App;
  const canEdit = App.can('manage_content');
  let rows;
  try {
    rows = (await api.get('learning-paths')).data;
  } catch {
    return;
  }

  let status = 'all';
  let query = '';
  // the site draws its own icons; the dashboard shows the ones it also has, else a pin
  const pathIcon = (name) => (['code', 'globe', 'briefcase', 'monitor', 'clock', 'users', 'play', 'calendar', 'article', 'award', 'star', 'chat', 'mail', 'lock', 'check', 'link', 'pin', 'user', 'search'].includes(name) ? name : 'pin');

  function stats() {
    const sum = (key) => rows.reduce((a, p) => a + p[key], 0);
    $('#pathStats').innerHTML = [
      ['pin', 'c-blue', 'المسارات الظاهرة', `${rows.filter((p) => p.is_published).length} / ${rows.length}`],
      ['layers', 'c-violet', 'المراحل', num(sum('stages_count'))],
      ['link', 'c-green', 'المصادر المجانية', num(sum('resources_count'))],
      ['play', 'c-amber', 'دورات وورش ومقالات مرتبطة', num(sum('items_count'))],
    ]
      .map(([ic, tone, label, value]) => `<div class="card kpi"><div class="kpi-top"><span class="kpi-label">${label}</span><span class="kpi-ico ${tone}">${icon(ic)}</span></div><div class="kpi-value">${value}</div></div>`)
      .join('');
  }

  const SEGMENTS = [['all', 'الكل'], ['published', 'ظاهرة'], ['hidden', 'مخفية']];
  const matches = (p, s) => s === 'all' || (s === 'published') === p.is_published;
  function renderSeg() {
    $('#statusSeg').innerHTML = SEGMENTS.map(([key, label]) => `<button data-s="${key}" class="${status === key ? 'on' : ''}">${label}<span class="n">${rows.filter((p) => matches(p, key)).length}</span></button>`).join('');
  }

  function render() {
    const shown = rows.filter((p) => matches(p, status) && (!query || p.title.toLowerCase().includes(query)));
    // moving only makes sense on the full list
    const movable = canEdit && status === 'all' && !query;
    $('#list').innerHTML =
      shown
        .map((p, i) => `
        <article class="path-row ${p.is_published ? '' : 'is-hidden'}">
          <span class="kpi-ico c-blue">${icon(pathIcon(p.icon))}</span>
          <div class="path-main">
            <b>${esc(p.title)}</b>
            <p>${esc(p.summary || '')}</p>
            <div class="path-meta">
              <span>${icon('layers', 'sm')}${num(p.stages_count)} مراحل</span>
              <span>${icon('link', 'sm')}${num(p.resources_count)} مصدراً مجانياً</span>
              <span>${icon('play', 'sm')}${num(p.items_count)} محتوى مرتبط</span>
              ${p.duration ? `<span>${icon('clock', 'sm')}${esc(p.duration)}</span>` : ''}
            </div>
          </div>
          <div class="path-actions">
            ${canEdit ? `<label class="switch" title="ظاهر في الموقع"><input type="checkbox" data-toggle="${p.id}" ${p.is_published ? 'checked' : ''} aria-label="ظاهر في الموقع"><span class="track"></span></label>` : badge(p)}
            ${movable ? `<button class="btn-icon" data-move="up" data-id="${p.id}" ${i === 0 ? 'disabled' : ''} title="لأعلى" aria-label="لأعلى">${icon('arrow-up', 'sm')}</button>
            <button class="btn-icon" data-move="down" data-id="${p.id}" ${i === shown.length - 1 ? 'disabled' : ''} title="لأسفل" aria-label="لأسفل">${icon('arrow-down', 'sm')}</button>` : ''}
            ${p.is_published && App.siteUrl ? `<a class="btn-icon" href="${esc(`${App.siteUrl}/paths/${p.slug}`)}" target="_blank" rel="noopener" title="عرض في الموقع" aria-label="عرض في الموقع">${icon('external', 'sm')}</a>` : ''}
            ${canEdit ? `<a class="btn-icon" href="${App.url('path-edit', { id: p.id })}" title="تعديل" aria-label="تعديل">${icon('edit', 'sm')}</a>
            <button class="btn-icon danger" data-delete="${p.id}" title="حذف" aria-label="حذف">${icon('trash', 'sm')}</button>` : ''}
          </div>
        </article>`)
        .join('') ||
      `<div class="empty"><span class="e-ico">${icon('pin')}</span><b>${rows.length ? 'لا توجد مسارات مطابقة' : 'لا توجد مسارات بعد'}</b>${rows.length || !canEdit ? '' : `<a class="btn btn-primary btn-sm" style="margin-top:12px" href="${App.url('path-create')}">${icon('plus', 'sm')}أضف أول مسار</a>`}</div>`;
  }
  const badge = (p) => App.badge(p.is_published ? 'منشور' : 'مخفي');

  function refresh() {
    stats();
    renderSeg();
    render();
  }

  $('#statusSeg').addEventListener('click', (e) => {
    const b = e.target.closest('button');
    if (!b) return;
    status = b.dataset.s;
    renderSeg();
    render();
  });
  $('#q').addEventListener('input', App.debounce((e) => { query = e.target.value.trim().toLowerCase(); render(); }, 150));

  $('#list').addEventListener('change', async (e) => {
    const toggle = e.target.closest('[data-toggle]');
    if (!toggle) return;
    const p = rows.find((r) => r.id === Number(toggle.dataset.toggle));
    try {
      Object.assign(p, (await api.patch(`learning-paths/${p.id}`, { is_published: toggle.checked })).data);
    } catch (err) {
      toggle.checked = !toggle.checked;
      return showFieldErrors(err);
    }
    refresh();
    toast(p.is_published ? 'المسار ظاهر في الموقع الآن' : 'تم إخفاء المسار');
  });

  $('#list').addEventListener('click', async (e) => {
    const move = e.target.closest('[data-move]');
    if (move) {
      try {
        rows = (await api.post(`learning-paths/${move.dataset.id}/move`, { direction: move.dataset.move })).data;
      } catch (err) {
        return showFieldErrors(err);
      }
      return refresh();
    }
    const del = e.target.closest('[data-delete]');
    if (!del) return;
    const p = rows.find((r) => r.id === Number(del.dataset.delete));
    if (!(await confirmDialog({ title: 'حذف المسار؟', text: `سيُحذف "${p.title}" بمراحله ومصادره من الموقع. دوراتك وورشك ومقالاتك المرتبطة به تبقى كما هي.`, ok: 'حذف' }))) return;
    try {
      await api.delete(`learning-paths/${p.id}`);
    } catch (err) {
      return showFieldErrors(err);
    }
    rows = rows.filter((r) => r !== p);
    refresh();
    toast('تم حذف المسار');
  });

  refresh();
});
