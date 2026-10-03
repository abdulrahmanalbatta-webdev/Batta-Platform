document.addEventListener('app:ready', () => {
  const { $, $$, esc, num, money, icon, toast } = App;

  const revenue = DB.courses.reduce((s, c) => s + c.revenue, 0);
  $('#pStats').innerHTML = [
    [num(DB.students.length), 'طالب'],
    [DB.courses.filter((c) => c.status === 'منشورة').length, 'دورة منشورة'],
    [DB.articles.filter((a) => a.status === 'منشور').length, 'مقالة'],
    [money(revenue), 'إيرادات الدورات'],
  ]
    .map(([v, l]) => `<div class="mini-stat"><b>${v}</b><small>${l}</small></div>`)
    .join('');

  const mine = [
    { tone: '#0066ff', title: 'نشرت مقالة جديدة', text: 'بناء نظام مصادقة كامل في Next.js', time: 'قبل يومين' },
    { tone: '#0e9f6e', title: 'رددت على تقييم', text: 'محمد الخطيب · Next.js من الصفر', time: 'قبل 3 أيام' },
    { tone: '#7c3aed', title: 'أنشأت كوبون LAUNCH30', text: 'خصم 30% على كل الدورات', time: 'قبل أسبوع' },
    { tone: '#c27803', title: 'حدّثت منهج دورة', text: 'أضفت 4 دروس إلى APIs باستخدام Node', time: 'قبل أسبوع' },
    { tone: '#0b0d12', title: 'تسجيل دخول من جهاز جديد', text: 'iPhone · Safari — غزة', time: 'قبل أسبوعين' },
  ];
  $('#myActivity').innerHTML = mine.map((a) => `<li><span class="t-dot" style="background:${a.tone}"></span><b>${esc(a.title)}</b><p>${esc(a.text)}</p><time>${a.time}</time></li>`).join('');

  // avatar preview
  $('#avatarInput').addEventListener('change', (e) => {
    const f = e.target.files[0];
    if (!f) return;
    if (!f.type.startsWith('image/')) return toast('اختر ملف صورة', 'error');
    if (f.size > 3 * 1024 * 1024) return toast('الحد الأقصى 3MB', 'error');
    const url = URL.createObjectURL(f);
    $('#avatarImg').src = url;
    $$('.tb-user img, .sb-user img').forEach((img) => (img.src = url));
    toast('تم تحديث الصورة');
  });
  $('#viewSite').addEventListener('click', (e) => {
    e.preventDefault();
    toast('ستفتح صفحتك في الموقع عند ربط الرابط', 'info');
  });

  // bio counter
  const bio = $('#fBio');
  const count = () => ($('#bioCount').textContent = bio.value.length);
  bio.addEventListener('input', count);
  count();

  $('#infoForm').addEventListener('reset', () => setTimeout(() => {
    count();
    $$('#infoForm .invalid').forEach((i) => i.classList.remove('invalid'));
  }));
  $('#infoForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const name = $('#fName');
    const email = $('#fEmail');
    name.classList.toggle('invalid', !name.value.trim());
    email.classList.toggle('invalid', !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value));
    if ($$('#infoForm .invalid').length) return toast('تحقق من الحقول المطلوبة', 'error');
    $('#pName').textContent = name.value.trim();
    toast('تم حفظ معلوماتك');
  });

  // password strength
  const colors = ['#e02424', '#c27803', '#0066ff', '#0e9f6e'];
  const labels = ['ضعيفة', 'متوسطة', 'جيدة', 'قوية'];
  const score = (p) => [p.length >= 8, /[A-Z]/.test(p), /\d/.test(p), /[^A-Za-z0-9]/.test(p) || p.length >= 12].filter(Boolean).length;
  $('#pwNew').addEventListener('input', (e) => {
    const s = score(e.target.value);
    $$('#pwMeter i').forEach((b, i) => (b.style.background = e.target.value && i < s ? colors[s - 1] : ''));
    $('#pwHint').textContent = e.target.value ? `قوة كلمة المرور: ${labels[Math.max(0, s - 1)]}` : '8 أحرف على الأقل، مع رقم وحرف كبير.';
  });
  $('#pwForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const [o, n, c] = ['#pwOld', '#pwNew', '#pwConfirm'].map((s) => $(s));
    $$('#pwForm .invalid').forEach((i) => i.classList.remove('invalid'));
    if (!o.value) return o.classList.add('invalid'), toast('أدخل كلمة المرور الحالية', 'error');
    if (score(n.value) < 3) return n.classList.add('invalid'), toast('كلمة المرور الجديدة ضعيفة', 'error');
    if (n.value !== c.value) return c.classList.add('invalid'), toast('كلمتا المرور غير متطابقتين', 'error');
    e.target.reset();
    $$('#pwMeter i').forEach((b) => (b.style.background = ''));
    toast('تم تحديث كلمة المرور');
  });
});
