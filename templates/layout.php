<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['title'] ?? 'My Blog') ?><?= isset($pageTitle) ? ' - ' . htmlspecialchars($pageTitle) : '' ?></title>
    <?php
    $canonicalUrl = $pageCanonical ?? ($site['url'] ?? '/');
    $metaDesc      = $pageDescription ?? ($site['description'] ?? '');
    $ogTitle       = $ogTitle ?? $pageTitle ?? ($site['title'] ?? '');
    $ogType        = $ogType ?? 'website';
    $ogImage       = $ogImage ?? ($site['og_image'] ?? '');
    $ogLocale      = $ogLocale ?? ($site['og_locale'] ?? 'zh_CN');
    $twitterCard   = ($ogImage !== '') ? 'summary_large_image' : 'summary';
    ?>
    <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
    <!-- Open Graph -->
    <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($metaDesc) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
    <meta property="og:type" content="<?= htmlspecialchars($ogType) ?>">
    <meta property="og:site_name" content="<?= htmlspecialchars($site['title'] ?? '') ?>">
    <meta property="og:locale" content="<?= htmlspecialchars($ogLocale) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php endif; ?>
    <!-- Twitter Card -->
    <meta name="twitter:card" content="<?= $twitterCard ?>">
    <meta name="twitter:title" content="<?= htmlspecialchars($ogTitle) ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($metaDesc) ?>">
    <?php if ($ogImage !== ''): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($ogImage) ?>">
    <?php endif; ?>
    <script>
        (function () {
            try {
                var key = 'theme';
                var saved = localStorage.getItem(key);
                var theme = (saved === 'dark' || saved === 'light') ? saved : 'light';
                document.documentElement.setAttribute('data-theme', theme);
                document.documentElement.style.colorScheme = theme;
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
                document.documentElement.style.colorScheme = 'light';
            }
        })();
    </script>
    <!-- 使用 GitHub Markdown 样式 -->
    <link rel="stylesheet" href="/assets/css/github-markdown.css">
    <link rel="stylesheet" href="/assets/css/site.css">
    <!-- 功能样式 (自动扫描 assets/features/ 目录) -->
    <?php
    $featuresDir = __DIR__ . '/../assets/features';
    if (is_dir($featuresDir)):
        foreach (glob($featuresDir . '/*', GLOB_ONLYDIR) as $fDir):
            $fName = basename($fDir);
            if (file_exists("{$fDir}/style.css")):
    ?>
    <link rel="stylesheet" href="/assets/features/<?= $fName ?>/style.css">
    <?php
            endif;
        endforeach;
    endif;
    ?>
    <link rel="stylesheet" href="/assets/css/nav-reveal.css">
    <link rel="stylesheet" href="/assets/css/search.css">
    <!-- 代码高亮样式 -->
    <link rel="stylesheet" href="/assets/prism/prism.css">
</head>
<body>
    <div class="reading-progress" aria-hidden="true">
        <div class="reading-progress__bar"></div>
    </div>
    <header>
        <nav>
            <div class="nav-right">
                <a href="/">首页</a>
                <a href="/archives">归档</a>
                <a href="/categories">分类</a>
                <a href="/tags">标签</a>
                
                <!-- 搜索框 -->
                <div class="search-box">
                    <input 
                        id="searchInput" 
                        type="text" 
                        placeholder="搜索文章..."
                        autocomplete="off"
                    >
                    <div id="searchResults" class="search-results" style="display:none;">
                        <ul></ul>
                    </div>
                </div>
            </div>
        </nav>
    </header>
    <?php $hasSidebar = isset($sidebar) && trim($sidebar) !== ''; ?>
    <?php if ($hasSidebar): ?>
        <div class="page-layout">
            <main class="markdown-body">
                <?= $content ?? '' ?>
            </main>
            <?= $sidebar ?>
        </div>
    <?php else: ?>
        <main class="markdown-body">
            <?= $content ?? '' ?>
        </main>
    <?php endif; ?>
    <button id="backToTop" class="fab fab-top" type="button" aria-label="返回顶部" title="返回顶部">↑</button>
    <button id="themeToggle" class="theme-toggle" type="button" aria-label="切换主题" title="切换主题"></button>
    <script src="/assets/js/site.js"></script>
    <!-- 功能脚本 (自动扫描 assets/features/ 目录) -->
    <?php
    if (is_dir($featuresDir)):
        foreach (glob($featuresDir . '/*', GLOB_ONLYDIR) as $fDir):
            $fName = basename($fDir);
            if (file_exists("{$fDir}/script.js")):
    ?>
    <script src="/assets/features/<?= $fName ?>/script.js"></script>
    <?php
            endif;
        endforeach;
    endif;
    ?>
    <script src="/assets/js/navReveal.js"></script>
    <!-- 启动代码高亮及行号显示 -->
    <script src="/assets/prism/prism.js"></script>
    <script src="/assets/prism/prism-line-number.js"></script>
    <!-- 主题切换 -->
    <script src="/assets/js/ThemeSwitch.js"></script>
    <!-- 搜索功能 -->
    <script src="/assets/js/fuse.js"></script>
    <script src="/assets/js/search.js"></script>
</body>
</html>
