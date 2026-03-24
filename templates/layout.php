<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($site['title'] ?? 'My Blog') ?><?= isset($pageTitle) ? ' - ' . htmlspecialchars($pageTitle) : '' ?></title>
    <?php if (!empty($site['description'])): ?>
    <meta name="description" content="<?= htmlspecialchars($site['description']) ?>">
    <?php endif; ?>
    <?php if (!empty($site['canonical'])): ?>
    <link rel="canonical" href="<?= htmlspecialchars($site['canonical']) ?>">
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
    <?php if (!empty($enableTaxAccordion)): ?>
    <link rel="stylesheet" href="/assets/css/tax-accordion.css">
    <?php endif; ?>
    <?php if (!empty($enableReadingProgress)): ?>
    <link rel="stylesheet" href="/assets/css/reading-progress.css">
    <?php endif; ?>
    <?php if (!empty($enableImageEnhance)): ?>
    <link rel="stylesheet" href="/assets/css/image-enhance.css">
    <?php endif; ?>
    <link rel="stylesheet" href="/assets/css/nav-reveal.css">
    <!-- 代码高亮样式 -->
    <link rel="stylesheet" href="/assets/prism/prism.css">
</head>
<body>
    <?php if (!empty($enableReadingProgress)): ?>
    <div class="reading-progress" aria-hidden="true">
        <div class="reading-progress__bar"></div>
    </div>
    <?php endif; ?>
    <header>
        <nav>
            <div class="nav-right">
                <a href="/">首页</a>
                <a href="/archives">归档</a>
                <a href="/categories">分类</a>
                <a href="/tags">标签</a>
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
    <?php if (!empty($enableTaxAccordion)): ?>
    <script src="/assets/js/taxAccordion.js"></script>
    <?php endif; ?>
    <script src="/assets/js/navReveal.js"></script>
    <?php if (!empty($enableReadingProgress)): ?>
    <script src="/assets/js/readingProgress.js"></script>
    <?php endif; ?>
    <?php if (!empty($enableImageEnhance)): ?>
    <script src="/assets/js/imageEnhance.js"></script>
    <?php endif; ?>
    <!-- 启动代码高亮及行号显示 -->
    <script src="/assets/prism/prism.js"></script>
    <script src="/assets/prism/prism-line-number.js"></script>
    <!-- 主题切换 -->
    <script src="/assets/js/ThemeSwitch.js"></script>
</body>
</html>
