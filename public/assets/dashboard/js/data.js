/* ==========================================================================
   بيانات تجريبية للوحة التحكم.
   عند ربط اللوحة بخادم حقيقي، استبدل هذه المصفوفات باستدعاءات API (fetch).
   ========================================================================== */
(function () {
  // deterministic pseudo-random so the sample data looks the same on every load
  let seed = 7;
  const rand = () => ((seed = (seed * 9301 + 49297) % 233280) / 233280);
  const pick = (arr) => arr[Math.floor(rand() * arr.length)];
  const int = (min, max) => Math.floor(rand() * (max - min + 1)) + min;

  const admin = { name: 'عبدالرحمن البطة', role: 'مدير المنصة', email: 'admin@batta.dev', initial: 'ع', photo: 'img/profile-face.jpg' };

  const courses = [
    { id: 'C-101', title: 'Next.js من الصفر إلى الإنتاج', glyph: '▲ next', level: 'متوسط', price: 79, students: 410, lessons: 56, hours: 18, rating: 4.8, status: 'منشورة', revenue: 32390, updated: '2026-09-28' },
    { id: 'C-102', title: 'أساسيات الويب الحديث', glyph: '</>', level: 'مبتدئ', price: 39, students: 640, lessons: 42, hours: 12, rating: 4.9, status: 'منشورة', revenue: 24960, updated: '2026-09-20' },
    { id: 'C-103', title: 'برنامج المطوّر المستقل', glyph: '{ $ }', level: 'متقدم', price: 249, students: 45, lessons: 8, hours: 24, rating: 5.0, status: 'منشورة', revenue: 11205, updated: '2026-09-30' },
    { id: 'C-104', title: 'Git و GitHub للفرق', glyph: 'git', level: 'مبتدئ', price: 0, students: 980, lessons: 18, hours: 4, rating: 4.7, status: 'منشورة', revenue: 0, updated: '2026-08-14' },
    { id: 'C-105', title: 'APIs باستخدام Node و PostgreSQL', glyph: 'API', level: 'متوسط', price: 69, students: 220, lessons: 38, hours: 14, rating: 4.8, status: 'منشورة', revenue: 15180, updated: '2026-09-02' },
    { id: 'C-106', title: 'Vue 3 عملياً: من المكونات إلى المتجر', glyph: 'V', level: 'متوسط', price: 59, students: 0, lessons: 24, hours: 10, rating: 0, status: 'مسودة', revenue: 0, updated: '2026-10-01' },
    { id: 'C-107', title: 'TypeScript للمطورين', glyph: 'TS', level: 'متوسط', price: 49, students: 0, lessons: 12, hours: 6, rating: 0, status: 'قيد المراجعة', revenue: 0, updated: '2026-09-25' },
  ];

  const workshops = [
    { id: 'W-21', title: 'ابنِ ملف أعمالك في ساعتين', date: '2026-10-14', time: '19:00', format: 'أونلاين', place: 'Zoom', price: 0, seats: 100, taken: 82, status: 'مفتوحة' },
    { id: 'W-22', title: 'Server Actions في Next.js عملياً', date: '2026-10-28', time: '19:00', format: 'أونلاين', place: 'Zoom', price: 19, seats: 40, taken: 31, status: 'مفتوحة' },
    { id: 'W-23', title: 'يوم كامل: من الفكرة إلى منتج منشور', date: '2026-11-09', time: '10:00', format: 'حضوري', place: 'عمّان', price: 49, seats: 25, taken: 11, status: 'مفتوحة' },
    { id: 'W-24', title: 'ورشة خاصة: فريق حاضنة الأعمال', date: '2026-11-18', time: '10:00', format: 'حضوري', place: 'رام الله', price: 1200, seats: 18, taken: 18, status: 'مكتملة' },
    { id: 'W-19', title: 'مقدمة في Git للطلاب', date: '2026-09-16', time: '18:00', format: 'أونلاين', place: 'Zoom', price: 0, seats: 120, taken: 118, status: 'منتهية' },
  ];

  const articles = [
    { id: 'A-31', title: 'بناء نظام مصادقة كامل في Next.js خطوة بخطوة', category: 'دروس عملية', status: 'منشور', views: 8420, comments: 34, date: '2026-09-30', minutes: 14 },
    { id: 'A-30', title: 'كيف سلّمت موقع مطعم في 14 يوماً', category: 'خلف الكواليس', status: 'منشور', views: 5210, comments: 21, date: '2026-09-23', minutes: 8 },
    { id: 'A-29', title: 'كيف تسعّر أول مشروع عمل حر لك', category: 'العمل الحر', status: 'منشور', views: 12890, comments: 58, date: '2026-09-16', minutes: 6 },
    { id: 'A-28', title: 'أدواتي اليومية كمطوّر في 2026', category: 'أدوات و AI', status: 'منشور', views: 6730, comments: 19, date: '2026-09-09', minutes: 5 },
    { id: 'A-27', title: 'PostgreSQL للمبتدئين: الفهارس بلغة بسيطة', category: 'دروس عملية', status: 'منشور', views: 4120, comments: 12, date: '2026-09-02', minutes: 11 },
    { id: 'A-32', title: 'Server Actions: متى تستخدمها ومتى تتجنبها', category: 'دروس عملية', status: 'مجدول', views: 0, comments: 0, date: '2026-10-07', minutes: 9 },
    { id: 'A-33', title: 'كيف تكتب عرض سعر يقنع العميل', category: 'العمل الحر', status: 'مسودة', views: 0, comments: 0, date: '2026-10-02', minutes: 7 },
  ];

  const firstNames = ['محمد', 'أحمد', 'سارة', 'ليان', 'يوسف', 'رامي', 'نور', 'خالد', 'مريم', 'عمر', 'هبة', 'زيد', 'دانة', 'علي', 'جود', 'سلمى', 'آدم', 'رنا', 'مالك', 'تالا'];
  const lastNames = ['الخطيب', 'النجار', 'العلي', 'حمدان', 'الشريف', 'عودة', 'منصور', 'سالم', 'الحسن', 'يونس', 'البرغوثي', 'قاسم'];
  const countries = ['السعودية', 'الأردن', 'فلسطين', 'مصر', 'الإمارات', 'الكويت', 'المغرب', 'قطر'];
  const avatarColors = ['#0066ff', '#0b0d12', '#334155', '#5c9dff', '#0e9f6e', '#7c3aed', '#c27803'];
  const translit = ['mohammad', 'ahmad', 'sara', 'layan', 'yousef', 'rami', 'noor', 'khaled', 'maryam', 'omar', 'heba', 'zaid', 'dana', 'ali', 'jood', 'salma', 'adam', 'rana', 'malek', 'tala'];

  const students = Array.from({ length: 48 }, (_, i) => {
    const fi = int(0, firstNames.length - 1);
    const name = `${firstNames[fi]} ${pick(lastNames)}`;
    const enrolled = int(1, 4);
    const status = rand() > 0.85 ? 'موقوف' : rand() > 0.25 ? 'نشط' : 'غير نشط';
    return {
      id: `S-${1000 + i}`,
      name,
      initial: firstNames[fi][0],
      color: pick(avatarColors),
      email: `${translit[fi]}${int(10, 99)}@mail.com`,
      country: pick(countries),
      courses: enrolled,
      progress: int(5, 100),
      spent: enrolled * pick([0, 39, 49, 69, 79]),
      joined: `2026-${String(int(1, 9)).padStart(2, '0')}-${String(int(1, 28)).padStart(2, '0')}`,
      status,
      pro: rand() > 0.8,
    };
  });

  const orderItems = [
    { name: 'Next.js من الصفر إلى الإنتاج', amount: 79, type: 'دورة' },
    { name: 'أساسيات الويب الحديث', amount: 39, type: 'دورة' },
    { name: 'APIs باستخدام Node و PostgreSQL', amount: 69, type: 'دورة' },
    { name: 'برنامج المطوّر المستقل', amount: 249, type: 'دورة' },
    { name: 'ورشة Server Actions', amount: 19, type: 'ورشة' },
    { name: 'ورشة يوم كامل', amount: 49, type: 'ورشة' },
    { name: 'اشتراك Pro شهري', amount: 9, type: 'اشتراك' },
  ];
  const methods = ['بطاقة', 'PayPal', 'Apple Pay'];
  const orders = Array.from({ length: 64 }, (_, i) => {
    const s = students[int(0, students.length - 1)];
    const item = pick(orderItems);
    const r = rand();
    const status = r > 0.9 ? 'مسترد' : r > 0.82 ? 'معلّق' : r > 0.78 ? 'فشل' : 'مكتمل';
    const day = 30 - Math.floor(i / 2.2);
    return {
      id: `#${(4821 - i).toString()}`,
      customer: s.name,
      initial: s.initial,
      color: s.color,
      email: s.email,
      item: item.name,
      type: item.type,
      amount: item.amount,
      method: pick(methods),
      date: `2026-${day > 0 ? '09' : '08'}-${String(day > 0 ? day : 28 + day).padStart(2, '0')}`,
      status,
    };
  });

  const leads = [
    { id: 'L-51', name: 'رامي حمدان', company: 'محمصة البن الذهبي', service: 'المتاجر الإلكترونية', budget: 2500, stage: 'new', date: '2026-10-02', note: 'متجر مع اشتراكات شهرية' },
    { id: 'L-50', name: 'د. هبة يونس', company: 'عيادة الابتسامة', service: 'تطبيقات الويب', budget: 4000, stage: 'new', date: '2026-10-01', note: 'نظام حجوزات وتذكير' },
    { id: 'L-49', name: 'خالد منصور', company: 'حاضنة رواد', service: 'تدريب الفرق والجامعات', budget: 1200, stage: 'contacted', date: '2026-09-29', note: 'ورشة يومين لفريق 18 شخصاً' },
    { id: 'L-48', name: 'نور الشريف', company: 'استوديو نور', service: 'تطوير المواقع', budget: 800, stage: 'contacted', date: '2026-09-27', note: 'موقع معرض أعمال' },
    { id: 'L-47', name: 'عمر قاسم', company: 'لوجستك برو', service: 'لوحات التحكم والأنظمة', budget: 6500, stage: 'proposal', date: '2026-09-24', note: 'لوحة تتبع شحنات' },
    { id: 'L-46', name: 'سلمى العلي', company: 'مطعم البيت', service: 'تطوير المواقع', budget: 600, stage: 'proposal', date: '2026-09-22', note: 'قائمة طعام وحجز طاولات' },
    { id: 'L-45', name: 'زيد النجار', company: 'متجر زيد', service: 'الصيانة والتطوير المستمر', budget: 300, stage: 'won', date: '2026-09-18', note: 'اشتراك صيانة شهري' },
    { id: 'L-44', name: 'مريم سالم', company: 'أكاديمية مريم', service: 'تطبيقات الويب', budget: 5200, stage: 'won', date: '2026-09-12', note: 'منصة دورات داخلية' },
    { id: 'L-43', name: 'آدم عودة', company: 'شركة آدم', service: 'تطوير المواقع', budget: 400, stage: 'lost', date: '2026-09-08', note: 'اختار حلاً جاهزاً' },
  ];
  const leadStages = [
    { key: 'new', label: 'جديد', color: '#0066ff' },
    { key: 'contacted', label: 'تم التواصل', color: '#0891b2' },
    { key: 'proposal', label: 'عرض مُرسل', color: '#c27803' },
    { key: 'won', label: 'مقبول', color: '#0e9f6e' },
    { key: 'lost', label: 'مرفوض', color: '#e02424' },
  ];

const toolCategories = [    { id: 'editor', name: 'المحرر', color: '#0066ff' },    { id: 'frontend', name: 'الواجهات', color: '#0891b2' },    { id: 'backend', name: 'الخلفية', color: '#0e9f6e' },    { id: 'deploy', name: 'النشر', color: '#0b0d12' },    { id: 'design', name: 'التصميم', color: '#7c3aed' },    { id: 'productivity', name: 'الإنتاجية', color: '#c27803' },  ];
  const tools = [
    { id: 'T-1', name: 'VS Code', short: 'VS', color: '#0066ff', category: 'editor', why: 'محرري الأساسي مع إعدادات مشتركة في كل مستودع.', since: 2019, url: 'https://code.visualstudio.com', affiliate: false, status: 'منشور', clicks: 1240 },
    { id: 'T-2', name: 'Cursor', short: 'Cu', color: '#0b0d12', category: 'editor', why: 'للمهام المتكررة وإعادة الهيكلة بمساعدة AI.', since: 2024, url: 'https://cursor.com', affiliate: false, status: 'منشور', clicks: 980 },
    { id: 'T-3', name: 'Next.js', short: 'N', color: '#0b0d12', category: 'frontend', why: 'إطار العمل الافتراضي لكل مشاريع العملاء.', since: 2021, url: 'https://nextjs.org', affiliate: false, status: 'منشور', clicks: 1610 },
    { id: 'T-4', name: 'Vue', short: 'V', color: '#0e9f6e', category: 'frontend', why: 'للواجهات التفاعلية والمشاريع التي تحتاج بساطة وسرعة.', since: 2020, url: 'https://vuejs.org', affiliate: false, status: 'منشور', clicks: 870 },
    { id: 'T-5', name: 'Tailwind CSS', short: 'tw', color: '#0891b2', category: 'frontend', why: 'تصميم سريع ومتسق بدون ملفات CSS ضخمة.', since: 2021, url: 'https://tailwindcss.com', affiliate: false, status: 'منشور', clicks: 760 },
    { id: 'T-6', name: 'Node.js', short: 'JS', color: '#0e9f6e', category: 'backend', why: 'للـ APIs والمهام الخلفية.', since: 2020, url: 'https://nodejs.org', affiliate: false, status: 'منشور', clicks: 540 },
    { id: 'T-7', name: 'PostgreSQL', short: 'PG', color: '#334155', category: 'backend', why: 'قاعدة البيانات الأولى لأي مشروع جاد.', since: 2020, url: 'https://postgresql.org', affiliate: false, status: 'منشور', clicks: 610 },
    { id: 'T-8', name: 'Prisma', short: 'Pr', color: '#334155', category: 'backend', why: 'تعامل آمن ومكتوب الأنواع مع قاعدة البيانات.', since: 2022, url: 'https://prisma.io', affiliate: false, status: 'منشور', clicks: 420 },
    { id: 'T-9', name: 'Vercel', short: '▲', color: '#0b0d12', category: 'deploy', why: 'نشر تلقائي مع كل دفعة إلى GitHub.', since: 2021, url: 'https://vercel.com', affiliate: true, status: 'منشور', clicks: 1930 },
    { id: 'T-10', name: 'GitHub Actions', short: 'GH', color: '#334155', category: 'deploy', why: 'اختبارات تلقائية قبل كل دمج.', since: 2022, url: 'https://github.com/features/actions', affiliate: false, status: 'منشور', clicks: 380 },
    { id: 'T-11', name: 'Figma', short: 'Fg', color: '#7c3aed', category: 'design', why: 'تصميم الواجهات ومشاركتها مع العميل.', since: 2020, url: 'https://figma.com', affiliate: false, status: 'منشور', clicks: 690 },
    { id: 'T-12', name: 'Lemon Squeezy', short: 'LS', color: '#c27803', category: 'productivity', why: 'بيع الدورات وتحصيل المدفوعات دولياً.', since: 2025, url: 'https://lemonsqueezy.com', affiliate: true, status: 'منشور', clicks: 1150 },
    { id: 'T-13', name: 'Supabase', short: 'SB', color: '#0e9f6e', category: 'backend', why: 'قاعدة بيانات ومصادقة جاهزة للمشاريع السريعة.', since: 2025, url: 'https://supabase.com', affiliate: false, status: 'مسودة', clicks: 0 },
  ];

  const coupons = [
    { code: 'LAUNCH30', type: 'percent', value: 30, uses: 142, limit: 200, expires: '2026-10-31', status: 'نشط', scope: 'كل الدورات' },
    { code: 'NEXT20', type: 'percent', value: 20, uses: 58, limit: 100, expires: '2026-11-15', status: 'نشط', scope: 'Next.js من الصفر إلى الإنتاج' },
    { code: 'STUDENT10', type: 'fixed', value: 10, uses: 311, limit: 0, expires: '2026-12-31', status: 'نشط', scope: 'كل الدورات' },
    { code: 'WORKSHOP5', type: 'fixed', value: 5, uses: 12, limit: 50, expires: '2026-10-28', status: 'نشط', scope: 'الورش' },
    { code: 'SUMMER25', type: 'percent', value: 25, uses: 200, limit: 200, expires: '2026-08-31', status: 'منتهي', scope: 'كل الدورات' },
  ];

  const months = ['أكتوبر', 'نوفمبر', 'ديسمبر', 'يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو', 'يوليو', 'أغسطس', 'سبتمبر'];
  const revenue = {
    labels: months,
    courses: [900, 1400, 1800, 2100, 2600, 3200, 3500, 4100, 4600, 5200, 5900, 6400],
    services: [2400, 1500, 3500, 2800, 4200, 3100, 4500, 3900, 5200, 4400, 5000, 5600],
  };

  const traffic = {
    labels: Array.from({ length: 30 }, (_, i) => `${i + 1} سبتمبر`),
    visits: Array.from({ length: 30 }, (_, i) => Math.round(520 + i * 14 + Math.sin(i / 2) * 90 + int(-40, 40))),
    signups: Array.from({ length: 30 }, (_, i) => Math.round(14 + i * 0.6 + Math.cos(i / 3) * 5 + int(-3, 3))),
  };
  const sources = [
    { label: 'بحث Google', value: 42, color: '#0066ff' },
    { label: 'LinkedIn', value: 23, color: '#0b0d12' },
    { label: 'YouTube', value: 17, color: '#5c9dff' },
    { label: 'مباشر', value: 11, color: '#94a3b8' },
    { label: 'أخرى', value: 7, color: '#d2d9e4' },
  ];
  const funnel = [
    { label: 'زيارات', value: 21400 },
    { label: 'مشاهدة صفحة دورة', value: 6200 },
    { label: 'إنشاء حساب', value: 1480 },
    { label: 'بدء الدفع', value: 520 },
    { label: 'شراء مكتمل', value: 384 },
  ];
  const topPages = [
    { path: '/', title: 'الرئيسية', views: 9840, avg: '1:42' },
    { path: '/courses', title: 'الدورات', views: 6210, avg: '2:15' },
    { path: '/articles/price-first-project', title: 'كيف تسعّر أول مشروع', views: 4830, avg: '5:08' },
    { path: '/services', title: 'الخدمات', views: 3920, avg: '2:51' },
    { path: '/articles/nextjs-auth', title: 'نظام مصادقة في Next.js', views: 3410, avg: '7:22' },
  ];

  window.DB = { admin, courses, workshops, articles, tools, toolCategories, students, orders, leads, leadStages, coupons, revenue, traffic, sources, funnel, topPages, countries };
})();
