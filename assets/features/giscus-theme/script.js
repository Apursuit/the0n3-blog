/**
 * Giscus 评论主题
 *
 * 站点主题由访客的 localStorage 决定，只有前端才知道；而 giscus 的主题是在创建
 * iframe 时由 client.js 读取 script 标签的 data-theme 拼进 widget URL 决定的。
 * 所以这里不做"先用写死的主题渲染、再 postMessage 纠正"（存在竞态，纠正消息可能
 * 在 widget 挂载前被丢弃），而是先算出访客当前主题再注入 script —— 评论区第一帧
 * 就是正确主题。postMessage 只负责用户主动切换时的运行期更新。
 *
 * 配置与容器由 templates/post.php 输出，本文件由 templates/layout.php 的
 * assets/features/ 自动扫描机制引入。
 */
(function () {
    'use strict';

    var SRC = 'https://giscus.app/client.js';
    var ORIGIN = 'https://giscus.app';
    // 本模块的控制字段，不转发给 giscus 的 script 标签
    var CONTROL = ['giscus', 'themeMode', 'themeLight', 'themeDark'];

    function init() {
        var section = document.querySelector('[data-giscus]');
        if (!section) return;                                              // 非文章页或未启用评论
        if (section.querySelector('script[src*="giscus.app"]')) return;     // 防止重复注入

        var pending = null;   // 加载期间用户切换主题：先记下，等 widget 就绪后补发
        var ready = false;    // 收到过 widget 的消息即视为其 setConfig 监听已注册

        function siteTheme() {
            return document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
        }

        function resolveTheme() {
            var mode = section.dataset.themeMode || 'auto';
            if (mode !== 'auto') return mode;                               // 配置里指定了固定主题
            return siteTheme() === 'dark'
                ? (section.dataset.themeDark || 'dark_dimmed')
                : (section.dataset.themeLight || 'light');
        }

        function kebab(key) {
            return key.replace(/[A-Z]/g, function (m) { return '-' + m.toLowerCase(); });
        }

        function inject(theme) {
            var el = document.createElement('script');
            el.src = SRC;
            el.async = true;
            el.crossOrigin = 'anonymous';

            Object.keys(section.dataset).forEach(function (key) {
                if (CONTROL.indexOf(key) !== -1) return;
                el.setAttribute('data-' + kebab(key), section.dataset[key]);
            });
            el.setAttribute('data-theme', theme);

            // client.js 依赖 document.currentScript，且会把 iframe 容器插到自己之后，
            // 所以脚本元素必须真正挂载到文档里（游离节点会抛错）
            section.appendChild(el);
            return el;
        }

        function post(theme) {
            var iframe = document.querySelector('iframe.giscus-frame');
            if (!iframe || !iframe.contentWindow) return false;

            iframe.contentWindow.postMessage(
                {
                    giscus: {
                        setConfig: {
                            theme: theme
                        }
                    }
                },
                ORIGIN
            );

            return true;
        }

        // widget 挂载后会主动向父页面发消息（resizeHeight 等），以此作为就绪信号，
        // 替代原先"固定次数 + 固定间隔"的盲发重试
        window.addEventListener('message', function (event) {
            if (event.origin !== ORIGIN) return;
            ready = true;
            if (pending) {
                post(pending);
                pending = null;
            }
        });

        var script = inject(resolveTheme());

        script.addEventListener('load', function () {
            if (pending && !ready) post(pending);
        });

        document.addEventListener('themechange', function () {
            var theme = resolveTheme();
            if (post(theme)) return;
            pending = theme;
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
