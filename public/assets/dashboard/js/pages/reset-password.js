document.addEventListener('app:ready', () => {
  const { $, $$, api, showFieldErrors } = App;
  const form = $('#resetForm');

  form.addEventListener('input', (e) => e.target.classList.remove('invalid'));
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    $$('.invalid', form).forEach((i) => i.classList.remove('invalid'));
    const btn = $('#submit');
    const label = btn.textContent;
    btn.disabled = true;
    btn.textContent = 'جارٍ الحفظ…';
    try {
      await api.post('auth/reset-password', {
        token: form.dataset.token,
        email: $('#email').value,
        password: $('#password').value,
        password_confirmation: $('#password_confirmation').value,
        invite: form.dataset.invite === '1',
      });
      location.href = App.url('login', { reset: 1 });
    } catch (err) {
      btn.disabled = false;
      btn.textContent = label;
      showFieldErrors(err, { password: '#password', email: '#email' });
    }
  });
});
