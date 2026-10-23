<?php
$pageTitle = $post['frontMatter']['title'];
ob_start();
?>

<article class="post-content">
    <h1><?= htmlspecialchars($post['frontMatter']['title']) ?></h1>
    <p>
        <span class="meta">发布于: <?= \App\Utils::formatDate($post['frontMatter']['date'], 'Y-m-d H:i') ?></span>
    </p>
    <div class="post-body">
        <?= $post['html'] ?>
    </div>
    <?php if (!empty($post['frontMatter']['categories']) || !empty($post['frontMatter']['tags'])): ?>
    <div class="post-meta-footer">
        <?php if (!empty($post['frontMatter']['categories'])): ?>
            <span class="post-meta-item">分类:
                <?php foreach ($post['frontMatter']['categories'] as $category): ?>
                    <span><?= htmlspecialchars($category) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
        <?php if (!empty($post['frontMatter']['tags'])): ?>
            <span class="post-meta-item">标签:
                <?php foreach ($post['frontMatter']['tags'] as $tag): ?>
                    <span>#<?= htmlspecialchars($tag) ?></span>
                <?php endforeach; ?>
            </span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</article>

<?php
/*
 * 精选推荐占位容器：清单在 random-recommend/script.js 的 FEATURED 数组里。
 * hidden 保证无 JS 或数据缺失时不留空白；脚本以本容器的存在作为「仅文章页启用」的开关。
 * 外观复用 .markdown-alert-tip，配色见 site.css。
 */
?>
<section class="post-recommend markdown-alert markdown-alert-tip" data-featured-recommend dir="auto" hidden aria-labelledby="featuredRecommendTitle">
    <p class="markdown-alert-title" id="featuredRecommendTitle">
        <svg class="octicon octicon-light-bulb mr-2" viewBox="0 0 16 16" width="16" height="16" aria-hidden="true"><path d="M8 1.5c-2.363 0-4 1.69-4 3.75 0 .984.424 1.625.984 2.304l.214.253c.223.264.47.556.673.848.284.411.537.896.621 1.49a.75.75 0 0 1-1.484.211c-.04-.282-.163-.547-.37-.847a8.456 8.456 0 0 0-.542-.68c-.084-.1-.173-.205-.268-.32C3.201 7.75 2.5 6.766 2.5 5.25 2.5 2.31 4.863 0 8 0s5.5 2.31 5.5 5.25c0 1.516-.701 2.5-1.328 3.259-.095.115-.184.22-.268.319-.207.245-.383.453-.541.681-.208.3-.33.565-.37.847a.751.751 0 0 1-1.485-.212c.084-.593.337-1.078.621-1.489.203-.292.45-.584.673-.848.075-.088.147-.173.213-.253.561-.679.985-1.32.985-2.304 0-2.06-1.637-3.75-4-3.75ZM5.75 12h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5ZM6 15.25a.75.75 0 0 1 .75-.75h2.5a.75.75 0 0 1 0 1.5h-2.5a.75.75 0 0 1-.75-.75Z"></path></svg>推荐阅读
    </p>
    <div class="post-recommend__slot" data-featured-recommend-slot></div>
</section>

<?php
$giscus = $site['giscus'] ?? null;
$giscusEnabled = is_array($giscus) && !empty($giscus['enabled']);
?>

<?php if ($giscusEnabled): ?>
<?php
/*
 * 评论区只输出容器与配置，不直接输出 giscus 的 script 标签。
 * giscus 的主题是在创建 iframe 时由 client.js 读取 data-theme 拼进 widget URL 决定的，
 * 而站点主题只有前端才知道（存在 localStorage 里），构建期无法确定。
 * 因此真正的 script 由 assets/features/giscus-theme/script.js 按访客当前主题注入，
 * 保证评论区第一帧就与正文一致，不依赖事后 postMessage 纠正。
 */
?>
<section class="post-comments" aria-label="Comments"
    data-giscus
    data-theme-mode="<?= htmlspecialchars($giscus['theme'] ?? 'auto') ?>"
    data-theme-light="<?= htmlspecialchars($giscus['theme_light'] ?? 'light') ?>"
    data-theme-dark="<?= htmlspecialchars($giscus['theme_dark'] ?? 'dark_dimmed') ?>"
    data-repo="<?= htmlspecialchars($giscus['repo'] ?? '') ?>"
    data-repo-id="<?= htmlspecialchars($giscus['repo_id'] ?? '') ?>"
    data-category="<?= htmlspecialchars($giscus['category'] ?? '') ?>"
    data-category-id="<?= htmlspecialchars($giscus['category_id'] ?? '') ?>"
    data-mapping="<?= htmlspecialchars($giscus['mapping'] ?? 'pathname') ?>"
    data-strict="<?= htmlspecialchars($giscus['strict'] ?? '0') ?>"
    data-reactions-enabled="<?= htmlspecialchars($giscus['reactions_enabled'] ?? '1') ?>"
    data-emit-metadata="<?= htmlspecialchars($giscus['emit_metadata'] ?? '0') ?>"
    data-input-position="<?= htmlspecialchars($giscus['input_position'] ?? 'bottom') ?>"
    data-lang="<?= htmlspecialchars($giscus['lang'] ?? 'zh-CN') ?>">
    <div class="giscus"></div>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();

$siteUrl = rtrim($site['url'] ?? '', '/');
$pageCanonical = $siteUrl . $post['frontMatter']['permalink'];

$customDescription = trim((string) ($post['frontMatter']['description'] ?? ''));
if ($customDescription !== '') {
    $pageDescription = $customDescription;
} else {
    $plain = trim(strip_tags($post['html']));
    $plain = preg_replace('/\s+/', ' ', $plain);
    $pageDescription = mb_substr($plain, 0, 160);
    if (mb_strlen($plain) > 160) {
        $pageDescription .= '…';
    }
}

$ogType = 'article';
$ogTitle = $post['frontMatter']['title'];

if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $post['html'], $m)) {
    $src = $m[1];
    if ($src !== '' && $src[0] === '/') {
        $ogImage = $siteUrl . $src;
    } elseif ($src !== '' && !preg_match('/^https?:\/\//', $src)) {
        $ogImage = $siteUrl . '/' . $src;
    } elseif ($src !== '') {
        $ogImage = $src;
    }
}

$showSidebar = $post['frontMatter']['sidebar'] ?? true;
if ($showSidebar) {
    ob_start();
    ?>
    <aside class="post-toc" aria-label="Table of contents">
        <div class="toc-title">目录</div>
        <nav class="toc-list"></nav>
    </aside>
    <?php
    $sidebar = ob_get_clean();
}
include 'layout.php';
?>
