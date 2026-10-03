document.addEventListener('app:ready', () => {
  const { $, icon, toast } = App;
  const email = $('#email');
  const pw = $('#password');

  const setErr = (input, el, msg) => {
    input.classList.toggle('invalid', !!msg);
    el.hidden = !msg;
    el.textContent = msg || '';
  };

  $('#togglePw').addEventListener('click', (e) => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    e.currentTarget.innerHTML = icon(show ? 'eye-off' : 'eye', 'sm');
    e.currentTarget.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
  });
  $('#forgot').addEventListener('click', (e) => {
    e.preventDefault();
    if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value)) return setErr(email, $('#emailErr'), 'أدخل بريدك أولاً لنرسل رابط الاستعادة');
    toast(`أرسلنا رابط الاستعادة إلى ${email.value}`);
  });
  email.addEventListener('input', () => setErr(email, $('#emailErr')));
  pw.addEventListener('input', () => setErr(pw.closest('.input-group'), $('#pwErr')));

  $('#loginForm').addEventListener('submit', (e) => {
    e.preventDefault();
    const okEmail = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email.value.trim());
    const okPw = pw.value.length >= 6;
    setErr(email, $('#emailErr'), okEmail ? '' : 'البريد الإلكتروني غير صحيح');
    setErr(pw.closest('.input-group'), $('#pwErr'), okPw ? '' : 'كلمة المرور 6 أحرف على الأقل');
    if (!okEmail) return email.focus();
    if (!okPw) return pw.focus();

    const btn = $('#submit');
    btn.disabled = true;
    btn.textContent = 'جاري الدخول…';
    // replace with a real API call: POST /api/auth/login
    setTimeout(() => (location.href = App.url('dashboard')), 700);
  });
});
