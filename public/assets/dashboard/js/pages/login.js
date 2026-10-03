document.addEventListener('app:ready', () => {
  const { $, icon, toast, api } = App;
  const email = $('#email');
  const pw = $('#password');
  const validEmail = (v) => /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v);

  const setErr = (input, el, msg) => {
    input.classList.toggle('invalid', !!msg);
    el.hidden = !msg;
    el.textContent = msg || '';
  };

  // back from the reset-password / invitation page
  if (new URLSearchParams(location.search).has('reset')) toast('تم تعيين كلمة المرور، سجّل الدخول الآن');

  $('#togglePw').addEventListener('click', (e) => {
    const show = pw.type === 'password';
    pw.type = show ? 'text' : 'password';
    e.currentTarget.innerHTML = icon(show ? 'eye-off' : 'eye', 'sm');
    e.currentTarget.setAttribute('aria-label', show ? 'إخفاء كلمة المرور' : 'إظهار كلمة المرور');
  });
  $('#forgot').addEventListener('click', async (e) => {
    e.preventDefault();
    if (!validEmail(email.value.trim())) return setErr(email, $('#emailErr'), 'أدخل بريدك أولاً لنرسل رابط الاستعادة');
    try {
      const res = await api.post('auth/forgot-password', { email: email.value.trim() });
      toast(res.message);
    } catch (err) {
      if (err.status === 422) setErr(email, $('#emailErr'), err.errors.email?.[0] || err.message);
    }
  });
  email.addEventListener('input', () => setErr(email, $('#emailErr')));
  pw.addEventListener('input', () => setErr(pw.closest('.input-group'), $('#pwErr')));

  $('#loginForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const okEmail = validEmail(email.value.trim());
    const okPw = pw.value.length > 0;
    setErr(email, $('#emailErr'), okEmail ? '' : 'البريد الإلكتروني غير صحيح');
    setErr(pw.closest('.input-group'), $('#pwErr'), okPw ? '' : 'أدخل كلمة المرور');
    if (!okEmail) return email.focus();
    if (!okPw) return pw.focus();

    const btn = $('#submit');
    btn.disabled = true;
    btn.textContent = 'جاري الدخول…';
    try {
      const res = await api.post('auth/login', { email: email.value.trim(), password: pw.value, remember: $('#remember').checked });
      location.href = res.redirect;
    } catch (err) {
      btn.disabled = false;
      btn.textContent = 'تسجيل الدخول';
      // 422 wrong credentials, 429 too many attempts: both carry the message for the email field
      if (err.errors?.email) {
        setErr(email, $('#emailErr'), err.errors.email[0]);
        pw.value = '';
        pw.focus();
      }
    }
  });
});
