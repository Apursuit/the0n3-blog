/**
 * 精选推荐：文章页末尾随机推荐 1 篇精选文章。
 * 数据源为本文件的 FEATURED 数组，顺序即权重，单条可用 weight 显式覆盖。
 * 权重按 w = RANK_DECAY^i 几何衰减，取 0.8 时首条约 22%、末条约 3%。
 * 仅文章页生效（靠 [data-featured-recommend] 容器判断），排除当前文章、会话内不重复。
 *
 * 维护提醒：修改文章 permalink 后务必同步 FEATURED，否则会 404。
 */
(function () {
    'use strict';

    // 唯一数据源，顺序即权重。url 须与 posts/*.md 的 frontMatter.permalink 完全一致
    var FEATURED = [
        { url: '/pages/mazesec-mao/', title: 'MazeSec 矛计划 - 如何制作一台靶机' },
        { url: '/pages/linux-config-privesc/', title: '利用逐行解析配置文件实现权限提升的八种方式' },
        { url: '/posts/windows-admin-to-system/', title: 'Windows从Administrator提升到system权限' },
        { url: '/pages/disk-root/', title: 'disk组用户提权' },
        { url: '/posts/busybox138/', title: 'Alpine Linux 下 SUID Bash 获取 euid=0 Shell 后 BusyBox 降权机制分析' },
        { url: '/posts/lnk-file-phishing/', title: 'lnk 文件钓鱼实验' },
        { url: '/posts/comBypassUAC/', title: 'BypassUAC 实验记录' },
        { url: '/posts/maze-cat/', title: 'MazeSec cat靶机设计流程' },
        { url: '/pages/maze-111/', title: 'MazeSec 111' },
        { url: '/posts/maze-hihyh/', title: 'MazeSec HiHYH 靶机设计流程' }
    ];

    // 越小越集中在头部，1 为完全均匀
    var RANK_DECAY = 0.8;

    // 会话去重用的存储 key
    var LAST_KEY = 'featured-recommend:last';

    // 计算第 index 篇（从 0 开始）的权重
    function weightOf(entry, index) {
        if (typeof entry.weight === 'number' && entry.weight > 0) {
            return entry.weight;
        }
        return Math.pow(RANK_DECAY, index);
    }

    // 累积权重轮盘抽样：按权重比例随机返回一项
    function pickWeighted(entries, weights) {
        var total = 0;
        for (var i = 0; i < weights.length; i++) total += weights[i];
        if (total <= 0) {
            return entries[Math.floor(Math.random() * entries.length)];
        }
        var r = Math.random() * total;
        for (var j = 0; j < entries.length; j++) {
            r -= weights[j];
            if (r <= 0) return entries[j];
        }
        return entries[entries.length - 1];
    }

    // 路径归一化：去掉结尾斜杠，便于判断是否同一篇文章
    function normalizePath(path) {
        return (path || '').replace(/\/+$/, '') || '/';
    }

    function readLast() {
        try { return sessionStorage.getItem(LAST_KEY) || ''; } catch (e) { return ''; }
    }

    function writeLast(url) {
        try { sessionStorage.setItem(LAST_KEY, normalizePath(url)); } catch (e) {}
    }

    function init() {
        // 仅文章页有占位容器；其它页面直接退出
        var root = document.querySelector('[data-featured-recommend]');
        if (!root) return;
        var slot = root.querySelector('[data-featured-recommend-slot]');
        if (!slot) return;

        var here = normalizePath(location.pathname);

        // 先按原始顺序计算权重（保证 rank 不因过滤而变化），再排除当前文章
        var candidates = [];
        for (var i = 0; i < FEATURED.length; i++) {
            var entry = FEATURED[i];
            if (!entry || !entry.url || !entry.title) continue;
            if (normalizePath(entry.url) === here) continue;
            candidates.push({ entry: entry, weight: weightOf(entry, i) });
        }
        if (candidates.length === 0) return; // 无候选则不显示

        // 会话内去重：优先从候选里排除上次推荐过的文章
        var last = readLast();
        var pool = candidates.filter(function (c) {
            return normalizePath(c.entry.url) !== last;
        });
        if (pool.length === 0) pool = candidates;

        var entries = pool.map(function (c) { return c.entry; });
        var weights = pool.map(function (c) { return c.weight; });
        var picked = pickWeighted(entries, weights);

        // 渲染单个推荐链接
        var link = document.createElement('a');
        link.className = 'post-recommend__link';
        link.href = picked.url;
        link.textContent = picked.title;
        slot.appendChild(link);

        root.hidden = false;
        writeLast(picked.url);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
