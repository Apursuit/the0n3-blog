/**
 * 精选推荐（Featured Recommendation）
 * ---------------------------------------------------------------------------
 * 功能：文章页末尾随机推荐 1 篇精选文章，避免优质内容在归档页淹没。
 *
 * 数据：本文件顶部的 FEATURED 数组是唯一数据源（10 篇）。
 *      顺序即优先级：越靠前推荐权重越高。
 *
 * 权重：几何衰减 w = RANK_DECAY^i，默认 0.8。10 篇时的曝光概率：
 *        #1 22.4%  #2 17.9%  #3 14.3%  #4 11.5%  #5 9.2%
 *        #6 7.3%   #7 5.9%   #8 4.7%   #9 3.8%   #10 3.0%
 *      单条可用 weight 字段显式覆盖（用于强推某篇旗舰文）。
 *
 * 规则：
 *  - 仅文章页生效（靠 [data-featured-recommend] 占位容器判断）。
 *  - 排除当前文章，避免推荐自己。
 *  - 同一会话内尽量不重复推荐上一篇（sessionStorage）。
 *
 * 维护提醒：修改文章 permalink / 标题时，务必同步更新 FEATURED，否则会 404。
 */
(function () {
    'use strict';

    // =======================================================================
    // 1. 精选文章清单（唯一数据源，10 篇）
    //    - url    : 文章 permalink，需与 posts/*.md 的 frontMatter.permalink 完全一致
    //    - title  : 推荐卡片展示的标题
    //    - weight : 可选，正数。不填则按顺序几何衰减
    //    顺序 = 优先级，越靠前权重越高
    // =======================================================================
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

    // =======================================================================
    // 2. 权重参数
    //    RANK_DECAY 越小越集中在头部，越大越平均（1 = 完全均匀，推荐 0.7~0.9）
    // =======================================================================
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
            if (normalizePath(entry.url) === here) continue; // 排除当前文章
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
