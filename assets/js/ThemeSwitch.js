const key = 'theme';
const root = document.documentElement;
const saved = localStorage.getItem(key);
const initial = (saved === 'dark' || saved === 'light')
  ? saved
  : (root.dataset.theme || 'light');

root.dataset.theme = initial;
root.style.colorScheme = initial;

function syncButtonLabel(theme) {
  const btn = document.getElementById('themeToggle');
  if (!btn) return;
  btn.setAttribute('data-theme', theme);
  btn.textContent = '';
}

function getGiscusTheme(siteTheme) {
  return siteTheme === 'dark' ? 'dark_dimmed' : 'light';
}

function syncGiscusTheme(theme) {
  const iframe = document.querySelector('iframe.giscus-frame');
  if (!iframe) return false;

  iframe.contentWindow.postMessage(
    {
      giscus: {
        setConfig: {
          theme: getGiscusTheme(theme)
        }
      }
    },
    'https://giscus.app'
  );

  return true;
}

function syncGiscusThemeWithRetry(theme, retries = 10, delay = 300) {
  let count = 0;

  const trySync = () => {
    const ok = syncGiscusTheme(theme);
    count += 1;

    if (count < retries) {
      setTimeout(trySync, delay);
    }
  };

  trySync();
}

syncButtonLabel(root.dataset.theme);
syncGiscusThemeWithRetry(root.dataset.theme);

document.getElementById('themeToggle')?.addEventListener('click', () => {
  const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
  root.dataset.theme = next;
  root.style.colorScheme = next;
  localStorage.setItem(key, next);
  syncButtonLabel(next);
  syncGiscusThemeWithRetry(next, 4, 200);
});