const key = 'theme';
const root = document.documentElement;
const saved = localStorage.getItem(key);
const initial = (saved === 'dark' || saved === 'light') ? saved : (root.dataset.theme || 'light');
root.dataset.theme = initial;
root.style.colorScheme = initial;

function syncButtonLabel(theme) {
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.setAttribute('data-theme', theme);
  btn.textContent = '';
}

syncButtonLabel(root.dataset.theme);

document.getElementById('themeToggle')?.addEventListener('click', () => {
  const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
  root.dataset.theme = next;
  root.style.colorScheme = next;
  localStorage.setItem(key, next);
  syncButtonLabel(next);
});