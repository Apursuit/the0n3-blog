// 放在 site.js 顶部即可（不必等 DOMContentLoaded）
document.querySelectorAll('pre > code[class*="language-"]').forEach(code => {
  code.parentElement.classList.add('line-numbers');
});