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
      // invitations and reset links are separate tokens with separate endpoints
      await api.post(form.dataset.invite === '1' ? 'invitations/accept' : 'auth/reset-password', {
        token: form.dataset.token,
        email: $('#email').value,
        password: $('#password').value,
        password_confirmation: $('#password_confirmation').value,
      });
      location.href = App.url('login', { reset: 1 });
    } catch (err) {
      btn.disabled = false;
      btn.textContent = label;
      showFieldErrors(err, { password: '#password', email: '#email' });
    }
  });
});
