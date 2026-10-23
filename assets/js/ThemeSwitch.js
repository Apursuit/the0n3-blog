/**
 * 站点主题切换
 *
 * 只负责站点自身的主题状态，切换后通过 themechange 事件对外广播；
 * 评论区（giscus）的主题同步由 assets/features/giscus-theme/script.js 订阅该事件完成，
 * 两边不再互相依赖，脚本加载顺序也不影响结果。
 */
(function () {
    'use strict';

    var KEY = 'theme';
    var root = document.documentElement;
    var unlockTimer;

    function readSaved() {
        try {
            return localStorage.getItem(KEY);
        } catch (e) {
            return null;
        }
    }

    function save(theme) {
        try {
            localStorage.setItem(KEY, theme);
        } catch (e) {
            // 隐私模式等场景下写入失败，仅本次会话生效
        }
    }

    function currentTheme() {
        var saved = readSaved();
        if (saved === 'dark' || saved === 'light') return saved;
        // 无保存偏好时沿用 <head> 内联脚本已写入的值，默认 light
        return root.dataset.theme || 'light';
    }

    function paint(theme) {
        // 加锁后强制一次样式计算，让统一时长先生效，再提交新色
        root.classList.add('theme-switching');
        void root.offsetHeight;

        root.dataset.theme = theme;
        root.style.colorScheme = theme;

        var btn = document.getElementById('themeToggle');
        if (btn) {
            btn.setAttribute('data-theme', theme);
            btn.textContent = '';
        }

        void root.offsetHeight;
        // 过渡跑完再解锁，中途改时长会打断过渡；余量留足，调时长不必同步改这里
        clearTimeout(unlockTimer);
        unlockTimer = setTimeout(function () {
            root.classList.remove('theme-switching');
        }, 400);
    }

    paint(currentTheme());

    var toggle = document.getElementById('themeToggle');
    if (!toggle) return;

    toggle.addEventListener('click', function () {
        var next = root.dataset.theme === 'dark' ? 'light' : 'dark';
        save(next);
        paint(next);
        document.dispatchEvent(new CustomEvent('themechange', { detail: { theme: next } }));
    });
})();
