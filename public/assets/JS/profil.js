document.addEventListener('DOMContentLoaded', () => {
  const input = document.getElementById('avatarInput');
  if (input && input.form) {
    input.addEventListener('change', () => input.form.submit());
  }
});