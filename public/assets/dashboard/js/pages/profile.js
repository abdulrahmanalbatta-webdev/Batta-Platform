document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, money, toast, api, showFieldErrors } = App;

  // platform totals (cached with the dashboard numbers) and what I did lately
  api
    .get('dashboard')
    .then(({ data }) => {
      const t = data.totals;
      $('#pStats').innerHTML = [
        [num(t.students), 'طالب'],
        [num(t.published_courses), 'دورة منشورة'],
        [num(t.published_articles), 'مقالة منشورة'],
        [money(t.course_revenue), 'إيرادات المنصة'],
      ]
        .map(([v, l]) => `<div class="mini-stat"><b>${v}</b><small>${l}</small></div>`)
        .join('');
    })
    .catch(() => {});
  api
    .get('activity', { mine: 1, limit: 5 })
    .then(({ data }) => {
      $('#myActivity').innerHTML =
        data.map((a) => `<li><span class="t-dot" style="background:${App.activityTone(a.action)}"></span><b>${esc(a.description)}</b><time>${esc(App.ago(a.at))}</time></li>`).join('') ||
        '<li class="muted" style="list-style:none">لا يوجد نشاط بعد</li>';
    })
    .catch(() => {});

  /* ---------- the signed-in member (App.user comes from Laravel) ---------- */
  let me = App.user;
  const FIELDS = { name: '#fName', title: '#fTitle', email: '#fEmail', phone: '#fPhone', bio: '#fBio', github: '#fGithub', linkedin: '#fLinkedin' };
  const bio = $('#fBio');
  const count = () => ($('#bioCount').textContent = bio.value.length);

  function showMe() {
    $('#pName').textContent = me.name;
    $('#pRole').textContent = [me.role_label, me.title].filter(Boolean).join(' · ');
    $('#pInitial').textContent = me.initial;
    const img = $('#avatarImg');
    img.hidden = !me.avatar_url;
    if (me.avatar_url) img.src = me.avatar_url;
    // keep the sidebar and topbar in step
    $$('.sb-user .sb-text b, .tb-user .who b, .sb-brand .sb-text b').forEach((b) => (b.textContent = me.name));
  }
  function fillForm() {
    Object.entries(FIELDS).forEach(([key, sel]) => ($(sel).value = me[key] ?? ''));
    $$('#infoForm .invalid').forEach((i) => i.classList.remove('invalid'));
    count();
  }
  showMe();
  fillForm();

  // avatar upload
  $('#avatarInput').addEventListener('change', async (e) => {
    const f = e.target.files[0];
    e.target.value = '';
    if (!f) return;
    if (!/^image\/(jpeg|png|webp)$/.test(f.type)) return toast('اختر صورة بصيغة JPG أو PNG أو WebP', 'error');
    if (f.size > 3 * 1024 * 1024) return toast('الحد الأقصى 3MB', 'error');
    const body = new FormData();
    body.append('avatar', f);
    try {
      me = (await api.post('profile/avatar', body)).data;
    } catch (err) {
      return showFieldErrors(err);
    }
    showMe();
    $$('.tb-user .avatar, .sb-user .avatar').forEach((a) => {
      let img = $('img', a);
      if (!img) a.prepend((img = document.createElement('img')));
      img.src = me.avatar_url;
    });
    toast('تم تحديث الصورة');
  });
  $('#viewSite').addEventListener('click', (e) => {
    e.preventDefault();
    toast('ستفتح صفحتك في الموقع عند ربط الرابط', 'info');
  });

  bio.addEventListener('input', count);
  $('#infoForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  // "تراجع" brings back the saved values (the inputs have no HTML defaults)
  $('#infoForm').addEventListener('reset', (e) => {
    e.preventDefault();
    fillForm();
  });
  $('#infoForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const payload = Object.fromEntries(Object.entries(FIELDS).map(([key, sel]) => [key, $(sel).value.trim() || null]));
    try {
      me = (await api.put('profile', payload)).data;
    } catch (err) {
      return showFieldErrors(err, FIELDS);
    }
    showMe();
    fillForm();
    toast('تم حفظ معلوماتك');
  });

  // password strength (the server enforces the rules)
  const colors = ['#e02424', '#c27803', '#0066ff', '#0e9f6e'];
  const labels = ['ضعيفة', 'متوسطة', 'جيدة', 'قوية'];
  const hint = '8 أحرف على الأقل، مع رقم وحرف كبير وحرف صغير.';
  const score = (p) => [p.length >= 8, /[A-Z]/.test(p) && /[a-z]/.test(p), /\d/.test(p), /[^A-Za-z0-9]/.test(p) || p.length >= 12].filter(Boolean).length;
  const clearMeter = () => $$('#pwMeter i').forEach((b) => (b.style.background = ''));
  $('#pwNew').addEventListener('input', (e) => {
    const s = score(e.target.value);
    $$('#pwMeter i').forEach((b, i) => (b.style.background = e.target.value && i < s ? colors[s - 1] : ''));
    $('#pwHint').textContent = e.target.value ? `قوة كلمة المرور: ${labels[Math.max(0, s - 1)]}` : hint;
  });
  $('#pwForm').addEventListener('input', (e) => e.target.classList.remove('invalid'));
  $('#pwForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const [o, n, c] = ['#pwOld', '#pwNew', '#pwConfirm'].map((s) => $(s));
    $$('#pwForm .invalid').forEach((i) => i.classList.remove('invalid'));
    if (!o.value) return o.classList.add('invalid'), o.focus(), toast('أدخل كلمة المرور الحالية', 'error');
    if (n.value !== c.value) return c.classList.add('invalid'), c.focus(), toast('كلمتا المرور غير متطابقتين', 'error');
    try {
      await api.put('auth/user/password', { current_password: o.value, password: n.value, password_confirmation: c.value });
    } catch (err) {
      return showFieldErrors(err, { current_password: '#pwOld', password: '#pwNew' });
    }
    e.target.reset();
    clearMeter();
    $('#pwHint').textContent = hint;
    toast('تم تحديث كلمة المرور وتسجيل الخروج من أجهزتك الأخرى');
  });
});
